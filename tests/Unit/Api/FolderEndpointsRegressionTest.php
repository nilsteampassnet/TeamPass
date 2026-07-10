<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: folder API endpoints (create fix + update + delete).
 *
 * Static regression guards that lock the structural invariants of the folder
 * lifecycle API so future evolutions cannot silently regress them:
 *
 *  - create fix C1–C4 (full lifecycle $options, server-derived personal_folder,
 *    read-only/foreign-parent guards, folder_created event);
 *  - FolderManager gains updateFolder()/deleteFolders() with a guarded session
 *    access and a transaction-wrapped, recycle-bin-compatible delete;
 *  - the two new endpoints are PUT-only / DELETE-only with the right Allow header
 *    and mark functional activity;
 *  - the security invariants of the plan (§7): personal_folder never client
 *    controlled, personal roots protected, foreign personal trees unreachable,
 *    cross-domain / descendant moves rejected;
 *  - the router CRUD gate still maps update/delete;
 *  - openapi.json documents both new paths.
 */
class FolderEndpointsRegressionTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    // -------------------------------------------------------------------------
    // folder/create — C1..C4 fixes
    // -------------------------------------------------------------------------

    public function testCreatePassesFullLifecycleOptions(): void
    {
        // C1: without $options the tree/roles/cache steps are all skipped.
        $model = $this->readSource('/app/api/Model/FolderModel.php');

        foreach ([
            "'rebuildFolderTree' => true",
            "'manageFolderPermissions' => true",
            "'refreshCacheForUsersWithSimilarRoles' => true",
        ] as $needle) {
            self::assertStringContainsString(
                $needle,
                $model,
                "FolderModel::createFolder must pass $needle so the folder is fully wired (C1)"
            );
        }

        self::assertStringContainsString(
            'createNewFolder($params, $options)',
            $model,
            'FolderModel::createFolder must pass the options array to createNewFolder (C1)'
        );
    }

    public function testCreateDerivesPersonalFolderServerSide(): void
    {
        // C2 + security invariant 1: personal_folder is derived, never trusted.
        $model = $this->readSource('/app/api/Model/FolderModel.php');

        self::assertStringContainsString(
            "'personal_folder' => (int) \$isPersonal",
            $model,
            'FolderModel::createFolder must set personal_folder from the derived value (C2)'
        );
        self::assertStringContainsString(
            'isFolderInsideAllowedPersonalRoot',
            $model,
            'FolderModel must derive/validate personal-tree membership server-side'
        );
    }

    public function testCreateGuardsReadOnlyAndForeignParent(): void
    {
        // C3 + security invariant 2/5
        $model = $this->readSource('/app/api/Model/FolderModel.php');

        self::assertStringContainsString(
            'isFolderReadOnlyForUser($parent_id',
            $model,
            'FolderModel::createFolder must reject a read-only parent (C3)'
        );
        self::assertStringContainsString(
            'parent folder is not accessible',
            $model,
            'FolderModel::createFolder must reject a parent inside another user personal tree (C3)'
        );
    }

    public function testCreateEmitsFolderCreatedEvent(): void
    {
        // C4
        $model = $this->readSource('/app/api/Model/FolderModel.php');
        self::assertStringContainsString(
            "emitFolderEvent('created'",
            $model,
            'FolderModel::createFolder must emit a folder_created WebSocket event (C4)'
        );
    }

    public function testCreateSupportsPrivateFlag(): void
    {
        $model = $this->readSource('/app/api/Model/FolderModel.php');
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');

        self::assertStringContainsString('bool $private = false', $model, 'createFolder must accept the private flag');
        self::assertStringContainsString('int $pf_enabled = 0', $model, 'createFolder must receive pf_enabled');
        self::assertStringContainsString(
            "filter_var(\$arrQueryStringParams['private']",
            $controller,
            'createAction must read the optional private flag'
        );
        // Private folders relax the required-params list (§3.2)
        self::assertStringContainsString(
            "\$private === true ? ['title'] : ['title', 'parent_id', 'complexity']",
            $controller,
            'createAction must not require parent_id/complexity for a private folder'
        );
    }

    // -------------------------------------------------------------------------
    // FolderManager — new engine methods
    // -------------------------------------------------------------------------

    public function testFolderManagerExposesUpdateAndDeleteEngines(): void
    {
        $class = $this->readSource('/app/sources/folders.class.php');

        self::assertStringContainsString(
            'public function updateFolder(array $params, array $options = []): array',
            $class,
            'FolderManager must expose updateFolder() so web and API converge'
        );
        self::assertStringContainsString(
            'public function deleteFolders(array $folderIds, array $context): array',
            $class,
            'FolderManager must expose deleteFolders()'
        );
    }

    public function testSessionAccessIsGuardedUnderApiSapi(): void
    {
        // Security invariant 9: no session creation under the API SAPI.
        $class = $this->readSource('/app/sources/folders.class.php');
        self::assertStringContainsString(
            'session_status() === PHP_SESSION_ACTIVE',
            $class,
            'FolderManager::rebuildFolderTree must guard the SessionManager call (no session under API SAPI)'
        );
    }

    public function testDeleteIsTransactionalAndRecycleBinCompatible(): void
    {
        // Security invariant 7: recycle-bin JSON byte-compatible with the restore parser.
        $class = $this->readSource('/app/sources/folders.class.php');

        self::assertStringContainsString('DB::startTransaction();', $class, 'deleteFolders must be transactional');
        self::assertStringContainsString('DB::rollback();', $class, 'deleteFolders must roll back on failure');
        self::assertStringContainsString('DB::commit();', $class, 'deleteFolders must commit');

        self::assertStringContainsString("'folder_deleted'", $class, 'deleteFolders must write the folder_deleted recycle-bin row');
        self::assertStringContainsString(
            'JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES',
            $class,
            'deleteFolders must encode the recycle-bin payload with the same flags as the web handler'
        );

        // Every key the restore parser (tpParseFolderDeletedValeur) relies on must be produced.
        foreach ([
            "'id' =>", "'parent_id' =>", "'title' =>", "'nleft' =>", "'nright' =>",
            "'nlevel' =>", "'bloquer_creation' =>", "'bloquer_modification' =>",
            "'personal_folder' =>", "'renewal_period' =>",
        ] as $key) {
            self::assertStringContainsString(
                $key,
                $class,
                "deleteFolders recycle-bin payload must contain $key (restore compatibility)"
            );
        }
    }

    public function testUpdateAndDeleteEmitWebSocketEvents(): void
    {
        $class = $this->readSource('/app/sources/folders.class.php');
        self::assertMatchesRegularExpression(
            "/emitFolderEvent\(\s*'updated'/",
            $class,
            'updateFolder must emit a folder_updated WebSocket event'
        );
        self::assertMatchesRegularExpression(
            "/emitFolderEvent\(\s*'deleted'/",
            $class,
            'deleteFolders must emit a folder_deleted WebSocket event'
        );
    }

    public function testUpdateRebuildsTreeNeverTouchesNleftManually(): void
    {
        // Security invariant 6: nleft/nright/nlevel only change through rebuild().
        $class = $this->readSource('/app/sources/folders.class.php');
        self::assertStringContainsString('$tree->rebuild();', $class, 'updateFolder/deleteFolders must rebuild the nested tree');
    }

    // -------------------------------------------------------------------------
    // FolderModel — update/delete adapters and their guards
    // -------------------------------------------------------------------------

    public function testModelAdaptersExist(): void
    {
        $model = $this->readSource('/app/api/Model/FolderModel.php');
        self::assertStringContainsString('public function updateFolder(array $data, array $userData): array', $model);
        self::assertStringContainsString('public function deleteFolder(int $folderId, array $userData): array', $model);
    }

    public function testUpdateProtectsPersonalRootAndRejectsCrossDomainAndDescendantMoves(): void
    {
        $model = $this->readSource('/app/api/Model/FolderModel.php');

        // Security invariant 3
        self::assertStringContainsString(
            'A personal root folder cannot be renamed or moved',
            $model,
            'updateFolder must reject renaming/moving a personal root'
        );
        // Cross-domain move guard (safer than the web behaviour)
        self::assertStringContainsString(
            'Moving a folder between personal and shared trees is not supported',
            $model,
            'updateFolder must reject personal<->shared moves'
        );
        // Descendant move guard (the web omits this and would corrupt the tree)
        self::assertStringContainsString(
            'A folder cannot be moved into one of its descendants',
            $model,
            'updateFolder must reject moving a folder under one of its descendants'
        );
        self::assertStringContainsString(
            'A folder cannot be moved into itself',
            $model,
            'updateFolder must reject a circular move'
        );
    }

    public function testDeleteProtectsPersonalRoot(): void
    {
        // Security invariant 3/4
        $model = $this->readSource('/app/api/Model/FolderModel.php');
        self::assertStringContainsString(
            'A personal root folder cannot be deleted',
            $model,
            'deleteFolder must reject deleting a personal root'
        );
    }

    public function testAdaptersEnforceAccessChecks(): void
    {
        // Security invariant 2/5: access + read-only + personal-tree checks on every id
        $model = $this->readSource('/app/api/Model/FolderModel.php');
        self::assertStringContainsString('canUseFolder(', $model, 'adapters must verify folder access');
        self::assertStringContainsString('isFolderReadOnlyForUser(', $model, 'adapters must reject read-only folders');
    }

    // -------------------------------------------------------------------------
    // FolderController — method gating & activity marking
    // -------------------------------------------------------------------------

    public function testUpdateActionIsPutOnly(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');
        self::assertStringContainsString('public function updateAction(array $userData): void', $controller);
        self::assertStringContainsString("strtoupper(\$requestMethod) === 'PUT'", $controller, 'update must accept PUT');
        self::assertStringContainsString("'Allow: PUT'", $controller, 'update must advertise Allow: PUT on 405');
    }

    public function testDeleteActionIsDeleteOnly(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');
        self::assertStringContainsString('public function deleteAction(array $userData): void', $controller);
        self::assertStringContainsString("strtoupper(\$requestMethod) === 'DELETE'", $controller, 'delete must accept DELETE');
        self::assertStringContainsString("'Allow: DELETE'", $controller, 'delete must advertise Allow: DELETE on 405');
    }

    public function testWriteActionsMarkFunctionalActivity(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');
        // create, update and delete each mark activity on success
        self::assertSame(
            3,
            substr_count($controller, 'markApiFunctionalActivity($userData)'),
            'create/update/delete must each mark functional activity on success'
        );
    }

    // -------------------------------------------------------------------------
    // Router CRUD gate — unchanged mapping (bootstrap)
    // -------------------------------------------------------------------------

    public function testRouterGateMapsUpdateAndDelete(): void
    {
        $bootstrap = $this->readSource('/app/api/inc/bootstrap.php');
        self::assertStringContainsString(
            "\$actionToPerform === 'update' && \$userData['allowed_to_update'] === 1",
            $bootstrap,
            'folder update must be gated by allowed_to_update'
        );
        self::assertStringContainsString(
            "\$actionToPerform === 'delete' && \$userData['allowed_to_delete'] === 1",
            $bootstrap,
            'folder delete must be gated by allowed_to_delete'
        );
    }

    // -------------------------------------------------------------------------
    // OpenAPI contract
    // -------------------------------------------------------------------------

    public function testOpenApiDocumentsNewPaths(): void
    {
        $spec = json_decode($this->readSource('/app/api/openapi.json'), true);
        self::assertIsArray($spec);

        self::assertArrayHasKey('/folder/update', $spec['paths'], 'openapi must document folder/update');
        self::assertArrayHasKey('put', $spec['paths']['/folder/update'], 'folder/update must be a PUT');

        self::assertArrayHasKey('/folder/delete', $spec['paths'], 'openapi must document folder/delete');
        self::assertArrayHasKey('delete', $spec['paths']['/folder/delete'], 'folder/delete must be a DELETE');

        // create keeps the private property
        $createProps = $spec['components']['schemas']['FolderCreateBody']['properties'] ?? [];
        self::assertArrayHasKey('private', $createProps, 'FolderCreateBody must document the private property');
    }
}
