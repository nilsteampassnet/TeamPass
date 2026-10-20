<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/licence_trial_logic.php';

/**
 * Behavioural tests of the self-service licence trial decision logic.
 *
 * The contract under test is workReadmeFiles/CLIENT-INTEGRATION-TRIAL.md.
 */
class LicenceTrialLogicTest extends TestCase
{
    /**
     * A real public host name is what a licence can be attached to.
     */
    public function testValidFqdnIsAccepted(): void
    {
        self::assertTrue(licenceTrialIsValidFqdn('teampass.acme.example'));
        self::assertTrue(licenceTrialIsValidFqdn('TeamPass.Acme.Example'));
        self::assertTrue(licenceTrialIsValidFqdn('a.b'));
        self::assertTrue(licenceTrialIsValidFqdn('my-passwords.co.uk'));
        self::assertTrue(licenceTrialIsValidFqdn('teampass.acme.example.'));
    }

    /**
     * The values browser_extension_fqdn legitimately holds on a local install must be
     * refused: a trial is granted once per FQDN forever, and spending it on "localhost" or
     * on a subfolder name cannot be undone.
     */
    public function testUnusableFqdnIsRejected(): void
    {
        foreach ([
            '',
            'localhost',
            'TeamPass',
            '127.0.0.1',
            '::1',
            '192.168.1.10',
            'teampass.acme.example:8080',
            'https://teampass.acme.example',
            'teampass.acme.example/path',
            '-acme.example',
            'acme-.example',
            'acme..example',
            'acme.exa_mple',
        ] as $candidate) {
            self::assertFalse(licenceTrialIsValidFqdn($candidate), $candidate . ' must be refused');
        }
    }

    /**
     * A host longer than 255 characters cannot be stored by the licence server.
     */
    public function testOverlongFqdnIsRejected(): void
    {
        $host = str_repeat('a.', 130) . 'example';

        self::assertGreaterThan(255, strlen($host));
        self::assertFalse(licenceTrialIsValidFqdn($host));
    }

    /**
     * Contract §2: 16 to 255 printable ASCII characters, no space.
     */
    public function testTokenBounds(): void
    {
        self::assertTrue(licenceTrialIsValidToken(str_repeat('a', 16)));
        self::assertTrue(licenceTrialIsValidToken(str_repeat('a', 255)));
        self::assertTrue(licenceTrialIsValidToken(bin2hex(random_bytes(32))));

        self::assertFalse(licenceTrialIsValidToken(''));
        self::assertFalse(licenceTrialIsValidToken(str_repeat('a', 15)));
        self::assertFalse(licenceTrialIsValidToken(str_repeat('a', 256)));
        self::assertFalse(licenceTrialIsValidToken('has a space in it 1234'));
        self::assertFalse(licenceTrialIsValidToken("tab\tseparated12345"));
        self::assertFalse(licenceTrialIsValidToken('accentué' . str_repeat('a', 12)));
    }

    /**
     * The domain heuristic only warns, so it must not shout at legitimate addresses.
     */
    public function testEmailDomainAlignment(): void
    {
        self::assertTrue(licenceTrialEmailDomainLooksAligned('admin@acme.example', 'teampass.acme.example'));
        self::assertTrue(licenceTrialEmailDomainLooksAligned('admin@mail.acme.example', 'teampass.acme.example'));
        self::assertFalse(licenceTrialEmailDomainLooksAligned('admin@gmail.com', 'teampass.acme.example'));
        self::assertFalse(licenceTrialEmailDomainLooksAligned('not-an-address', 'teampass.acme.example'));
    }

    /**
     * A discovery document without the trial endpoint means trials are closed on that
     * server, and the whole block must disappear rather than fail on use.
     */
    public function testDiscoveryWithoutTrialEndpoint(): void
    {
        $parsed = licenceTrialParseDiscovery(['status' => 'online', 'version' => '1.2.1', 'endpoints' => []]);

        self::assertTrue($parsed['server_online']);
        self::assertSame('1.2.1', $parsed['server_version']);
        self::assertFalse($parsed['trial_available']);
    }

    /**
     * The trial description is read from the server, never hard-coded.
     */
    public function testDiscoveryWithTrialEndpoint(): void
    {
        $parsed = licenceTrialParseDiscovery([
            'status' => 'online',
            'endpoints' => [
                'trial' => [
                    'url' => '/api/v1.2/trial.php',
                    'method' => 'post',
                    'trial_days' => 30,
                    'requires_email_confirmation' => true,
                ],
            ],
        ]);

        self::assertTrue($parsed['trial_available']);
        self::assertSame('/api/v1.2/trial.php', $parsed['trial_url']);
        self::assertSame('POST', $parsed['trial_method']);
        self::assertSame(30, $parsed['trial_days']);
        self::assertTrue($parsed['requires_email_confirmation']);
    }

