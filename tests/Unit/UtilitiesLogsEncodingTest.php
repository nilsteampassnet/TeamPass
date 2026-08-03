<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for legacy HTML entities displayed on utilities.logs.
 */
class UtilitiesLogsEncodingTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function dataTableBranch(string $source, string $action, string $nextAction): string
    {
        $branchStart = strpos($source, "\$params['action'] === '{$action}'");
        $nextBranch = strpos($source, "\$params['action'] === '{$nextAction}'", (int) $branchStart);

        self::assertIsInt($branchStart);
        self::assertIsInt($nextBranch);

        return substr($source, $branchStart, $nextBranch - $branchStart);
    }

    /**
     * Mirror the pure production transformation so the expected entity contract is explicit.
     */
    private function normalizeLogDisplayValue(mixed $value): string
    {
        $decodedValue = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($decodedValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }

    public function testLegacyAccentsAreReducedToAtMostOneEntityLayer(): void
    {
        self::assertSame('Clémence', $this->normalizeLogDisplayValue('Cl&eacute;mence'));
        self::assertSame('Cl&eacute;mence', $this->normalizeLogDisplayValue('Cl&amp;eacute;mence'));
        self::assertSame('Code accès', $this->normalizeLogDisplayValue('Code acc&egrave;s'));
        self::assertSame('François', $this->normalizeLogDisplayValue('Fran&#231;ois'));
        self::assertSame('R&amp;D', $this->normalizeLogDisplayValue('R&D'));
    }

    public function testNormalizationKeepsMarkupInertForTheClientRenderer(): void
    {
        $payload = '<img src=x onerror=alert(1)>';

        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            $this->normalizeLogDisplayValue($payload)
        );
        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            $this->normalizeLogDisplayValue('&lt;img src=x onerror=alert(1)&gt;')
        );
        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            $this->normalizeLogDisplayValue('&amp;lt;img src=x onerror=alert(1)&amp;gt;')
        );
    }

    public function testProductionHelperUsesTheAuditedDecodeThenEscapeContract(): void
    {
        $functions = $this->source('app/sources/main.functions.php');

        self::assertStringContainsString('function normalizeLogDisplayValue(mixed $value): string', $functions);
        self::assertStringContainsString(
            "html_entity_decode((string) \$value, ENT_QUOTES | ENT_HTML5, 'UTF-8')",
            $functions
        );
        self::assertStringContainsString(
            "htmlspecialchars(\$decodedValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false)",
            $functions
        );
    }

    public function testAllUtilitiesLogDataSourcesNormalizeDatabaseBackedText(): void
    {
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $knowledgeBase = $this->source('app/sources/kb.queries.php');

        foreach (
            [
                ['connections', 'access'],
                ['access', 'copy'],
                ['copy', 'admin'],
                ['admin', 'items'],
                ['items', 'authentication_lockouts'],
                ['authentication_lockouts', 'failed_auth'],
                ['failed_auth', 'errors'],
                ['errors', 'items_in_edition'],
            ] as [$action, $nextAction]
        ) {
            self::assertStringContainsString(
                'normalizeLogDisplayValue(',
                $this->dataTableBranch($dataTable, $action, $nextAction),
                "The {$action} log source must normalize its database-backed display text."
            );
        }

        foreach (
            [
                "normalizeLogDisplayValue(\$record['name'] ?? '')",
                "normalizeLogDisplayValue(\$record['lastname'] ?? '')",
                "normalizeLogDisplayValue(\$record['login'] ?? '')",
                "normalizeLogDisplayValue(trim((string) \$record['label']))",
                "normalizeLogDisplayValue(trim((string) \$record['folder']))",
                'normalizeLogDisplayValue($failedLoginUser)',
                'normalizeLogDisplayValue($userDisplay)',
            ] as $expectedCall
        ) {
            self::assertStringContainsString($expectedCall, $dataTable);
        }

        foreach (['label', 'user_login', 'action', 'reason'] as $field) {
            self::assertStringContainsString(
                "normalizeLogDisplayValue(\$row['{$field}'] ?? '')",
                $knowledgeBase
            );
        }
    }

    public function testClientDecodesThenEscapesEveryTextRenderer(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');
        $safeRenderer = "return $('<div/>').text(decodeHtmlEntities(data)).html();";

        self::assertGreaterThanOrEqual(7, substr_count($javascript, $safeRenderer));
        self::assertStringContainsString('const normalizedValue = decodeHtmlEntities(', $javascript);
        self::assertStringContainsString("return $('<div/>').text(normalizedValue).html();", $javascript);
        self::assertStringNotContainsString('return decodeHtmlEntities(data);', $javascript);
    }

    public function testAuthenticationLockoutActionKeepsItsRawDatabaseIdentifier(): void
    {
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $branchStart = strpos($dataTable, "\$params['action'] === 'authentication_lockouts'");
        $nextBranch = strpos($dataTable, '/* FAILED AUTHENTICATION */', (int) $branchStart);

        self::assertIsInt($branchStart);
        self::assertIsInt($nextBranch);
        $branch = substr($dataTable, $branchStart, $nextBranch - $branchStart);

        self::assertStringContainsString("'value' => (string) (\$lockoutRow['value'] ?? '')", $branch);
        self::assertStringNotContainsString("normalizeLogDisplayValue(\$lockoutRow['value']", $branch);
    }
}
