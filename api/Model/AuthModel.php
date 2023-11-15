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
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
use PasswordLib\PasswordLib;
use TeampassClasses\NestedTree\NestedTree;

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
            ],
            API_ROOT_PATH . '/..'
        );
        if (empty($inputData['login']) === true || empty($inputData['apikey']) === true) {
            return ["error" => "Login failed0.", "info" => "Empty entry"];
        }
        
        // Check apikey
        if (empty($inputData['password']) === true) {
            // case where it is a generic key
            $apiInfo = $this->select("SELECT count(*) FROM " . prefixTable('api') . " WHERE value='".$inputData['apikey']."' AND label='".$inputData['login']."'");
            if ((int) $apiInfo[0]['count(*)'] === 0) {
                return ["error" => "Login failed1.", "info" => "apikey : Not valid"];
            }

            return ["error" => "Login failed2.", "info" => "Not managed."];
        } else {
            // case where it is a user api key
            // Check if user exists
            $userInfoRes = $this->select(
                "SELECT u.id, u.pw, u.public_key, u.private_key, u.personal_folder, u.fonction_id, u.groupes_visibles, u.groupes_interdits, a.value AS user_api_key
                FROM " . prefixTable('users') . " AS u
                INNER JOIN " . prefixTable('api') . " AS a ON (a.user_id=u.id)
                WHERE login='".$inputData['login']."'");
            if (count($userInfoRes) === 0) {
                return ["error" => "Login failed3.", "info" => "apikey : Not valid"];
            }
            $userInfoRes[0]['special'] = '';
            $userInfo = $userInfoRes[0];
            
            // Check password
            $pwdlib = new PasswordLib();
            if ($pwdlib->verifyPasswordHash($inputData['password'], $userInfo['pw']) === true) {
                // Correct credentials
                // get user keys
                $privateKeyClear = decryptPrivateKey($inputData['password'], (string) $userInfo['private_key']);

                // check API key
                if ($inputData['apikey'] !== base64_decode(decryptUserObjectKey($userInfo['user_api_key'], $privateKeyClear))) {
                    return ["error" => "Login failed4.", "apikey" => "Not valid"];
                }

                // get user folders list
                $ret = $this->buildUserFoldersList($userInfo);

                // create JWT
                return $this->createUserJWT(
                    $userInfo['id'],
                    $inputData['login'],
                    $userInfo['personal_folder'],
                    $userInfo['public_key'],
                    $privateKeyClear,
                    implode(",", $ret['folders']),
                    implode(",", $ret['items'])
                );
            } else {
                return ["error" => "Login failed5.", "info" => "password : Not valid"];
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
     * @return array
     */
    private function createUserJWT(int $id, string $login, int $pf_enabled, string $pubkey, string $privkey, string $folders, string $items): array
    {
        include_once API_ROOT_PATH . '/../includes/config/tp.config.php';
        $headers = ['alg'=>'HS256','typ'=>'JWT'];
		$payload = [
            'username' => $login,
            'id' => $id, 
            'exp' => (time() + $SETTINGS['api_token_duration'] + 600),
            'public_key' => $pubkey,
            'private_key' => $privkey,
            'pf_enabled' => $pf_enabled,
            'folders_list' => $folders,
            'restricted_items_list' => $items,
        ];

        include_once API_ROOT_PATH . '/inc/jwt_utils.php';
		return ['token' => generate_jwt($headers, $payload)];
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
        $allowedFolders = explode(";", $userInfo['groupes_visibles']);
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