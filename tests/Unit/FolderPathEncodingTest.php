<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute the shipped folder-title transformations without decrypting any item. */
class FolderPathEncodingTest extends TestCase
{
    /** Cover legacy and HTML5 names, literal entities, and text resembling markup. */
    public static function names(): array
    {
        return [
            ['Lab O&apos;Brien', "Lab O'Brien"],
            ['Lab O&#039;Brien', "Lab O'Brien"],
            ['R&amp;D', 'R&D'],
            ['&quot;Ops&quot; &lt;Guide&gt;', '"Ops" <Guide>'],
            ['S&eacute;curit&eacute;', 'Sécurité'],
            ['&amp;lt;literal&amp;gt;', '&lt;literal&gt;'],
            ['&lt;img src=x onerror=alert(1)&gt;', '<img src=x onerror=alert(1)>'],
        ];
    }

    /** Paths used by notifications, breadcrumbs, dropdowns and the API stay escaped. */
    #[DataProvider('names')]
    public function testFolderPathTitlesAreDecodedBeforeEscaping(string $stored, string $displayed): void
    {
        $elem = (object) ['title' => $stored];
        $currentId = 7;
        $foldersById = [7 => $elem];
        foreach (['app/sources/main.functions.php', 'app/sources/items.queries.php', 'app/api/Model/ItemModel.php'] as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../../' . $file);
            preg_match_all('/htmlspecialchars\(stripslashes\(html(?:specialchars_decode|_entity_decode)\((?:\$elem->title|\$foldersById\[\$currentId\]->title),[^;\r\n]*?\)\), ENT_QUOTES\)/', $source, $matches);
            self::assertNotEmpty($matches[0], $file);
            foreach ($matches[0] as $expression) {
                $html = eval('return ' . $expression . ';');
                self::assertSame($displayed, html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $file);
                self::assertStringNotContainsString('<img', $html, $file);
            }
        }
    }

    /** Importing an HTML5-encoded title must not store another entity layer. */
    #[DataProvider('names')]
    public function testImportedFolderNamesAreNormalizedOnce(string $folderTitle, string $displayed): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/import.queries.php'));
        $function = strpos($source, 'function createFolder(');
        self::assertNotFalse($function);
        $start = strpos($source, '$folderTitle = htmlspecialchars(', $function);
        self::assertNotFalse($start);
        $end = strpos($source, "\n    );", $start);
        self::assertNotFalse($end);
        eval(substr($source, $start, $end + strlen("\n    );") - $start));
        self::assertSame($displayed, html_entity_decode($folderTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        self::assertStringNotContainsString('<img', $folderTitle);
    }
}
