<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/main_queries_logic.php';

/**
 * A password change or recovery must end the sessions opened before it (GHSA-p84q-3h9h-qvh6).
 *
 * The web session is accepted while users.key_tempo equals its key (PerformChecks), so a change
 * that leaves key_tempo and the session id alone keeps a stolen session alive. A change made by
 * the user regenerates the session id (only a stolen cookie can share it); a recovery or a key
 * generation for another account clears key_tempo, like an administrator disconnect. A session
 * holder must also never set the account password without proving the current one.
 */
class CredentialChangeSessionRevocationTest extends TestCase
{
    private function source(string $file): string
    {
        $path = __DIR__ . '/../../app/sources/' . $file;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    /**
     * Returns the part of $file between two markers.
     */
    private function block(string $file, string $startMarker, string $endMarker): string
    {
        $content = $this->source($file);
        $start = strpos($content, $startMarker);
        self::assertNotFalse($start, 'Marker not found: ' . $startMarker);
        $end = strpos($content, $endMarker, $start + strlen($startMarker));
        self::assertNotFalse($end, 'Marker not found: ' . $endMarker);

        return substr($content, $start, $end - $start);
    }

    public function testGeneratedPasswordOnlyForAnotherAccount(): void
    {
        // An administrator or manager resetting another account
        self::assertTrue(userKeysGenerationMayGeneratePassword(true, 12, 3, true));

        // The caller's own account, with or without an explicit user_id
        self::assertFalse(userKeysGenerationMayGeneratePassword(false, 3, 3, true));
        self::assertFalse(userKeysGenerationMayGeneratePassword(true, 3, 3, true));

        // Not requested, or no valid target
        self::assertFalse(userKeysGenerationMayGeneratePassword(true, 12, 3, false));
        self::assertFalse(userKeysGenerationMayGeneratePassword(true, 0, 3, true));
    }

    public function testSessionExtensionIsCappedAtTheMaximumDuration(): void
    {
        $now = 1_800_000_000;

        // Within the limit
        self::assertSame($now + 1800 + 600, sessionExtensionEnd($now + 1800, 600, $now, 60));

        // Beyond it: capped at now + 60 minutes
        self::assertSame($now + 3600, sessionExtensionEnd($now + 1800, 999_999_999, $now, 60));

        // A non-positive duration changes nothing
        self::assertSame($now + 1800, sessionExtensionEnd($now + 1800, 0, $now, 60));
        self::assertSame($now + 1800, sessionExtensionEnd($now + 1800, -3600, $now, 60));

        // Never shortened, even when the session already ends past the cap
        self::assertSame($now + 7200, sessionExtensionEnd($now + 7200, 600, $now, 60));
    }

    public function testKeyGenerationNeverGeneratesAPasswordForTheCaller(): void
    {
        $block = $this->block('main.queries.php', "case 'user_new_keys_generation'://action_key", "case 'user_only_personal_items_encryption'");

        self::assertStringContainsString('userKeysGenerationMayGeneratePassword(', $block);
        self::assertStringContainsString('if ($generateUserNewPassword === false) {', $block);
        self::assertStringNotContainsString(
            "\$dataReceived['generate_user_new_password'] !== true",
            $block,
            'The client flag alone must not skip the password check.'
        );

        // The decision, not the raw flag, reaches handleUserKeys()
        $call = substr($block, (int) strpos($block, 'handleUserKeys('));
        self::assertMatchesRegularExpression(
            '/\(bool\) filter_var\(\$dataReceived\[\'encrypt_with_user_pwd\'\], FILTER_VALIDATE_BOOLEAN\),\s*\$generateUserNewPassword,/',
            $call
        );
    }

    public function testKeyGenerationForAnotherAccountEndsItsSessions(): void
    {
        $block = $this->block('main.queries.php', "case 'user_new_keys_generation'://action_key", "case 'user_only_personal_items_encryption'");

        $call = strpos($block, 'handleUserKeys(');
        $revoke = strpos($block, 'tpRevokeUserSessions($keysTargetUserId)');
        self::assertNotFalse($call);
        self::assertNotFalse($revoke);
        self::assertGreaterThan($call, $revoke, 'Sessions end once the new keys are in place.');
        self::assertStringContainsString('if ($keysForAnotherUser === true) {', $block);
    }

    public function testDialogPasswordChangeRenewsTheSession(): void
    {
        $block = $this->block('main.queries.php', 'function changeUserAuthenticationPassword(', '* Change user LDAP auth password');

        $write = strpos($block, 'insertPrivateKeyWithCurrentFlag(');
        $renew = strpos($block, 'tpRenewSessionAfterPasswordChange($post_user_id);');
        self::assertNotFalse($write);
        self::assertNotFalse($renew);
        self::assertGreaterThan($write, $renew);
    }

    public function testProfilePasswordChangeRequiresTheCurrentPassword(): void
    {
        $block = $this->block('main.queries.php', 'function changePassword(', 'function generateQRCode(');

        $check = strpos($block, "verifyPassword((string) \$dataUser['pw'], \$post_current_password) === false");
        $write = strpos($block, "'pw' => \$post_new_password_hashed,");
        self::assertNotFalse($check, 'The current password must be verified against the stored hash.');
        self::assertNotFalse($write);
        self::assertLessThan($write, $check, 'The check must come before any write.');

        $renew = strpos($block, 'tpRenewSessionAfterPasswordChange($post_user_id);');
        self::assertNotFalse($renew);
        self::assertGreaterThan($write, $renew);
    }

    public function testRecoveryEndsEverySessionOfTheAccount(): void
    {
        $content = $this->source('reset-password.php');

        $reset = strpos($content, 'handleUserKeys(');
        $revoke = strpos($content, "tpRevokeUserSessions((int) \$userInfo['id']);");
        self::assertNotFalse($reset);
        self::assertNotFalse($revoke);
        self::assertGreaterThan($reset, $revoke);
    }

    public function testSessionExtensionGoesThroughTheCap(): void
    {
        $block = $this->block('main.queries.php', 'function increaseSessionDuration(', 'function generateAnOTP(');

        self::assertStringContainsString('sessionExtensionEnd(', $block);
        self::assertStringNotContainsString("(int) \$session->get('user-session_duration') + \$duration", $block);
    }

    public function testRevocationHelpersMirrorTheAdministratorDisconnect(): void
    {
        $revoke = $this->block('main.functions.php', 'function tpRevokeUserSessions(', 'function tpRenewSessionAfterPasswordChange(');
        self::assertStringContainsString("'key_tempo' => ''", $revoke);
        self::assertStringContainsString("'session_end' => ''", $revoke);
        self::assertStringContainsString('tpInvalidateUserApiSession($userId);', $revoke);

        $renew = $this->block('main.functions.php', 'function tpRenewSessionAfterPasswordChange(', 'function tpFinishRequestEarly(');
        self::assertStringContainsString('->migrate(true)', $renew, 'The old session id must be destroyed, not kept.');
        self::assertStringContainsString('tpInvalidateUserApiSession($userId);', $renew);
    }
}
