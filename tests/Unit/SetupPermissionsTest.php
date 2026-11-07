<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file SetupPermissionsTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/setup_permissions.php';

final class SetupPermissionsTest extends TestCase
{
    /** Build a synthetic current-service report, without changing real permissions. */
    private function report(int $issues = 0): array
    {
        return ['scan_supported' => true, 'has_result' => true, 'counts' => ['issues' => $issues],
            'identity' => ['source' => 'current_process', 'web_user' => 'php-test', 'web_group' => 'php-test'],
            'issues' => []];
    }

    public function testKnownFindingsCannotBeAcknowledgedAway(): void
    {
        $review = tpSetupPermissionsReview(__DIR__, $this->report(1), 123);
        $this->expectExceptionMessage('PERMISSIONS_REVIEW_REQUIRED');
        tpSetupPermissionsApprove($review, true);
    }

    public function testOnlyARealCurrentIdentityScanCanPass(): void
    {
        $report = $this->report();
        $review = tpSetupPermissionsReview(__DIR__, $report, 123);
        self::assertSame('passed', tpSetupPermissionsApprove($review, false)['status']);
        $report['identity']['source'] = 'distribution_default';
        $manual = tpSetupPermissionsReview(__DIR__, $report, 124);
        self::assertSame('manual', $manual['status']);
        $this->expectExceptionMessage('PERMISSIONS_MANUAL_ACK_REQUIRED');
        tpSetupPermissionsApprove($manual, false);
    }

    public function testUnsupportedAuditRequiresExplicitAcknowledgementAndNeverBecomesAPass(): void
    {
        $review = tpSetupPermissionsReview(__DIR__, ['scan_supported' => false], 123);
        $approved = tpSetupPermissionsApprove($review, true);
        self::assertSame('manual', $approved['status']);
        self::assertIsInt($approved['manual_acknowledged_at']);
        self::assertNull($approved['issues']);
        self::assertSame([], $approved['commands']);
    }

    public function testContainerOwnershipNeedsManualReviewButWorldWriteStillBlocks(): void
    {
        $report = $this->report(1) + ['manual_reason' => 'container'];
        $report['issues'] = [['reason' => 'protected_writable']];
        $review = tpSetupPermissionsReview(__DIR__, $report, 123);
        self::assertSame('manual', $review['status']);
        self::assertSame([], $review['commands']);
        self::assertSame('manual', tpSetupPermissionsApprove($review, true)['status']);
        $report['issues'][] = ['reason' => 'world_writable'];
        $blocked = tpSetupPermissionsReview(__DIR__, $report, 124);
        self::assertSame('issues', $blocked['status']);
        $this->expectExceptionMessage('PERMISSIONS_REVIEW_REQUIRED');
        tpSetupPermissionsApprove($blocked, true);
    }

    public function testInvalidOrMissingReviewCannotAuthorizeFinalization(): void
    {
        $this->expectExceptionMessage('PERMISSIONS_REVIEW_REQUIRED');
        tpSetupPermissionsApprove([], true);
    }

    public function testReplacingAPassedReportWithCurrentFindingsRefusesFinalization(): void
    {
        $passed = tpSetupPermissionsReview(__DIR__, $this->report(), 123);
        self::assertSame('passed', $passed['status']);
        $current = tpSetupPermissionsReview(__DIR__, $this->report(2), 124);
        $this->expectExceptionMessage('PERMISSIONS_REVIEW_REQUIRED');
        tpSetupPermissionsApprove($current, false);
    }
}
