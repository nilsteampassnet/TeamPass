<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: every file that exists in two locations must stay byte-identical.
 *
 * TeamPass keeps two copies of its front-end scripts and of every teampassclasses package.
 * In both cases only ONE copy is the live one:
 *
 *   - JavaScript      — public/assets/js/ is what public/index.php loads.
 *   - teampassclasses — app/vendor/ is what Composer autoloads (see CLAUDE.md).
 *
 * Editing the other copy therefore produces a change with zero runtime effect, and the
 * mistake is invisible: the code reads correctly, the tests pass, nothing happens in the
 * browser. It has already cost two shipped fixes that never took effect —
 * 76f506ba4 ("Fix password integrity: preserve HTML-sensitive characters") and d890b242c
 * ("Add 'otp_secret' to ignoredFields"), both applied only to app/includes/js/functions.js,
 * so `password` and `otp_secret` kept being purified in production. A third,
 * rehydratePresenceIndicators() in teampass-websocket-init.js, was never served at all.
 *
 * CryptoManagerCopiesInSyncTest and LdapExtraCopiesInSyncTest cover two packages
 * individually; this one covers every pair, including the ones added later.
 */
class DuplicatedAssetsInSyncTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function javascriptPairsProvider(): array
    {
        $root = self::root();
        $pairs = [];

        foreach ((array) glob($root . '/app/includes/js/*.js') as $source) {
            $name = basename((string) $source);
            $served = $root . '/public/assets/js/' . $name;

            if (is_file($served) === true) {
                $pairs[$name] = ['app/includes/js/' . $name, 'public/assets/js/' . $name];
            }
        }

        return $pairs;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function teampassClassPackagesProvider(): array
    {
        $root = self::root();
        $packages = [];

        foreach ((array) glob($root . '/app/includes/libraries/teampassclasses/*', GLOB_ONLYDIR) as $directory) {
            $name = basename((string) $directory);

            if (is_dir($root . '/app/vendor/teampassclasses/' . $name) === true) {
                $packages[$name] = [$name];
            }
        }

        return $packages;
    }

    /**
     * @dataProvider javascriptPairsProvider
     */
    public function testJavascriptCopiesAreByteIdentical(string $editable, string $served): void
    {
        $root = self::root();

        $this->assertFileExists($root . '/' . $editable);
        $this->assertFileExists($root . '/' . $served);

        $this->assertSame(
            hash_file('sha256', $root . '/' . $editable),
            hash_file('sha256', $root . '/' . $served),
            sprintf(
                "%s and %s have diverged.\n"
                . "public/assets/js is the copy public/index.php actually loads, so a change kept "
                . "only in app/includes/js has no effect in the browser. Apply the edit to both.",
                $editable,
                $served
            )
        );
    }

    /**
     * @dataProvider teampassClassPackagesProvider
     */
    public function testTeampassClassPackagesAreIdentical(string $package): void
    {
        $root = self::root();
        $includes = $root . '/app/includes/libraries/teampassclasses/' . $package;
        $vendor = $root . '/app/vendor/teampassclasses/' . $package;

        $this->assertSame(
            self::directoryFingerprint($includes),
            self::directoryFingerprint($vendor),
            sprintf(
                "The two copies of the '%s' package have diverged.\n"
                . "app/vendor is the autoloaded copy, so a change kept only in "
                . "app/includes/libraries has no runtime effect. Apply the edit to both.",
                $package
            )
        );
    }

    /**
     * Hash of every PHP file in the tree, keyed by its path relative to the package root, so
     * the failure message points at a package rather than at an opaque single digest.
     *
     * @return array<string, string>
     */
    private static function directoryFingerprint(string $directory): array
    {
        $fingerprint = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() === false || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1);
            $fingerprint[$relative] = (string) hash_file('sha256', $file->getPathname());
        }

        ksort($fingerprint);

        return $fingerprint;
    }

    /**
     * Guard against the providers silently matching nothing — an empty data set would make the
     * two tests above pass without comparing anything at all.
     */
    public function testProvidersActuallyFindThePairs(): void
    {
        $this->assertGreaterThanOrEqual(
            5,
            count(self::javascriptPairsProvider()),
            'Expected the app/includes/js <-> public/assets/js pairs to be discovered.'
        );
        $this->assertGreaterThanOrEqual(
            15,
            count(self::teampassClassPackagesProvider()),
            'Expected the teampassclasses packages to be discovered in both locations.'
        );
    }
}
