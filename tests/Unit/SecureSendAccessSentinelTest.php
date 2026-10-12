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
 * @file      SecureSendAccessSentinelTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

/**
 * Source-order sentinel for the Secure Send access boundary.
 *
 * Kept apart from SecureSendAccessTest so it runs without SQLite3: it only reads sources.
 */
class SecureSendAccessSentinelTest extends TestCase
{
    /** Do not reintroduce the unused route that bypassed creation policy limits. */
    public function testLegacyLinkUpdateHandlerIsNotExposed(): void
    {
        $sender = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        self::assertStringNotContainsString("case 'update_OTV_url':", $sender);
    }

    /** Keep the authorization gates before decryption, insertion and recipient output. */
    public function testExistingHandlersUseTheAccessBoundary(): void
    {
        $sender = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($sender, "case 'generate_OTV_url':");
        $end = strpos($sender, "case 'list_secure_sends':", $start);
        $create = substr($sender, $start, $end - $start);
        self::assertLessThan(strpos($create, 'secureSendItemPassword('), strpos($create, 'secureSendReadItem('));
        self::assertLessThan(strpos($create, 'secureSendStoreLink('), strpos($create, 'secureSendItemPassword('));
        self::assertStringContainsString("catch (InvalidArgumentException \$e)", $create);
        self::assertStringContainsString("'cannot_decrypt'", $create);
        self::assertStringContainsString('secureSendFilterLinks($secureSendRows,', $sender);
        $recipient = (string) file_get_contents(__DIR__ . '/../../app/core/otv.php');
        self::assertStringContainsString('secureSendPrepareRecipient(', $recipient);
        $lifecycle = (string) file_get_contents(__DIR__ . '/../../app/sources/secure_send.functions.php');
        $redemption = substr($lifecycle, strpos($lifecycle, 'function secureSendRedeem('));
        self::assertLessThan(strpos($redemption, 'secureSendDecryptPayload('), strpos($redemption, 'secureSendReadItem('));
        self::assertStringContainsString("(int) \$link['originator']", $redemption);
    }
}
