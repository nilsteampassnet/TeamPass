<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: how the self-registration group list (oauth_self_register_groups) is wired into the
 * sign-in, the OAuth2 synchronization and the OAuth page. The decisions themselves are covered
 * by Oauth2SelfRegisterLogicTest, the Entra group retrieval by OAuth2EntraGroupsTest.
 */
final class Oauth2SelfRegisterWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testTheSignInChecksTheGroupsBeforeCreatingTheAccount(): void
    {
        $identify = $this->source('app/sources/identify.php');
        $this->assertStringContainsString("require_once __DIR__ . '/oauth2_self_register_logic.php';", $identify);

        $check = $this->functionBody($identify, 'checkOauth2User');
        $gate = strpos($check, 'oauth2UserMayRegister(');
        $create = strpos($check, 'return createOauth2User(');
        $this->assertIsInt($gate);
        $this->assertIsInt($create);
        $this->assertLessThan($create, $gate, 'The group check must come before the account creation');

        // A refusal names its cause on the Logs page, with the groups received
        $this->assertStringContainsString("'failed_auth',\n                'oauth2_self_register_refused'", $check);
        $this->assertStringContainsString('oauth2SelfRegisterLogDetail(', $check);
        $this->assertStringContainsString("'message' => 'oauth2_self_register_refused'", $check);
    }

    public function testTheGroupsComeFromTheProviderAnswerNeverFromTheLoginForm(): void
    {
        $identify = $this->source('app/sources/identify.php');

        // The login form posts the provider answer back: its groups must not be trusted
        $this->assertStringContainsString('$oauth2Groups = oauth2SessionGroups();', $this->functionBody($identify, 'checkOauth2User'));
        $this->assertStringContainsString('$userInfo[\'groups\'] = oauth2SessionGroups();', $this->functionBody($identify, 'createOauth2User'));

        $sessionGroups = $this->functionBody($identify, 'oauth2SessionGroups');
        $this->assertStringContainsString("SessionManager::getSession()->get('userOauth2Info')", $sessionGroups);
        $this->assertStringContainsString("\$oauth2Info['oauth2LoginOngoing']", $sessionGroups);
    }

    public function testTheAllowedGroupsAreLeftOutOfTheRoleMatchingEverywhere(): void
    {
        $this->assertStringContainsString(
            'oauth2GroupsForRoleMatching(',
            $this->functionBody($this->source('app/sources/identify.php'), 'createOauth2User')
        );

        $sync = $this->between($this->source('app/sources/users.queries.php'), "case 'get_list_of_users_in_oauth2':", "\n        case '");
        $this->assertStringContainsString("require_once __DIR__ . '/oauth2_self_register_logic.php';", $sync);
        $this->assertStringContainsString('oauth2GroupsForRoleMatching($selfRegisterGroups, $oAuthUser[\'groups\'])', $sync);
    }

    public function testGroupsAreMatchedAgainstTheRoleTitlesAsTheyAreStored(): void
    {
        // Role titles are stored entity-encoded: a raw lookup missed "Équipe", "R&D", "O'Neil"
        $create = $this->functionBody($this->source('app/sources/identify.php'), 'externalAdCreateUser');
        $this->assertStringContainsString('oauth2RoleTitleCandidates(', $create);
        $this->assertStringContainsString('WHERE title IN %ls', $create);
        $this->assertStringNotContainsString('$group["displayName"]', $create);

        $sync = $this->between($this->source('app/sources/users.queries.php'), "case 'get_list_of_users_in_oauth2':", "\n        case '");
        $this->assertStringContainsString("\$titleToIdMap[oauth2RoleTitleAsGroupName((string) \$record['title'])]", $sync);
        $this->assertLessThan(
            strpos($sync, 'oauth2RoleTitleAsGroupName('),
            strpos($sync, "require_once __DIR__ . '/oauth2_self_register_logic.php';"),
            'The logic module must be loaded before the role titles are read'
        );
    }

    public function testNoCodeComparesTheWholeSettingWithOneGroupName(): void
    {
        // The pre-fix rule: "A,B" never matched anything, and any value let everybody in
        foreach (['app/sources/identify.php', 'app/sources/users.queries.php'] as $path) {
            $source = $this->source($path);
            $this->assertDoesNotMatchRegularExpression('/[!=]==\s*\$SETTINGS\[\'oauth_self_register_groups\'\]/', $source, $path);
            $this->assertDoesNotMatchRegularExpression('/\$SETTINGS\[\'oauth_self_register_groups\'\]\s*[!=]==/', $source, $path);
            $this->assertStringNotContainsString("empty(\$SETTINGS['oauth_self_register_groups'])", $source, $path);
        }
    }

    public function testTheEntraSignInReadsTheNestedGroupsThroughOneMethod(): void
    {
        foreach ([
            'app/vendor/teampassclasses/oauth2controller/src/OAuth2Controller.php',
            'app/includes/libraries/teampassclasses/oauth2controller/src/OAuth2Controller.php',
        ] as $path) {
            $controller = $this->source($path);
            $this->assertStringContainsString("\$userGroups = \$this->entraUserGroups('me', \$token->getToken());", $this->functionBody($controller, 'callback'), $path);
            $this->assertStringNotContainsString('/me/memberOf', $controller, $path);
        }
    }

    public function testTheOauthPageEscapesTheSetting(): void
    {
        $page = $this->source('app/pages/oauth.php');

        $this->assertStringNotContainsString("value='<?php echo \$SETTINGS['oauth_self_register_groups'] ?? ''; ?>'", $page);
        $this->assertStringContainsString(
            "htmlspecialchars(html_entity_decode((string) (\$SETTINGS['oauth_self_register_groups'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8')",
            $page
        );
    }

    public function testTheRefusalIsTranslated(): void
    {
        foreach (['english', 'french'] as $language) {
            $strings = require self::ROOT . '/app/includes/language/' . $language . '.php';
            $this->assertIsArray($strings);
            $this->assertNotEmpty($strings['oauth2_self_register_refused'] ?? '', $language);
            $this->assertStringContainsString('*', (string) $strings['oauth_self_register_groups_tip'], $language);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(self::ROOT . '/' . $path);
        $this->assertIsString($source, $path);

        return $source;
    }

    /** Body of a function or method, up to the next declaration. */
    private function functionBody(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        $this->assertIsInt($start, 'Function not found: ' . $name);
        $found = preg_match(
            '/\n[ \t]*(?:(?:public|protected|private|static)[ \t]+)*function[ \t]+\w+\(/',
            $source,
            $next,
            PREG_OFFSET_CAPTURE,
            $start + 1
        );

        return $found === 1 ? substr($source, $start, $next[0][1] - $start) : substr($source, $start);
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
