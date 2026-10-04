<?php

declare(strict_types=1);

namespace TeamPass\Tests\SpecialOptions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Stubs/folder_special_options_runtime.php';

/** Exercise the production option mapping, persistence and password checks without a database. */
class FolderSpecialOptionsTest extends TestCase
{
    protected function setUp(): void { DB::reset(); }

    private function manager(): FolderManager
    {
        $reflection = new \ReflectionClass(FolderManager::class);
        $manager = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('settings')->setValue($manager, ['enable_user_can_create_folders' => 1]);
        $reflection->getProperty('lang')->setValue($manager, new class {
            /** Return translation keys to make business errors independent of locale. */
            public function get(string $key): string { return $key; }
        });
        return $manager;
    }

    /** Run the web/API request-to-FolderManager mapping from the actual handlers. */
    private function createParams(string $path, array $options, int $parentId): array
    {
        $payload = ['title' => 'Child', 'parentId' => $parentId, 'complexity' => 60];
        if ($path === 'web') {
            $payload += $options;
            $source = sourceBetween(productionSource('app/sources/folders.queries.php'), "case 'add_folder':", "case 'delete_folders':");
            $variables = evaluateSource(
                sourceBetween($source, '$data = [', "\n            if ("),
                ['dataReceived' => $payload, 'session' => new TestSession()]
            );
            $variables += ['isPersonal' => 0, 'session' => new TestSession()];
            return evaluateSource(sourceBetween($source, '$params = [', '$options = ['), $variables)['params'];
        }

        $controller = productionSource('app/api/Controller/Api/FolderController.php');
        $model = new class {
            /** Capture the actual controller arguments before model processing. */
            public function createFolder(...$args): array { return $args; }
        };
        $user = [
            'is_admin' => 1, 'folders_list' => '7', 'is_manager' => 0,
            'user_can_create_root_folder' => 1, 'user_can_manage_all_users' => 0,
            'id' => 1, 'roles' => '', 'personal_folder' => 0, 'username' => '',
        ];
        $call = sourceBetween($controller, '$arrFolder = $folderModel->createFolder(', ');') . ');';
        $args = evaluateSource($call, [
            'folderModel' => $model, 'arrQueryStringParams' => [
                'title' => 'Child', 'parent_id' => $parentId, 'complexity' => 60,
            ] + $options,
            'userData' => $user, 'private' => false,
        ])['arrFolder'];
        $variables = [];
        foreach ((new \ReflectionMethod(FolderModel::class, 'createFolder'))->getParameters() as $index => $parameter) {
            $variables[$parameter->getName()] = array_key_exists($index, $args) ? $args[$index] : $parameter->getDefaultValue();
        }
        $source = productionSource('app/api/Model/FolderModel.php');
        $variables = evaluateSource(sourceBetween($source, '$data = [', '// A title made only'), $variables);
        $variables['isPersonal'] = 0;
        return evaluateSource(sourceBetween($source, '$params = [', '// Full lifecycle options'), $variables)['params'];
    }

    /** Cover each parent combination with independent omitted and explicit overrides. */
    public static function creationCases(): iterable
    {
        foreach (['web', 'api'] as $path) {
            $keys = $path === 'web' ? ['addRestriction', 'editRestriction'] : ['create_auth_without', 'edit_auth_without'];
            foreach ([[0, 0], [0, 1], [1, 0], [1, 1]] as [$create, $edit]) {
                foreach ([[], [$keys[0] => 0], [$keys[1] => 0], [$keys[0] => 1], [$keys[1] => 1], [$keys[0] => '0', $keys[1] => '1']] as $options) {
                    yield [$path, $create, $edit, $options, $keys];
                }
            }
        }
    }

    /** Verify the request mapping reaches the real folder insertion with the intended values. */
    #[DataProvider('creationCases')]
    public function testNewFoldersInheritOnlyOmittedOptions(string $path, int $create, int $edit, array $options, array $keys): void
    {
        $params = $this->createParams($path, $options, 7);
        DB::$rows = [['personal_folder' => 0, 'bloquer_creation' => $create, 'bloquer_modification' => $edit], ['valeur' => 38]];
        $result = $this->manager()->createNewFolder($params);
        self::assertFalse($result['error']);
        self::assertSame((int) ($options[$keys[0]] ?? $create), (int) DB::$writes[0]['data']['bloquer_creation']);
        self::assertSame((int) ($options[$keys[1]] ?? $edit), (int) DB::$writes[0]['data']['bloquer_modification']);
    }

