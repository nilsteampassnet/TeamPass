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
 * Filtering and pagination rules for the administrator activity feed.
 *
 * @file      AdminActivityTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
require_once __DIR__ . '/../../app/sources/admin_activity_logic.php';

/** Parse real MeekroDB placeholders without connecting to MySQL. */
class ActivityTestSql extends MeekroDB
{
    /** Quote strings for the SQLite integration fixture. */
    public function escape($str) { return "'" . str_replace("'", "''", (string) $str) . "'"; }
}

/** Exercise actual filtered SQL and keyset pagination across the three journals. */
final class AdminActivityTest extends TestCase
{
    private PDO $db;
    private array $tables = ['log_items' => 'test_log_items', 'log_system' => 'test_log_system',
        'users' => 'test_users', 'items' => 'test_items', 'misc' => 'test_misc'];

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec('CREATE TABLE test_log_items (increment_id INTEGER, date TEXT, id_user INTEGER, action TEXT, raison TEXT, id_item INTEGER);
            CREATE TABLE test_users (id INTEGER, login TEXT);
            CREATE TABLE test_items (id INTEGER, label TEXT);
            CREATE TABLE test_log_system (id INTEGER, date TEXT, type TEXT, label TEXT, qui TEXT, field_1 TEXT);
            CREATE TABLE test_misc (increment_id INTEGER, created_at TEXT, type TEXT, valeur TEXT);
            INSERT INTO test_users VALUES (1, "alice");
            INSERT INTO test_items VALUES (1, "Item");
            INSERT INTO test_log_items VALUES (1, "990", 1, "at_creation", "", 1), (2, "990", 1, "at_shown", "", 1);
            INSERT INTO test_log_system VALUES (1, "990", "failed_auth", "wrong_mfa_code", "127.0.0.1", "unknown"),
                (2, "990", "user_connection", "connection", "1", ""),
                (3, "995", "user_connection", "at_2fa_google_code_send_by_email", "1", "alice"),
                (4, "600", "failed_auth", "user_not_exists", "127.0.0.1", "old"),
                (5, "1001", "failed_auth", "user_not_exists", "127.0.0.1", "future");
            INSERT INTO test_misc VALUES (1, "990", "kb_log", \'{"action":"at_creation","user_login":"alice","label":"Article"}\');');
    }

