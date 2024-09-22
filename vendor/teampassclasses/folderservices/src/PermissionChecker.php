<?php
namespace TeampassClasses\FolderServices;


use DB;

class PermissionChecker
{
    public function isUserAllowedToCreate(int $userId, int $parentId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }
        $accessibleFolders = $this->getUserAccessibleFolders($userId);
        return in_array($parentId, $accessibleFolders);
    }

    private function getUserAccessibleFolders(int $userId): array
    {
        return DB::queryFirstColumn('SELECT folder_id FROM accessible_folders WHERE user_id = %i', $userId);
    }
}
