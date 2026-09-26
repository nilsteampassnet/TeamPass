<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecureSendAccessTest extends TestCase
{
    /** Build only the tables queried by the access boundary, without a TeamPass installation. */
    protected function setUp(): void
    {
        require_once __DIR__ . '/../Fixtures/secure_send_access_db.php';
        require_once __DIR__ . '/../../app/sources/secure_send_access.php';
        DB::$connection = new SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->exec('CREATE TABLE sharing_fixture_users (id INTEGER, admin INTEGER, disabled INTEGER, deleted_at TEXT)');
        DB::$connection->exec('CREATE TABLE sharing_fixture_items (id INTEGER, id_tree INTEGER, label TEXT, pw TEXT, pw_iv TEXT, pw_len INTEGER, inactif INTEGER, deleted_at INTEGER)');
        DB::$connection->exec('CREATE TABLE sharing_fixture_sharekeys_items (user_id INTEGER, object_id INTEGER, share_key TEXT, increment_id INTEGER)');
        DB::$connection->exec("INSERT INTO sharing_fixture_users VALUES (42, 0, 0, NULL)");
        DB::$connection->exec("INSERT INTO sharing_fixture_items VALUES (123, 7, 'Visible item', 'encrypted', 'iv', 13, 0, NULL)");
        DB::$connection->exec("INSERT INTO sharing_fixture_sharekeys_items VALUES (42, 123, 'wrapped-key', 81)");
    }

    /** Losing a folder grant blocks an existing item link even if its sharekey remains. */
    public function testCurrentAccessIsRequiredDespiteAnExistingSharekey(): void
    {
        self::assertSame(123, secureSendReadItem(123, 42)['id']);
        DB::$folders[42] = [];
        self::assertSame([], secureSendReadItem(123, 42));
        self::assertSame(1, DB::$connection->querySingle('SELECT COUNT(*) FROM sharing_fixture_sharekeys_items'));
        self::assertSame([], secureSendReadItem(123, 99));
        self::assertSame([], secureSendReadItem(0, 42));
        self::assertSame([], secureSendReadItem(123, 0));
    }

    /** Disabled, deleted and administrator accounts cannot originate item access. */
    public function testIneligibleOriginatorsAreRejected(): void
    {
        foreach (['disabled = 1', "deleted_at = '2026-09-26'", 'admin = 1'] as $change) {
            DB::$connection->exec('UPDATE sharing_fixture_users SET disabled = 0, deleted_at = NULL, admin = 0');
            DB::$connection->exec('UPDATE sharing_fixture_users SET ' . $change);
            self::assertSame([], secureSendReadItem(123, 42), $change);
        }
        DB::$connection->exec('DELETE FROM sharing_fixture_users');
        self::assertSame([], secureSendReadItem(123, 42));
    }

    /** Inactive, soft-deleted and physically deleted items cannot be redeemed. */
    public function testUnavailableItemsAreRejected(): void
    {
        foreach (['inactif = 1', 'deleted_at = 123456'] as $change) {
            DB::$connection->exec('UPDATE sharing_fixture_items SET inactif = 0, deleted_at = NULL');
            DB::$connection->exec('UPDATE sharing_fixture_items SET ' . $change);
            self::assertSame([], secureSendReadItem(123, 42), $change);
        }
        DB::$connection->exec('DELETE FROM sharing_fixture_items');
        self::assertSame([], secureSendReadItem(123, 42));
    }

    /** Filtering uses current grants and does not reinterpret an orphaned item link as a note. */
    public function testListHidesRevokedLabelsButRetainsStandaloneNotes(): void
    {
        $rows = [
            ['id' => 1, 'send_type' => 'item', 'item_id' => 123, 'item_label' => 'Stale label'],
            ['id' => 2, 'send_type' => 'note', 'item_id' => null],
            ['id' => 3, 'send_type' => 'item', 'item_id' => null],
        ];
        $visible = secureSendFilterLinks($rows, 42);
        self::assertSame([1, 2], array_column($visible, 'id'));
        self::assertSame('Visible item', $visible[0]['item_label']);
        DB::$folders[42] = [];
        self::assertSame([$rows[1]], secureSendFilterLinks($rows, 42));
    }

    /** Creation keeps the existing migration path for usable keys and passwords. */
    public function testPasswordUsesTheMigrationAwareKeyHelper(): void
    {
        self::assertSame('shared-secret', secureSendItemPassword(secureSendReadItem(123, 42), 42, 'private', 'public'));
        self::assertSame([['wrapped-key', 'private', 'public', 81, 'sharekeys_items']], DB::$keyCalls);
    }

    /** Missing and undecryptable keys or passwords fail instead of creating empty-password links. */
    public function testUnreadablePasswordsFailClosed(): void
    {
        $item = secureSendReadItem(123, 42);
        foreach (['missing-key', 'empty-key', 'empty-password', 'exception'] as $case) {
            DB::$connection->exec('DELETE FROM sharing_fixture_sharekeys_items');
            if ($case !== 'missing-key') {
                DB::$connection->exec("INSERT INTO sharing_fixture_sharekeys_items VALUES (42, 123, 'wrapped-key', 81)");
            }
            DB::$objectKey = $case === 'empty-key' ? '' : 'item-key';
            DB::$password = $case === 'empty-password' ? '' : 'shared-secret';
            DB::$cryptoFailure = $case === 'exception';
            try {
                secureSendItemPassword($item, 42, 'private', 'public');
                self::fail('Expected decryption failure: ' . $case);
            } catch (InvalidArgumentException $e) {
                self::assertSame('cannot_decrypt', $e->getMessage(), $case);
            }
        }
    }

    /** A deliberately empty password must remain shareable when its key is usable. */
    public function testLegitimatelyEmptyPasswordRemainsSupported(): void
    {
        $item = secureSendReadItem(123, 42);
        $item['pw'] = '';
        $item['pw_len'] = 0;
        self::assertSame('', secureSendItemPassword($item, 42, 'private', 'public'));
    }

    /** Keep the authorization gates before decryption, insertion and recipient output. */
    public function testExistingHandlersUseTheAccessBoundary(): void
    {
        $sender = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($sender, "case 'generate_OTV_url':");
        $end = strpos($sender, "case 'update_OTV_url':", $start);
        $create = substr($sender, $start, $end - $start);
        self::assertLessThan(strpos($create, 'secureSendItemPassword('), strpos($create, 'secureSendReadItem('));
        self::assertLessThan(strpos($create, 'DB::insert('), strpos($create, 'secureSendItemPassword('));
        self::assertStringContainsString("catch (InvalidArgumentException \$e)", $create);
        self::assertStringContainsString("array('error' => 'cannot_decrypt')", $create);
        self::assertStringContainsString('secureSendFilterLinks($secureSendRows,', $sender);
        $recipient = (string) file_get_contents(__DIR__ . '/../../app/core/otv.php');
        self::assertStringContainsString('secureSendPrepareRecipient(', $recipient);
        $lifecycle = (string) file_get_contents(__DIR__ . '/../../app/sources/secure_send.functions.php');
        $redemption = substr($lifecycle, strpos($lifecycle, 'function secureSendRedeem('));
        self::assertLessThan(strpos($redemption, 'secureSendDecryptPayload('), strpos($redemption, 'secureSendReadItem('));
        self::assertStringContainsString("(int) \$link['originator']", $redemption);
    }
}
