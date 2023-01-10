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
        // Check if user exists
        $userInfoRes = $this->select("SELECT id, pw, public_key, private_key, personal_folder, fonction_id, groupes_visibles, groupes_interdits, user_api_key FROM " . prefixTable('users') . " WHERE login='".$login."'");

        if (!empty($userInfoRes)) {
            $userInfoRes[0]['special'] = '';
            $userInfo = $userInfoRes[0];

            // Check password
            include_once API_ROOT_PATH . '/../sources/SplClassLoader.php';

            $pwdlib = new SplClassLoader('PasswordLib', API_ROOT_PATH . '/../includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();

            if ($pwdlib->verifyPasswordHash($password, $userInfo['pw']) === true) {
                // Correct credentials
                // Now check apikey
                // We check if it is the correct user api or if it is a generic api key
                $apiInfo = $this->select("SELECT count(*) FROM " . prefixTable('api') . " WHERE value='".$apikey."'");
                if ($apikey === $userInfo['user_api_key'] || (int) $apiInfo[0]['count(*)'] === 1) {
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
                    return ["error" => "Login failed", "info" => "ApiKey not valid"];
                }
            } else {
                return ["error" => "Login failed", "info" => "Wrong password"];
            }
        } else {
            return ["error" => "Login failed", "info" => "Wrong login"];
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
    private function createUserJWT(int $id, string $login, int $pf_enabled, string $pubkey, string $privkey, string $folders): array
    {
        require API_ROOT_PATH . '/../includes/config/tp.config.php';
        $headers = ['alg'=>'HS256','typ'=>'JWT'];
        $payload = [
            'username' => $login,
            'id' => $id,
            'exp' => (time() + $SETTINGS['api_token_duration'] + 600),
            'public_key' => $pubkey,
            'private_key' => $privkey,
            'pf_enabled' => $pf_enabled,
            'folders_list' => $folders,
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
        $tree = new SplClassLoader('Tree\NestedTree', API_ROOT_PATH . '/../includes/libraries');
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
        return array_unique(
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
        );
    }
    //end buildUserFoldersList
}