    /**
     * An unusable body is not a working server.
     */
    public function testDiscoveryOnEmptyBody(): void
    {
        $parsed = licenceTrialParseDiscovery(null);

        self::assertFalse($parsed['server_online']);
        self::assertFalse($parsed['trial_available']);
    }

    /**
     * 201 is the immediate grant.
     */
    public function testClassifyGranted(): void
    {
        $classified = licenceTrialClassifyResponse(
            201,
            ['status' => 'TRIAL_GRANTED', 'trial_days' => 30],
            true,
            false
        );

        self::assertSame(LICENCE_OUTCOME_GRANTED, $classified['outcome']);
        self::assertSame('TRIAL_GRANTED', $classified['status']);
    }

    /**
     * 202 is a SUCCESS. Treating "anything but 201" as a failure is the integration defect
     * the contract warns about most explicitly.
     */
    public function testClassify202IsASuccess(): void
    {
        $classified = licenceTrialClassifyResponse(
            202,
            [
                'status' => 'CONFIRMATION_SENT',
                'expires_at' => '2026-08-24 16:30:07',
                'resend_after' => 900,
            ],
            true,
            false
        );

        self::assertSame(LICENCE_OUTCOME_PENDING, $classified['outcome']);
        self::assertSame(900, $classified['resend_after']);
        self::assertSame(strtotime('2026-08-24 16:30:07 UTC'), $classified['expires_at']);
    }

    /**
     * A resend asked too early is still a pending request, not an error.
     */
    public function testClassifyConfirmationPending(): void
    {
        $classified = licenceTrialClassifyResponse(
            429,
            ['status' => 'CONFIRMATION_PENDING', 'retry_after' => 420],
            true,
            false
        );

        self::assertSame(LICENCE_OUTCOME_PENDING, $classified['outcome']);
        self::assertSame(420, $classified['resend_after']);
    }

    /**
     * Terminal refusals must be recognised as such so no retry is offered.
     */
    public function testClassifyPermanentRefusals(): void
    {
        $cases = [
            [409, 'TRIAL_ALREADY_USED'],
            [409, 'PRODUCT_ALREADY_LICENSED'],
            [401, 'UNAUTHORIZED'],
            [403, 'LICENCE_REVOKED'],
        ];

        foreach ($cases as [$code, $status]) {
            $classified = licenceTrialClassifyResponse($code, ['status' => $status], true, false);
            self::assertSame(
                LICENCE_OUTCOME_REFUSED_PERMANENT,
                $classified['outcome'],
                $status . ' must be terminal'
            );
        }
    }

    /**
     * Retryable refusals keep the door open, and the field errors are carried through.
     */
    public function testClassifyRetryableRefusals(): void
    {
        $classified = licenceTrialClassifyResponse(
            422,
            ['status' => 'INVALID_REQUEST', 'errors' => ['contact_email is required']],
            true,
            false
        );

        self::assertSame(LICENCE_OUTCOME_REFUSED_RETRY, $classified['outcome']);
        self::assertSame(['contact_email is required'], $classified['errors']);

        $rateLimited = licenceTrialClassifyResponse(
            429,
            ['status' => 'RATE_LIMITED', 'retry_after' => 3600],
            true,
            false
        );
        self::assertSame(LICENCE_OUTCOME_REFUSED_RETRY, $rateLimited['outcome']);
        self::assertSame(3600, $rateLimited['retry_after']);
    }

    /**
     * The feature must vanish when the server says trials are closed.
     */
    public function testClassifyDisabled(): void
    {
        $classified = licenceTrialClassifyResponse(503, ['status' => 'TRIAL_API_DISABLED'], true, false);

        self::assertSame(LICENCE_OUTCOME_DISABLED, $classified['outcome']);
    }

    /**
     * ERROR is a send failure: nothing was created, so retrying is legitimate.
     */
    public function testClassifySendFailure(): void
    {
        $classified = licenceTrialClassifyResponse(503, ['status' => 'ERROR'], true, false);

        self::assertSame(LICENCE_OUTCOME_REFUSED_RETRY, $classified['outcome']);
    }

    /**
     * A request that never left is unavailability, whatever else is passed.
     */
    public function testClassifyNetworkError(): void
    {
        $classified = licenceTrialClassifyResponse(0, null, false, true);

        self::assertSame(LICENCE_OUTCOME_UNREACHABLE, $classified['outcome']);
    }

    /**
     * A body whose signature does not verify is never used.
     */
    public function testClassifyUntrustedBody(): void
    {
        $classified = licenceTrialClassifyResponse(201, ['status' => 'TRIAL_GRANTED'], false, false);

        self::assertSame(LICENCE_OUTCOME_UNTRUSTED, $classified['outcome']);
    }

