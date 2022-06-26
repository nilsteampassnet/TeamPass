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
 * @file      AuthModel.php
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

 
class AuthModel extends Database
{
    public function getUserAuth($login, $password, $apikey)
    {
        // Check if user exists
        $userInfoRes = $this->select("SELECT id, pw, public_key, private_key, personal_folder, fonction_id, groupes_visibles, groupes_interdits FROM " . prefixTable('users') . " WHERE login='".$login."'");
        $userInfoRes[0]['special'] = '';
        $userInfo = $userInfoRes[0];
        
        // Check password
        include_once PROJECT_ROOT_PATH . '/../sources/SplClassLoader.php';
        $pwdlib = new SplClassLoader('PasswordLib', PROJECT_ROOT_PATH . '/../includes/libraries');
        $pwdlib->register();
        $pwdlib = new PasswordLib\PasswordLib();
        if ($pwdlib->verifyPasswordHash($password, $userInfo['pw']) === true) {
            // Correct credentials
            // Now check apikey
            $apiInfo = $this->select("SELECT count(*) FROM " . prefixTable('api') . " WHERE value='".$apikey."'");
            if ((int) $apiInfo[0]['count(*)'] === 1) {
                // get user keys
                $privateKeyClear = decryptPrivateKey($password, (string) $userInfo['private_key']); //prepareUserEncryptionKeys($userInfo, $password);

                // get user folders list
                $folders = $this->buildUserFoldersList($userInfo);

                // create JWT
                return $this->createUserJWT(
                    $userInfo['id'],
                    $login,
                    $userInfo['personal_folder'],
                    $userInfo['public_key'],
                    $privateKeyClear,
                    implode(",", $folders)
                );
            } else {
                return array("error" => "Login failed.", "apikey" => "Not valid");
            }
        } else {
            return array("error" => "Login failed.", "password" => $password);
        }
    }

    private function createUserJWT($id, $login, $pf_enabled, $pubkey, $privkey, $folders): array
    {
        require PROJECT_ROOT_PATH . '/../includes/config/tp.config.php';
        $headers = array('alg'=>'HS256','typ'=>'JWT');
		$payload = array(
            'username' => $login,
            'id' => $id, 
            'exp' => (time() + $SETTINGS['api_token_duration'] + 600),
            'public_key' => $pubkey,
            'private_key' => $privkey,
            'pf_enabled' => $pf_enabled,
            'folders_list' => $folders,
        );

        include_once PROJECT_ROOT_PATH . '/inc/jwt_utils.php';
		return array('token' => generate_jwt($headers, $payload));
    }

    private function buildUserFoldersList($userInfo)
    {
        //Build tree
        $tree = new SplClassLoader('Tree\NestedTree', PROJECT_ROOT_PATH . '/../includes/libraries');
        $tree->register();
        $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
        
        // Start by adding the manually added folders
        $allowedFolders = explode(";", $userInfo['groupes_visibles']);
        $readOnlyFolders = [];
        $allowedFoldersByRoles = [];
        $restrictedFoldersForItems = [];
        $foldersLimited = [];
        $foldersLimitedFull = [];
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
            $restrictedFoldersForItems[$record['id_tree']][$inc] = $record['id'];
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
            array_push($foldersLimitedFull, $record['item_id']);
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
        $allowedFolders = array_unique(array_filter(array_merge(
            $allowedFolders,
            $foldersLimitedFull,
            $allowedFoldersByRoles,
            $restrictedFoldersForItems,
            $readOnlyFolders,
            $personalFolders
        )));

        return $allowedFolders;
    }
}