    /** A missing parent supplies disabled defaults, while explicit choices still win. */
    public function testRootDefaultsAndExplicitOverrides(): void
    {
        foreach (['web', 'api'] as $path) {
            DB::reset();
            $params = $this->createParams($path, [], 0);
            DB::$rows = [null, null];
            self::assertFalse($this->manager()->createNewFolder($params)['error']);
            self::assertSame(0, DB::$writes[0]['data']['bloquer_creation']);
            self::assertSame(0, DB::$writes[0]['data']['bloquer_modification']);

            DB::reset();
            $options = $path === 'web' ? ['addRestriction' => 1, 'editRestriction' => 1] : ['create_auth_without' => 1, 'edit_auth_without' => 1];
            DB::$rows = [null, null];
            self::assertFalse($this->manager()->createNewFolder($this->createParams($path, $options, 0))['error']);
            self::assertSame(1, DB::$writes[0]['data']['bloquer_creation']);
            self::assertSame(1, DB::$writes[0]['data']['bloquer_modification']);
        }
    }

    public function testAdministrativeDeletionProtectionIsPersistedOnCreation(): void
    {
        DB::$rows = [['personal_folder' => 0, 'bloquer_creation' => 0, 'bloquer_modification' => 0], ['valeur' => 60]];
        $params = $this->createParams('web', ['deletionProtection' => 1], 7);

        self::assertFalse($this->manager()->createNewFolder($params)['error']);
        self::assertSame(1, DB::$writes[0]['data']['deletion_protected']);
    }

    public function testDeletionProtectionCannotBeCreatedWithoutAdministrativeContext(): void
    {
        $result = $this->manager()->createNewFolder([
            'title' => 'Child',
            'parent_id' => 7,
            'complexity' => 60,
            'deletion_protected' => 1,
            'user_is_admin' => 0,
            'user_accessible_folders' => [7],
        ]);

        self::assertTrue($result['error']);
        self::assertSame([], DB::$writes);
    }

    /** Rendering must reflect inheritance even when request defaults differ. */
    public function testCreationResponseUsesPersistedOptions(): void
    {
        $source = sourceBetween(productionSource('app/sources/folders.queries.php'), "case 'add_folder':", "case 'delete_folders':");
        $variables = evaluateSource(sourceBetween($source, '$rowData = [', "\n            }"), [
            'newFolderId' => 100, 'newNode' => (object) ['nlevel' => 2, 'bloquer_creation' => '1', 'bloquer_modification' => '0'],
            'inputData' => ['parentId' => 7, 'title' => 'Child', 'create_auth_without' => 0, 'edit_auth_without' => 0],
            'arrayPath' => [], 'arrayParents' => [], 'complexityValue' => 0,
        ]);
        self::assertSame(1, $variables['rowData']['add_is_blocked']);
        self::assertSame(0, $variables['rowData']['edit_is_blocked']);
    }

    /** Exercise the CSV parameter mapping under both permission-inheritance settings. */
    public function testCsvInheritsIndependentlyOfRolePermissionInheritance(): void
    {
        $source = sourceBetween(productionSource('app/sources/import.queries.php'), '// Insert folder and get its ID', '// Capture unexpected failures');
        foreach ([0, 1] as $inheritRights) {
            DB::reset();
            $params = evaluateSource(sourceBetween($source, '$params = [', '$options = ['), [
                'currentFolder' => 'Imported', 'parentId' => 7, 'personalFolder' => 0,
                'dataReceived' => ['folderPasswordComplexity' => 60], 'session' => new TestSession(),
            ])['params'];
            $manager = $this->manager();
            (new \ReflectionProperty($manager, 'settings'))->setValue($manager, ['subfolder_rights_as_parent' => $inheritRights]);
            DB::$rows = [['personal_folder' => 0, 'bloquer_creation' => 1, 'bloquer_modification' => 1], ['valeur' => 60]];
            self::assertFalse($manager->createNewFolder($params)['error']);
            self::assertSame(1, DB::$writes[0]['data']['bloquer_creation']);
            self::assertSame(1, DB::$writes[0]['data']['bloquer_modification']);
        }
    }

    /** Personal folders retain their existing complexity exception. */
    public function testPersonalCreationKeepsItsComplexityExemption(): void
    {
        DB::$rows = [['personal_folder' => 1, 'bloquer_creation' => 0, 'bloquer_modification' => 1]];
        $result = $this->manager()->createNewFolder([
            'title' => 'Personal child', 'parent_id' => 7, 'personal_folder' => 1,
            'complexity' => 0, 'user_accessible_folders' => [7],
        ]);
        self::assertFalse($result['error']);
        self::assertSame(1, DB::$writes[0]['data']['personal_folder']);
        self::assertSame(1, DB::$writes[0]['data']['bloquer_modification']);
    }

