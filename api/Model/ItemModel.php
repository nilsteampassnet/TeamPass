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
use TeampassClasses\ConfigManager\ConfigManager;
use ZxcvbnPhp\Zxcvbn;

class ItemModel
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
        // Get items
        $rows = DB::query(
            'SELECT i.id, label, description, i.pw, i.url, i.id_tree, i.login, i.email, i.viewed_no, i.fa_icon, i.inactif, i.perso, t.title as folder_label
            FROM ' . prefixTable('items') . ' AS i
            LEFT JOIN '.prefixTable('nested_tree').' as t ON (t.id = i.id_tree) '.
            $sqlExtra . 
            " ORDER BY i.id ASC" .
            ($limit > 0 ? " LIMIT ". $limit : '')
        );

        $ret = [];
        foreach ($rows as $row) {
            $userKey = DB::queryfirstrow(
                'SELECT share_key
                FROM ' . prefixTable('sharekeys_items') . '
                WHERE user_id = %i AND object_id = %i',
                $userId,
                $row['id']
            );
            if (DB::count() === 0 || empty($row['pw']) === true) {
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
                            $userKey['share_key'],
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
     * Main function to add a new item to the database.
     * It handles data preparation, validation, password checks, folder settings,
     * item creation, and post-insertion tasks (like logging, sharing, and tagging).
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
        string $anyone_can_modify,
        string $icon,
        int $userId,
        string $username
    ) : array
    {
        try {
            include_once API_ROOT_PATH . '/../sources/main.functions.php';

            // Load config
            $configManager = new ConfigManager();
            $SETTINGS = $configManager->getAllSettings();

            // Step 1: Prepare data and sanitize inputs
            $data = $this->prepareData($folderId, $label, $password, $description, $login, $email, $url, $tags, $anyone_can_modify, $icon);
            $this->validateData($data); // Step 2: Validate the data

            // Step 3: Validate password rules (length, emptiness)
            $this->validatePassword($password, $SETTINGS);

            // Step 4: Check folder settings for permission checks
            $itemInfos = $this->getFolderSettings($folderId);

            // Step 5: Ensure the password meets folder complexity requirements
            $this->checkPasswordComplexity($password, $itemInfos);

            // Step 6: Check for duplicates in the system
            $this->checkForDuplicates($label, $SETTINGS, $itemInfos);

            // Step 7: Encrypt password if provided
            $cryptedData = $this->encryptPassword($password);
            $passwordKey = $cryptedData['passwordKey'];
            $password = $cryptedData['encrypted'];

            // Step 8: Insert the new item into the database
            $newID = $this->insertNewItem($data, $password, $itemInfos);

            // Step 9: Handle post-insert tasks (logging, sharing, tagging)
            $this->handlePostInsertTasks($newID, $itemInfos, $folderId, $passwordKey, $userId, $username, $tags, $data, $SETTINGS);

            // Success response
            return [
                'error' => false,
                'message' => 'Item added successfully',
                'newId' => $newID,
            ];

        } catch (Exception $e) {
            // Error response
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepares the data array for processing by combining all inputs.
     * @param int $folderId - Folder ID where the item is stored
     * @param string $label - Label or title of the item
     * @param string $password - Password associated with the item
     * @param string $description - Description of the item
     * @param string $login - Login associated with the item
     * @param string $email - Email linked to the item
     * @param string $url - URL for the item
     * @param string $tags - Tags for categorizing the item
     * @param string $anyone_can_modify - Permission to allow modifications by others
     * @param string $icon - Icon representing the item
     * @return array - Returns the prepared data
     */
    private function prepareData(
        int $folderId, string $label, string $password, string $description, string $login, 
        string $email, string $url, string $tags, string $anyone_can_modify, string $icon
    ) : array {
        return [
            'folderId' => $folderId,
            'label' => $label,
            'password' => $password,
            'description' => $description,
            'login' => $login,
            'email' => $email,
            'tags' => $tags,
            'anyoneCanModify' => $anyone_can_modify,
            'url' => $url,
            'icon' => $icon,
        ];
    }

    /**
     * Sanitizes and validates the input data according to specified filters.
     * If validation fails, throws an exception.
     * @param array $data - Data to be validated and sanitized
     * @throws Exception - If the data is invalid
     */
    private function validateData(array $data) : void
    {
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
            'icon' => 'trim|escape',
        ];

        $inputData = dataSanitizer($data, $filters);
        if (is_string($inputData)) {
            throw new Exception('Data is not valid');
        }
    }

    /**
     * Validates the password against length and empty password rules.
     * Throws an exception if validation fails.
     * @param string $password - The password to validate
     * @param array $SETTINGS - Global settings from configuration
     * @throws Exception - If the password is invalid
     */
    private function validatePassword(string $password, array $SETTINGS) : void
    {
        if ($this->isPasswordEmptyAllowed($password, $SETTINGS['create_item_without_password'])) {
            throw new Exception('Empty password is not allowed');
        }

        if (strlen($password) > $SETTINGS['pwd_maximum_length']) {
            throw new Exception('Password is too long (max allowed is ' . $SETTINGS['pwd_maximum_length'] . ' characters)');
        }
    }

    /**
     * Retrieves folder-specific settings, including permission to modify and create items.
     * @param int $folderId - The folder ID to fetch settings for
     * @return array - Returns an array with folder-specific permissions
     */
    private function getFolderSettings(int $folderId) : array
    {
        $dataFolderSettings = DB::queryFirstRow(
            'SELECT bloquer_creation, bloquer_modification, personal_folder
            FROM ' . prefixTable('nested_tree') . ' 
            WHERE id = %i',
            $folderId
        );

        return [
            'personal_folder' => $dataFolderSettings['personal_folder'],
            'no_complex_check_on_modification' => (int) $dataFolderSettings['personal_folder'] === 1 ? 1 : (int) $dataFolderSettings['bloquer_modification'],
            'no_complex_check_on_creation' => (int) $dataFolderSettings['personal_folder'] === 1 ? 1 : (int) $dataFolderSettings['bloquer_creation'],
        ];
    }

    /**
     * Validates that the password meets the complexity requirements of the folder.
     * Throws an exception if the password is too weak.
     * @param string $password - The password to check
     * @param array $itemInfos - Folder settings including password complexity requirements
     * @throws Exception - If the password complexity is insufficient
     */
    private function checkPasswordComplexity(string $password, array $itemInfos) : void
    {
        $folderComplexity = DB::queryFirstRow(
            'SELECT valeur
            FROM ' . prefixTable('misc') . '
            WHERE type = %s AND intitule = %i',
            'complex',
            $itemInfos['folderId']
        );

        $requested_folder_complexity = $folderComplexity !== null ? (int) $folderComplexity['valeur'] : 0;

        $zxcvbn = new Zxcvbn();
        $passwordStrength = $zxcvbn->passwordStrength($password);
        $passwordStrengthScore = convertPasswordStrength($passwordStrength['score']);

        if ($passwordStrengthScore < $requested_folder_complexity && (int) $itemInfos['no_complex_check_on_creation'] === 0) {
            throw new Exception('Password strength is too low');
        }
    }

    /**
     * Checks if an item with the same label already exists in the folder.
     * Throws an exception if duplicates are not allowed.
     * @param string $label - The label of the item to check for duplicates
     * @param array $SETTINGS - Global settings for duplicate items
     * @param array $itemInfos - Folder-specific settings
     * @throws Exception - If a duplicate item is found and not allowed
     */
    private function checkForDuplicates(string $label, array $SETTINGS, array $itemInfos) : void
    {
        DB::queryFirstRow(
            'SELECT * FROM ' . prefixTable('items') . '
            WHERE label = %s AND inactif = %i',
            $label,
            0
        );

        if (DB::count() > 0 && (
            (isset($SETTINGS['duplicate_item']) && (int) $SETTINGS['duplicate_item'] === 0)
            || (int) $itemInfos['personal_folder'] === 0)
        ) {
            throw new Exception('Similar item already exists. Duplicates are not allowed.');
        }
    }

    /**
     * Encrypts the password using the system's encryption function.
     * Returns an array containing both the encrypted password and the encryption key.
     * @param string $password - The password to encrypt
     * @return array - Returns the encrypted password and the encryption key
     */
    private function encryptPassword(string $password) : array
    {
        $cryptedStuff = doDataEncryption($password);
        return [
            'encrypted' => $cryptedStuff['encrypted'],
            'passwordKey' => $cryptedStuff['objectKey'],
        ];
    }

    /**
     * Inserts the new item into the database with all its associated data.
     * @param array $data - The item data to insert
     * @param string $password - The encrypted password
     * @param array $itemInfos - Folder-specific settings
     * @return int - Returns the ID of the newly created item
     */
    private function insertNewItem(array $data, string $password, array $itemInfos) : int
    {
        DB::insert(
            prefixTable('items'),
            [
                'label' => $data['label'],
                'description' => $data['description'],
                'pw' => $password,
                'pw_iv' => '',
                'pw_len' => strlen($data['password']),
                'email' => $data['email'],
                'url' => $data['url'],
                'id_tree' => $data['folderId'],
                'login' => $data['login'],
                'inactif' => 0,
                'restricted_to' => '',
                'perso' => $itemInfos['personal_folder'],
                'anyone_can_modify' => $data['anyoneCanModify'],
                'complexity_level' => $this->getPasswordComplexityLevel($password),
                'encryption_type' => 'teampass_aes',
                'fa_icon' => $data['icon'],
                'item_key' => uniqidReal(50),
                'created_at' => time(),
            ]
        );

        return DB::insertId();
    }

    /**
     * Determines the complexity level of a password based on its strength score.
     * @param string $password - The encrypted password for which complexity is being evaluated
     * @return int - Returns the complexity level (0 for weak, higher numbers for stronger passwords)
     */
    private function getPasswordComplexityLevel(string $password) : int
    {
        $zxcvbn = new Zxcvbn();
        $passwordStrength = $zxcvbn->passwordStrength($password);
        return convertPasswordStrength($passwordStrength['score']);
    }

    /**
     * Handles tasks that need to be performed after the item is inserted:
     * 1. Stores sharing keys
     * 2. Logs the item creation
     * 3. Adds a task if the folder is not personal
     * 4. Adds tags to the item
     * @param int $newID - The ID of the newly created item
     * @param array $itemInfos - Folder-specific settings
     * @param int $folderId - Folder ID of the item
     * @param string $passwordKey - The encryption key for the item
     * @param int $userId - ID of the user creating the item
     * @param string $username - Username of the creator
     * @param string $tags - Tags to be associated with the item
     * @param array $data - The original data used to create the item (including the label)
     * @param array $SETTINGS - System settings for logging and task creation
     */
    private function handlePostInsertTasks(
        int $newID,
        array $itemInfos,
        int $folderId,
        string $passwordKey,
        int $userId,
        string $username,
        string $tags,
        array $data,
        array $SETTINGS
    ) : void {
        // Create share keys for the creator
        storeUsersShareKey(
            prefixTable('sharekeys_items'),
            (int) $itemInfos['personal_folder'],
            (int) $folderId,
            (int) $newID,
            $passwordKey,
            true,
            false,
            [],
            -1,
            $userId
        );

        // Log the item creation
        logItems($SETTINGS, $newID, $data['label'], $userId, 'at_creation', $username);

        // Create a task if the folder is not personal
        if ((int) $itemInfos['personal_folder'] === 0) {
            storeTask('new_item', $userId, 0, $folderId, $newID, $passwordKey, [], []);
        }

        // Add tags to the item
        $this->addTags($newID, $tags);
    }


    /**
     * Splits the tags string into individual tags and inserts them into the database.
     * @param int $newID - The ID of the item to associate tags with
     * @param string $tags - A comma-separated string of tags
     */
    private function addTags(int $newID, string $tags) : void
    {
        $tagsArray = explode(',', $tags);
        foreach ($tagsArray as $tag) {
            if (!empty($tag)) {
                DB::insert(
                    prefixTable('tags'),
                    ['item_id' => $newID, 'tag' => strtolower($tag)]
                );
            }
        }
    }


    private function isPasswordEmptyAllowed($password, $create_item_without_password)
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