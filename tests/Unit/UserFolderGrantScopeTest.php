<?php

declare(strict_types=1);

namespace UserFolderGrantScopeRuntime;

use PHPUnit\Framework\TestCase;

// Real production set logic (DB-free): grantableFolderIds(), mergeGrantableRoleSets(),
// rolesScopeNormalizeIds(). The evaluated wrappers below fall back to these global functions.
require_once __DIR__ . '/../../app/sources/roles_scope.functions.php';

/** Fixture session shared with the namespaced stubs below. */
class Fixture
{
    public static array $session = [];
}

/** Stands in for TeampassClasses\SessionManager\SessionManager in the evaluated wrappers. */
class SessionManager
{
    /** Return a session reading the fixture. */
    public static function getSession(): object
    {
        return new class {
            /** Read a fixture session value. */
            public function get(string $key) { return Fixture::$session[$key] ?? null; }
        };
    }
}

/** Folders 6 and 7 form a personal tree. */
function getPersonalFolderIdsWithDescendants(): array { return [6, 7]; }

/**
 * Regression tests for GHSA-vrhq-38vx-9vjp.
 *
 * The Users page stored the allowed folders (users_groups) and forbidden folders
 * (users_groups_forbidden) of an account exactly as submitted. A direct grant gives full
 * write access to the folder, so a manager could create or edit an account, grant it any
 * folder by id, sign in with it and read every item of folders they cannot open themselves.
 * The roles were already limited to the caller's scope (GHSA-j3ph-4847-695h); the folders
 * now follow the same rule.
 */
class UserFolderGrantScopeTest extends TestCase
{
    private const MAIN_FUNCTIONS = __DIR__ . '/../../app/sources/main.functions.php';

    /** Load the shipped wrappers once, against the stubs of this namespace. */
    public static function setUpBeforeClass(): void
    {
        if (function_exists(__NAMESPACE__ . '\\reconcileUserFolderSet')) {
            return;
        }
        $source = (string) file_get_contents(self::MAIN_FUNCTIONS);
        $code = self::extractFunction($source, 'callerGrantableFolderIds')
            . "\n" . self::extractFunction($source, 'reconcileUserFolderSet');

        // Test-only: runs two functions of the shipped file (never user input) against the
        // namespaced stubs above, the pattern RoleRightsScopeTest already uses.
        eval('namespace ' . __NAMESPACE__ . '; ' . $code);
    }

    // -------------------------------------------------------------------
    // grantableFolderIds()
    // -------------------------------------------------------------------

    /** Accessible folders minus personal and denied ones, as unique integers. */
    public function testGrantableFoldersExcludePersonalAndDeniedFolders(): void
    {
        $this->assertSame([1, 2], grantableFolderIds(['1', 2, '3', 6, '7', '2'], [6, 7, '3']));
    }

    /** Empty strings and the root never count as folders. */
    public function testGrantableFoldersDropNonFolderValues(): void
    {
        $this->assertSame([5], grantableFolderIds(['', 0, 'abc', '5'], []));
    }

    // -------------------------------------------------------------------
    // reconcileUserFolderSet(), as a manager
    // -------------------------------------------------------------------

    /** The reported attack: a new account granted a folder outside the manager's scope. */
    public function testNewAccountCannotBeGrantedAFolderOutsideTheCallersScope(): void
    {
        $this->asManager();

        // Folder 4 is not accessible, 3 is denied to the caller, 7 is a personal folder.
        $this->assertSame([], reconcileUserFolderSet([], ['4']));
        $this->assertSame([], reconcileUserFolderSet([], ['3', '7']));
        $this->assertSame([1], reconcileUserFolderSet([], ['1', '4']));
    }

    /** Saving the form keeps the folders the caller cannot see, and cannot revoke them. */
    public function testEditKeepsStoredFoldersOutsideTheCallersScope(): void
    {
        $this->asManager();

        // Stored: 2 (in scope) and 4 (outside). Submitted: 1 only.
        $this->assertSame([4, 1], reconcileUserFolderSet(['2', '4'], ['1']));
        // An empty submission revokes the in-scope folder only.
        $this->assertSame([4], reconcileUserFolderSet(['2', '4'], []));
    }