    /**
     * An unsigned 5xx is an incident page, not an attack: reporting it as tampering would
     * send the administrator looking for the wrong problem.
     */
    public function testUnsigned5xxIsUnavailabilityNotTampering(): void
    {
        $classified = licenceTrialClassifyResponse(502, null, false, false);

        self::assertSame(LICENCE_OUTCOME_UNREACHABLE, $classified['outcome']);
    }

    /**
     * A 202 opens the pending state and records both deadlines.
     */
    public function testStateMovesToPendingOn202(): void
    {
        $now = 1755000000;
        $classified = licenceTrialClassifyResponse(
            202,
            [
                'status' => 'CONFIRMATION_SENT',
                'expires_at' => gmdate('Y-m-d H:i:s', $now + 172800),
                'resend_after' => 900,
            ],
            true,
            false
        );

        $state = licenceTrialNextState([], 'extension', $classified, $now, 'teampass.acme.example', 'admin@acme.example');
        $entry = licenceTrialStateForProduct($state, 'extension');

        self::assertSame(LICENCE_TRIAL_STATE_PENDING, $entry['state']);
        self::assertSame('admin@acme.example', $entry['contact_email']);
        self::assertSame('teampass.acme.example', $entry['fqdn']);
        self::assertSame($now + 900, $entry['resend_allowed_at']);
        self::assertSame($now + 172800, $entry['link_expires_at']);
    }

    /**
     * A CONFIRMATION_PENDING answer refreshes the resend delay but must not move the link
     * expiry: that deadline belongs to the message already sitting in the mailbox.
     */
    public function testConfirmationPendingDoesNotMoveTheLinkExpiry(): void
    {
        $now = 1755000000;
        $state = [
            'extension' => [
                'state' => LICENCE_TRIAL_STATE_PENDING,
                'link_expires_at' => $now + 100000,
                'resend_allowed_at' => $now + 60,
                'pending_since' => $now - 100,
            ],
        ];

        $classified = licenceTrialClassifyResponse(
            429,
            ['status' => 'CONFIRMATION_PENDING', 'retry_after' => 300],
            true,
            false
        );

        $entry = licenceTrialStateForProduct(
            licenceTrialNextState($state, 'extension', $classified, $now, 'teampass.acme.example', 'admin@acme.example'),
            'extension'
        );

        self::assertSame($now + 100000, $entry['link_expires_at']);
        self::assertSame($now + 300, $entry['resend_allowed_at']);
        self::assertSame($now - 100, $entry['pending_since']);
    }

    /**
     * Nothing was created server-side on a retryable refusal, so the local state must not
     * move — in particular a pending request must survive a transient rate limit.
     */
    public function testRetryableRefusalLeavesTheStateAlone(): void
    {
        $now = 1755000000;
        $state = [
            'extension' => [
                'state' => LICENCE_TRIAL_STATE_PENDING,
                'link_expires_at' => $now + 100000,
                'resend_allowed_at' => $now + 60,
            ],
        ];

        $classified = licenceTrialClassifyResponse(429, ['status' => 'RATE_LIMITED'], true, false);
        $entry = licenceTrialStateForProduct(
            licenceTrialNextState($state, 'extension', $classified, $now, 'teampass.acme.example', 'admin@acme.example'),
            'extension'
        );

        self::assertSame(LICENCE_TRIAL_STATE_PENDING, $entry['state']);
        self::assertSame($now + 100000, $entry['link_expires_at']);
    }

    /**
     * A dead confirmation link puts the instance back where it was: nothing was consumed,
     * so a new request must be offered.
     */
    public function testExpiredLinkReopensTheRequest(): void
    {
        $now = 1755000000;
        $entry = licenceTrialExpirePending(
            licenceTrialStateForProduct([
                'extension' => [
                    'state' => LICENCE_TRIAL_STATE_PENDING,
                    'link_expires_at' => $now - 1,
                ],
            ], 'extension'),
            $now
        );

        self::assertSame(LICENCE_TRIAL_STATE_NONE, $entry['state']);
        self::assertSame('licence_trial_link_expired', $entry['last_error']);
    }

    /**
     * A pending request that is still within its window stays pending.
     */
    public function testLivePendingRequestIsKept(): void
    {
        $now = 1755000000;
        $entry = licenceTrialExpirePending(
            licenceTrialStateForProduct([
                'extension' => ['state' => LICENCE_TRIAL_STATE_PENDING, 'link_expires_at' => $now + 10],
            ], 'extension'),
            $now
        );

        self::assertSame(LICENCE_TRIAL_STATE_PENDING, $entry['state']);
    }

