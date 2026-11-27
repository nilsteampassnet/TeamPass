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
 * @file      secure_send_statistics_database.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Exercise the production statistics queries in the existing disposable MariaDB harness.
 *
 * Only called after secure_send_database.php validates the test-only database name,
 * builds prefixed tables and runs the lifecycle checks. No installation data is touched.
 * The fixed historical window isolates fixtures from the harness's real-time events.
 *
 * @param array $settings Synthetic application settings from the parent harness
 * @return void
 */
function secureSendStatisticsDatabaseChecks(array $settings): void
{
    $sqlMode = (string) DB::queryFirstField('SELECT @@SESSION.sql_mode');
    if (!in_array('ONLY_FULL_GROUP_BY', explode(',', $sqlMode), true)) {
        DB::query('SET SESSION sql_mode = %s', $sqlMode . ',ONLY_FULL_GROUP_BY');
    }
    try {
        $empty = secureSendBuildOperationalStatistics(100, 200, $settings, []);
        check($empty['available'] === true && $empty['totals']['created'] === 0,
            'Empty historical window failed with ONLY_FULL_GROUP_BY');

        DB::insert(prefixTable('users'), ['id' => 9010, 'login' => 'fixture-statistics-sender',
            'name' => 'Synthetic', 'lastname' => 'Sender', 'disabled' => 1]);
        $events = [
            ['created', 9010, 111, 100, 'note', 1, 1],
            ['created', 9011, 112, 200, 'item_v2', 0, 0],
            ['created', 9011, 114, 99, 'item', 0, 0],
            ['created', 9011, 115, 201, 'item', 0, 0],
            ['revealed', 9010, 111, 101, 'note', 1, 1],
            ['revealed', 9010, 111, 102, 'note', 1, 1],
            ['revealed', 9012, 113, 150, 'item', 0, 0],
            ['revoked', 9011, 112, 150, 'item', 0, 0],
            ['expired', 9010, 111, 150, 'note', 1, 1],
            ['invalidated', 9010, 111, 160, 'note', 1, 1],
        ];
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $events[] = ['reveal_failed', 9010, 111, 150 + $attempt, 'note', 1, 1];
        }
        foreach ($events as [$event, $originator, $sendId, $observedAt, $type, $protected, $public]) {
            $reason = ['revoked' => 'sender_revoked', 'expired' => 'deadline_elapsed',
                'invalidated' => 'attempts_exhausted', 'reveal_failed' => 'wrong_credentials'][$event] ?? '';
            $actor = in_array($event, ['created', 'revoked'], true) ? $originator : null;
            DB::insert(prefixTable('secure_send_audit'), secureSendAuditRecord([
                'id' => $sendId, 'originator' => $originator, 'send_type' => $type,
                'item_id' => $type === 'note' ? null : 123, 'timestamp' => 10, 'time_limit' => 99,
                'max_views' => 5, 'views' => 2, 'failed_attempts' => 5,
                'has_passphrase' => $protected, 'shared_globaly' => $public,
            ], $event, $reason, $actor, $observedAt));
        }
        $data = secureSendBuildOperationalStatistics(100, 200, $settings, []);
        check($data['available'] === true && $data['error'] === false, 'MariaDB aggregate queries failed');
        check($data['totals'] === ['created' => 2, 'revealed' => 3, 'reveal_failed' => 5,
            'revoked' => 1, 'invalidated' => 1, 'expired' => 1, 'sends_revealed' => 2, 'senders' => 2],
            'MariaDB period/event totals differ from the metadata contract');
        check($data['creations'] === ['items' => 1, 'notes' => 1, 'unknown' => 0,
            'protected' => 1, 'unprotected' => 1, 'public_links' => 1, 'internal_links' => 1],
            'MariaDB creation policy totals were inflated by later events');
        check(array_column($data['top_senders'], 'id') === [9011, 9010], 'Top sender ranking or tie break differs');
        check(array_column($data['top_senders'], 'account_state') === ['missing', 'disabled'],
            'Missing/disabled accounts lost their historical activity');

        DB::update(prefixTable('users'), ['disabled' => 0, 'deleted_at' => 1], 'id = %i', 9010);
        $deleted = secureSendBuildOperationalStatistics(100, 200, $settings, []);
        check($deleted['top_senders'][1]['account_state'] === 'deleted', 'Deleted sender status was not retained');
        DB::delete(prefixTable('users'), 'id = %i', 9010);
        $disabled = secureSendBuildOperationalStatistics(100, 200, array_replace($settings, ['otv_is_enabled' => 0]), []);
        check($disabled['enabled'] === false && $disabled['totals'] === $data['totals'],
            'Feature disable or account purge erased usage history');
        check($disabled['top_senders'][1]['account_state'] === 'missing', 'Purged sender requires an id fallback');

        $excluded = secureSendBuildOperationalStatistics(100, 200, $settings, [9010]);
        check($excluded['totals']['created'] === 1 && $excluded['totals']['revealed'] === 1,
            'MeekroDB integer-list filtering did not exclude the original sender');
        // Exercise a full population ranking, with more than five tied eligible senders.
        foreach (range(9020, 9026) as $originator) {
            DB::insert(prefixTable('secure_send_audit'), secureSendAuditRecord([
                'id' => $originator, 'originator' => $originator, 'send_type' => 'note',
            ], 'created', '', $originator, 180));
        }
        $top = secureSendBuildOperationalStatistics(100, 200, $settings, []);
        check(array_column($top['top_senders'], 'id') === [9011, 9020, 9021, 9022, 9023],
            'MariaDB top five was not bounded and deterministic');

        $link = ['id' => 9200, 'originator' => 9201, 'send_type' => 'note', 'shared_globaly' => 1];
        $otherLink = array_replace($link, ['id' => 9202, 'has_passphrase' => 1]);
        $unknownLink = array_replace($link, ['id' => 9203, 'shared_globaly' => 0]);
        $excludedLink = array_replace($link, ['id' => 9204, 'originator' => 9299]);
        $unknownCreation = secureSendAuditRecord($unknownLink, 'created', actorId: 9201, now: 307);
        $unknownCreation['attachment_count'] = null;
        foreach ([
            secureSendAuditRecord($link, 'created', actorId: 9201, now: 300, attachmentCount: 1, deliveryChannel: 'direct_email'),
            secureSendAuditRecord($link, 'download_authorized', now: 301, attachmentCount: 1),
            secureSendAuditRecord($link, 'email_outcome', 'accepted', now: 312),
            secureSendAuditRecord($otherLink, 'created', actorId: 9201, now: 305, deliveryChannel: 'no_email'),
            secureSendAuditRecord($otherLink, 'invalidated', 'attempts_exhausted', now: 306),
            $unknownCreation,
            secureSendAuditRecord($unknownLink, 'invalidated', 'sender_unavailable', now: 310),
            secureSendAuditRecord($excludedLink, 'created', actorId: 9299, now: 303, deliveryChannel: 'direct_email'),
        ] as $record) {
            DB::insert(prefixTable('secure_send_audit'), $record);
        }
        $governance = secureSendReadAuditPeriodSummary(300, 310, [9299]);
        foreach ([
            'created' => 3, 'public_links' => 2, 'public_unprotected' => 1, 'invalidated_access' => 1,
            'downloads_authorized' => 1, 'email_queued' => 1, 'email_files_shared' => 1,
            'file_count_unknown' => 1, 'channel_unknown' => 1, 'email_accepted' => 0,
        ] as $metric => $expected) {
            check((int) $governance[$metric] === $expected, 'Secure Send governance count differs for ' . $metric);
        }
        $later = secureSendReadAuditPeriodSummary(311, 312, []);
        check((int) $later['created'] === 0 && (int) $later['email_accepted'] === 1,
            'Invitation outcomes must follow their own observation time');
        echo "OK: Secure Send statistics preserve history, period boundaries and exact top five with ONLY_FULL_GROUP_BY\n";
    } finally {
        DB::query('SET SESSION sql_mode = %s', $sqlMode);
    }
}
