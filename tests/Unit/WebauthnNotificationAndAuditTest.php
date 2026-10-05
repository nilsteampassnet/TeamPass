<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: what an administrator and a user see of vault passkeys outside the item card — the
 * two switches, the notification email, the audit labels and their translations.
 */
final class WebauthnNotificationAndAuditTest extends TestCase
{
    /**
     * Languages synced with POEditor. Arabic is not, and never receives new keys.
     */
    private const POEDITOR_LANGUAGES = [
        'bulgarian', 'catalan', 'chinese', 'czech', 'dutch', 'english', 'estonian', 'french',
        'german', 'greek', 'hungarian', 'italian', 'japanese', 'norwegian', 'polish', 'portuguese',
        'portuguese_br', 'romanian', 'russian', 'spanish', 'swedish', 'turkish', 'ukrainian',
        'vietnamese',
    ];

    public function testBothSwitchesAreOnTheBrowserExtensionTab(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/api.php');
        $start = strpos($page, 'id="extension"');
        $end = strpos($page, 'id="licence"');
        $this->assertIsInt($start);
        $this->assertIsInt($end);
        $tab = substr($page, $start, $end - $start);

        foreach (['webauthn_provider_enabled', 'webauthn_email_on_add'] as $setting) {
            // Saved by the generic toggle handler of admin.js.php: id + "<id>_input".
            $this->assertStringContainsString("id='" . $setting . "'", $tab);
            $this->assertStringContainsString("id='" . $setting . "_input'", $tab);
        }
    }

    public function testEmailIsQueuedAfterTheCommitAndGated(): void
    {
        $model = (string) file_get_contents(__DIR__ . '/../../app/api/Model/WebauthnModel.php');
        $create = $this->between($model, 'public function createCredential(', 'public function listCredentials(');

        // A failed transaction must not tell the user a passkey was saved.
        $commit = strpos($create, 'DB::commit();');
        $notify = strpos($create, '$this->notifyCredentialAdded(');
        $this->assertIsInt($commit);
        $this->assertIsInt($notify);
        $this->assertGreaterThan($commit, $notify);

        $notifier = $this->between($model, 'private function notifyCredentialAdded(', 'private function canEditItem(');
        $this->assertStringContainsString("'webauthn_email_on_add'", $notifier);
        $this->assertStringContainsString("'email_smtp_server'", $notifier);
        $this->assertStringContainsString("getEmailTemplateSubject('webauthn_credential_added'", $notifier);
        // Queued, never sent inside the API request.
        $this->assertMatchesRegularExpression('/\],\s*false,\s*\'\',/', $notifier);
        // The relying party strings come from the site's page.
        $this->assertStringContainsString('htmlspecialchars(', $notifier);
    }

    public function testEmailIsCustomizable(): void
    {
        $catalog = require __DIR__ . '/../../app/config/emails_templates.php';

        $this->assertArrayHasKey('webauthn_credential_added', $catalog);
        $this->assertSame('email_body_webauthn_credential_added', $catalog['webauthn_credential_added']['body_key']);
        $this->assertSame('email_subject_webauthn_credential_added', $catalog['webauthn_credential_added']['subject_key']);
    }

    public function testAuditCodesHaveLabelsAndDetails(): void
    {
        $english = require __DIR__ . '/../../app/includes/language/english.php';
        foreach (['at_webauthn_credential_added', 'at_webauthn_credential_deleted', 'at_webauthn_credential_used', 'action_webauthn_used'] as $key) {
            $this->assertArrayHasKey($key, $english);
        }

        require_once __DIR__ . '/../../app/sources/logs_filter_logic.php';
        $this->assertContains('at_webauthn_credential_used', logsAllowedItemActions());

        // The item history shows which site each passkey event concerns.
        $queries = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $this->assertStringContainsString("\$record['action'] === 'at_webauthn_credential_used'", $queries);
        $this->assertStringContainsString("\$reason[0] === 'at_webauthn_credential_added' || \$reason[0] === 'at_webauthn_credential_deleted'", $queries);
    }

    public function testEveryPasskeyStringReachesEveryPoeditorLanguage(): void
    {
        $english = require __DIR__ . '/../../app/includes/language/english.php';
        $keys = array_filter(
            array_keys($english),
            static fn (string $key): bool => str_contains($key, 'webauthn')
        );
        $this->assertNotEmpty($keys);

        foreach (self::POEDITOR_LANGUAGES as $language) {
            $strings = require __DIR__ . '/../../app/includes/language/' . $language . '.php';
            $this->assertSame([], array_values(array_diff($keys, array_keys($strings))), $language);
        }
    }

    private function between(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        $this->assertIsInt($start, 'Start marker not found: ' . $startMarker);
        $end = strpos($source, $endMarker, $start);
        $this->assertIsInt($end, 'End marker not found: ' . $endMarker);

        return substr($source, $start, $end - $start);
    }
}
