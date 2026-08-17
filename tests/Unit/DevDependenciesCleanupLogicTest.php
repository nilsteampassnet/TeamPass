<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/scripts/dev_dependencies_cleanup_logic.php';

/**
 * Tests for the removal of the development dependencies from an installation
 * (upgrade_run_3.2.1.php).
 *
 * Two things must hold. The lists have to be derived from composer.lock so they follow
 * the dependencies instead of being hardcoded, and the deletion must never be able to
 * reach outside app/vendor — this step deletes files on a production server, so a wrong
 * path is not a cosmetic bug.
 */
class DevDependenciesCleanupLogicTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/tp-devdeps-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0o700, true);
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && is_dir($this->tmpDir) === true) {
            exec('rm -rf ' . escapeshellarg($this->tmpDir));
        }
    }

    // --- Derivation from composer.lock -------------------------------------------

    public function testExtractsDevPackageNamesAndBinaries(): void
    {
        $lock = json_encode([
            'packages' => [
                ['name' => 'symfony/http-foundation'],
            ],
            'packages-dev' => [
                ['name' => 'phpunit/phpunit', 'bin' => ['phpunit']],
                ['name' => 'phpstan/phpstan', 'bin' => ['phpstan', 'phpstan.phar']],
                ['name' => 'sebastian/diff'],
            ],
        ]);

        $result = devDependenciesFromLock((string) $lock);

        self::assertSame(
            ['phpunit/phpunit', 'phpstan/phpstan', 'sebastian/diff'],
            $result['packages']
        );
        // Each launcher comes with its Windows companion.
        self::assertSame(
            ['phpunit', 'phpunit.bat', 'phpstan', 'phpstan.bat', 'phpstan.phar', 'phpstan.phar.bat'],
            $result['binaries']
        );
    }

    public function testBinaryDeclaredWithAPathIsReducedToItsBaseName(): void
    {
        // nikic/php-parser declares "bin/php-parse", but Composer installs the launcher
        // as app/vendor/bin/php-parse.
        $lock = json_encode([
            'packages' => [],
            'packages-dev' => [
                ['name' => 'nikic/php-parser', 'bin' => ['bin/php-parse']],
            ],
        ]);

        $result = devDependenciesFromLock((string) $lock);

        self::assertSame(['php-parse', 'php-parse.bat'], $result['binaries']);
    }

    public function testPackageAlsoPresentInProductionIsNeverRemoved(): void
    {
        // A malformed lock must not be able to make the cleanup delete a package the
        // application loads at runtime.
        $lock = json_encode([
            'packages' => [
                ['name' => 'guzzlehttp/guzzle'],
            ],
            'packages-dev' => [
                ['name' => 'guzzlehttp/guzzle'],
                ['name' => 'phpunit/phpunit'],
            ],
        ]);

        $result = devDependenciesFromLock((string) $lock);

        self::assertSame(['phpunit/phpunit'], $result['packages']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafePackageNameProvider(): array
    {
        return [
            'parent traversal'       => ['../../etc'],
            'absolute path'          => ['/etc/passwd'],
            'dot segment'            => ['vendor/..'],
            'leading dot'            => ['.hidden/package'],
            'no namespace'           => ['phpunit'],
            'too many segments'      => ['a/b/c'],
            'empty'                  => [''],
        ];
    }

    /**
     * @dataProvider unsafePackageNameProvider
     */
    public function testUnsafePackageNamesAreRejected(string $name): void
    {
        self::assertFalse(devDependencyPackageNameIsSafe($name));

        $lock = json_encode(['packages' => [], 'packages-dev' => [['name' => $name]]]);
        self::assertSame([], devDependenciesFromLock((string) $lock)['packages']);
    }

    public function testValidPackageNameIsAccepted(): void
    {
        self::assertTrue(devDependencyPackageNameIsSafe('phpunit/php-code-coverage'));
        self::assertTrue(devDependencyPackageNameIsSafe('phpstan/phpstan'));
    }

    public function testMalformedOrEmptyLockYieldsNothing(): void
    {
        foreach (['', 'not json', '[]', '{}', '{"packages-dev": "nope"}'] as $contents) {
            $result = devDependenciesFromLock($contents);
            self::assertSame([], $result['packages'], 'for: ' . $contents);
            self::assertSame([], $result['binaries'], 'for: ' . $contents);
        }
    }

    // --- Deletion ----------------------------------------------------------------

    public function testRemovesADirectoryTree(): void
    {
        $base = $this->tmpDir . '/vendor';
        mkdir($base . '/phpunit/phpunit/src', 0o700, true);
        file_put_contents($base . '/phpunit/phpunit/src/Foo.php', '<?php');
        file_put_contents($base . '/phpunit/phpunit/README.md', 'readme');

        self::assertTrue(devDependencyRemovePath($base, $base . '/phpunit/phpunit'));
        self::assertDirectoryDoesNotExist($base . '/phpunit/phpunit');
        // Only the package was targeted; its namespace directory is a separate decision.
        self::assertDirectoryExists($base . '/phpunit');
    }

    public function testMissingPathCountsAsRemoved(): void
    {
        $base = $this->tmpDir . '/vendor';
        mkdir($base, 0o700, true);

        self::assertTrue(devDependencyRemovePath($base, $base . '/never/existed'));
    }

    public function testRefusesToDeleteOutsideTheBaseDirectory(): void
    {
        $base = $this->tmpDir . '/vendor';
        mkdir($base, 0o700, true);
        $outside = $this->tmpDir . '/precious';
        mkdir($outside, 0o700, true);
        file_put_contents($outside . '/settings.php', '<?php');

        self::assertFalse(devDependencyRemovePath($base, $outside));
        self::assertFileExists($outside . '/settings.php');

        // Same refusal when the escape is spelled as a traversal.
        self::assertFalse(devDependencyRemovePath($base, $base . '/../precious'));
        self::assertFileExists($outside . '/settings.php');
    }

    public function testDeletesASymlinkWithoutFollowingIt(): void
    {
        $base = $this->tmpDir . '/vendor';
        mkdir($base . '/bin', 0o700, true);
        $outside = $this->tmpDir . '/real';
        mkdir($outside, 0o700, true);
        file_put_contents($outside . '/target.php', '<?php');

        $link = $base . '/bin/phpstan';
        symlink($outside . '/target.php', $link);

        self::assertTrue(devDependencyRemovePath($base . '/bin', $link));
        self::assertFalse(is_link($link));
        // The link was removed, never what it pointed at.
        self::assertFileExists($outside . '/target.php');
    }

    public function testRemovesAnEmptyDirectoryButKeepsAPopulatedOne(): void
    {
        $base = $this->tmpDir . '/vendor';
        mkdir($base . '/phpunit', 0o700, true);
        mkdir($base . '/symfony/http-foundation', 0o700, true);

        self::assertTrue(devDependencyRemoveDirectoryIfEmpty($base, $base . '/phpunit'));
        self::assertDirectoryDoesNotExist($base . '/phpunit');

        // symfony still holds a production package, so it must survive.
        self::assertFalse(devDependencyRemoveDirectoryIfEmpty($base, $base . '/symfony'));
        self::assertDirectoryExists($base . '/symfony');
    }

    public function testEmptyDirectoryRemovalIsAlsoContained(): void
    {
        $base = $this->tmpDir . '/vendor';
        mkdir($base, 0o700, true);
        $outside = $this->tmpDir . '/empty-but-outside';
        mkdir($outside, 0o700, true);

        self::assertFalse(devDependencyRemoveDirectoryIfEmpty($base, $outside));
        self::assertDirectoryExists($outside);
    }

    // --- Sentinel on the real lock file ------------------------------------------

    public function testProjectLockYieldsTheDevToolsAndNoProductionPackage(): void
    {
        $lockPath = __DIR__ . '/../../composer.lock';
        self::assertFileExists($lockPath);

        $result = devDependenciesFromLock((string) file_get_contents($lockPath));

        // The tools that have no reason to sit on a production server.
        self::assertContains('phpstan/phpstan', $result['packages']);
        self::assertContains('phpunit/phpunit', $result['packages']);
        self::assertContains('dominikb/composer-license-checker', $result['packages']);
        self::assertContains('phpstan.phar', $result['binaries']);

        // Packages the application loads at runtime must never appear.
        foreach (
            [
                'symfony/http-foundation',
                'phpseclib/phpseclib',
                'guzzlehttp/guzzle',
                'defuse/php-encryption',
                'sergeytsalkov/meekrodb',
                'composer/composer',
            ] as $productionPackage
        ) {
            self::assertNotContains($productionPackage, $result['packages']);
        }

        // Production launchers must survive the cleanup.
        foreach (['composer', 'carbon', 'generate-defuse-key'] as $productionBinary) {
            self::assertNotContains($productionBinary, $result['binaries']);
        }
    }
}
