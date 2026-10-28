<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPackageAuditLogTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/sources/log_display_logic.php';

final class RecoveryPackageAuditLogTest extends TestCase
{
    public function testTheMachineCodeAndEarlierLabelsUseTheAdministratorsLanguage(): void
    {
        foreach (['english' => 'Recovery Package downloaded', 'french' => 'Kit de récupération téléchargé'] as $language => $expected) {
            $lang = new Language($language, __DIR__ . '/../../app/includes/language');
            self::assertSame($expected, formatAdminLogLabel('recovery_package_downloaded', $lang));
            self::assertSame($expected, formatAdminLogLabel('Recovery Package downloaded', $lang));
        }
    }
}