    /**
     * The unsuffixed info.php keys are the extension figures; the mobile ones are suffixed.
     */
    public function testInfoNormalisationSplitsTheTwoProducts(): void
    {
        $info = licenceTrialNormalizeInfo([
            'status' => 'VALID',
            'expiration_date' => '2027-12-31 23:59:59',
            'max_users' => 10,
            'consumed_this_month' => 7,
            'trial' => false,
            'status_mobile' => 'VALID',
            'expiration_date_mobile' => '2026-09-18 23:59:59',
            'max_users_mobile' => 5,
            'consumed_this_month_mobile' => 2,
            'trial_mobile' => true,
            'extension_allowed' => true,
            'mobile_app_allowed' => true,
        ]);

        self::assertTrue($info['has_licence']);
        self::assertSame(10, $info['extension']['max_users']);
        self::assertSame(7, $info['extension']['consumed']);
        self::assertFalse($info['extension']['trial']);
        self::assertSame(5, $info['mobile_app']['max_users']);
        self::assertTrue($info['mobile_app']['trial']);
    }

    /**
     * No body means no licence figures at all, never a partially filled card.
     */
    public function testInfoNormalisationOnEmptyBody(): void
    {
        $info = licenceTrialNormalizeInfo(null);

        self::assertFalse($info['has_licence']);
        self::assertSame(0, $info['extension']['max_users']);
        self::assertSame('', $info['extension']['status']);
        self::assertSame(0, $info['extension']['refused']);
        self::assertSame(licenceTrialEmptyHistory(), $info['extension']['history']);
    }

    /**
     * info.php 1.2.4 counts the users refused by the monthly quota, per product.
     */
    public function testInfoNormalisationReadsRefusedCounters(): void
    {
        $info = licenceTrialNormalizeInfo([
            'status' => 'VALID',
            'max_users' => 20,
            'consumed_this_month' => 20,
            'refused_this_month' => 3,
            'status_mobile' => 'VALID',
            'refused_this_month_mobile' => 1,
        ]);

        self::assertSame(3, $info['extension']['refused']);
        self::assertSame(1, $info['mobile_app']['refused']);
    }

    /**
     * A 1.2.1 server sends neither key: no badge, no chart, nothing else changes.
     */
    public function testInfoNormalisationWithoutHistoryFromAnOlderServer(): void
    {
        $info = licenceTrialNormalizeInfo([
            'status' => 'VALID',
            'max_users' => 10,
            'consumed_this_month' => 7,
        ]);

        self::assertSame(0, $info['extension']['refused']);
        self::assertSame(licenceTrialEmptyHistory(), $info['extension']['history']);
        self::assertSame(licenceTrialEmptyHistory(), $info['mobile_app']['history']);
        self::assertSame(7, $info['extension']['consumed']);
    }

    /**
     * The shape the licence server builds (keys sorted, first_snapshot null) is kept as sent.
     */
    public function testHistoryNormalisationKeepsAWellFormedSeries(): void
    {
        $info = licenceTrialNormalizeInfo([
            'status' => 'VALID',
            'history' => [
                'days' => 3,
                'first_snapshot' => '2026-10-15',
                'points' => [
                    ['active_users' => null, 'date' => '2026-10-14', 'live' => false, 'seats_quota' => null, 'seats_used' => null],
                    ['active_users' => 5, 'date' => '2026-10-15', 'live' => false, 'seats_quota' => 20, 'seats_used' => 12],
                    ['active_users' => 6, 'date' => '2026-10-16', 'live' => true, 'seats_quota' => 20, 'seats_used' => 14],
                ],
                'product' => 'extension',
                'today' => '2026-10-16',
            ],
        ]);
        $history = $info['extension']['history'];

        self::assertSame(3, $history['days']);
        self::assertSame('2026-10-16', $history['today']);
        self::assertSame('2026-10-15', $history['first_snapshot']);
        self::assertNull($history['points'][0]['seats_used']);
        self::assertSame(12, $history['points'][1]['seats_used']);
        self::assertSame(20, $history['points'][1]['seats_quota']);
        self::assertSame(5, $history['points'][1]['active_users']);
        self::assertSame([false, false, true], array_column($history['points'], 'live'));
    }

    /**
     * A figure that is not a non-negative JSON integer is a gap, never a guessed number.
     */
    public function testHistoryNormalisationNullsInvalidFigures(): void
    {
        $history = licenceTrialNormalizeHistory([
            'points' => [
                ['date' => '2026-10-13', 'seats_used' => -1, 'seats_quota' => '5', 'active_users' => 5.0],
                ['date' => '2026-10-14', 'seats_used' => true, 'seats_quota' => 0, 'active_users' => []],
            ],
        ]);

        self::assertSame(2, $history['days']);
        self::assertNull($history['points'][0]['seats_used']);
        self::assertNull($history['points'][0]['seats_quota']);
        self::assertNull($history['points'][0]['active_users']);
        self::assertNull($history['points'][1]['seats_used']);
        self::assertSame(0, $history['points'][1]['seats_quota']);
        self::assertNull($history['points'][1]['active_users']);
    }

