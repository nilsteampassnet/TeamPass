<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\BugReportPrivacy;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/secret_settings_logic.php';

/** Simulate a future registered secret without changing the production registry. */
function tpSecretSettingNames(): array
{
    return array_merge(\tpSecretSettingNames(), ['future_test_secret']);
}

final class BugReportEmailPrivacyTest extends TestCase
{
    private function exclusionSource(): string
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/main.queries.php');
        $function = strpos($source, 'function generateBugReport(');
        self::assertIsInt($function);
        $start = strpos($source, '$config_exclude_vars =', $function);
        $end = strpos($source, "// Load user's language", $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        return substr($source, $start, $end - $start);
    }

    /** Provider identities and all current/future registered secrets belong to the actual mask list. */
    public function testReportMasksEveryProviderSettingAndRegisteredSecret(): void
    {
        eval('namespace ' . __NAMESPACE__ . ';' . $this->exclusionSource());
        foreach (array_merge(tpSecretSettingNames(), ['email_microsoft_tenant_id', 'email_microsoft_client_id',
            'email_microsoft_client_secret', 'email_microsoft_mailbox', 'email_google_client_id',
            'email_google_client_secret', 'email_google_sender']) as $name) {
            self::assertContains($name, $config_exclude_vars);
        }
    }

    /** Execute the report's actual settings formatter and exclude values, including ciphertext. */
    public function testFormattedSettingsNeverContainProviderOrSecretValues(): void
    {
        eval('namespace ' . __NAMESPACE__ . ';' . $this->exclusionSource());
        $SETTINGS = ['email_transport' => 'microsoft_graph'];
        foreach ($config_exclude_vars as $name) {
            $SETTINGS[$name] = 'def50200-private-value-' . $name;
        }
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/sources/main.queries.php');
        $start = strpos($source, '// Read config file', strpos($source, 'function generateBugReport('));
        $end = strpos($source, '// Get error', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        eval(substr($source, $start, $end - $start));
        self::assertStringNotContainsString('def50200-private-value', $list_of_options);
        self::assertStringContainsString("'email_transport' => 'microsoft_graph'", $list_of_options);
        foreach ($config_exclude_vars as $name) {
            self::assertStringContainsString("'$name' => '<removed>'", $list_of_options);
        }
    }
}
