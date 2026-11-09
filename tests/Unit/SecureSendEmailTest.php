<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SecureSendEmailTest extends TestCase
{
    private array $settings;
    private array $link;
    private array $delivery;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Fixtures/secure_send_email_db.php';
        require_once __DIR__ . '/../../app/sources/secret_settings_logic.php';
        require_once __DIR__ . '/../../app/sources/secure_send_email.php';
        require_once __DIR__ . '/../../app/sources/secure_send_storage.php';
        DB::$connection = new SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$instanceKey = \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();
        DB::$connection->exec('CREATE TABLE email_test_users (id INTEGER PRIMARY KEY, admin INTEGER DEFAULT 0, disabled INTEGER DEFAULT 0, deleted_at INTEGER, name TEXT, lastname TEXT)');
        DB::$connection->exec('INSERT INTO email_test_users (id) VALUES (42), (43)');
        DB::$connection->exec('CREATE TABLE email_test_items (id INTEGER PRIMARY KEY, inactif INTEGER DEFAULT 0, deleted_at INTEGER)');
        DB::$connection->exec('INSERT INTO email_test_items (id) VALUES (123)');
        DB::$connection->exec('CREATE TABLE email_test_automatic_del (item_id INTEGER, del_enabled INTEGER, del_type INTEGER, del_value INTEGER)');
        DB::$connection->exec('CREATE TABLE email_test_otv (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, send_type TEXT, timestamp INTEGER, originator INTEGER, code TEXT, encrypted TEXT, protected_key TEXT, has_passphrase INTEGER, failed_attempts INTEGER, views INTEGER DEFAULT 0, time_limit INTEGER, max_views INTEGER, shared_globaly INTEGER)');
        DB::$connection->exec('CREATE TABLE email_test_secure_send_audit (record TEXT)');
        DB::$connection->exec('CREATE TABLE email_test_background_tasks (increment_id INTEGER PRIMARY KEY AUTOINCREMENT, created_at INTEGER, process_type TEXT, arguments TEXT, status TEXT DEFAULT "queued")');
        DB::$connection->exec('CREATE TABLE email_test_secure_send_email (send_id INTEGER PRIMARY KEY, task_id INTEGER UNIQUE, originator INTEGER, created_at INTEGER, address_hash TEXT)');
        $this->settings = ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1, 'cpassman_url' => 'https://vault.example.test',
            'email_smtp_server' => 'smtp.example.test', 'email_from' => 'team@example.test', 'timezone' => 'Europe/Paris'];
        $this->link = ['item_id' => null, 'send_type' => 'note', 'timestamp' => time(), 'originator' => 42,
            'code' => 'lookup-canary', 'encrypted' => 'encrypted-content-canary', 'protected_key' => 'wrapped-canary',
            'has_passphrase' => 1, 'failed_attempts' => 0, 'time_limit' => time() + 86400, 'max_views' => 1, 'shared_globaly' => 0];
        $this->delivery = ['recipient' => 'recipient@example.test', 'url' => 'https://vault.example.test/index.php?otv=1&code=lookup-canary&key=link-secret-canary&stamp=' . time()];
    }

    public function testLinkAuditAndEncryptedJobCommitTogetherBeforeWakeup(): void
    {
        $id = secureSendStoreLink($this->link, $this->settings, $this->delivery);
        self::assertSame(1, $id);
        self::assertSame(1, DB::$wakeups);
        self::assertSame(1, DB::$connection->querySingle('SELECT COUNT(*) FROM email_test_secure_send_audit'));
        $task = DB::queryFirstRow('SELECT * FROM email_test_background_tasks');
        self::assertSame('secure_send_email', $task['process_type']);
        foreach (['recipient@', 'link-secret-canary', 'lookup-canary'] as $secret) {
            self::assertStringNotContainsString($secret, $task['arguments']);
        }
        $message = emailQueueOpen(json_decode($task['arguments'], true));
        self::assertSame('recipient@example.test', $message['receivers']);
        self::assertStringContainsString('&amp;key=link-secret-canary', $message['body']);
        self::assertStringNotContainsString('encrypted-content-canary', $message['body']);
        self::assertStringNotContainsString('wrapped-canary', $message['body']);
        self::assertSame('', $message['encryptedUserPassword']);
        self::assertTrue(secureSendEmailCanDeliver(1, $this->settings));
    }

    public function testPersistenceFailureRollsBackLinkAuditAndJob(): void
    {
        foreach (['secure_send_audit', 'background_tasks', 'secure_send_email'] as $table) {
            DB::$failTable = prefixTable($table);
            try { secureSendStoreLink($this->link, $this->settings, $this->delivery); self::fail('Expected failure'); }
            catch (RuntimeException $e) { self::assertSame('private-failure-canary', $e->getMessage()); }
            foreach (['otv', 'secure_send_audit', 'background_tasks', 'secure_send_email'] as $empty) {
                self::assertSame(0, DB::$connection->querySingle('SELECT COUNT(*) FROM ' . prefixTable($empty)));
            }
            self::assertSame(0, DB::$wakeups);
        }
    }

    public function testQuotaSurvivesRevocationAndFailedDeliveriesAndIsPerSender(): void
    {
        for ($i = 0; $i < 10; ++$i) { secureSendStoreLink($this->link, $this->settings, $this->delivery); }
        DB::$connection->exec('DELETE FROM email_test_otv');
        DB::$connection->exec('UPDATE email_test_background_tasks SET status = "failed"');
        secureSendEmailPruneMetadata(time());
        try { secureSendStoreLink($this->link, $this->settings, $this->delivery); self::fail('Expected quota refusal'); }
        catch (InvalidArgumentException $e) { self::assertSame('email_rate_limited', $e->getMessage()); }
        secureSendStoreLink(array_replace($this->link, ['originator' => 43]), $this->settings, $this->delivery);
        DB::$connection->exec('UPDATE email_test_secure_send_email SET created_at = created_at - 3601');
        secureSendEmailPruneMetadata(time());
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        self::assertSame(12, DB::$wakeups);
    }

    public function testRevokedExpiredExhaustedOrSuspendedLinksAreNotDelivered(): void
    {
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        foreach (['otv_is_enabled' => 0, 'secure_send_allow_notes' => 0, 'cpassman_url' => 'https://changed.example.test'] as $key => $value) {
            self::assertFalse(secureSendEmailCanDeliver(1, array_replace($this->settings, [$key => $value])));
        }
        self::assertFalse(secureSendEmailCanDeliver(1, $this->settings, time() + 86400));
        foreach (['views = 1', 'failed_attempts = 5', 'has_passphrase = 0, protected_key = NULL'] as $change) {
            DB::$connection->exec('UPDATE email_test_otv SET views=0, failed_attempts=0, has_passphrase=1, protected_key="wrapped"');
            DB::$connection->exec('UPDATE email_test_otv SET ' . $change);
            self::assertFalse(secureSendEmailCanDeliver(1, $this->settings + ['secure_send_require_passphrase' => 1]));
        }
        DB::$connection->exec('DELETE FROM email_test_otv');
        self::assertFalse(secureSendEmailCanDeliver(1, $this->settings));
    }

    public function testDeliveryRechecksAccountItemAccessAndAutomaticDeletionWithoutRevealing(): void
    {
        secureSendStoreLink(array_replace($this->link, ['send_type' => 'item_v2', 'item_id' => 123]), $this->settings, $this->delivery);
        self::assertTrue(secureSendEmailCanDeliver(1, $this->settings));
        DB::$access = false;
        self::assertFalse(secureSendEmailCanDeliver(1, $this->settings));
        DB::$access = true;
        foreach (['admin=1', 'disabled=1', 'deleted_at=1'] as $change) {
            DB::$connection->exec('UPDATE email_test_users SET admin=0, disabled=0, deleted_at=NULL');
            DB::$connection->exec('UPDATE email_test_users SET ' . $change . ' WHERE id=42');
            self::assertFalse(secureSendEmailCanDeliver(1, $this->settings));
        }
        DB::$connection->exec('UPDATE email_test_users SET admin=0, disabled=0, deleted_at=NULL');
        DB::$connection->exec('INSERT INTO email_test_automatic_del VALUES (123, 1, 1, 0)');
        self::assertFalse(secureSendEmailCanDeliver(1, $this->settings + ['enable_delete_after_consultation' => 1]));
        self::assertSame(0, DB::$connection->querySingle('SELECT views FROM email_test_otv'));
        self::assertSame(1, DB::$connection->querySingle('SELECT COUNT(*) FROM email_test_secure_send_audit'));
    }

    public function testExactlyOneValidMailboxAndHttpsAreRequired(): void
    {
        foreach ([null, [], '', 'a@example.test,b@example.test', 'Name <a@example.test>', "a@example.test\r\nBcc: b@example.test"] as $bad) {
            try { secureSendEmailRecipient($bad); self::fail('Invalid recipient accepted'); }
            catch (InvalidArgumentException $e) { self::assertSame('invalid_email', $e->getMessage()); }
        }
        self::assertSame('a@example.test', secureSendEmailRecipient(' a@example.test '));
        $this->settings['cpassman_url'] = 'http://vault.example.test';
        $this->delivery['url'] = str_replace('https:', 'http:', $this->delivery['url']);
        $this->expectExceptionMessage('email_requires_https');
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
    }

    public function testDeliveryStatusIsOwnerScopedAndDoesNotExposeRecipientsOrSecrets(): void
    {
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        self::assertSame([], secureSendEmailStatuses(43));
        self::assertSame([1 => 'queued'], secureSendEmailStatuses(42));
        DB::$connection->exec('UPDATE email_test_background_tasks SET status="completed"');
        self::assertSame([1 => 'accepted'], secureSendEmailStatuses(42));
        DB::$connection->exec('UPDATE email_test_background_tasks SET status="failed"');
        self::assertSame([1 => 'failed'], secureSendEmailStatuses(42));
        DB::$connection->exec('DELETE FROM email_test_background_tasks');
        self::assertSame([1 => 'unknown'], secureSendEmailStatuses(42));
    }

    /** Run the real creation handler after its existing page/session authorization gate. */
    private function dispatch(array $payload, string $key = 'session-key'): array
    {
        require_once __DIR__ . '/../../app/sources/secure_send_input.php';
        $source = file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($source, "case 'generate_OTV_url':");
        $end = strpos($source, "case 'count_secure_sends':", $start);
        $handler = substr($source, $start, $end - $start);
        $session = new class {
            public function get(string $name): string|int { return $name === 'key' ? 'session-key' : 42; }
        };
        $SETTINGS = $this->settings;
        $inputData = ['key' => $key, 'data' => json_encode($payload)];
        ob_start();
        try {
            eval('switch (true) { case true: ' . $handler . ' }');
            return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
        } finally { ob_end_clean(); }
    }

    public function testAuthenticatedHandlerBuildsInvitationWithoutSendingTheNoteOrPassphrase(): void
    {
        $payload = ['send_type' => 'note', 'payload' => ['secret' => 'note-content-canary'],
            'passphrase' => 'passphrase-canary', 'recipient_email' => 'recipient@example.test'];
        $result = $this->dispatch($payload);
        self::assertSame('', $result['error']);
        self::assertTrue($result['email_queued']);
        $message = emailQueueOpen(json_decode(DB::$connection->querySingle('SELECT arguments FROM email_test_background_tasks'), true));
        self::assertStringContainsString(htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8'), $message['body']);
        foreach (['note-content-canary', 'passphrase-canary'] as $secret) {
            self::assertStringNotContainsString($secret, json_encode($message));
        }
    }

    public function testHandlerRejectsInvalidRecipientAndKeyWithoutCreatingAnything(): void
    {
        $payload = ['send_type' => 'note', 'payload' => ['note' => 'test'], 'recipient_email' => ['recipient@example.test']];
        self::assertSame(['error' => 'invalid_email'], $this->dispatch($payload));
        self::assertSame([['error' => 'key_not_conform']], $this->dispatch($payload, 'wrong-key'));
        self::assertSame(0, DB::$connection->querySingle('SELECT COUNT(*) FROM email_test_otv'));
        self::assertSame(0, DB::$connection->querySingle('SELECT COUNT(*) FROM email_test_background_tasks'));
    }

    public function testClipboardSharingDoesNotRequireEmailConfigurationOrUseTheEmailQuota(): void
    {
        unset($this->settings['email_smtp_server']);
        $result = $this->dispatch(['send_type' => 'note', 'payload' => ['note' => 'test']]);
        self::assertSame('', $result['error']);
        self::assertFalse($result['email_queued']);
        self::assertSame(0, DB::$wakeups);
        self::assertSame(0, DB::$connection->querySingle('SELECT COUNT(*) FROM email_test_secure_send_email'));
    }

    public function testSchemaIsMetadataOnlyAndBothInstallPathsCreateIt(): void
    {
        $ddl = secureSendEmailSchemaSql('custom_secure_send_email');
        self::assertStringContainsString('ENGINE=InnoDB', $ddl);
        self::assertStringContainsString('(`originator`, `created_at`)', $ddl);
        foreach (['recipient', 'ciphertext', '`url`', '`body`', 'FOREIGN KEY'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $ddl);
        }
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString("action: 'secure_send_email'", file_get_contents($root . '/public/install/install-steps/install.js'));
        foreach (['public/install/upgrade_run_3.2.4.php', 'public/install/install-steps/run.step5.php'] as $file) {
            self::assertStringContainsString('secureSendEmailSchemaSql(', file_get_contents($root . '/' . $file));
        }
        self::assertGreaterThan(1794218153, (int) UPGRADE_MIN_DATE);
        self::assertLessThanOrEqual(time(), (int) UPGRADE_MIN_DATE, 'A future floor would require repeated upgrades on fresh installations.');
    }
}
