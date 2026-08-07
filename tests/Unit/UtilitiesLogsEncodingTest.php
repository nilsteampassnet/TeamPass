<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free), included by app/sources/main.functions.php as well.
require_once __DIR__ . '/../../app/sources/log_display_logic.php';

/**
 * Regression guards for legacy HTML entities displayed on utilities.logs.
 *
 * The encoding contract is exercised on the production function itself, so a change in its
 * semantics fails here — not only a change in its source text.
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

    // ------------------------------------------------------------- behaviour

    public function testLegacyAccentsAreReducedToAtMostOneEntityLayer(): void
    {
        self::assertSame('Clémence', normalizeLogDisplayValue('Cl&eacute;mence'));
        self::assertSame('Cl&eacute;mence', normalizeLogDisplayValue('Cl&amp;eacute;mence'));
        self::assertSame('Code accès', normalizeLogDisplayValue('Code acc&egrave;s'));
        self::assertSame('François', normalizeLogDisplayValue('Fran&#231;ois'));
        self::assertSame('R&amp;D', normalizeLogDisplayValue('R&D'));
    }

    public function testRawUtf8AndEmptyValuesStayUsable(): void
    {
        self::assertSame('Clémence', normalizeLogDisplayValue('Clémence'));
        self::assertSame('', normalizeLogDisplayValue(''));
        self::assertSame('', normalizeLogDisplayValue(null));
        self::assertSame('42', normalizeLogDisplayValue(42));
    }

    public function testNormalizationKeepsMarkupInertForTheClientRenderer(): void
    {
        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            normalizeLogDisplayValue('<img src=x onerror=alert(1)>')
        );
        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            normalizeLogDisplayValue('&lt;img src=x onerror=alert(1)&gt;')
        );
        self::assertSame(
            '&lt;img src=x onerror=alert(1)&gt;',
            normalizeLogDisplayValue('&amp;lt;img src=x onerror=alert(1)&amp;gt;')
        );
    }

    /**
     * The security invariant behind double_encode = false: only the '&' handling is relaxed, so no
     * markup delimiter and no quote can ever reach the page — including inside an HTML attribute.
     */
    public function testNoMarkupDelimiterNorQuoteEverSurvivesNormalization(): void
    {
        foreach (
            [
                '<script>alert(1)</script>',
                '&lt;script&gt;alert(1)&lt;/script&gt;',
                '&amp;lt;script&amp;gt;',
                '" onmouseover="alert(1)',
                '&quot; onmouseover=&quot;alert(1)',
                '&amp;quot; onmouseover=&amp;quot;alert(1)',
                "' onfocus='alert(1)",
                '&#39; onfocus=&#39;alert(1)',
                '&apos; onfocus=&apos;alert(1)',
            ] as $payload
        ) {
            $normalized = normalizeLogDisplayValue($payload);

            foreach (['<', '>', '"', "'"] as $delimiter) {
                self::assertStringNotContainsString(
                    $delimiter,
                    $normalized,
                    "Normalizing '{$payload}' must not emit a raw {$delimiter}."
                );
            }
        }
    }

    public function testNormalizationIsStableOnAnAlreadyNormalizedValue(): void
    {
        // Some display values are built from already normalized parts; a second pass must not
        // consume another entity layer.
        foreach (['Clémence', 'R&amp;D', '&lt;b&gt;', 'plain login'] as $value) {
            $once = normalizeLogDisplayValue($value);
            self::assertSame($once, normalizeLogDisplayValue($once));
        }
    }

    // ---------------------------------------------------------------- wiring

    public function testTheSharedHelperIsWiredIntoProduction(): void
    {
        self::assertStringContainsString(
            "require_once __DIR__ . '/log_display_logic.php';",
            $this->source('app/sources/main.functions.php'),
            'The log data sources reach normalizeLogDisplayValue() through main.functions.php.'
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

        foreach (['label', 'user_login', 'action', 'reason'] as $field) {
            self::assertStringContainsString(
                "normalizeLogDisplayValue(\$row['{$field}'] ?? '')",
                $knowledgeBase
            );
        }
    }

    // ----------------------------------------------------------- client side

    public function testClientDecodesThenEscapesEveryTextRenderer(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertGreaterThanOrEqual(
            7,
            substr_count($javascript, "return $('<div/>').text(decodeHtmlEntities(data)).html();")
        );
        self::assertStringNotContainsString('return decodeHtmlEntities(data);', $javascript);
    }

    /**
     * text().html() leaves quotes untouched, so an attribute needs its own escaper.
     */
    public function testClientUsesADedicatedEscaperForAttributeContexts(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertStringContainsString('function escapeAuthenticationLockoutAttribute(value) {', $javascript);
        self::assertStringContainsString('.replace(/"/g, \'&quot;\')', $javascript);
        self::assertStringContainsString('.replace(/\'/g, \'&#39;\')', $javascript);

        // Every title="..." built by the lockout tab goes through the attribute escaper.
        preg_match_all('/title="\'\s*\+\s*(escapeAuthenticationLockout\w+)\(/', $javascript, $matches);

        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $helper) {
            self::assertSame('escapeAuthenticationLockoutAttribute', $helper);
        }
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

    /**
     * The identifier column must display exactly what the unlock action targets: decoding it
     * client-side would show a value that differs from the stored login or IP.
     */
    public function testAuthenticationLockoutIdentifierIsDisplayedWithoutEntityDecoding(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertMatchesRegularExpression(
            "/'data': 'value',\s*'render': function\(data, type\) \{\s*"
            . "return type === 'display' \? escapeAuthenticationLockoutValue\(data\) : data;/",
            $javascript,
            'The raw lockout identifier must not go through the decoding renderer.'
        );
        self::assertStringContainsString(
            'renderAuthenticationLockoutDisplayValue(data)',
            $javascript,
            'The server-normalized user_display column keeps the decode-then-escape renderer.'
        );
    }
}
