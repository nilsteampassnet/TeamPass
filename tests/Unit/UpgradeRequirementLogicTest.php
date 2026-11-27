<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/sources/upgrade_requirement_logic.php';

/**
 * One definition of "the schema lags the deployed files" for every upgrade gate.
 *
 * upgrade_timestamp alone is blind to an older release upgraded after the cutoff of a newer one:
 * a 3.2.4.5 database upgraded after the 3.2.4.6 cutoff showed a fresh timestamp, so the sign-in
 * gate, the background tasks, the Docker entrypoint and the command line all skipped the
 * 3.2.4.6 migration. The recorded teampass_schema_level closes that gap.
 */
final class UpgradeRequirementLogicTest extends TestCase
{
    private const MIN_DATE = 1795633142;

    private static function source(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    /**
     * @dataProvider requirementProvider
     */
    public function testRequirement(int $timestamp, string $schemaLevel, bool $required): void
    {
        $this->assertSame($required, tpSchemaUpgradeRequired($timestamp, $schemaLevel, self::MIN_DATE));
    }

    public static function requirementProvider(): iterable
    {
        yield 'up to date' => [self::MIN_DATE + 100, (string) self::MIN_DATE, false];
        yield 'older timestamp' => [self::MIN_DATE - 1, (string) self::MIN_DATE, true];
        yield 'fresh timestamp, older schema level' => [self::MIN_DATE + 100, '1794554343', true];
        yield 'schema level never recorded' => [self::MIN_DATE + 100, '', false];
        yield 'schema level not numeric' => [self::MIN_DATE + 100, 'n/a', false];
        yield 'schema level padded' => [self::MIN_DATE + 100, ' 1794554343 ', true];
        yield 'files older than the database' => [self::MIN_DATE + 100, (string) (self::MIN_DATE + 50), false];
        yield 'timestamp missing' => [0, (string) self::MIN_DATE, true];
    }

    public function testEffectiveLevelIsTheLowerOfTheTwo(): void
    {
        $this->assertSame(1794554343, tpEffectiveSchemaLevel(self::MIN_DATE + 100, '1794554343'));
        $this->assertSame(self::MIN_DATE - 1, tpEffectiveSchemaLevel(self::MIN_DATE - 1, (string) self::MIN_DATE));
        $this->assertSame(self::MIN_DATE + 100, tpEffectiveSchemaLevel(self::MIN_DATE + 100, ''));
    }

    public function testEveryGateUsesTheSharedRule(): void
    {
        $main = self::source('app/sources/main.functions.php');
        $start = (int) strpos($main, 'function upgradeRequired(');
        $upgradeRequired = substr($main, $start, (int) strpos($main, "\nfunction ", $start + 1) - $start);
        $this->assertStringContainsString("require_once __DIR__ . '/upgrade_requirement_logic.php';", $main);
        $this->assertStringContainsString('tpSchemaUpgradeRequired(', $upgradeRequired);
        $this->assertStringContainsString("'teampass_schema_level'", $upgradeRequired);

        // The cached pre-check must not filter out what upgradeRequired() would catch.
        $index = self::source('public/index.php');
        $this->assertStringContainsString("(string) (\$SETTINGS['teampass_schema_level'] ?? '')", $index);
        $this->assertStringNotContainsString("(int) (\$SETTINGS['upgrade_timestamp'] ?? 0) < (int) UPGRADE_MIN_DATE", $index);

        $this->assertStringContainsString('tpSchemaUpgradeRequired(', self::source('app/sources/upgrade_cli_logic.php'));
        $this->assertStringContainsString('tpEffectiveSchemaLevel(', self::source('app/scripts/read-db-upgrade-timestamp.php'));
    }

    public function testDisasterRecoveryRecordsTheSchemaLevelItUpgradedTo(): void
    {
        $this->assertMatchesRegularExpression(
            "/'teampass_schema_level' => \\(string\\) UPGRADE_MIN_DATE,\\s*'upgrade_timestamp' => \\(string\\) UPGRADE_MIN_DATE/",
            self::source('app/sources/recovery.functions.php')
        );
    }
}
