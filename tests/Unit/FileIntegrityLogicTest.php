<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      FileIntegrityLogicTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/file_integrity.functions.php';

class FileIntegrityLogicTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'teampass-file-integrity-test-' . bin2hex(random_bytes(8));
        foreach (array('app', 'public', 'storage/logs', 'storage/files', 'secrets') as $directory) {
            self::assertTrue(mkdir($this->root . DIRECTORY_SEPARATOR . $directory, 0770, true));
        }
    }

    protected function tearDown(): void
    {
        $temporaryRoot = realpath(sys_get_temp_dir());
        $testRoot = realpath($this->root);
        if (
            $temporaryRoot === false
            || $testRoot === false
            || str_starts_with($testRoot, $temporaryRoot . DIRECTORY_SEPARATOR . 'teampass-file-integrity-test-') === false
        ) {
            return;
        }
        $this->removeTree($testRoot);
    }

    public function testScanSeparatesModifiedMissingAndExcludedRuntimeFiles(): void
    {
        $this->write('app/good.php', 'known-good');
        $this->write('public/changed.js', 'changed');
        $this->write('storage/files/runtime.php', 'instance data');
        $this->write('secrets/instance-key.txt', 'secret');
        $this->manifest(array(
            'app/good.php' => md5('known-good'),
            'public/changed.js' => md5('original'),
            'public/missing.css' => md5('missing'),
        ));

        $report = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );

        self::assertSame(1, $report['counts']['modified']);
        self::assertSame(1, $report['counts']['missing']);
        self::assertSame(0, $report['counts']['unknown']);
        self::assertSame('public/changed.js', $report['issues']['modified'][0]['path']);
        self::assertSame('public/missing.css', $report['issues']['missing'][0]['path']);
        self::assertSame('danger', $report['status']);
        self::assertStringNotContainsString('storage', json_encode($report['issues']));
        self::assertStringNotContainsString('instance-key', json_encode($report['issues']));
    }

    public function testScanClassifiesLegacyDevelopmentAndCriticalUnknownFiles(): void
    {
        $composerLock = json_encode(array(
            'packages-dev' => array(
                array('name' => 'phpunit/phpunit', 'bin' => array('phpunit')),
            ),
        ), JSON_UNESCAPED_SLASHES);
        self::assertIsString($composerLock);
        $this->write('composer.lock', $composerLock);
        $this->write('app/vendor/phpunit/phpunit/Test.php', 'dev');
        $this->write('app/vendor/bin/phpunit', 'dev-bin');
        $this->write('pages/legacy.php', 'legacy');
        $this->write('rogue-root.php', 'rogue');
        $this->write('files/legacy-runtime.bin', 'runtime');
        $this->manifest(array('composer.lock' => md5($composerLock)));

        $report = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );

        self::assertSame(2, $report['counts']['development']);
        self::assertSame(1, $report['counts']['legacy']);
        self::assertSame(1, $report['counts']['unknown']);
        self::assertSame(1, $report['counts']['critical']);
        self::assertTrue($report['issues']['unknown'][0]['critical']);
        self::assertSame('rogue-root.php', $report['issues']['unknown'][0]['path']);
        self::assertSame('pages', $report['issues']['legacy'][0]['root']);
        self::assertStringNotContainsString('legacy-runtime.bin', json_encode($report['issues']));
    }

    public function testRepositoryArtifactsAreNeutralEvenWhenManifestHashesDiffer(): void
    {
        $this->write('app/known.php', 'known');
        $this->write('.claude/commands/review.md', 'locally changed');
        $this->write('.github/workflows/tests.yml', 'locally changed');
        $this->write('tests/Unit/LocalTest.php', '<?php');
        $this->write('docs/local-notes.md', 'notes');
        $this->write('AGENTS.md', 'local instructions');
        $this->write('.eslintrc', 'local lint configuration');
        $this->manifest(array(
            'app/known.php' => md5('known'),
            '.claude/commands/review.md' => md5('release version'),
            '.github/workflows/tests.yml' => md5('release version'),
            'tests/Unit/MissingTest.php' => md5('missing'),
        ));

        $report = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );

        self::assertSame(1, $report['counts']['checked']);
        self::assertSame(3, $report['counts']['excluded_reference']);
        self::assertSame(0, $report['counts']['modified']);
        self::assertSame(0, $report['counts']['missing']);
        self::assertSame(0, $report['counts']['unknown']);
        self::assertSame('success', $report['status']);
    }

    public function testSensitiveHiddenFilesRemainCriticalUnknownFiles(): void
    {
        $this->write('app/known.php', 'known');
        $this->write('public/.htaccess', 'configuration');
        $this->write('public/.user.ini', 'configuration');
        $this->write('.env', 'SECRET=value');
        $this->write('.env.local', 'SECRET=value');
        $this->manifest(array('app/known.php' => md5('known')));

        $report = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );

        self::assertFalse(tpFileScopeIsRepositoryArtifact('.htaccess'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('.user.ini'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('.env'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('composer.json'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('composer.lock'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('Dockerfile'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('docker/start.sh'));
        self::assertFalse(tpFileScopeIsRepositoryArtifact('app/scripts/task.php'));
        self::assertSame(4, $report['counts']['unknown']);
        self::assertSame(4, $report['counts']['critical']);
        foreach ($report['issues']['unknown'] as $issue) {
            self::assertTrue($issue['critical']);
        }
    }

    public function testReleaseGeneratorUsesTheSharedRepositoryArtifactPolicy(): void
    {
        $generator = file_get_contents(
            __DIR__ . '/../../.claude/skills/prepare-release/scripts/regenerate_checksums.sh'
        );

        self::assertIsString($generator);
        self::assertStringContainsString(
            'require "app/sources/file_scope.functions.php";',
            $generator
        );
        self::assertStringContainsString('tpFileScopeIsRepositoryArtifact($path)', $generator);
    }

    public function testWritableAvatarDirectoryIgnoresImagesButFlagsExecutableFiles(): void
    {
        $this->write('app/known.php', 'known');
        $this->write('public/assets/avatars/user.png', 'image');
        $this->write('public/assets/avatars/shell.php', 'executable');
        $this->manifest(array('app/known.php' => md5('known')));

        $report = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );

        self::assertSame(1, $report['counts']['unknown']);
        self::assertSame(1, $report['counts']['critical']);
        self::assertSame('public/assets/avatars/shell.php', $report['issues']['unknown'][0]['path']);
    }

    public function testRemovedInstallerIsAllowedButAnIncompletePresentInstallerIsNot(): void
    {
        $this->write('app/known.php', 'known');
        $this->manifest(array(
            'app/known.php' => md5('known'),
            'public/install/setup.php' => md5('installer'),
        ));

        $withoutInstaller = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );
        self::assertSame(0, $withoutInstaller['counts']['missing']);
        self::assertSame(1, $withoutInstaller['counts']['excluded_reference']);

        self::assertTrue(mkdir($this->root . '/public/install', 0770, true));
        $withIncompleteInstaller = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );
        self::assertSame(1, $withIncompleteInstaller['counts']['missing']);
        self::assertSame('public/install/setup.php', $withIncompleteInstaller['issues']['missing'][0]['path']);
    }

    public function testExpectedInternalSymbolicLinkIsHashedWithoutFalsePositive(): void
    {
        $this->write('app/language.txt', 'translation');
        $linkPath = $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'language.txt';
        if (@symlink('../app/language.txt', $linkPath) === false) {
            self::markTestSkipped('Symbolic links are not available in this test environment.');
        }
        $hash = md5('translation');
        $this->manifest(array(
            'app/language.txt' => $hash,
            'public/language.txt' => $hash,
        ));

        $report = tpFileIntegrityScan(
            $this->root,
            $this->root . '/app/files_reference.txt',
            false,
            false
        );

        self::assertSame(0, $report['counts']['modified']);
        self::assertSame(0, $report['counts']['missing']);
        self::assertSame(0, $report['counts']['unknown']);
        self::assertSame('success', $report['status']);
    }

    public function testManifestParserRejectsUnsafeAndDuplicateEntries(): void
    {
        $hash = md5('known');
        $sha256 = hash('sha256', 'known-sha256');
        $this->write(
            'app/files_reference.txt',
            "../outside.php {$hash}\napp/known.php {$hash}\napp/known.php {$hash}\napp/sha.php {$sha256}\ninvalid\n"
        );

        $parsed = tpFileIntegrityParseReference($this->root . '/app/files_reference.txt');

        self::assertSame(array('app/known.php', 'app/sha.php'), array_keys($parsed['files']));
        self::assertCount(3, $parsed['warnings']);
        self::assertSame('md5', $parsed['files']['app/known.php']['algorithm']);
        self::assertSame('sha256', $parsed['files']['app/sha.php']['algorithm']);
    }

    public function testReportsArePersistedAndIssuesAreSafelyPaginated(): void
    {
        $this->write('app/known.php', 'known');
        $this->manifest(array('app/known.php' => md5('known')));
        $report = tpFileIntegrityDefaultReport();
        $report['has_result'] = true;
        $report['scan_id'] = 'scan-test';
        $report['status'] = 'success';
        $report['reference_hash'] = str_repeat('0', 64);
        $report['issues']['modified'] = array(array('path' => 'app/a.php'));
        $report['issues']['warnings'] = array(array('path' => 'manifest:1', 'message' => 'warning'));

        tpFileIntegritySaveReport($this->root, $report);
        $loaded = tpFileIntegrityLoadReport($this->root);
        $summary = tpFileIntegrityLoadSummary($this->root);
        $page = tpFileIntegrityIssuePage($loaded, 'all', -10, 500);

        self::assertSame('scan-test', $loaded['scan_id']);
        self::assertSame('scan-test', $summary['scan_id']);
        self::assertArrayNotHasKey('issues', $summary);
        self::assertTrue($loaded['stale']);
        self::assertTrue($summary['stale']);
        self::assertSame('stale', $loaded['status']);
        self::assertSame(0, $page['offset']);
        self::assertSame(200, $page['limit']);
        self::assertSame(2, $page['total']);
        self::assertSame('modified', $page['items'][0]['category']);
        self::assertSame('warnings', $page['items'][1]['category']);
    }

    public function testDashboardSummaryStaysSmallAndRejectsMismatchedDetails(): void
    {
        $this->write('app/known.php', 'known');
        $this->manifest(array('app/known.php' => md5('known')));
        $report = tpFileIntegrityDefaultReport();
        $report['has_result'] = true;
        $report['scan_id'] = 'large-scan';
        $report['status'] = 'warning';
        $report['reference_hash'] = hash_file('sha256', $this->root . '/app/files_reference.txt');
        $report['counts']['unknown'] = 5000;
        $report['counts']['total_issues'] = 5000;
        for ($index = 0; $index < 5000; $index++) {
            $report['issues']['unknown'][] = array(
                'path' => 'public/unknown/unknown-' . strval($index) . '.txt',
                'critical' => false,
                'link' => false,
            );
        }

        tpFileIntegritySaveReport($this->root, $report);

        $summaryPath = tpFileIntegritySummaryPath($this->root);
        $detailPath = tpFileIntegrityReportPath($this->root);
        self::assertLessThan(10000, filesize($summaryPath));
        self::assertGreaterThan(filesize($summaryPath) * 20, filesize($detailPath));
        self::assertSame('large-scan', tpFileIntegrityLoadSummary($this->root)['scan_id']);

        $mismatched = tpFileIntegrityLoadReport($this->root, 'another-scan');
        self::assertFalse($mismatched['has_result']);
        self::assertTrue($mismatched['report_invalid']);
        self::assertSame('error', $mismatched['status']);
    }

    public function testCleanupPlanNeverTargetsRuntimeAndProtectsMixedLegacyDirectories(): void
    {
        $this->write('scripts/current.php', 'current');
        $this->write('scripts/legacy.php', 'legacy');
        $this->manifest(array('scripts/current.php' => md5('current')));
        $report = tpFileIntegrityDefaultReport();
        $report['scan_id'] = 'scan-test';
        $report['reference_hash'] = hash_file('sha256', $this->root . '/app/files_reference.txt');
        $report['issues']['legacy'] = array(
            array('path' => 'scripts/legacy.php', 'root' => 'scripts'),
        );

        $plan = implode("\n", tpFileIntegrityCleanupPlan($this->root, $report));
        $webCommands = tpFileIntegrityCleanupCommands($this->root, $report);

        self::assertStringContainsString('Review legacy entries', $plan);
        self::assertStringNotContainsString('mv -- ', $plan);
        self::assertSame(array(), $webCommands);
        self::assertStringNotContainsString(DIRECTORY_SEPARATOR . 'storage', $plan);
        self::assertStringNotContainsString(DIRECTORY_SEPARATOR . 'files', $plan);
        self::assertStringNotContainsString(DIRECTORY_SEPARATOR . 'upload', $plan);
        self::assertStringNotContainsString(DIRECTORY_SEPARATOR . 'backups', $plan);
    }

    public function testCleanupCommandsQuarantineOnlyUnambiguouslyLegacyDirectories(): void
    {
        $this->write('app/known.php', 'known');
        $this->write('pages/legacy.php', 'legacy');
        $this->manifest(array('app/known.php' => md5('known')));
        $report = tpFileIntegrityDefaultReport();
        $report['scan_id'] = 'scan-test';
        $report['reference_hash'] = hash_file('sha256', $this->root . '/app/files_reference.txt');
        $report['issues']['legacy'] = array(
            array('path' => 'pages/legacy.php', 'root' => 'pages'),
        );

        $commands = tpFileIntegrityCleanupCommands($this->root, $report);

        self::assertCount(2, $commands);
        self::assertStringStartsWith('sudo install -d -m 0700 -- ', $commands[0]);
        self::assertStringStartsWith('sudo mv -- ', $commands[1]);
        self::assertStringNotContainsString('rm ', implode("\n", $commands));
    }

    public function testDevelopmentCleanupUsesBundledOfflineCliInsteadOfComposer(): void
    {
        $this->write('app/known.php', 'known');
        $this->manifest(array('app/known.php' => md5('known')));
        $report = tpFileIntegrityDefaultReport();
        $report['reference_hash'] = hash_file('sha256', $this->root . '/app/files_reference.txt');
        $report['counts']['development'] = 1;

        $plan = implode("\n", tpFileIntegrityCleanupCommands($this->root, $report));

        self::assertStringContainsString('sudo php ', $plan);
        self::assertStringContainsString('cleanup_dev_dependencies.php', $plan);
        self::assertStringNotContainsString('composer install', $plan);
        self::assertStringNotContainsString('optimize-autoloader', $plan);
    }

    /** @param array<string,string> $entries */
    private function manifest(array $entries): void
    {
        $lines = array();
        foreach ($entries as $path => $hash) {
            $lines[] = $path . ' ' . $hash;
        }
        $this->write('app/files_reference.txt', implode("\n", $lines) . "\n");
    }

    private function write(string $relativePath, string $contents): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($path);
        if (is_dir($directory) === false) {
            self::assertTrue(mkdir($directory, 0770, true));
        }
        self::assertNotFalse(file_put_contents($path, $contents));
    }

    private function removeTree(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && $entry->isLink() === false) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($directory);
    }
}
