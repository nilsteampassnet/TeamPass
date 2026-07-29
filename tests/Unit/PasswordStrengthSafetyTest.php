<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/password_strength.functions.php';
require_once __DIR__ . '/../../app/sources/security_posture_logic.php';

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

    /**
     * Repo-wide guard: a new server-side caller must not reach zxcvbn directly.
     *
     * The list above only pins the four known call sites; this sweep is what keeps a future
     * file from reintroducing the fatal error. Instantiating Zxcvbn is allowed — callers may
     * build the object once and hand it over as the adapter's evaluator — but only the adapter
     * may actually invoke passwordStrength(), because that is the call that must be guarded.
     */
    public function testNoServerSideFileCallsZxcvbnOutsideTheSafeAdapter(): void
    {
        $root = dirname(__DIR__, 2);
        $adapter = $root . '/app/sources/password_strength.functions.php';
        $offenders = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current): bool {
                    // Third-party code owns its own call sites.
                    return $current->getFilename() !== 'vendor';
                }
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() === false || $file->getExtension() !== 'php') {
                continue;
            }

            $path = (string) $file->getRealPath();
            if ($path === $adapter) {
                continue;
            }

            if (str_contains((string) file_get_contents($path), '->passwordStrength(') === true) {
                $offenders[] = str_replace($root . '/', '', $path);
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These files must call evaluatePasswordStrengthSafely() instead of zxcvbn directly.'
        );
    }

    /**
     * An unassessable password must be marked, not left unset.
     *
     * Leaving complexity_level at NULL/-1/'' keeps the item inside the background calculation
     * work queue for ever: the same rows get decrypted and re-evaluated on every run, and past
     * 100 such items the LIMIT 0,100 window is saturated and no other item is ever processed.
     */
    public function testUnassessablePasswordsAreMarkedWithTheSentinel(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertStringContainsString(
            "define('TP_PW_COMPLEXITY_UNASSESSABLE', -2);",
            (string) file_get_contents($root . '/app/config/include.php')
        );

        $writers = [
            $root . '/app/scripts/background_tasks___do_calculation.php',
            $root . '/app/sources/dashboard.queries.php',
            $root . '/app/sources/items.queries.php',
        ];

        foreach ($writers as $writer) {
            self::assertStringContainsString(
                "\$metadataUpdates['complexity_level'] = TP_PW_COMPLEXITY_UNASSESSABLE;",
                (string) file_get_contents($writer),
                $writer
            );
        }

        // The sentinel must still read as "unassessed" everywhere, so the item is never
        // presented as healthy or weak on the strength of metadata that could not be computed.
        self::assertSame(
            'unassessed',
            securityPasswordHealthClassify(-2, 24, true, (int) 38, 12)
        );
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
