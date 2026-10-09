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

    public function testSignInAsksAccountsWithAPasskeyForIt(): void
    {
        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');

        $this->assertStringContainsString("require_once __DIR__ . '/webauthn_login.functions.php';", $identify);

        // The opt-in rule comes before the short-circuit on the other methods: with passkeys
        // alone enabled, an account with one must still be asked for it.
        $needsMfa = $this->between($identify, 'function userNeedsMfa(', 'function getMfaMethodsForUserInfo(');
        $optIn = strpos($needsMfa, 'webauthnLoginIsSecondFactor(');
        $shortCircuit = strpos($needsMfa, 'isOneVarOfArrayEqualToValue(');
        $this->assertIsInt($optIn);
        $this->assertIsInt($shortCircuit);
        $this->assertLessThan($shortCircuit, $optIn);

        $this->assertStringContainsString("'webauthn' => \$needsMfa === true && \$hasPasskey === true", $identify);
        $this->assertStringContainsString("case 'webauthn':", $this->between($identify, 'function identifyDoMFAChecks(', 'function identifyDoAzureChecks('));
        $this->assertStringContainsString("'webauthn_options' => \$userMfa['webauthn_options']", $identify);
    }

    public function testPasswordlessSignInSharesTheEndOfThePasswordSignIn(): void
    {
        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');

        foreach (["\$post_type === 'webauthn_login_options'", "\$post_type === 'webauthn_login_verify'"] as $route) {
            $this->assertStringContainsString($route, $identify);
        }
        // One session-opening code for both paths: a fix to it cannot miss one of them
        $this->assertStringContainsString('return identifyFinishLogin(', $this->between($identify, 'function identifyUser(', 'function identifyUserWithPasskey('));
        $passkey = $this->between($identify, 'function identifyUserWithPasskey(', 'function identifyFinishLogin(');
        $this->assertStringContainsString('return identifyFinishLogin(', $passkey);
        // The account comes from the verified passkey, and the password gates still apply
        $this->assertStringContainsString("\$username = (string) \$check['login'];", $passkey);
        $this->assertStringContainsString('identifyDoInitialChecks(', $passkey);
        $this->assertStringContainsString('webauthnLoginPasswordlessBlockedByMfa(', $passkey);

        $session = $this->between($identify, 'function buildUserSession(', 'function performPostLoginTasks(');
        $this->assertStringContainsString('if ($privateKeyClear !== null) {', $session);
        $postLogin = $this->between($identify, 'function performPostLoginTasks(', 'function shouldAdjustPermissionsFromRoleNames(');
        $this->assertStringContainsString("if (\$passwordClear !== '') {", $postLogin);
    }

    public function testEveryKeyRegenerationDropsThePasskeyCopies(): void
    {
        $sites = [
            'app/sources/main.functions.php' => ['function handleUserKeys(', 'function '],
            'app/sources/main.queries.php' => ['function initializeUserPassword(', 'function generateOneTimeCode('],
            'app/sources/users.queries.php' => ["case \"create_new_user_tasks\":", 'triggerBackgroundHandler();'],
        ];
        foreach ($sites as $file => [$start, $end]) {
            $source = (string) file_get_contents(__DIR__ . '/../../' . $file);
            $from = strpos($source, $start);
            $this->assertIsInt($from, $file);
            $to = strpos($source, $end, $from + strlen($start));
            $this->assertStringContainsString('invalidateUserPasskeyWraps(', substr($source, $from, ($to ?: strlen($source)) - $from), $file);
        }
        $queries = (string) file_get_contents(__DIR__ . '/../../app/sources/main.queries.php');
        $this->assertStringContainsString('invalidateUserPasskeyWraps(', $this->between($queries, 'function generateOneTimeCode(', "\n}\n"));

        $purge = (string) file_get_contents(__DIR__ . '/../../app/sources/users_purge.functions.php');
        $this->assertStringContainsString("DB::delete(prefixTable('user_webauthn_credentials'), 'user_id = %i', \$userId);", $purge);
    }

    public function testLoginPageOffersPasswordlessOnlyInPasswordlessMode(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/core/login.php');

        $this->assertMatchesRegularExpression("/webauthn_login_mode'\\] \\?\\? 0\\) === 2 \\? '\\s*<button type=\"button\" id=\"but_login_with_passkey\"/", $page);
    }

    public function testMfaMethodCountStubMatchesProduction(): void
    {
        $body = function (string $file): string {
            $source = (string) file_get_contents(__DIR__ . '/../../' . $file);
            return $this->between($source, 'function countEnabledMfaMethods(', "\n}\n");
        };

        $this->assertStringContainsString("'webauthn'", $body('app/sources/identify.php'));
        $this->assertSame($body('app/sources/identify.php'), $body('tests/Stubs/auth_pure_functions.php'));
    }

    public function testLoginPageOffersThePasskeyOnlyWhileTheFeatureIsOn(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/core/login.php');

        $this->assertMatchesRegularExpression("/webauthn_login_mode'\\] \\?\\? 0\\) !== 0 \\?\\s*'\\s*<label for=\"select2fa-webauthn\">/", $page);
        $this->assertStringContainsString('data-mfa="webauthn"', $page);
        $this->assertStringContainsString('id="div-2fa-webauthn" class="mb-3 div-2fa-method hidden"', $page);
    }

    public function testEnrolmentEmailAndLogLabelsExist(): void
    {
        $catalog = require __DIR__ . '/../../app/config/emails_templates.php';
        $this->assertSame('email_body_webauthn_login_added', $catalog['webauthn_login_added']['body_key'] ?? null);

        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        foreach (['at_user_webauthn_added', 'at_user_webauthn_deleted', 'at_user_webauthn_passwordless_enabled', 'at_user_webauthn_passwordless_disabled'] as $label) {
            $this->assertNotSame($label, formatAdminLogLabel($label, $lang), $label);
        }
        // failed_auth rows are labelled with their key
        foreach (['webauthn_login_2fa_failed', 'webauthn_login_passwordless_failed', 'webauthn_login_stepup_failed'] as $label) {
            $this->assertNotSame($label, $lang->get($label), $label);
        }
    }

    public function testAddingAPasskeyOrAPasswordlessCopyAsksForTheAccountFirst(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');

        // No ceremony is handed out before the step-up: the verify steps need that ceremony.
        $ceremonies = [
            ['function webauthnLoginRegisterOptions(', 'function webauthnLoginRegisterVerify('],
            ['function webauthnLoginPasswordlessOptions(', 'function webauthnLoginPasswordlessVerify('],
        ];
        foreach ($ceremonies as [$start, $end]) {
            $body = $this->between($functions, $start, $end);
            $check = strpos($body, 'webauthnLoginCheckStepUp(');
            $pending = strpos($body, '->set(TP_WEBAUTHN_LOGIN_PENDING_KEY');
            $this->assertIsInt($check, $start);
            $this->assertIsInt($pending, $start);
            $this->assertLessThan($pending, $check, $start);
        }

        // A wrong password is a failed authentication, refused while the account is locked
        $stepUp = $this->between($functions, 'function webauthnLoginCheckStepUp(', 'function webauthnLoginPasswordMatches(');
        $this->assertStringContainsString('getAuthenticationLockUntil(', $stepUp);
        $this->assertStringContainsString('addFailedAuthentication(', $stepUp);
        $this->assertStringContainsString("'webauthn_login_stepup_failed'", $stepUp);

        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');
        $this->assertStringContainsString(
            "\$session->set('user-authenticated_at', time());",
            $this->between($identify, 'function buildUserSession(', 'function performPostLoginTasks(')
        );

        $profile = (string) file_get_contents(__DIR__ . '/../../app/pages/profile.js.php');
        $this->assertStringContainsString("webauthnLoginStart('webauthn_login_register_options'", $profile);
        $this->assertStringContainsString("'webauthn_login_passwordless_options',", $profile);
        $this->assertStringNotContainsString("webauthnLoginPost('webauthn_login_register_options'", $profile);
        $this->assertStringNotContainsString("webauthnLoginPost('webauthn_login_passwordless_options'", $profile);
    }

    public function testRelyingPartyIdAndRequirePrfAreEnforcedWhenSaved(): void
    {
        $admin = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');

        // A relying party ID that does not suit the TeamPass URL is refused, never stored
        $rpId = $this->between($admin, "if (\$post_field === 'webauthn_rp_id') {", "require_once 'main.functions.php';");
        $this->assertStringContainsString('webauthnLoginRpIdIsValidFor(', $rpId);
        $this->assertStringContainsString('break;', $rpId);

        // Requiring PRF deletes the server copies already registered
        $purge = $this->between($admin, "if (\$post_field === 'webauthn_login_require_prf' && (int) \$post_value === 1) {", '// Keep local settings array aligned');
        $this->assertStringContainsString('TP_WEBAUTHN_LOGIN_WRAP_SERVER', $purge);
        $this->assertStringContainsString("'wrapped_private_key' => null", $purge);

        // Saved by 2fa.js.php once the administrator confirmed, not by the generic handler
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.php');
        $field = array_values(array_filter(
            explode("\n", $page),
            static fn (string $line): bool => str_contains($line, 'id="webauthn_rp_id"')
        ));
        $this->assertCount(1, $field);
        $this->assertMatchesRegularExpression('/class="[^"]*\bno-save\b/', $field[0]);
        $js = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.js.php');
        $this->assertStringContainsString("saveFieldValue(\$field, 'webauthn_rp_id', false);", $js);
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
