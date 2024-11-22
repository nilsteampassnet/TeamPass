<?php
namespace TeampassClasses\FolderServices;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      FolderCreationService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

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