    /**
     * A series out of order would be drawn wrong; it is dropped whole.
     */
    public function testHistoryNormalisationDropsAnUnorderedSeries(): void
    {
        foreach ([
            ['2026-10-14', '2026-10-14'],
            ['2026-10-15', '2026-10-14'],
        ] as $dates) {
            $history = licenceTrialNormalizeHistory([
                'points' => array_map(static fn (string $date): array => ['date' => $date, 'seats_used' => 1], $dates),
            ]);

            self::assertSame(licenceTrialEmptyHistory(), $history, implode(',', $dates));
        }
    }

    /**
     * Only real "Y-m-d" calendar days are accepted.
     */
    public function testHistoryNormalisationDropsInvalidDates(): void
    {
        foreach (['2026-02-30', '16/10/2026', '', "2026-10-16\n", '2026-10-16 00:00:00', 20261016] as $date) {
            $history = licenceTrialNormalizeHistory(['points' => [['date' => $date, 'seats_used' => 1]]]);

            self::assertSame(licenceTrialEmptyHistory(), $history, var_export($date, true));
        }

        self::assertSame(
            licenceTrialEmptyHistory(),
            licenceTrialNormalizeHistory(['points' => [['date' => '2026-10-15'], 'not a point']])
        );
    }

    /**
     * Only today's point is computed live; a live flag elsewhere is ignored.
     */
    public function testHistoryNormalisationHonoursLiveOnTheLastPointOnly(): void
    {
        $history = licenceTrialNormalizeHistory([
            'points' => [
                ['date' => '2026-10-14', 'seats_used' => 1, 'live' => true],
                ['date' => '2026-10-15', 'seats_used' => 2, 'live' => 1],
                ['date' => '2026-10-16', 'seats_used' => 3, 'live' => 'true'],
            ],
        ]);

        self::assertSame([false, false, false], array_column($history['points'], 'live'));
    }

    /**
     * Whatever the server sends, at most 62 points (the most recent) reach the renderer.
     */
    public function testHistoryNormalisationCapsThePointCount(): void
    {
        $start = new DateTimeImmutable('2026-08-01', new DateTimeZone('UTC'));
        $points = [];
        for ($i = 0; $i < 70; $i++) {
            $points[] = ['date' => $start->modify('+' . $i . ' day')->format('Y-m-d'), 'seats_used' => $i];
        }

        $history = licenceTrialNormalizeHistory(['points' => $points]);

        self::assertSame(LICENCE_SEATS_HISTORY_MAX_POINTS, $history['days']);
        self::assertCount(LICENCE_SEATS_HISTORY_MAX_POINTS, $history['points']);
        self::assertSame(8, $history['points'][0]['seats_used']);
        self::assertSame($points[69]['date'], $history['today']);
    }

    /**
     * Anything but an object with a points list is no history at all.
     */
    public function testHistoryNormalisationRejectsNonArrays(): void
    {
        foreach ([null, 'x', 31, [], ['points' => 'x'], ['points' => null]] as $raw) {
            self::assertSame(licenceTrialEmptyHistory(), licenceTrialNormalizeHistory($raw), var_export($raw, true));
        }
    }

    /**
     * first_snapshot is kept only when it is a calendar day; the server sends null when none exists.
     */
    public function testHistoryNormalisationKeepsFirstSnapshotOnlyWhenValid(): void
    {
        $point = [['date' => '2026-10-16', 'seats_used' => 1]];

        self::assertSame('2026-10-15', licenceTrialNormalizeHistory(['first_snapshot' => '2026-10-15', 'points' => $point])['first_snapshot']);
        self::assertSame('', licenceTrialNormalizeHistory(['first_snapshot' => 'yesterday', 'points' => $point])['first_snapshot']);
        self::assertSame('', licenceTrialNormalizeHistory(['first_snapshot' => null, 'points' => $point])['first_snapshot']);
    }

    /**
     * The widget handler normalises the cached history again; that must change nothing.
     */
    public function testHistoryNormalisationIsIdempotent(): void
    {
        $once = licenceTrialNormalizeHistory([
            'first_snapshot' => '2026-10-15',
            'points' => [
                ['date' => '2026-10-14', 'seats_used' => null],
                ['date' => '2026-10-15', 'seats_used' => 12, 'seats_quota' => 20, 'active_users' => 5],
                ['date' => '2026-10-16', 'seats_used' => 14, 'seats_quota' => 20, 'active_users' => 6, 'live' => true],
            ],
        ]);

        self::assertSame($once, licenceTrialNormalizeHistory($once));
        self::assertSame(licenceTrialEmptyHistory(), licenceTrialNormalizeHistory(licenceTrialEmptyHistory()));
    }

