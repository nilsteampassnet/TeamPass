<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static integration guards for the admin dashboard configuration sources.
 */
class AdminNoticesConfigurationTest extends TestCase
{
    public function testScheduledBackupNoticeReadsTheOperationalSettingsNamespace(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/admin_notices.functions.php');
        self::assertIsString($source);

        self::assertMatchesRegularExpression(
            '/\$scheduledBackupEnabled\s*=\s*DB::queryFirstField\(.*?'
                . "prefixTable\('misc'\).*?'settings'.*?'bck_scheduled_enabled'/s",
            $source
        );
        self::assertStringNotContainsString("\$SETTINGS['bck_scheduled_enabled']", $source);
    }
}
