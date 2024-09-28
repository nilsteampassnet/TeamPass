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
 * @file      AuthModel.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\ConfigManager\ConfigManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthModel
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
                'login' => 'trim|escape|strip_tags',
                'password' => 'trim|escape',
                'apikey' => 'trim|escape|strip_tags',
            ]
        );

        // Check apikey and credentials
        if (empty($inputData['login']) === true || empty($inputData['apikey']) === true || empty($inputData['password']) === true) {
            // case where it is a generic key
            // Not allowed to use this API

            return ["error" => "Login failed.", "info" => "User password is requested"];
        } else {
            // case where it is a user api key
            // Check if user exists
            $userInfo = DB::queryfirstrow(
                "SELECT u.id, u.pw, u.login, u.admin, u.gestionnaire, u.can_manage_all_users, u.fonction_id, u.can_create_root_folder, u.public_key, u.private_key, u.personal_folder, u.fonction_id, u.groupes_visibles, u.groupes_interdits, a.value AS user_api_key, a.allowed_folders as user_api_allowed_folders, a.enabled, a.allowed_to_create, a.allowed_to_read, a.allowed_to_update, a.allowed_to_delete
                FROM " . prefixTable('users') . " AS u
                INNER JOIN " . prefixTable('api') . " AS a ON (a.user_id=u.id)
                WHERE login = %s",
                $inputData['login']
            );
            if (DB::count() === 0) {
                return ["error" => "Login failed.", "info" => "apikey : Not valid"];
            }

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
                DB::update(
                    prefixTable('users'),
                    [
                        'key_tempo' => $keyTempo,
                    ],
                    'id = %i',
                    $userInfo['id']
                );
                
                // get user folders list
                $ret = $this->buildUserFoldersList($userInfo);

                // Load config
                $configManager = new ConfigManager();
                $SETTINGS = $configManager->getAllSettings();

                // Log user
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
        // Load config
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();
        
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

        $userFunctionId = explode(";", $userInfo['fonction_id']);

        // Get folders from the roles
        if (count($userFunctionId) > 0) {
            $rows = DB::query(
                'SELECT * 
                FROM ' . prefixTable('roles_values') . '
                WHERE role_id IN %li  AND type IN ("W", "ND", "NE", "NDNE", "R")',
                $userFunctionId
            );
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
        $rows = DB::query(
            'SELECT id, id_tree 
            FROM ' . prefixTable('items') . '
            WHERE restricted_to LIKE %s'.
            (count($userFunctionId) > 0 ? ' AND id_tree NOT IN %li' : ''),
            $userInfo['id'],
            count($userFunctionId) > 0 ? $userFunctionId : DB::sqleval('0')
        );
        foreach ($rows as $record) {
            // Exclude restriction on item if folder is fully accessible
            $restrictedFoldersForItems[$inc] = $record['id_tree'];
            ++$inc;
        }

        // Check for the users roles if some specific rights exist on items
        $rows = DB::query(
            'SELECT i.id_tree, r.item_id
            FROM ' . prefixTable('items') . ' AS i
            INNER JOIN ' . prefixTable('restriction_to_roles') . ' AS r ON (r.item_id=i.id)
            WHERE '.(count($userFunctionId) > 0 ? ' id_tree NOT IN %li AND ' : '').' i.id_tree != ""
            ORDER BY i.id_tree ASC',
            count($userFunctionId) > 0 ? $userFunctionId : DB::sqleval('0')
        );
        foreach ($rows as $record) {
            $foldersLimited[$record['id_tree']][$inc] = $record['item_id'];
            //array_push($foldersLimitedFull, $record['item_id']);
            array_push($restrictedItems, $record['item_id']);
            array_push($foldersLimitedFull, $record['id_tree']);
            ++$inc;
        }

        // Add all personal folders
        $rows = DB::queryFirstRow(
            'SELECT id 
            FROM ' . prefixTable('nested_tree') . '
            WHERE title = %i AND personal_folder = 1'.
            (count($userFunctionId) > 0 ? ' AND id NOT IN %li' : ''),
            $userInfo['id'],
            count($userFunctionId) > 0 ? $userFunctionId : DB::sqleval('0')
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