<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      InstallerSecureFilePermissionsTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

namespace TeamPass\Tests\InstallerSecureFile;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Intercept chmod failures without requiring a privileged filesystem fixture. */
function chmod(string $path, int $mode): bool
{
    return InstallerSecureFilePermissionsTest::$denyChmod ? false : \chmod($path, $mode);
}

final class InstallerSecureFilePermissionsTest extends TestCase
{
    public static bool $denyChmod = false;
    private string $root;
    private static \Closure $writeKey;

    public static function setUpBeforeClass(): void
    {
        // The installer is an HTTP/DB entrypoint: exercise its actual file-write
        // block without running the request handler or creating real settings.
        $source = str_replace("\r\n", "\n", (string) file_get_contents(
            __DIR__ . '/../../public/install/install-steps/run.step6.php'
        ));
        $start = strpos($source, '// Store key in file');
        $end = strpos($source, '// Store the secure file name in the database', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $block = str_replace('$this->installConfig[\'teampassSecurePath\']', '$securePath', substr($source, $start, $end - $start));
        self::$writeKey = eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; '
            . 'return static function (string $securePath, string $secureFile, string $newSalt): void {' . $block . '};');
    }

    protected function setUp(): void
    {
        self::$denyChmod = false;
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'teampass-installer-key-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        self::$denyChmod = false;
        if (is_file($this->root . '/fixture.key')) {
            unlink($this->root . '/fixture.key');
        }
        rmdir($this->root);
    }

    /** @return array<string,array{int}> */
    public static function creationMasks(): array
    {
        return ['open' => [0000], 'group writable' => [0002], 'usual' => [0022],
            'restricted' => [0027], 'private' => [0077], 'all masked' => [0777]];
    }

    #[DataProvider('creationMasks')]
    public function testKeyIsCreatedWithOwnerOnlyAccessRegardlessOfUmask(int $mask): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX modes are required.');
        }
        $previousMask = umask($mask);
        try {
            (self::$writeKey)($this->root, 'fixture.key', 'synthetic fixture, not a real key');
            clearstatcache(true, $this->root . '/fixture.key');
            self::assertSame(0600, fileperms($this->root . '/fixture.key') & 0777);
            self::assertSame('synthetic fixture, not a real key', file_get_contents($this->root . '/fixture.key'));
            self::assertSame($mask, umask());
        } finally {
            umask($previousMask);
        }
    }

    public function testWriteFailureStopsBeforeTheKeyCanBeRegistered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to write the encryption key file.');
        (self::$writeKey)($this->root . '/missing', 'fixture.key', 'synthetic fixture');
    }

    public function testPermissionFailureStopsBeforeTheKeyCanBeRegistered(): void
    {
        self::$denyChmod = true;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to restrict encryption key file permissions.');
        (self::$writeKey)($this->root, 'fixture.key', 'synthetic fixture');
    }
}
