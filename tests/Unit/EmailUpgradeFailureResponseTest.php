<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2) . '/app/sources/upgrade_cli_logic.php';

final class EmailUpgradeFailureResponseTest extends TestCase
{
    /** Every migration failure produces the wizard's JSON and is also rejected by the CLI parser. */
    public function testQueryFailuresStopBeforeRecordingTheUpgradeAndReturnWizardErrors(): void
    {
        foreach (['settings', 'tokens', 'columns', 'scheduling', 'index_inspect', 'index_create', 'exception', 'success'] as $mode) {
            $statePath = tempnam(sys_get_temp_dir(), 'teampass-email-upgrade-test-');
            self::assertNotFalse($statePath);
            try {
                $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Fixtures/email_upgrade_failure_probe.php', $mode, $statePath]);
                $process->setTimeout(15);
                $process->run();
                self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $output = $process->getOutput();
                $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('1', $response[0]['finish']);
                $success = $mode === 'success';
                self::assertSame($success, $response[0]['error'] === '', $mode);
                self::assertSame($success, tpUpgradeCliParseStepOutput($output, 0)['ok'], $mode);
                self::assertStringNotContainsString('private-database-detail', $output);
                $state = json_decode((string) file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame($success, $state['timestamp_written'], $mode);
                self::assertSame(0, $state['queries_after_failure'], $mode);
            } finally {
                unlink($statePath);
            }
        }
    }
}
