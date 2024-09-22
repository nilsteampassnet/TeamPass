<?php
namespace TeampassClasses\FolderServices;

use DB;

class FolderComplexityService
{
    public function checkComplexityLevel(int $folderId, int $complexity): bool
    {
        $parentComplexity = DB::queryFirstField('SELECT valeur FROM complexity_levels WHERE folder_id = %i', $folderId);
        return $complexity >= $parentComplexity;
    }

    public function addComplexity(int $folderId, int $complexity)
    {
        DB::insert(prefixTable('misc'), [
            'type' => 'complex',
            'intitule' => $folderId,
            'valeur' => $complexity,
            'created_at' => time(),
        ]);
    }
}
