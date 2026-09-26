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
        require_once __DIR__ . '/../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../app/sources/secure_send.functions.php';
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
        self::assertSame([], DB::$automatic);
        self::assertSame(1, DB::$item['inactif']);
        self::assertSame(['at_shown', 'at_delete'], array_column(DB::$audit, 'action'));
        self::assertSame('invalid_link', secureSendRedeem($second, '', $this->settings)['error']);
        self::assertArrayNotHasKey(2, DB::$links);
    }

    /** An elapsed automatic deletion date also deactivates the item without revealing it. */
    public function testExpiredAutomaticDeletionDeactivatesWithoutConsumingAView(): void
    {
        $this->settings['enable_delete_after_consultation'] = 1;
        DB::$automatic = ['del_enabled' => 1, 'del_type' => 2, 'del_value' => time() - 1];
        $parameters = $this->create();
        $result = secureSendRedeem($parameters, '', $this->settings);
        self::assertSame('invalid_link', $result['error']);
        self::assertArrayNotHasKey('fields', $result);
        self::assertSame(0, DB::$links[1]['views']);
        self::assertSame(1, DB::$item['inactif']);
        self::assertSame(['at_delete'], array_column(DB::$audit, 'action'));
    }

}
