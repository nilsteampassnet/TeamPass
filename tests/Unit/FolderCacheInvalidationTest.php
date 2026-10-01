<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Source sentinels for the lifecycle, authorization refresh and cache writers. */
class FolderCacheInvalidationTest extends TestCase
{
    /** Normalize line endings so Windows and CI enforce the same invariants. */
    private function source(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../' . $path));
    }

    /** Extract one declaration without executing or copying production code. */
    private function body(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start, $name);
        $next = strpos($source, 'function ', $start + 9);
        return $next === false ? substr($source, $start) : substr($source, $start, $next - $start);
    }

    /** Verify ordering inside one declaration or handler. */
    private function before(string $source, string $first, string $second): void
    {
        $left = strpos($source, $first);
        $right = strpos($source, $second);
        self::assertNotFalse($left, $first);
        self::assertNotFalse($right, $second);
        self::assertLessThan($right, $left, "$first must precede $second");
    }

    /** Commit permissions, rebuild, then invalidate the creator and affected users. */
    public function testCreationInvalidatesAfterCommitAndRebuild(): void
    {
        $body = $this->body($this->source('app/sources/folders.class.php'), 'createFolder');
        $this->before($body, 'DB::commit()', 'rebuildFolderTree(');
        $this->before($body, 'rebuildFolderTree(', 'invalidateCacheForFolderUsers(');
        self::assertStringContainsString('invalidateCacheForFolderUsers((int) $newId, [(int) $user_id])', $body);
        self::assertStringNotContainsString('refreshCacheForUsersWithSimilarRoles', $body);
        self::assertStringContainsString('DB::rollback()', $body);
    }

    /** Creation no longer schedules unrelated users; parent changes retain their old path. */
    public function testCreationCallersDoNotQueueBroadInvalidation(): void
    {
        foreach (['app/sources/folders.queries.php', 'app/sources/import.queries.php', 'app/api/Model/FolderModel.php'] as $path) {
            self::assertStringNotContainsString('refreshCacheForUsersWithSimilarRoles', $this->source($path), $path);
        }
        $update = $this->body($this->source('app/sources/folders.class.php'), 'updateFolder');
        self::assertStringContainsString('refreshCacheForUsersWithSimilarRoles', $update);
    }

    /** Targeted invalidation includes direct-grant users and explicit owner IDs. */
    public function testInvalidationIncludesDirectGrantsAndCreator(): void
    {
        $source = $this->source('app/sources/main.functions.php');
        $body = $this->body($source, 'invalidateCacheForFolderUsers');
        self::assertStringContainsString("prefixTable('users_groups')", $body);
        self::assertStringContainsString('$additionalUserIds', $body);
        self::assertStringContainsString('invalidateUserFolderCache($affectedUsers)', $body);
        $invalidate = $this->body($source, 'invalidateUserFolderCache');
        self::assertStringContainsString('folderCacheNormalizeIds($userIds)', $invalidate);
        foreach (['data', 'visible_folders', 'folders'] as $field) {
            self::assertStringContainsString("'$field' => '[]'", $invalidate);
        }
    }

    /** Non-creation personal-folder mutations also invalidate the acting owner. */
    public function testUpdateAndCopyIncludeActingUserAfterRebuild(): void
    {
        $update = $this->body($this->source('app/sources/folders.class.php'), 'updateFolder');
        self::assertStringContainsString("[(int) (\$params['user_id'] ?? 0)]", $update);
        $this->before($update, '$tree->rebuild()', 'invalidateCacheForFolderUsers(');
        $web = $this->source('app/sources/folders.queries.php');
        self::assertStringContainsString("invalidateCacheForFolderUsers((int) \$dataFolder['id'], [(int) \$session->get('user-id')])", $web);
        self::assertStringContainsString("invalidateCacheForFolderUsers((int) \$post_target_folder_id, [(int) \$session->get('user-id')])", $web);
    }

    /** Deletion must not publish its invalidation before the new tree is committed. */
    public function testDeletionInvalidatesAfterCommitAndRebuild(): void
    {
        $delete = $this->body($this->source('app/sources/folders.class.php'), 'deleteFolders');
        $this->before($delete, 'DB::commit()', '$tree->rebuild()');
        $this->before($delete, '$tree->rebuild()', 'invalidateCacheForFolderUsers(');
        self::assertStringContainsString('$affectedUserIds = [$userId]', $delete);
        self::assertStringContainsString("prefixTable('users_groups')", $delete);
        $web = $this->source('app/sources/folders.queries.php');
        $start = strpos($web, '$affectedUserIds = [(int)');
        self::assertNotFalse($start);
        $webDelete = substr($web, $start, strpos($web, '// Emit WebSocket events for deleted folders', $start) - $start);
        $this->before($webDelete, 'DB::commit()', '$tree->rebuild()');
        $this->before($webDelete, '$tree->rebuild()', 'invalidateCacheForFolderUsers(');
        self::assertStringContainsString("prefixTable('users_groups')", $webDelete);
    }

    /** Keep consuming old tasks, but remove obsolete login/account/import producers. */
    public function testLegacyWorkerRemainsWithoutObsoleteProducers(): void
    {
        $worker = $this->body($this->source('app/scripts/background_tasks___functions.php'), 'performVisibleFoldersHtmlUpdate');
        self::assertStringContainsString('invalidateUserFolderCache([$user_id])', $worker);
        foreach (['app/sources/identify.php', 'app/scripts/traits/UserHandlerTrait.php', 'app/sources/import.queries.php'] as $path) {
            self::assertStringNotContainsString("'user_build_cache_tree'", $this->source($path));
        }
        self::assertStringContainsString("case 'user_build_cache_tree':", $this->source('app/scripts/background_tasks___worker.php'));
        $import = $this->source('app/sources/import.queries.php');
        $finalize = substr($import, strpos($import, "case 'keepass_finalize':"));
        $this->before($finalize, '$tree->rebuild()', 'invalidateUserFolderCache(');
    }

    /** Refresh before restriction/direct-grant/read-only shortcuts can authorize access. */
    public function testAuthorizationRefreshPrecedesEveryShortcut(): void
    {
        $source = $this->source('app/sources/items.queries.php');
        $access = $this->body($source, 'getCurrentFolderAccessRights');
        foreach (["get('user-read_only_folders')", "get('user-allowed_folders_by_definition')"] as $shortcut) {
            $this->before($access, 'refreshUserFolderPermissionScope(', $shortcut);
            $this->before($access, 'itemAccessFolderIsInScope(', $shortcut);
        }
        $itemAccess = $this->body($source, 'getCurrentAccessRights');
        foreach (['getItemRestrictedUsersList(', 'isProcessOnGoing('] as $itemShortcut) {
            $this->before($itemAccess, 'getCurrentFolderAccessRights(', $itemShortcut);
        }
        $fallback = $this->body($source, 'buildVisibleFoldersOnTheFly');
        $this->before($fallback, 'refreshUserFolderPermissionScope(', 'folderCacheVisibleScope(');
        self::assertStringContainsString('WHERE id IN %li', $fallback);
        self::assertStringNotContainsString('DB::queryFirstRow(', $fallback);
        $this->before($fallback, '$visibleFolderIds === []', 'DB::query(');
        self::assertStringContainsString('folderCacheVisibleRows(', $fallback);
    }

    /** Administrators stay out of shared items once the refresh no longer fails for them. */
    public function testAdministratorsAreDeniedRightAfterTheScopeCheck(): void
    {
        $access = $this->body($this->source('app/sources/items.queries.php'), 'getCurrentFolderAccessRights');
        $adminCheck = "if ((int) \$session->get('user-admin') === 1) {\n        return getFolderAccessResponse(false, false, false, false);";
        self::assertStringContainsString($adminCheck, $access);
        $this->before($access, 'itemAccessFolderIsInScope(', $adminCheck);
        $this->before($access, $adminCheck, "get('user-read_only_folders')");
    }

    /** Read-only accounts keep their personal folder, as the item/folder handlers expect. */
    public function testReadOnlyAccountsKeepTheirPersonalFolders(): void
    {
        $access = (string) preg_replace('/\s+/', ' ', $this->body($this->source('app/sources/items.queries.php'), 'getCurrentFolderAccessRights'));
        self::assertStringContainsString(
            "((int) \$session->get('user-read_only') === 1 && in_array(\$treeId, (array) \$session->get('user-personal_folders')) === false)",
            $access
        );
        $refresh = $this->body($this->source('app/sources/main.functions.php'), 'refreshUserFolderPermissionScope');
        self::assertStringContainsString("set('user-read_only', \$userData['read_only'])", $refresh);
        self::assertStringNotContainsString("set('user-read_only', (int)", $refresh);
    }

    /** Update every role array used by authorization, with one refresh per request. */
    public function testRefreshUsesLiveRolesAndIsSharedWithDropdown(): void
    {
        $refresh = $this->body($this->source('app/sources/main.functions.php'), 'refreshUserFolderPermissionScope');
        self::assertStringContainsString('static $refreshed = null', $refresh);
        $this->before($refresh, 'if ($refreshed !== null)', 'DB::queryFirstRow(');
        foreach (['users', 'users_roles', 'users_groups_forbidden', 'roles_title'] as $table) {
            self::assertStringContainsString("prefixTable('$table')", $refresh);
        }
        self::assertStringContainsString('identifyUserRights(', $refresh);
        foreach (['user-roles', 'user-roles_array', 'system-array_roles', 'user-personal_folder_enabled', 'user-can_create_root_folder'] as $key) {
            self::assertStringContainsString("set('$key'", $refresh);
        }
        self::assertStringContainsString("set('user-roles_array', array_map('strval', \$roles))", $refresh);
        $source = $this->source('app/sources/items.queries.php');
        $dropdown = substr($source, strpos($source, "case 'refresh_visible_folders':"));
        $this->before($dropdown, 'beginUserFolderCacheBuild(', 'refreshUserFolderPermissionScope(');
        self::assertStringNotContainsString('identifyUserRights(', $source);
    }

    /** Reject a deleted/recreated row or same-second invalidation in the SQL update itself. */
    public function testWritesAreConditionalAndCannotRecreateDeletedRows(): void
    {
        $source = $this->source('app/sources/main.functions.php');
        $write = $this->body($source, 'cacheTreeUserHandler');
        self::assertStringContainsString('user_id = %i AND increment_id = %i AND IFNULL(invalidated_at, 0) < %i', $write);
        self::assertStringContainsString('CAST(timestamp AS UNSIGNED) <= %i', $write);
        self::assertStringContainsString('AND IFNULL(invalidated_at, 0) = %i', $write);
        self::assertStringContainsString("\$build['invalidated_at']", $write);
        self::assertStringNotContainsString('DB::insert', $write);
        self::assertStringNotContainsString('time()', $write);
        self::assertStringContainsString('folderCacheWriteFields(', $write);
        $begin = $this->body($source, 'beginUserFolderCacheBuild');
        self::assertStringContainsString('folderCacheEmptyRow($userId)', $begin);
        self::assertStringContainsString('ORDER BY increment_id LIMIT 1', $begin);
        self::assertStringNotContainsString('DB::startTransaction', $begin);
    }

    /** Capture the row/time before snapshotting refreshed session rights and folder rows. */
    public function testTreeBuildStartPrecedesPermissionSnapshot(): void
    {
        $tree = $this->source('app/sources/tree.php');
        $this->before($tree, 'beginUserFolderCacheBuild(', 'refreshUserFolderPermissionScope(');
        $this->before($tree, 'refreshUserFolderPermissionScope(', '$data = [');
        $this->before($tree, 'beginUserFolderCacheBuild(', '$completTree = $tree->getTreeWithChildren()');
        self::assertStringContainsString("\$treeBuiltAt = \$folderCacheBuild['started_at']", $tree);
        self::assertMatchesRegularExpression('/cacheTreeUserHandler\([^;]+\$folderCacheBuild\s*\);/s', $tree);
    }

    /** API writers keep invalidation history and capture context before reading permissions. */
    public function testApiWritesShareTheGuardAndDoNotResetInvalidation(): void
    {
        foreach (['app/api/index.php', 'app/api/Model/AuthModel.php'] as $path) {
            $source = $this->source($path);
            self::assertDoesNotMatchRegularExpression("/'invalidated_at'\\s*=>\\s*0/", $source);
            self::assertStringContainsString('cacheTreeUserHandler(', $source);
        }
        $auth = $this->body($this->source('app/api/Model/AuthModel.php'), 'issueJwtForUser');
        $this->before($auth, 'beginUserFolderCacheBuild(', '$folderUserInfo = getUserCompleteData(');
        $this->before($auth, '$folderUserInfo = getUserCompleteData(', 'buildUserFoldersList(');
        $api = $this->source('app/api/index.php');
        $this->before($api, 'beginUserFolderCacheBuild(', '$cacheRow = DB::queryFirstRow(');
    }
}
