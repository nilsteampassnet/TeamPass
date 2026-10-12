<?php

declare(strict_types=1);

use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * handleSecurefileConstant() moves the legacy teampass-seckey.txt to a random name during the
 * upgrade. A PHP constant cannot be redefined, so when settings.php already named the legacy file
 * the rest of the request kept reading a file that no longer existed (issue #5423).
 *
 * Each test runs in its own process: the function reads and defines global constants, and writes
 * settings.php under TEAMPASS_ROOT, which points to a temporary directory here.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecurefileMigrationTest extends TestCase
{
    private string $baseDir = '';

    private string $asciiKey = '';

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/tp_securefile_' . bin2hex(random_bytes(6));
        mkdir($this->baseDir . '/root/app/config', 0700, true);
        mkdir($this->baseDir . '/secrets', 0700, true);

        define('TEAMPASS_ROOT', $this->baseDir . '/root');
        define('TEAMPASS_SECRETS', $this->baseDir . '/secrets');
        define('DB_HOST', 'localhost');
        define('DB_USER', 'teampass');
        define('DB_PASSWD', 'secret');
        define('DB_NAME', 'teampass');
        define('DB_PREFIX', 'teampass_');
        define('DB_PORT', '3306');
        define('DB_ENCODING', 'utf8mb4');
        define('DB_SSL', false);

        require_once __DIR__ . '/../../public/install/tp.functions.php';

        $this->asciiKey = Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    protected function tearDown(): void
    {
        foreach (['/secrets', '/root/app/config'] as $dir) {
            foreach (glob($this->baseDir . $dir . '/*') ?: [] as $file) {
                unlink($file);
            }
        }
        foreach (['/secrets', '/root/app/config', '/root/app', '/root', ''] as $dir) {
            rmdir($this->baseDir . $dir);
        }
    }

    /** The documented 2.x procedure names the legacy file: the request must be run again. */
    public function testLegacyConstantRequiresAReload(): void
    {
        define('SECUREFILE', 'teampass-seckey.txt');
        file_put_contents(TEAMPASS_SECRETS . '/teampass-seckey.txt', $this->asciiKey);

        $ret = handleSecurefileConstant();

        self::assertFalse($ret['error']);
        self::assertTrue($ret['reload']);
        // This is why: the constant still names the file that was just renamed
        self::assertFileDoesNotExist(TEAMPASS_SECRETS . '/' . SECUREFILE);

        $newName = $this->securefileInSettings();
        self::assertNotSame('teampass-seckey.txt', $newName);
        self::assertSame($this->asciiKey, file_get_contents(TEAMPASS_SECRETS . '/' . $newName));
    }

    /** Without SECUREFILE the function defines it itself, so the request stays consistent. */
    public function testUndefinedConstantNeedsNoReload(): void
    {
        file_put_contents(TEAMPASS_SECRETS . '/teampass-seckey.txt', $this->asciiKey);

        $ret = handleSecurefileConstant();

        self::assertFalse($ret['error']);
        self::assertFalse($ret['reload']);
        self::assertSame($this->asciiKey, file_get_contents(TEAMPASS_SECRETS . '/' . SECUREFILE));
        self::assertSame(SECUREFILE, $this->securefileInSettings());
    }

    /** The pass that follows the reload: the migration is done, nothing is touched. */
    public function testMigratedInstanceIsLeftAlone(): void
    {
        define('SECUREFILE', 'AlreadyRandomName0123456789');
        file_put_contents(TEAMPASS_SECRETS . '/' . SECUREFILE, $this->asciiKey);

        $ret = handleSecurefileConstant();

        self::assertFalse($ret['error']);
        self::assertFalse($ret['reload']);
        self::assertFileDoesNotExist(TEAMPASS_ROOT . '/app/config/settings.php');
        self::assertSame($this->asciiKey, file_get_contents(TEAMPASS_SECRETS . '/' . SECUREFILE));
    }

    /** SECUREFILE value written in the rewritten settings.php. */
    private function securefileInSettings(): string
    {
        $settings = (string) file_get_contents(TEAMPASS_ROOT . '/app/config/settings.php');
        self::assertSame(1, preg_match('/define\("SECUREFILE", "([^"]+)"\);/', $settings, $matches));

        return $matches[1];
    }
}
