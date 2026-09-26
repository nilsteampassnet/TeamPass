<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_input.php';

class SecureSendInputTest extends TestCase
{
    public function testRejectMalformedFieldsBeforeTheyCanBecomeSharedContent(): void
    {
        foreach ([
            ['send_type' => 'unexpected'], ['id' => [123]], ['days' => []], ['views' => []],
            ['shared_globaly' => []], ['passphrase' => []], ['passphrase' => str_repeat('a', 1025)],
            ['send_type' => 'note', 'payload' => 'not-an-object'],
            ['send_type' => 'note', 'payload' => ['secret' => ['hidden']]],
            ['send_type' => 'note', 'payload' => ['note' => "\xff"]],
        ] as $input) {
            try {
                secureSendValidateInput($input);
                self::fail('Malformed input was accepted');
            } catch (InvalidArgumentException $e) {
                self::assertSame('invalid_payload', $e->getMessage());
            }
        }
    }

    public function testValidNotesAndRecipientLengthBoundaryRemainAccepted(): void
    {
        $input = ['send_type' => 'note', 'passphrase' => str_repeat('é', 512),
            'payload' => ['title' => 'Title', 'secret' => ' secret ', 'note' => "中文\nL'envoi", 'url' => '', 'login' => 'alice']];
        secureSendValidateInput($input);
        self::assertSame(1024, strlen($input['passphrase']));
        self::assertSame(' secret ', $input['payload']['secret']);
    }

    public function testRequestedLimitsCannotExceedPolicyOrBecomeNegative(): void
    {
        $settings = ['otv_expiration_period' => 7, 'secure_send_max_views' => 5];
        self::assertSame(['days' => 7, 'views' => 5, 'time_limit' => 605800],
            secureSendLimits($settings, ['days' => 3650, 'views' => PHP_INT_MAX], 1000));
        self::assertSame(['days' => 1, 'views' => 1, 'time_limit' => 87400],
            secureSendLimits($settings, ['days' => -1, 'views' => 0], 1000));
        self::assertSame(2147483647, secureSendLimits(['secure_send_max_views' => PHP_INT_MAX], ['views' => PHP_INT_MAX], 1000)['views']);
    }
}
