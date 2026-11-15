<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net.
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TreeCountersRuntime;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/folder_cache_logic.php';

/** Provide a language preference without opening a real user session. */
class SessionManager
{
    public static string $language = 'english';

    /** Return the isolated session interface used by the renderer. */
    public static function getSession(): object
    {
        return new class {
            /** Supply the selected test language. */
            public function get(string $key): string
            {
                return SessionManager::$language;
            }
        };
    }
}

/** Keep translated access hints independent of the application's session. */
class Language
{
    /** Accept the requested language. */
    public function __construct(private string $language)
    {
    }

    /** Return an inert translated hint. */
    public function get(string $key): string
    {
        return $this->language . ':' . $key;
    }
}

/** Feed one synthetic cache row to the production cache reader. */
class DB
{
    public static array $row = [];

    /** Return the current test snapshot without connecting to a database. */
    public static function queryFirstRow(string $query, int $userId): array
    {
        return self::$row;
    }
}

/** Keep the cache reader independent of installation settings. */
function prefixTable(string $table): string
{
    return 'fixture_' . $table;
}

/** Supply the optional folder-visibility setting used by the renderer. */
function isKeyExistingAndEqual(string $key, int $value, array $settings): bool
{
    return isset($settings[$key]) && (int) $settings[$key] === $value;
}

/** Exercise the shipped renderer and cache decisions rather than copied logic. */
class TreeCountersTest extends TestCase
{
    /** Load only function declarations, bypassing HTTP and database setup. */
    public static function setUpBeforeClass(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/tree.php');
        $start = strpos($source, 'function getDescendantNodesFromTree(');
        self::assertNotFalse($start);
        eval('namespace ' . __NAMESPACE__ . '; use stdClass;' . substr($source, $start));
    }

    /** Cover both setting values, languages and folder-access presentation branches. */
    public static function nodeContexts(): array
    {
        $cases = [];
        foreach (['english', 'french'] as $language) {
            foreach ([0, 1] as $enabled) {
                foreach (['standard', 'read_only_folder', 'read_only_user', 'personal', 'restricted_items', 'restricted_read_only'] as $context) {
                    $cases[$language . '/' . $enabled . '/' . $context] = [$language, $enabled, $context];
                }
            }
        }
        return $cases;
    }

    /** Disabled means no badge, including individual-item access and read-only hints. */
    #[DataProvider('nodeContexts')]
    public function testEveryNodeContextHonorsTheSetting(string $language, int $enabled, string $context): void
    {
        SessionManager::$language = $language;
        $restricted = str_starts_with($context, 'restricted_');
        $readOnly = in_array($context, ['read_only_user', 'restricted_read_only'], true);
        $node = prepareNodeData(7, $restricted ? [] : [7], $context === 'read_only_folder' ? [7] : [],
            $context === 'personal' ? [7] : [], 2, 5, 1, $enabled, $readOnly,
            $restricted ? [7] : [], $restricted ? [7 => [11, 12]] : [],
            $context === 'personal' ? [7] : [], []);
        self::assertSame($enabled === 1, str_contains($node['html'], 'items_count'));
        self::assertFalse($node['hide_node']);
        self::assertSame($context === 'personal' ? 1 : 0, $node['is_pf']);
        if ($context === 'read_only_folder' || $readOnly) {
            self::assertStringContainsString('fa-eye', $node['html']);
        }
        if ($enabled === 1) {
            self::assertStringContainsString($restricted ? '>2</span>' : '>2/5/1</span>', $node['html']);
        }
    }

