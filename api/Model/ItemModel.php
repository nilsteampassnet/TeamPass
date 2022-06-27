<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass API
 *
 * @file      ItemModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
require_once PROJECT_ROOT_PATH . "/Model/Database.php";
 
class ItemModel extends Database
{


    /**
     * Get the list of items to return
     *
     * @param string $sqlExtra
     * @param integer $limit
     * @param string $userPrivateKey
     * @param integer $userId
     * @return string
     */
    public function getItems(string $sqlExtra, int $limit, string $userPrivateKey, int $userId): string
    {
        $rows = $this->select(
            "SELECT id, label, description, pw, url, id_tree, login, email, viewed_no, fa_icon, inactif, perso 
            FROM ".prefixTable('items')."".
            $sqlExtra . 
            " ORDER BY id ASC" .
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
                ]
            );
        }

        return $ret;
    }
    //end getItems() 
}