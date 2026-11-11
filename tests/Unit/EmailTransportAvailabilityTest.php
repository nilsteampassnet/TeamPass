<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/emails_transport_logic.php';

final class EmailTransportAvailabilityTest extends TestCase
{
    /** @return array<string, array{array, bool}> */
    public static function configurations(): array
    {
        return [
            'unconfigured' => [[], false],
            'legacy relay' => [['email_smtp_server' => 'relay.internal'], true],
            'blank relay' => [['email_smtp_server' => '  '], false],
            'relay without auth' => [['email_smtp_auth_mode' => 'none', 'email_smtp_server' => 'relay.internal'], true],
            'relay password' => [['email_smtp_auth_mode' => 'password', 'email_smtp_server' => 'relay.internal'], true],
            'legacy password mode' => [['email_smtp_auth' => '1', 'email_smtp_auth_mode' => '', 'email_smtp_server' => 'relay.internal'], true],
            'legacy password missing host' => [['email_smtp_auth' => 1], false],
            'unknown auth' => [['email_smtp_auth_mode' => 'unknown', 'email_smtp_server' => 'relay.internal'], false],
            'unknown transport' => [['email_transport' => 'unknown', 'email_smtp_server' => 'relay.internal'], false],
            'Graph without SMTP host' => [['email_transport' => 'microsoft_graph', 'email_microsoft_mailbox' => 'sender@example.test'], true],
            'Graph missing mailbox' => [['email_transport' => 'microsoft_graph', 'email_smtp_server' => 'old.relay'], false],
            'Graph invalid mailbox' => [['email_transport' => 'microsoft_graph', 'email_microsoft_mailbox' => 'not-an-email'], false],
            'Gmail without SMTP host' => [['email_transport' => 'gmail_api', 'email_google_sender' => 'sender@example.test'], true],
            'Gmail missing sender' => [['email_transport' => 'gmail_api', 'email_smtp_server' => 'old.relay'], false],
            'Gmail invalid sender' => [['email_transport' => 'gmail_api', 'email_google_sender' => 'not-an-email'], false],
            'Microsoft SMTP without host' => [['email_smtp_auth_mode' => 'microsoft_oauth', 'email_microsoft_mailbox' => 'sender@example.test'], true],
            'Microsoft SMTP missing mailbox' => [['email_smtp_auth_mode' => 'microsoft_oauth', 'email_smtp_server' => 'old.relay'], false],
            'Microsoft SMTP invalid mailbox' => [['email_smtp_auth_mode' => 'microsoft_oauth', 'email_microsoft_mailbox' => 'not-an-email'], false],
        ];
    }

    /** Availability depends on the selected transport, without inspecting credentials or tokens. @dataProvider configurations */
    public function testEachTransportUsesItsOwnDestination(array $settings, bool $expected): void
    {
        self::assertSame($expected, emailTransportIsConfigured($settings));
    }

    /** Execute recovery eligibility through main.functions.php, including its shared helper import. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLocalRecoveryRemainsAvailableWithEveryConfiguredTransport(): void
    {
        define('TEAMPASS_FUNCTIONS_ONLY', true);
        require dirname(__DIR__, 2) . '/app/sources/main.functions.php';
        $user = ['auth_type' => 'local', 'email' => 'recipient@example.test'];
        foreach (self::configurations() as $name => [$settings, $expected]) {
            self::assertSame($expected, getForgotLocalPasswordContext($user, $settings)['available'], $name);
            self::assertFalse(getForgotLocalPasswordContext($user, $settings + ['enable_local_password_recovery' => '0'])['available']);
        }
    }
}
