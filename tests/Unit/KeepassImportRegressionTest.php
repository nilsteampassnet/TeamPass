<?php

declare(strict_types=1);

namespace KeepassImportRegressionTest;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the KeePass XML import (keepass_create_items).
 *
 * - An entry exported without a Title string reached stripslashes(null) when the
 *   result list was built: a TypeError under strict_types aborted the whole import.
 * - The items were sanitized with FILTER_SANITIZE_FULL_SPECIAL_CHARS before the
 *   password was encrypted, so "a&b" was stored as "a&amp;b". The CSV import already
 *   kept the raw password; the KeePass import now does the same.
 *
 * The handler needs a session and a database, so the production sections are
 * extracted and evaluated in isolation.
 */
class KeepassImportRegressionTest extends TestCase
{
    private static function source(): string
    {
        $source = file_get_contents(__DIR__ . '/../../app/sources/import.queries.php');
        self::assertIsString($source);

        return str_replace("\r\n", "\n", $source);
    }

    private static function between(string $source, string $start, string $end): string
    {
        $offset = strpos($source, $start);
        self::assertNotFalse($offset, 'Production section not found: ' . $start);
        $limit = strpos($source, $end, $offset + strlen($start));
        self::assertNotFalse($limit, 'Production section end not found: ' . $end);

        return substr($source, $offset, $limit - $offset);
    }

    private static function createItemsBlock(): string
    {
        return self::between(self::source(), "case 'keepass_create_items':", "case 'keepass_finalize':");
    }

    public function testEntryWithoutTitleOrUserNameGetsEmptyDefaults(): void
    {
        if (function_exists(__NAMESPACE__ . '\\buildItemDefinition') === false) {
            eval('namespace ' . __NAMESPACE__ . '; ' . self::between(
                self::source(),
                'function buildItemDefinition(',
                '/**'
            ));
        }

        $item = buildItemDefinition([['Key' => 'Password', 'Value' => 'secret']], 7);

        self::assertSame('', $item['Title']);
        self::assertSame('', $item['UserName']);
        self::assertSame('secret', $item['Password']);
        self::assertSame(7, $item['parentFolderId']);
    }

    public function testRawPasswordsSurviveTheHtmlSanitization(): void
    {
        $capture = self::between(
            self::createItemsBlock(),
            '// Keep the raw passwords before the HTML sanitization',
            '$post_folders = filter_var_array('
        );
        $receivedParameters = [
            'items' => [
                ['Title' => 'A', 'Password' => 'a&b<"x\'y'],
                ['Title' => 'B'],
                ['Title' => 'C', 'Password' => ['unexpected']],
                'not-an-item',
            ],
        ];

        $rawPasswords = (static function (array $receivedParameters, string $capture): array {
            eval($capture);

            return $rawPasswords;
        })($receivedParameters, $capture);

        self::assertSame(['a&b<"x\'y', '', '', ''], $rawPasswords);
    }

    public function testEncryptionUsesTheRawPassword(): void
    {
        $block = self::createItemsBlock();

        self::assertStringContainsString('foreach($post_items as $itemKey => $item)', $block);
        self::assertStringContainsString('$itemPassword = $rawPasswords[$itemKey] ?? \'\';', $block);
        self::assertStringContainsString('doDataEncryption($itemPassword)', $block);
        self::assertStringNotContainsString("\$item['Password']", $block);
    }

    public function testEveryTitleReadIsGuarded(): void
    {
        self::assertDoesNotMatchRegularExpression(
            "/\\\$item\\['Title'\\](?!\\s*\\?\\?)/",
            self::createItemsBlock(),
            'A KeePass entry may have no Title: every read must fall back to an empty string.'
        );
    }

    public function testReturnedTitleIsDecodedAndNeverNull(): void
    {
        $block = self::createItemsBlock();
        $offset = strpos($block, "'title' =>");
        self::assertNotFalse($offset);
        $line = substr($block, $offset, strpos($block, "\n", $offset) - $offset);

        $title = static fn (array $item): string => eval('return [' . $line . "]['title'];");

        self::assertSame('', $title([]));
        self::assertSame('Tom & "Jerry" \\ backup', $title(['Title' => 'Tom &amp; &quot;Jerry&quot; \\ backup']));
    }
}
