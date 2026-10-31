<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of the TeamPass project, distributed under the GNU GPL-3.0.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendActiveLinksTest extends TestCase
{
    private array $settings = ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1];

    protected function setUp(): void
    {
        if (!extension_loaded('sqlite3')) {
            self::markTestSkipped('The active-link inventory SQL requires SQLite3.');
        }
        require_once __DIR__ . '/../Fixtures/secure_send_active_db.php';
        require_once __DIR__ . '/../../app/sources/secure_send_active.php';
        require_once __DIR__ . '/../../app/sources/secure_send_logic.php';
        DB::$connection = new SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->exec('CREATE TABLE active_fixture_users (id INTEGER PRIMARY KEY, admin INTEGER DEFAULT 0,
            disabled INTEGER DEFAULT 0, deleted_at TEXT)');
        DB::$connection->exec('CREATE TABLE active_fixture_items (id INTEGER PRIMARY KEY, id_tree INTEGER,
            label TEXT, inactif INTEGER DEFAULT 0, deleted_at INTEGER)');
        DB::$connection->exec('CREATE TABLE active_fixture_automatic_del (item_id INTEGER,
            del_enabled INTEGER, del_type INTEGER, del_value INTEGER)');
        DB::$connection->exec('CREATE TABLE active_fixture_otv (id INTEGER PRIMARY KEY, originator INTEGER,
            item_id INTEGER, send_type TEXT, has_passphrase INTEGER, protected_key TEXT, views INTEGER,
            max_views INTEGER, time_limit INTEGER, failed_attempts INTEGER, payload TEXT, code TEXT)');
        DB::$connection->exec('INSERT INTO active_fixture_users (id) VALUES (42), (43)');
        DB::$connection->exec("INSERT INTO active_fixture_items (id, id_tree, label)
            VALUES (123, 7, 'R&amp;D &lt;item&gt;'), (124, 8, 'Other sender')");
    }

    protected function tearDown(): void
    {
        if (isset(DB::$connection)) {
            DB::$connection->close();
        }
    }

    /** Insert synthetic link state without exercising unrelated cryptographic storage. */
    private function link(int $id = 1, array $changes = []): array
    {
        $row = array_replace(['id' => $id, 'originator' => 42, 'item_id' => 123, 'send_type' => 'item_v2',
            'has_passphrase' => 0, 'protected_key' => '', 'views' => 0, 'max_views' => 2,
            'time_limit' => 2000, 'failed_attempts' => 0, 'payload' => 'encrypted-canary', 'code' => 'credential-canary'], $changes);
        $statement = DB::$connection->prepare('INSERT INTO active_fixture_otv (' . implode(',', array_keys($row))
            . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
        foreach (array_values($row) as $position => $value) {
            $statement->bindValue($position + 1, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        $statement->execute();
        return $row;
    }

    public function testPersonalAndGlobalInventoriesUseEachSendersCurrentScope(): void
    {
        $this->link();
        $this->link(2, ['originator' => 43, 'item_id' => 124]);
        $this->link(3, ['originator' => 43, 'item_id' => 123]); // Sender 43 cannot read this folder.
        $this->link(4, ['send_type' => 'note', 'item_id' => null]);
        self::assertSame(3, secureSendCountActiveLinks($this->settings, null, null, 1000));
        self::assertSame(2, secureSendCountActiveLinks($this->settings, 42, null, 1000));
        self::assertSame(1, secureSendCountActiveLinks($this->settings, 43, null, 1000));
        self::assertSame(1, secureSendCountActiveLinks($this->settings, 42, 123, 1000));
        DB::$folders[42] = [];
        self::assertSame(1, secureSendCountActiveLinks($this->settings, 42, null, 1000));
        self::assertSame(0, secureSendCountActiveLinks($this->settings, 0, null, 1000));
    }

    public function testInventoryMatchesRecipientStateRulesIncludingExactExpiry(): void
    {
        $states = [[], ['views' => 2], ['max_views' => 0], ['time_limit' => 1000],
            ['time_limit' => 999], ['failed_attempts' => 5], ['send_type' => 'unknown'],
            ['has_passphrase' => 1], ['has_passphrase' => 1, 'protected_key' => 'wrapped'],
            ['send_type' => 'item'], ['send_type' => 'note', 'item_id' => null]];
        foreach ($states as $index => $state) {
            $row = $this->link($index + 1, $state);
            foreach ([[], ['secure_send_allow_notes' => 0], ['secure_send_require_passphrase' => 1], ['otv_is_enabled' => 0]] as $policy) {
                $settings = array_replace($this->settings, $policy);
                $ids = array_column(secureSendActiveLinks($settings, 42, null, 1000), 'id');
                self::assertSame(secureSendIsAvailable($row, $settings, 1000), in_array($index + 1, $ids, true),
                    json_encode([$state, $policy]));
            }
        }
    }

    public function testDisabledDeletedAndMissingSendersAndUnavailableItemsAreExcluded(): void
    {
        $this->link();
        foreach (['disabled = 1', "deleted_at = '2026-10-31'", 'admin = 1'] as $change) {
            DB::$connection->exec('UPDATE active_fixture_users SET disabled = 0, deleted_at = NULL, admin = 0');
            DB::$connection->exec('UPDATE active_fixture_users SET ' . $change . ' WHERE id = 42');
            self::assertSame(0, secureSendCountActiveLinks($this->settings, null, null, 1000));
        }
        DB::$connection->exec('UPDATE active_fixture_users SET admin = 0');
        foreach (['inactif = 1', 'deleted_at = 1'] as $change) {
            DB::$connection->exec('UPDATE active_fixture_items SET inactif = 0, deleted_at = NULL');
            DB::$connection->exec('UPDATE active_fixture_items SET ' . $change);
            self::assertSame(0, secureSendCountActiveLinks($this->settings, 42, null, 1000));
        }
        DB::$connection->exec('DELETE FROM active_fixture_items');
        self::assertSame(0, secureSendCountActiveLinks($this->settings, 42, null, 1000));
        $this->link(2, ['send_type' => 'note', 'item_id' => null]);
        DB::$connection->exec('DELETE FROM active_fixture_users WHERE id = 42');
        self::assertSame(0, secureSendCountActiveLinks($this->settings, null, null, 1000));
    }

    public function testElapsedAndExhaustedItemAutoDeletionPoliciesAreReadWithoutMutation(): void
    {
        $this->link();
        $settings = $this->settings + ['enable_delete_after_consultation' => 1];
        foreach ([[1, 0], [2, 1000], [2, 999]] as [$type, $value]) {
            DB::$connection->exec('DELETE FROM active_fixture_automatic_del');
            DB::$connection->exec("INSERT INTO active_fixture_automatic_del VALUES (123, 1, $type, $value)");
            self::assertSame(0, secureSendCountActiveLinks($settings, 42, null, 1000));
            self::assertSame(1, secureSendCountActiveLinks($this->settings, 42, null, 1000));
        }
        DB::$connection->exec('UPDATE active_fixture_automatic_del SET del_value = 1001');
        self::assertSame(1, secureSendCountActiveLinks($settings, 42, null, 1000));
        self::assertSame(1, DB::$connection->querySingle('SELECT COUNT(*) FROM active_fixture_otv'));
        self::assertSame(0, DB::$connection->querySingle('SELECT inactif FROM active_fixture_items WHERE id = 123'));
        self::assertSame(0, DB::$connection->querySingle('SELECT views FROM active_fixture_otv WHERE id = 1'));
    }

    public function testOnlyPersonalListingSelectsAndDecodesLabelsAndNoSecretIsReturned(): void
    {
        $this->link();
        $this->link(2);
        $count = secureSendCountActiveLinks($this->settings, 42, null, 1000);
        self::assertSame(2, $count);
        self::assertSame([42], DB::$resolvedSenders);
        self::assertCount(2, DB::$queries);
        self::assertStringNotContainsString('i.label', implode(' ', DB::$queries));
        $labels = ['R&amp;D', 'O&#039;Brien', '&quot;quoted&quot;', '&lt;img src=x onerror=alert(1)&gt;'];
        foreach ($labels as $label) {
            $statement = DB::$connection->prepare('UPDATE active_fixture_items SET label = ? WHERE id = 123');
            $statement->bindValue(1, $label, SQLITE3_TEXT);
            $statement->execute();
            $links = secureSendActiveLinks($this->settings, 42, null, 1000, true);
            self::assertSame(html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $links[0]['item_label']);
            self::assertStringNotContainsString('canary', json_encode($links));
        }
    }

    public function testDisabledFeatureDoesNotReadOrCleanUpLinks(): void
    {
        self::assertSame([], secureSendActiveLinks(['otv_is_enabled' => 0], 42));
        self::assertSame([], DB::$queries);
    }

    public function testNavigationUsesTheCurrentAuthorizedFolderAndIsAbsentFromCounts(): void
    {
        $this->link();
        $metadata = secureSendActiveLinks($this->settings, 42, null, 1000);
        self::assertArrayNotHasKey('folder_id', $metadata[0]);
        self::assertArrayNotHasKey('item_label', $metadata[0]);
        $listing = secureSendActiveLinks($this->settings, 42, null, 1000, true);
        self::assertSame(123, $listing[0]['item_id']);
        self::assertSame(7, $listing[0]['folder_id']);

        DB::$connection->exec('UPDATE active_fixture_items SET id_tree = 9 WHERE id = 123');
        DB::$folders[42] = [7, 9];
        $listing = secureSendActiveLinks($this->settings, 42, null, 1000, true);
        self::assertSame(9, $listing[0]['folder_id']);
        DB::$folders[42] = [7];
        self::assertSame([], secureSendActiveLinks($this->settings, 42, null, 1000, true));
    }

    public function testListHandlerReturnsOnlyOwnedAuthorizedItemTargetsAndNullTargetsForNotes(): void
    {
        $expiry = time() + 3600;
        $this->link(1, ['time_limit' => $expiry]);
        $this->link(2, ['originator' => 43, 'item_id' => 124, 'time_limit' => $expiry]);
        $this->link(3, ['send_type' => 'note', 'time_limit' => $expiry]);
        $this->link(4, ['item_id' => 124, 'time_limit' => $expiry]);
        $this->link(5, ['send_type' => 'item', 'time_limit' => $expiry]);
        $source = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($source, "case 'list_secure_sends':");
        $end = strpos($source, "case 'revoke_secure_send':", $start);
        $handler = substr($source, $start, $end - $start);
        $session = new class {
            public function get(string $name): string|int { return $name === 'key' ? 'fixture-session-key' : 42; }
        };
        $lang = new class {
            public function get(string $name): string { return $name; }
        };
        $SETTINGS = $this->settings + ['date_format' => 'Y-m-d', 'time_format' => 'H:i'];
        $inputData = ['key' => 'fixture-session-key', 'user_id' => 43, 'originator' => 43];
        ob_start();
        try {
            eval('switch (true) { case true: ' . $handler . ' }');
            $response = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally {
            ob_end_clean();
        }
        self::assertSame('', $response['error']);
        self::assertSame([5, 3, 1], array_column($response['sends'], 'id'));
        foreach ([$response['sends'][0], $response['sends'][2]] as $send) {
            self::assertSame('item', $send['send_type']);
            self::assertSame(123, $send['item_id']);
            self::assertSame(7, $send['folder_id']);
            self::assertSame('R&D <item>', $send['label']);
        }
        self::assertSame('note', $response['sends'][1]['send_type']);
        self::assertNull($response['sends'][1]['item_id']);
        self::assertNull($response['sends'][1]['folder_id']);
        self::assertStringNotContainsString('canary', json_encode($response));
    }

    public function testCountHandlerUsesSessionScopeAndRejectsIncorrectKeys(): void
    {
        $this->link(1, ['time_limit' => time() + 3600]);
        $this->link(2, ['originator' => 43, 'item_id' => 124, 'time_limit' => time() + 3600]);
        $source = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($source, "case 'count_secure_sends':");
        $end = strpos($source, "case 'list_secure_sends':", $start);
        $handler = substr($source, $start, $end - $start);
        $session = new class {
            public function get(string $name): string|int { return $name === 'key' ? 'fixture-session-key' : 42; }
        };
        $dispatch = static function (array $SETTINGS, string $key) use ($handler, $session): array {
            $inputData = ['key' => $key, 'user_id' => 43, 'originator' => 43];
            ob_start();
            try {
                eval('switch (true) { case true: ' . $handler . ' }');
                return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
            } finally {
                ob_end_clean();
            }
        };
        self::assertSame(['error' => 'key_not_conform'], $dispatch($this->settings, 'wrong-key'));
        self::assertSame([], DB::$queries);
        self::assertSame(['error' => '', 'enabled' => true, 'count' => 1], $dispatch($this->settings, 'fixture-session-key'));
        DB::$queries = [];
        self::assertSame(['error' => '', 'enabled' => false, 'count' => null], $dispatch(['otv_is_enabled' => 0], 'fixture-session-key'));
        self::assertSame([], DB::$queries);
    }
}
