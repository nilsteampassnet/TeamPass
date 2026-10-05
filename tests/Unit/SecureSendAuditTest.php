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
 * @file      SecureSendAuditTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendAuditTest extends TestCase
{
    private array $settings;
    private string $log;
    private string $previousLog;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../app/sources/otp.functions.php';
        require_once __DIR__ . '/../Fixtures/secure_send_memory_db.php';
        require_once __DIR__ . '/../Fixtures/secure_send_dependencies.php';
        require_once __DIR__ . '/../../app/sources/secure_send.functions.php';
        require_once __DIR__ . '/../../app/sources/secure_send_storage.php';
        DB::reset();
        $this->settings = ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1,
            'cpassman_url' => 'https://vault.example.com', 'syslog_enable' => 1,
            'syslog_host' => 'localhost', 'syslog_port' => 514];
        $this->log = tempnam(sys_get_temp_dir(), 'tp-send-audit-');
        $this->previousLog = (string) ini_get('error_log');
        ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        $contents = (string) file_get_contents($this->log);
        ini_set('error_log', $this->previousLog);
        unlink($this->log);
        self::assertStringNotContainsString('secret-canary', $contents);
    }

    /** Build real encrypted fixtures and, when requested, use the production creation transaction. */
    private function create(array $overrides = [], bool $audited = false): array
    {
        $created = secureSendFixtureCreate($overrides);
        parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
        $parameters = secureSendRequestParameters($query);
        $id = $created['otv_id'];
        if ($audited) {
            $link = DB::$links[$id];
            unset(DB::$links[$id], $link['id']);
            $id = secureSendStoreLink($link, $this->settings);
        }
        return ['id' => $id, 'parameters' => $parameters];
    }

    public function testCreationAndRevealKeepOriginatorAndAnonymousActorSeparate(): void
    {
        foreach (['item', 'note'] as $type) {
            DB::reset();
            $created = $this->create(['send_type' => $type, 'views' => 2], true);
            self::assertSame('', secureSendRedeem($created['parameters'], '', $this->settings)['error']);
            self::assertSame(['created', 'revealed'], array_column(DB::$secureAudit, 'event'));
            self::assertSame([42, 42], array_column(DB::$secureAudit, 'originator'));
            self::assertSame([42, null], array_column(DB::$secureAudit, 'actor_id'));
            self::assertSame([$created['id'], $created['id']], array_column(DB::$secureAudit, 'send_id'));
            self::assertSame([0, 1], array_column(DB::$secureAudit, 'views'));
            self::assertCount(2, DB::$forwarded);
        }
    }

    public function testMetadataAllowlistDiscardsEverySecretAndKeepsPolicy(): void
    {
        $created = $this->create(['send_type' => 'note', 'passphrase' => 'sensitive-phrase',
            'payload' => ['title' => 'sensitive-title', 'secret' => 'sensitive-payload'],
            'shared_globaly' => 1, 'views' => 5], true);
        $link = DB::$links[$created['id']];
        $record = DB::$secureAudit[0];
        self::assertSame('note', $record['send_type']);
        self::assertNull($record['item_id']);
        self::assertSame(1, $record['has_passphrase']);
        self::assertSame(1, $record['is_public']);
        self::assertSame(5, $record['max_views']);
        self::assertSame((int) $link['timestamp'], $record['created_at']);
        self::assertSame((int) $link['time_limit'], $record['expires_at']);
        $serialized = json_encode(DB::$secureAudit) . implode('', DB::$forwarded);
        foreach (['sensitive-phrase', 'sensitive-title', 'sensitive-payload', $link['code'],
            $created['parameters']['key'], $link['encrypted'], $link['protected_key']] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
        self::assertSame(array_keys(secureSendAuditRecord($link, 'created', '', 42)), array_keys($record));
        $snapshot = array_replace($link, ['send_type' => 'item_v2', 'item_id' => 123]);
        self::assertSame('item', secureSendAuditRecord($snapshot, 'revealed')['send_type']);
    }

    public function testUnknownEventsReasonsAndForgedActorsAreRejected(): void
    {
        $created = $this->create();
        $link = DB::$links[$created['id']];
        foreach ([
            ['secret-event', '', null], ['created', 'secret-reason', 42],
            ['created', '', null], ['revoked', 'sender_revoked', 99],
            ['revealed', '', 42],
        ] as [$event, $reason, $actor]) {
            try {
                secureSendAudit($link, $event, $reason, $actor);
                self::fail('Invalid audit metadata was accepted');
            } catch (InvalidArgumentException $e) {
                self::assertStringNotContainsString('secret-', $e->getMessage());
            }
        }
        self::assertSame([], DB::$secureAudit);
    }

    public function testCreationAuditFailureRollsBackTheLink(): void
    {
        $created = $this->create();
        $link = DB::$links[$created['id']];
        DB::$links = [];
        unset($link['id']);
        DB::$failSecureAudit = true;
        try {
            secureSendStoreLink($link, $this->settings);
            self::fail('Unaudited creation succeeded');
        } catch (RuntimeException $e) {
            self::assertSame([], DB::$links);
            self::assertSame([], DB::$secureAudit);
            self::assertSame([], DB::$forwarded);
        }
    }

    public function testRevealAuditFailureRollsBackBothAuditsAndViewWithoutPlaintext(): void
    {
        $created = $this->create();
        DB::$failSecureAudit = true;
        self::assertSame(['error' => 'server_error'], secureSendRedeem($created['parameters'], '', $this->settings));
        self::assertSame(0, DB::$links[$created['id']]['views']);
        self::assertSame([], DB::$audit);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], DB::$forwarded);
    }

    public function testFiveFailedRevealsAndFinalInvalidationRemainAfterDeletion(): void
    {
        $created = $this->create(['passphrase' => 'correct'], true);
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $result = secureSendRedeem($created['parameters'], 'wrong', $this->settings);
            self::assertSame($attempt === 5 ? 'too_many_attempts' : 'wrong_passphrase', $result['error']);
            self::assertArrayNotHasKey('fields', $result);
        }
        self::assertSame([], DB::$links);
        self::assertSame(['created', 'reveal_failed', 'reveal_failed', 'reveal_failed',
            'reveal_failed', 'reveal_failed', 'invalidated'], array_column(DB::$secureAudit, 'event'));
        self::assertSame('attempts_exhausted', DB::$secureAudit[6]['reason']);
        self::assertSame(5, DB::$secureAudit[6]['failed_attempts']);
        self::assertSame(0, DB::$secureAudit[6]['views']);
        $before = DB::$secureAudit;
        secureSendRedeem($created['parameters'], 'wrong', $this->settings);
        self::assertSame($before, DB::$secureAudit);
    }

    public function testFailedRevealAuditFailureDoesNotIncrementAttemptsOrInvalidate(): void
    {
        $created = $this->create(['passphrase' => 'correct']);
        DB::$links[$created['id']]['failed_attempts'] = 4;
        DB::$failSecureAudit = true;
        self::assertSame(['error' => 'server_error'], secureSendRedeem($created['parameters'], 'wrong', $this->settings));
        self::assertSame(4, DB::$links[$created['id']]['failed_attempts']);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], DB::$forwarded);
    }

    public function testRevocationChecksOwnershipAndPreservesCreationEvidence(): void
    {
        $created = $this->create([], true);
        self::assertFalse(secureSendRevokeLink($created['id'], 99, $this->settings));
        self::assertFalse(secureSendRevokeLink(0, 42, $this->settings));
        self::assertCount(1, DB::$links);
        self::assertCount(1, DB::$secureAudit);
        self::assertTrue(secureSendRevokeLink($created['id'], 42, $this->settings));
        self::assertFalse(secureSendRevokeLink($created['id'], 42, $this->settings));
        self::assertSame([], DB::$links);
        self::assertSame(['created', 'revoked'], array_column(DB::$secureAudit, 'event'));
        self::assertSame([42, 42], array_column(DB::$secureAudit, 'actor_id'));
    }

    public function testFinalInvalidationFailureRollsBackTheFifthAttemptEvent(): void
    {
        $created = $this->create(['passphrase' => 'correct']);
        DB::$links[$created['id']]['failed_attempts'] = 4;
        DB::$failSecureAuditAfter = 1;
        self::assertSame(['error' => 'server_error'], secureSendRedeem($created['parameters'], 'wrong', $this->settings));
        self::assertSame(4, DB::$links[$created['id']]['failed_attempts']);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], DB::$forwarded);
    }

    public function testRevocationAuditOrDeletionFailureRollsBack(): void
    {
        foreach (['audit', 'delete'] as $failure) {
            DB::reset();
            $created = $this->create();
            DB::$failSecureAudit = $failure === 'audit';
            DB::$failDelete = $failure === 'delete';
            try {
                secureSendRevokeLink($created['id'], 42, $this->settings);
                self::fail('Failed revocation succeeded');
            } catch (RuntimeException $e) {
                self::assertCount(1, DB::$links);
                self::assertSame([], DB::$secureAudit);
                self::assertSame([], DB::$forwarded);
            }
        }
    }

    public function testCurrentAccessInvalidationIsAuditedOnce(): void
    {
        foreach (['item', 'note'] as $type) {
            DB::reset();
            $created = $this->create(['send_type' => $type]);
            DB::$activeUser = false;
            self::assertSame(['error' => 'invalid_link'], secureSendRedeem($created['parameters'], '', $this->settings));
            self::assertSame([], DB::$links);
            self::assertSame('invalidated', DB::$secureAudit[0]['event']);
            self::assertSame($type === 'note' ? 'sender_unavailable' : 'item_access_lost', DB::$secureAudit[0]['reason']);
            self::assertSame(42, DB::$secureAudit[0]['originator']);
            self::assertNull(DB::$secureAudit[0]['actor_id']);
        }
    }

    public function testInvalidationAuditFailureKeepsTheLink(): void
    {
        $created = $this->create();
        DB::$access = false;
        DB::$failSecureAudit = true;
        self::assertSame(['error' => 'server_error'], secureSendRedeem($created['parameters'], '', $this->settings));
        self::assertCount(1, DB::$links);
        self::assertSame([], DB::$secureAudit);
    }

    public function testExpiryCleanupIsBoundedReplayableAndDoesNotInventPastCreations(): void
    {
        $created = $this->create();
        $row = DB::$links[$created['id']];
        $now = time();
        for ($id = 1; $id <= 102; ++$id) {
            DB::$links[$id] = array_replace($row, ['id' => $id, 'time_limit' => $now - 1]);
        }
        DB::$links[103] = array_replace($row, ['id' => 103, 'time_limit' => $now + 1]);
        self::assertSame(100, secureSendPurgeExpiredLinks($this->settings, $now));
        self::assertSame(2, secureSendPurgeExpiredLinks($this->settings, $now));
        self::assertSame(0, secureSendPurgeExpiredLinks($this->settings, $now));
        self::assertSame([103], array_keys(DB::$links));
        self::assertSame(['expired'], array_values(array_unique(array_column(DB::$secureAudit, 'event'))));
        self::assertCount(102, DB::$secureAudit);
        self::assertSame($now, DB::$secureAudit[0]['occurred_at']);
        self::assertSame($now - 1, DB::$secureAudit[0]['expires_at']);
        self::assertCount(102, array_unique(array_column(DB::$secureAudit, 'send_id')));
    }

    public function testExpiryAuditOrDeletionFailureKeepsTheEntireBatch(): void
    {
        foreach (['audit', 'delete'] as $failure) {
            DB::reset();
            $created = $this->create();
            DB::$links[$created['id']]['time_limit'] = time() - 1;
            DB::$failSecureAudit = $failure === 'audit';
            DB::$failDelete = $failure === 'delete';
            try {
                secureSendPurgeExpiredLinks($this->settings);
                self::fail('Failed cleanup succeeded');
            } catch (RuntimeException $e) {
                self::assertCount(1, DB::$links);
                self::assertSame([], DB::$secureAudit);
                self::assertSame([], DB::$forwarded);
            }
        }
    }

    public function testGetUnconfirmedPostAndUnknownLinksDoNotGenerateAuditNoise(): void
    {
        $created = $this->create();
        $tokens = [];
        secureSendPrepareRecipient($created['parameters'], 'GET', $this->settings, $tokens);
        secureSendPrepareRecipient($created['parameters'], 'POST', $this->settings, $tokens);
        $bad = array_replace($created['parameters'], ['code' => 'not-a-link']);
        secureSendRedeem($bad, '', $this->settings);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], DB::$forwarded);
    }

    public function testCleanupFailureOnSecondLinkRestoresTheFirstDeletionAndEvent(): void
    {
        $first = $this->create();
        $second = $this->create();
        foreach ([$first['id'], $second['id']] as $id) {
            DB::$links[$id]['time_limit'] = time() - 1;
        }
        DB::$failSecureAuditAfter = 1;
        try {
            secureSendPurgeExpiredLinks($this->settings);
            self::fail('Partially audited cleanup succeeded');
        } catch (RuntimeException $e) {
            self::assertCount(2, DB::$links);
            self::assertSame([], DB::$secureAudit);
            self::assertSame([], DB::$forwarded);
        }
    }

    public function testAutomaticDeletionAuditFailureRollsBackAllSideEffects(): void
    {
        $this->settings['enable_delete_after_consultation'] = 1;
        DB::$automatic = ['del_enabled' => 1, 'del_type' => 2, 'del_value' => time() - 1];
        $created = $this->create();
        DB::$failSecureAudit = true;
        self::assertSame(['error' => 'server_error'], secureSendRedeem($created['parameters'], '', $this->settings));
        self::assertCount(1, DB::$links);
        self::assertSame(0, DB::$item['inactif']);
        self::assertNull(DB::$item['deleted_at']);
        self::assertSame([123 => ['id' => 123]], DB::$cache);
        self::assertSame([7 => 1], DB::$folderCounts);
        self::assertSame([], DB::$audit);
        self::assertSame([], DB::$secureAudit);
        self::assertSame([], DB::$forwarded);
    }

    public function testForwardingFailureCannotUndoCommittedCreation(): void
    {
        DB::$failForward = true;
        $created = $this->create([], true);
        self::assertArrayHasKey($created['id'], DB::$links);
        self::assertCount(1, DB::$secureAudit);
        self::assertSame([], DB::$forwarded);
    }
}