    /** Options cannot override the folder authorization gate. */
    public function testOptionsDoNotGrantAccessToAnUnauthorizedParent(): void
    {
        $result = $this->manager()->createNewFolder([
            'title' => 'Child', 'parent_id' => 7, 'complexity' => 60,
            'user_accessible_folders' => [], 'create_auth_without' => 1, 'edit_auth_without' => 1,
        ]);
        self::assertTrue($result['error']);
        self::assertSame([], DB::$writes);
    }

    /** Shared folder creation enforces the parent floor even for an administrator. */
    public function testOptionsNeverRelaxTheSharedParentComplexityFloor(): void
    {
        foreach ([0, 1] as $admin) {
            foreach ([[0, 0], [0, 1], [1, 0], [1, 1]] as [$create, $edit]) {
                DB::reset();
                DB::$rows = [['personal_folder' => 0, 'bloquer_creation' => $create, 'bloquer_modification' => $edit], ['valeur' => 60]];
                $result = $this->manager()->createNewFolder([
                    'title' => 'Child', 'parent_id' => 7, 'complexity' => 38,
                    'user_is_admin' => $admin, 'user_accessible_folders' => [7],
                    'create_auth_without' => $create, 'edit_auth_without' => $edit,
                ]);
                self::assertTrue($result['error']);
                self::assertStringContainsString('error_folder_complexity_lower_than_top_folder', $result['message']);
                self::assertSame([], DB::$writes);
            }
        }
    }

    /** Cover preservation and independent checkbox changes for each stored combination. */
    public static function updateCases(): iterable
    {
        foreach ([[0, 0], [0, 1], [1, 0], [1, 1]] as [$create, $edit]) {
            foreach ([[], ['addRestriction' => 0], ['editRestriction' => 0], ['addRestriction' => 1], ['editRestriction' => 1]] as $options) {
                yield [$create, $edit, $options];
            }
        }
    }

    /** Execute the actual web update preparation and return the stored row after its SQL write. */
    private function webUpdate(array $dataReceived, array $current): array
    {
        $source = sourceBetween(productionSource('app/sources/folders.queries.php'), "case 'update_folder':", "case 'add_folder':");
        $variables = evaluateSource(
            sourceBetween($source, '$data = [', "\n            if ("),
            ['dataReceived' => $dataReceived, 'session' => new TestSession()]
        );
        evaluateSource(
            sourceBetween($source, '// Prepare update parameters', '// Add or update complexity row'),
            $variables + ['isPersonal' => 0, 'dataFolder' => $current, 'SETTINGS' => []]
        );
        self::assertCount(1, DB::$writes);
        return array_replace($current, DB::$writes[0]['data']);
    }

    /** Execute the actual web update preparation and capture its SQL write. */
    #[DataProvider('updateCases')]
    public function testWebRenameOrMovePreservesUnspecifiedOptions(int $create, int $edit, array $options): void
    {
        $current = ['id' => 7, 'parent_id' => 1, 'renewal_period' => 0, 'bloquer_creation' => $create, 'bloquer_modification' => $edit];
        $stored = $this->webUpdate(['id' => 7, 'title' => 'Renamed', 'parentId' => 9, 'complexity' => 60] + $options, $current);
        self::assertSame((int) ($options['addRestriction'] ?? $create), $stored['bloquer_creation']);
        self::assertSame((int) ($options['editRestriction'] ?? $edit), $stored['bloquer_modification']);
        self::assertSame('Renamed', $stored['title']);
        self::assertSame(9, $stored['parent_id']);
    }

    public function testWebUpdatePreservesOrChangesDeletionProtectionExplicitly(): void
    {
        $current = [
            'id' => 7,
            'parent_id' => 1,
            'renewal_period' => 0,
            'bloquer_creation' => 0,
            'bloquer_modification' => 0,
            'deletion_protected' => 1,
        ];
        $base = ['id' => 7, 'title' => 'Renamed', 'parentId' => 1, 'complexity' => 60];

        self::assertSame(1, $this->webUpdate($base, $current)['deletion_protected']);
        DB::reset();
        self::assertSame(0, $this->webUpdate($base + ['deletionProtection' => 0], $current)['deletion_protected']);
    }

    /** Omitted (Items page), explicit zero and explicit renewal periods. */
    public static function renewalCases(): iterable
    {
        yield 'omitted' => [[], 30];
        yield 'explicit zero' => [['renewalPeriod' => 0], 0];
        yield 'explicit value' => [['renewalPeriod' => 90], 90];
    }

