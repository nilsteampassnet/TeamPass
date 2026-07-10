<?php

declare(strict_types=1);

/**
 * Pure functions extracted from the folder API decision logic for unit testing.
 *
 * These helpers capture the DB-free decisions embedded in:
 *   - app/api/Model/FolderModel.php      (createFolder / updateFolder / deleteFolder)
 *   - app/api/Controller/Api/FolderController.php (createAction required params)
 *   - app/sources/folders.class.php      (FolderManager::deleteFolders recycle-bin payload,
 *                                          canCreateFolder gate)
 *   - app/sources/folders.queries.php    (web update_folder / delete_folders gates)
 *
 * They contain no DB / session / filesystem dependency and can be tested in full
 * isolation. FolderEndpointsRegressionTest keeps the production source and these
 * replicas structurally aligned (static sentinels).
 *
 * MAINTENANCE: keep in sync with their originals. If the production logic
 * changes, update this file and its tests (FolderLogicTest) accordingly.
 */

/**
 * Global folder create/update/delete permission gate.
 *
 * Mirrors the expression used by FolderModel::updateFolder/deleteFolder,
 * FolderManager::canCreateFolder and the web update_folder/delete_folders
 * handlers: a personal folder is always allowed; otherwise the user needs one
 * of the privileged flags.
 */
function folderGlobalPermissionGate(
    bool $isPersonal,
    bool $isAdmin,
    bool $isManager,
    bool $canManageAllUsers,
    bool $enableUserCanCreateFolders,
    bool $canCreateRootFolder
): bool {
    return $isPersonal
        || $isAdmin
        || $isManager
        || $canManageAllUsers
        || $enableUserCanCreateFolders
        || $canCreateRootFolder;
}

/**
 * Is this node a personal ROOT folder?
 *
 * Personal roots (parent_id = 0, personal_folder = 1, title = owner user id)
 * can never be renamed, moved or deleted through the API.
 */
function folderIsPersonalRoot(int $parentId, int $personalFolder, string $title, int $userId): bool
{
    return $parentId === 0 && $personalFolder === 1 && $title === (string) $userId;
}

/**
 * Which create parameters are required, given the private flag (§3.2)?
 *
 * A shared folder needs title + parent_id + complexity; a private folder only
 * needs a title (parent defaults to the personal root, complexity is skipped).
 *
 * @return string[]
 */
function folderCreateRequiredParams(bool $private): array
{
    return $private === true ? ['title'] : ['title', 'parent_id', 'complexity'];
}

/**
 * A valid complexity (password strength) is required only for shared targets.
 */
function folderComplexityRequired(bool $isPersonalTarget): bool
{
    return $isPersonalTarget === false;
}

/**
 * Is the given complexity value an accepted password-strength level?
 *
 * @param array<int> $validStrengths
 */
function folderIsValidComplexity(int $complexity, array $validStrengths): bool
{
    return in_array($complexity, $validStrengths, true);
}

/**
 * Is the given access-right token valid?
 */
function folderIsValidAccessRight(string $accessRight): bool
{
    return in_array($accessRight, ['R', 'W', 'NE', 'ND', 'NDNE'], true);
}

/**
 * Resolve the create target (personal vs shared) and its validation status (§3.2).
 *
 * Returns ['status' => <code>, 'is_personal' => 0|1] where <code> is one of:
 *   - 'ok'                  → proceed with the resolved is_personal
 *   - 'pf_disabled'         → private requested but personal folders disabled (403)
 *   - 'no_personal_root'    → private requested but no personal root exists (403)
 *   - 'parent_not_personal' → private + a parent that is not inside the caller's
 *                             own personal tree (422)
 *
 * personal_folder is NEVER taken from the client — it is derived here.
 *
 * @return array{status: string, is_personal: int}
 */
function folderResolveCreateTarget(
    bool $private,
    bool $pfEnabled,
    bool $hasPersonalRoot,
    bool $parentProvided,
    ?int $parentPersonalFolder,
    bool $parentInsideOwnPersonalTree
): array {
    if ($private === true) {
        if ($pfEnabled === false) {
            return ['status' => 'pf_disabled', 'is_personal' => 0];
        }
        if ($hasPersonalRoot === false) {
            return ['status' => 'no_personal_root', 'is_personal' => 0];
        }
        if ($parentProvided === true
            && ($parentPersonalFolder !== 1 || $parentInsideOwnPersonalTree === false)
        ) {
            return ['status' => 'parent_not_personal', 'is_personal' => 0];
        }
        return ['status' => 'ok', 'is_personal' => 1];
    }

    // Parent-derived: a parent inside the caller's personal tree yields a personal folder
    $isPersonal = ($parentProvided === true && $parentPersonalFolder === 1) ? 1 : 0;
    return ['status' => 'ok', 'is_personal' => $isPersonal];
}

