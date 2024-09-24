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
 * @file      FolderComplexityService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

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
