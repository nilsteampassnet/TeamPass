<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel tests for warning/danger rendering of corrupted items.
 */
class CorruptedItemSeverityRegressionTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        $content = file_get_contents($path);
        self::assertNotFalse($content, $path . ' must be readable');

        return (string) $content;
    }

    public function testBackendReturnsCorruptionSeverityAndReasonLabel(): void
    {
        $source = self::source('app/sources/items.queries.php');

        $this->assertStringContainsString(
            "['corruption_severity'] = \$corruptedState['severity'] ?? '';",
            $source
        );
        $this->assertStringContainsString(
            "['corruption_reason_label'] = \$corruptedState !== null",
            $source
        );
    }

    public function testExceptionReasonIsNotLabelledInItemsList(): void
    {
        $source = self::source('app/sources/items.queries.php');

        // The exception label would expose the raw scanner exception message to
        // non-admin users, so the item list must leave the label empty and let
        // the renderer fall back to the generic message.
        $this->assertStringContainsString(
            "&& \$corruptedState['reason'] !== 'exception'",
            $source
        );
    }

    public function testItemsListNeverSelectsExceptionMessage(): void
    {
        $source = self::source('app/sources/items.queries.php');

        $this->assertStringContainsString(
            'SELECT item_id, reason_code, severity',
            $source
        );
        $this->assertStringNotContainsString(
            'SELECT item_id, reason_code, severity, exception_message',
            $source
        );
    }

    public function testRendererFallsBackWhenReasonLabelIsEmpty(): void
    {
        $source = self::source('app/pages/items.js.php');

        $this->assertStringContainsString(
            'const corruptionLabel = value.corruption_reason_label ||',
            $source
        );
    }

    public function testRendererWhitelistsWarningAndFallsBackToDanger(): void
    {
        $source = self::source('app/pages/items.js.php');

        $this->assertStringContainsString(
            "const corruptionSeverity = value.corruption_severity === 'warning' ? 'warning' : 'danger';",
            $source
        );
        $this->assertStringContainsString(
            "corruption_row_class = ' tp-item-corrupted-' + corruptionSeverity;",
            $source
        );
        $this->assertStringContainsString(
            "tp-item-corrupted-marker text-' + corruptionSeverity",
            $source
        );
        $this->assertStringNotContainsString(
            "corruption_row_class = ' tp-item-corrupted-danger';",
            $source
        );
    }

    public function testStylesExistForBothSupportedSeverities(): void
    {
        $source = self::source('public/assets/css/teampass.css');

        $this->assertStringContainsString('tr.tp-item-corrupted-warning', $source);
        $this->assertStringContainsString('tr.tp-item-corrupted-danger', $source);
    }
}
