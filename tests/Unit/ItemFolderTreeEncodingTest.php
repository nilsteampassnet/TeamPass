<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace ItemFolderTreeEncodingRuntime;

use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Provide only the language preference; no real user session is opened. */
class SessionManager
{
    /** Return the minimal session interface used by the renderer. */
    public static function getSession(): object
    {
        return new class {
            /** Supply the fixed test language. */
            public function get(string $key): string
            {
                return 'english';
            }
        };
    }
}

/** Keep translated access hints deterministic without loading application state. */
class Language
{
    /** Accept the renderer's selected language without loading a catalogue. */
    public function __construct(string $language)
    {
    }

    /** Use the key as the translated access hint. */
    public function get(string $key): string
    {
        return $key;
    }
}

/** Support the renderer's optional setting without bootstrapping the application. */
function isKeyExistingAndEqual(string $key, int $value, array $settings): bool
{
    return isset($settings[$key]) && (int) $settings[$key] === $value;
}

/** Exercise the shipped tree renderer with stored names, not a copy of its decoder. */
class ItemFolderTreeEncodingTest extends TestCase
{
    /** Load only the rendering functions, bypassing HTTP/session/database setup. */
    public static function setUpBeforeClass(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/tree.php');
        $start = strpos($source, 'function getDescendantNodesFromTree(');
        self::assertNotFalse($start);
        eval('namespace ' . __NAMESPACE__ . '; use stdClass;' . substr($source, $start));
    }

    /** Include HTML5, legacy numeric entities, and names that must remain inert text. */
    public static function storedNames(): array
    {
        return [
            'HTML5 apostrophe' => ['Lab O&apos;Brien', "Lab O'Brien"],
            'legacy apostrophe' => ['Lab O&#039;Brien', "Lab O'Brien"],
            'raw apostrophe' => ["Lab O'Brien", "Lab O'Brien"],
            'ampersand' => ['R&amp;D', 'R&D'],
            'quotes and brackets' => ['Environnements &quot;test&quot; &lt;démo&gt;', 'Environnements "test" <démo>'],
            'named accent' => ['S&eacute;curit&eacute;', 'Sécurité'],
            'literal entity' => ['&amp;lt;literal&amp;gt;', '&lt;literal&gt;'],
            'encoded markup' => ['&lt;img src=x onerror=alert(1)&gt;', '<img src=x onerror=alert(1)>'],
        ];
    }

    /** Personal/shared and read-only/blocked nodes must all display the same safe name. */
    #[DataProvider('storedNames')]
    public function testTreeNamesRemainReadableAndEscaped(string $stored, string $displayed): void
    {
        foreach (['shared', 'personal', 'read-only', 'blocked'] as $context) {
            $node = (object) [
                'id' => 7, 'parent_id' => '0', 'nlevel' => 2, 'title' => $stored,
                'personal_folder' => $context === 'personal' ? 1 : 0,
                'fa_icon' => 'fas fa-folder', 'fa_icon_selected' => 'fas fa-folder-open',
                'children' => [],
            ];
            $input = [
                'userId' => 99, 'userLogin' => 'test.user', 'visibleFolders' => $context === 'blocked' ? [] : [7],
                'readOnlyFolders' => $context === 'read-only' ? [7] : [], 'personalVisibleFolders' => [],
                'personalFolders' => $context === 'personal' ? [7] : [], 'userReadOnly' => false,
                'restrictedFoldersForItems' => [], 'userCanCreateRootFolder' => 0,
                'userTreeLoadStrategy' => 'full',
            ];
            $json = [];
            handleNode(7, $node, [7 => $node], [], -1, 1, [], $input, '', 0, 1, 0, $json);
            self::assertCount(1, $json, $context);

            $document = new DOMDocument();
            $document->loadHTML('<?xml encoding="UTF-8"><div>' . $json[0]['text'] . '</div>');
            self::assertSame($displayed, $document->getElementsByTagName('div')->item(0)->textContent, $context);
            self::assertSame(0, $document->getElementsByTagName('img')->length, $context);
            self::assertSame(0, $document->getElementsByTagName('script')->length, $context);
            if ($context !== 'blocked') {
                self::assertSame($displayed, html_entity_decode($json[0]['a_attr']['data-title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
    }
}
