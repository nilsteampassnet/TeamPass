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

    /**
     * Return the source of a method, from its signature to the next one.
     * Enough to assert statement ordering inside a single method.
     */
    private function extractMethodBody(string $source, string $signature): string
    {
        $start = strpos($source, $signature);
        self::assertIsInt($start, "Method '$signature' not found");

        $next = strpos($source, 'function ', $start + strlen($signature));

        return $next === false
            ? substr($source, $start)
            : substr($source, $start, $next - $start);
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
    // folder/writableFolders — tree ordering
    // -------------------------------------------------------------------------

    public function testWritableFoldersIsOrderedByTreePosition(): void
    {
        // The flat list must stay in MPTT pre-order so a client can rebuild the
        // hierarchy — sibling order included — from a single call.
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');

        self::assertStringContainsString(
            'ORDER BY nt.nleft ASC',
            $controller,
            'writableFolders must return the folders in tree order (nleft), not alphabetically'
        );
        self::assertStringContainsString(
            "'position' => (int) \$row['nleft']",
            $controller,
            'writableFolders must expose the tree position so clients can re-sort'
        );
    }

    public function testWritableFoldersKeepsPersonalFolderLabelMapping(): void
    {
        // A personal root folder is stored with the user id as title; the API
        // must keep exposing the login instead.
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');

        self::assertStringContainsString(
            "'label' => \$row['title'] === \$userId ? \$username : \$row['title']",
            $controller,
            'writableFolders must map the personal root folder title to the user login'
        );
    }

    // -------------------------------------------------------------------------
    // Folder complexity exposure (listFolders + writableFolders)
    // -------------------------------------------------------------------------

    /**
     * Both folder listings must expose the folder complexity level so a client
     * can generate a compliant password before calling item/create.
     *
     * The level is read through the shared FolderModel::getComplexityLevels()
     * prefetch: a folder carrying no rule (personal root) has no misc row and
     * must still be returned, with 0.
     */
    public function testFolderListingsExposeComplexity(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');
        $writable = $this->extractMethodBody($controller, 'public function writableFoldersAction(array $userData)');

        self::assertStringContainsString(
            'FolderModel::getComplexityLevels($userFolders)',
            $writable,
            'writableFolders must resolve the complexity levels through the shared prefetch'
        );
        self::assertStringContainsString(
            "'complexity' => \$complexityLevels[\$folderId] ?? 0",
            $writable,
            'writableFolders must expose the folder complexity level, defaulting to 0'
        );

        $model = $this->readSource('/app/api/Model/FolderModel.php');
        self::assertSame(
            2,
            substr_count($model, "'complexity' => \$complexityLevels["),
            'listFolders must expose the complexity on both root nodes and children'
        );
        self::assertStringContainsString(
            'public static function getComplexityLevels(?array $foldersId = null): array',
            $model,
            'the complexity prefetch must stay shared between both folder listings'
        );
    }

    // -------------------------------------------------------------------------
    // OpenAPI contract
    // -------------------------------------------------------------------------

    public function testOpenApiDocumentsFolderComplexity(): void
    {
        $spec = json_decode($this->readSource('/app/api/openapi.json'), true);
        self::assertIsArray($spec);

        foreach (['WritableFolder', 'FolderNode'] as $schema) {
            $props = $spec['components']['schemas'][$schema]['properties'] ?? [];
            self::assertArrayHasKey(
                'complexity',
                $props,
                $schema . ' must document the folder complexity level'
            );
        }
    }

    public function testOpenApiDocumentsWritableFolderPosition(): void
    {
        $spec = json_decode($this->readSource('/app/api/openapi.json'), true);
        self::assertIsArray($spec);

        $props = $spec['components']['schemas']['WritableFolder']['properties'] ?? [];
        self::assertArrayHasKey(
            'position',
            $props,
            'WritableFolder must document the tree position field'
        );
    }

    public function testFolderManagementCapabilitiesReuseTheMutationGate(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/FolderController.php');
        $model = $this->readSource('/app/api/Model/FolderModel.php');

        self::assertStringContainsString(
            'getFolderManagementCapabilities(',
            $controller,
            'writableFolders must use the authoritative capability evaluator'
        );

        // Asserted per mutation rather than by counting occurrences: a future
        // folder mutation reusing the gate must not make this sentinel fail.
        foreach ([
            'public function createFolder(',
            'public function updateFolder(array $data, array $userData): array',
            'public function deleteFolder(int $folderId, array $userData): array',
        ] as $signature) {
            self::assertStringContainsString(
                'hasFolderManagementPrivilege(',
                $this->extractMethodBody($model, $signature),
                "$signature must reuse the same global management gate"
            );
        }
    }

    /**
     * The response contract of writableFolders is public documentation, not just
     * openapi.json: docs/api/api-basic.md is the docsify site users actually read.
     * A field added to the endpoint without a doc entry silently makes it wrong.
     */
    public function testWritableFolderCapabilitiesArePubliclyDocumented(): void
    {
        $doc = $this->readSource('/docs/api/api-basic.md');

        foreach ([
            'is_personal',
            'is_personal_root',
            'can_create_subfolder',
            'can_rename_folder',
            'can_move_folder',
            'can_delete_folder',
        ] as $field) {
            self::assertStringContainsString(
                '`' . $field . '`',
                $doc,
                "docs/api/api-basic.md must document the $field field"
            );
        }

        // The whole point of the split: item rights and folder capabilities are
        // different families, and can_move_folder qualifies the source only.
        self::assertStringContainsString('Item rights vs folder capabilities', $doc);
        self::assertStringContainsString('source folder only', $doc);
    }

    /**
     * The server version fields are returned in the response body of both
     * authentication routes — clients rely on them to adapt to the instance.
     */
    public function testServerVersionFieldsArePubliclyDocumented(): void
    {
        $doc = $this->readSource('/docs/api/api-basic.md');

        foreach (['teampass_version', 'teampass_version_major', 'teampass_version_minor'] as $field) {
            self::assertStringContainsString(
                '`' . $field . '`',
                $doc,
                "docs/api/api-basic.md must document the $field field"
            );
        }
    }

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

    // -------------------------------------------------------------------------
    // Web ↔ API rights parity
    // -------------------------------------------------------------------------

    /**
     * The API must resolve multiple role types with the SAME implementation as the
     * web (evaluateFolderAccesLevel), not a hasReadOnly/hasWrite heuristic which
     * made R + W writable — the opposite of the documented least-permissive rule.
     */
    public function testAccessLevelResolutionReusesTheSharedEvaluator(): void
    {
        $model = $this->readSource('/app/api/Model/FolderAccessModel.php');

        self::assertStringContainsString(
            'evaluateFolderAccesLevel(',
            $model,
            'FolderAccessModel must fold role types through the shared web resolver'
        );
        self::assertStringNotContainsString(
            '$hasReadOnly && !$hasWrite',
            $model,
            'the write-wins heuristic must not come back'
        );
        self::assertStringContainsString(
            "getFolderAccessLevelForUser(\$folderId, \$userId)['type'] === 'R'",
            $model,
            'is_readonly must mean "resolved type is R"'
        );
    }

    /**
     * AD/LDAP-sourced roles must count everywhere: filtering on source = "manual"
     * both hid folders and made a role-granted R folder look unrestricted.
     */
    public function testRoleLookupsDoNotFilterOnManualSource(): void
    {
        foreach ([
            '/app/api/Model/FolderAccessModel.php',
            '/app/api/Model/AuthModel.php',
        ] as $file) {
            self::assertStringNotContainsString(
                'source = "manual"',
                $this->readSource($file),
                $file . ' must take AD-sourced roles into account'
            );
        }

        // The cache rebuild path must select both sources
        $router = $this->readSource('/app/api/index.php');
        self::assertStringContainsString(
            'AS roles_from_ad_groups',
            $router,
            'the folders cache rebuild must load AD roles'
        );
    }

    /**
     * ND / NE / NDNE are writable but restricted: item update and delete must be
     * gated on the granular rights, not on the read-only boolean.
     */
    public function testItemWritesUseGranularFolderRights(): void
    {
        $controller = $this->readSource('/app/api/Controller/Api/ItemController.php');

        self::assertStringContainsString(
            'canEditInFolder(',
            $controller,
            'item update must be blocked on NE / NDNE folders'
        );
        self::assertStringContainsString(
            'canDeleteInFolder(',
            $controller,
            'item delete must be blocked on ND / NDNE folders'
        );
    }

    /**
     * An administrator has access to every shared folder, and a folder listed in
     * users_groups_forbidden must be removed from the list whatever granted it.
     */
    public function testFoldersListHandlesAdminAndForbiddenFolders(): void
    {
        $authModel = $this->readSource('/app/api/Model/AuthModel.php');

        self::assertStringContainsString(
            "groupes_interdits",
            $authModel,
            'buildUserFoldersList must subtract the explicitly denied folders'
        );
        self::assertStringContainsString(
            'personal_folder = %i',
            $authModel,
            'the admin branch must load every shared folder'
        );

        $router = $this->readSource('/app/api/index.php');
        foreach (['u.admin', 'AS groupes_interdits'] as $needle) {
            self::assertStringContainsString(
                $needle,
                $router,
                'the folders cache rebuild must resolve like the /authorize path'
            );
        }
    }

    /**
     * Folder create/update must be atomic: a folder row without its complexity or
     * its roles_values entries is silently unusable.
     */
    public function testFolderWritesAreTransactional(): void
    {
        $manager = $this->readSource('/app/sources/folders.class.php');

        // createFolder + updateFolder + deleteFolders + refreshCacheForUsersWithSimilarRoles
        self::assertSame(
            4,
            substr_count($manager, 'DB::startTransaction();'),
            'createFolder, updateFolder and deleteFolders must each be transactional'
        );

        // refreshCacheForUsersWithSimilarRoles opens its OWN transaction: MySQL commits
        // implicitly on a nested BEGIN, so it must run strictly after the commit.
        $createBody = $this->extractMethodBody($manager, 'private function createFolder');
        $commitPos = strpos($createBody, 'DB::commit();');
        $refreshPos = strpos($createBody, 'refreshCacheForUsersWithSimilarRoles(');
        self::assertIsInt($commitPos);
        self::assertIsInt($refreshPos);
        self::assertLessThan(
            $refreshPos,
            $commitPos,
            'the cache refresh must not run inside the create transaction (nested BEGIN)'
        );

        // Same for the tree rebuild, which locks the whole nested_tree table
        $rebuildPos = strpos($createBody, 'rebuildFolderTree(');
        self::assertIsInt($rebuildPos);
        self::assertLessThan(
            $rebuildPos,
            $commitPos,
            'the tree rebuild must run after the commit'
        );

        self::assertStringContainsString(
            "return ['error' => true, 'newId' => null, 'db_error' => true];",
            $manager,
            'a rolled-back create must be reported as an infrastructure failure, not a validation error'
        );

        $model = $this->readSource('/app/api/Model/FolderModel.php');
        self::assertStringContainsString(
            "(\$creationStatus['db_error'] ?? false) === true",
            $model,
            'a db_error must map to 500, never to the 422/403 validation branches'
        );
    }

    /**
     * The documented private-folder example sends no access_rights; the model must
     * default to W instead of failing the enum check.
     */
    public function testCreateDefaultsAccessRightsToWrite(): void
    {
        $model = $this->readSource('/app/api/Model/FolderModel.php');

        self::assertStringContainsString(
            "empty(\$inputData['access_rights']) === true ? 'W' : \$inputData['access_rights']",
            $model,
            'an omitted access_rights must fall back to W'
        );
    }

    public function testOpenApiPrivateFolderRequirementsAreConditional(): void
    {
        $spec = json_decode($this->readSource('/app/api/openapi.json'), true);
        self::assertIsArray($spec);

        $schema = $spec['components']['schemas']['FolderCreateBody'];

        self::assertSame(
            ['title'],
            $schema['required'],
            'only title is unconditionally required — parent_id/complexity depend on private'
        );
        self::assertArrayHasKey(
            'allOf',
            $schema,
            'the parent_id/complexity requirement must be expressed conditionally'
        );
        self::assertSame(
            ['parent_id', 'complexity'],
            $schema['allOf'][0]['else']['required'],
            'a shared folder still requires parent_id and complexity'
        );
    }
}
