<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/upgrade_cli_logic.php';

/**
 * Decision logic of the command-line upgrade (app/scripts/upgrade-cli.php).
 *
 * The plan must replay the script of the recorded version, never start below the floor, and a
 * step must count as a success only when its JSON says so: the upgrade scripts always exit 0.
 */
final class UpgradeCliLogicTest extends TestCase
{
    /** @return list<array{0: string, 1: string}> */
    private static function manifest(): array
    {
        return require dirname(__DIR__, 2) . '/public/install/upgrade_manifest.php';
    }

    /** @return array{db_version: string, db_version_full: string, db_schema_level: string, upgrade_timestamp: int, files_version: string, files_version_full: string, min_date: int} */
    private static function state(array $overrides = []): array
    {
        return array_merge([
            'db_version' => '3.2.3',
            'db_version_full' => '3.2.3.4',
            'db_schema_level' => '1800000000',
            'upgrade_timestamp' => 1800000100,
            'files_version' => '3.2.3',
            'files_version_full' => '3.2.3.4',
            'min_date' => 1800000000,
        ], $overrides);
    }

    public function testVersionsCompareNumerically(): void
    {
        $this->assertGreaterThan(0, tpUpgradeCliCompareVersions('3.2.10', '3.2.9'));
        $this->assertSame(0, tpUpgradeCliCompareVersions('3.2.3', '3.2.3.0'));
        $this->assertLessThan(0, tpUpgradeCliCompareVersions('3.2.2', '3.2.3'));
        $this->assertNull(tpUpgradeCliParseVersion('3.2.x'));
        $this->assertNull(tpUpgradeCliParseVersion(''));
    }

    public function testOnlyVersionedScriptsHaveAVersion(): void
    {
        $this->assertSame('3.2.3', tpUpgradeCliScriptVersion('upgrade_run_3.2.3.php'));
        $this->assertNull(tpUpgradeCliScriptVersion('upgrade_run_3.0.0_users.php'));
        $this->assertNull(tpUpgradeCliScriptVersion('upgrade_run_3.1.php'));
        $this->assertNull(tpUpgradeCliScriptVersion('upgrade_operations.php'));
    }

    public function testUpToDateNeedsVersionTimestampReleaseAndSchemaLevel(): void
    {
        $this->assertSame('up_to_date', tpUpgradeCliDecide(self::state())['decision']);
    }

    /**
     * @dataProvider decisionProvider
     */
    public function testDecision(array $overrides, string $decision, string $reason): void
    {
        $this->assertSame(['decision' => $decision, 'reason' => $reason], tpUpgradeCliDecide(self::state($overrides)));
    }

    public static function decisionProvider(): iterable
    {
        yield 'unreadable version' => [['db_version' => ''], 'blocked', 'DB_VERSION_UNREADABLE'];
        yield 'database newer than the files' => [['db_version' => '3.2.4'], 'blocked', 'DOWNGRADE'];
        yield 'below the floor' => [['db_version' => '3.2.1'], 'blocked', 'BELOW_FLOOR'];
        yield 'older version' => [['db_version' => '3.2.2', 'db_version_full' => ''], 'upgrade', 'VERSION_BEHIND'];
        yield 'patch release with schema change' => [['upgrade_timestamp' => 1799999999], 'upgrade', 'SCHEMA_BEHIND'];
        yield 'patch release without schema change' => [['db_version_full' => '3.2.3.3'], 'record_release_only', 'RELEASE_RECORD_STALE'];
        yield 'schema level never recorded' => [['db_schema_level' => ''], 'record_release_only', 'RELEASE_RECORD_STALE'];
    }

    public function testAFreshTimestampDoesNotHideAnOlderVersion(): void
    {
        // A chain stopped after its first 3.2.x script already wrote upgrade_timestamp.
        $decision = tpUpgradeCliDecide(self::state(['db_version' => '3.2.2', 'upgrade_timestamp' => 1900000000]));

        $this->assertSame('upgrade', $decision['decision']);
    }

    public function testPlanFrom322ReplaysItsScriptThenRunsTheNextOneAndItsOperations(): void
    {
        $plan = tpUpgradeCliBuildPlan(self::manifest(), '3.2.2', '3.2.3');

        $this->assertSame(
            [
                ['script', 'upgrade_run_3.2.2.php', '3.2.2'],
                ['script', 'upgrade_run_3.2.3.php', '3.2.3'],
                ['operation', 'sanitize_legacy_fa_icons', '3.2.3'],
                ['operation', 'sanitize_directory_user_fields', '3.2.3'],
                ['operation', 'sanitize_imported_folder_titles', '3.2.3'],
            ],
            array_map(static fn (array $step): array => [$step['kind'], $step['name'], $step['version']], $plan)
        );
    }

