<?php

declare(strict_types=1);

namespace TeamPass\Tests\Renewal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Stubs/renewal_runtime.php';

/** Exercise the real page permission matrix, grant queries and renewal table SQL. */
class RenewalAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('sqlite3')) {
            self::markTestSkipped('The SQL-backed renewal tests require SQLite3 (enabled in CI).');
        }
        DB::$connection = new \SQLite3(':memory:');
        DB::$connection->enableExceptions(true);
        DB::$connection->createFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
        ConfigManager::$settings = [
            'activate_expiration' => 1, 'enable_pf_feature' => 1,
            'date_format' => 'Y-m-d', 'time_format' => 'H:i:s',
        ];
        DB::$connection->exec(<<<'SQL'
            CREATE TABLE renewal_users (id INTEGER PRIMARY KEY, login TEXT, key_tempo TEXT,
                admin INTEGER DEFAULT 0, gestionnaire INTEGER DEFAULT 0, can_manage_all_users INTEGER DEFAULT 0,
                can_manage_lapr INTEGER DEFAULT 0, deleted_at TEXT, personal_folder INTEGER DEFAULT 1);
            INSERT INTO renewal_users (id, login, key_tempo) VALUES (7, 'alice', 'test-key');
            CREATE TABLE renewal_users_groups (user_id INTEGER, group_id INTEGER);
            CREATE TABLE renewal_users_groups_forbidden (user_id INTEGER, group_id INTEGER);
            CREATE TABLE renewal_users_roles (user_id INTEGER, role_id INTEGER, source TEXT);
            CREATE TABLE renewal_roles_values (role_id INTEGER, folder_id INTEGER, type TEXT);
            CREATE TABLE renewal_restriction_to_roles (item_id INTEGER, role_id INTEGER);
            CREATE TABLE renewal_nested_tree (id INTEGER PRIMARY KEY, title TEXT, parent_id INTEGER,
                nleft INTEGER, nright INTEGER, nlevel INTEGER, personal_folder INTEGER, renewal_period INTEGER);
            INSERT INTO renewal_nested_tree VALUES
                (10, 'Hidden ancestor', 0, 1, 6, 1, 0, 1),
                (11, 'Shared <folder>', 10, 2, 3, 2, 0, 1),
                (12, 'Denied folder', 10, 4, 5, 2, 0, 1),
                (20, '7', 0, 7, 10, 1, 1, 1),
                (21, 'Own personal child', 20, 8, 9, 2, 0, 1),
                (30, '8', 0, 11, 14, 1, 1, 1),
                (31, 'Foreign personal child', 30, 12, 13, 2, 0, 1);
            CREATE TABLE renewal_items (id INTEGER PRIMARY KEY, label TEXT, id_tree INTEGER,
                created_at TEXT DEFAULT '1000', restricted_to TEXT DEFAULT '', inactif INTEGER DEFAULT 0, deleted_at TEXT);
            INSERT INTO renewal_items (id, label, id_tree, restricted_to) VALUES
                (1, 'Open <item>', 11, ''),
                (2, 'User restriction', 11, '8'),
                (3, 'Role restriction', 11, ''),
                (4, 'Named user', 11, '7'),
                (5, 'Own personal item', 21, ''),
                (6, 'Foreign personal item', 31, ''),
                (7, 'Denied item', 12, ''),
                (8, 'Ancestor item', 10, '');
            INSERT INTO renewal_restriction_to_roles VALUES (3, 3);
            INSERT INTO renewal_users_groups VALUES (7, 11), (7, 12), (7, 31);
            INSERT INTO renewal_users_groups_forbidden VALUES (7, 12);
            CREATE TABLE renewal_log_items (id_item INTEGER, date TEXT, action TEXT, raison TEXT);
            SQL);
    }

    /** Cover normal accounts and privileged combinations that must not bypass the admin exclusion. */
    public static function privilegeCases(): iterable
    {
        yield 'user' => [0, 0, 0, true];
        yield 'manager' => [0, 1, 0, true];
        yield 'HR' => [0, 0, 1, true];
        yield 'admin' => [1, 0, 0, false];
        yield 'admin and manager' => [1, 1, 0, false];
        yield 'admin and HR' => [1, 0, 1, false];
    }

    /** Evaluate the production class loaded from the autoloaded vendor copy. */
    #[DataProvider('privilegeCases')]
    public function testRenewalPageIsForNonAdminAccounts(int $admin, int $manager, int $hr, bool $allowed): void
    {
        DB::query('UPDATE renewal_users SET admin = %i, gestionnaire = %i, can_manage_all_users = %i', $admin, $manager, $hr);
        $class = newRequest() . '\\PerformChecks';
        $checks = new $class(['type' => ''], ['user_id' => 7, 'user_key' => 'test-key']);
        self::assertSame($allowed, $checks->userAccessPage('utilities.renewal'));
        self::assertSame($admin === 1, $checks->userAccessPage('reports'), 'Existing compliance report permissions are unchanged.');
        if ($admin === 1) {
            self::assertFalse($checks->userAccessPage('["admin","utilities.renewal"]'));
        }
    }

    /** An ordinary grant must never bypass a missing user, deleted account or invalid session key. */
    public function testInvalidAccountsCannotOpenRenewal(): void
    {
        $class = newRequest() . '\\PerformChecks';
        self::assertFalse((new $class(['type' => ''], ['user_id' => 7, 'user_key' => 'wrong']))->userAccessPage('utilities.renewal'));
        self::assertFalse((new $class(['type' => ''], ['user_id' => 99, 'user_key' => 'test-key']))->userAccessPage('utilities.renewal'));
        DB::query('UPDATE renewal_users SET deleted_at = %s', '2026-09-13');
        self::assertFalse((new $class(['type' => ''], ['user_id' => 7, 'user_key' => 'test-key']))->userAccessPage('utilities.renewal'));
    }

    /** Results, totals, search and pagination all share the same item authorization. */
    public function testRestrictedItemsAreAbsentFromResultsAndCounts(): void
    {
        $namespace = newRequest();
        $all = runTable($namespace, ['order' => [['column' => 0, 'dir' => 'asc']]]);
        self::assertSame(3, $all['recordsTotal']);
        self::assertSame(3, $all['recordsFiltered']);
        self::assertCount(3, $all['data']);
        self::assertStringNotContainsString('Hidden ancestor', json_encode($all));
        self::assertStringNotContainsString('Foreign personal', json_encode($all));
        self::assertStringContainsString('index.php?page=items&amp;group=11&amp;id=1', json_encode($all));
        self::assertStringContainsString('Open &lt;item&gt;', json_encode($all));
        self::assertStringContainsString('Shared &lt;folder&gt;', json_encode($all));
        $page = runTable($namespace, ['start' => 1, 'length' => 1]);
        self::assertCount(1, $page['data']);
        self::assertSame(3, $page['recordsTotal']);
        foreach (['User restriction', 'Role restriction', 'Foreign personal item', 'Denied item', 'Ancestor item', "' OR 1=1 --"] as $term) {
            $filtered = runTable($namespace, ['search' => ['value' => $term]]);
            self::assertSame(3, $filtered['recordsTotal']);
            self::assertSame(0, $filtered['recordsFiltered'], $term);
            self::assertSame([], $filtered['data'], $term);
        }
    }

    /** Role-granted read access includes read-only and non-editable folders. */
    #[DataProvider('readableRoleCases')]
    public function testReadableRoleGrantsAndItemRestrictions(string $type, string $source): void
    {
        DB::query('DELETE FROM renewal_users_groups');
        DB::query('INSERT INTO renewal_users_roles VALUES (7, 3, %s)', $source);
        DB::query('INSERT INTO renewal_roles_values VALUES (3, 11, %s)', $type);
        $result = runTable(newRequest());
        self::assertSame(4, $result['recordsTotal']);
        self::assertStringContainsString('Role restriction', json_encode($result));
        self::assertStringNotContainsString('User restriction', json_encode($result));
    }

    /** Every readable role type works for both manual and directory-assigned roles. */
    public static function readableRoleCases(): iterable
    {
        foreach (['W', 'ND', 'NE', 'NDNE', 'R'] as $type) {
            foreach (['manual', 'ad'] as $source) {
                yield $type . '-' . $source => [$type, $source];
            }
        }
    }

    /** Fresh requests must not trust folder or role grants left in an old session. */
    public function testRevokedGrantsTakeEffectOnTheNextRequest(): void
    {
        self::assertSame(3, runTable(newRequest())['recordsTotal']);
        DB::query('DELETE FROM renewal_users_groups');
        $result = runTable(newRequest(), [], ['user-accessible_folders' => [10, 11, 12, 31], 'user-roles' => '3']);
        self::assertSame(1, $result['recordsTotal']);
        self::assertStringContainsString('Own personal item', json_encode($result));
    }

    /** Current role membership and item restrictions override stale session data. */
    public function testRoleRevocationAndNewItemRestrictionsTakeEffect(): void
    {
        DB::query('INSERT INTO renewal_users_roles VALUES (7, 3, %s)', 'ad');
        self::assertSame(4, runTable(newRequest())['recordsTotal']);
        DB::query('DELETE FROM renewal_users_roles');
        self::assertSame(3, runTable(newRequest(), [], ['user-roles' => '3'])['recordsTotal']);
        DB::query('UPDATE renewal_items SET restricted_to = %s WHERE id = 1', '8');
        self::assertSame(2, runTable(newRequest())['recordsTotal']);
    }

    /** Personal trees need both switches; shared grants must never expose their descendants. */
    public function testPersonalFoldersOffAndEmptyScope(): void
    {
        ConfigManager::$settings['enable_pf_feature'] = 0;
        self::assertSame(2, runTable(newRequest())['recordsTotal']);
        ConfigManager::$settings['enable_pf_feature'] = 1;
        DB::query('UPDATE renewal_users SET personal_folder = 0');
        self::assertSame(2, runTable(newRequest())['recordsTotal']);
        DB::query('DELETE FROM renewal_users_groups');
        self::assertSame([], runTable(newRequest())['data']);
        ConfigManager::$settings['activate_expiration'] = 0;
        self::assertSame(0, runTable(newRequest())['recordsTotal']);
    }

    /** Keep the existing expiration semantics, including password changes and soft deletion. */
    public function testExpirationDateAndItemStateFiltersStillApply(): void
    {
        DB::query('UPDATE renewal_items SET inactif = 1 WHERE id = 1');
        DB::query('UPDATE renewal_items SET deleted_at = %s WHERE id = 4', '2026-09-13');
        DB::query('INSERT INTO renewal_log_items VALUES (5, %s, %s, %s)', '172800', 'at_modification', 'at_pw');
        $early = runTable(newRequest(), ['dateCriteria' => 86400000]);
        self::assertSame([], $early['data']);
        $later = runTable(newRequest(), ['dateCriteria' => 259200000]);
        self::assertSame(1, $later['recordsTotal']);
        self::assertSame(date('Y-m-d H:i:s', 259200), $later['data'][0][1]);
    }

    /** All served entry points must use the same permission check and class copies. */
    public function testPageAndEndpointGuardsRemainWired(): void
    {
        self::assertSame(
            source('app/includes/libraries/teampassclasses/performchecks/src/PerformChecks.php'),
            source('app/vendor/teampassclasses/performchecks/src/PerformChecks.php')
        );
        foreach (['app/pages/utilities.renewal.php', 'app/pages/utilities.renewal.js.php', 'app/sources/expired.datatables.php'] as $file) {
            $code = source($file);
            self::assertStringContainsString("userAccessPage('utilities.renewal') === false", $code);
            self::assertStringContainsString('checkSession() === false', $code);
            self::assertStringContainsString("\$SETTINGS['activate_expiration']", $code);
        }
        self::assertStringContainsString('/app/sources/expired.datatables.php', source('public/sources/expired.datatables.php'));
        $index = source('public/index.php');
        self::assertSame(1, substr_count($index, 'data-name="utilities.renewal"'));
        $menu = substr($index, strpos($index, '// Renewal follows'), strpos($index, '// KB menu') - strpos($index, '// Renewal follows'));
        self::assertStringContainsString('(int) $session_user_admin === 0', $menu);
        self::assertStringContainsString("\$SETTINGS['activate_expiration']", $menu);
    }

    /** Render the production menu condition for each role and expiration setting. */
    public function testMenuVisibilityAndActiveState(): void
    {
        $index = source('public/index.php');
        $start = strpos($index, '// Renewal follows');
        self::assertIsInt($start);
        $menu = substr($index, $start, strpos($index, '// KB menu', $start) - $start);
        $lang = new class {
            /** Return a predictable translated label for the menu render. */
            public function get(string $key): string { return 'Renouvellement'; }
        };
        foreach ([0, 1] as $session_user_admin) {
            foreach ([0, 1] as $enabled) {
                $SETTINGS = ['activate_expiration' => $enabled];
                $get = ['page' => 'utilities.renewal'];
                ob_start();
                try {
                    eval($menu);
                    $html = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                if ($session_user_admin === 0 && $enabled === 1) {
                    self::assertStringContainsString('data-name="utilities.renewal" class="nav-link active"', $html);
                    self::assertStringContainsString('Renouvellement', $html);
                } else {
                    self::assertSame('', $html);
                }
            }
        }
    }
}
