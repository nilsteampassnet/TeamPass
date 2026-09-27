<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Source-order sentinel for the Secure Send access boundary.
 *
 * Kept apart from SecureSendAccessTest so it runs without SQLite3: it only reads sources.
 */
class SecureSendAccessSentinelTest extends TestCase
{
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
        self::assertLessThan(strpos($recipient, '$payload_decrypted = cryption('), strpos($recipient, 'secureSendReadItem('));
        self::assertStringContainsString("(int) \$data['originator']", $recipient);
    }
}
