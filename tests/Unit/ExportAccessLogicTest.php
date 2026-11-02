<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/export_access_logic.php';

/**
 * Behavioural tests for the export permission (allow_print + roles_allowed_to_print_select).
 *
 * The Export page applied this rule but the handler building the files did not, so any user
 * could export by posting to it directly. Both now share exportAllowedForUser().
 */
class ExportAccessLogicTest extends TestCase
{
    private const SETTINGS = [
        'allow_print' => '1',
        'roles_allowed_to_print_select' => '["3","7"]',
    ];

    public function testUserHoldingAnAllowedRoleMayExport(): void
    {
        self::assertTrue(exportAllowedForUser(self::SETTINGS, '3', false));
        self::assertTrue(exportAllowedForUser(self::SETTINGS, '1;7', false));
        self::assertTrue(exportAllowedForUser(self::SETTINGS, ' 2 ; 7 ', false));
    }

    public function testUserWithoutAnAllowedRoleMayNotExport(): void
    {
        self::assertFalse(exportAllowedForUser(self::SETTINGS, '1;2', false));
        self::assertFalse(exportAllowedForUser(self::SETTINGS, '', false));
        // "37" is not role 3 nor role 7.
        self::assertFalse(exportAllowedForUser(self::SETTINGS, '37', false));
    }

    public function testExportsDisabledRefuseEveryone(): void
    {
        self::assertFalse(exportAllowedForUser(['allow_print' => '0'] + self::SETTINGS, '3', false));
        self::assertFalse(exportAllowedForUser(['roles_allowed_to_print_select' => '["3"]'], '3', false));
        self::assertFalse(exportAllowedForUser(['allow_print' => '1', 'roles_allowed_to_print_select' => ''], '3', false));
        self::assertFalse(exportAllowedForUser(['allow_print' => '1', 'roles_allowed_to_print_select' => '[]'], '3', false));
        self::assertFalse(exportAllowedForUser([], '3', false));
    }

    public function testAdministratorsNeverExport(): void
    {
        self::assertFalse(exportAllowedForUser(self::SETTINGS, '3;7', true));
    }

    public function testRoleListParsingKeepsPositiveIntegersOnly(): void
    {
        self::assertSame([3, 7], exportAccessParseRoleIds('3,7,3', ','));
        self::assertSame([5], exportAccessParseRoleIds('5;0;-2;abc;1.5;', ';'));
        self::assertSame([], exportAccessParseRoleIds('', ';'));
    }
}