    /** Even a blocked first node must stamp the snapshot with its presentation setting. */
    public function testRenderedSnapshotsCarryTheSettingWithoutChangingNamesOrAccess(): void
    {
        foreach ([false, true] as $enabled) {
            foreach ([false, true] as $blocked) {
                $node = (object) ['id' => 7, 'parent_id' => '0', 'nlevel' => 1, 'title' => 'R&amp;D O&#039;Brien &quot;test&quot; &lt;demo&gt;',
                    'fa_icon' => 'fas fa-folder', 'fa_icon_selected' => 'fas fa-folder-open', 'children' => []];
                $input = ['userId' => 99, 'userLogin' => 'fixture', 'visibleFolders' => $blocked ? [] : [7],
                    'readOnlyFolders' => [], 'personalVisibleFolders' => [], 'personalFolders' => [],
                    'userReadOnly' => false, 'restrictedFoldersForItems' => [], 'userCanCreateRootFolder' => 0,
                    'userTreeLoadStrategy' => 'full'];
                $rows = [];
                handleNode(7, $node, [7 => $node], [], -1, 1, ['tree_counters' => $enabled ? '1' : '0'], $input, '', 5, 1, 2, $rows);
                self::assertCount(1, $rows);
                self::assertSame($enabled, $rows[0]['data']['tree_counters']);
                $expectedLabel = 'R&D O\'Brien "test" <demo>' . ($enabled && !$blocked ? '2/5/1' : '');
                self::assertSame($expectedLabel, html_entity_decode(strip_tags($rows[0]['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                self::assertSame(!$blocked, isset($rows[0]['a_attr']));
                self::assertTrue(folderCacheTreeMatchesCounters(json_encode($rows), $enabled));
                self::assertFalse(folderCacheTreeMatchesCounters(json_encode($rows), !$enabled));
            }
        }
    }

    /** Both changes invalidate existing trees, even when no folder was modified. */
    public function testCacheRebuildsInBothDirectionsAndReusesMatchingSnapshots(): void
    {
        foreach ([false, true] as $cachedSetting) {
            $data = json_encode([['id' => 'li_7', 'data' => ['tree_counters' => $cachedSetting], 'text' => 'Folder']]);
            DB::$row = ['data' => $data, 'timestamp' => 100, 'invalidated_at' => 0];
            $cached = loadTreeStrategy(0, 99, 0, $cachedSetting);
            self::assertFalse($cached['state']);
            self::assertSame($data, $cached['data']);
            self::assertTrue(loadTreeStrategy(0, 99, 0, !$cachedSetting)['state']);
            self::assertTrue(loadTreeStrategy(0, 99, 1, $cachedSetting)['state']);
            self::assertTrue(loadTreeStrategy(101, 99, 0, $cachedSetting)['state']);
            DB::$row['invalidated_at'] = 101;
            self::assertTrue(loadTreeStrategy(0, 99, 0, $cachedSetting)['state']);
        }
    }

    /** Legacy, missing and corrupt cache data is rebuilt for either option value. */
    public function testOldCachesRecoverOnFirstLoadAfterUpgrade(): void
    {
        foreach (['', '[]', 'null', 'invalid', '[{"id":"li_7","text":"Folder"}]', '[{"data":{"tree_counters":"1"}}]'] as $data) {
            DB::$row = ['data' => $data, 'timestamp' => 100, 'invalidated_at' => 0];
            foreach ([false, true] as $enabled) {
                self::assertTrue(loadTreeStrategy(0, 99, 0, $enabled)['state'], $data);
            }
        }
    }

    /** HTTP adapters use fresh settings, never the client-supplied pagination snapshot. */
    public function testHttpAdaptersPassTheCurrentSetting(): void
    {
        $tree = (string) file_get_contents(__DIR__ . '/../../app/sources/tree.php');
        self::assertMatchesRegularExpression('/loadTreeStrategy\([^;]+\$SETTINGS\[\x27tree_counters\x27\]/s', $tree);
        $items = (string) file_get_contents(__DIR__ . '/../../app/sources/items.queries.php');
        self::assertMatchesRegularExpression('/\x27counter_full\x27 => \$counter_full,\s*\x27tree_counters\x27 => \(int\) \(\$SETTINGS\[\x27tree_counters\x27\] \?\? 0\) === 1 \? 1 : 0/', $items);
    }
}
