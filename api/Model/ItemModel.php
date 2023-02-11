<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      ItemModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
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
            ($limit > 0 ? " LIMIT ?". ["i", $limit] : '')
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
                $pwd = '';
            } else {
                $pwd = base64_decode(doDataDecryption(
                    $row['pw'],
                    decryptUserObjectKey(
                        $userKey[0]['share_key'],
                        $userPrivateKey
                    )
                ));
            }

            // get path to item
            require_once API_ROOT_PATH. '/../includes/libraries/Tree/NestedTree/NestedTree.php';
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $arbo = $tree->getPath($row['id_tree'], false);
            $path = '';
            foreach ($arbo as $elem) {
                if (empty($path) === true) {
                    $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                } else {
                    $path .= '>' . htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
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
}