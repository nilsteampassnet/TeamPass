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
 * @copyright 2009-2022 Teampass.net
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
            "SELECT id, label, description, pw, url, id_tree, login, email, viewed_no, fa_icon, inactif, perso
            FROM ".prefixTable('items')

            . $sqlExtra . " ORDER BY id ASC" .
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

            $champs = $this->select(
                'SELECT c.title, ci.data
                FROM ' . prefixTable('categories_items') . ' AS ci
                INNER JOIN ' . prefixTable('categories') . ' AS c
                ON ci.field_id = c.id
                WHERE item_id = '. $row['id']









            );

            if ((int) $row['inactif'] === 0) {
                if (count($champs) === 0) {
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
                            'perso' => (int) $row['perso']
                        ]
                    );
                } else {
                    $retChamps = array();

                    foreach ($champs as $champ) {
                        $retChamps[$champ['title']] = $champ['data'];
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
                            'champs' => $retChamps
                        ]
                    );
                }
            }
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

    public function getItem(string $itemId, string $userPrivateKey, int $userId, array $foldersList): array
    {
        $item = $this->select("SELECT id, label, pw, id_tree, login FROM " . prefixTable('items') . " WHERE id=" . $itemId )[0];

        if (in_array($item['id_tree'], $foldersList)) {
            $userKey = $this->select('SELECT share_key FROM ' . prefixTable('sharekeys_items') . ' WHERE user_id = '.$userId.' AND object_id = '.$item['id']);

            if (count($userKey) === 0 || empty($item['pw']) === true) {
                // No share key found
                $pwd = '';
            } else {
                $pwd = base64_decode(doDataDecryption(
                    $item['pw'],
                    decryptUserObjectKey(
                        $userKey[0]['share_key'],
                        $userPrivateKey
                    )
                ));
            }

            return [
                    'id' => $item['id'],
                    'label' => $item['label'],
                    'pwd' => $pwd,
                    'login' => $item['login']
            ];
        }

        return array();
    }

    public function getItemByLabel(string $itemLabel, string $userPrivateKey, int $userId, array $foldersList): array
    {
        $item = $this->select("SELECT id, label, pw, id_tree, login FROM " . prefixTable('items') . " WHERE label='" . $itemLabel . "'")[0];

        if (in_array($item['id_tree'], $foldersList)) {
            $userKey = $this->select('SELECT share_key FROM ' . prefixTable('sharekeys_items') . ' WHERE user_id = '.$userId.' AND object_id = '.$item['id'] );

            if (count($userKey) === 0 || empty($item['pw']) === true) {
                // No share key found
                $pwd = '';
            } else {
                $pwd = base64_decode(doDataDecryption(
                    $item['pw'],
                    decryptUserObjectKey(
                        $userKey[0]['share_key'],
                        $userPrivateKey
                    )
                ));
            }

            return [
                    'id' => $item['id'],
                    'label' => $item['label'],
                    'pwd' => $pwd,
                    'login' => $item['login']
            ];
        }

        return array();
    }
}
