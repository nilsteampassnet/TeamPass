<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\EmailQueue;

use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Observe exactly what the production enqueue function passes to SQL. */
final class DB
{
    public static array $inserts = [];
    /** Capture a write without configuring an installation. */
    public static function insert(string $table, array $row): void { self::$inserts[] = [$table, $row]; }
}

/** Keep the wakeup boundary isolated from cron and PHP-FPM. */
function triggerBackgroundHandler(): void { ++$GLOBALS['email_queue_wakeups']; }
/** Use a non-default prefix at the real enqueue boundary. */
function prefixTable(string $table): string { return 'email_queue_test_' . $table; }

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EmailQueuePayloadTest extends TestCase
{
    private string $keyFile = '';

    protected function setUp(): void
    {
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        $this->keyFile = tempnam(sys_get_temp_dir(), 'teampass-email-queue-key-');
        file_put_contents($this->keyFile, Key::createNewRandomKey()->saveToAsciiSafeString());
        define('TEAMPASS_FUNCTIONS_ONLY', true);
        define('TEAMPASS_SECRETS', dirname($this->keyFile));
        define('SECUREFILE', basename($this->keyFile));
        require_once dirname(__DIR__, 2) . '/app/sources/main.functions.php';
        $source = str_replace("\r\n", "\n", file_get_contents(dirname(__DIR__, 2) . '/app/sources/main.functions.php'));
        $start = strpos($source, 'function prepareSendingEmail(');
        $end = strpos($source, "\n}\n", $start) + 2;
        eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end - $start));
        $GLOBALS['email_queue_wakeups'] = 0;
        DB::$inserts = [];
    }

    protected function tearDown(): void
    {
        if ($this->keyFile !== '') { unlink($this->keyFile); }
    }

    /** New email data reaches the INSERT only after authenticated encryption. */
    public function testEnqueueEncryptsBeforeSqlAndWakesTheHandler(): void
    {
        prepareSendingEmail('R&amp;D O&#039;Brien', '<p>private-mail-body</p>', 'private-recipient@example.test');
        self::assertSame(1, $GLOBALS['email_queue_wakeups']);
        self::assertCount(1, DB::$inserts);
        self::assertSame('email_queue_test_background_tasks', DB::$inserts[0][0]);
        $row = DB::$inserts[0][1];
        self::assertSame('send_email', $row['process_type']);
        foreach (['private-mail-body', 'private-recipient', "O'Brien"] as $sensitive) {
            self::assertStringNotContainsString($sensitive, $row['arguments']);
        }
        $envelope = json_decode($row['arguments'], true);
        self::assertSame(1, $envelope['email_payload_version']);
        self::assertTrue(tpIsInstanceKeyCiphertext($envelope['ciphertext']));
        $opened = emailQueueOpen($envelope);
        self::assertSame("R&D O'Brien", $opened['subject']);
        self::assertSame('<p>private-mail-body</p>', $opened['body']);
    }

    /** Preserve legacy tasks and long UTF-8 messages without changing template content. */
    public function testLegacyAndEncryptedMessagesHaveTheSameContent(): void
    {
        $message = ['subject' => 'Émail', 'body' => str_repeat('<p>R&amp;D éè</p>', 10000), 'receivers' => 'recipient@example.test'];
        self::assertSame($message, emailQueueOpen($message));
        self::assertSame($message, emailQueueOpen(emailQueueSeal($message)));
    }

    /** Modified ciphertext cannot fall back to historical plaintext processing. */
    public function testTamperingFailsClosed(): void
    {
        $envelope = emailQueueSeal(['subject' => 'Test', 'body' => 'private-body', 'receivers' => 'recipient@example.test']);
        $position = strlen($envelope['ciphertext']) - 1;
        $envelope['ciphertext'][$position] = $envelope['ciphertext'][$position] === 'a' ? 'b' : 'a';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email payload could not be decrypted.');
        emailQueueOpen($envelope);
    }

    /** Unsupported versions are refused even if plaintext fields are also present. */
    public function testUnknownVersionCannotBypassEncryption(): void
    {
        $this->expectException(RuntimeException::class);
        emailQueueOpen(['email_payload_version' => 2, 'subject' => 'Test', 'body' => 'Body', 'receivers' => 'recipient@example.test']);
    }

    /** Token material is never part of the task message contract. */
    public function testTokensAreRefusedBeforePersistence(): void
    {
        try {
            emailQueueSeal(['subject' => 'Test', 'body' => 'Body', 'receivers' => 'recipient@example.test', 'access_token' => 'private-token']);
            self::fail('Token field was accepted');
        } catch (RuntimeException $e) {
            self::assertSame('Email payload format is invalid.', $e->getMessage());
            self::assertSame([], DB::$inserts);
        }
    }

    /** A retry timestamp never shortens Retry-After and never replays unsafe outcomes. */
    public function testRetryPolicyHonorsDelayAndAttemptBudget(): void
    {
        $resultClass = \TeampassClasses\EmailService\EmailSendResult::class;
        self::assertSame(1030, emailQueueRetryAt($resultClass::failure('temporary', true), 1, 1000));
        self::assertSame(1060, emailQueueRetryAt($resultClass::failure('network', true), 2, 1000));
        self::assertSame(1120, emailQueueRetryAt($resultClass::failure('throttled', true, 120), 1, 1000));
        self::assertSame(4600, emailQueueRetryAt($resultClass::failure('throttled', true, 3600), 1, 1000));
        foreach ([3601, 172800, PHP_INT_MAX] as $delay) {
            self::assertNull(emailQueueRetryAt($resultClass::failure('throttled', true, $delay), 1, 1000));
        }
        self::assertNull(emailQueueRetryAt($resultClass::failure('temporary', true), 1, PHP_INT_MAX - 10));
        self::assertNull(emailQueueRetryAt($resultClass::failure('temporary', true), 3, 1000));
        self::assertNull(emailQueueRetryAt($resultClass::accepted(), 1, 1000));
        self::assertNull(emailQueueRetryAt($resultClass::indeterminate('timeout'), 1, 1000));
        foreach (['authentication', 'configuration', 'permission', 'message', 'rejected', 'partial_acceptance'] as $code) {
            self::assertNull(emailQueueRetryAt($resultClass::failure($code, true), 1, 1000));
        }
    }
}
