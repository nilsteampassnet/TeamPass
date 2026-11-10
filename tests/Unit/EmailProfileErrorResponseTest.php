<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\EmailProfileResponse;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 2) . '/app/sources/emails_transport_logic.php';

/** Keep the response boundary isolated from the session encryption adapter. */
function prepareExchangedData(array $data, string $operation): string
{
    return json_encode($data, JSON_THROW_ON_ERROR);
}

final class EmailProfileErrorResponseTest extends TestCase
{
    /** Execute the production handler's failure response, including the field allowlist. */
    public function testResponseContainsOnlyKnownFieldNamesAndGenericMessages(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/emails_transport.queries.php');
        $start = strpos($source, 'catch (Throwable $e)');
        self::assertIsInt($start);
        $handler = substr($source, $start);
        $transaction = false;
        $callback = false;
        $session = new class {
            /** Supply an already authenticated session key for the response adapter. */
            public function get(string $name): string { return 'session'; }
        };
        $lang = new class {
            /** Use translation keys to assert that arbitrary exception messages are ignored. */
            public function get(string $name): string { return $name; }
        };
        foreach ([
            [new InvalidArgumentException('email_smtp_server'), 'email_smtp_server'],
            [new InvalidArgumentException('email_microsoft_client_secret'), 'email_microsoft_client_secret'],
            [new InvalidArgumentException('private-submitted-value'), null],
            [new RuntimeException('private-submitted-value'), null],
            [new RuntimeException('email_from'), null],
        ] as [$error, $expectedField]) {
            ob_start();
            try {
                eval('namespace ' . __NAMESPACE__ . '; use Throwable; try { throw $error; } ' . $handler);
                $output = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertSame(['error' => true, 'message' => 'email_profile_save_failed', 'field' => $expectedField],
                json_decode($output, true, 512, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('private-submitted-value', $output);
        }
    }
}
