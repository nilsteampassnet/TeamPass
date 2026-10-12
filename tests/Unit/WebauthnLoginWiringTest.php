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

    public function testPasskeyActionsAreHiddenOutsideASecureContext(): void
    {
        $login = (string) file_get_contents(__DIR__ . '/../../app/core/login.js.php');
        $this->assertMatchesRegularExpression(
            "/if \\(window\\.isSecureContext !== true\\) \\{\\s*\\$\\('#but_login_with_passkey'\\)\\.addClass\\('hidden'\\);/",
            $login
        );
        $profile = (string) file_get_contents(__DIR__ . '/../../app/pages/profile.js.php');
        $this->assertStringContainsString("if (window.isSecureContext !== true && \$('#webauthn-login-add').length > 0) {", $profile);

        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.php');
        $this->assertStringContainsString('webauthnLoginOriginIsSecure($webauthnLoginOrigin) === false', $page);
        $this->assertStringContainsString("\$lang->get('webauthn_login_https_required')", $page);
    }

    public function testPasswordlessSignInRecoversLikeThePasswordSignIn(): void
    {
        // A login page that outlived its server-side session gets "ERROR SESSION EXPIRED" back
        // from identifyUserWithPasskey(); like the password path, it renews the key and resends.
        $login = (string) file_get_contents(__DIR__ . '/../../app/core/login.js.php');
        $ceremony = $this->between($login, 'function runPasswordlessCeremony(pending)', 'function runWebauthnSecondFactor(');
        $this->assertStringContainsString('return recoverFromStaleSessionKey(sendAssertion);', $ceremony);
        $this->assertStringNotContainsString('showLoginRequestError()', $ceremony);
        // The maintenance refusal has no message of its own: the page supplies the notice
        $this->assertStringContainsString("if (data.error === 'maintenance_mode_enabled') {", $ceremony);
        $this->assertStringContainsString("\$lang->get('index_maintenance_mode_admin')", $ceremony);

        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');
        $server = $this->between($identify, 'function identifyUserWithPasskey(', 'webauthnLoginPasswordlessLoginVerify(');
        $this->assertStringContainsString("echo 'ERROR SESSION EXPIRED';", $server);
    }

    public function testRequiringPrfIsConfirmedWhenItDeletesCopies(): void
    {
        // Turning the setting on deletes the server copies for good (admin.queries.php), and
        // turning it off does not bring them back: the page asks first, with their number.
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.php');
        $toggle = array_values(array_filter(
            explode("\n", $page),
            static fn (string $line): bool => str_contains($line, 'id="webauthn_login_require_prf"')
        ));
        $this->assertCount(1, $toggle);
        $this->assertMatchesRegularExpression('/class="[^"]*\bno-save\b/', $toggle[0]);
        $this->assertStringContainsString('data-server-copies="<?php echo $webauthnLoginServerCopyCount; ?>"', $toggle[0]);
        $this->assertStringContainsString("WHERE key_wrap_mode = %i',\n                                TP_WEBAUTHN_LOGIN_WRAP_SERVER", $page);

        // The generic toggle handler leaves a no-save toggle to its page
        $generic = (string) file_get_contents(__DIR__ . '/../../app/pages/admin.js.php');
        $handler = $this->between($generic, "\$('.toggle').on('toggle', function(e, active) {", '// .-> END. TOGGLES');
        $skip = "if (\$(e.target).hasClass('no-save') === true) {";
        $this->assertStringContainsString($skip, $handler);
        $this->assertLessThan(strpos($handler, '$.post('), strpos($handler, $skip));

        $js = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.js.php');
        $own = $this->between($js, "\$(document).on('toggle', '#webauthn_login_require_prf'", "\$(document).on('click', '#button-duo-config-check'");
        $this->assertStringContainsString("\$lang->get('webauthn_login_require_prf_confirm')", $own);
        $this->assertStringContainsString("saveFieldValue(\$input, 'webauthn_login_require_prf', true);", $own);
        // A cancelled confirmation puts the toggle back without saving anything
        $this->assertStringContainsString("\$toggle.data('toggles').toggle(false, false, true);", $own);

        $english = require __DIR__ . '/../../app/includes/language/english.php';
        $this->assertStringContainsString('#count#', (string) ($english['webauthn_login_require_prf_confirm'] ?? ''));
    }

    public function testTheVaultNeitherKeepsNorUsesAPasskeyOfItsOwnSignInPage(): void
    {
        // A sign-in passkey of the instance kept in an item would let whoever can open the item
        // sign in as its owner. Refused at creation, and at signature — the binding check, since
        // the signature covers the client data — before anything is read or written.
        $model = (string) file_get_contents(__DIR__ . '/../../app/api/Model/WebauthnModel.php');
        $guard = "webauthnLoginIsOwnCeremony((string) \$request['client_data_json'], \$SETTINGS) === true";

        $create = $this->between($model, 'public function createCredential(', 'public function listCredentials(');
        $this->assertStringContainsString($guard, $create);
        $this->assertLessThan(strpos($create, 'DB::startTransaction()'), strpos($create, $guard));

        $assert = $this->between($model, 'public function assertCredential(', 'public function deleteCredential(');
        $this->assertStringContainsString($guard, $assert);
        $this->assertLessThan(strpos($assert, 'DB::queryFirstRow('), strpos($assert, $guard));
        $this->assertLessThan(strpos($assert, 'webauthnSignAssertion('), strpos($assert, $guard));
    }

    public function testThePasswordlessRefusalReadsWhetherTheAccountIsReady(): void
    {
        // webauthnLoginPasswordlessRefusal() tells an account whose keys are being regenerated
        // from the other refusals with is_ready_for_usage: without the column it never would.
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');
        $verify = $this->between($functions, 'function webauthnLoginPasswordlessLoginVerify(', 'function webauthnLoginPrivateKeyMatches(');
        $this->assertStringContainsString('u.is_ready_for_usage', $verify);
        $this->assertStringContainsString('webauthnLoginPasswordlessRefusal($SETTINGS, $row)', $verify);
    }

    public function testPasskeysOfAPreviousRelyingPartyIdAreNeverAskedFor(): void
    {
        // Same column in a fresh install and in the upgrade, added apart for earlier 3.2.3 builds
        $column = "`rp_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Relying party id it was registered for',";
        $upgrade = (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.3.php');
        $install = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
        $this->assertStringContainsString($column, $this->between($upgrade, "user_webauthn_credentials` (", 'ENGINE=InnoDB'));
        $this->assertStringContainsString($column, $this->between($install, "user_webauthn_credentials` (", 'ENGINE=InnoDB'));
        $this->assertMatchesRegularExpression("/addColumnIfNotExist\\(\\s*\\\$pre \\. 'user_webauthn_credentials',\\s*'rp_id',/", $upgrade);

        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');
        $this->assertStringContainsString("'rp_id' => \$registeredRpId !== '' ? \$registeredRpId : null,", $functions);
        $secondFactor = $this->between($functions, 'function webauthnLoginSecondFactor(', 'function webauthnLoginPasswordlessLoginOptions(');
        $this->assertStringContainsString('webauthnLoginUserHasPasskey($userId, $rpId)', $secondFactor);
        $this->assertStringContainsString('webauthnLoginCredentialIds($userId, $rpId)', $secondFactor);
        $this->assertStringContainsString('(rp_id IS NULL OR rp_id = %s)', $this->between($functions, 'function webauthnLoginUserHasPasskey(', 'function webauthnLoginSecondFactor('));

        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');
        $this->assertStringContainsString("webauthnLoginUserHasPasskey((int) (\$userInfo['id'] ?? 0), webauthnLoginRpId(\$SETTINGS))", $identify);

        // Shown, not hidden: the owner and the administrators can delete them
        $this->assertStringContainsString('if (credential.usable === false) {', (string) file_get_contents(__DIR__ . '/../../app/pages/profile.js.php'));
        $this->assertStringContainsString('if (credential.usable === false) {', (string) file_get_contents(__DIR__ . '/../../app/pages/users.js.php'));
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

    public function testThePasswordlessChallengeNeverReplacesACeremonyInProgress(): void
    {
        // The login page fetches that challenge ahead of any click: it has a slot of its own, so
        // that it cannot overwrite the second factor the same session is waiting on.
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');

        $options = $this->between($functions, 'function webauthnLoginPasswordlessLoginOptions(', 'function webauthnLoginPasswordlessLoginVerify(');
        $this->assertStringContainsString('->set(TP_WEBAUTHN_LOGIN_PRELOGIN_KEY', $options);
        $this->assertStringNotContainsString('TP_WEBAUTHN_LOGIN_PENDING_KEY', $options);

        $take = $this->between($functions, 'function webauthnLoginTakePending(', 'function webauthnLoginRows(');
        $this->assertStringContainsString(
            "\$slot = \$purpose === 'passwordless_login' ? TP_WEBAUTHN_LOGIN_PRELOGIN_KEY : TP_WEBAUTHN_LOGIN_PENDING_KEY;",
            $take
        );
        $this->assertStringContainsString('$session->remove($slot);', $take);

        $secondFactor = $this->between($functions, 'function webauthnLoginSecondFactor(', 'function webauthnLoginPasswordlessLoginOptions(');
        $this->assertStringContainsString('->set(TP_WEBAUTHN_LOGIN_PENDING_KEY', $secondFactor);
    }

    public function testTheLoginPageKeepsAChallengeItCanStillSign(): void
    {
        require_once __DIR__ . '/../../app/sources/webauthn_login_logic.php';
        $login = (string) file_get_contents(__DIR__ . '/../../app/core/login.js.php');

        // Fetched when the page loads, so that the click itself starts the ceremony
        $this->assertMatchesRegularExpression(
            "/if \\(\\$\\('#but_login_with_passkey'\\)\\.length > 0 && window\\.isSecureContext === true\\) \\{\\s*prefetchPasswordlessChallenge\\(\\);/",
            $login
        );
        // Never signed once the server may have forgotten it by the time the user answers
        $this->assertSame(1, preg_match('/const passwordlessChallengeMaxAge = (\\d+);/', $login, $match));
        $this->assertLessThanOrEqual(
            TP_WEBAUTHN_LOGIN_CEREMONY_TTL * 1000 - TP_WEBAUTHN_LOGIN_TIMEOUT_MS,
            (int) $match[1]
        );
        // Fetched ahead, it dies with a session the page outlived: a new one is signed instead
        $ceremony = $this->between($login, 'function runPasswordlessCeremony(pending)', 'function runWebauthnSecondFactor(');
        $this->assertStringContainsString('return recoverFromStaleSessionKey(signNewPasswordlessChallenge);', $ceremony);
    }

    public function testAPasskeyDeletedHereIsReportedToTheDevice(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');
        $verify = $this->between($functions, 'function webauthnLoginPasswordlessLoginVerify(', 'function webauthnLoginPrivateKeyMatches(');
        // An unknown passkey says so, and still counts as a failed authentication
        $this->assertMatchesRegularExpression(
            "/if \\(\\\$row === null\\) \\{.*?'webauthn_login_unknown_passkey'\\), 'counted' => true, 'unknown' => true/s",
            $verify
        );

        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');
        $passkey = $this->between($identify, 'function identifyUserWithPasskey(', 'function identifyFinishLogin(');
        $this->assertStringContainsString("'unknown_credential' => (\$check['unknown'] ?? false) === true,", $passkey);

        $login = (string) file_get_contents(__DIR__ . '/../../app/core/login.js.php');
        $this->assertStringContainsString('window.tpWebauthnLogin.signalUnknown(pending.options.rpId, result.credential.rawId);', $login);

        // The profile reports the complete list, and nothing while the feature is off: the
        // passkeys are kept then, and a manager drops whatever the list omits
        $signal = $this->between($functions, 'function webauthnLoginSignalData(', 'function webauthnLoginRegisterOptions(');
        $this->assertStringContainsString("if (webauthnLoginMode(\$SETTINGS) === TP_WEBAUTHN_LOGIN_MODE_DISABLED || \$rpId === '') {\n        return null;", $signal);
        $this->assertStringContainsString('webauthnLoginCredentialIds($userId, $rpId)', $signal);
        $profile = (string) file_get_contents(__DIR__ . '/../../app/pages/profile.js.php');
        $load = $this->between($profile, 'function loadWebauthnLogin()', 'function webauthnLoginAskPassword()');
        $this->assertStringContainsString('if (data.error === false) {', $load);
        $this->assertStringContainsString('window.tpWebauthnLogin.signalAccepted(data.signal);', $load);
    }

    public function testTheProfileExplainsWhatTheListDoesNotSay(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');
        $list = $this->between($functions, 'function webauthnLoginList(', 'function webauthnLoginSignalData(');
        $this->assertStringContainsString("'notices' => webauthnLoginProfileNotices(\$SETTINGS, \$mfaEnabled, \$credentials),", $list);
        // MFA is read from the account, not from a session an administrator may have outdated
        $this->assertStringContainsString("'SELECT mfa_enabled FROM ' . prefixTable('users') . ' WHERE id = %i'", $list);

        $profile = (string) file_get_contents(__DIR__ . '/../../app/pages/profile.js.php');
        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        foreach (['not_asked', 'single_device'] as $notice) {
            $this->assertStringContainsString("'" . $notice . "' => \$lang->get('webauthn_login_notice_" . $notice . "'),", $profile);
            $this->assertNotSame('webauthn_login_notice_' . $notice, $lang->get('webauthn_login_notice_' . $notice));
        }

        // A passkey left unnamed takes the name of its manager
        $register = $this->between($functions, 'function webauthnLoginRegisterVerify(', 'function webauthnLoginRename(');
        $this->assertSame(2, substr_count($register, "webauthnLoginLabelOrDefault(\$data['label'] ?? '', \$lang, (string) \$record->aaguid)"));
    }

    public function testAPasskeySignInIsToldApartInTheConnectionLogs(): void
    {
        $identify = (string) file_get_contents(__DIR__ . '/../../app/sources/identify.php');
        $this->assertStringContainsString("(int) (\$check['passkey_id'] ?? 0)", $this->between($identify, 'function identifyUserWithPasskey(', 'function identifyFinishLogin('));
        $this->assertStringContainsString(
            '$passkeyId > 0 ? webauthnLoginConnectionMarker($passkeyId) : null,',
            $this->between($identify, 'function identifyFinishLogin(', 'function buildUserSession(')
        );
        // The label stays 'connection': the dashboards and the last-connection lookups filter on it
        $this->assertStringContainsString(
            "logEvents(\$SETTINGS, 'user_connection', 'connection', (string) \$userInfoId, stripslashes(\$username), \$signInMethod);",
            $identify
        );

        $logs = (string) file_get_contents(__DIR__ . '/../../app/sources/logs.datatables.php');
        $this->assertStringContainsString("require_once __DIR__ . '/webauthn_login_logic.php';", $logs);
        $this->assertStringContainsString("\$type === 'user_connection' ? webauthnLoginConnectionPasskeyId(\$field1) : null;", $logs);
        $this->assertStringContainsString("\$lang->get('webauthn_login_log_method')", $logs);
    }

    public function testAdministratorsSeeHowFarPasskeysAreAdopted(): void
    {
        $functions = (string) file_get_contents(__DIR__ . '/../../app/sources/webauthn_login.functions.php');

        // Users list: one query for the page, counting what a sign-in would accept
        $counts = $this->between($functions, 'function webauthnLoginCountsByUser(', 'function webauthnLoginAccountsWithPasskey(');
        $this->assertStringContainsString('WHERE user_id IN %li AND (rp_id IS NULL OR rp_id = %s)', $counts);
        $this->assertStringContainsString('GROUP BY user_id', $counts);
        $list = (string) file_get_contents(__DIR__ . '/../../app/sources/users.datatable.php');
        $this->assertStringContainsString("webauthnLoginCountsByUser(array_column(\$rows, 'id'), webauthnLoginRpId(\$SETTINGS))", $list);
        $this->assertStringContainsString('$passkeyBadge = webauthnLoginUsersBadge(', $list);
        // The count query comes after the test that opens the JSON array: DB::count() reads the last query
        $this->assertLessThan(strpos($list, 'webauthnLoginCountsByUser('), strpos($list, 'if (DB::count() > 0) {'));
        // Only numbers and translations reach the hand-built JSON, escaped for the attribute
        $badge = $this->between($list, '$passkeyBadge = webauthnLoginUsersBadge(', '// Inactive users management icon');
        $this->assertStringContainsString("htmlspecialchars(\$passkeyTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')", $badge);
        $this->assertStringContainsString("\$passkeyBadge['asked'] === true ? 'badge-info' : 'badge-warning'", $badge);

        // Dashboard: accounts that can sign in, system accounts left out like the other counters
        $adoption = $this->between($functions, 'function webauthnLoginAccountsWithPasskey(', 'function webauthnLoginSecondFactor(');
        $this->assertStringContainsString('COUNT(DISTINCT c.user_id)', $adoption);
        $this->assertStringContainsString('u.disabled = %i', $adoption);
        $this->assertStringContainsString('u.deleted_at IS NULL', $adoption);
        $this->assertStringContainsString("['tp', 'otv', 'api']", $adoption);

        $admin = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $stats = $this->between($admin, "case 'get_dashboard_stats':", "case 'get_live_activity':");
        $this->assertStringContainsString('webauthnLoginAccountsWithPasskey(webauthnLoginRpId($SETTINGS))', $stats);
        $this->assertStringContainsString("'passkeys' => \$usersWithPasskey,", $stats);
        // Shown only while the feature is on
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/admin.php');
        $this->assertMatchesRegularExpression(
            "/webauthn_login_mode'\\] \\?\\? 0\\) !== 0\\) \\{ \\?>.*?id=\"stat-users-passkeys\".*?<\\?php \\} \\?>/s",
            $page
        );
        $this->assertStringContainsString("\$('#stat-users-passkeys').text(", (string) file_get_contents(__DIR__ . '/../../app/pages/admin.js.php'));

        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        foreach (['webauthn_login_users_not_asked', 'webauthn_login_adoption_tip'] as $key) {
            $this->assertNotSame($key, $lang->get($key), $key);
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
