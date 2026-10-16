<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      InstallerRequestHardeningTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/install_security_logic.php';

/**
 * Source-contract guard for the installer hardening (GHSA-8xp7-fh3p-5pcr).
 *
 * The runtime behaviour (session grant + CSRF) needs an HTTP/DB context, so it is not exercised
 * here; instead these tests pin the wiring that makes the fix exist and cannot be silently
 * reverted — every step guards itself, the dispatchers are allow-listed, and settingsFile()
 * routes its values through the escaper — and then prove end to end that the actual settings.php
 * the installer generates no longer executes an injected payload.
 */
final class InstallerRequestHardeningTest extends TestCase
{
    private const STEPS_DIR = __DIR__ . '/../../public/install/install-steps';

    private static function read(string $relative): string
    {
        $path = self::STEPS_DIR . '/' . $relative;
        $source = file_get_contents($path);
        self::assertNotFalse($source, "Unable to read {$relative}");

        return str_replace("\r\n", "\n", (string) $source);
    }

    /** @return array<string,array{string}> */
    public static function stepFiles(): array
    {
        return [
            'step1' => ['run.step1.php'],
            'step2' => ['run.step2.php'],
            'step3' => ['run.step3.php'],
            'step4' => ['run.step4.php'],
            'step5' => ['run.step5.php'],
            'step6' => ['run.step6.php'],
        ];
    }

    #[DataProvider('stepFiles')]
    public function testEveryStepGuardsTheRequest(string $file): void
    {
        self::assertStringContainsString(
            'installAssertStepAuthorized();',
            self::read($file),
            "{$file} must reject unauthenticated/forged requests before processing input."
        );
    }

    /** @return array<string,array{string,string}> */
    public static function dispatchers(): array
    {
        return [
            'step5' => ['run.step5.php', 'step5'],
            'step6' => ['run.step6.php', 'step6'],
        ];
    }

    #[DataProvider('dispatchers')]
    public function testDispatcherUsesTheAllowListNotBareMethodExists(string $file, string $step): void
    {
        $source = self::read($file);

        self::assertStringContainsString(
            "installIsActionAllowed('{$step}',",
            $source,
            "{$file} must dispatch through the action allow-list."
        );
        self::assertStringNotContainsString(
            "method_exists(\$this, \$this->inputData['action'])",
            $source,
            "{$file} must not dispatch any method that merely exists on the class."
        );
    }

    public function testSettingsFileEscapesEveryInterpolatedValue(): void
    {
        $source = self::read('run.step6.php');

        // The value-bearing defines must each go through the escaper.
        foreach (['DB_HOST', 'DB_USER', 'DB_PASSWD', 'DB_NAME', 'DB_PREFIX', 'DB_PORT', 'SECUREFILE'] as $name) {
            self::assertMatchesRegularExpression(
                '/define\("' . $name . '", "\' \. installSanitizeSettingsValue\(/',
                $source,
                "settings.php define({$name}) must be escaped with installSanitizeSettingsValue()."
            );
        }

        // And no raw request/config value may be interpolated straight into a quoted literal.
        self::assertStringNotContainsString('"\' . $this->inputData[', $source);
        self::assertStringNotContainsString('"\' . $this->installConfig[', $source);

        // The prefix is validated before it reaches either the sink or the table identifiers.
        self::assertStringContainsString('installIsValidTablePrefix(', $source);
    }

    public function testInstallPageLocksWhenAlreadyInstalledAndEmitsTheToken(): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../public/install/install.php'));

        self::assertStringContainsString("=== 'installed'", $source, 'install.php must refuse an installed instance.');
        self::assertStringContainsString('installIssueCsrfToken(', $source, 'install.php must mint the install token.');
        self::assertStringContainsString('name="csrf-token"', $source, 'install.php must expose the token to install.js.');
    }

    /**
     * End-to-end proof: take the ACTUAL settingsContent block from run.step6.php, feed it the
     * reporter's style of payload through tablePrefix, generate the file exactly as the installer
     * would, include it, and show the payload is a dead literal — no PHP ran.
     */
    public function testGeneratedSettingsFileDoesNotExecuteAnInjectedPrefix(): void
    {
        $source = self::read('run.step6.php');

        $startMarker = '$settingsContent = ';
        $start = strpos($source, $startMarker);
        self::assertNotFalse($start);
        // The assignment ends at the first line-leading "';" after the start.
        $end = strpos($source, "\n';", $start);
        self::assertNotFalse($end);

        // Right-hand side expression, ending at the closing quote of the last segment.
        $rhs = substr($source, $start + strlen($startMarker), ($end + 2) - ($start + strlen($startMarker)));

        // Remap instance access to locals and namespace the generated constants so including the
        // file cannot collide with anything (DB_HOST etc. are global in TeamPass).
        $prefix = 'TPTEST_' . bin2hex(random_bytes(4)) . '_';
        $rhs = str_replace('$this->inputData', '$inputData', $rhs);
        $rhs = str_replace('$this->installConfig', '$installConfig', $rhs);
        $rhs = str_replace('define("', 'define("' . $prefix, $rhs);

        $probe = '__tp_rce_probe_' . bin2hex(random_bytes(4)) . '__';
        unset($GLOBALS[$probe]);

        $inputData = [
            'dbHost'      => 'localhost',
            'dbLogin'     => 'root',
            'dbName'      => 'teampass',
            'dbPort'      => '3306',
            'tablePrefix' => 'zz_${($GLOBALS["' . $probe . '"]=1)}',
        ];
        $installConfig = ['teampassSecureFile' => 'sk.key'];
        $encryptedPassword = 'def00000deadbeef';

        // Safe by construction: $rhs is TeamPass's own committed settingsContent expression read
        // from run.step6.php (never external input), mirroring InstallerSecureFilePermissionsTest.
        /** @var string $generated */
        $generated = eval('return ' . $rhs . ';');
        self::assertIsString($generated);

        $file = tempnam(sys_get_temp_dir(), 'tp_settings_');
        self::assertNotFalse($file);
        file_put_contents($file, $generated);
        include $file;
        unlink($file);

        self::assertArrayNotHasKey($probe, $GLOBALS, 'The injected tablePrefix must not execute.');
        self::assertTrue(defined($prefix . 'DB_PREFIX'));
        self::assertSame($inputData['tablePrefix'], constant($prefix . 'DB_PREFIX'));
    }
}
