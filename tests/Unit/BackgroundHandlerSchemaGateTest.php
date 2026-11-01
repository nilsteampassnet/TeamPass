<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the background tasks handler stays idle while the schema lags the deployed files.
 *
 * Between the deployment of new files and their upgrade, and after a command-line revert that
 * leaves the new files in place, every task would otherwise run new code against old tables.
 */
final class BackgroundHandlerSchemaGateTest extends TestCase
{
    public function testHandlerChecksTheRecordedSchemaBeforeRunningAnyTask(): void
    {
        $handler = (string) file_get_contents(dirname(__DIR__, 2) . '/app/scripts/background_tasks___handler.php');
        $main = substr($handler, (int) strpos($handler, '// Main execution'));

        $gate = strpos($main, "(string) (\$settings['teampass_version'] ?? '') !== TP_VERSION");
        $this->assertIsInt($gate, 'the handler must compare the recorded version with TP_VERSION');
        $this->assertStringContainsString("(int) (\$settings['upgrade_timestamp'] ?? 0) < (int) UPGRADE_MIN_DATE", $main);
        $this->assertLessThan((int) strpos($main, 'new BackgroundTasksHandler('), $gate);
    }
}
