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
use TeampassClasses\Language\Language;

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
     * Add an item
     *
     * @param integer $folderId
     * @param string $label
     * @param string $password
     * @param string $description
     * @param string $login
     * @param string $email
     * @param string $url
     * @param string $tags
     * @param string $anyone_can_modify
     * 
     * @return boolean
     */
    public function addItem(
        int $folderId,
        string $label,
        string $password,
        string $description,
        string $login,
        string $email,
        string $url,
        string $tags,
        string $anyone_can_modify
    ) : array
    {
        include_once API_ROOT_PATH . '/../sources/main.functions.php';
        $data = [
            'folderId' => $folderId,
            'label' => $label,
            'password' => $password,
            'description' => $description,
            'login' => $login,
            'email' => $email,
            'tags' => $tags,
            'anyoneCanModify' => $anyone_can_modify,
            'url' => $url,
        ];
        
        $filters = [
            'folderId' => 'cast:integer',
            'label' => 'trim|escape',
            'password' => 'trim|escape',
            'description' => 'trim|escape',
            'login' => 'trim|escape',
            'email' => 'trim|escape',
            'tags' => 'trim|escape',
            'anyoneCanModify' => 'trim|escape',
            'url' => 'trim|escape',
        ];
        
        $inputData = dataSanitizer(
            $data,
            $filters
        );
        extract($inputData);

        $lang = new Language();
        include API_ROOT_PATH . '/../includes/config/tp.config.php';

        // is pwd empty?
        if ($this->isPasswordEmptyAllowed($password, $SETTINGS['create_item_without_password'], $lang)) {
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                'error_message' => 'Empty password is not allowed'
            ];
        }

        // Check length
        if (strlen($password) > $SETTINGS['pwd_maximum_length']) {
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                'error_message' => 'Password is too long (max allowed is ' . $SETTINGS['pwd_maximum_length'] . ' characters)'
            ];
        }

        // Need info in DB
        // About special settings
        $dataFolderSettings = DB::queryFirstRow(
            'SELECT bloquer_creation, bloquer_modification, personal_folder
            FROM ' . prefixTable('nested_tree') . ' 
            WHERE id = %i',
            $inputData['folderId']
        );
        $itemInfos = [];
        $itemInfos['personal_folder'] = $dataFolderSettings['personal_folder'];
        $itemInfos['no_complex_check_on_modification'] = (int) $itemInfos['personal_folder'] === 1 ? 1 : (int) $dataFolderSettings['bloquer_modification'];
        $itemInfos['no_complex_check_on_creation'] = (int) $itemInfos['personal_folder'] === 1 ? 1 : (int) $dataFolderSettings['bloquer_creation'];
        error_log('debug: ' . print_r($itemInfos, true));
/*
        // Get folder complexity
        $folderComplexity = DB::queryfirstrow(
            'SELECT valeur
            FROM ' . prefixTable('misc') . '
            WHERE type = %s AND intitule = %i',
            'complex',
            $inputData['folderId']
        );
        $itemInfos['requested_folder_complexity'] = $folderComplexity !== null ? (int) $folderComplexity['valeur'] : 0;

        // Check COMPLEXITY
        if ($post_complexity_level < $itemInfos['requested_folder_complexity'] && $itemInfos['no_complex_check_on_creation'] === 0) {
            echo (string) prepareExchangedData(
                array(
                    'error' => true,
                    'message' => $lang->get('error_security_level_not_reached'),
                ),
                'encode'
            );
            break;
        }



        // check if element doesn't already exist
        $itemExists = 0;
        $newID = '';
        $data = DB::queryfirstrow(
            'SELECT * FROM ' . prefixTable('items') . '
            WHERE label = %s AND inactif = %i',
            $inputData['label'],
            0
        );
        $counter = DB::count();
        if ($counter > 0) {
            $itemExists = 1;
        } else {
            $itemExists = 0;
        }

        // Manage case where item is personal.
        // In this case, duplication is allowed
        if (
            isset($SETTINGS['duplicate_item']) === true
            && (int) $SETTINGS['duplicate_item'] === 0
            && (int) $post_folder_is_personal === 1
            && isset($post_folder_is_personal) === true
        ) {
            $itemExists = 0;
        }

        create_item_without_password

        // Handle case where pw is empty
        // if not allowed then warn user
        if (($session->has('user-create_item_without_password') && $session->has('user-create_item_without_password') && null !== $session->get('user-create_item_without_password')
                && (int) $session->get('user-create_item_without_password') !== 1) ||
            empty($post_password) === false
        ) {
            // NEW ENCRYPTION
            $cryptedStuff = doDataEncryption($post_password);
        } else {
            $cryptedStuff['encrypted'] = '';
            $cryptedStuff['objectKey'] = '';
        }

        $post_password = $cryptedStuff['encrypted'];
        $post_password_key = $cryptedStuff['objectKey'];
        $itemFilesForTasks = [];
        $itemFieldsForTasks = [];
        
        // ADD item
        DB::insert(
            prefixTable('items'),
            array(
                'label' => $inputData['label'],
                'description' => $post_description,
                'pw' => $post_password,
                'pw_iv' => '',
                'email' => $post_email,
                'url' => $post_url,
                'id_tree' => $inputData['folderId'],
                'login' => $post_login,
                'inactif' => 0,
                'restricted_to' => empty($post_restricted_to) === true ?
                    '' : (is_array($post_restricted_to) === true ? implode(';', $post_restricted_to) : $post_restricted_to),
                'perso' => (isset($post_folder_is_personal) === true && (int) $post_folder_is_personal === 1) ?
                    1 : 0,
                'anyone_can_modify' => (isset($post_anyone_can_modify) === true
                    && $post_anyone_can_modify === 'on') ? 1 : 0,
                'complexity_level' => $post_complexity_level,
                'encryption_type' => 'teampass_aes',
                'fa_icon' => $post_fa_icon,
                'item_key' => uniqidReal(50),
                'created_at' => time(),
            )
        );
        $newID = DB::insertId();

        // Create sharekeys for the user itself
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            (int) $post_folder_is_personal,
            (int) $inputData['folderId'],
            (int) $newID,
            $cryptedStuff['objectKey'],
            true,   // only for the item creator
            false,  // no delete all
        );
        */
        return [
            'error' => true,
            'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
            'error_message' => 'Invalid parameters'
        ];
    }

    private function isPasswordEmptyAllowed($password, $create_item_without_password, $lang)
    {
        if (
            empty($password) === true
            && null !== $create_item_without_password
            && (int) $create_item_without_password !== 1
        ) {
            return true;
        }
        return false;
    }
}