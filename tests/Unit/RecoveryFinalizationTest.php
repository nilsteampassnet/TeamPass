<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file RecoveryFinalizationTest.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/recovery_environment.php';
require_once __DIR__ . '/../../app/sources/recovery_finalization.php';

final class RecoveryFinalizationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . "/tp-finalization O'Brien " . bin2hex(random_bytes(8));
        mkdir($this->root . '/public/install', 0700, true);
        mkdir($this->root . '/app/sources', 0700, true);
        file_put_contents($this->root . '/app/sources/scheduler.php', '<?php // synthetic scheduler; never executed');
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        unlink($this->root . '/app/sources/scheduler.php');
        rmdir($this->root . '/app/sources');
        rmdir($this->root . '/app');
        rmdir($this->root . '/public/install');
        rmdir($this->root . '/public');
        rmdir($this->root);
    }

    public function testReceiptsExposeOnlyFinalizationMetadata(): void
    {
        $receipt = ['id' => 'job', 'status' => 'resumed', 'resumed_at' => 123,
            'permissions' => ['status' => 'passed'], 'connection' => ['password' => 'secret'],
            'kit' => ['master_key' => 'secret'], 'backup_password' => 'secret'];
        self::assertSame(['id' => 'job', 'status' => 'resumed', 'resumed_at' => 123,
            'permissions' => ['status' => 'passed']], tpRecoveryReceiptFields($receipt));
    }

    public function testReceiptsKeepEncryptionCountersAndAcknowledgementWithoutPrivateCheckpointData(): void
    {
        $diagnostic = ['tp_key_status' => 'unusable', 'diagnostic' => 'private_key_missing',
            'total_items' => 103, 'checked_items' => 0, 'missing_tp_sharekeys' => 1,
            'skipped_items' => 102, 'complete' => true];
        $receipt = ['id' => 'job', 'status' => 'complete', 'unusable_tp_key_acknowledged_at' => 123,
            'encryption' => $diagnostic + ['last_id' => 204, 'private_key' => 'synthetic secret'],
            'connection' => ['password' => 'synthetic secret']];
        self::assertSame(['id' => 'job', 'status' => 'complete', 'unusable_tp_key_acknowledged_at' => 123,
            'encryption' => $diagnostic], tpRecoveryReceiptFields($receipt));
    }

    public function testPermissionFindingsReuseTheCentralIntegrityPlanWithoutApplyingIt(): void
    {
        $report = ['scan_supported' => true, 'has_result' => true, 'remediation_supported' => true,
            'identity' => ['source' => 'current_process', 'web_user' => 'www-data', 'web_group' => 'www-data'],
            'counts' => ['issues' => 3]];
        $before = fileperms($this->root);
        $review = tpRecoveryPermissionReview($this->root, $report, 123);
        self::assertSame('issues', $review['status']);
        self::assertSame(3, $review['issues']);
        self::assertSame(123, $review['checked_at']);
        self::assertNotEmpty($review['commands']);
        self::assertSame(tpFilePermissionsRemediationCommands($this->root, $report), $review['commands']);
        clearstatcache();
        self::assertSame($before, fileperms($this->root));
        self::assertDirectoryDoesNotExist($this->root . '/storage');
    }

    public function testAPassRequiresARealScanUnderTheCurrentServiceIdentity(): void
    {
        $report = ['scan_supported' => true, 'has_result' => true,
            'identity' => ['source' => 'current_process'], 'counts' => ['issues' => 0]];
        self::assertSame('passed', tpRecoveryPermissionReview($this->root, $report, 123)['status']);
        $report['identity']['source'] = 'os_default';
        $review = tpRecoveryPermissionReview($this->root, $report, 123);
        self::assertSame('manual', $review['status']);
        self::assertNull($review['issues']);
        self::assertSame([], $review['commands']);
        $report['identity']['source'] = 'current_process';
        $report['has_result'] = false;
        self::assertSame('manual', tpRecoveryPermissionReview($this->root, $report, 123)['status']);
    }

    public function testUnsupportedPlatformsNeverClaimAPassOrOfferPosixCommands(): void
    {
        $report = ['scan_supported' => false, 'has_result' => true, 'remediation_supported' => true,
            'identity' => ['source' => 'current_process', 'web_user' => 'www-data', 'web_group' => 'www-data'],
            'counts' => ['issues' => 0]];
        $review = tpRecoveryPermissionReview($this->root, $report, 123);
        self::assertSame('manual', $review['status']);
        self::assertFalse($review['scan_supported']);
        self::assertFalse($review['remediation_supported']);
        self::assertSame([], $review['commands']);
    }

    public function testCleanupTargetsOnlyTheValidatedInstallerOnLinuxAndWindows(): void
    {
        $installer = $this->root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'install';
        self::assertSame('sudo rm -rf -- ' . tpRecoveryCommandArgument($installer, 'Linux'),
            tpRecoveryInstallerCleanupCommand($this->root, 'Linux'));
        self::assertSame('Remove-Item -LiteralPath ' . tpRecoveryCommandArgument($installer, 'Windows') . ' -Recurse -Force',
            tpRecoveryInstallerCleanupCommand($this->root, 'Windows'));
        self::assertDirectoryExists($installer);
        self::assertNull(tpRecoveryInstallerCleanupCommand(dirname($this->root), 'Linux'));
        self::assertNull(tpRecoveryInstallerCleanupCommand($this->root . '/missing', 'Linux'));
        self::assertNull(tpRecoveryInstallerCleanupCommand(DIRECTORY_SEPARATOR, 'Linux'));
    }

    public function testCleanupRefusesASymlinkedInstaller(): void
    {
        if (PHP_OS_FAMILY === 'Windows') { self::markTestSkipped('Symlink creation needs additional Windows privileges'); }
        rmdir($this->root . '/public/install');
        symlink($this->root, $this->root . '/public/install');
        try {
            self::assertNull(tpRecoveryInstallerCleanupCommand($this->root, 'Linux'));
        } finally {
            unlink($this->root . '/public/install');
            mkdir($this->root . '/public/install', 0700);
        }
    }

    public function testCronInstructionsUseTheRealServiceIdentityAndQuotedAbsolutePaths(): void
    {
        $result = tpRecoverySchedulerInstructions($this->root, 'Linux', '/usr/local/PHP 8.5/php%', 'www-data');
        self::assertSame("sudo crontab -u 'www-data' -e", $result['edit']);
        self::assertStringStartsWith("* * * * * '/usr/local/PHP 8.5/php\\%' ", $result['entry']);
        self::assertStringContainsString(tpRecoveryCommandArgument($this->root . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'sources' . DIRECTORY_SEPARATOR . 'scheduler.php', 'Linux'), $result['entry']);
        self::assertStringEndsWith(' #Teampass scheduler', $result['entry']);
        self::assertArrayNotHasKey('program', $result);
    }

    public function testCronInstructionsDoNotInventAnAccountOrAllowLineInjection(): void
    {
        self::assertSame([], tpRecoverySchedulerInstructions($this->root, 'Linux', '/usr/bin/php', null));
        self::assertSame([], tpRecoverySchedulerInstructions($this->root, 'Linux', '/usr/bin/php', 'user;id'));
        self::assertSame([], tpRecoverySchedulerInstructions($this->root, 'Linux', "/usr/bin/php\nother job", 'www-data'));
        self::assertSame([], tpRecoverySchedulerInstructions($this->root . '/missing', 'Linux', '/usr/bin/php', 'www-data'));
    }

    public function testWindowsTaskSchedulerUsesProgramAndDoubleQuotedArgumentsWithoutCron(): void
    {
        $result = tpRecoverySchedulerInstructions($this->root, 'Windows', 'C:\\PHP 8.2\\php.exe', null);
        self::assertSame('C:\\PHP 8.2\\php.exe', $result['program']);
        self::assertSame('"' . $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
            . 'sources' . DIRECTORY_SEPARATOR . 'scheduler.php"', $result['arguments']);
        self::assertArrayNotHasKey('entry', $result);
        self::assertArrayNotHasKey('edit', $result);
    }

    public function testCronServiceCommandsRequireAnExistingKnownUnitAndNeverTargetWindows(): void
    {
        self::assertNull(tpRecoveryCronServiceCommand('Linux', [$this->root]));
        file_put_contents($this->root . '/crond.service', '# synthetic unit; never activated');
        try {
            self::assertSame("sudo systemctl enable --now crond.service\nsudo systemctl status crond.service --no-pager",
                tpRecoveryCronServiceCommand('Linux', [$this->root]));
            self::assertNull(tpRecoveryCronServiceCommand('Windows', [$this->root]));
        } finally { unlink($this->root . '/crond.service'); }
        file_put_contents($this->root . '/cron.service', '# synthetic unit; never activated');
        try {
            self::assertSame("sudo systemctl enable --now cron.service\nsudo systemctl status cron.service --no-pager",
                tpRecoveryCronServiceCommand('Linux', [$this->root]));
        } finally { unlink($this->root . '/cron.service'); }
    }
}
