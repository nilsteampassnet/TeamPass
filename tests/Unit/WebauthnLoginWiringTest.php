<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/log_display_logic.php';

/**
 * Sentinel: how sign-in passkey enrolment (part B) is wired into the pages and handlers. The
 * ceremonies themselves are covered by WebauthnLoginLogicTest.
 */
final class WebauthnLoginWiringTest extends TestCase
{
    /** Actions on the caller's own passkeys, open to every user. */
    private const PROFILE_ACTIONS = [
        'webauthn_login_list',
        'webauthn_login_register_options',
        'webauthn_login_register_verify',
        'webauthn_login_rename',
        'webauthn_login_delete',
        'webauthn_login_passwordless_options',
        'webauthn_login_passwordless_verify',
        'webauthn_login_passwordless_disable',
    ];

    /** Actions on another account's passkeys, for administrators and managers only. */
    private const ADMIN_ACTIONS = ['webauthn_login_admin_list', 'webauthn_login_admin_delete'];

    public function testProfileActionsAreOpenAndAdminActionsAreNot(): void
    {
        $queries = (string) file_get_contents(__DIR__ . '/../../app/sources/users.queries.php');
        $start = strpos($queries, '$all_users_can_access = [');
        $this->assertIsInt($start);
        $allowList = substr($queries, $start, (int) strpos($queries, '];', $start) - $start);

        foreach (self::PROFILE_ACTIONS as $action) {
            $this->assertStringContainsString("'" . $action . "'", $allowList, $action);
            $this->assertStringContainsString("case '" . $action . "':", $queries, $action);
        }
        // Outside the allow-list, the checks on user_id (manager scope) apply before the handler.
        foreach (self::ADMIN_ACTIONS as $action) {
            $this->assertStringNotContainsString("'" . $action . "'", $allowList, $action);
            $this->assertStringContainsString("case '" . $action . "':", $queries, $action);
        }
    }

    public function testProfileActionsAlwaysActOnTheCaller(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');
        $start = strpos($functions, 'function webauthnLoginProfileAction(');
        $end = strpos($functions, 'function webauthnLoginAdminAction(');
        $this->assertIsInt($start);
        $this->assertIsInt($end);
        $dispatcher = substr($functions, $start, $end - $start);

        // The owner comes from the session, never from the request.
        $this->assertStringContainsString("\$userId = (int) \$session->get('user-id');", $dispatcher);
        $this->assertStringNotContainsString("\$data['user_id']", $dispatcher);
    }

    public function testListingNeverReturnsKeyMaterial(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');
        $start = strpos($functions, 'function webauthnLoginRows(');
        $this->assertIsInt($start);
        $rows = substr($functions, $start, (int) strpos($functions, 'function webauthnLoginRow(', $start) - $start);

        foreach (['wrapped_private_key', 'wrap_salt', 'public_key_cose', 'credential_id'] as $column) {
            $this->assertStringNotContainsString($column, $rows, $column);
        }
    }

    public function testTheScriptIsLoadedOnlyWhileTheFeatureIsOn(): void
    {
        $index = (string) file_get_contents(__DIR__ . '/../../public/index.php');

        $this->assertMatchesRegularExpression(
            "/webauthn_login_mode'\] \?\? 0\) !== 0\) \{ \?>\s*<script[^>]+assets\/js\/webauthn-login\.js/",
            $index
        );
        $this->assertFileExists(__DIR__ . '/../../public/assets/js/webauthn-login.js');
    }

    public function testAdministratorSettingsAreOnTheMfaPage(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.php');

        // Saved by the generic handlers of admin.js.php
        $this->assertStringContainsString("<select class='form-control form-control-sm select2' id='webauthn_login_mode'", $page);
        foreach (['webauthn_login_require_prf', 'webauthn_email_on_add'] as $toggle) {
            $this->assertStringContainsString('id="' . $toggle . '"', $page);
            $this->assertStringContainsString('id="' . $toggle . '_input"', $page);
        }
        foreach (['webauthn_rp_id', 'webauthn_rp_name'] as $field) {
            $this->assertStringContainsString('id="' . $field . '"', $page);
        }

        $admin = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $this->assertStringContainsString("if (\$post_field === 'webauthn_login_mode') {", $admin);
    }

    public function testEnrolmentEmailAndLogLabelsExist(): void
    {
        $catalog = require __DIR__ . '/../../app/config/emails_templates.php';
        $this->assertSame('email_body_webauthn_login_added', $catalog['webauthn_login_added']['body_key'] ?? null);

        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        foreach (['at_user_webauthn_added', 'at_user_webauthn_deleted', 'at_user_webauthn_passwordless_enabled', 'at_user_webauthn_passwordless_disabled'] as $label) {
            $this->assertNotSame($label, formatAdminLogLabel($label, $lang), $label);
        }
    }
}