    /**
     * The budget window opens on the first call and holds six of them.
     */
    public function testBudgetAllowsSixCallsPerWindow(): void
    {
        $now = 1755000000;
        $budget = [];

        for ($i = 0; $i < LICENCE_INFO_MAX_CALLS_PER_HOUR; $i++) {
            self::assertTrue(licenceTrialBudgetAllows($budget, $now), 'call ' . $i . ' must be allowed');
            $budget = licenceTrialBudgetConsume($budget, $now);
        }

        self::assertFalse(licenceTrialBudgetAllows($budget, $now));
        self::assertSame(LICENCE_INFO_BUDGET_WINDOW, licenceTrialBudgetRetryAfter($budget, $now));
    }

    /**
     * Once the window has elapsed the counter starts again.
     */
    public function testBudgetWindowRolls(): void
    {
        $now = 1755000000;
        $budget = ['window_start' => $now, 'count' => LICENCE_INFO_MAX_CALLS_PER_HOUR];

        self::assertFalse(licenceTrialBudgetAllows($budget, $now + 10));
        self::assertTrue(licenceTrialBudgetAllows($budget, $now + LICENCE_INFO_BUDGET_WINDOW));

        $rolled = licenceTrialBudgetConsume($budget, $now + LICENCE_INFO_BUDGET_WINDOW);
        self::assertSame(1, $rolled['count']);
        self::assertSame($now + LICENCE_INFO_BUDGET_WINDOW, $rolled['window_start']);
    }

    /**
     * A pending confirmation must be noticed quickly, but not at the cost of the budget.
     */
    public function testInfoTtlDependsOnTheSituation(): void
    {
        self::assertSame(LICENCE_INFO_TTL_PENDING, licenceTrialInfoTtl(true, true));
        self::assertSame(LICENCE_INFO_TTL_ONLINE, licenceTrialInfoTtl(false, true));
        self::assertSame(LICENCE_INFO_TTL_OFFLINE, licenceTrialInfoTtl(true, false));

        // The pending interval must leave room under the hourly budget for manual checks.
        self::assertLessThan(
            LICENCE_INFO_MAX_CALLS_PER_HOUR,
            (int) (LICENCE_INFO_BUDGET_WINDOW / LICENCE_INFO_TTL_PENDING)
        );
    }

    /**
     * An active licence answers the only question that matters and wins over everything.
     */
    public function testDisplayShowsTheLicenceFirst(): void
    {
        $vm = licenceTrialResolveDisplay(
            ['extension' => ['state' => LICENCE_TRIAL_STATE_PENDING, 'link_expires_at' => 1755100000]],
            licenceTrialNormalizeInfo(['status' => 'VALID', 'max_users' => 10, 'trial' => true,
                'expiration_date' => '2026-09-21 23:59:59']),
            licenceTrialParseDiscovery(['status' => 'online', 'endpoints' => ['trial' => ['url' => '/t']]]),
            1755000000,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_GRANTED, $vm['panel']);
        self::assertTrue($vm['licence']['trial']);
    }

