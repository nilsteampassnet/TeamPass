<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/user_keys_task_logic.php';

/**
 * Issue #5451: an LDAP account written into the users table without keys (directory
 * synchronization, legacy 'none' placeholder) crashed its first login with a TypeError.
 *
 * The login reads the private key from user_private_keys, so such an account comes back with
 * NULL, and handleExternalPasswordChange() passed it to decryptPrivateKey(string, string).
 * The account is now keyed like a new LDAP account: the create_user_keys task also builds its
 * sharekeys, which a key pair generated alone at login would never have.
 */
class LdapUserWithoutKeyPairTest extends TestCase
{
    public function testAbsentKeyValuesAreMissing(): void
    {
        self::assertTrue(userKeyIsMissing(null), 'No current user_private_keys row');
        self::assertTrue(userKeyIsMissing(''));
        self::assertTrue(userKeyIsMissing('none'), 'Legacy placeholder');
    }

    public function testRealKeyIsNotMissing(): void
    {
        self::assertFalse(userKeyIsMissing('dGVzdA=='));
        self::assertFalse(userKeyIsMissing('v2:bWV0YQ==:Y2lwaGVy'));
    }

    public function testAccountWithoutAnyKeyHasNoKeyPair(): void
    {
        self::assertTrue(userHasNoKeyPair(['private_key' => null, 'public_key' => null]));
        self::assertTrue(userHasNoKeyPair(['private_key' => 'none', 'public_key' => 'none']));
        self::assertTrue(userHasNoKeyPair(['private_key' => '', 'public_key' => '']));
        self::assertTrue(userHasNoKeyPair([]));
    }

    public function testAccountHoldingAPublicKeyKeepsItsKeyPair(): void
    {
        // Sharekeys may be encrypted with that public key: generating would replace the pair
        self::assertFalse(userHasNoKeyPair(['private_key' => null, 'public_key' => 'cHVibGlj']));
        self::assertFalse(userHasNoKeyPair(['private_key' => 'cHJpdmF0ZQ==', 'public_key' => 'cHVibGlj']));
    }

    public function testExternalPasswordChangeSkipsAccountWithoutPrivateKey(): void
    {
        $body = $this->functionBody(
            $this->source('app/sources/main.functions.php'),
            'function handleExternalPasswordChange('
        );

        $guard = strpos($body, 'userKeyIsMissing($userInfo[\'private_key\'] ?? null)');
        $decrypt = strpos($body, 'decryptPrivateKey($newPassword, $userInfo[\'private_key\'])');
        self::assertIsInt($guard, 'An account without private key must be skipped');
        self::assertIsInt($decrypt);
        self::assertLessThan($decrypt, $guard, 'The guard must run before decryptPrivateKey()');
    }

    public function testLdapLoginKeysAnExistingAccountWithoutKeyPair(): void
    {
        $body = $this->functionBody(
            $this->source('app/sources/identify.php'),
            'function authenticateThroughAD('
        );

        $decision = strpos($body, '$keyPairToGenerate = ');
        $creation = strpos($body, 'handleNewUser(');
        $groups = strpos($body, 'handleUserADGroups(');
        $generation = strpos($body, 'generateKeyPairForLdapUserWithoutKeys(');
        $finalize = strpos($body, 'finalizeAuthentication(');
        foreach ([$decision, $creation, $groups, $generation, $finalize] as $position) {
            self::assertIsInt($position);
        }

        // Decided on the loaded row: the array handleNewUser() returns carries no key either
        self::assertLessThan($creation, $decision, 'A just-created account must not be keyed twice');
        self::assertLessThan($generation, $groups, 'The AD roles must be set before the keys task runs');
        self::assertLessThan($finalize, $generation, 'A queued generation must skip finalizeAuthentication()');

        $decisionLine = substr($body, $decision, (int) strpos($body, ';', $decision) - $decision);
        self::assertStringContainsString("empty(\$userInfo['ldap_user_to_be_created']) === true", $decisionLine);
        self::assertStringContainsString("(int) (\$userInfo['disabled'] ?? 0) !== 1", $decisionLine);
        self::assertStringContainsString('userHasNoKeyPair($userInfo) === true', $decisionLine);

        // The login stops as for a new account ("ad_user_created")
        self::assertStringContainsString("\$userInfo['has_been_created'] = 1;", substr($body, $generation, $finalize - $generation));
    }

    public function testGenerationMirrorsTheNewAccountOne(): void
    {
        $src = $this->source('app/sources/identify.php');
        $body = $this->functionBody($src, 'function generateKeyPairForLdapUserWithoutKeys(');

        self::assertStringContainsString('handleUserKeys(', $body);
        self::assertStringContainsString("'email_body_user_config_2'", $body);
        // handleUserKeys() answers with an encoded payload: its error flag must be read back
        self::assertStringContainsString("'decode'", $body);
        self::assertStringContainsString("(\$result['error'] ?? true) !== false", $body);
    }

    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    private function functionBody(string $src, string $signature): string
    {
        $start = strpos($src, $signature);
        self::assertIsInt($start, $signature . ' not found');
        $next = strpos($src, "\nfunction ", $start + strlen($signature));
        return substr($src, $start, $next === false ? null : $next - $start);
    }
}
