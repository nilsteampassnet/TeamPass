<?php
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
 * @version    API
 *
 * @file      ItemModel.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
use TeampassClasses\NestedTree\NestedTree;

require_once API_ROOT_PATH . "/Model/Database.php";

class ItemModel extends Database
{


    /**
     * Get the list of items to return
     *
     * @param string $sqlExtra
     * @param integer $limit
     * @param string $userPrivateKey
     * @param integer $userId
     * 
     * @return array
     */
    public function getItems(string $sqlExtra, int $limit, string $userPrivateKey, int $userId): array
    {
        $rows = $this->select(
            "SELECT i.id, label, description, i.pw, i.url, i.id_tree, i.login, i.email, i.viewed_no, i.fa_icon, i.inactif, i.perso, t.title as folder_label
            FROM ".prefixTable('items')." as i
            LEFT JOIN ".prefixTable('nested_tree')." as t ON (t.id = i.id_tree) ".
            $sqlExtra . 
            " ORDER BY i.id ASC" .
            //($limit > 0 ? " LIMIT ?". ["i", $limit] : '')
            ($limit > 0 ? " LIMIT ". $limit : '')
        );
        $ret = [];
        foreach ($rows as $row) {
            $userKey = $this->select(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE user_id = '.$userId.' AND object_id = '.$row['id']                
            );
            if (count($userKey) === 0 || empty($row['pw']) === true) {
                // No share key found
                // Exit this item
                continue;
            }

            // Get password
            try {
                $pwd = base64_decode(
                    (string) doDataDecryption(
                        $row['pw'],
                        decryptUserObjectKey(
                            $userKey[0]['share_key'],
                            $userPrivateKey
                        )
                    )
                );
            } catch (Exception $e) {
                // Password is not encrypted
                // deepcode ignore ServerLeak: No important data
                echo "ERROR";
            }
            

            // get path to item
            $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $arbo = $tree->getPath($row['id_tree'], false);
            $path = '';
            foreach ($arbo as $elem) {
                if (empty($path) === true) {
                    $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                } else {
                    $path .= '/' . htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                }
            }

            array_push(
                $ret,
                [
                    'id' => (int) $row['id'],
                    'label' => $row['label'],
                    'description' => $row['description'],
                    'pwd' => $pwd,
                    'url' => $row['url'],
                    'login' => $row['login'],
                    'email' => $row['email'],
                    'viewed_no' => (int) $row['viewed_no'],
                    'fa_icon' => $row['fa_icon'],
                    'inactif' => (int) $row['inactif'],
                    'perso' => (int) $row['perso'],
                    'id_tree' => (int) $row['id_tree'],
                    'folder_label' => $row['folder_label'],
                    'path' => empty($path) === true ? '' : $path,
                ]
            );
        }

        return $ret;
    }
    //end getItems() 

    /**
     * Add item
     *
     * @return bool
     */
    public function addItem(string $idTree, string $userName, string $hostname, string $password) : bool
    {
        // TODO ecrire
        
        return true;
    }
}