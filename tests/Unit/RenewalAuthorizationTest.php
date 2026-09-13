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
                created_at TEXT DEFAULT '1000', restricted_to TEXT DEFAULT '', inactif INTEGER DEFAULT 0, deleted_at TEXT,
                renewal_period INTEGER DEFAULT 0, perso INTEGER DEFAULT 0);
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
            CREATE TABLE renewal_lapr_accounts (id INTEGER PRIMARY KEY, item_id INTEGER, status TEXT,
                username_cache TEXT, next_rotation_at TEXT, last_rotation_status TEXT, endpoint_id INTEGER, policy_id INTEGER);
            CREATE TABLE renewal_lapr_endpoints (id INTEGER PRIMARY KEY, ssh_credential_source INTEGER, status TEXT,
                label TEXT, hostname TEXT, os_info TEXT, ssh_username TEXT);
            CREATE TABLE renewal_lapr_policies (id INTEGER PRIMARY KEY, label TEXT, frequency_days INTEGER, is_preset INTEGER);
            SQL);
    }

    /** Run the shipped preview with the same SQL and grant fixture as the renewal table. */
    private function preview(int $folderId = 11, array $itemIds = [], bool $creation = false): array
    {
        $preview = newRequest() . '\\renewalPreview';
        return $preview(7, $folderId, $itemIds, $creation, ConfigManager::$settings);
    }

    /** All policy combinations work for both shared and personal items, including folder expiration off. */
    public function testIndividualPolicyMatrixAcrossPreviewItemAndRenewalTable(): void
    {
        $base = time() - 40 * 86400;
        foreach ([[11, 1, 'Open'], [21, 5, 'Own personal item']] as [$folderId, $itemId, $term]) {
            foreach ([0, 1] as $folderEnabled) {
                foreach ([0, 90] as $folderDays) {
                    foreach ([0, 30, 180] as $itemDays) {
                        ConfigManager::$settings['activate_expiration'] = $folderEnabled;
                        DB::query('UPDATE renewal_nested_tree SET renewal_period = %i WHERE id = %i', $folderDays, $folderId);
                        DB::query('UPDATE renewal_items SET renewal_period = %i, created_at = %s WHERE id = %i', $itemDays, (string) $base, $itemId);
                        $expected = $folderEnabled && $folderDays > 0
                            ? ($itemDays > 0 ? min($itemDays, $folderDays) : $folderDays) : $itemDays;
                        $due = $expected > 0 ? $base + $expected * 86400 : null;
                        $preview = $this->preview($folderId, [$itemId]);
                        self::assertSame($expected, $preview['items'][0]['days']);
                        self::assertSame($due, $preview['items'][0]['due_at']);
                        $readDeadline = newRequest() . '\\renewalItemDueAt';
                        self::assertSame($due, $readDeadline($itemId, ConfigManager::$settings));
                        $readStatus = newRequest() . '\\renewalItemStatus';
                        self::assertSame(renewalStatus($expected, $due, ConfigManager::$settings), $readStatus($itemId, ConfigManager::$settings));
                        $table = runTable(newRequest(), ['search' => ['value' => $term]]);
                        self::assertSame($due !== null ? 1 : 0, $table['recordsFiltered']);
                    }
                }
            }
        }
    }

    /** Both LAPR roles are excluded, even paused/error links, without depending on the scheduler or policy. */
    public function testLaprRelationsAgreeAcrossPreviewDeadlinesAndGovernance(): void
    {
        DB::query('UPDATE renewal_items SET renewal_period = 1');
        DB::query('INSERT INTO renewal_lapr_accounts (id, item_id, status, endpoint_id) VALUES (1, 1, %s, 1)', 'active');
        DB::query('INSERT INTO renewal_lapr_endpoints (id, ssh_credential_source, status, label, hostname) VALUES (1, 4, %s, %s, %s)', 'active', 'Host', 'example.invalid');
        foreach (['active', 'paused', 'error', 'deleted'] as $accountStatus) {
            foreach (['active', 'disabled', 'error', 'deleted'] as $endpointStatus) {
                DB::query('UPDATE renewal_lapr_accounts SET status = %s', $accountStatus);
                DB::query('UPDATE renewal_lapr_endpoints SET status = %s', $endpointStatus);
                foreach ([0, 1] as $enabled) {
                    ConfigManager::$settings['lapr_enabled'] = $enabled;
                    $namespace = newRequest();
                    $status = $namespace . '\\renewalItemStatus';
                    $relations = ($namespace . '\\laprGetItemRelations')([1, 4], ConfigManager::$settings);
                    $eligibleSql = ($namespace . '\\renewalEligibleItemSql')(ConfigManager::$settings);
                    $eligible = array_column(DB::query('SELECT i.id, ' . $eligibleSql . ' AS eligible FROM renewal_items i'), 'eligible', 'id');
                    $table = runTable($namespace);
                    $overdue = array_column(runRotationReport($namespace, 'report_rotation_overdue')['rows'], 'item_id');
                    $coverage = array_column(runRotationReport($namespace, 'report_rotation_sla')['rows'], null, 'folder_id');
                    $excludedCount = 0;
                    foreach ([1 => $accountStatus, 4 => $endpointStatus] as $id => $linkStatus) {
                        $excluded = $enabled === 1 && $linkStatus !== 'deleted';
                        $excludedCount += $excluded ? 1 : 0;
                        self::assertSame(!$excluded, (bool) $eligible[$id]);
                        self::assertSame($excluded, !empty($relations[$id]['is_managed']) || !empty($relations[$id]['is_credential']));
                        self::assertSame($excluded ? 'none' : 'expired', $status($id, ConfigManager::$settings)['state']);
                        $preview = $this->preview(11, [$id])['items'][0];
                        self::assertSame($excluded ? 'lapr' : 'folder', $preview['source']);
                        self::assertSame($excluded, $preview['due_at'] === null);
                        self::assertSame(!$excluded, in_array($id, $overdue, true));
                    }
                    self::assertSame(3 - $excludedCount, $table['recordsTotal']);
                    foreach (['items', 'overdue', 'individual_policies', 'covered_items', 'effective_overdue'] as $key) {
                        self::assertSame(4 - $excludedCount, $coverage[11][$key], $key);
                    }
                }
            }
        }
    }

    /** A copied item has no LAPR relationship; its inherited ordinary policy starts a new age. */
    public function testCopyingALaprItemAndRemovingItsLastLinkRestoresOrdinaryRenewal(): void
    {
        ConfigManager::$settings['lapr_enabled'] = 1;
        DB::query('UPDATE renewal_items SET renewal_period = 30 WHERE id = 1');
        DB::query('INSERT INTO renewal_lapr_endpoints (id, ssh_credential_source, status) VALUES (1, 1, %s), (2, 1, %s)', 'deleted', 'disabled');
        self::assertSame('lapr', $this->preview(11, [1])['items'][0]['source']);
        $preview = newRequest() . '\\renewalPreview';
        $copy = $preview(7, 11, [1], false, ConfigManager::$settings, null, true)['items'][0];
        self::assertSame('folder', $copy['source']);
        self::assertSame(1, $copy['days']);
        self::assertFalse($copy['expired']);
        DB::query('UPDATE renewal_lapr_endpoints SET status = %s', 'deleted');
        self::assertSame('folder', $this->preview(11, [1])['items'][0]['source']);
        self::assertSame(30, DB::queryFirstField('SELECT renewal_period FROM renewal_items WHERE id = 1'));
    }

    /** LAPR credentials without a dormant policy must not be reported as missing expiration. */
    public function testPostureAndSearchDoNotReclassifyLaprAsMissingExpiration(): void
    {
        ConfigManager::$settings['lapr_enabled'] = 1;
        DB::query('UPDATE renewal_items SET renewal_period = 0');
        DB::query('UPDATE renewal_nested_tree SET renewal_period = 0');
        DB::query('INSERT INTO renewal_lapr_endpoints (id, ssh_credential_source, status) VALUES (1, 1, %s)', 'active');
        $namespace = newRequest();
        $effectivePeriodSql = ($namespace . '\\renewalApplicablePeriodSql')(ConfigManager::$settings);
        $SETTINGS = ConfigManager::$settings;
        $dashboard = source('app/sources/dashboard.queries.php');
        preg_match('/^\$flagNoExpirySql = .*;$/m', $dashboard, $assignment);
        $flag = eval('namespace ' . $namespace . '; ' . $assignment[0] . ' return $flagNoExpirySql;');
        $rows = array_column(DB::query('SELECT i.id, ' . $flag . ' AS no_expiry FROM renewal_items i INNER JOIN renewal_nested_tree n ON n.id = i.id_tree'), 'no_expiry', 'id');
        self::assertSame(0, $rows[1]);
        self::assertSame(1, $rows[4]);

        require_once __DIR__ . '/../../app/sources/search.functions.php';
        $filters = searchNormalizeFilters(['health' => ['no_expiry']]);
        $where = searchBuildWhere($filters, [
            'user_id' => 7, 'role_ids' => [], 'folder_scope' => [11],
            'renewal_period_sql' => $effectivePeriodSql,
            'renewal_eligible_sql' => ($namespace . '\\renewalEligibleItemSql')($SETTINGS),
            'tables' => ['restriction_to_roles' => 'renewal_restriction_to_roles'],
        ]);
        $found = DB::query('SELECT i.id FROM renewal_items i INNER JOIN renewal_nested_tree n ON n.id = i.id_tree INNER JOIN renewal_items c ON c.id = i.id WHERE ' . $where['sql'], $where['params']);
        self::assertSame([4], array_column($found, 'id'));
    }

    /** Pending edits preview the new setting without persisting it; copying starts a new age. */
    public function testDraftPolicyAndCopyPreviewNeverMutateTheSource(): void
    {
        ConfigManager::$settings['activate_expiration'] = 0;
        DB::query('UPDATE renewal_items SET renewal_period = 30 WHERE id IN (1, 5, 6)');
        $preview = newRequest() . '\\renewalPreview';
        $draft = $preview(7, 11, [1], false, ConfigManager::$settings, 90);
        self::assertSame(90, $draft['items'][0]['days']);
        self::assertSame(30, (int) DB::queryFirstField('SELECT renewal_period FROM renewal_items WHERE id = 1'));
        $disabled = $preview(7, 11, [1], false, ConfigManager::$settings, 0);
        self::assertNull($disabled['items'][0]['due_at']);
        $copy = $preview(7, 11, [1], false, ConfigManager::$settings, null, true);
        self::assertSame(30, $copy['items'][0]['days']);
        self::assertGreaterThanOrEqual(time() + 29 * 86400, $copy['items'][0]['due_at']);
        self::assertFalse($copy['items'][0]['expired']);
        $table = runTable(newRequest());
        self::assertSame(2, $table['recordsTotal']);
        self::assertStringContainsString('Own personal item', json_encode($table));
        self::assertStringNotContainsString('Foreign personal item', json_encode($table));
    }

    /** Governance counts individual deadlines but never includes personal trees or their legacy descendants. */
    public function testGovernanceSeparatesFolderSlaAndEffectiveDeadlines(): void
    {
        DB::query('UPDATE renewal_items SET renewal_period = 1 WHERE id IN (1, 5, 6)');
        DB::query('UPDATE renewal_nested_tree SET renewal_period = 90');
        DB::query('UPDATE renewal_items SET created_at = %s', (string) (time() - 30 * 86400));
        foreach ([0, 1] as $enabled) {
            ConfigManager::$settings['activate_expiration'] = $enabled;
            $overdue = runRotationReport(newRequest(), 'report_rotation_overdue');
            self::assertCount(1, $overdue['rows']);
            self::assertSame(1, $overdue['rows'][0]['item_id']);
            self::assertSame(1, $overdue['rows'][0]['sla_days']);
            self::assertSame($enabled ? 90 : 0, $overdue['rows'][0]['folder_sla_days']);
            self::assertStringNotContainsString('personal', json_encode($overdue));
            $coverage = runRotationReport(newRequest(), 'report_rotation_sla');
            $folders = array_column($coverage['rows'], null, 'folder_id');
            self::assertSame([10, 11, 12], array_values(array_intersect([10, 11, 12], array_keys($folders))));
            self::assertCount(3, $folders);
            self::assertSame(0, $folders[11]['overdue']);
            self::assertSame(1, $folders[11]['effective_overdue']);
            self::assertSame(1, $folders[11]['individual_policies']);
            self::assertSame($enabled ? 3 : 0, $coverage['folders_with_sla']);
        }
        DB::query('DELETE FROM renewal_items WHERE id_tree = 10');
        $empty = array_column(runRotationReport(newRequest(), 'report_rotation_sla')['rows'], null, 'folder_id');
        self::assertSame(0, $empty[10]['covered_items']);
    }

    /** Preview and renewal table agree on password history, creation fallback and moved items. */
    public function testPreviewUsesPasswordAgeInDestinationWithoutResettingIt(): void
    {
        DB::query('UPDATE renewal_nested_tree SET renewal_period = 90 WHERE id = 11');
        DB::query('INSERT INTO renewal_log_items VALUES (1, %s, %s, %s)', '2000', 'at_creation', '');
        DB::query('INSERT INTO renewal_log_items VALUES (1, %s, %s, %s)', '3000', 'at_modification', 'at_pw');
        DB::query('INSERT INTO renewal_log_items VALUES (1, %s, %s, %s)', '9000', 'at_modification', 'at_moved');
        $result = $this->preview(11, [1, 4, 5]);
        self::assertFalse($result['error']);
        self::assertSame(90, $result['days']);
        $items = array_column($result['items'], null, 'id');
        self::assertSame(3000 + 90 * 86400, $items[1]['due_at']);
        self::assertSame(1000 + 90 * 86400, $items[4]['due_at']);
        self::assertSame(1000 + 90 * 86400, $items[5]['due_at'], 'Source folder period is irrelevant to the destination preview.');
        self::assertTrue($items[1]['expired']);
        self::assertSame(21, (int) DB::queryFirstField('SELECT id_tree FROM renewal_items WHERE id = 5'), 'Preview never moves an item.');
        $table = runTable(newRequest(), ['search' => ['value' => 'Open']]);
        self::assertStringContainsString($items[1]['due_date'], $table['data'][0][1]);
    }

    /** Expiration off and a zero period never claim an expiry date. */
    public function testPreviewHandlesDisabledExpirationAndNoRenewalPeriod(): void
    {
        ConfigManager::$settings['activate_expiration'] = 0;
        $disabled = $this->preview(11, [1]);
        self::assertFalse($disabled['folder_enabled']);
        self::assertSame(0, $disabled['days']);
        self::assertNull($disabled['items'][0]['due_at']);
        ConfigManager::$settings['activate_expiration'] = 1;
        DB::query('UPDATE renewal_nested_tree SET renewal_period = 0 WHERE id = 11');
        $none = $this->preview(11, [1]);
        self::assertTrue($none['enabled']);
        self::assertSame(0, $none['days']);
        self::assertNull($none['items'][0]['due_at']);
    }

    /** Empty folders show their policy and creation previews begin at the current time. */
    public function testCreationPreviewEstimatesFromNowAndHandlesMissingHistory(): void
    {
        self::assertSame([], $this->preview()['items']);
        $before = time();
        $created = $this->preview(11, [], true);
        self::assertTrue($created['creation']);
        self::assertGreaterThanOrEqual($before + 86400, $created['items'][0]['due_at']);
        self::assertLessThanOrEqual(time() + 86400, $created['items'][0]['due_at']);
        self::assertFalse($created['items'][0]['expired']);
        DB::query("UPDATE renewal_items SET created_at = '0' WHERE id = 1");
        $unknown = $this->preview(11, [1]);
        self::assertNull($unknown['items'][0]['due_at']);
        self::assertFalse($unknown['items'][0]['expired']);
    }

    /** All denial cases return no folder policy, label or password-history metadata. */
    public function testPreviewRejectsInaccessibleFoldersAndMixedItemSelections(): void
    {
        foreach ([10, 12, 30, 31, 999] as $folderId) {
            self::assertSame(['error' => true], $this->preview($folderId));
        }
        foreach ([[1, 2], [3], [6], [7], [8], [999], ['1 OR 1=1'], [0], [[]]] as $items) {
            self::assertSame(['error' => true], $this->preview(11, $items));
        }
        self::assertSame(['error' => true], $this->preview(11, [1], true));
        self::assertFalse($this->preview(21, [5])['error']);
        DB::query('UPDATE renewal_users SET admin = 1 WHERE id = 7');
        self::assertSame(['error' => true], $this->preview());
    }

    /** Revoked grants and deleted items cannot survive in a subsequent preview. */
    public function testPreviewRefreshesPermissionsAndExcludesDeletedItems(): void
    {
        self::assertFalse($this->preview(11, [1])['error']);
        DB::query('UPDATE renewal_items SET deleted_at = %s WHERE id = 1', '100');
        self::assertSame(['error' => true], $this->preview(11, [1]));
        DB::query('DELETE FROM renewal_users_groups WHERE group_id = 11');
        self::assertSame(['error' => true], $this->preview());
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

    public function testDefaultIncludesFutureDeadlinesAndClearingCutoffRestoresThem(): void
    {
        ConfigManager::$settings['activate_expiration'] = 0;
        $now = time();
        DB::query('UPDATE renewal_items SET renewal_period = 30, created_at = %s', (string) $now);
        DB::query('UPDATE renewal_items SET created_at = %s WHERE id = 1', (string) ($now - 60 * 86400));
        DB::query('UPDATE renewal_items SET created_at = %s WHERE id = 4', (string) ($now - 20 * 86400));
        DB::query('UPDATE renewal_items SET renewal_period = 90 WHERE id = 5');
        $all = runTable(newRequest());
        self::assertSame(3, $all['recordsTotal']);
        self::assertStringContainsString('id=1', $all['data'][0][0]);
        self::assertStringContainsString('id=4', $all['data'][1][0]);
        self::assertStringContainsString('id=5', $all['data'][2][0]);
        self::assertStringNotContainsString('Foreign personal', json_encode($all));
        self::assertStringNotContainsString('User restriction', json_encode($all));
        $limited = runTable(newRequest(), ['dateCriteria' => strtotime('+14 days midnight') * 1000]);
        self::assertSame(2, $limited['recordsTotal']);
        self::assertSame($all['data'], runTable(newRequest(), ['dateCriteria' => ''])['data']);
        DB::query('UPDATE renewal_items SET created_at = %s WHERE id = 4', '0');
        DB::query('UPDATE renewal_items SET renewal_period = 0 WHERE id = 5');
        self::assertSame(1, runTable(newRequest())['recordsTotal']);
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
            self::assertStringNotContainsString("(int) (\$SETTINGS['activate_expiration'] ?? 0) !== 1", $code);
        }
        self::assertStringContainsString('/app/sources/expired.datatables.php', source('public/sources/expired.datatables.php'));
        $index = source('public/index.php');
        self::assertSame(1, substr_count($index, 'data-name="utilities.renewal"'));
        $menu = substr($index, strpos($index, '// Renewal follows'), strpos($index, '// KB menu') - strpos($index, '// Renewal follows'));
        self::assertStringContainsString('(int) $session_user_admin === 0', $menu);
        self::assertStringNotContainsString("\$SETTINGS['activate_expiration']", $menu);
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
                if ($session_user_admin === 0) {
                    self::assertStringContainsString('data-name="utilities.renewal" class="nav-link active"', $html);
                    self::assertStringContainsString('Renouvellement', $html);
                } else {
                    self::assertSame('', $html);
                }
            }
        }
    }
}
