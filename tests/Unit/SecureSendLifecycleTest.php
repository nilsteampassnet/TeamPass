<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendLifecycleTest extends TestCase
{
    private array $settings;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../app/sources/otp.functions.php';
        require_once __DIR__ . '/../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../app/sources/secure_send.functions.php';
        require_once __DIR__ . '/../../app/sources/secure_send_snapshot.php';
        DB::reset();
        $this->settings = ['otv_is_enabled' => 1, 'cpassman_url' => 'https://vault.example.com',
            'otv_subdomain' => 'https://share.example.com/vault', 'otv_expiration_period' => 7,
            'secure_send_max_views' => 5, 'secure_send_allow_notes' => 1];
    }

    private function create(array $overrides = []): array
    {
        $result = secureSendFixtureCreate($overrides);
        parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $parameters);
        return secureSendRequestParameters($parameters);
    }

    private function page(array $parameters, string $method, array &$tokens, array $extra = []): array
    {
        return secureSendPrepareRecipient($parameters + $extra, $method, $this->settings, $tokens);
    }

    /** Reject a wrong public host before confirmation, decryption or view consumption. */
    public function testPublicHostIsCheckedOnBothConfirmationAndReveal(): void
    {
        foreach (['item', 'note'] as $type) {
            DB::reset();
            $parameters = $this->create(['send_type' => $type, 'shared_globaly' => 1]);
            $tokens = [];
            $bad = secureSendPrepareRecipient($parameters, 'GET', $this->settings, $tokens, 'vault.example.com');
            self::assertSame('secure_send_invalid_link', $bad['error']);
            self::assertSame('', $bad['token']);
            $page = secureSendPrepareRecipient($parameters, 'GET', $this->settings, $tokens, 'share.example.com:443');
            self::assertNotEmpty($page['token']);
            $post = $parameters + ['confirmation' => $page['token']];
            self::assertSame('secure_send_invalid_link', secureSendPrepareRecipient($post, 'POST', $this->settings, $tokens, 'evil.test')['error']);
            self::assertSame('invalid_link', secureSendRedeem($parameters, '', $this->settings, 'evil.test')['error']);
            self::assertSame(0, DB::$links[1]['views']);
            self::assertSame('', secureSendPrepareRecipient($post, 'POST', $this->settings, $tokens, 'share.example.com')['result']['error']);
        }
    }

    /** Preserve internal links behind proxies and through LAN aliases. */
    public function testInternalRevealToleratesRewrittenHostAndUnderscores(): void
    {
        $this->settings['cpassman_url'] = 'https://tp_internal/vault';
        $this->settings['otv_subdomain'] = 'invalid/path';
        $parameters = $this->create();
        $tokens = [];
        $page = secureSendPrepareRecipient($parameters, 'GET', $this->settings, $tokens, 'proxy_backend:8080');
        self::assertNotEmpty($page['token']);
        $post = $parameters + ['confirmation' => $page['token']];
        self::assertSame('', secureSendPrepareRecipient($post, 'POST', $this->settings, $tokens, 'dns-alias')['result']['error']);
    }

    public function testGetAndUnconfirmedPostDoNotRevealOrConsume(): void
    {
        $parameters = $this->create();
        $tokens = [];
        $page = $this->page($parameters, 'GET', $tokens);
        self::assertNotEmpty($page['token']);
        self::assertNull($page['result']);
        self::assertStringNotContainsString(DB::$password, json_encode($page));
        self::assertSame(0, DB::$links[1]['views']);
        $page = $this->page($parameters, 'POST', $tokens, ['confirmation' => 'forged']);
        self::assertSame('secure_send_confirmation_expired', $page['error']);
        self::assertNull($page['result']);
        self::assertSame(0, DB::$links[1]['views']);
        self::assertSame([], DB::$audit);
    }

    public function testConfirmationIsBoundToSessionLinkAndSecretAndCannotBeReplayed(): void
    {
        $parameters = $this->create(['views' => 3]);
        $tokens = [];
        $page = $this->page($parameters, 'GET', $tokens);
        $token = $page['token'];
        $otherSession = [];
        self::assertSame('secure_send_confirmation_expired', $this->page($parameters, 'POST', $otherSession, ['confirmation' => $token])['error']);
        $wrongSecret = array_replace($parameters, ['key' => 'other-secret']);
        self::assertSame('secure_send_confirmation_expired', $this->page($wrongSecret, 'POST', $tokens, ['confirmation' => $token])['error']);
        $success = $this->page($parameters, 'POST', $tokens, ['confirmation' => $token]);
        self::assertSame('', $success['result']['error']);
        self::assertSame(1, DB::$links[1]['views']);
        self::assertSame('secure_send_confirmation_expired', $this->page($parameters, 'POST', $tokens, ['confirmation' => $token])['error']);
        self::assertSame(1, DB::$links[1]['views']);
    }

    public function testOneViewIsConsumedOnlyOnSuccessfulReveal(): void
    {
        $parameters = $this->create();
        $tokens = [];
        $page = $this->page($parameters, 'GET', $tokens);
        $page = $this->page($parameters, 'POST', $tokens, ['confirmation' => $page['token']]);
        self::assertSame(DB::$password, $page['result']['fields']['password']);
        self::assertSame(0, $page['result']['remaining_views']);
        self::assertSame(1, DB::$links[1]['views']);
        self::assertCount(1, DB::$audit);
        self::assertSame('invalid_link', secureSendRedeem($parameters, '', $this->settings)['error']);
    }

    /** Nullable database fields must remain renderable after a one-view link is committed. */
    public function testNullableItemFieldsAreStringsAtRedemption(): void
    {
        require_once __DIR__ . '/../../app/sources/otv_render_logic.php';
        DB::$item['login'] = DB::$item['url'] = DB::$item['description'] = null;
        $parameters = $this->create();
        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('', $result['error']);
        foreach (['label', 'login', 'url', 'description', 'password'] as $field) {
            self::assertIsString($result['fields'][$field], $field);
        }
        self::assertSame('', otvRenderPlainField($result['fields']['login']));
        self::assertSame('', otvRenderPlainField($result['fields']['url']));
        self::assertSame('', otvSanitizeDescription($result['fields']['description']));
        self::assertSame(DB::$password, $result['fields']['password']);
        self::assertSame(1, DB::$links[1]['views']);
        self::assertCount(1, DB::$audit);
        self::assertSame('invalid_link', secureSendRedeem($parameters, '', $this->settings)['error']);
    }

    public function testDeletionOrRemovedAccessBetweenGetAndPostBlocksAndRevokes(): void
    {
        foreach (['deleted', 'access', 'disabled-user'] as $case) {
            DB::reset();
            $parameters = $this->create();
            $tokens = [];
            $page = $this->page($parameters, 'GET', $tokens);
            if ($case === 'deleted') { DB::$item['inactif'] = 1; }
            if ($case === 'access') { DB::$access = false; }
            if ($case === 'disabled-user') { DB::$activeUser = false; }
            $page = $this->page($parameters, 'POST', $tokens, ['confirmation' => $page['token']]);
            self::assertSame('invalid_link', $page['result']['error']);
            self::assertSame([], DB::$links);
            self::assertSame([], DB::$audit);
        }
    }

    public function testWrongPassphraseConsumesNoViewAndFiveFailuresRevoke(): void
    {
        $parameters = $this->create(['passphrase' => ' phrase with spaces ']);
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $result = secureSendRedeem($parameters, 'wrong', $this->settings);
            self::assertSame($attempt === 5 ? 'too_many_attempts' : 'wrong_passphrase', $result['error']);
            if ($attempt < 5) {
                self::assertSame(0, DB::$links[1]['views']);
                self::assertSame($attempt, DB::$links[1]['failed_attempts']);
            }
        }
        self::assertSame([], DB::$links);
        self::assertSame([], DB::$audit);
    }

    public function testPassphraseIsRequiredAndNeverTrimmed(): void
    {
        $parameters = $this->create(['passphrase' => ' exact phrase ']);
        self::assertSame('passphrase_required', secureSendRedeem($parameters, '', $this->settings)['error']);
        self::assertSame(0, DB::$links[1]['failed_attempts']);
        self::assertSame('', secureSendRedeem($parameters, ' exact phrase ', $this->settings)['error']);
    }

    public function testTamperedLinkDoesNotReveal(): void
    {
        $parameters = $this->create();

        $parameters['key'] = 'tampered';
        self::assertSame('invalid_link', secureSendRedeem($parameters, '', $this->settings)['error']);
        self::assertSame(0, DB::$links[1]['views']);
    }

    public function testStandaloneNotesRemainReadable(): void
    {
        $parameters = $this->create(['send_type' => 'note', 'payload' => ['title' => 'Note', 'secret' => 'standalone']]);
        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('standalone', $result['fields']['secret']);
    }

    /** Authenticated but malformed payloads and broken storage never penalize the recipient. */
    public function testCorruptStoredDataDoesNotConsumeAttemptsOrViews(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'tp-otv-test-');
        $previous = ini_set('error_log', $log);
        try {
            foreach (['json', 'missing-field', 'wrong-type', 'stored-key', 'ciphertext', 'unwrap-environment', 'cipher-environment'] as $case) {
                DB::reset();
                $parameters = $this->create(['send_type' => 'note', 'passphrase' => 'correct']);
                $key = defuse_validate_personal_key(hash('sha256', $parameters['key'] . '|correct'), DB::$links[1]['protected_key']);
                if (in_array($case, ['json', 'missing-field', 'wrong-type'], true)) {
                    $payload = match ($case) {
                        'json' => '{synthetic-corrupt-payload',
                        'missing-field' => '{"secret":"synthetic-corrupt-payload"}',
                        default => '{"title":"","secret":"synthetic-corrupt-payload","note":null,"login":"","url":""}',
                    };
                    DB::$links[1]['encrypted'] = cryption($payload, $key, 'encrypt')['string'];
                } elseif ($case === 'stored-key') {
                    DB::$links[1]['protected_key'] = 'invalid-stored-key';
                } elseif ($case === 'ciphertext') {
                    DB::$links[1]['encrypted'] = 'invalid-stored-ciphertext';
                } elseif ($case === 'unwrap-environment') {
                    DB::$unwrapError = 'Error - Major issue as the encryption is broken.';
                } else {
                    DB::$cipherError = 'environment_error';
                }
                DB::$links[1]['failed_attempts'] = 2;
                // Repeated attempts must neither revoke the link nor disclose any payload.
                for ($attempt = 0; $attempt < 6; ++$attempt) {
                    $result = secureSendRedeem($parameters, 'correct', $this->settings);
                    self::assertSame(['error' => 'server_error'], $result, $case);
                    self::assertSame(2, DB::$links[1]['failed_attempts'], $case);
                    self::assertSame(0, DB::$links[1]['views'], $case);
                    self::assertSame([], DB::$audit, $case);
                }
            }
            self::assertStringNotContainsString('synthetic-corrupt-payload', file_get_contents($log));
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

    /** Invalid raw keys in historical URLs are still counted as credential failures. */
    public function testIncorrectLegacyKeysCountAsAttempts(): void
    {
        $parameters = $this->create();
        DB::$links[1]['protected_key'] = null;
        foreach (['malformed', \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString()] as $offset => $key) {
            $parameters['key'] = $key;
            self::assertSame(['error' => 'invalid_link'], secureSendRedeem($parameters, '', $this->settings));
            self::assertSame($offset + 1, DB::$links[1]['failed_attempts']);
            self::assertSame(0, DB::$links[1]['views']);
        }
    }

    public function testLegacyRawPasswordAndWrappedPasswordLinksRemainReadable(): void
    {
        foreach ([false, true] as $wrapped) {
            DB::reset();
            $parameters = $this->create();
            $row = &DB::$links[1];
            $key = defuse_validate_personal_key($parameters['key'], $row['protected_key']);
            $row['send_type'] = 'item';
            // A password that looks like JSON must still be rendered as a password.
            $row['encrypted'] = cryption('{"password":"literal"}', $key, 'encrypt')['string'];
            if (!$wrapped) {
                $row['protected_key'] = null;
                $parameters['key'] = $key;
            }
            self::assertSame('{"password":"literal"}', secureSendRedeem($parameters, '', $this->settings)['fields']['password']);
        }
    }

    /** Later edits cannot mix new metadata with the password copied at link creation. */
    public function testSnapshotsKeepTheCopiedFieldsAndReportTruncation(): void
    {
        DB::$item['description'] = str_repeat('Description 中文 ', 5000);
        $snapshot = secureSendEncodeSnapshot(DB::$item, DB::$password);
        $parameters = $this->create(['password' => $snapshot['plaintext']]);
        DB::$links[1]['send_type'] = 'item_v2';
        DB::$item['label'] = 'Changed label';
        DB::$item['login'] = 'bob';
        DB::$password = 'new-password';
        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('', $result['error']);
        self::assertSame('Original label', $result['fields']['label']);
        self::assertSame('alice', $result['fields']['login']);
        self::assertSame('secret-at-creation', $result['fields']['password']);
        self::assertTrue($result['fields']['description_truncated']);
        self::assertTrue(mb_check_encoding($result['fields']['description'], 'UTF-8'));
    }

    /** TOTP seeds remain encrypted in the snapshot; recipients receive only a current code. */
    public function testSnapshotTotpBecomesOnlyAShortLivedRecipientCode(): void
    {
        $profile = ['secret' => 'JBSWY3DPEHPK3PXP', 'algorithm' => 'sha1', 'digits' => 6, 'period' => 30];
        $snapshot = secureSendEncodeSnapshot(DB::$item, DB::$password, $profile);
        $parameters = $this->create(['password' => $snapshot['plaintext']]);
        DB::$links[1]['send_type'] = 'item_v2';

        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('', $result['error']);
        self::assertMatchesRegularExpression('/^\d{6}$/', $result['fields']['otp_code']);
        self::assertGreaterThanOrEqual(1, $result['fields']['otp_expires_in']);
        self::assertLessThanOrEqual(30, $result['fields']['otp_expires_in']);
        self::assertArrayNotHasKey('totp', $result['fields']);
        self::assertStringNotContainsString($profile['secret'], json_encode($result, JSON_THROW_ON_ERROR));
    }

    /** A nearly expired current code is accompanied by the next server-generated code. */
    public function testTotpFallbackCodeIsOnlyProvidedNearExpiration(): void
    {
        $profile = ['secret' => 'JBSWY3DPEHPK3PXP', 'algorithm' => 'sha1', 'digits' => 6, 'period' => 30];
        $totp = createItemTotp($profile['secret'], $profile['algorithm'], $profile['digits'], $profile['period']);

        $comfortable = secureSendTotpRecipientFields($profile, 100);
        self::assertSame($totp->at(100), $comfortable['otp_code']);
        self::assertSame(20, $comfortable['otp_expires_in']);
        self::assertArrayNotHasKey('otp_next_code', $comfortable);

        $nearExpiry = secureSendTotpRecipientFields($profile, 119);
        self::assertSame($totp->at(119), $nearExpiry['otp_code']);
        self::assertSame(1, $nearExpiry['otp_expires_in']);
        self::assertSame($totp->at(120), $nearExpiry['otp_next_code']);
        self::assertSame(1, $nearExpiry['otp_next_valid_in']);
        self::assertSame(30, $nearExpiry['otp_next_valid_for']);
        self::assertStringNotContainsString($profile['secret'], json_encode($nearExpiry, JSON_THROW_ON_ERROR));
    }

    /** Snapshots without TOTP keep the former recipient contract and render no empty row. */
    public function testSnapshotWithoutTotpReturnsNoOtpFields(): void
    {
        $snapshot = secureSendEncodeSnapshot(DB::$item, DB::$password);
        $parameters = $this->create(['password' => $snapshot['plaintext']]);
        DB::$links[1]['send_type'] = 'item_v2';

        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('', $result['error']);
        self::assertArrayNotHasKey('totp', $result['fields']);
        self::assertArrayNotHasKey('otp_code', $result['fields']);
        self::assertArrayNotHasKey('otp_expires_in', $result['fields']);
    }

    /** Malformed encrypted TOTP profiles fail before a view or audit entry is consumed. */
    public function testMalformedSnapshotTotpFailsClosed(): void
    {
        $profile = ['secret' => 'NOT-BASE32!', 'algorithm' => 'sha1', 'digits' => 6, 'period' => 30];
        $snapshot = secureSendEncodeSnapshot(DB::$item, DB::$password, $profile);
        $parameters = $this->create(['password' => $snapshot['plaintext']]);
        DB::$links[1]['send_type'] = 'item_v2';
        $log = tempnam(sys_get_temp_dir(), 'tp-otv-test-');
        $previous = ini_set('error_log', $log);
        try {
            $result = secureSendRedeem($parameters, '', $this->settings);
            self::assertSame(['error' => 'server_error'], $result);
            self::assertSame(0, DB::$links[1]['views']);
            self::assertSame([], DB::$audit);
            self::assertStringNotContainsString($profile['secret'], file_get_contents($log));
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

    public function testReservationOrAuditFailureRollsBackWithoutReturningPlaintext(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'tp-otv-test-');
        $previous = ini_set('error_log', $log);
        try {
            foreach (['reservation', 'audit'] as $case) {
                DB::reset();
                $parameters = $this->create();
                DB::$rejectReservation = $case === 'reservation';
                DB::$failAudit = $case === 'audit';
                $result = secureSendRedeem($parameters, '', $this->settings);
                self::assertArrayNotHasKey('fields', $result);
                self::assertSame(0, DB::$links[1]['views']);
                self::assertSame([], DB::$audit);
            }
            self::assertStringNotContainsString('secret-at-creation', file_get_contents($log));
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

    public function testAutomaticDeletionBudgetIsSharedAcrossLinks(): void
    {
        $this->settings['enable_delete_after_consultation'] = 1;
        DB::$automatic = ['del_enabled' => 1, 'del_type' => 1, 'del_value' => 1];
        $first = $this->create();
        $second = $this->create();
        self::assertSame('', secureSendRedeem($first, '', $this->settings)['error']);
        self::assertSame(['del_enabled' => 1, 'del_type' => 1, 'del_value' => 0], DB::$automatic);
        self::assertSame(1, DB::$item['inactif']);
        self::assertGreaterThan(0, DB::$item['deleted_at']);
        self::assertSame([], DB::$cache);
        self::assertSame([7 => 0], DB::$folderCounts);
        self::assertSame(['at_shown', 'at_delete'], array_column(DB::$audit, 'action'));
        self::assertSame('invalid_link', secureSendRedeem($second, '', $this->settings)['error']);
        self::assertArrayNotHasKey(2, DB::$links);
        self::assertSame([7 => 0], DB::$folderCounts);
        self::assertSame(['at_shown', 'at_delete'], array_column(DB::$audit, 'action'));
    }

    /** An elapsed automatic deletion date also deactivates the item without revealing it. */
    public function testExpiredAutomaticDeletionDeactivatesWithoutConsumingAView(): void
    {
        $this->settings['enable_delete_after_consultation'] = 1;
        DB::$automatic = ['del_enabled' => 1, 'del_type' => 2, 'del_value' => time() - 1];
        $automatic = DB::$automatic;
        $parameters = $this->create();
        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('invalid_link', $result['error']);
        self::assertArrayNotHasKey('fields', $result);
        self::assertSame(0, DB::$links[1]['views']);
        self::assertSame(1, DB::$item['inactif']);
        self::assertGreaterThan(0, DB::$item['deleted_at']);
        self::assertSame($automatic, DB::$automatic);
        self::assertSame([], DB::$cache);
        self::assertSame([7 => 0], DB::$folderCounts);
        self::assertSame(['at_delete'], array_column(DB::$audit, 'action'));
    }

    /** Cache or counter failures must roll back the reveal and every deletion side effect. */
    public function testAutomaticDeletionMaintenanceFailureRollsBack(): void
    {
        $this->settings['enable_delete_after_consultation'] = 1;
        $log = tempnam(sys_get_temp_dir(), 'tp-otv-test-');
        $previous = ini_set('error_log', $log);
        try {
            foreach (['cache', 'counter'] as $failure) {
                DB::reset();
                DB::$automatic = ['del_enabled' => 1, 'del_type' => 1, 'del_value' => 1];
                $parameters = $this->create();
                DB::$failCache = $failure === 'cache';
                DB::$failCounter = $failure === 'counter';
                $result = secureSendRedeem($parameters, '', $this->settings);
                self::assertSame('server_error', $result['error']);
                self::assertArrayNotHasKey('fields', $result);
                self::assertSame(0, DB::$links[1]['views']);
                self::assertSame(0, DB::$item['inactif']);
                self::assertNull(DB::$item['deleted_at']);
                self::assertSame(1, DB::$automatic['del_value']);
                self::assertSame([123 => ['id' => 123]], DB::$cache);
                self::assertSame([7 => 1], DB::$folderCounts);
                self::assertSame([], DB::$audit);
            }
        } finally {
            ini_set('error_log', $previous);
            unlink($log);
        }
    }

}
