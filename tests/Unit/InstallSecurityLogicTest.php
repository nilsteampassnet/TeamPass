<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * @file      InstallSecurityLogicTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/install_security_logic.php';

/**
 * Pure-logic contract of the installer security helpers (GHSA-8xp7-fh3p-5pcr).
 *
 * The blocking rule is installSanitizeSettingsValue(): a value interpolated into settings.php
 * must never be able to form a "${...}" / "{${...}}" interpolation, re-open the string with a
 * double quote, or run away through a trailing backslash. These tests pin that, plus the table
 * prefix validator, the dispatcher allow-list and the constant-time CSRF comparison.
 */
final class InstallSecurityLogicTest extends TestCase
{
    /** The actual fresh-install schedule must be executable through the secure dispatcher. */
    public function testEveryScheduledSchemaActionIsExplicitlyAllowed(): void
    {
        $javascript = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/install.js');
        preg_match_all('/const checks = \[([\s\S]*?)\];/', $javascript, $blocks);
        $checked = false;
        foreach ($blocks[1] as $block) {
            if (strpos($block, "action: 'api_tokens'") === false) {
                continue;
            }
            preg_match_all("/action: '([A-Za-z0-9_]+)'/", $block, $actions);
            foreach ($actions[1] as $action) {
                self::assertTrue(installIsActionAllowed('step5', $action), 'Scheduled schema action is rejected: ' . $action);
            }
            $checked = true;
        }
        self::assertTrue($checked, 'Schema schedule not found');
        self::assertFalse(installIsActionAllowed('step5', 'settingsFile'));
        self::assertFalse(installIsActionAllowed('step5', '__construct'));
    }

    /**
     * The actual code-execution primitive the advisory reported: a value escaped for settings.php
     * must round-trip as a plain literal with no PHP evaluated, even for the exact PoC payloads.
     *
     * @return array<string,array{string}>
     */
    public static function rceePayloads(): array
    {
        return [
            'dollar brace'        => ['pwn_${($GLOBALS["__tp_probe__"]=1)}'],
            'brace dollar'        => ['pwn_{${($GLOBALS["__tp_probe__"]=1)}}'],
            'chr based poc'       => ['pwn_${file_put_contents(chr(47).chr(116),chr(79))}'],
            'closing quote'       => ['a"); phpinfo(); define("X", "b'],
            'trailing backslash'  => ['value\\'],
            'mixed'               => ['x\\"${1}'],
        ];
    }

    #[DataProvider('rceePayloads')]
    public function testEscapedValueIsAnInertLiteralWhenIncluded(string $raw): void
    {
        $GLOBALS['__tp_probe__'] = null;
        unset($GLOBALS['__tp_probe__']);

        $const = 'TP_ESCAPE_' . bin2hex(random_bytes(6));
        $escaped = installSanitizeSettingsValue($raw);

        // Build the exact shape settingsFile() writes, then execute it like settings.php is.
        $php = '<?php define("' . $const . '", "' . $escaped . '");';
        $file = tempnam(sys_get_temp_dir(), 'tp_settings_');
        self::assertNotFalse($file);
        file_put_contents($file, $php);

        include $file;
        unlink($file);

        // The constant must equal the raw input verbatim: nothing was interpolated or executed.
        self::assertTrue(defined($const));
        self::assertSame($raw, constant($const));
        self::assertArrayNotHasKey('__tp_probe__', $GLOBALS, 'Payload must not execute on include.');
    }

    public function testSanitizeOrderDoublesBackslashBeforeOtherEscapes(): void
    {
        // A lone backslash must become exactly one escaped backslash, not swallow the next char.
        self::assertSame('\\\\', installSanitizeSettingsValue('\\'));
        self::assertSame('\\"', installSanitizeSettingsValue('"'));
        self::assertSame('\\$', installSanitizeSettingsValue('$'));
        self::assertSame('abc', installSanitizeSettingsValue('abc'));
    }

    /**
     * @return array<string,array{string,bool}>
     */
    public static function tablePrefixes(): array
    {
        return [
            'default'        => ['teampass_', true],
            'empty'          => ['', true],
            'word only'      => ['Tp_123_', true],
            'max length'     => [str_repeat('a', 64), true],
            'too long'       => [str_repeat('a', 65), false],
            'interpolation'  => ['pwn_${x}', false],
            'quote'          => ['a"b', false],
            'space'          => ['a b', false],
            'dash'           => ['a-b', false],
            'dot'            => ['a.b', false],
        ];
    }

    #[DataProvider('tablePrefixes')]
    public function testTablePrefixValidation(string $prefix, bool $expected): void
    {
        self::assertSame($expected, installIsValidTablePrefix($prefix));
    }

    public function testDispatcherAllowListCoversTheRealActionsAndRejectsEverythingElse(): void
    {
        // The sink and a representative schema action are reachable...
        self::assertTrue(installIsActionAllowed('step6', 'settingsFile'));
        self::assertTrue(installIsActionAllowed('step6', 'cleanInstall'));
        self::assertTrue(installIsActionAllowed('step5', 'users'));

        // ...but a step6 action cannot be invoked through step5, and vice versa.
        self::assertFalse(installIsActionAllowed('step5', 'settingsFile'));
        self::assertFalse(installIsActionAllowed('step6', 'users'));

        // Arbitrary / internal names are never dispatchable.
        self::assertFalse(installIsActionAllowed('step6', '__construct'));
        self::assertFalse(installIsActionAllowed('step6', 'handleAction'));
        self::assertFalse(installIsActionAllowed('step6', ''));
        self::assertFalse(installIsActionAllowed('unknown-step', 'settingsFile'));
    }

    public function testCsrfTokenMatchRequiresTwoEqualNonEmptyTokens(): void
    {
        $token = bin2hex(random_bytes(32));

        self::assertTrue(installCsrfTokenMatches($token, $token));
        self::assertFalse(installCsrfTokenMatches($token, $token . 'x'));
        self::assertFalse(installCsrfTokenMatches($token, null));
        self::assertFalse(installCsrfTokenMatches(null, $token));
        self::assertFalse(installCsrfTokenMatches('', ''));
        self::assertFalse(installCsrfTokenMatches(null, null));
    }
}
