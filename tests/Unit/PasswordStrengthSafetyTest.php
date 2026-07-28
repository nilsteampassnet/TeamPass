<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/password_strength.functions.php';

/**
 * Regression coverage for malformed legacy password bytes passed to zxcvbn-php.
 */
class PasswordStrengthSafetyTest extends TestCase
{
    public function testValidUtf8PasswordIsEvaluated(): void
    {
        $result = evaluatePasswordStrengthSafely(
            "Valid\xC3\xA9Password!",
            static fn (string $password): array => ['score' => 3]
        );

        self::assertSame(
            ['success' => true, 'score' => 3, 'reason' => ''],
            $result
        );
    }

    public function testInvalidUtf8IsRejectedBeforeCallingZxcvbn(): void
    {
        $called = false;
        $password = 'legacy-' . chr(0xE9);

        $result = evaluatePasswordStrengthSafely(
            $password,
            static function (string $value) use (&$called): array {
                $called = true;
                return ['score' => 2];
            }
        );

        self::assertFalse($called);
        self::assertSame(
            ['success' => false, 'score' => null, 'reason' => 'invalid_utf8'],
            $result
        );
    }

    public function testEvaluatorThrowableBecomesAnUnassessableResult(): void
    {
        $result = evaluatePasswordStrengthSafely(
            'valid-password',
            static function (string $password): array {
                throw new TypeError('Synthetic zxcvbn failure');
            }
        );

        self::assertSame(
            ['success' => false, 'score' => null, 'reason' => 'evaluation_failed'],
            $result
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidResultProvider(): array
    {
        return [
            'not an array' => ['invalid'],
            'missing score' => [['guesses' => 100]],
            'non numeric score' => [['score' => 'unknown']],
            'negative score' => [['score' => -1]],
            'score above zxcvbn range' => [['score' => 5]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidResultProvider')]
    public function testMalformedEvaluatorResultIsRejected(mixed $evaluation): void
    {
        $result = evaluatePasswordStrengthSafely(
            'valid-password',
            static fn (string $password): mixed => $evaluation
        );

        self::assertSame(
            ['success' => false, 'score' => null, 'reason' => 'invalid_result'],
            $result
        );
    }

    public function testEveryServerSideZxcvbnCallerUsesTheSafeAdapter(): void
    {
        $root = dirname(__DIR__, 2);
        $callers = [
            $root . '/app/sources/dashboard.queries.php',
            $root . '/app/sources/items.queries.php',
            $root . '/app/scripts/background_tasks___do_calculation.php',
            $root . '/app/api/Model/ItemModel.php',
        ];

        foreach ($callers as $caller) {
            $source = (string) file_get_contents($caller);
            self::assertStringContainsString('evaluatePasswordStrengthSafely(', $source, $caller);
            self::assertStringNotContainsString('->passwordStrength(', $source, $caller);
        }
    }

    public function testSecurityPostureReportsSkippedItemsAndHandlesHttpFailure(): void
    {
        $root = dirname(__DIR__, 2);
        $querySource = (string) file_get_contents($root . '/app/sources/dashboard.queries.php');
        $clientSource = (string) file_get_contents($root . '/app/pages/dashboard.js.php');
        $english = (string) file_get_contents($root . '/app/includes/language/english.php');
        $french = (string) file_get_contents($root . '/app/includes/language/french.php');

        self::assertStringContainsString("'skipped_count' => \$skippedAssessments", $querySource);
        self::assertStringContainsString('dashboardScanSkipped +=', $clientSource);
        self::assertStringContainsString('.fail(function ()', $clientSource);
        self::assertStringContainsString('dashboardScanFinish(false)', $clientSource);
        self::assertStringContainsString("'security_dashboard_scan_done_skipped' =>", $english);
        self::assertStringContainsString("'security_dashboard_scan_done_skipped' =>", $french);
        self::assertStringContainsString("'security_dashboard_scan_failed' =>", $english);
        self::assertStringContainsString("'security_dashboard_scan_failed' =>", $french);
        self::assertStringContainsString("'error_password_strength_evaluation' =>", $english);
        self::assertStringContainsString("'error_password_strength_evaluation' =>", $french);
    }
}
