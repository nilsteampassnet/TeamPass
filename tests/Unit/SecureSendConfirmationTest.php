<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_logic.php';

class SecureSendConfirmationTest extends TestCase
{
    /** Reject exhausted links and administrator-disabled sharing before prompting. */
    public function testUnavailableLinksAreRejected(): void
    {
        $settings = ['otv_is_enabled' => 1];
        $link = ['time_limit' => 1001, 'max_views' => 1, 'views' => 0, 'failed_attempts' => 0];
        self::assertTrue(secureSendIsAvailable($link, $settings, 1000));
        foreach ([['time_limit' => 1000], ['views' => 1], ['failed_attempts' => 5], ['max_views' => 0], ['send_type' => 'unexpected']] as $change) {
            self::assertFalse(secureSendIsAvailable(array_replace($link, $change), $settings, 1000));
        }
        self::assertFalse(secureSendIsAvailable($link, ['otv_is_enabled' => 0], 1000));
        self::assertFalse(secureSendIsAvailable($link, $settings + ['secure_send_require_passphrase' => 1], 1000));
        self::assertFalse(secureSendIsAvailable($link + ['send_type' => 'note'], $settings, 1000));
        self::assertFalse(secureSendIsAvailable($link + ['has_passphrase' => 1], $settings, 1000));
        self::assertTrue(secureSendIsAvailable($link + ['has_passphrase' => 1, 'protected_key' => 'wrapped-key'], $settings, 1000));
    }

    /** PHP array parameters and malformed credentials must not become confirmation keys. */
    public function testCredentialsAreBoundedScalars(): void
    {
        $parameters = ['code' => 'code', 'key' => 'secret', 'stamp' => '123'];
        self::assertSame($parameters, secureSendRequestParameters($parameters));
        foreach ([['key' => ['secret']], ['stamp' => '123xyz'], ['stamp' => '-1'], ['key' => ''], ['code' => str_repeat('x', 101)]] as $change) {
            self::assertNull(secureSendRequestParameters(array_replace($parameters, $change)));
        }
        self::assertNotSame(secureSendConfirmationId($parameters), secureSendConfirmationId(array_replace($parameters, ['key' => 'other'])));
    }
}
