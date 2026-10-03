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
        if (!extension_loaded('sqlite3')) {
            self::markTestSkipped('The SQL-backed Secure Send tests require SQLite3 (enabled in CI).');
        }
        require_once __DIR__ . '/../../app/sources/otp.functions.php';
        require_once __DIR__ . '/../Fixtures/secure_send_access_db.php';
        require_once __DIR__ . '/../../app/sources/secure_send_access.php';
        DB::$connection = new SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->exec('CREATE TABLE sharing_fixture_users (id INTEGER, admin INTEGER, disabled INTEGER, deleted_at TEXT, name TEXT, lastname TEXT)');
        DB::$connection->exec('CREATE TABLE sharing_fixture_items (id INTEGER, id_tree INTEGER, label TEXT, pw TEXT, pw_iv TEXT, pw_len INTEGER, inactif INTEGER, deleted_at INTEGER)');
        DB::$connection->exec('CREATE TABLE sharing_fixture_items_otp (item_id INTEGER, enabled INTEGER, secret TEXT, algorithm TEXT, digits INTEGER, period INTEGER)');
        DB::$connection->exec('CREATE TABLE sharing_fixture_sharekeys_items (user_id INTEGER, object_id INTEGER, share_key TEXT, increment_id INTEGER)');
        DB::$connection->exec("INSERT INTO sharing_fixture_users VALUES (42, 0, 0, NULL, 'Alice', 'Sender')");
        DB::$connection->exec("INSERT INTO sharing_fixture_items VALUES (123, 7, 'Visible item', 'encrypted', 'iv', 13, 0, NULL)");
        DB::$connection->exec("INSERT INTO sharing_fixture_sharekeys_items VALUES (42, 123, 'wrapped-key', 81)");
        DB::$totpSecret = 'JBSWY3DPEHPK3PXP';
        DB::$totpFailure = false;
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

    /** The recipient sees only the display name, and only while the sender remains eligible. */
    public function testPublicSenderNeverExposesAuthenticationIdentifiers(): void
    {
        $link = ['send_type' => 'item_v2', 'item_id' => 123, 'originator' => 42];
        self::assertSame(['display_name' => 'Alice Sender'], secureSendPublicSender($link));
        self::assertArrayNotHasKey('login', secureSendPublicSender($link));
        self::assertArrayNotHasKey('email', secureSendPublicSender($link));

        DB::$folders[42] = [];
        self::assertNull(secureSendPublicSender($link));
        self::assertSame(
            ['display_name' => 'Alice Sender'],
            secureSendPublicSender(['send_type' => 'note', 'item_id' => null, 'originator' => 42])
        );

        DB::$connection->exec('UPDATE sharing_fixture_users SET disabled = 1');
        self::assertNull(secureSendPublicSender(['send_type' => 'note', 'item_id' => null, 'originator' => 42]));
    }

    /** Accounts without profile names remain valid without falling back to their login. */
    public function testPublicSenderAllowsAnEmptyDisplayName(): void
    {
        DB::$connection->exec("UPDATE sharing_fixture_users SET name = '', lastname = ''");

        self::assertSame(
            ['display_name' => ''],
            secureSendPublicSender(['send_type' => 'note', 'item_id' => null, 'originator' => 42])
        );
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

    /** Only enabled, decryptable TOTP profiles become part of the encrypted snapshot. */
    public function testEnabledTotpProfileIsNormalizedAndFailuresCloseTheLink(): void
    {
        self::assertNull(secureSendItemTotp(123));
        DB::$connection->exec("INSERT INTO sharing_fixture_items_otp VALUES (123, 0, 'encrypted-totp', 'sha1', 6, 30)");
        self::assertNull(secureSendItemTotp(123));

        DB::$connection->exec('UPDATE sharing_fixture_items_otp SET enabled = 1');
        self::assertSame([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'algorithm' => 'sha1',
            'digits' => 6,
            'period' => 30,
        ], secureSendItemTotp(123));

        foreach (['decrypt', 'secret', 'profile'] as $failure) {
            DB::$totpFailure = $failure === 'decrypt';
            DB::$totpSecret = $failure === 'secret' ? 'NOT-BASE32!' : 'JBSWY3DPEHPK3PXP';
            DB::$connection->exec("UPDATE sharing_fixture_items_otp SET algorithm = '" . ($failure === 'profile' ? 'md5' : 'sha1') . "'");
            try {
                secureSendItemTotp(123);
                self::fail('Expected TOTP failure: ' . $failure);
            } catch (InvalidArgumentException $e) {
                self::assertSame('totp_unusable', $e->getMessage(), $failure);
            }
        }
    }
}
