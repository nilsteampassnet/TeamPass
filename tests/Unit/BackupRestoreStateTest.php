<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BackupRestoreStateTest extends TestCase
{
    public function testRestoredCredentialsAndTasksAreInvalidatedAndSchedulesUseTheRuntimeNamespace(): void
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/backup_restore_state.php']);
        $process->mustRun();
        $operations = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $deleted = [];
        $settings = [];
        foreach ($operations as $operation) {
            if ($operation[0] === 'DELETE FROM %b') {
                $deleted[] = $operation[1][0];
            } elseif ($operation[0] === 'update') {
                $settings[$operation[1][3][1]] = [$operation[1][3][0], $operation[1][1]['valeur']];
            }
        }
        foreach (['api_tokens', 'api_sessions', 'device_pairings', 'background_tasks', 'api_idempotency', 'tokens'] as $table) {
            self::assertContains('fixture_' . $table, $deleted);
        }
        self::assertSame(['settings', '0'], $settings['bck_scheduled_enabled']);
        self::assertSame(['settings', '0'], $settings['bck_externalized_enabled']);
        self::assertSame(['admin', '1'], $settings['maintenance_mode']);
        self::assertSame(['admin', '0'], $settings['lapr_enabled']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $settings['recovery_generation'][1]);
        self::assertSame('commit', end($operations)[0]);
    }
}
