<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      FilePermissionsLogicTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/file_permissions.functions.php';

class FilePermissionsLogicTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'teampass-file-permissions-test-' . bin2hex(random_bytes(8));
        foreach (['app', 'public/assets/avatars', 'storage/logs', 'storage/files', 'secrets', 'app/includes/libraries/csrfp/log'] as $directory) {
            self::assertTrue(mkdir($this->root . DIRECTORY_SEPARATOR . $directory, 0770, true));
        }
        $resolvedRoot = realpath($this->root);
        self::assertNotFalse($resolvedRoot);
        $this->root = $resolvedRoot;
        self::assertNotFalse(file_put_contents($this->root . '/app/known.php', '<?php'));
    }

    protected function tearDown(): void
    {
        $testRoot = realpath($this->root);
        $temporaryRoot = realpath(sys_get_temp_dir());
        if (
            $testRoot === false
            || $temporaryRoot === false
            || str_starts_with($testRoot, $temporaryRoot . DIRECTORY_SEPARATOR . 'teampass-file-permissions-test-') === false
        ) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && $entry->isLink() === false) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($testRoot);
    }

    public function testCommonDistributionFamiliesAreDetectedWithoutGuessingExoticOnes(): void
    {
        $ubuntu = tpFilePermissionsDetectPlatform(
            "ID=ubuntu\nNAME=\"Ubuntu\"\nVERSION_ID=\"26.04\"\nID_LIKE=debian\n",
            'Linux'
        );
        $rocky = tpFilePermissionsDetectPlatform(
            "ID=rocky\nNAME=\"Rocky Linux\"\nVERSION_ID=\"10\"\nID_LIKE=\"rhel centos fedora\"\n",
            'Linux'
        );
        $alpine = tpFilePermissionsDetectPlatform(
            "ID=alpine\nNAME=\"Alpine Linux\"\nVERSION_ID=\"3.23\"\n",
            'Linux'
        );

        self::assertSame('debian', $ubuntu['family']);
        self::assertTrue($ubuntu['remediation_supported']);
        self::assertSame('rhel', $rocky['family']);
        self::assertTrue($rocky['remediation_supported']);
        self::assertSame('unsupported', $alpine['family']);
        self::assertTrue($alpine['scan_supported']);
        self::assertFalse($alpine['remediation_supported']);
    }

    public function testNonLinuxPlatformsAreExplicitlyUnsupported(): void
    {
        $platform = tpFilePermissionsDetectPlatform('', 'Windows');

        self::assertFalse($platform['scan_supported']);
        self::assertFalse($platform['remediation_supported']);
    }

    public function testPermissionScanChecksDirectoriesAndFilesAndFlagsWorldWrite(): void
    {
        if (PHP_OS_FAMILY !== 'Linux' || function_exists('posix_geteuid') === false) {
            self::markTestSkipped('POSIX permission semantics are required.');
        }
        self::assertTrue(chmod($this->root . '/app/known.php', 0666));
        self::assertTrue(mkdir($this->root . '/.github/workflows', 0770, true));
        self::assertNotFalse(file_put_contents($this->root . '/.github/workflows/tests.yml', 'workflow'));
        self::assertTrue(chmod($this->root . '/.github/workflows/tests.yml', 0666));
        $uid = posix_geteuid();
        $gid = posix_getegid();
        $identity = [
            'web_user' => 'test-web',
            'web_group' => 'test-web',
            'source' => 'test',
            'uid' => $uid,
            'gids' => [$gid],
        ];
        $platform = tpFilePermissionsDetectPlatform("ID=ubuntu\nID_LIKE=debian\n", 'Linux');

        $report = tpFilePermissionsScan($this->root, $platform, $identity);
        $worldWritable = array_values(array_filter(
            $report['issues'],
            static function (array $issue): bool {
                return $issue['path'] === 'app/known.php' && $issue['reason'] === 'world_writable';
            }
        ));
        $repositoryIssues = array_values(array_filter(
            $report['issues'],
            static function (array $issue): bool {
                return str_starts_with($issue['path'], '.github');
            }
        ));

        self::assertGreaterThan(1, $report['counts']['checked']);
        self::assertCount(1, $worldWritable);
        self::assertCount(0, $repositoryIssues);
        self::assertSame('danger', $report['status']);
    }

    public function testRemediationUsesSudoAndDistributionSpecificWebIdentity(): void
    {
        $report = tpFilePermissionsDefaultReport();
        $report['remediation_supported'] = true;
        $report['counts']['issues'] = 2;
        $report['identity']['web_user'] = 'www-data';
        $report['identity']['web_group'] = 'www-data';
        $report['platform']['family'] = 'debian';

        $commands = tpFilePermissionsRemediationCommands($this->root, $report);
        $joined = implode("\n", $commands);

        self::assertNotEmpty($commands);
        self::assertStringContainsString('sudo ', $joined);
        self::assertStringContainsString('www-data', $joined);
        self::assertStringContainsString('chmod 0750', $joined);
        self::assertStringContainsString('-type f -exec chmod u=rwX,g=rX,o=', $joined);
        self::assertStringNotContainsString('semanage', $joined);

        $report['identity']['web_user'] = 'apache';
        $report['identity']['web_group'] = 'apache';
        $report['platform']['family'] = 'rhel';
        $rhelCommands = implode("\n", tpFilePermissionsRemediationCommands($this->root, $report));
        self::assertStringContainsString('apache', $rhelCommands);
        self::assertStringContainsString('httpd_sys_rw_content_t', $rhelCommands);
        self::assertStringContainsString('restorecon', $rhelCommands);
    }

    public function testProtectedRemediationExcludesRepositoryArtifactsButKeepsSensitiveDotfiles(): void
    {
        foreach (['.github/workflows', '.claude/commands', 'tests/Unit', 'docs/install'] as $directory) {
            self::assertTrue(mkdir($this->root . DIRECTORY_SEPARATOR . $directory, 0770, true));
        }
        self::assertNotFalse(file_put_contents($this->root . '/.github/workflows/tests.yml', 'workflow'));
        self::assertNotFalse(file_put_contents($this->root . '/AGENTS.md', 'instructions'));
        self::assertNotFalse(file_put_contents($this->root . '/.htaccess', 'configuration'));
        self::assertNotFalse(file_put_contents($this->root . '/.env', 'SECRET=value'));

        $relativePaths = array_map(
            static function (string $path): string {
                return basename($path);
            },
            tpFilePermissionsProtectedTopLevelPaths($this->root)
        );

        self::assertNotContains('.github', $relativePaths);
        self::assertNotContains('.claude', $relativePaths);
        self::assertNotContains('tests', $relativePaths);
        self::assertNotContains('docs', $relativePaths);
        self::assertNotContains('AGENTS.md', $relativePaths);
        self::assertContains('.htaccess', $relativePaths);
        self::assertContains('.env', $relativePaths);
        self::assertContains('app', $relativePaths);
        self::assertContains('public', $relativePaths);
    }

    public function testEveryRuntimeRootRemainsAnExplicitTraversalStartingPoint(): void
    {
        foreach (['storage/upload', 'storage/backups', 'app/websocket/logs'] as $directory) {
            self::assertTrue(mkdir($this->root . '/' . $directory, 0750, true));
        }
        $commands = tpFilePermissionsRemediationCommands($this->root, $this->repairReport());
        $storageArgument = escapeshellarg($this->root . DIRECTORY_SEPARATOR . 'storage');
        $runtimeFileCommands = array_values(array_filter(
            $commands,
            static fn (string $command): bool => str_contains($command, '-type f -exec chmod 0640')
                && str_contains($command, $storageArgument)
        ));

        self::assertCount(1, $runtimeFileCommands);
        foreach (tpFilePermissionsRuntimeRules() as $rule) {
            $path = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rule['path']);
            self::assertStringContainsString(escapeshellarg($path), $runtimeFileCommands[0]);
        }
    }

    public function testOwnershipAndModeRepairsUseTheSameNonFollowingFilesystemBoundary(): void
    {
        $commands = tpFilePermissionsRemediationCommands($this->root, $this->repairReport());
        self::assertStringNotContainsString('chown -R', implode("\n", $commands));
        $findCommands = array_values(array_filter(
            $commands,
            static fn (string $command): bool => str_starts_with($command, 'sudo find ')
        ));
        self::assertNotEmpty($findCommands);
        foreach ($findCommands as $command) {
            self::assertStringStartsWith('sudo find -P ', $command);
            self::assertStringContainsString(' -xdev ', $command);
            self::assertStringContainsString(' -prune -o ', $command);
        }
    }

    public function testGeneratedFindPrunesVendoredMetadataButKeepsRuntimeDotfiles(): void
    {
        foreach (['app/vendor/package/.github/workflows', 'app/includes/.externals'] as $directory) {
            self::assertTrue(mkdir($this->root . '/' . $directory, 0750, true));
        }
        $excluded = ['app/vendor/package/.github/workflows/ci.yml', 'app/vendor/package/.gitignore'];
        $included = ['app/vendor/package/.htaccess', 'app/.env', 'app/includes/.externals/library.php'];
        foreach (array_merge($excluded, $included) as $path) {
            self::assertNotFalse(file_put_contents($this->root . '/' . $path, 'fixture'));
        }
        $commands = tpFilePermissionsRemediationCommands($this->root, $this->repairReport());
        $ownershipCommands = array_values(array_filter(
            $commands,
            static fn (string $command): bool => str_contains($command, ' -exec chown -h -- ')
        ));
        self::assertNotEmpty($ownershipCommands);
        $command = $ownershipCommands[0];
        $actionOffset = strpos($command, ' -exec chown -h -- ');
        self::assertNotFalse($actionOffset);
        // Execute only the generated selection, never sudo, chown or chmod.
        $selection = substr($command, strlen('sudo '), $actionOffset - strlen('sudo ')) . ' -print';
        $selection = str_replace($this->root, str_replace('\\', '/', $this->root), $selection);
        $bash = PHP_OS_FAMILY === 'Windows' ? 'C:/Program Files/Git/bin/bash.exe' : '/bin/bash';
        if (is_file($bash) === false || function_exists('proc_open') === false) {
            self::markTestSkipped('Bash is required to execute the generated find selection.');
        }
        $process = proc_open([$bash, '--noprofile', '--norc', '-s'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], $selection . "\n");
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $error);
        $selected = array_map('trim', preg_split('/\R/', str_replace('\\', '/', (string) $output)) ?: []);
        $normalizedRoot = str_replace('\\', '/', $this->root);
        foreach ($excluded as $path) {
            self::assertNotContains($normalizedRoot . '/' . $path, $selected);
        }
        foreach ($included as $path) {
            self::assertContains($normalizedRoot . '/' . $path, $selected);
        }
    }

    public function testSymlinkedRuntimeRootsAreLeftForManualReview(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            self::markTestSkipped('Linux symlink semantics are required.');
        }
        self::assertTrue(rmdir($this->root . '/storage/files'));
        self::assertTrue(rmdir($this->root . '/storage/logs'));
        self::assertTrue(rmdir($this->root . '/storage'));
        self::assertTrue(mkdir($this->root . '/docs/external-storage/logs', 0750, true));
        self::assertTrue(mkdir($this->root . '/docs/external-storage/files', 0750));
        self::assertTrue(symlink($this->root . '/docs/external-storage', $this->root . '/storage'));
        $commands = tpFilePermissionsRemediationCommands($this->root, $this->repairReport());

        self::assertStringNotContainsString($this->root . '/storage', implode("\n", $commands));
        self::assertStringNotContainsString($this->root . '/docs', implode("\n", $commands));
    }

    /**
     * Return a supported report requiring a permission repair, without scanning.
     *
     * @return array<string,mixed>
     */
    private function repairReport(): array
    {
        $report = tpFilePermissionsDefaultReport();
        $report['remediation_supported'] = true;
        $report['counts']['issues'] = 1;
        $report['identity']['web_user'] = 'www-data';
        $report['identity']['web_group'] = 'www-data';
        $report['platform']['family'] = 'debian';

        return $report;
    }

    public function testPermissionFindingsAreAggregatedWithBoundedSamples(): void
    {
        $report = tpFilePermissionsDefaultReport();
        for ($index = 0; $index < 100; $index++) {
            tpFilePermissionsAddIssue(
                $report,
                'app/generated/file-' . strval($index) . '.php',
                'protected_writable',
                'warning',
                '0664',
                'read-only-for-web'
            );
        }

        self::assertSame(100, $report['counts']['issues']);
        self::assertSame(100, $report['counts']['protected_writable']);
        self::assertCount(1, $report['issues']);
        $group = array_values($report['issues'])[0];
        self::assertSame('app', $group['path']);
        self::assertSame(100, $group['affected_count']);
        self::assertCount(5, $group['samples']);
    }

    public function testStandardScanSkipsHighVolumeRuntimeContentsButDeepScanIncludesThem(): void
    {
        self::assertTrue(mkdir($this->root . '/storage/upload', 0770, true));
        for ($index = 0; $index < 25; $index++) {
            self::assertNotFalse(file_put_contents(
                $this->root . '/storage/upload/attachment-' . strval($index) . '.bin',
                'encrypted'
            ));
        }
        $platform = tpFilePermissionsDetectPlatform("ID=ubuntu\nID_LIKE=debian\n", 'Linux');
        $identity = [
            'web_user' => 'test-web',
            'web_group' => 'test-web',
            'source' => 'test',
            'uid' => null,
            'gids' => [],
        ];

        $standard = tpFilePermissionsScan($this->root, $platform, $identity);
        $deep = tpFilePermissionsScan($this->root, $platform, $identity, true);

        self::assertSame('standard', $standard['scope']['mode']);
        self::assertContains('storage/upload', $standard['scope']['skipped_descendants']);
        self::assertSame('deep', $deep['scope']['mode']);
        self::assertSame([], $deep['scope']['skipped_descendants']);
        self::assertSame(25, $deep['counts']['checked'] - $standard['counts']['checked']);
    }
}
