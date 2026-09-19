<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

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

        // The four log_system views and the item view are served by one merged branch now; the
        // option lists it also serves feed the facet pickers and are normalized the same way.
        foreach (
            [
                ['logs', 'user_options'],
                ['user_options', 'folder_options'],
                ['folder_options', 'authentication_lockouts'],
                ['authentication_lockouts', 'items_in_edition'],
            ] as [$action, $nextAction]
        ) {
            self::assertStringContainsString(
                'normalizeLogDisplayValue(',
                $this->dataTableBranch($dataTable, $action, $nextAction),
                "The {$action} log source must normalize its database-backed display text."
            );
        }

        foreach (['label', 'user_display', 'action_display', 'reason_display'] as $field) {
            self::assertStringContainsString(
                "normalizeLogDisplayValue(\$row['{$field}'] ?? '')",
                $knowledgeBase
            );
        }
        self::assertStringContainsString('formatKnowledgeBaseLogRow(', $knowledgeBase);
        self::assertStringContainsString('knowledgeBaseLogRowMatchesSearch(', $knowledgeBase);
    }

    /** Test the display/search helpers directly with current catalog wording. */
    public function testKnowledgeBaseLogsTranslateAndSearchDisplayedValuesWhileKeepingTextSafe(): void
    {
        $rows = [
            ['date' => 100, 'label' => 'Guide', 'user_id' => 42, 'user_login' => 'clem', 'action' => 'at_shown', 'reason' => ''],
            ['date' => 101, 'label' => 'Guide', 'user_id' => 43, 'user_login' => 'old-login', 'action' => 'at_modification', 'reason' => 'label, allow_comments, associated_items'],
            ['date' => 102, 'label' => '<b>Title</b>', 'user_id' => 44, 'user_login' => '<b>login</b>', 'action' => 'legacy_action', 'reason' => '<img src=x onerror=alert(1)>'],
        ];
        $users = [42 => ['name' => 'Clémence', 'lastname' => 'Dupont', 'login' => 'clem']];
        foreach (['french', 'english'] as $language) {
            $lang = new Language($language, __DIR__ . '/../../app/includes/language');
            $data = array_map(static fn (array $row): array => formatKnowledgeBaseLogRow($row, $users[$row['user_id']] ?? [], $lang), $rows);
            self::assertSame('Clémence Dupont [clem]', $data[0]['user_display']);
            self::assertSame($lang->get('at_shown'), $data[0]['action_display']);
            self::assertSame('old-login', $data[1]['user_display']);
            self::assertSame($lang->get('at_modification'), $data[1]['action_display']);
            self::assertSame('at_modification', $data[1]['action']);
            self::assertSame(
                implode(', ', [$lang->get('label'), $lang->get('kb_allow_comments'), $lang->get('kb_associated_items')]),
                $data[1]['reason_display']
            );
            self::assertSame('&lt;b&gt;login&lt;/b&gt;', normalizeLogDisplayValue($data[2]['user_display']));
            self::assertSame('legacy_action', $data[2]['action_display']);
            self::assertSame('&lt;img src=x onerror=alert(1)&gt;', normalizeLogDisplayValue($data[2]['reason_display']));
            foreach (['clémence', 'Dupont', (string) $lang->get('at_shown')] as $search) {
                self::assertTrue(knowledgeBaseLogRowMatchesSearch($data[0], $search));
                self::assertFalse(knowledgeBaseLogRowMatchesSearch($data[1], $search));
            }
            self::assertTrue(knowledgeBaseLogRowMatchesSearch($data[1], (string) $lang->get('kb_associated_items')));
            self::assertTrue(knowledgeBaseLogRowMatchesSearch($data[0], ''));
            self::assertFalse(knowledgeBaseLogRowMatchesSearch($data[0], 'no-such-log-value'));
        }
    }

    // ----------------------------------------------------------- client side

    public function testClientDecodesThenEscapesEveryTextRenderer(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        // One renderer for every text column, instead of the same two lines repeated per table.
        self::assertStringContainsString(
            "return escapeLogValue(decodeHtmlEntities(value));",
            $javascript
        );
        // Any column without a dedicated renderer falls back to it, so none is left raw.
        self::assertStringContainsString(
            'return logCellRenderers[key] ? logCellRenderers[key](row) : renderLogText(data)',
            $javascript
        );
        self::assertStringNotContainsString('return decodeHtmlEntities(data);', $javascript);
    }

    /**
     * text().html() leaves quotes untouched, so an attribute needs its own escaper.
     */
    public function testClientUsesADedicatedEscaperForAttributeContexts(): void
    {
        $javascript = $this->source('app/pages/utilities.logs.js.php');

        self::assertStringContainsString('function escapeLogAttribute(value) {', $javascript);
        self::assertStringContainsString('.replace(/"/g, \'&quot;\')', $javascript);
        self::assertStringContainsString('.replace(/\'/g, \'&#39;\')', $javascript);

        // Every title="..." built by the lockout tab goes through the attribute escaper.
        preg_match_all('/title="\'\s*\+\s*(escapeLog\w+)\(/', $javascript, $matches);

        self::assertNotEmpty($matches[1]);
        foreach ($matches[1] as $helper) {
            self::assertSame('escapeLogAttribute', $helper);
        }
    }

    public function testAuthenticationLockoutActionKeepsItsRawDatabaseIdentifier(): void
    {
        $dataTable = $this->source('app/sources/logs.datatables.php');
        $branchStart = strpos($dataTable, "\$params['action'] === 'authentication_lockouts'");
        $nextBranch = strpos($dataTable, "\$params['action'] === 'items_in_edition'", (int) $branchStart);

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
            . "return type === 'display' \? escapeLogValue\(data\) : data;/",
            $javascript,
            'The raw lockout identifier must not go through the decoding renderer.'
        );
        self::assertStringContainsString(
            'renderLogText(data)',
            $javascript,
            'The server-normalized user_display column keeps the decode-then-escape renderer.'
        );
    }
}
