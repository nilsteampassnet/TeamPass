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
    private int $maxExecutionTime = 60;

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
        DB::$connection->exec('CREATE TABLE email_test_background_tasks (increment_id INTEGER PRIMARY KEY AUTOINCREMENT, created_at INTEGER, process_type TEXT, arguments TEXT, status TEXT DEFAULT "queued", error_message TEXT)');
        DB::$connection->exec('CREATE TABLE email_test_secure_send_email (send_id INTEGER PRIMARY KEY, task_id INTEGER UNIQUE, originator INTEGER, created_at INTEGER, address_hash TEXT)');
        $this->settings = ['otv_is_enabled' => 1, 'secure_send_allow_notes' => 1, 'secure_send_allow_email' => 1, 'cpassman_url' => 'https://vault.example.test',
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

    /** Missing or disabled consent refuses creation and a later submission. */
    public function testEmailInvitationsRequireOptInBeforeCreationAndAtSubmission(): void
    {
        foreach ([0, null] as $value) {
            $this->settings['secure_send_allow_email'] = $value;
            if ($value === null) { unset($this->settings['secure_send_allow_email']); }
            self::assertSame(['error' => 'email_not_allowed'], $this->dispatch([
                'send_type' => 'note', 'payload' => ['note' => 'test'], 'recipient_email' => 'recipient@example.test',
            ]));
            foreach (['otv', 'secure_send_audit', 'background_tasks', 'secure_send_email'] as $table) {
                self::assertSame(0, DB::$connection->querySingle('SELECT COUNT(*) FROM ' . prefixTable($table)));
            }
            try { secureSendEmailMessage($this->delivery, $this->link, $this->settings); self::fail('Expected opt-in refusal'); }
            catch (InvalidArgumentException $e) { self::assertSame('email_not_allowed', $e->getMessage()); }
        }
        $this->settings['secure_send_allow_email'] = 1;
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        self::assertTrue(secureSendEmailCanDeliver(1, $this->settings));
        $this->settings['secure_send_allow_email'] = 0;
        self::assertFalse(secureSendEmailCanDeliver(1, $this->settings));
    }

    /** Execute the actual modal section with consent missing, off and on. */
    public function testModalOmitsTheEmailBlockUntilTheAdministratorEnablesIt(): void
    {
        $page = file_get_contents(__DIR__ . '/../../app/pages/items.php');
        $start = strpos($page, "<?php if ((int) (\$SETTINGS['secure_send_allow_email'] ?? 0) === 1) { ?>");
        self::assertNotFalse($start);
        $end = strpos($page, '<details id="secure-send-snapshot-hint"', $start);
        $section = substr($page, $start, $end - $start);
        $lang = new class { public function get(string $key): string { return $key; } };
        $secureSendEmailConfigured = true;
        foreach ([[], ['secure_send_allow_email' => 0], ['secure_send_allow_email' => 1]] as $SETTINGS) {
            ob_start();
            try { eval('?>' . $section); $html = ob_get_contents(); }
            finally { ob_end_clean(); }
            self::assertSame((int) ($SETTINGS['secure_send_allow_email'] ?? 0) === 1,
                str_contains($html, 'id="form-secure-send-email-recipient"'));
        }
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

    public function testQueuedExpirationUsesRegionalDateTimeAndTimezoneSettings(): void
    {
        foreach ([
            ['2026-11-10T16:05:09Z', 'Europe/Paris', 'd/m/Y', 'H:i:s', '10/11/2026 17:05:09 CET'],
            ['2026-11-10T16:05:09Z', 'America/New_York', 'm/d/Y', 'g:i:s a', '11/10/2026 11:05:09 am EST'],
            ['2026-07-10T16:05:09Z', 'Europe/Paris', 'd-M-y', 'G:i:s', '10-Jul-26 18:05:09 CEST'],
            ['2026-11-10T16:05:09Z', 'UTC', 'Y-m-d', 'H:i', '2026-11-10 16:05 UTC'],
        ] as [$instant, $timezone, $dateFormat, $timeFormat, $expected]) {
            $settings = array_replace($this->settings, [
                'timezone' => $timezone, 'date_format' => $dateFormat, 'time_format' => $timeFormat,
            ]);
            $link = array_replace($this->link, ['time_limit' => (new DateTimeImmutable($instant))->getTimestamp()]);
            $id = secureSendStoreLink($link, $settings, $this->delivery);
            $arguments = DB::queryFirstField('SELECT t.arguments FROM email_test_background_tasks t
                INNER JOIN email_test_secure_send_email e ON e.task_id=t.increment_id WHERE e.send_id=%i', $id);
            $message = emailQueueOpen(json_decode($arguments, true));
            self::assertStringContainsString($expected, $message['body']);
            self::assertStringNotContainsString('#expires#', $message['body']);
        }
    }

    public function testExpirationFallsBackToInstallationFormatsWhenSettingsAreMissing(): void
    {
        $settings = $this->settings;
        unset($settings['timezone']);
        $link = array_replace($this->link, ['time_limit' => (new DateTimeImmutable('2026-11-10T16:05:09Z'))->getTimestamp()]);
        $message = secureSendEmailMessage($this->delivery, $link, $settings);
        self::assertStringContainsString('10/11/2026 16:05:09 UTC', $message['body']);
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
        foreach ([null, [], '', 'a@example.test,b@example.test', '"a,b"@example.test', 'Name <a@example.test>', "a@example.test\r\nBcc: b@example.test"] as $bad) {
            try { secureSendEmailRecipient($bad); self::fail('Invalid recipient accepted'); }
            catch (InvalidArgumentException $e) { self::assertSame('invalid_email', $e->getMessage()); }
        }
        self::assertSame('a@example.test', secureSendEmailRecipient(' a@example.test '));
        $this->settings['cpassman_url'] = 'http://vault.example.test';
        $this->delivery['url'] = str_replace('https:', 'http:', $this->delivery['url']);
        $this->expectExceptionMessage('email_requires_https');
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
    }

    public function testMailAvailabilityIncludesAllConfiguredTransportsAndFixedExchangeSmtp(): void
    {
        self::assertTrue(secureSendEmailIsConfigured($this->settings));
        $settings = $this->settings;
        unset($settings['email_smtp_server']);
        self::assertFalse(secureSendEmailIsConfigured($settings));
        self::assertTrue(secureSendEmailIsConfigured($settings + ['email_smtp_auth_mode' => 'microsoft_oauth', 'email_microsoft_mailbox' => 'team@example.test']));
        self::assertTrue(secureSendEmailIsConfigured($settings + ['email_transport' => 'microsoft_graph', 'email_microsoft_mailbox' => 'team@example.test']));
        self::assertTrue(secureSendEmailIsConfigured($settings + ['email_transport' => 'gmail_api', 'email_google_sender' => 'team@example.test']));
        self::assertFalse(secureSendEmailIsConfigured($settings + ['email_transport' => 'unknown']));
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
        DB::query('UPDATE email_test_background_tasks SET error_message=%s', 'Secure Send is no longer available; email was not submitted.');
        self::assertSame([1 => 'not_sent'], secureSendEmailStatuses(42));
        self::assertSame([], secureSendEmailStatuses(43));
        DB::query('UPDATE email_test_background_tasks SET error_message=%s', 'Email delivery interrupted; acceptance is unknown. Check the provider before resending.');
        self::assertSame([1 => 'failed'], secureSendEmailStatuses(42));
        DB::$connection->exec('DELETE FROM email_test_background_tasks');
        self::assertSame([1 => 'unknown'], secureSendEmailStatuses(42));
    }

    /** Execute the administration query against both email types and unrelated tasks. */
    public function testAdministratorQueueCountersIncludeBothEmailTypesOnly(): void
    {
        DB::$connection->exec('ALTER TABLE email_test_background_tasks ADD COLUMN is_in_progress INTEGER DEFAULT 0');
        DB::$connection->exec('ALTER TABLE email_test_background_tasks ADD COLUMN finished_at TEXT');
        foreach (['send_email', 'secure_send_email', 'other_task'] as $type) {
            foreach ([[0, null, 'queued'], [0, '', 'queued'], [0, '0', 'queued'], [1, null, 'in_progress'],
                [-1, '123', 'failed'], [-1, '123', 'completed'], [0, '123', 'completed']] as [$progress, $finished, $status]) {
                DB::insert(prefixTable('background_tasks'), ['process_type' => $type,
                    'is_in_progress' => $progress, 'finished_at' => $finished, 'status' => $status]);
            }
        }
        $source = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../../app/pages/emails.php'));
        $start = strpos($source, '$emailBackgroundQueue =');
        $end = strpos($source, "\n);", $start);
        eval(substr($source, $start, $end + 3 - $start));
        self::assertSame(['queued' => 6, 'running' => 2, 'failed' => 2], $emailBackgroundQueue);
    }

    /** Exercise direct handler failure and stale-task SQL without an installed instance. */
    public function testInterruptedInvitationsLoseTheirPayloadInBothHandlerFailurePaths(): void
    {
        if (!defined('LOG_TASKS')) { define('LOG_TASKS', false); }
        $source = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../../app/scripts/background_tasks___handler.php'));
        $start = strpos($source, 'class BackgroundTasksHandler');
        eval(substr($source, $start, strpos($source, '// Main execution', $start) - $start));
        $handler = (new ReflectionClass(BackgroundTasksHandler::class))->newInstanceWithoutConstructor();
        foreach (['is_in_progress INTEGER', 'finished_at INTEGER', 'updated_at INTEGER'] as $column) {
            DB::$connection->exec('ALTER TABLE email_test_background_tasks ADD COLUMN ' . $column);
        }
        foreach (['direct', 'stale'] as $path) {
            DB::$connection->exec('DELETE FROM email_test_background_tasks');
            foreach (['secure_send_email', 'send_email', 'other_task'] as $type) {
                DB::insert(prefixTable('background_tasks'), ['process_type' => $type, 'status' => 'in_progress',
                    'arguments' => 'sealed-url-secret-canary', 'is_in_progress' => 1, 'updated_at' => time() - 120]);
            }
            if ($path === 'direct') {
                $id = (int) DB::queryFirstField('SELECT increment_id FROM email_test_background_tasks WHERE process_type=%s', 'secure_send_email');
                (new ReflectionMethod(BackgroundTasksHandler::class, 'markTaskFailed'))->invoke($handler, $id, 'private-stderr-canary');
            } else {
                $from = strpos($source, '// Mark tasks as failed if');
                $to = strpos($source, '// Remove very old failed tasks', $from);
                eval(substr($source, $from, $to - $from));
            }
            $task = DB::queryFirstRow('SELECT * FROM email_test_background_tasks WHERE process_type=%s', 'secure_send_email');
            self::assertSame('failed', $task['status']);
            self::assertSame(['failed' => true, 'interrupted' => true], json_decode($task['arguments'], true));
            self::assertStringNotContainsString('private-stderr-canary', $task['error_message']);
            foreach (['send_email', 'other_task'] as $type) {
                self::assertSame('sealed-url-secret-canary', DB::queryFirstField('SELECT arguments FROM email_test_background_tasks WHERE process_type=%s', $type));
            }
        }
    }

    public function testInvitationSubjectIncludesOnlyThePermittedDecodedProfileName(): void
    {
        DB::$connection->exec("UPDATE email_test_users SET name='R&amp;D', lastname='O&#039;Brien &quot;Ops&quot; &lt;Guide&gt;' WHERE id=42");
        foreach (['english' => 'Someone shared a secure message with you via TeamPass', 'french' => 'Quelqu’un a partagé un message sécurisé avec vous via TeamPass'] as $locale => $generic) {
            $settings = $this->settings + ['default_language' => $locale];
            $id = secureSendStoreLink($this->link, $settings, $this->delivery);
            $taskId = (int) DB::queryFirstField('SELECT task_id FROM email_test_secure_send_email WHERE send_id=%i', $id);
            $message = emailQueueOpen(json_decode(DB::queryFirstField('SELECT arguments FROM email_test_background_tasks WHERE increment_id=%i', $taskId), true));
            self::assertStringNotContainsString('O\'Brien', json_encode($message));
            self::assertSame($generic, secureSendEmailPersonalize($message, $taskId, $settings)['subject']);
            $named = secureSendEmailPersonalize($message, $taskId, $settings + ['secure_send_show_sender_name' => 1]);
            self::assertSame('R&D O\'Brien "Ops" <Guide> ' . ($locale === 'french' ? 'a partagé un message sécurisé avec vous via TeamPass' : 'shared a secure message with you via TeamPass'), $named['subject']);
            self::assertStringNotContainsString('R&D', $named['body']);
            self::assertSame($generic, secureSendEmailPersonalize($message, $taskId, $settings + ['secure_send_show_sender_name' => 0])['subject']);
        }
    }

    public function testQueuedInvitationUsesTheCurrentNameAndPolicyOnEveryAttempt(): void
    {
        DB::$connection->exec("UPDATE email_test_users SET name='Queued', lastname='Name' WHERE id=42");
        secureSendStoreLink($this->link, $this->settings + ['secure_send_show_sender_name' => 1], $this->delivery);
        $message = emailQueueOpen(json_decode(DB::queryFirstField('SELECT arguments FROM email_test_background_tasks'), true));
        self::assertStringNotContainsString('Queued', json_encode($message));
        DB::$connection->exec("UPDATE email_test_users SET name='Current', lastname='Profile' WHERE id=42");
        self::assertSame('Current Profile shared a secure message with you via TeamPass',
            secureSendEmailPersonalize($message, 1, $this->settings + ['secure_send_show_sender_name' => 1])['subject']);
        self::assertSame('Someone shared a secure message with you via TeamPass',
            secureSendEmailPersonalize($message, 1, $this->settings + ['secure_send_show_sender_name' => 0])['subject']);
        DB::$connection->exec("UPDATE email_test_users SET name='Latest', lastname='Profile' WHERE id=42");
        self::assertSame('Latest Profile shared a secure message with you via TeamPass',
            secureSendEmailPersonalize($message, 1, $this->settings + ['secure_send_show_sender_name' => 1])['subject']);
        self::assertSame(0, DB::$connection->querySingle('SELECT views FROM email_test_otv'));
    }

    public function testEmptyProfilesStayGenericAndCustomSubjectsRemainEditable(): void
    {
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        $message = emailQueueOpen(json_decode(DB::queryFirstField('SELECT arguments FROM email_test_background_tasks'), true));
        $settings = $this->settings + ['secure_send_show_sender_name' => 1];
        self::assertSame('Someone shared a secure message with you via TeamPass', secureSendEmailPersonalize($message, 1, $settings)['subject']);
        foreach (['', '  ', '&#10;&#x2028;'] as $emptyName) {
            DB::query('UPDATE email_test_users SET name=%s, lastname=%s WHERE id=42', $emptyName, '');
            self::assertSame('Someone shared a secure message with you via TeamPass', secureSendEmailPersonalize($message, 1, $settings)['subject']);
        }
        DB::$connection->exec("UPDATE email_test_users SET name='Visible', lastname='Profile' WHERE id=42");
        $message['subject'] = 'Administrator custom subject';
        self::assertSame($message, secureSendEmailPersonalize($message, 1, $settings));
        $message['subject'] = '#sender_prefix#Custom invitation';
        self::assertSame('Visible Profile — Custom invitation', secureSendEmailPersonalize($message, 1, $settings)['subject']);
    }

    public function testProfilePrefixCannotInjectHeadersAndIsEscapedInCustomHtmlBodies(): void
    {
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        DB::query('UPDATE email_test_users SET name=%s, lastname=%s WHERE id=42', "R&amp;D\r\nBcc: injected@example.test\0", 'O&#039;Brien &quot;Ops&quot; &lt;Guide&gt;');
        $message = ['subject' => '#sender_prefix#Custom invitation', 'body' => '<p>#sender#</p><p>#sender_prefix#Invitation</p>'];
        $named = secureSendEmailPersonalize($message, 1, $this->settings + ['secure_send_show_sender_name' => 1]);
        self::assertSame('R&D Bcc: injected@example.test O\'Brien "Ops" <Guide> — Custom invitation', $named['subject']);
        self::assertSame(0, preg_match('/[\r\n\x00]/', $named['subject']));
        self::assertStringContainsString('R&amp;D Bcc: injected@example.test O&#039;Brien &quot;Ops&quot; &lt;Guide&gt; — ', $named['body']);
        $hidden = secureSendEmailPersonalize($message, 1, $this->settings);
        self::assertSame(['subject' => 'Custom invitation', 'body' => '<p>Someone</p><p>Invitation</p>'], $hidden);
    }

    public function testNamedSubjectSurvivesRealMimeHeaderPreparation(): void
    {
        secureSendStoreLink($this->link, $this->settings, $this->delivery);
        DB::query('UPDATE email_test_users SET name=%s, lastname=%s WHERE id=42', 'Élodie', 'O&#039;Brien');
        $message = emailQueueOpen(json_decode(DB::queryFirstField('SELECT arguments FROM email_test_background_tasks'), true));
        $named = secureSendEmailPersonalize($message, 1, $this->settings + ['secure_send_show_sender_name' => 1]);
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom('sender@example.test');
        $mailer->addAddress('recipient@example.test');
        $mailer->Subject = $named['subject'];
        $mailer->isHTML(true);
        $mailer->Body = $named['body'];
        self::assertTrue($mailer->preSend());
        $mime = $mailer->getSentMIMEMessage();
        $headers = iconv_mime_decode_headers($mime, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        self::assertSame($named['subject'], $headers['Subject']);
        self::assertStringNotContainsString('#sender_prefix#', $mime);
        self::assertDoesNotMatchRegularExpression('/\r\n(?:Bcc|Cc):/i', $mime);
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
        $this->settings['secure_send_allow_email'] = 0;
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
        // #5454 merged at this time; installations upgraded since then also need the delivery table.
        self::assertGreaterThan(1794241179, (int) UPGRADE_MIN_DATE);
        self::assertGreaterThan(1794244602, (int) UPGRADE_MIN_DATE);
        // The latest #5458 follow-up also predates this feature's required migration.
        self::assertGreaterThan(1794333583, (int) UPGRADE_MIN_DATE);
        // Existing installations may have been upgraded after #5458 reached develop.
        self::assertGreaterThan(1794380104, (int) UPGRADE_MIN_DATE);
        self::assertStringContainsString("array('admin', 'secure_send_allow_email', '0')", file_get_contents($root . '/public/install/install-steps/run.step5.php'));
        self::assertStringContainsString("'secure_send_allow_email' => '0'", file_get_contents($root . '/public/install/upgrade_run_3.2.4.php'));
        self::assertLessThanOrEqual(time(), (int) UPGRADE_MIN_DATE, 'A future floor would require repeated upgrades on fresh installations.');
    }
}
