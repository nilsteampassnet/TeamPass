<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/log_display_logic.php';

/**
 * Sentinel: how the OAuth2 identity binding (users.oauth2_identity) is wired into the sign-in,
 * the account creation, the administration and the schema. The decisions themselves are covered
 * by Oauth2IdentityLogicTest, the identity sources by OAuth2GenericProviderTest.
 */
final class Oauth2IdentityBindingWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testTheIdentityIsCheckedBeforeAnAccountIsTakenOver(): void
    {
        $identify = $this->source('app/sources/identify.php');
        $this->assertStringContainsString("require_once __DIR__ . '/oauth2_identity_logic.php';", $identify);

        $should = $this->functionBody($identify, 'shouldUserAuthWithOauth2');
        $check = strpos($should, 'oauth2CheckAccountIdentity($SETTINGS, $userInfo, $username)');
        $conversion = strpos($should, "'auth_type' => 'oauth2'");
        $this->assertIsInt($check);
        $this->assertIsInt($conversion);
        $this->assertLessThan($conversion, $check, 'The local/LDAP conversion must come after the identity check');
        // The name binding of GHSA-2mvr stays in front of everything
        $this->assertLessThan($check, (int) strpos($should, 'isOauth2LoginBoundToUser($username) === false'));
    }

    public function testTheCheckWritesNothing(): void
    {
        $check = $this->functionBody($this->source('app/sources/identify.php'), 'oauth2CheckAccountIdentity');

        $this->assertStringNotContainsString('DB::update', $check);
        $this->assertStringNotContainsString('DB::insert', $check);
        $this->assertStringContainsString("logEvents(\$SETTINGS, 'failed_auth', \$refusalKey", $check);
    }

    public function testTheBindingIsWrittenOnlyOnceTheSignInSucceeded(): void
    {
        $identify = $this->source('app/sources/identify.php');

        // The ordinary sign-in binds only after it has built the authenticated session.
        $finish = $this->functionBody($identify, 'identifyFinishLogin');
        $session = strpos($finish, '$sessionData = buildUserSession(');
        $keyError = strpos($finish, "\$lang->get('private_key_decryption_failed')");
        $persist = strpos($finish, 'oauth2PersistSessionIdentity($SETTINGS, (int) $userInfo[\'id\'], $username);');
        $this->assertIsInt($session);
        $this->assertIsInt($keyError);
        $this->assertIsInt($persist);
        $this->assertGreaterThan($keyError, $persist);

        // Only two writers of the column: the deferred binding and the account creation
        $this->assertSame(1, substr_count($identify, "'oauth2_identity' =>"));
        $this->assertStringContainsString("['oauth2_identity' => \$identity]", $this->functionBody($identify, 'oauth2PersistSessionIdentity'));
        $this->assertStringContainsString("'id = %i AND (oauth2_identity IS NULL OR oauth2_identity = %s) AND auth_type = %s'", $this->functionBody($identify, 'oauth2PersistSessionIdentity'));
        $this->assertStringContainsString("\$userData['oauth2_identity'] = \$oauth2Identity;", $this->functionBody($identify, 'externalAdCreateUser'));
    }

    /** The provisioning secret stays on the server and the worker leaves the existing pair in place. */
    public function testTheImportQueuesKeyDistributionWithoutReturningACode(): void
    {
        $users = $this->source('app/sources/users.queries.php');
        $import = $this->caseBody($users, 'add_user_from_ad');
        $queue = $this->functionBody($users, 'queueImportedOauth2KeyDistribution');

        $this->assertStringContainsString('queueImportedOauth2KeyDistribution($newUserId, $password, $SETTINGS)', $import);
        $this->assertStringContainsString("'user_code' => \$oauth2Import ? '' : \$password", $import);
        $this->assertStringContainsString("cryption(\$bootstrapPassword, '', 'encrypt', \$SETTINGS)", $queue);
        $this->assertStringContainsString("'final_special_after_generation' => 'oauth2-key-setup'", $queue);
        $this->assertStringContainsString("'email_body' => 'email_body_user_config_2'", $queue);
    }

    /** A pending import can only open and rewrap its existing key after authentication. */
    public function testAnImportedAccountKeepsItsKeyPairAfterMfa(): void
    {
        $identify = $this->source('app/sources/identify.php');
        $finish = $this->functionBody($identify, 'identifyFinishLogin');
        $setup = $this->functionBody($identify, 'completeImportedOauth2KeySetup');

        $this->assertStringContainsString("oauth2KeyPasswordFromSession(\$session->get('userOauth2Info'), \$username)", $finish);
        $this->assertStringContainsString('completeImportedOauth2KeySetup($userInfo, $username, $passwordClear, $SETTINGS)', $finish);
        $this->assertStringContainsString('decryptPrivateKeyBackup(', $setup);
        $this->assertStringNotContainsString('generateUserKeys(', $setup);
        $this->assertStringContainsString('FOR UPDATE', $setup);
        $this->assertStringContainsString('hash_equals($identity, (string) $boundIdentity)', $setup);
        $this->assertStringContainsString('insertPrivateKeyWithCurrentFlag($userId, $privateKey)', $setup);
        $this->assertStringContainsString("'otp_provided' => 1", $setup);
        $this->assertStringContainsString("'special' => 'none'", $setup);
    }

    public function testANewAccountIsBoundAtCreationAndNeverDuplicatesAnIdentity(): void
    {
        $create = $this->functionBody($this->source('app/sources/identify.php'), 'createOauth2User');

        $collision = strpos($create, 'oauth2IdentityBoundElsewhere($oauth2Identity, 0)');
        $insert = strpos($create, '$ret = externalAdCreateUser(');
        $this->assertIsInt($collision);
        $this->assertIsInt($insert);
        $this->assertLessThan($insert, $collision, 'The UNIQUE key must never be what refuses a renamed user');
        $this->assertStringContainsString("            \$SETTINGS,\n            \$oauth2Identity\n        );", $create);
    }

    public function testTheControllerPublishesTheIdentityOfBothProviders(): void
    {
        $callback = $this->functionBody(
            $this->source('app/vendor/teampassclasses/oauth2controller/src/OAuth2Controller.php'),
            'callback'
        );

        $this->assertStringContainsString('$identity = self::entraIdentity($user);', $callback);
        $this->assertStringContainsString('$identity = self::genericIdentity(', $callback);
        $this->assertStringContainsString("'oauth2LoginOngoing' => true), \$identity)", $callback);
    }

    public function testChangingTheAuthenticationTypeReleasesTheBinding(): void
    {
        $queries = $this->source('app/sources/users.queries.php');

        $this->assertStringContainsString(
            'usersResetOauth2Identity((int) $post_user_id, $SETTINGS);',
            $this->caseBody($queries, 'save_user_change')
        );
        $this->assertStringContainsString(
            'usersResetOauth2Identity((int) $post_id, $SETTINGS);',
            $this->caseBody($queries, 'change_user_auth_type')
        );

        // The Users page action rewrites the unchanged type, which is all a generic provider has
        $page = $this->source('app/pages/users.js.php');
        $action = $this->between($page, "data('action') === 'oauth2-identity-reset'", "data('action') === 'new-enc-code'");
        $this->assertStringContainsString("type: 'save_user_change'", $action);
        $this->assertStringContainsString("'field': 'auth_type'", $action);
        $this->assertStringContainsString("'value': 'oauth2'", $action);
    }

    public function testInstallAndUpgradeDeclareTheSameUniqueColumn(): void
    {
        $definition = "`oauth2_identity` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin";

        $install = $this->source('public/install/install-steps/run.step5.php');
        $this->assertStringContainsString($definition, $install);
        $this->assertStringContainsString('UNIQUE KEY `oauth2_identity` (`oauth2_identity`)', $install);
        $this->assertStringContainsString("array('admin', 'oauth2_link_existing_accounts', '0'),", $install);

        $upgrade = $this->source('public/install/upgrade_run_3.2.4.php');
        $this->assertStringContainsString('ADD COLUMN ' . $definition, $upgrade);
        $this->assertStringContainsString('ADD UNIQUE KEY `oauth2_identity` (`oauth2_identity`)', $upgrade);
        $this->assertStringContainsString("INSERT IGNORE INTO `\" . \$pre . \"misc`", $upgrade);
        $this->assertStringContainsString("'oauth2_link_existing_accounts'", $upgrade);
        // tests/Fixtures/email_upgrade_failure_probe.php evaluates the script from the email block
        // on, with mocked mysqli functions: the OAuth2 block must stay in front of it
        $this->assertLessThan(
            (int) strpos($upgrade, '$emailUpgradeFailure'),
            (int) strpos($upgrade, '$oauth2IdentityUpgradeFailure')
        );
    }

    public function testRefusalsAndEventsAreReadableInTheLogs(): void
    {
        foreach (['english', 'french'] as $language) {
            $lang = new Language($language, self::ROOT . '/app/includes/language');
            // failed_auth rows are labelled with their key
            foreach (['oauth2_identity_mismatch', 'oauth2_identity_already_bound', 'oauth2_link_disabled', 'oauth2_identity_missing'] as $key) {
                $this->assertNotSame($key, $lang->get($key), $language . ': ' . $key);
            }
            foreach (['at_user_oauth2_identity_bound', 'at_user_oauth2_identity_reset'] as $label) {
                $this->assertNotSame($label, formatAdminLogLabel($label, $lang), $language . ': ' . $label);
            }
            foreach (['oauth2_link_existing_accounts', 'oauth2_link_existing_accounts_tip', 'oauth2_identity_reset', 'oauth2_identity_reset_confirm'] as $key) {
                $this->assertNotSame($key, $lang->get($key), $language . ': ' . $key);
            }
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(self::ROOT . '/' . $path);
        $this->assertIsString($source, $path);

        return str_replace("\r\n", "\n", $source);
    }

    /** Body of a function or method, up to the next declaration. */
    private function functionBody(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertIsInt($start, 'Function not found: ' . $name);
        // The next declaration starts a line; the word "function" alone also appears in comments
        $found = preg_match(
            '/\n[ \t]*(?:(?:public|protected|private|static)[ \t]+)*function[ \t]+\w+\(/',
            $source,
            $next,
            PREG_OFFSET_CAPTURE,
            $start + 1
        );

        return $found === 1 ? substr($source, $start, $next[0][1] - $start) : substr($source, $start);
    }

    /** Body of a case of the users.queries.php dispatcher, up to the next case. */
    private function caseBody(string $source, string $case): string
    {
        return $this->between($source, "case '" . $case . "':", "\n        case '");
    }

    private function between(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        $this->assertIsInt($start, 'Start marker not found: ' . $startMarker);
        $end = strpos($source, $endMarker, $start + strlen($startMarker));
        $this->assertIsInt($end, 'End marker not found: ' . $endMarker);

        return substr($source, $start, $end - $start);
    }
}
