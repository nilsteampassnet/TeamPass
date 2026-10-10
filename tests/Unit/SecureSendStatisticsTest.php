<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      SecureSendStatisticsTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use TeampassClasses\PerformChecks\PerformChecks;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendStatisticsTest extends TestCase
{
    private string $previousLog;
    private string $log;

    protected function setUp(): void
    {
        if (!extension_loaded('sqlite3')) {
            self::markTestSkipped('SQLite3 is required for SQL-backed statistics tests.');
        }
        require_once __DIR__ . '/../Fixtures/secure_send_statistics_db.php';
        require_once __DIR__ . '/../../app/sources/secure_send_statistics.php';
        require_once __DIR__ . '/../../app/sources/secure_send_audit.php';
        require_once __DIR__ . '/../../app/sources/operational_statistics_logic.php';
        DB::$connection = new SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$queries = [];
        DB::$failQuery = null;
        DB::$missingSummary = false;
        // Match the journal's typed metadata, without creating live-link/item tables.
        DB::$connection->exec('CREATE TABLE stats_fixture_secure_send_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT, send_id INTEGER, event TEXT, reason TEXT,
            occurred_at INTEGER, originator INTEGER, actor_id INTEGER, item_id INTEGER,
            send_type TEXT, created_at INTEGER, has_passphrase INTEGER, is_public INTEGER,
            max_views INTEGER, expires_at INTEGER, views INTEGER, failed_attempts INTEGER)');
        DB::$connection->exec('CREATE TABLE stats_fixture_users (
            id INTEGER PRIMARY KEY, login TEXT, name TEXT, lastname TEXT, disabled INTEGER DEFAULT 0,
            deleted_at TEXT, email TEXT, admin INTEGER DEFAULT 0, gestionnaire INTEGER DEFAULT 0,
            can_manage_all_users INTEGER DEFAULT 0, can_manage_lapr INTEGER DEFAULT 0, key_tempo TEXT)');
        DB::$connection->exec("INSERT INTO stats_fixture_users (id, login, name, lastname, email)
            VALUES (42, 'alice', 'Alice', 'Sender', 'private-email-canary')");
        $this->previousLog = (string) ini_get('error_log');
        $this->log = tempnam(sys_get_temp_dir(), 'tp-send-statistics-');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        if (isset($this->log)) {
            $contents = (string) file_get_contents($this->log);
            ini_set('error_log', $this->previousLog);
            unlink($this->log);
            self::assertStringNotContainsString('secret-canary', $contents);
            DB::$connection->close();
        }
    }

    /** Insert real allowlisted journal rows, including no fabricated creation for legacy sends. */
    private function event(string $event, int $sender = 42, int $sendId = 1, int $timestamp = 500, array $changes = []): void
    {
        $link = array_replace([
            'id' => $sendId, 'originator' => $sender, 'send_type' => 'item', 'item_id' => 123,
            'timestamp' => 10, 'time_limit' => 10000, 'max_views' => 5, 'views' => 0,
            'has_passphrase' => 0, 'shared_globaly' => 0, 'failed_attempts' => 0,
        ], $changes);
        $reason = ['revoked' => 'sender_revoked', 'invalidated' => 'attempts_exhausted',
            'expired' => 'deadline_elapsed', 'reveal_failed' => 'wrong_credentials'][$event] ?? '';
        $actor = in_array($event, ['created', 'revoked'], true) ? $sender : null;
        $record = secureSendAuditRecord($link, $event, $reason, $actor, $timestamp);
        $statement = DB::$connection->prepare('INSERT INTO stats_fixture_secure_send_audit ('
            . implode(',', array_keys($record)) . ') VALUES (' . implode(',', array_fill(0, count($record), '?')) . ')');
        foreach (array_values($record) as $index => $value) {
            $statement->bindValue($index + 1, $value, $value === null ? SQLITE3_NULL : (is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT));
        }
        $statement->execute();
    }

    private function statistics(array $settings = ['otv_is_enabled' => 1], array $excluded = []): array
    {
        return secureSendBuildOperationalStatistics(100, 900, $settings, $excluded);
    }

    public function testEmptyJournalIsAvailableAndReturnsIntegerZeros(): void
    {
        $data = $this->statistics();
        self::assertTrue($data['available']);
        self::assertFalse($data['error']);
        self::assertTrue($data['enabled']);
        self::assertSame('ok', $data['reason']);
        self::assertSame([], $data['top_senders']);
        foreach (array_merge($data['totals'], $data['creations']) as $value) {
            self::assertSame(0, $value);
        }
        self::assertSame(['period'], $data['meta']['filters_applied']);
        self::assertFalse($data['meta']['historical_backfill']);
        self::assertFalse($data['meta']['recipient_identity_known']);
        self::assertCount(2, DB::$queries);
    }

    public function testEveryEventIsCountedWithoutSummingCumulativeViewsOrFailures(): void
    {
        $this->event('created');
        $this->event('revealed', 42, 1, 501, ['views' => 1]);
        $this->event('revealed', 42, 1, 502, ['views' => 2]);
        for ($i = 1; $i <= 5; ++$i) {
            $this->event('reveal_failed', 42, 1, 503 + $i, ['failed_attempts' => $i]);
        }
        $this->event('invalidated', 42, 1, 510, ['failed_attempts' => 5]);
        $this->event('revoked', 42, 2);
        $this->event('expired', 42, 3);
        $data = $this->statistics();
        self::assertSame(['created' => 1, 'revealed' => 2, 'reveal_failed' => 5, 'revoked' => 1,
            'invalidated' => 1, 'expired' => 1, 'sends_revealed' => 1, 'senders' => 1], $data['totals']);
        self::assertSame(1, $data['top_senders'][0]['created']);
        self::assertSame(2, $data['top_senders'][0]['revealed']);
        self::assertSame(5, $data['top_senders'][0]['reveal_failed']);
    }

    public function testCreationsArePartitionedByTypeProtectionAndPublicPolicy(): void
    {
        $this->event('created', 42, 1, 500, ['send_type' => 'item_v2', 'has_passphrase' => 1, 'shared_globaly' => 1]);
        $this->event('created', 42, 2, 500, ['send_type' => 'note', 'item_id' => null]);
        $this->event('created', 43, 3, 500, ['send_type' => 'unsupported']);
        // Reveal policy metadata must not inflate creation/protection counts.
        $this->event('revealed', 42, 1, 501, ['has_passphrase' => 1, 'shared_globaly' => 1]);
        $data = $this->statistics();
        self::assertSame(['items' => 1, 'notes' => 1, 'unknown' => 1, 'protected' => 1,
            'unprotected' => 2, 'public_links' => 1, 'internal_links' => 2], $data['creations']);
        self::assertSame(3, $data['totals']['created']);
        self::assertSame(2, $data['totals']['senders']);
    }

    public function testPeriodIncludesBothBoundariesAndUsesObservationNotLinkCreationTime(): void
    {
        foreach ([99, 100, 900, 901] as $time) {
            $this->event('created', 42, $time, $time, ['timestamp' => 500]);
        }
        $this->event('expired', 42, 7, 500, ['time_limit' => 99]);
        $data = $this->statistics();
        self::assertSame(2, $data['totals']['created']);
        self::assertSame(1, $data['totals']['expired']);
        self::assertSame(900, $data['top_senders'][0]['last_created']);
    }

    public function testLegacyRevealsDoNotInventCreationOrSenderCounts(): void
    {
        $this->event('revealed', 42, 1, 500, ['timestamp' => 500]);
        $this->event('revealed', 43, 2);
        $data = $this->statistics();
        self::assertSame(0, $data['totals']['created']);
        self::assertSame(0, $data['totals']['senders']);
        self::assertSame(2, $data['totals']['sends_revealed']);
        self::assertSame([], $data['top_senders']);
    }

    public function testAccountRemovalAndFeatureDisableDoNotEraseObservedActivity(): void
    {
        $this->event('created');
        foreach (['disabled = 1', "deleted_at = '1234'"] as $change) {
            DB::$connection->exec('UPDATE stats_fixture_users SET disabled = 0, deleted_at = NULL');
            DB::$connection->exec('UPDATE stats_fixture_users SET ' . $change);
            $data = $this->statistics(['otv_is_enabled' => 0]);
            self::assertFalse($data['enabled']);
            self::assertTrue($data['available']);
            self::assertSame(1, $data['totals']['created']);
            self::assertSame(str_starts_with($change, 'disabled') ? 'disabled' : 'deleted', $data['top_senders'][0]['account_state']);
        }
        DB::$connection->exec('DELETE FROM stats_fixture_users');
        $data = $this->statistics();
        self::assertSame(42, $data['top_senders'][0]['id']);
        self::assertSame('missing', $data['top_senders'][0]['account_state']);
        self::assertSame('', $data['top_senders'][0]['login']);
        self::assertSame(1, $data['totals']['created']);
    }

    public function testSystemExclusionsUseOriginatorRatherThanAnonymousActor(): void
    {
        $this->event('created', 900);
        $this->event('revealed', 900, 1);
        $this->event('created', 42, 2);
        $this->event('revealed', 42, 2);
        $data = $this->statistics(['otv_is_enabled' => 1], [900, 900, 0]);
        self::assertSame(1, $data['totals']['created']);
        self::assertSame(1, $data['totals']['revealed']);
        self::assertSame([42], array_column($data['top_senders'], 'id'));
    }

    public function testTopFiveIsExactBoundedAndHasStableTies(): void
    {
        foreach (range(1, 510) as $sender) {
            $this->event('created', $sender, $sender, 500);
        }
        foreach (range(1, 6) as $send) {
            $this->event('created', 700, 1000 + $send, 600);
        }
        $this->event('created', 600, 1600, 700);
        $data = $this->statistics();
        self::assertCount(5, $data['top_senders']);
        self::assertSame([700, 600, 1, 2, 3], array_column($data['top_senders'], 'id'));
        self::assertSame([6, 1, 1, 1, 1], array_column($data['top_senders'], 'created'));
        self::assertSame(512, $data['totals']['senders']);
        self::assertSame($data, $this->statistics());
    }

    public function testPayloadContainsOnlyAggregateMetadataAndAdministratorSenderIdentity(): void
    {
        $this->event('created');
        $data = $this->statistics();
        self::assertSame(['id', 'login', 'name', 'account_state', 'created', 'revealed', 'reveal_failed', 'last_created'],
            array_keys($data['top_senders'][0]));
        self::assertSame('Alice Sender', $data['top_senders'][0]['name']);
        self::assertStringNotContainsString('private-email-canary', json_encode($data));
        foreach (['item_id', 'send_id', 'actor_id', 'encrypted', 'protected_key', 'email'] as $forbidden) {
            self::assertStringNotContainsString('"' . $forbidden . '"', json_encode($data), $forbidden);
        }
        foreach (['item_id', 'actor_id', 'encrypted', 'protected_key', 'email', 'SELECT *', 'stats_fixture_items', 'stats_fixture_otv'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, implode("\n", DB::$queries), $forbidden);
        }
    }

    public function testAuditAndIdentityQueryFailuresReturnUnavailableNotFabricatedZeroCounters(): void
    {
        $this->event('created');
        foreach ([1, 2] as $query) {
            DB::$queries = [];
            DB::$failQuery = $query;
            $data = $this->statistics();
            self::assertFalse($data['available']);
            self::assertTrue($data['error']);
            self::assertSame('query_failed', $data['reason']);
            self::assertNull($data['totals']);
            self::assertNull($data['creations']);
            self::assertSame([], $data['top_senders']);
            self::assertStringNotContainsString('secret-canary', json_encode($data));
        }
    }

    public function testMissingMigrationIsDistinguishedFromAnEmptyJournal(): void
    {
        DB::$connection->exec('DROP TABLE stats_fixture_secure_send_audit');
        $data = $this->statistics();
        self::assertFalse($data['available']);
        self::assertNull($data['totals']);
    }

    public function testMissingAggregateResultCannotMasqueradeAsAnEmptyPeriod(): void
    {
        $this->event('created');
        DB::$missingSummary = true;
        $data = $this->statistics();
        self::assertFalse($data['available']);
        self::assertNull($data['totals']);
        self::assertNull($data['creations']);
        self::assertSame([], $data['top_senders']);
        self::assertCount(1, DB::$queries);
    }

    public function testInvalidRangesAreRejectedBeforeAnyDatabaseRead(): void
    {
        foreach ([[-1, 900], [900, 100], [100, 100 + 91 * 86400]] as [$from, $to]) {
            try {
                secureSendBuildOperationalStatistics($from, $to, [], []);
                self::fail('Invalid range was accepted');
            } catch (InvalidArgumentException $e) {
                self::assertSame([], DB::$queries);
            }
        }
    }

    public function testAllDashboardPeriodsReuseTheConfiguredTimezoneResolver(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
        try {
            $to = (new DateTimeImmutable('2026-10-26 12:00:00'))->getTimestamp();
            foreach (['24h', 'current_week', 'current_month', '7d', '30d', '90d'] as $period) {
                $from = opsStatsResolvePeriodRange($period, $to)['from'];
                $this->event('created', 42, $from, $from);
                $data = secureSendBuildOperationalStatistics($from, $to, [], []);
                self::assertSame($from, $data['meta']['from']);
                self::assertSame($to, $data['meta']['to']);
                self::assertGreaterThanOrEqual(1, $data['totals']['created']);
            }
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testStatisticsPagePermissionRemainsAdministratorOnly(): void
    {
        DB::$connection->exec("UPDATE stats_fixture_users SET key_tempo = 'fixture-key'");
        $checks = new PerformChecks(['type' => 'get_operational_statistics'], ['user_id' => 42, 'user_key' => 'fixture-key']);
        foreach (['admin = 0', 'gestionnaire = 1', 'can_manage_all_users = 1', 'can_manage_lapr = 1'] as $role) {
            DB::$connection->exec('UPDATE stats_fixture_users SET admin = 0, gestionnaire = 0, can_manage_all_users = 0, can_manage_lapr = 0');
            DB::$connection->exec('UPDATE stats_fixture_users SET ' . $role);
            self::assertFalse($checks->userAccessPage('admin'));
            self::assertFalse($checks->userAccessPage('statistics'));
        }
        DB::$connection->exec('UPDATE stats_fixture_users SET admin = 1');
        self::assertTrue($checks->userAccessPage('admin'));
        self::assertTrue($checks->userAccessPage('statistics'));
        DB::$connection->exec("UPDATE stats_fixture_users SET deleted_at = '1234'");
        self::assertFalse($checks->userAccessPage('admin'));
    }

    public function testExistingAuthenticatedEndpointPublishesTheAdditiveUsersContract(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $start = strpos($source, "case 'get_operational_statistics':");
        $end = strpos($source, "case 'save_sending_statistics':", $start);
        $case = substr($source, $start, $end - $start);
        self::assertStringContainsString("checkSession() === false", substr($source, 0, $start));
        self::assertStringContainsString("userAccessPage('admin') === false", substr($source, 0, $start));
        self::assertLessThan(strpos($case, 'secureSendBuildOperationalStatistics('), strpos($case, "\$post_key !== \$session->get('key')"));
        self::assertStringContainsString("'secure_send' => \$secureSendStatistics", $case);
        self::assertStringContainsString('opsStatsResolvePeriodRange($period, $nowTs)', $case);
        self::assertStringContainsString('array(TP_USER_ID, OTV_USER_ID, API_USER_ID, SSH_USER_ID)', $case);
        self::assertStringContainsString("'rankings' => \$userRankings", $case);
        $proxy = (string) file_get_contents(__DIR__ . '/../../public/sources/admin.queries.php');
        self::assertStringContainsString('/app/sources/admin.queries.php', $proxy);
    }
}