    public function testPlanOfAPatchReleaseReplaysTheCurrentScriptOnly(): void
    {
        $plan = tpUpgradeCliBuildPlan(self::manifest(), '3.2.3', '3.2.3');

        $this->assertSame('upgrade_run_3.2.3.php', $plan[0]['name']);
        $this->assertCount(4, $plan);
    }

    public function testPlanNeverStartsBelowTheFloor(): void
    {
        $plan = tpUpgradeCliBuildPlan(self::manifest(), '3.1.5', '3.2.3');

        $this->assertSame('upgrade_run_3.2.2.php', $plan[0]['name']);
    }

    public function testPlanRefusesAPostOnlyOperationAfterAKeptScript(): void
    {
        $this->expectException(RuntimeException::class);

        tpUpgradeCliBuildPlan(
            [['upgrade_run_3.2.3.php', 'user_id'], ['upgrade_operations.php', '20230604_1']],
            '3.2.3',
            '3.2.3'
        );
    }

    public function testStepSucceedsOnlyWhenItsJsonSaysSo(): void
    {
        $this->assertTrue(tpUpgradeCliParseStepOutput('[{"finish":"1" , "next":"", "error":""}]', 0)['ok']);

        $failed = tpUpgradeCliParseStepOutput('[{"finish":"1","error":"Error installing external device pairing"}]', 0);
        $this->assertFalse($failed['ok']);
        $this->assertSame('Error installing external device pairing', $failed['error']);
    }

    public function testWarningsBeforeTheJsonAreIgnored(): void
    {
        $result = tpUpgradeCliParseStepOutput("PHP Warning: something\n[{\"finish\":\"1\", \"next\":\"\", \"error\":\"\"}]\n", 0);

        $this->assertTrue($result['ok']);
    }

    public function testAddslashesErrorIsStillReported(): void
    {
        // upgrade_run_3.2.2.php embeds addslashes(mysqli_error()), which is not valid JSON.
        $result = tpUpgradeCliParseStepOutput('[{"finish":"1", "msg":"", "error":"Error creating table: Table \\\'x\\\' exists"}]', 0);

        $this->assertFalse($result['ok']);
        $this->assertSame("Error creating table: Table 'x' exists", $result['error']);
    }

    public function testMissingOutputOrNonZeroExitIsAFailure(): void
    {
        $this->assertSame('No result returned', tpUpgradeCliParseStepOutput('', 0)['error']);
        $this->assertFalse(tpUpgradeCliParseStepOutput('[{"finish":"1","error":""}]', 255)['ok']);
        $this->assertStringStartsWith('Exit code 255', tpUpgradeCliParseStepOutput('PHP Fatal error', 255)['error']);
    }

    public function testCallAgainIsNotASuccess(): void
    {
        $result = tpUpgradeCliParseStepOutput('[{"finish":"0" , "next":"", "error":"", "total":"0"}]', 0);

        $this->assertFalse($result['ok']);
        $this->assertSame('0', $result['finish']);
        $this->assertSame('', $result['error']);
    }

    public function testSchemaLevelPrefersTheRecordedValue(): void
    {
        $this->assertSame('1800000000', tpUpgradeCliResolveSchemaLevel('1800000000', '3.2.3.3'));
        $this->assertSame('1792993330', tpUpgradeCliResolveSchemaLevel('', '3.2.3.3'));
        $this->assertSame('', tpUpgradeCliResolveSchemaLevel('', '3.2.2.8'));
        $this->assertSame('', tpUpgradeCliResolveSchemaLevel('', ''));
    }

    public function testOnlyPrefixedTablesCreatedByTheMigrationAreDropped(): void
    {
        $drop = tpUpgradeCliTablesToDrop(
            ['teampass_items', 'teampass_webauthn_credentials', 'other_app_table', 'teampassXitems'],
            ['teampass_items' => 3],
            'teampass_'
        );

        $this->assertSame(['teampass_webauthn_credentials'], $drop);
    }

    public function testInventoryDiffReportsMissingAndChangedTables(): void
    {
        $this->assertSame(
            ['b' => [2, 3], 'c' => [1, null]],
            tpUpgradeCliInventoryDiff(['a' => 1, 'b' => 2, 'c' => 1], ['a' => 1, 'b' => 3])
        );
    }

