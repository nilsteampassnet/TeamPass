<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/upgrade_cli_logic.php';

/**
 * Sentinel: the web wizard and the command-line upgrade share one manifest.
 *
 * A new upgrade_run_X.Y.Z.php that is not listed would be skipped by both; a new operation that
 * reads POST parameters placed after a script the command line runs would stop every
 * command-line upgrade, so it must be ported deliberately.
 */
final class UpgradeManifestTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<array{0: string, 1: string}> */
    private static function manifest(): array
    {
        return require self::root() . '/public/install/upgrade_manifest.php';
    }

    public function testWizardReadsTheSharedManifest(): void
    {
        $manager = (string) file_get_contents(self::root() . '/public/install/upgrade_scripts_manager.php');

        $this->assertStringContainsString("\$scripts_list = require __DIR__ . '/upgrade_manifest.php';", $manager);
        $this->assertStringNotContainsString('$scripts_list = array(', $manager);
    }

    public function testEveryVersionedUpgradeScriptIsListedInVersionOrder(): void
    {
        $listed = [];
        foreach (self::manifest() as $entry) {
            $version = tpUpgradeCliScriptVersion($entry[0]);
            if ($version !== null && tpUpgradeCliCompareVersions($version, '3.1.5') >= 0) {
                $listed[] = $version;
            }
        }

        $shipped = [];
        foreach (glob(self::root() . '/public/install/upgrade_run_*.php') ?: [] as $path) {
            $version = tpUpgradeCliScriptVersion(basename($path));
            if ($version !== null && tpUpgradeCliCompareVersions($version, '3.1.5') >= 0) {
                $shipped[] = $version;
            }
        }
        usort($shipped, 'tpUpgradeCliCompareVersions');

        $this->assertSame($shipped, $listed);
    }

    public function testOperationsReachableFromTheCommandLineAreAllowedThere(): void
    {
        // Throws when an operation after a kept script is not runnable from the command line.
        $plan = tpUpgradeCliBuildPlan(self::manifest(), TP_UPGRADE_CLI_FLOOR, '99.0.0');

        $this->assertNotEmpty($plan);
    }

    public function testOperationsScriptAllowsExactlyTheCommandLineOperations(): void
    {
        $operations = (string) file_get_contents(self::root() . '/public/install/upgrade_operations.php');

        $this->assertMatchesRegularExpression('/PHP_SAPI === \'cli\'.*?in_array\(\$post_operation, \[([^\]]+)\]/s', $operations);
        preg_match('/PHP_SAPI === \'cli\'.*?in_array\(\$post_operation, \[([^\]]+)\]/s', $operations, $matches);
        preg_match_all("/'([a-z_]+)'/", $matches[1], $names);

        $this->assertSame(TP_UPGRADE_CLI_CLI_OPERATIONS, $names[1]);
    }
}
