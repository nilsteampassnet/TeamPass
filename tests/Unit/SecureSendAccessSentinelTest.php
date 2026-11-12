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
        $orchestrator = (string) file_get_contents(__DIR__ . '/../../app/sources/secure_send_create.php');
        $create = substr($orchestrator, (int) strpos($orchestrator, 'function secureSendCreateLink('));
        self::assertLessThan(strpos($create, 'secureSendItemPassword('), strpos($create, 'secureSendReadItem('));
        self::assertLessThan(strpos($create, 'secureSendStoreLink('), strpos($create, 'secureSendItemPassword('));
        self::assertStringContainsString("catch (InvalidArgumentException \$e)", $create);
        self::assertStringContainsString("'cannot_decrypt'", $create);
        self::assertStringContainsString("secureSendActiveLinks(\$SETTINGS, (int) \$session->get('user-id'),", $sender);
        $recipient = (string) file_get_contents(__DIR__ . '/../../app/core/otv.php');
        self::assertStringContainsString('secureSendPrepareRecipient(', $recipient);
        $lifecycle = (string) file_get_contents(__DIR__ . '/../../app/sources/secure_send.functions.php');
        $redemption = substr($lifecycle, strpos($lifecycle, 'function secureSendRedeem('));
        self::assertLessThan(strpos($redemption, 'secureSendDecryptPayload('), strpos($redemption, 'secureSendReadItem('));
        self::assertStringContainsString("(int) \$link['originator']", $redemption);
    }

    /** The web handler and the API reach link creation only through the shared orchestrator. */
    public function testWebHandlerDelegatesCreationToTheSessionFreeOrchestrator(): void
    {
        $sender = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $start = strpos($sender, "case 'generate_OTV_url':");
        $handler = substr($sender, $start, strpos($sender, "case 'count_secure_sends':", $start) - $start);
        self::assertStringContainsString('secureSendCreateLink(', $handler);
        self::assertStringContainsString('catch (SecureSendCreateRefusal $e)', $handler);
        foreach (['secureSendStoreLink(', 'secureSendReadItem(', 'secureSendItemPassword(', 'cryption('] as $call) {
            self::assertStringNotContainsString($call, $handler, $call);
        }
        // The session key check still precedes any decoding or creation.
        self::assertLessThan(strpos($handler, 'prepareExchangedData('), strpos($handler, "\$session->get('key')"));

        $orchestrator = (string) file_get_contents(__DIR__ . '/../../app/sources/secure_send_create.php');
        foreach (['SessionManager', '$_SESSION', '$session', '$_POST', '$_GET', '$SETTINGS', 'filter_input('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $orchestrator, $forbidden);
        }
    }

    /** Every literal refusal code is part of the published list the API maps to HTTP statuses. */
    public function testEveryRaisedRefusalCodeIsDeclared(): void
    {
        $orchestrator = (string) file_get_contents(__DIR__ . '/../../app/sources/secure_send_create.php');
        self::assertSame(1, preg_match('/public const CODES = \[(.*?)\];/s', $orchestrator, $declaration));
        preg_match_all("/'([a-z_]+)'/", $declaration[1], $declared);
        preg_match_all("/new SecureSendCreateRefusal\\('([a-z_]+)'\\)/", $orchestrator, $raised);
        self::assertNotEmpty($raised[1]);
        foreach ($raised[1] as $code) {
            self::assertContains($code, $declared[1], $code);
        }
        // Codes forwarded from InvalidArgumentException messages are declared as well.
        $body = substr($orchestrator, (int) strpos($orchestrator, 'function secureSendCreateLink('));
        foreach (['invalid_payload', 'totp_unusable', 'invalid_email', 'email_rate_limited', 'email_not_configured',
            'email_requires_https', 'email_not_allowed', 'not_allowed'] as $code) {
            self::assertStringContainsString("'" . $code . "'", $body, $code);
            self::assertContains($code, $declared[1], $code);
        }
    }
}