    /**
     * A trial in its last days must raise the no-grace-period warning.
     */
    public function testTrialExpiringSoonIsFlagged(): void
    {
        $now = strtotime('2026-09-18 09:00:00 UTC');
        $vm = licenceTrialResolveDisplay(
            [],
            licenceTrialNormalizeInfo(['status' => 'VALID', 'max_users' => 10, 'trial' => true,
                'expiration_date' => '2026-09-21 23:59:59']),
            licenceTrialParseDiscovery(['status' => 'online']),
            $now,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertTrue($vm['licence']['expiring_soon']);
        self::assertSame(3, $vm['licence']['days_left']);
    }

    /**
     * A paid subscription keeps its 15-day tolerance and must not raise the trial warning.
     */
    public function testPaidLicenceNearExpiryIsNotFlaggedAsATrial(): void
    {
        $now = strtotime('2026-09-18 09:00:00 UTC');
        $vm = licenceTrialResolveDisplay(
            [],
            licenceTrialNormalizeInfo(['status' => 'VALID', 'max_users' => 10, 'trial' => false,
                'expiration_date' => '2026-09-21 23:59:59']),
            licenceTrialParseDiscovery(['status' => 'online']),
            $now,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertFalse($vm['licence']['expiring_soon']);
    }

    /**
     * Without a licence, a pending request is what the administrator must see.
     */
    public function testDisplayShowsPendingRequest(): void
    {
        $now = 1755000000;
        $vm = licenceTrialResolveDisplay(
            ['extension' => [
                'state' => LICENCE_TRIAL_STATE_PENDING,
                'link_expires_at' => $now + 3600,
                'resend_allowed_at' => $now + 600,
                'contact_email' => 'admin@acme.example',
                'fqdn' => 'teampass.acme.example',
            ]],
            licenceTrialNormalizeInfo(null),
            licenceTrialParseDiscovery(['status' => 'online', 'endpoints' => ['trial' => ['url' => '/t']]]),
            $now,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_PENDING, $vm['panel']);
        self::assertSame(600, $vm['resend_in']);
        self::assertSame(3600, $vm['link_expires_in']);
        self::assertFalse($vm['fqdn_changed_since_request']);
    }

    /**
     * Changing the FQDN while a confirmation is in flight invalidates what will be activated,
     * and the administrator has to be told before clicking the link.
     */
    public function testFqdnChangeDuringPendingIsReported(): void
    {
        $now = 1755000000;
        $vm = licenceTrialResolveDisplay(
            ['extension' => [
                'state' => LICENCE_TRIAL_STATE_PENDING,
                'link_expires_at' => $now + 3600,
                'fqdn' => 'old.acme.example',
            ]],
            licenceTrialNormalizeInfo(null),
            licenceTrialParseDiscovery(['status' => 'online', 'endpoints' => ['trial' => ['url' => '/t']]]),
            $now,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertTrue($vm['fqdn_changed_since_request']);
    }

    /**
     * An unreachable licence server is its own panel, never a generic failure.
     */
    public function testDisplayReportsUnreachableServer(): void
    {
        $vm = licenceTrialResolveDisplay(
            [],
            licenceTrialNormalizeInfo(null),
            licenceTrialParseDiscovery(null),
            1755000000,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_UNREACHABLE, $vm['panel']);
    }

    /**
     * Trials closed on the server means the block is hidden, not that a request fails.
     */
    public function testDisplayReportsClosedTrials(): void
    {
        $vm = licenceTrialResolveDisplay(
            [],
            licenceTrialNormalizeInfo(null),
            licenceTrialParseDiscovery(['status' => 'online', 'endpoints' => []]),
            1755000000,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_CLOSED, $vm['panel']);
    }

    /**
     * An unusable identity is caught before anything can be sent.
     */
    public function testDisplayBlocksOnUnusableIdentity(): void
    {
        $discovery = licenceTrialParseDiscovery(['status' => 'online', 'endpoints' => ['trial' => ['url' => '/t']]]);

        $badFqdn = licenceTrialResolveDisplay(
            [], licenceTrialNormalizeInfo(null), $discovery, 1755000000, 'localhost', str_repeat('a', 64)
        );
        self::assertSame(LICENCE_PANEL_INVALID_FQDN, $badFqdn['panel']);

        $badToken = licenceTrialResolveDisplay(
            [], licenceTrialNormalizeInfo(null), $discovery, 1755000000, 'teampass.acme.example', 'short'
        );
        self::assertSame(LICENCE_PANEL_INVALID_FQDN, $badToken['panel']);
        self::assertFalse($badToken['token_valid']);
    }

    /**
     * With a sound identity and trials open, the form is what gets rendered.
     */
    public function testDisplayOffersTheForm(): void
    {
        $vm = licenceTrialResolveDisplay(
            [],
            licenceTrialNormalizeInfo(null),
            licenceTrialParseDiscovery([
                'status' => 'online',
                'endpoints' => ['trial' => ['url' => '/t', 'trial_days' => 30]],
            ]),
            1755000000,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_NONE, $vm['panel']);
        self::assertSame(30, $vm['trial_days']);
        self::assertTrue($vm['fqdn_valid']);
    }

    /**
     * A terminal refusal must not offer the form again.
     */
    public function testDisplayKeepsTerminalRefusal(): void
    {
        $vm = licenceTrialResolveDisplay(
            ['extension' => ['state' => LICENCE_TRIAL_STATE_REFUSED, 'status' => 'TRIAL_ALREADY_USED']],
            licenceTrialNormalizeInfo(null),
            licenceTrialParseDiscovery(['status' => 'online', 'endpoints' => ['trial' => ['url' => '/t']]]),
            1755000000,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_REFUSED, $vm['panel']);
        self::assertSame('TRIAL_ALREADY_USED', $vm['status']);
    }

    /**
     * Dates are read as UTC, the way the licence server writes them.
     */
    public function testServerDateParsing(): void
    {
        self::assertSame(strtotime('2026-08-24 16:30:07 UTC'), licenceTrialParseServerDate('2026-08-24 16:30:07'));
        self::assertSame(0, licenceTrialParseServerDate(''));
        self::assertSame(0, licenceTrialParseServerDate('not a date'));
    }

    /** Server calendar days must not shift with the instance timezone. */
    public function testServerDayFormattingIgnoresTheInstanceTimezone(): void
    {
        $originalTimezone = date_default_timezone_get();
        try {
            foreach (['UTC', 'Europe/Paris', 'Asia/Tokyo', 'America/Los_Angeles'] as $timezone) {
                date_default_timezone_set($timezone);
                foreach (['d/m/Y' => '16/06/2027', 'm/d/Y' => '06/16/2027', 'd-M-Y' => '16-Jun-2027',
                    'd/m/y' => '16/06/27', 'm/d/y' => '06/16/27', 'd-M-y' => '16-Jun-27',
                    'd-m-y' => '16-06-27', 'Y-m-d' => '2027-06-16'] as $format => $expected) {
                    self::assertSame($expected, licenceTrialFormatServerDay('2027-06-16 23:59:59', $format), $timezone);
                    self::assertSame($expected, licenceTrialFormatServerDay('2027-06-16', $format), $timezone);
                }
                self::assertSame('29/02/2028', licenceTrialFormatServerDay('2028-02-29 23:59:59', 'd/m/Y'));
                foreach (['', '   ', 'not a date'] as $invalid) {
                    self::assertSame('', licenceTrialFormatServerDay($invalid, 'd/m/Y'));
                }
            }
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    /**
     * Remaining days must be null when there is no date, and negative once past.
     */
    public function testDaysLeft(): void
    {
        $now = strtotime('2026-09-01 12:00:00 UTC');

        self::assertNull(licenceTrialDaysLeft('', $now));
        self::assertSame(0, licenceTrialDaysLeft('2026-09-01 23:59:59', $now));
        self::assertSame(9, licenceTrialDaysLeft('2026-09-10 23:59:59', $now));
        self::assertLessThan(0, licenceTrialDaysLeft('2026-08-30 23:59:59', $now));
    }

    /**
     * An unusable identity is reported as such even when the licence server is unreachable.
     *
     * Both are true at once on an isolated instance whose FQDN was never set. Reporting
     * "unreachable" would send the administrator looking at the firewall, when the blocker
     * is local and the offline request link cannot be built without a real FQDN anyway.
     */
    public function testInvalidIdentityWinsOverUnreachable(): void
    {
        $offline = licenceTrialParseDiscovery(null);
        self::assertFalse($offline['server_online']);

        $vm = licenceTrialResolveDisplay(
            [],
            [],
            $offline,
            time(),
            'localhost',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_INVALID_FQDN, $vm['panel']);
        self::assertFalse($vm['fqdn_valid']);
    }

    /**
     * With a usable identity and no outbound access, the panel is the offline one.
     */
    public function testUnreachableWithAUsableIdentity(): void
    {
        $vm = licenceTrialResolveDisplay(
            [],
            [],
            licenceTrialParseDiscovery(null),
            time(),
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_UNREACHABLE, $vm['panel']);
        self::assertTrue($vm['fqdn_valid']);
        self::assertTrue($vm['token_valid']);
    }

    /**
     * The offline trace is only a record of what this instance handed over, never a state.
     *
     * Nothing was requested when the link was e-mailed - the licence server knows nothing
     * about it - so the state must stay 'none' and the trial must remain requestable.
     */
    public function testOfflineLinkTraceIsNotAStateTransition(): void
    {
        $now = time();
        $state = ['extension' => [
            'offline_link_sent_at' => $now,
            'offline_link_sent_to' => 'admin@acme.example',
        ]];

        $entry = licenceTrialStateForProduct($state, 'extension');
        self::assertSame(LICENCE_TRIAL_STATE_NONE, $entry['state']);
        self::assertSame($now, $entry['offline_link_sent_at']);

        $vm = licenceTrialResolveDisplay(
            $state,
            [],
            licenceTrialParseDiscovery(null),
            $now,
            'teampass.acme.example',
            str_repeat('a', 64)
        );

        self::assertSame(LICENCE_PANEL_UNREACHABLE, $vm['panel']);
        self::assertSame($now, $vm['offline_link_sent_at']);
        self::assertSame('admin@acme.example', $vm['offline_link_sent_to']);
    }

    /**
     * The trace must survive a later real request: licenceTrialNextState() merges onto the
     * existing entry, so a 202 arriving after an offline link was sent keeps both facts.
     */
    public function testOfflineTraceSurvivesALaterRequest(): void
    {
        $now = time();
        $state = ['extension' => [
            'offline_link_sent_at' => $now - 3600,
            'offline_link_sent_to' => 'admin@acme.example',
        ]];

        $classified = licenceTrialClassifyResponse(
            202,
            ['status' => 'CONFIRMATION_SENT', 'expires_at' => '2026-08-24 16:30:07', 'resend_after' => 900],
            true,
            false
        );

        $next = licenceTrialNextState($state, 'extension', $classified, $now, 'teampass.acme.example', 'a@acme.example');
        $entry = licenceTrialStateForProduct($next, 'extension');

        self::assertSame(LICENCE_TRIAL_STATE_PENDING, $entry['state']);
        self::assertSame($now - 3600, $entry['offline_link_sent_at']);
    }
}