    /** The Items page omits the renewal period too; renaming a folder there must keep it. */
    #[DataProvider('renewalCases')]
    public function testWebRenameOrMovePreservesUnspecifiedRenewalPeriod(array $options, int $expected): void
    {
        $current = ['id' => 7, 'parent_id' => 1, 'renewal_period' => 30, 'bloquer_creation' => 1, 'bloquer_modification' => 1];
        $stored = $this->webUpdate(['id' => 7, 'title' => 'Renamed', 'parentId' => 1, 'complexity' => 60] + $options, $current);
        self::assertSame($expected, $stored['renewal_period']);
        self::assertSame(1, $stored['bloquer_creation']);
        self::assertSame(1, $stored['bloquer_modification']);
    }

    /** The legacy import path copies defaults only when inserting a new folder. */
    public function testKeePassCopiesParentOptionsAndDoesNotOverwriteExistingFolders(): void
    {
        foreach ([[0, 0], [0, 1], [1, 0], [1, 1]] as [$create, $edit]) {
            DB::reset();
            DB::$rows = [['bloquer_creation' => $create, 'bloquer_modification' => $edit]];
            self::assertSame(100, createFolder('Imported', 7, 1, 0, 60));
            self::assertSame($create, DB::$writes[0]['data']['bloquer_creation']);
            self::assertSame($edit, DB::$writes[0]['data']['bloquer_modification']);
        }
        DB::reset();
        DB::$queryResults = [[['id' => 8]]];
        DB::$rows = [['id' => 8], ['c' => 1]];
        self::assertSame(8, createFolder('Existing', 7, 1, 0, 60));
        self::assertSame([], DB::$writes);
    }

    /** Combine operation, exception flags, folder privacy and required strength. */
    public static function passwordCases(): iterable
    {
        foreach ([0, 1] as $personal) {
            foreach ([false, true] as $update) {
                foreach ([[0, 0], [0, 1], [1, 0], [1, 1]] as [$create, $edit]) {
                    foreach ([0, 38, 60] as $floor) {
                        yield [$personal, $update, $create, $edit, $floor];
                    }
                }
            }
        }
    }

    /** Run the real API evaluator and selected exception against a known weak password. */
    #[DataProvider('passwordCases')]
    public function testApiUsesTheExceptionForTheRequestedOperation(int $personal, bool $update, int $create, int $edit, int $floor): void
    {
        $model = new ItemModel();
        DB::$rows = [['personal_folder' => $personal, 'bloquer_creation' => $create, 'bloquer_modification' => $edit], ['valeur' => $floor]];
        $settings = (new \ReflectionMethod($model, 'getFolderSettings'))->invoke($model, 7);
        $allowed = $personal === 1 || $floor === 0 || ($update ? $edit : $create) === 1;
        if (!$allowed) {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Password strength is too low');
        }
        $score = (new \ReflectionMethod($model, 'checkPasswordComplexity'))->invoke($model, '1234', $settings + ['folderId' => 7], $update);
        self::assertSame(0, $score);
    }

    /** Bypassing a minimum never bypasses safe password evaluation. */
    public function testUnassessablePasswordStillFailsWithBothExceptionsEnabled(): void
    {
        DB::$rows = [['valeur' => 60]];
        $this->expectException(\InvalidArgumentException::class);
        (new \ReflectionMethod(ItemModel::class, 'checkPasswordComplexity'))->invoke(new ItemModel(), "bad\xE9", [
            'folderId' => 7, 'no_complex_check_on_creation' => 1, 'no_complex_check_on_modification' => 1,
        ], true);
    }

    /** A sufficient password needs no exception for either operation. */
    public function testStrongPasswordIsAcceptedWithoutEitherException(): void
    {
        foreach ([false, true] as $update) {
            DB::$rows = [['valeur' => 60]];
            $score = (new \ReflectionMethod(ItemModel::class, 'checkPasswordComplexity'))->invoke(new ItemModel(), 'jN4!sF9#pL2@cR8%vT6&zW3*', [
                'folderId' => 7, 'no_complex_check_on_creation' => 0, 'no_complex_check_on_modification' => 0,
            ], $update);
            self::assertSame(60, $score);
        }
    }

    /** Guard the operation arguments at the two production entry points. */
    public function testApiCallersSelectCreationAndEditingExplicitly(): void
    {
        $source = productionSource('app/api/Model/ItemModel.php');
        self::assertStringContainsString("\$this->checkPasswordComplexity(\$password, array_merge(\$itemInfos, ['folderId' => \$folderId]), isUpdate: false)", $source);
        self::assertStringContainsString("\$this->checkPasswordComplexity(\$newPassword, array_merge(\$itemInfos, ['folderId' => \$folderId]), isUpdate: true)", $source);
    }
}
