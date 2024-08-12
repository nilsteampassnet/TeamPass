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
 * @file      AuthModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\NestedTree\NestedTree;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once API_ROOT_PATH . "/Model/Database.php";


class AuthModel extends Database
{


    /**
     * Is the user allowed
     *
     * @param string $login
     * @param string $password
     * @param string $apikey
     * @return array
     */
    public function getUserAuth(string $login, string $password, string $apikey): array
    {
        // Sanitize
        include_once API_ROOT_PATH . '/../sources/main.functions.php';
        $inputData = dataSanitizer(
            [
                'login' => isset($login) === true ? $login : '',
                'password' => isset($password) === true ? $password : '',
                'apikey' => isset($apikey) === true ? $apikey : '',
            ],
            [
                'login' => 'trim|escape',
                'password' => 'trim|escape',
                'apikey' => 'trim|escape',
            ]
        );
        if (empty($inputData['login']) === true || empty($inputData['apikey']) === true) {
            return ["error" => "Login failed.", "info" => "Empty entry"];
        }
        
        // Check apikey
        if (empty($inputData['password']) === true) {
            // case where it is a generic key
            $apiInfo = $this->select("SELECT * FROM " . prefixTable('api') . " WHERE value='".$inputData['apikey']."' AND label='".$inputData['login']."'");
            $apiInfo = $apiInfo[0];
            if (WIP === true) {
                if (isset($apiInfo['increment_id']) === false) {
                    return ["error" => "Login failed.", "info" => "apikey : Not valid"];
                }

                // Check if user is enabled
                if ((int) $apiInfo['enabled'] === 0) {
                    return ["error" => "Login failed.", "info" => "User not allowed to use API"];
                }

                // Log user
                include API_ROOT_PATH . '/../includes/config/tp.config.php';
                logEvents($SETTINGS, 'api', 'user_connection', (string) $apiInfo['increment_id'], stripslashes($inputData['login']));

                // create JWT
                return $this->createUserJWT(
                    $apiInfo['increment_id'],
                    $inputData['login'],
                    0,
                    '',
                    '',
                    '',
                    '',
                    '',
                    0,
                    0,
                    1,
                    0,
                    '',
                    $apiInfo['allowed_folders'],
                    $apiInfo['allowed_to_create'],
                    $apiInfo['allowed_to_read'],
                    $apiInfo['allowed_to_update'],
                    $apiInfo['allowed_to_delete'],
                );
            } else {
                return ["error" => "Login failed.", "info" => "Not managed."];
            }
        } else {
            // case where it is a user api key
            // Check if user exists
            $userInfoRes = $this->select(
                "SELECT u.id, u.pw, u.login, u.admin, u.gestionnaire, u.can_manage_all_users, u.fonction_id, u.can_create_root_folder, u.public_key, u.private_key, u.personal_folder, u.fonction_id, u.groupes_visibles, u.groupes_interdits, a.value AS user_api_key, a.allowed_folders as user_api_allowed_folders, a.enabled, a.allowed_to_create, a.allowed_to_read, a.allowed_to_update, a.allowed_to_delete
                FROM " . prefixTable('users') . " AS u
                INNER JOIN " . prefixTable('api') . " AS a ON (a.user_id=u.id)
                WHERE login='".$inputData['login']."'");
            if (count($userInfoRes) === 0) {
                return ["error" => "Login failed.", "info" => "apikey : Not valid"];
            }
            $userInfoRes[0]['special'] = '';
            $userInfo = $userInfoRes[0];

            // Check if user is enabled
            if ((int) $userInfo['enabled'] === 0) {
                return ["error" => "Login failed.", "info" => "User not allowed to use API"];
            }
            
            // Check password
            $passwordManager = new PasswordManager();
            if ($passwordManager->verifyPassword($userInfo['pw'], $inputData['password']) === true) {
                // Correct credentials
                // get user keys
                $privateKeyClear = decryptPrivateKey($inputData['password'], (string) $userInfo['private_key']);

                // check API key
                if ($inputData['apikey'] !== base64_decode(decryptUserObjectKey($userInfo['user_api_key'], $privateKeyClear))) {
                    return ["error" => "Login failed.", "apikey" => "Not valid"];
                }

                // Update user's key_tempo
                $keyTempo = bin2hex(random_bytes(16));
                $this->update(
                    "UPDATE " . prefixTable('users') . "
                    SET key_tempo='".$keyTempo."'
                    WHERE id=".$userInfo['id']
                );
                
                // get user folders list
                $ret = $this->buildUserFoldersList($userInfo);

                // Log user
                include API_ROOT_PATH . '/../includes/config/tp.config.php';
                logEvents($SETTINGS, 'api', 'user_connection', (string) $userInfo['id'], stripslashes($userInfo['login']));

                // create JWT
                return $this->createUserJWT(
                    (int) $userInfo['id'],
                    (string) $inputData['login'],
                    (int) $userInfo['personal_folder'],
                    (string) $userInfo['public_key'],
                    (string) $privateKeyClear,
                    (string) implode(",", $ret['folders']),
                    (string) implode(",", $ret['items']),
                    (string) $keyTempo,
                    (int) $userInfo['admin'],
                    (int) $userInfo['gestionnaire'],
                    (int) $userInfo['can_create_root_folder'],
                    (int) $userInfo['can_manage_all_users'],
                    (string) $userInfo['fonction_id'],
                    (string) $userInfo['user_api_allowed_folders'],
                    (int) $userInfo['allowed_to_create'],
                    (int) $userInfo['allowed_to_read'],
                    (int) $userInfo['allowed_to_update'],
                    (int) $userInfo['allowed_to_delete'],
                );
            } else {
                return ["error" => "Login failed.", "info" => "password : Not valid"];
            }
        }
    }
    //end getUserAuth

    /**
     * Create a JWT
     *
     * @param integer $id
     * @param string $login
     * @param integer $pf_enabled
     * @param string $pubkey
     * @param string $privkey
     * @param string $folders
     * @param string $keyTempo
     * @param integer $admin
     * @param integer $manager
     * @param integer $can_create_root_folder
     * @param integer $can_manage_all_users
     * @param string $roles
     * @param string $allowed_folders
     * @param integer $allowed_to_create
     * @param integer $allowed_to_read
     * @param integer $allowed_to_update
     * @param integer $allowed_to_delete
     * @return array
     */
    private function createUserJWT(
        int $id,
        string $login,
        int $pf_enabled,
        string $pubkey,
        string $privkey,
        string $folders,
        string $items,
        string $keyTempo,
        int $admin,
        int $manager,
        int $can_create_root_folder,
        int $can_manage_all_users,
        string $roles,
        string $allowed_folders,
        int $allowed_to_create,
        int $allowed_to_read,
        int $allowed_to_update,
        int $allowed_to_delete,
    ): array
    {
        include API_ROOT_PATH . '/../includes/config/tp.config.php';
        
		$payload = [
            'username' => $login,
            'id' => $id, 
            'exp' => (time() + $SETTINGS['api_token_duration'] + 600),
            'public_key' => $pubkey,
            'private_key' => $privkey,
            'pf_enabled' => $pf_enabled,
            'folders_list' => $folders,
            'restricted_items_list' => $items,
            'key_tempo' => $keyTempo,
            'is_admin' => $admin,
            'is_manager' => $manager,
            'user_can_create_root_folder' => $can_create_root_folder,
            'user_can_manage_all_users' => $can_manage_all_users,
            'roles' => $roles,
            'allowed_folders' => $allowed_folders,
            'allowed_to_create' => $allowed_to_create,
            'allowed_to_read' => $allowed_to_read,
            'allowed_to_update' => $allowed_to_update,
            'allowed_to_delete' => $allowed_to_delete,
        ];
        
        return ['token' => JWT::encode($payload, DB_PASSWD, 'HS256')];
    }

    //end createUserJWT


    /**
     * Permit to build the list of folders the user can access
     *
     * @param array $userInfo
     * @return array
     */
    private function buildUserFoldersList(array $userInfo): array
    {
        //Build tree
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        
        // Start by adding the manually added folders
        $allowedFolders = array_map('intval', explode(";", $userInfo['groupes_visibles']));
        $readOnlyFolders = [];
        $allowedFoldersByRoles = [];
        $restrictedFoldersForItems = [];
        $foldersLimited = [];
        $foldersLimitedFull = [];
        $restrictedItems = [];
        $personalFolders = [];

        $userFunctionId = str_replace(";", ",", $userInfo['fonction_id']);

        // Get folders from the roles
        if (empty($userFunctionId) === false) {
            $rows = $this->select("SELECT * FROM " . prefixTable('roles_values') . " WHERE role_id IN (".$userFunctionId.") AND type IN ('W', 'ND', 'NE', 'NDNE', 'R')");
            foreach ($rows as $record) {
                if ($record['type'] === 'R') {
                    array_push($readOnlyFolders, $record['folder_id']);
                } elseif (in_array($record['folder_id'], $allowedFolders) === false) {
                    array_push($allowedFoldersByRoles, $record['folder_id']);
                }
            }
            $allowedFoldersByRoles = array_unique($allowedFoldersByRoles);
            $readOnlyFolders = array_unique($readOnlyFolders);
            // Clean arrays
            foreach ($allowedFoldersByRoles as $value) {
                $key = array_search($value, $readOnlyFolders);
                if ($key !== false) {
                    unset($readOnlyFolders[$key]);
                }
            }
        }
        
        // Does this user is allowed to see other items
        $inc = 0;
        $rows = $this->select("SELECT id, id_tree FROM " . prefixTable('items') . " WHERE restricted_to LIKE '".$userInfo['id']."'".
            (empty($userFunctionId) === false ? ' AND id_tree NOT IN ('.$userFunctionId.')' : ''));
        foreach ($rows as $record) {
            // Exclude restriction on item if folder is fully accessible
            $restrictedFoldersForItems[$inc] = $record['id_tree'];
            ++$inc;
        }

        // Check for the users roles if some specific rights exist on items
        $rows = $this->select("SELECT i.id_tree, r.item_id
            FROM " . prefixTable('items') . " as i
            INNER JOIN " . prefixTable('restriction_to_roles') . " as r ON (r.item_id=i.id)
            WHERE ".(empty($userFunctionId) === false ? ' id_tree NOT IN ('.$userFunctionId.') AND ' : '')." i.id_tree != ''
            ORDER BY i.id_tree ASC");
        foreach ($rows as $record) {
            $foldersLimited[$record['id_tree']][$inc] = $record['item_id'];
            //array_push($foldersLimitedFull, $record['item_id']);
            array_push($restrictedItems, $record['item_id']);
            array_push($foldersLimitedFull, $record['id_tree']);
            ++$inc;
        }

        // Add all personal folders
        $rows = $this->select(
            'SELECT id
            FROM ' . prefixTable('nested_tree') . '
            WHERE title = '.$userInfo['id'].' AND personal_folder = 1'.
            (empty($userFunctionId) === false ? ' AND id NOT IN ('.$userFunctionId.')' : '').
            ' LIMIT 0,1'
        );
        if (empty($rows['id']) === false) {
            array_push($personalFolders, $rows['id']);
            // get all descendants
            $ids = $tree->getDescendants($rows['id'], false, false, true);
            foreach ($ids as $id) {
                array_push($personalFolders, $id);
            }
        }

        // All folders visibles
        return [
            'folders' => array_unique(
            array_filter(
                array_merge(
                    $allowedFolders,
                    $foldersLimitedFull,
                    $allowedFoldersByRoles,
                    $restrictedFoldersForItems,
                    $readOnlyFolders,
                    $personalFolders
                )
                )
            ),
            'items' => array_unique($restrictedItems),
        ];
    }
    //end buildUserFoldersList
}