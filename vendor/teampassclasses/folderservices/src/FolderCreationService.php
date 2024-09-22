<?php
namespace TeampassClasses\FolderServices;

use DB;

class FolderCreationService
{
    private $lang;
    private $settings;

    public function __construct($lang, $settings)
    {
        $this->lang = $lang;
        $this->settings = $settings;
    }

    public function validateFolderParameters(array $params): array
    {
        if (is_numeric($params['title'])) {
            return ['error' => true, 'message' => $this->lang->get('error_only_numbers_in_folder_name')];
        }
        return ['error' => false];
    }

    public function createFolder(array $params): int
    {
        DB::insert(prefixTable('nested_tree'), [
            'parent_id' => $params['parent_id'],
            'title' => $params['title'],
            'personal_folder' => $params['personal_folder'] ?? 0,
        ]);
        return DB::insertId();
    }

    public function finalizeCreation(int $newId, array $params)
    {
        // Add any additional steps needed to finalize the folder creation
    }
}
