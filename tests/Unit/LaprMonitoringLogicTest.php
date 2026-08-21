<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      LaprMonitoringLogicTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/lapr.monitoring.functions.php';

class LaprMonitoringLogicTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function healthyAccount(int $now): array
    {
        return [
            'status' => 'active',
            'endpoint_id_resolved' => 7,
            'endpoint_status' => 'active',
            'managed_item_id' => 19,
            'last_rotation_status' => 'success',
            'next_rotation_at' => date('Y-m-d H:i:s', $now + 86400),
            'retry_at' => null,
        ];
    }

    public function testGracePeriodUsesTwoSchedulerIntervalsWithTenMinuteFloor(): void
    {
        self::assertSame(600, laprMonitoringGraceSeconds(['lapr_scheduler_interval_minutes' => 2]));
        self::assertSame(1800, laprMonitoringGraceSeconds(['lapr_scheduler_interval_minutes' => 15]));
    }

    public function testOperatorSummaryDistinguishesEffectiveAndDormantGrants(): void
    {
        $row = [
            'granted_active' => 4,
            'disabled_grants' => 2,
            'granted_total' => 6,
        ];

        self::assertSame(
            ['active' => 4, 'granted_active' => 4, 'disabled_grants' => 2, 'granted_total' => 6],
            laprMonitoringAccessSummary(true, $row)
        );
        self::assertSame(0, laprMonitoringAccessSummary(false, $row)['active']);
    }

    public function testAccountClassificationDistinguishesNormalLifecycleStates(): void
    {
        $now = 1_750_000_000;
        $account = $this->healthyAccount($now);
        self::assertSame('healthy', laprMonitoringClassifyAccount($account, $now, 600));

        $account['last_rotation_status'] = 'never';
        self::assertSame('scheduled', laprMonitoringClassifyAccount($account, $now, 600));

        $account['last_rotation_status'] = 'failure';
        $account['retry_at'] = date('Y-m-d H:i:s', $now + 3600);
        self::assertSame('retrying', laprMonitoringClassifyAccount($account, $now, 600));

        $account['retry_at'] = date('Y-m-d H:i:s', $now - 3600);
        self::assertSame('overdue', laprMonitoringClassifyAccount($account, $now, 600));

        $account = $this->healthyAccount($now);
        $account['status'] = 'paused';
        self::assertSame('paused', laprMonitoringClassifyAccount($account, $now, 600));

        $account = $this->healthyAccount($now);
        $account['endpoint_status'] = 'disabled';
        self::assertSame('error', laprMonitoringClassifyAccount($account, $now, 600));

        $account = $this->healthyAccount($now);
        $account['monitoring_integrity_error'] = true;
        self::assertSame('error', laprMonitoringClassifyAccount($account, $now, 600));
    }

    public function testOverdueStateStartsOnlyAfterTheGracePeriod(): void
    {
        $now = 1_750_000_000;
        $account = $this->healthyAccount($now);
        $account['next_rotation_at'] = date('Y-m-d H:i:s', $now - 300);
        self::assertSame('healthy', laprMonitoringClassifyAccount($account, $now, 600));

        $account['next_rotation_at'] = date('Y-m-d H:i:s', $now - 601);
        self::assertSame('overdue', laprMonitoringClassifyAccount($account, $now, 600));
    }

    public function testFailureCodesAreGroupedIntoStablePresentationCategories(): void
    {
        self::assertSame('connectivity', laprMonitoringFailureCategory('ERR_TIMEOUT'));
        self::assertSame('authentication', laprMonitoringFailureCategory('ERR_AUTH_FAILED'));
        self::assertSame('hostkey', laprMonitoringFailureCategory('ERR_HOSTKEY_MISMATCH'));
        self::assertSame('password_change', laprMonitoringFailureCategory('ERR_CHPASSWD_FAILED'));
        self::assertSame('synchronization', laprMonitoringFailureCategory('ERR_ITEM_UPDATE_FAILED'));
        self::assertSame('synchronization', laprMonitoringFailureCategory('ERR_SSH_CREDENTIAL_SYNC_FAILED'));
        self::assertSame('other', laprMonitoringFailureCategory('ERR_FUTURE_CODE'));
    }

    public function testGlobalCronFailureIsReflectedInTheLaprHealthSnapshot(): void
    {
        $snapshot = laprMonitoringEmptySnapshot(true, 'healthy');
        $snapshot['available'] = true;
        $snapshot['enabled'] = true;
        $snapshot['scheduler']['enabled'] = true;
        $snapshot['scheduler']['status'] = 'success';
        $snapshot['overall']['status'] = 'success';

        $result = laprMonitoringApplyCronStatus($snapshot, 'danger');

        self::assertSame('danger', $result['scheduler']['status']);
        self::assertSame('cron_unhealthy', $result['scheduler']['reason']);
        self::assertSame('danger', $result['overall']['status']);
        self::assertSame('cron_unhealthy', $result['action_items'][0]['code']);
    }

    public function testCronOverlayKeepsTheActionListBounded(): void
    {
        $snapshot = laprMonitoringEmptySnapshot(true, 'healthy');
        $snapshot['available'] = true;
        $snapshot['enabled'] = true;
        $snapshot['scheduler']['enabled'] = true;
        $snapshot['overall']['status'] = 'info';
        $snapshot['action_items'] = array_fill(0, 50, ['code' => 'existing']);

        $result = laprMonitoringApplyCronStatus($snapshot, 'warning');

        self::assertCount(50, $result['action_items']);
        self::assertSame('cron_unhealthy', $result['action_items'][0]['code']);
        self::assertSame('warning', $result['overall']['status']);
    }

    public function testDisabledSchedulerIsNotPromotedToAHealthFailure(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/lapr.monitoring.functions.php');
        self::assertIsString($source);
        self::assertStringNotContainsString(
            "&& (bool) \$scheduler['enabled'] === false",
            $source
        );
        self::assertStringContainsString(
            "\$reason = \$moduleEnabled === false ? 'module_disabled' : 'scheduler_disabled';",
            $source
        );
    }

    public function testCredentialSynchronizationFailureCannotBeReportedAsRotationSuccess(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/scripts/traits/LAPRRotationTrait.php');
        self::assertIsString($source);
        self::assertStringContainsString("'ERR_SSH_CREDENTIAL_SYNC_FAILED'", $source);
        self::assertStringContainsString("'SSH_CREDENTIAL_RESYNC_REQUIRED'", $source);
        self::assertStringContainsString("], 'failure', \$accountId, 'ERR_SSH_CREDENTIAL_SYNC_FAILED'", $source);
    }
}