/**
 * Structural validation of a folder move.
 *
 * Returns one of: 'ok', 'circular', 'descendant', 'cross_domain'.
 *
 * @param array<int> $descendants Ids of the moved folder's descendants
 */
function folderMoveValidation(
    int $folderId,
    int $newParentId,
    array $descendants,
    int $currentPersonal,
    int $newPersonal
): string {
    if ($newParentId === $folderId) {
        return 'circular';
    }
    if ($newParentId !== 0
        && in_array($newParentId, array_map('intval', $descendants), true) === true
    ) {
        return 'descendant';
    }
    if ($currentPersonal !== $newPersonal) {
        return 'cross_domain';
    }
    return 'ok';
}

/**
 * Is the requested complexity below the parent folder's ceiling?
 *
 * The ceiling only applies to shared targets and non-privileged users
 * (admins and managers-with-manage-all bypass it).
 */
function folderComplexityCeilingViolated(
    int $newComplexity,
    ?int $parentComplexity,
    bool $isPersonalTarget,
    bool $isAdmin,
    bool $isManager,
    bool $canManageAllUsers
): bool {
    if ($isPersonalTarget === true
        || $isAdmin === true
        || ($isManager === true && $canManageAllUsers === true)
    ) {
        return false;
    }
    if ($parentComplexity === null) {
        return false;
    }
    return $newComplexity < $parentComplexity;
}

/**
 * Does the partial-update payload contain at least one updatable field?
 *
 * @param array<string, mixed> $params
 * @param array<int, string>   $updatableFields
 */
function folderUpdateHasUpdatableField(array $params, array $updatableFields): bool
{
    foreach ($updatableFields as $field) {
        if (array_key_exists($field, $params) === true) {
            return true;
        }
    }
    return false;
}

/**
 * Build the recycle-bin "folder_deleted" JSON payload (pure).
 *
 * The key set must stay a superset of what tpParseFolderDeletedValeur() in
 * utilities.queries.php restores, otherwise the recycle bin breaks.
 *
 * @param array<string, mixed> $node A nested_tree node (id, parent_id, title, …)
 * @return array<string, mixed>
 */
function folderBuildRecycleBinData(array $node, int $deletedBy, string $deletedByLogin): array
{
    $data = [
        'id' => (int) $node['id'],
        'parent_id' => (int) $node['parent_id'],
        'title' => (string) $node['title'],
        'nleft' => (int) $node['nleft'],
        'nright' => (int) $node['nright'],
        'nlevel' => (int) $node['nlevel'],
        'bloquer_creation' => (int) ($node['bloquer_creation'] ?? 0),
        'bloquer_modification' => (int) ($node['bloquer_modification'] ?? 0),
        'personal_folder' => (int) ($node['personal_folder'] ?? 0),
        'renewal_period' => (int) ($node['renewal_period'] ?? 0),
        'categories' => (string) ($node['categories'] ?? ''),
        'deleted_by' => $deletedBy,
        'deleted_by_login' => $deletedByLogin,
    ];
    if (isset($node['fa_icon']) === true) {
        $data['fa_icon'] = (string) $node['fa_icon'];
    }
    if (isset($node['fa_icon_selected']) === true) {
        $data['fa_icon_selected'] = (string) $node['fa_icon_selected'];
    }
    if (isset($node['is_template']) === true) {
        $data['is_template'] = (int) $node['is_template'];
    }
    return $data;
}

/**
 * Should a descendant node be skipped during deletion?
 *
 * Personal root folders (parent_id = 0, personal_folder = 1) and orphan nodes
 * (parent_id < 0) are never deleted — defense in depth.
 */
function folderDeleteShouldSkipNode(int $parentId, int $personalFolder): bool
{
    if ($parentId === 0 && $personalFolder === 1) {
        return true;
    }
    return $parentId < 0;
}