    /** A manager with no accessible folder can neither grant nor revoke anything. */
    public function testCallerWithoutFoldersChangesNothing(): void
    {
        $this->asManager(['user-accessible_folders' => []]);

        $this->assertSame([4], reconcileUserFolderSet(['4'], ['1', '2']));
    }

    /** A missing session list is an empty scope, never everything. */
    public function testMissingSessionListsGiveAnEmptyScope(): void
    {
        Fixture::$session = ['user-admin' => 0];

        $this->assertSame([], reconcileUserFolderSet([], ['1', '4']));
    }

    /** Administrators keep the submitted list, normalized. */
    public function testAdministratorKeepsTheSubmittedList(): void
    {
        $this->asManager(['user-admin' => 1]);

        $this->assertSame([4, 3], reconcileUserFolderSet(['1'], ['4', '', '3']));
    }

    // -------------------------------------------------------------------
    // Wiring: every write of a user's folder lists is reconciled
    // -------------------------------------------------------------------

    /**
     * Each call of setUserGroups() / setUserForbiddenGroups() outside their definition must
     * pass a reconcileUserFolderSet() result. A new call site storing raw ids fails here.
     */
    public function testEveryFolderListWriteIsReconciled(): void
    {
        $callSites = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../../app', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() !== 'php' || str_contains($path, '/app/vendor/')) {
                continue;
            }
            $source = (string) file_get_contents($path);
            if (preg_match_all('/(?<![\w>:$])(setUserGroups|setUserForbiddenGroups|addUserGroup|addUserForbiddenGroup)\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[1] as [$name, $offset]) {
                // Skip the definitions themselves
                if (preg_match('/function\s+$/', substr($source, max(0, $offset - 20), min(20, $offset))) === 1) {
                    continue;
                }
                $relative = substr($path, strpos($path, '/app/') + 1);
                $line = substr_count($source, "\n", 0, $offset) + 1;
                // The single-row helpers are only used by setUserGroups() / setUserForbiddenGroups()
                if (str_starts_with($name, 'add')) {
                    $this->assertSame(
                        'app/sources/main.functions.php',
                        $relative,
                        $name . '() called directly at ' . $relative . ':' . $line
                    );
                    continue;
                }
                $callSites++;
                $this->assertMatchesRegularExpression(
                    // First argument: a user id, optionally cast ("(int) $dest_user_id")
                    '/^\s*(?:\([^()]*\)\s*)?[^,()]+,\s*reconcileUserFolderSet\(/',
                    substr($source, $offset + strlen($name) + 1, 300),
                    $name . '() must store a reconcileUserFolderSet() result at ' . $relative . ':' . $line
                );
            }
        }

        // add_new_user, store_user_changes, update_users_rights_sharing: two lists each
        $this->assertSame(6, $callSites);
    }

    /** The scope is the caller's accessible folders minus personal and denied folders. */
    public function testCallerScopeReadsTheResolvedSessionLists(): void
    {
        $this->asManager();

        $this->assertSame([1, 2], callerGrantableFolderIds());
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * Manager session: folders 1, 2, 3 and the personal tree 6 > 7 are accessible, 3 is denied.
     * The grantable scope is therefore folders 1 and 2.
     */
    private function asManager(array $overrides = []): void
    {
        Fixture::$session = array_merge([
            'user-admin' => 0,
            'user-manager' => 1,
            'user-accessible_folders' => ['1', '2', '3', '6', '7'],
            'user-no_access_folders' => ['3'],
        ], $overrides);
    }

    /** Return the source of one top-level function of a PHP file. */
    private static function extractFunction(string $source, string $name): string
    {
        $start = strpos($source, "\nfunction " . $name . '(');
        self::assertNotFalse($start, $name . '() must exist in main.functions.php.');
        $end = strpos($source, "\n}\n", $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end + 2 - $start);
    }
}
