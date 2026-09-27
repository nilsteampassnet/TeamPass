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
 * @file      SecureSendInputTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_input.php';

class SecureSendInputTest extends TestCase
{
    /** Invalid types and malformed note text cannot reach encryption or storage. */
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

    /** Valid UTF-8 notes and a passphrase at the recipient's byte limit remain accepted. */
    public function testValidNotesAndRecipientLengthBoundaryRemainAccepted(): void
    {
        $input = ['send_type' => 'note', 'passphrase' => str_repeat('é', 512),
            'payload' => ['title' => 'Title', 'secret' => ' secret ', 'note' => "中文\nL'envoi", 'url' => '', 'login' => 'alice']];
        secureSendValidateInput($input);
        self::assertSame(1024, strlen($input['passphrase']));
        self::assertSame(' secret ', $input['payload']['secret']);
    }

    /** Clamp requested duration and views to policy and the database integer range. */
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