    public function testInstallerTablesAreExtracted(): void
    {
        $tables = tpUpgradeCliInstallerTables(
            (string) file_get_contents(dirname(__DIR__, 2) . '/public/install/install-steps/run.step5.php')
        );

        foreach (['items', 'misc', 'users', 'nested_tree', 'sharekeys_items'] as $table) {
            $this->assertContains($table, $tables);
        }
        $this->assertGreaterThan(50, count($tables));
    }

    public function testJournalTransitions(): void
    {
        $this->assertTrue(tpUpgradeCliCanTransition('', 'running'));
        $this->assertTrue(tpUpgradeCliCanTransition('failed_resumable', 'running'));
        $this->assertTrue(tpUpgradeCliCanTransition('completed', 'reverting'));
        $this->assertTrue(tpUpgradeCliCanTransition('revert_failed', 'reverting'));
        $this->assertFalse(tpUpgradeCliCanTransition('reverted', 'running'));
        $this->assertFalse(tpUpgradeCliCanTransition('aborted', 'reverting'));
        $this->assertFalse(tpUpgradeCliCanTransition('completed', 'running'));
    }

    public function testRunIdentifiers(): void
    {
        $runId = tpUpgradeCliNewRunId(1793000000, 'ab12cd34');

        $this->assertTrue(tpUpgradeCliIsValidRunId($runId));
        $this->assertFalse(tpUpgradeCliIsValidRunId('../../etc/passwd'));
        $this->assertFalse(tpUpgradeCliIsValidRunId($runId . '.json'));
    }

    public function testExitCodes(): void
    {
        $this->assertSame(0, tpUpgradeCliExitCodeForStatus('completed'));
        $this->assertSame(40, tpUpgradeCliExitCodeForStatus('reverted'));
        $this->assertSame(41, tpUpgradeCliExitCodeForStatus('failed_resumable'));
        $this->assertSame(50, tpUpgradeCliExitCodeForStatus('revert_failed'));
    }

    public function testRevertInstructionsNameTheReleaseOnlyWhenKnown(): void
    {
        $this->assertStringContainsString('git checkout 3.2.3.3', implode("\n", tpUpgradeCliRevertNextAction('3.2.3.3', true)));
        $this->assertStringNotContainsString('git checkout', implode("\n", tpUpgradeCliRevertNextAction('', true)));
        $this->assertStringContainsString('maintenance', implode("\n", tpUpgradeCliRevertNextAction('3.2.3.3', false)));
    }

    public function testArgumentsAreParsedAfterTheCommand(): void
    {
        $parsed = tpUpgradeCliParseArguments(['run', '--yes', '--on-failure=stop']);
        $this->assertSame('', $parsed['error']);
        $this->assertSame('run', $parsed['command']);
        $this->assertSame(['yes' => true, 'on-failure' => 'stop'], $parsed['options']);

        $resume = tpUpgradeCliParseArguments(['resume', '20261029T143000-ab12cd34']);
        $this->assertSame('', $resume['error']);
        $this->assertSame('20261029T143000-ab12cd34', $resume['run_id']);

        $this->assertSame('help', tpUpgradeCliParseArguments([])['command']);
        $this->assertSame('help', tpUpgradeCliParseArguments(['run', '--help'])['command']);
    }

    /**
     * @dataProvider invalidArgumentsProvider
     */
    public function testInvalidArgumentsAreRefused(array $arguments): void
    {
        $this->assertNotSame('', tpUpgradeCliParseArguments($arguments)['error']);
    }

    public static function invalidArgumentsProvider(): iterable
    {
        yield 'unknown command' => [['upgrade']];
        yield 'option of another command' => [['check', '--yes']];
        yield 'bad failure mode' => [['run', '--on-failure=ignore']];
        yield 'bad wait' => [['run', '--wait-tasks=abc']];
        yield 'resume without run' => [['resume']];
        yield 'run identifier with a path' => [['revert', '../../etc/passwd']];
        yield 'run identifier on run' => [['run', '20261029T143000-ab12cd34']];
    }

    public function testBackupSpaceCoversBothTemporaryDumps(): void
    {
        $this->assertSame(250 * 1024 * 1024, tpUpgradeCliRequiredFreeBytes(100 * 1024 * 1024));
        $this->assertSame(10 * 1024 * 1024, tpUpgradeCliRequiredFreeBytes(0));
    }
}
