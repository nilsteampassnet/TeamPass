<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryPackageNoticesTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/admin_notices_logic.php';

final class RecoveryPackageNoticesTest extends TestCase
{
    public function testCurrentKitClearsTheActionAndOtherStatesExplainTheRequiredWork(): void
    {
        self::assertSame([], adminNoticesRecoveryPackageMessages('current'));
        $messages = [];
        foreach (['missing', 'material_changed', 'configuration_changed', 'unavailable'] as $state) {
            $messages[$state] = adminNoticesRecoveryPackageMessages($state);
            self::assertNotEmpty($messages[$state]['title']);
            self::assertNotEmpty($messages[$state]['description']);
        }
        self::assertCount(4, array_unique(array_column($messages, 'description')));
        self::assertSame($messages['unavailable'], adminNoticesRecoveryPackageMessages('unknown'));
    }

    public function testEveryNoticeHasShippedEnglishAndFrenchMessages(): void
    {
        foreach (['english', 'french'] as $language) {
            $lang = require __DIR__ . '/../../app/includes/language/' . $language . '.php';
            foreach (['missing', 'material_changed', 'configuration_changed', 'unavailable'] as $state) {
                foreach (adminNoticesRecoveryPackageMessages($state) as $key) {
                    self::assertArrayHasKey($key, $lang);
                    self::assertNotEmpty($lang[$key]);
                }
            }
        }
    }
}
