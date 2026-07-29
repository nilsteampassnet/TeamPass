<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/api/Model/FolderAccessModel.php';

class FolderAccessModelTest extends TestCase
{
    /**
     * @param array<string,int> $overrides
     * @return array<string,int>
     */
    private function userData(array $overrides = []): array
    {
        return array_merge([
            'is_admin' => 0,
            'is_manager' => 0,
            'user_can_manage_all_users' => 0,
            'user_can_create_root_folder' => 0,
            'allowed_to_create' => 1,
            'allowed_to_update' => 1,
            'allowed_to_delete' => 1,
        ], $overrides);
    }

    /**
     * @return array{
     *     can_create_subfolder: bool,
     *     can_rename_folder: bool,
     *     can_move_folder: bool,
     *     can_delete_folder: bool
     * }
     */
    private function allManagementCapabilities(bool $value): array
    {
        return [
            'can_create_subfolder' => $value,
            'can_rename_folder' => $value,
            'can_move_folder' => $value,
            'can_delete_folder' => $value,
        ];
    }

    public function testNormalizeFolderIdsFromCsv(): void
    {
        $model = new FolderAccessModel();

        self::assertSame([1, 2, 3], $model->normalizeFolderIds('1,2,2,abc,3'));
    }

    public function testNormalizeFolderIdsDropsNonPositiveValues(): void
    {
        $model = new FolderAccessModel();

        self::assertSame([4, 9], $model->normalizeFolderIds(['4', 0, -3, '', '9']));
    }

    public function testNormalizeItemIdsFromArray(): void
    {
        $model = new FolderAccessModel();

        self::assertSame([8, 12], $model->normalizeItemIds(['8', '12', '12', 'not-an-id']));
    }

    public function testInvalidItemFolderSqlColumnFailsClosed(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(' AND 1 = 0', $model->getItemFolderSqlConstraint('i.id_tree;DROP', 7));
    }

    /**
     * Guards the personal-folder fix: the deny-list must restrict to top-level
     * personal roots (parent_id = 0) in all three exclusion sites, so a user's
     * own personal subfolders are not misclassified as another user's root.
     */
    public function testPersonalFolderFiltersOnlyExcludeOtherUsersRoots(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/api/Model/FolderAccessModel.php');
        self::assertIsString($source);

        self::assertSame(3, substr_count($source, 'other_personal.parent_id = 0'));
    }

    public function testUnprivilegedSharedFolderHasNoManagementCapabilities(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(
            $this->allManagementCapabilities(false),
            $model->getFolderManagementCapabilities(
                userData: $this->userData(),
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
    }

    public function testAdministratorAndManagerPassTheGlobalManagementGate(): void
    {
        $model = new FolderAccessModel();

        foreach ([
            'administrator' => ['is_admin' => 1],
            'manager' => ['is_manager' => 1],
            'manage-all user' => ['user_can_manage_all_users' => 1],
            'root-folder creator' => ['user_can_create_root_folder' => 1],
        ] as $label => $privilege) {
            self::assertSame(
                $this->allManagementCapabilities(true),
                $model->getFolderManagementCapabilities(
                    userData: $this->userData($privilege),
                    isPersonal: false,
                    isPersonalRoot: false,
                    isReadOnly: false,
                    hasEffectiveAccess: true,
                    enableUserCanCreateFolders: false
                ),
                $label
            );
        }
    }

    public function testGlobalSettingEnablesSharedFolderManagement(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(
            $this->allManagementCapabilities(true),
            $model->getFolderManagementCapabilities(
                userData: $this->userData(),
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: true
            )
        );
    }

    public function testPersonalFolderDoesNotRequireGlobalManagementPrivilege(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(
            $this->allManagementCapabilities(true),
            $model->getFolderManagementCapabilities(
                userData: $this->userData(),
                isPersonal: true,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
    }

    public function testPersonalRootOnlyAllowsCreatingASubfolder(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(
            [
                'can_create_subfolder' => true,
                'can_rename_folder' => false,
                'can_move_folder' => false,
                'can_delete_folder' => false,
            ],
            $model->getFolderManagementCapabilities(
                userData: $this->userData(),
                isPersonal: true,
                isPersonalRoot: true,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
    }

    public function testReadOnlyOrInaccessibleFolderHasNoManagementCapabilities(): void
    {
        $model = new FolderAccessModel();
        $admin = $this->userData(['is_admin' => 1]);

        // Read-only folder: nothing can be managed, even by an administrator.
        self::assertSame(
            $this->allManagementCapabilities(false),
            $model->getFolderManagementCapabilities(
                userData: $admin,
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: true,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
        // Folder not effectively accessible: same outcome.
        self::assertSame(
            $this->allManagementCapabilities(false),
            $model->getFolderManagementCapabilities(
                userData: $admin,
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: false,
                enableUserCanCreateFolders: false
            )
        );
    }

    public function testApiCrudPermissionsDisableOnlyTheirFolderOperations(): void
    {
        $model = new FolderAccessModel();

        self::assertSame(
            [
                'can_create_subfolder' => false,
                'can_rename_folder' => true,
                'can_move_folder' => true,
                'can_delete_folder' => true,
            ],
            $model->getFolderManagementCapabilities(
                userData: $this->userData(['is_admin' => 1, 'allowed_to_create' => 0]),
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
        self::assertSame(
            [
                'can_create_subfolder' => true,
                'can_rename_folder' => false,
                'can_move_folder' => false,
                'can_delete_folder' => true,
            ],
            $model->getFolderManagementCapabilities(
                userData: $this->userData(['is_admin' => 1, 'allowed_to_update' => 0]),
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
        self::assertSame(
            [
                'can_create_subfolder' => true,
                'can_rename_folder' => true,
                'can_move_folder' => true,
                'can_delete_folder' => false,
            ],
            $model->getFolderManagementCapabilities(
                userData: $this->userData(['is_admin' => 1, 'allowed_to_delete' => 0]),
                isPersonal: false,
                isPersonalRoot: false,
                isReadOnly: false,
                hasEffectiveAccess: true,
                enableUserCanCreateFolders: false
            )
        );
    }
}
