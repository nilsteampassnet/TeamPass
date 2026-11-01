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
            self::assertSame($stored, $node->title, 'Rendering must not decode the shared snapshot in place');
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

    /** Reusing the snapshot for dropdowns must not decode literal entities a second time. */
    public function testTreeAndDropdownPathsEachDecodeOneLayer(): void
    {
        $parent = (object) ['id' => 7, 'parent_id' => '0', 'nlevel' => 2, 'title' => '&amp;lt;Lab O&apos;Brien&amp;gt;',
            'personal_folder' => 1, 'fa_icon' => 'fas fa-folder', 'fa_icon_selected' => 'fas fa-folder-open', 'children' => []];
        $child = (object) ['id' => 8, 'parent_id' => '7', 'nlevel' => 3, 'title' => 'R&amp;D', 'personal_folder' => 1];
        $nodes = [7 => $parent, 8 => $child];
        $input = ['userId' => 99, 'userLogin' => 'test.user', 'visibleFolders' => [7, 8], 'readOnlyFolders' => [],
            'personalVisibleFolders' => [], 'personalFolders' => [7, 8], 'userReadOnly' => false,
            'restrictedFoldersForItems' => [], 'userCanCreateRootFolder' => 0, 'userTreeLoadStrategy' => 'full'];
        $json = [];
        handleNode(7, $parent, $nodes, [], -1, 1, [], $input, '', 0, 1, 0, $json);
        $json[] = ['id' => 'li_8'];
        $folders = buildVisibleFoldersFromTree($json, $nodes, $input);
        self::assertSame("&lt;Lab O'Brien&gt;", $folders[0]['title']);
        self::assertSame('R&D', $folders[1]['title']);
        self::assertSame("&lt;Lab O'Brien&gt;", html_entity_decode($folders[1]['path'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Personal roots use the login; encoded logins remain text in both cache producers. */
    public static function personalLogins(): array
    {
        return [
            ['jdoe', 'jdoe'],
            ['O&apos;Brien', "O'Brien"],
            ['R&amp;D', 'R&D'],
            ['&quot;Ops&quot; &lt;Guide&gt;', '"Ops" <Guide>'],
            ['&amp;lt;literal&amp;gt;', '&lt;literal&gt;'],
            ['&lt;img src=x onerror=alert(1)&gt;', '<img src=x onerror=alert(1)>'],
        ];
    }

    /** Exercise both production path loops with a level-one personal root, without a database. */
    #[DataProvider('personalLogins')]
    public function testPersonalRootLoginIsUsedByBothVisibleFolderProducers(string $login, string $displayed): void
    {
        $root = (object) ['id' => 7, 'parent_id' => '0', 'nlevel' => 1, 'title' => '99',
            'personal_folder' => 1, 'fa_icon' => 'fas fa-folder', 'fa_icon_selected' => 'fas fa-folder-open', 'children' => []];
        $nodes = [
            7 => $root,
            8 => (object) ['id' => 8, 'parent_id' => '7', 'nlevel' => 2, 'title' => 'Sub &amp; Co', 'personal_folder' => 1],
            9 => (object) ['id' => 9, 'parent_id' => '8', 'nlevel' => 3, 'title' => 'Nested', 'personal_folder' => 1],
            10 => (object) ['id' => 10, 'parent_id' => '0', 'nlevel' => 1, 'title' => 'Other &amp; Co', 'personal_folder' => 0],
            11 => (object) ['id' => 11, 'parent_id' => '10', 'nlevel' => 2, 'title' => '99', 'personal_folder' => 0],
            12 => (object) ['id' => 12, 'parent_id' => '11', 'nlevel' => 3, 'title' => 'Shared', 'personal_folder' => 0],
        ];
        $input = ['userId' => 99, 'userLogin' => $login, 'visibleFolders' => array_keys($nodes), 'readOnlyFolders' => [],
            'personalVisibleFolders' => [], 'personalFolders' => [7, 8, 9], 'userReadOnly' => false,
            'restrictedFoldersForItems' => [], 'userCanCreateRootFolder' => 0, 'userTreeLoadStrategy' => 'full'];
        $json = [];
        handleNode(7, $root, $nodes, [], -1, 1, [], $input, '', 0, 1, 0, $json);
        foreach ([8, 9, 10, 11, 12] as $id) {
            $json[] = ['id' => 'li_' . $id];
        }
        $treeFolders = array_column(buildVisibleFoldersFromTree($json, $nodes, $input), null, 'id');
        self::assertSame('99', $root->title, 'The shared tree snapshot must retain the stored root ID');
        self::assertSame($displayed, $treeFolders[7]['title']);

        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        $case = strpos($source, "case 'refresh_visible_folders':");
        self::assertNotFalse($case);
        $start = strpos($source, 'foreach ($folders as $folder) {', $case);
        self::assertNotFalse($start);
        $end = strpos($source, 'if (empty($arrayFolders) === false)', $start);
        self::assertNotFalse($end);
        $session = new class ($login) {
            /** Keep the test login separate from the immutable numeric account ID. */
            public function __construct(private string $login)
            {
            }

            /** Supply only the identity used by the shipped path builder. */
            public function get(string $key): int|string
            {
                return $key === 'user-id' ? 99 : $this->login;
            }
        };
        $folders = array_values($nodes);
        $foldersById = $nodes;
        $accessibleFoldersSet = $displayableFolders = array_fill_keys(array_keys($nodes), true);
        $forbidenPfSet = $readOnlyFoldersSet = $arrayFolders = [];
        eval(substr($source, $start, $end - $start));
        $dropdownFolders = array_column($arrayFolders, null, 'id');
        $expectedPaths = [8 => $displayed, 9 => $displayed . ' / Sub & Co', 12 => 'Other & Co / 99'];
        foreach ($expectedPaths as $id => $expected) {
            self::assertSame($expected, html_entity_decode($treeFolders[$id]['path'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            // The dropdown endpoint retains its existing extra transport-escaping layer.
            $dropdownPath = html_entity_decode($dropdownFolders[$id]['path'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            self::assertSame($expected, html_entity_decode($dropdownPath, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            self::assertStringNotContainsString('<img', $treeFolders[$id]['path']);
            self::assertStringNotContainsString('<img', $dropdownFolders[$id]['path']);
        }
        self::assertSame('99', $root->title);
    }
}
