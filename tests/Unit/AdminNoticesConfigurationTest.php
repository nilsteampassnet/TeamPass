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

    public function testMfaNoticeTakesSignInPasskeysIntoAccount(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/admin_notices.functions.php');
        self::assertIsString($source);

        self::assertMatchesRegularExpression(
            '/adminNoticesMfaRecommendation\(\s*\$mfaEnabled,\s*'
                . 'webauthnLoginMode\(\$SETTINGS\) !== TP_WEBAUTHN_LOGIN_MODE_DISABLED\s*\)/',
            $source
        );

        // Both recommendations are worded in every language file that holds the first one.
        foreach (glob(__DIR__ . '/../../app/includes/language/*.php') ?: [] as $file) {
            $language = (string) file_get_contents($file);
            if (str_contains($language, "'admin_checklist_mfa'") === false) {
                continue;
            }
            self::assertStringContainsString("'admin_checklist_mfa_passkeys_only' =>", $language, basename($file));
            self::assertStringContainsString("'admin_checklist_mfa_passkeys_only_desc' =>", $language, basename($file));
        }
    }
}