    /** Execute the same union and predicate as the AJAX endpoint. */
    private function rows(array $categories, ?array $cursor = null, bool $newer = false): array
    {
        $options = adminActivityOptions(['categories' => $categories], 1000, true);
        [$sql, $values] = adminActivityQuery($options, $this->tables);
        if ($sql === '') { return []; }
        $where = '';
        if ($cursor !== null) {
            [$predicate, $cursorValues] = adminActivityCursorPredicate($cursor, $newer);
            $where = ' WHERE ' . $predicate;
            array_push($values, ...$cursorValues);
        }
        $parser = new ActivityTestSql();
        return $this->db->query($parser->parse('SELECT * FROM (' . $sql . ') activity' . $where
            . ' ORDER BY timestamp DESC, source_rank DESC, event_id DESC', ...$values))->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testDefaultsAndBoundsIgnoreUnsupportedInput(): void
    {
        $options = adminActivityOptions([], 1000, false);
        self::assertSame(['changes', 'accesses'], $options['categories']);
        self::assertSame(700, $options['since']);
        self::assertSame(10, $options['limit']);
        $options = adminActivityOptions(['categories' => ['failed', 'kb', 'sql injection', []],
            'minutes' => 60, 'expanded' => true, 'until' => 999999], 1000, false);
        self::assertSame(['failed'], $options['categories']);
        self::assertSame(1000, $options['until']);
        self::assertSame(50, $options['limit']);
        self::assertSame([], $this->rows([]));
    }

    public function testFiltersExcludeAccessesAndNonLoginConnectionEvents(): void
    {
        self::assertSame(['at_creation'], array_column($this->rows(['changes']), 'action'));
        self::assertSame(['at_shown'], array_column($this->rows(['accesses']), 'action'));
        self::assertSame(['wrong_mfa_code'], array_column($this->rows(['failed']), 'action'));
        self::assertSame(['connection'], array_column($this->rows(['connections']), 'action'));
        self::assertCount(1, $this->rows(['kb']));
    }

    public function testAuthenticationJoinDoesNotDependOnConnectionCollation(): void
    {
        foreach ([['failed'], ['connections'], ['failed', 'connections']] as $categories) {
            [$sql] = adminActivityQuery(adminActivityOptions(['categories' => $categories], 1000, true), $this->tables);
            self::assertStringContainsString('l.qui = u.id', $sql);
            self::assertDoesNotMatchRegularExpression('/CAST\([^)]*\bAS\s+CHAR\b/i', $sql);
        }
        $connection = $this->rows(['connections'])[0];
        self::assertSame('alice', $connection['user_login']);
        self::assertSame(1, (int) $connection['user_id']);
    }

    public function testPaginationIsStableAcrossEqualSecondsAndNewInserts(): void
    {
        $categories = ['changes', 'accesses', 'failed', 'connections', 'kb'];
        $rows = $this->rows($categories);
        self::assertCount(5, $rows);
        $second = $rows[1];
        $cursor = [(int) $second['timestamp'], (int) $second['source_rank'], (int) $second['event_id']];
        $expected = array_slice($rows, 2);
        $this->db->exec('INSERT INTO test_log_system VALUES (6, "999", "failed_auth", "wrong_mfa_code", "127.0.0.1", "alice")');
        self::assertSame($expected, $this->rows($categories, $cursor));
        self::assertCount(2, $this->rows($categories, $cursor, true));
    }

    public function testItemChannelSurvivesQueryAndFormattingWithoutExposingLogDetails(): void
    {
        $this->db->exec("INSERT INTO test_log_items VALUES (3, '991', 1, 'at_shown', 'tp_src=api', 1)");
        $rows = $this->rows(['accesses']);
        $api = adminActivityFormat($rows[0], static fn ($key) => $key);
        $web = adminActivityFormat($rows[1], static fn ($key) => $key);
        self::assertSame('api', $api['channel']);
        self::assertSame('web', $web['channel']);
        self::assertSame($web['action_text'], $api['action_text']);
        self::assertSame('Item', $api['item_label']);
        self::assertArrayNotHasKey('detail', $api);
    }

    public function testPasskeyUseIsAnAccessWithTheUpstreamTranslatedLabel(): void
    {
        $this->db->exec("INSERT INTO test_log_items VALUES (3, '991', 1, 'at_webauthn_credential_used', 'example.test | tp_src=api', 1)");
        self::assertSame(['at_creation'], array_column($this->rows(['changes']), 'action'));
        $rows = $this->rows(['accesses']);
        self::assertSame(['at_webauthn_credential_used', 'at_shown'], array_column($rows, 'action'));
        $formatted = adminActivityFormat($rows[0], static fn ($key) => $key);
        self::assertSame('action_webauthn_used', $formatted['action_text']);
        self::assertSame('api', $formatted['channel']);
        self::assertSame('Item', $formatted['item_label']);
        self::assertArrayNotHasKey('detail', $formatted);
    }

    public function testStoredEntitiesAreDecodedOnceForItemsConnectionsAndKnowledgeBase(): void
    {
        $encodedLogin = 'O&#039;Brien &amp; Zo&euml;';
        $encodedLabel = 'S&eacute;curit&eacute; &lt;script&gt; &amp;lt;literal&amp;gt;';
        foreach (['item', 'user_connection', 'kb'] as $source) {
            $row = $this->rows(['changes'])[0];
            $row['source_type'] = $source;
            $row['user_login'] = $encodedLogin;
            $row['item_label'] = $source === 'user_connection' ? null : $encodedLabel;
            if ($source === 'kb') {
                $row['detail'] = json_encode(['action' => 'at_creation', 'user_login' => $encodedLogin,
                    'label' => $encodedLabel], JSON_THROW_ON_ERROR);
            }
            $formatted = adminActivityFormat($row, static fn ($key) => $key);
            self::assertSame("O'Brien & Zoë", $formatted['user_login']);
            self::assertSame($source === 'user_connection' ? null : 'Sécurité <script> &lt;literal&gt;', $formatted['item_label']);
        }
    }

    public function testFailedLoginEscapingMatchesTheJournalsPage(): void
    {
        $row = $this->rows(['failed'])[0];
        $row['action'] = 'api_invalid_password';
        $row['detail'] = addslashes('O&#039;Brien\\example | tp_src=api');
        $formatted = adminActivityFormat($row, static fn ($key) => $key);
        self::assertSame("O'Brien\\example", $formatted['user_login']);
        self::assertSame('api', $formatted['channel']);
    }

    public function testItemAndKnowledgeBaseActionsAreLowercasedWithUnicodeSupport(): void
    {
        foreach (['item', 'kb', 'failed_auth', 'user_connection'] as $source) {
            foreach (['at_copy', 'at_restored', 'at_password_shown_edit_form', 'at_password_shown'] as $action) {
                $row = $this->rows(['changes'])[0];
                $row['source_type'] = $source;
                $row['action'] = $action;
                if ($source === 'kb') {
                    $row['detail'] = json_encode(['action' => $action], JSON_THROW_ON_ERROR);
                }
                $formatted = adminActivityFormat($row, static fn ($key) => 'ÉLÉMENT Copied');
                self::assertSame(in_array($source, ['item', 'kb'], true) ? 'élément copied' : 'ÉLÉMENT Copied', $formatted['action_text']);
            }
        }
    }

    public function testMalformedCursorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        adminActivityOptions(['before' => [990, 2, '1 OR 1=1']], 1000, true);
    }

    public function testFailedLoginRemainsSubmittedTextAndApiMarkersCannotBeForged(): void
    {
        $row = $this->rows(['failed'])[0];
        $row['detail'] = '<script> | tp_src=api';
        $formatted = adminActivityFormat($row, static fn ($key) => $key);
        self::assertSame('<script> | tp_src=api', $formatted['user_login']);
        self::assertSame('web', $formatted['channel']);
        self::assertSame('wrong_mfa_code', $formatted['reason']);
        self::assertArrayNotHasKey('detail', $formatted);
        $row['action'] = 'api_invalid_password';
        $row['detail'] = 'alice | tp_src=api';
        $formatted = adminActivityFormat($row, static fn ($key) => $key);
        self::assertSame('alice', $formatted['user_login']);
        self::assertSame('api', $formatted['channel']);
    }
}
