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
 * @copyright 2009-2026 Teampass.net
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
     * @param bool $showItem Kept for caller compatibility — access is now logged on every path
     *
     * @return array
     */
    public function getItems(string $sqlExtra, int $limit, string $userPrivateKey, int $userId, bool $showItem = false): array
    {
        // Fetch user's public key once for migration-aware decryption
        $userPublicKey = '';
        $userKeyRow = DB::queryFirstRow(
            'SELECT public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );
        if ($userKeyRow !== null) {
            $userPublicKey = (string) $userKeyRow['public_key'];
        }

        // Load settings once to know whether custom fields are enabled
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();
        $itemExtraFields = isset($SETTINGS['item_extra_fields']) && (int) $SETTINGS['item_extra_fields'] === 1;

        // Get items
        $rows = DB::query(
            "SELECT i.id, i.label, i.description, i.pw, i.url, i.id_tree, i.login, i.email, 
                i.viewed_no, i.fa_icon, i.inactif, i.perso, i.favicon_url, i.anyone_can_modify,
                t.title as folder_label, 
                io.secret as otp_secret,
                (SELECT GROUP_CONCAT(tg.tag SEPARATOR ', ') 
                 FROM " . prefixTable('tags') . " AS tg 
                 WHERE tg.item_id = i.id) as tags
            FROM " . prefixTable('items') . " AS i
            LEFT JOIN " . prefixTable('nested_tree') . " AS t ON (t.id = i.id_tree)
            LEFT JOIN " . prefixTable('items_otp') . " AS io ON (io.item_id = i.id)".
            $sqlExtra . 
            " ORDER BY i.id ASC" .
            ($limit > 0 ? " LIMIT ". $limit : '')
        ); 
        
        $ret = [];
        foreach ($rows as $row) {
            $userKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
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

            // Get password (migration-aware: upgrades phpseclib v1 sharekeys to v3 on access)
            $pwd = '';
            try {
                $pwd = base64_decode(
                    (string) doDataDecryption(
                        $row['pw'],
                        decryptUserObjectKeyWithMigration(
                            $userKey['share_key'],
                            $userPrivateKey,
                            $userPublicKey,
                            (int) $userKey['increment_id'],
                            'sharekeys_items'
                        )
                    )
                );
            } catch (Exception $e) {
                error_log('[API] ItemModel::getItems decryption error for item ' . $row['id'] . ': ' . $e->getMessage());
                // Skip this item — decryption failed (e.g. legacy sharekey not yet migrated)
                continue;
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

            // Get TOTP
            if (empty($row['otp_secret']) === false) {
                $decryptedTotp = cryption(
                    $row['otp_secret'],
                    '',
                    'decrypt'
                );
                $row['otp_secret'] = $decryptedTotp['string'];
            }

            // Custom fields attached to this item (only if the feature is enabled)
            $itemFields = $itemExtraFields === true
                ? $this->getItemCustomFields((int) $row['id'], (int) $row['id_tree'], $userId, $userPrivateKey, $userPublicKey)
                : [];

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
                    'totp' => $row['otp_secret'],
                    'favicon_url' => $row['favicon_url'],
                    'tags' => $row['tags'],
                    'anyone_can_modify' => $row['anyone_can_modify'],
                    'fields' => $itemFields,
                ]
            );

            // Audit trail: the decrypted password is returned to the client, so log the
            // access for every lookup path (id, label, description, inFolders) — not only
            // get-by-id. logItems() tags API context (tp_src=api) and dedupes within 5s.
            logItems(
                [],
                (int) $row['id'],
                $row['label'] ?? '',
                (int) $userId,
                'at_shown',
                ''
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
        array $arrItemParams
    ) : array
    {
        try {
            include_once API_ROOT_PATH . '/../sources/main.functions.php';

            // Extract parameters
            $folderId = (int) $arrItemParams['folder_id'];
            $label = (string) $arrItemParams['label'];
            $password = (string) $arrItemParams['password'];
            $tags = (string) ($arrItemParams['tags'] ?? '');
            $fields = (isset($arrItemParams['fields']) && is_array($arrItemParams['fields'])) ? $arrItemParams['fields'] : [];
            $userId = (int) $arrItemParams['id'];
            $username = (string) $arrItemParams['username'];

            // Load config
            $configManager = new ConfigManager();
            $SETTINGS = $configManager->getAllSettings();

            // Step 1: Prepare data and sanitize inputs
            $data = $this->prepareData($arrItemParams);
            $this->validateData($data); // Step 2: Validate the data

            // Step 3: Validate password rules (length, emptiness)
            $this->validatePassword($password, $SETTINGS);

            // Step 4: Check folder settings for permission checks
            $itemInfos = $this->getFolderSettings($folderId);

            // Step 5: Ensure the password meets folder complexity requirements
            // Capture the score to reuse it — avoids running zxcvbn a second time on the ciphertext
            $complexityLevel = $this->checkPasswordComplexity($password, array_merge($itemInfos, ['folderId' => $folderId]));

            // Step 6: Check for duplicates in the system
            $this->checkForDuplicates($label, $SETTINGS, $itemInfos);

            // Step 7: Encrypt password if provided
            $cryptedData = $this->encryptPassword($password);
            $passwordKey = $cryptedData['passwordKey'];
            $password = $cryptedData['encrypted'];

            // Generate favicon URL if URL is provided and favicon_url is empty
            if (empty($data['url']) === false) {
                $data['favicon_url'] = $this->getFaviconUrl($data['url']);
            }

            // Step 8: Insert the new item into the database
            $newID = $this->insertNewItem($data, $password, $itemInfos, $complexityLevel);

            // Step 9: Handle post-insert tasks (logging, sharing, tagging, custom fields)
            $this->handlePostInsertTasks($newID, $itemInfos, $folderId, $passwordKey, $userId, $username, $tags, $fields, $data, $SETTINGS);

            // Notify WebSocket subscribers so other users viewing this folder see the new item
            emitItemEvent('created', $newID, $folderId, $label, $username, $userId);

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
     * Generate favicon URL using Google service
     * 
     * @param string $url Website URL
     * @return string|null Favicon URL
     */
    private function getFaviconUrl(string $url): ?string
    {
        try {
            $parsedUrl = parse_url($url);
            if (!isset($parsedUrl['host'])) {
                return null;
            }
            
            $domain = $parsedUrl['host'];
            
            // Quick DNS validation (very fast, ~50-100ms)
            if (!$this->isValidDomain($domain)) {
                return null;
            }
            
            // Google's service handles the rest gracefully
            return 'https://www.google.com/s2/favicons?domain=' . $domain . '&sz=32';
            
        } catch (Exception $e) {
            // Silent fail
            error_log('Favicon URL generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate domain using DNS lookup only
     * Very fast check (~50ms) without HTTP overhead
     * 
     * @param string $domain Domain to validate
     * @return bool True if domain has valid DNS records
     */
    private function isValidDomain(string $domain): bool
    {
        // Check for A or AAAA DNS records
        return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }

    /**
     * Prepares the data array for processing by combining all inputs.
     * @param array $arrItemParams - Array of item parameters
     * @return array - Returns the prepared data
     */
    private function prepareData(
        array $arrItemParams
    ) : array {
        return [
            'folderId' => (int) $arrItemParams['folder_id'],
            'label' => (string) $arrItemParams['label'],
            'password' => (string) $arrItemParams['password'],
            'description' => (string) ($arrItemParams['description'] ?? ''),
            'login' => (string) ($arrItemParams['login'] ?? ''),
            'email' => (string) ($arrItemParams['email'] ?? ''),
            'tags' => (string) ($arrItemParams['tags'] ?? ''),
            'anyoneCanModify' => (int) ($arrItemParams['anyone_can_modify'] ?? 0),
            'url' => (string) ($arrItemParams['url'] ?? ''),
            'icon' => (string) ($arrItemParams['icon'] ?? ''),
            'totp' => (string) ($arrItemParams['totp'] ?? ''),
            'favicon_url' => '',
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
            'totp' => 'trim|escape',
        ];

        dataSanitizer($data, $filters);
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
            'SELECT nt.bloquer_creation, nt.bloquer_modification,
            CASE WHEN COUNT(personal_root.id) > 0 THEN 1 ELSE nt.personal_folder END AS personal_folder
            FROM ' . prefixTable('nested_tree') . ' AS nt
            LEFT JOIN ' . prefixTable('nested_tree') . ' AS personal_root
                ON personal_root.personal_folder = 1
                AND nt.nleft >= personal_root.nleft
                AND nt.nright <= personal_root.nright
            WHERE nt.id = %i
            GROUP BY nt.id, nt.bloquer_creation, nt.bloquer_modification, nt.personal_folder',
            $folderId
        );

        if ($dataFolderSettings === false || $dataFolderSettings === null) {
            return ['personal_folder' => 0, 'no_complex_check_on_modification' => 0, 'no_complex_check_on_creation' => 0];
        }

        return [
            'personal_folder' => $dataFolderSettings['personal_folder'],
            'no_complex_check_on_modification' => (int) $dataFolderSettings['personal_folder'] === 1 ? 1 : (int) $dataFolderSettings['bloquer_modification'],
            'no_complex_check_on_creation' => (int) $dataFolderSettings['personal_folder'] === 1 ? 1 : (int) $dataFolderSettings['bloquer_creation'],
        ];
    }

    /**
     * Validates that the password meets the complexity requirements of the folder.
     * Throws an exception if the password is too weak.
     * Returns the computed complexity level so the caller can reuse it without a second zxcvbn pass.
     * @param string $password - The plaintext password to check
     * @param array $itemInfos - Folder settings including password complexity requirements
     * @return int - The computed complexity level (one of TP_PW_STRENGTH_*)
     * @throws Exception - If the password complexity is insufficient
     */
    private function checkPasswordComplexity(string $password, array $itemInfos) : int
    {
        // Check existence first
        if (isset($itemInfos['folderId']) === false) {
            throw new Exception('Folder ID is missing');
        }

        // Cast to integer for strict validation
        $folderId = (int) $itemInfos['folderId'];

        // Validate value
        if ($folderId <= 0) {
            throw new Exception('Invalid folder ID for complexity check');
        }

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

        return $passwordStrengthScore;
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
	     && (int) $itemInfos['personal_folder'] === 0)
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
     * @param int $complexityLevel - Complexity level computed from the plaintext password by checkPasswordComplexity()
     * @return int - Returns the ID of the newly created item
     */
    private function insertNewItem(array $data, string $password, array $itemInfos, int $complexityLevel) : int
    {
        include_once API_ROOT_PATH . '/../sources/main.functions.php';

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
                'complexity_level' => $complexityLevel,
                'encryption_type' => 'teampass_aes',
                'fa_icon' => $data['icon'],
                'item_key' => uniqidReal(50),
                'created_at' => time(),
                'favicon_url' => $data['favicon_url'],
            ]
        );

        $newItemId = DB::insertId();

        // Handle TOTP if provided
        if (empty($data['totp']) === false) {
            $encryptedSecret = cryption(
                $data['totp'],
                '',
                'encrypt'
            );

            DB::insert(
                prefixTable('items_otp'),
                array(
                    'item_id' => $newItemId,
                    'secret' => $encryptedSecret['string'],
                    'phone_number' => '',
                    'timestamp' => time(),
                    'enabled' => 1,
                )
            );
        }

        return $newItemId;
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
        array $fields,
        array $data,
        array $SETTINGS
    ) : void {
        // Create share keys for the creator
        storeUsersShareKey(
            'sharekeys_items',
            (int) $itemInfos['personal_folder'],
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

        // Add tags to the item
        $this->addTags($newID, $tags);

        // Store custom fields — returns the field object keys for the background task
        $fieldsForTasks = $this->handleCustomFieldsOnCreate($newID, $folderId, $fields, $userId, $itemInfos, $SETTINGS);

        // Create a task if the folder is not personal (generates pwd/fields sharekeys for the other users)
        if ((int) $itemInfos['personal_folder'] === 0) {
            storeTask('new_item', $userId, 0, $folderId, $newID, $passwordKey, $fieldsForTasks, []);
        }
    }


    /**
     * Splits the tags string into individual tags and inserts them into the database.
     *
     * Tags are stored one per row in a varchar(30) column. TeamPass uses whitespace as the
     * canonical separator (web UI, browser extension); commas are also accepted for robustness
     * and to round-trip the comma-separated GET response. Each tag is trimmed, lowercased and
     * capped at 30 characters to avoid silent truncation or strict-mode INSERT failures.
     *
     * @param int $newID - The ID of the item to associate tags with
     * @param string $tags - A whitespace- or comma-separated string of tags
     */
    private function addTags(int $newID, string $tags) : void
    {
        $tagsArray = preg_split('/[\s,]+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
        if ($tagsArray === false) {
            return;
        }
        foreach ($tagsArray as $tag) {
            $tag = mb_substr(trim($tag), 0, 30);
            if ($tag !== '') {
                DB::insert(
                    prefixTable('tags'),
                    ['item_id' => $newID, 'tag' => mb_strtolower($tag)]
                );
            }
        }
    }


    /**
     * Return the custom field IDs available for a folder.
     *
     * A folder is linked to top-level categories (categories_folders); the actual fields are
     * the child categories (parent_id IN those categories). Used to restrict writes/reads to
     * the fields that legitimately belong to the item's folder.
     *
     * @param int $folderId
     * @return int[] List of field (category) IDs
     */
    private function getFolderFieldIds(int $folderId): array
    {
        $catRows = DB::query(
            'SELECT id_category FROM ' . prefixTable('categories_folders') . ' WHERE id_folder = %i',
            $folderId
        );
        if (DB::count() === 0) {
            return [];
        }
        $arrCatList = array_map('intval', array_column($catRows, 'id_category'));

        $fieldRows = DB::query(
            'SELECT id FROM ' . prefixTable('categories') . ' WHERE parent_id IN %li',
            $arrCatList
        );

        return array_map('intval', array_column($fieldRows, 'id'));
    }


    /**
     * Read and decrypt the custom fields attached to an item.
     *
     * Only fields whose category is associated to the item's folder are returned. Encrypted
     * fields are decrypted with migration-aware sharekey handling and base64-decoded to return
     * clean plaintext (same convention as the password path). A field without an available
     * sharekey returns an empty value rather than leaking ciphertext.
     *
     * @param int    $itemId         Item ID
     * @param int    $folderId       Folder the item belongs to
     * @param int    $userId         Requesting user ID
     * @param string $userPrivateKey User private key (already decrypted)
     * @param string $userPublicKey  User public key
     *
     * @return array<int, array{id:int, title:string, type:string, masked:int, value:string}>
     */
    private function getItemCustomFields(int $itemId, int $folderId, int $userId, string $userPrivateKey, string $userPublicKey): array
    {
        // Categories associated to the item's folder
        $catRows = DB::query(
            'SELECT id_category FROM ' . prefixTable('categories_folders') . ' WHERE id_folder = %i',
            $folderId
        );
        if (DB::count() === 0) {
            return [];
        }
        $arrCatList = array_map('intval', array_column($catRows, 'id_category'));

        // Field values for this item, restricted to the folder's categories
        $rows = DB::query(
            'SELECT i.id AS object_id, i.field_id AS field_id, i.data AS data,
                i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data,
                c.title AS title, c.type AS type, c.masked AS masked
            FROM ' . prefixTable('categories_items') . ' AS i
            INNER JOIN ' . prefixTable('categories') . ' AS c ON (i.field_id = c.id)
            WHERE i.item_id = %i AND c.parent_id IN %li',
            $itemId,
            $arrCatList
        );

        $fields = [];
        foreach ($rows as $row) {
            $value = '';
            $isEncrypted = (int) $row['encrypted_data'] === 1 && $row['encryption_type'] !== 'not_set';

            if ($isEncrypted === true) {
                $userKey = DB::queryFirstRow(
                    'SELECT share_key, increment_id
                    FROM ' . prefixTable('sharekeys_fields') . '
                    WHERE user_id = %i AND object_id = %i',
                    $userId,
                    $row['object_id']
                );
                // Decrypt only when a sharekey is available; otherwise leave the value empty
                if (DB::count() > 0) {
                    try {
                        $value = (string) base64_decode(
                            (string) doDataDecryption(
                                $row['data'],
                                decryptUserObjectKeyWithMigration(
                                    $userKey['share_key'],
                                    $userPrivateKey,
                                    $userPublicKey,
                                    (int) $userKey['increment_id'],
                                    'sharekeys_fields'
                                )
                            )
                        );
                    } catch (Exception $e) {
                        error_log('[API] ItemModel::getItemCustomFields decryption error for field ' . $row['field_id'] . ': ' . $e->getMessage());
                        $value = '';
                    }
                }
            } else {
                $value = (string) $row['data'];
            }

            $fields[] = [
                'id' => (int) $row['field_id'],
                'title' => (string) $row['title'],
                'type' => (string) $row['type'],
                'masked' => (int) $row['masked'],
                'value' => $value,
            ];
        }

        return $fields;
    }


    /**
     * Insert the provided custom fields for a newly created item.
     *
     * Encrypt-before-INSERT for fields flagged as encrypted, create the share keys
     * synchronously, and collect the field object keys so the caller can pass them to the
     * background task (other users' keys). Only fields belonging to the item's folder and with
     * a non-empty value are stored.
     *
     * @param int   $newID     New item ID
     * @param int   $folderId  Folder the item belongs to
     * @param array $fields    Normalized fields: [ ['id'=>int,'value'=>string], ... ]
     * @param int   $userId    Creator user ID
     * @param array $itemInfos Folder settings (incl. personal_folder flag)
     * @param array $SETTINGS  Global settings
     *
     * @return array<int, array{object_id:int, object_key:string}> Field keys for the background task
     */
    private function handleCustomFieldsOnCreate(int $newID, int $folderId, array $fields, int $userId, array $itemInfos, array $SETTINGS): array
    {
        $fieldsForTasks = [];

        if (
            empty($fields) === true
            || isset($SETTINGS['item_extra_fields']) === false
            || (int) $SETTINGS['item_extra_fields'] !== 1
        ) {
            return $fieldsForTasks;
        }

        // Security: only store fields that legitimately belong to the item's folder
        $allowedFieldIds = $this->getFolderFieldIds($folderId);
        if (empty($allowedFieldIds) === true) {
            return $fieldsForTasks;
        }

        foreach ($fields as $field) {
            $fieldId = (int) ($field['id'] ?? 0);
            $value = (string) ($field['value'] ?? '');

            if ($fieldId <= 0 || $value === '' || in_array($fieldId, $allowedFieldIds, true) === false) {
                continue;
            }

            $cat = DB::queryFirstRow(
                'SELECT encrypted_data FROM ' . prefixTable('categories') . ' WHERE id = %i',
                $fieldId
            );
            if ($cat === null) {
                continue;
            }

            if ((int) $cat['encrypted_data'] === 1) {
                // Encrypt before INSERT so plaintext never lands in DB
                $cryptedStuff = doDataEncryption($value);

                DB::insert(
                    prefixTable('categories_items'),
                    [
                        'item_id' => $newID,
                        'field_id' => $fieldId,
                        'data' => $cryptedStuff['encrypted'],
                        'data_iv' => '',
                        'encryption_type' => 'teampass_aes',
                    ]
                );
                $newObjectId = (int) DB::insertId();

                // Create share keys (other users are also covered by the background task)
                storeUsersShareKey(
                    'sharekeys_fields',
                    (int) $itemInfos['personal_folder'],
                    $newObjectId,
                    $cryptedStuff['objectKey'],
                    true,
                    false,
                    [],
                    -1,
                    $userId
                );

                $fieldsForTasks[] = [
                    'object_id' => $newObjectId,
                    'object_key' => $cryptedStuff['objectKey'],
                ];
            } else {
                DB::insert(
                    prefixTable('categories_items'),
                    [
                        'item_id' => $newID,
                        'field_id' => $fieldId,
                        'data' => $value,
                        'data_iv' => '',
                        'encryption_type' => 'not_set',
                    ]
                );
            }
        }

        return $fieldsForTasks;
    }


    /**
     * Create or update the provided custom fields for an existing item.
     *
     * For each field: insert it if absent, otherwise compare against the current (decrypted)
     * value and update only when it changed. Encrypted fields are re-encrypted and their share
     * keys refreshed synchronously for all eligible users — consistent with how the API refreshes
     * the password share keys on update. Empty values are ignored (a field is not cleared).
     *
     * @param int    $itemId         Item ID
     * @param int    $folderId       Effective folder ID (target folder if the item is moved)
     * @param array  $fields         Normalized fields: [ ['id'=>int,'value'=>string], ... ]
     * @param array  $userData       User data from JWT token (id, username)
     * @param string $userPrivateKey User private key (already decrypted)
     * @param array  $SETTINGS       Global settings
     *
     * @return void
     */
    private function handleCustomFieldsOnUpdate(int $itemId, int $folderId, array $fields, array $userData, string $userPrivateKey, array $SETTINGS): void
    {
        if (
            empty($fields) === true
            || isset($SETTINGS['item_extra_fields']) === false
            || (int) $SETTINGS['item_extra_fields'] !== 1
        ) {
            return;
        }

        $allowedFieldIds = $this->getFolderFieldIds($folderId);
        if (empty($allowedFieldIds) === true) {
            return;
        }

        $userId = (int) $userData['id'];
        $username = (string) ($userData['username'] ?? '');
        $personal = (int) $this->getFolderSettings($folderId)['personal_folder'];

        // User public key (needed to decrypt the current encrypted values for comparison)
        $userPublicKey = '';
        $userKeyRow = DB::queryFirstRow(
            'SELECT public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );
        if ($userKeyRow !== null) {
            $userPublicKey = (string) $userKeyRow['public_key'];
        }

        foreach ($fields as $field) {
            $fieldId = (int) ($field['id'] ?? 0);
            $value = (string) ($field['value'] ?? '');

            if ($fieldId <= 0 || $value === '' || in_array($fieldId, $allowedFieldIds, true) === false) {
                continue;
            }

            $existing = DB::queryFirstRow(
                'SELECT i.id AS object_id, i.data AS data, i.encryption_type AS encryption_type,
                    c.encrypted_data AS encrypted_data, c.title AS title
                FROM ' . prefixTable('categories_items') . ' AS i
                INNER JOIN ' . prefixTable('categories') . ' AS c ON (i.field_id = c.id)
                WHERE i.item_id = %i AND i.field_id = %i',
                $itemId,
                $fieldId
            );

            // New field value for this item
            if ($existing === null) {
                if ((int) $this->isFieldEncrypted($fieldId) === 1) {
                    $cryptedStuff = doDataEncryption($value);
                    DB::insert(
                        prefixTable('categories_items'),
                        [
                            'item_id' => $itemId,
                            'field_id' => $fieldId,
                            'data' => $cryptedStuff['encrypted'],
                            'data_iv' => '',
                            'encryption_type' => 'teampass_aes',
                        ]
                    );
                    $newObjectId = (int) DB::insertId();
                    storeUsersShareKey(
                        'sharekeys_fields',
                        $personal,
                        $newObjectId,
                        $cryptedStuff['objectKey'],
                        false,
                        true,
                        [],
                        -1,
                        $userId
                    );
                } else {
                    DB::insert(
                        prefixTable('categories_items'),
                        [
                            'item_id' => $itemId,
                            'field_id' => $fieldId,
                            'data' => $value,
                            'data_iv' => '',
                            'encryption_type' => 'not_set',
                        ]
                    );
                }
                logItems($SETTINGS, $itemId, '', $userId, 'at_modification', $username, 'at_field');
                continue;
            }

            // Field already exists — compare current value, update only if it changed
            $objectId = (int) $existing['object_id'];
            $oldValue = '';

            if ($existing['encryption_type'] !== 'not_set') {
                $userKey = DB::queryFirstRow(
                    'SELECT share_key, increment_id
                    FROM ' . prefixTable('sharekeys_fields') . '
                    WHERE user_id = %i AND object_id = %i',
                    $userId,
                    $objectId
                );
                if (DB::count() > 0) {
                    $oldValue = (string) base64_decode(
                        (string) doDataDecryption(
                            $existing['data'],
                            decryptUserObjectKeyWithMigration(
                                $userKey['share_key'],
                                $userPrivateKey,
                                $userPublicKey,
                                (int) $userKey['increment_id'],
                                'sharekeys_fields'
                            )
                        )
                    );
                }
            } else {
                $oldValue = (string) $existing['data'];
            }

            if ($value === $oldValue) {
                continue;
            }

            if ((int) $existing['encrypted_data'] === 1) {
                $cryptedStuff = doDataEncryption($value);
                DB::update(
                    prefixTable('categories_items'),
                    [
                        'data' => $cryptedStuff['encrypted'],
                        'data_iv' => '',
                        'encryption_type' => 'teampass_aes',
                    ],
                    'item_id = %i AND field_id = %i',
                    $itemId,
                    $fieldId
                );
                storeUsersShareKey(
                    'sharekeys_fields',
                    $personal,
                    $objectId,
                    $cryptedStuff['objectKey'],
                    false,
                    true,
                    [],
                    -1,
                    $userId
                );
            } else {
                DB::update(
                    prefixTable('categories_items'),
                    [
                        'data' => $value,
                        'data_iv' => '',
                        'encryption_type' => 'not_set',
                    ],
                    'item_id = %i AND field_id = %i',
                    $itemId,
                    $fieldId
                );
            }

            logItems($SETTINGS, $itemId, (string) $existing['title'], $userId, 'at_modification', $username, 'at_field : ' . (string) $existing['title']);
        }
    }


    /**
     * Whether a field (category) is flagged as encrypted.
     *
     * @param int $fieldId
     * @return int 1 if encrypted, 0 otherwise
     */
    private function isFieldEncrypted(int $fieldId): int
    {
        $cat = DB::queryFirstRow(
            'SELECT encrypted_data FROM ' . prefixTable('categories') . ' WHERE id = %i',
            $fieldId
        );

        return ($cat !== null && (int) $cat['encrypted_data'] === 1) ? 1 : 0;
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

    /**
     * Main function to update an existing item in the database.
     * It handles data validation, password encryption, folder permission checks,
     * and updates the item with the provided fields.
     *
     * @param int $itemId The ID of the item to update
     * @param array $params Array of parameters to update
     * @param array $userData User data from JWT token
     * @param string $userPrivateKey User's private key for encryption
     * @return array Returns success or error response
     */
    public function updateItem(
        int $itemId,
        array $params,
        array $userData,
        string $userPrivateKey
    ): array
    {
        try {
            include_once API_ROOT_PATH . '/../sources/main.functions.php';

            // Load config
            $configManager = new ConfigManager();
            $SETTINGS = $configManager->getAllSettings();

            // Load current item data
            $currentItem = DB::queryFirstRow(
                'SELECT * FROM ' . prefixTable('items') . ' WHERE id = %i',
                $itemId
            );

            if (DB::count() === 0) {
                return [
                    'error' => true,
                    'error_message' => 'Item not found',
                    'error_header' => 'HTTP/1.1 404 Not Found',
                ];
            }

            // Prepare update data
            $updateData = [];
            $passwordKey = null;
            $newPassword = null;

            // Handle folder_id change
            if (isset($params['folder_id'])) {
                $newFolderId = (int) $params['folder_id'];
                $folderAccessModel = new FolderAccessModel();

                if ($folderAccessModel->canUseFolder($userData, (int) $currentItem['id_tree']) === false) {
                    return [
                        'error' => true,
                        'error_message' => 'Access denied to the source folder',
                        'error_header' => 'HTTP/1.1 403 Forbidden',
                    ];
                }

                if ($folderAccessModel->canUseFolder($userData, $newFolderId) === false) {
                    return [
                        'error' => true,
                        'error_message' => 'Access denied to the target folder',
                        'error_header' => 'HTTP/1.1 403 Forbidden',
                    ];
                }

                if ($folderAccessModel->isFolderReadOnlyForUser($newFolderId, (int) $userData['id'])) {
                    return [
                        'error' => true,
                        'error_message' => 'Access denied: target folder is read-only',
                        'error_header' => 'HTTP/1.1 403 Forbidden',
                    ];
                }

                $targetItemInfos = $this->getFolderSettings($newFolderId);
                $updateData['id_tree'] = $newFolderId;
                $updateData['perso'] = (int) $targetItemInfos['personal_folder'];
            }

            // Generate favicon URL if URL is provided and favicon_url is empty
            if (empty($currentItem['url']) === false) {
                $updateData['favicon_url'] = $this->getFaviconUrl($currentItem['url']);
            }

            $fieldsDefinitions = [
                'label'             => ['db_key' => 'label', 'type' => 'string'],
                'description'       => ['db_key' => 'description', 'type' => 'string'],
                'login'             => ['db_key' => 'login', 'type' => 'string'],
                'email'             => ['db_key' => 'email', 'type' => 'string'],
                'url'               => ['db_key' => 'url', 'type' => 'string'],
                'icon'              => ['db_key' => 'fa_icon', 'type' => 'string'],
                'anyone_can_modify' => ['db_key' => 'anyone_can_modify', 'type' => 'int'],
                'favicon_url' => ['db_key' => 'favicon_url', 'type' => 'string']
            ];
            foreach ($fieldsDefinitions as $paramKey => $def) {
                if (isset($params[$paramKey])) {
                    $updateData[$def['db_key']] = match($def['type']) {
                        'int'   => (int) $params[$paramKey],
                        default => $params[$paramKey],
                    };
                }
            }

            // Handle password update
            if (isset($params['password']) && !empty($params['password'])) {
                $newPassword = $params['password'];

                // Validate password length
                if (strlen($newPassword) > $SETTINGS['pwd_maximum_length']) {
                    return [
                        'error' => true,
                        'error_message' => 'Password is too long (max allowed is ' . $SETTINGS['pwd_maximum_length'] . ' characters)',
                        'error_header' => 'HTTP/1.1 400 Bad Request',
                    ];
                }

                // Get folder ID for complexity check
                $folderId = isset($updateData['id_tree']) ? $updateData['id_tree'] : $currentItem['id_tree'];

                // Get folder settings
                $itemInfos = $this->getFolderSettings((int) $folderId);

                // Check password complexity — capture score to avoid re-running zxcvbn on the ciphertext
                $complexityLevel = $this->checkPasswordComplexity($newPassword, array_merge($itemInfos, ['folderId' => $folderId]));

                // Encrypt password
                $cryptedData = $this->encryptPassword($newPassword);
                $passwordKey = $cryptedData['passwordKey'];
                $updateData['pw'] = $cryptedData['encrypted'];
                $updateData['pw_len'] = strlen($newPassword);
                $updateData['complexity_level'] = $complexityLevel;
            }
            
            // Update the item
            if (!empty($updateData) || (isset($params['totp']) === true && empty($params['totp']) === false)) {
                $updateData['updated_at'] = time();

                DB::update(
                    prefixTable('items'),
                    $updateData,
                    'id = %i',
                    $itemId
                );

                // Handle TOTP update
                if (isset($params['totp']) === true && empty($params['totp']) === false) {
                    $encryptedSecret = cryption(
                        $params['totp'],
                        '',
                        'encrypt'
                    );
                    
                    DB::insertUpdate(
                        prefixTable('items_otp'),
                        [
                            'item_id' => $itemId,
                            'secret' => $encryptedSecret['string'],
                            'phone_number' => '',
                            'timestamp' => time(),
                            'enabled' => 1,
                        ]
                    );
                }
            } else {
                // Check if an entry exists for TOTP in items_otp table
                // IF yes delete it
                if (isset($params['totp']) === true && empty($params['totp']) === true) {
                    DB::delete(
                        prefixTable('items_otp'),
                        'item_id = %i',
                        $itemId
                    );
                }
            }
            
            // Handle tags update
            if (isset($params['tags'])) {
                // Delete existing tags
                DB::delete(
                    prefixTable('tags'),
                    'item_id = %i',
                    $itemId
                );

                // Add new tags
                $this->addTags($itemId, $params['tags']);
            }

            // Handle custom fields update
            if (isset($params['fields']) && is_array($params['fields'])) {
                $effectiveFolderId = isset($updateData['id_tree'])
                    ? (int) $updateData['id_tree']
                    : (int) $currentItem['id_tree'];
                $this->handleCustomFieldsOnUpdate($itemId, $effectiveFolderId, $params['fields'], $userData, $userPrivateKey, $SETTINGS);
            }

            // If password was updated, update share keys
            if ($passwordKey !== null) {
                // Get folder ID (either new or current)
                $folderId = isset($updateData['id_tree']) ? $updateData['id_tree'] : $currentItem['id_tree'];

                // Get folder settings
                $itemInfos = $this->getFolderSettings((int) $folderId);

                // Update share keys for all users with access
                storeUsersShareKey(
                    'sharekeys_items',
                    (int) $itemInfos['personal_folder'],
                    $itemId,
                    $passwordKey,
                    false,
                    true,
                    [],
                    -1,
                    $userData['id']
                );
            }

            // Log the update
            $label = isset($updateData['label']) ? $updateData['label'] : $currentItem['label'];
            logItems($SETTINGS, $itemId, $label, $userData['id'], 'at_modification', $userData['username']);

            // Success response
            return [
                'error' => false,
                'message' => 'Item updated successfully',
                'item_id' => $itemId,
            ];

        } catch (Exception $e) {
            // Error response
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 500 Internal Server Error',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Main function to delete an existing item in the database.
     *
     * @param int $itemId The ID of the item to delete
     * @param array $userData User data from JWT token
     * @return array Returns success or error response
     */
    public function deleteItem(
        int $itemId,
        array $userData
    ): array
    {
        try {
            include_once API_ROOT_PATH . '/../sources/main.functions.php';

            // Load config
            $configManager = new ConfigManager();
            $SETTINGS = $configManager->getAllSettings();

            // Load current item data
            $currentItem = DB::queryFirstRow(
                'SELECT * FROM ' . prefixTable('items') . ' WHERE id = %i',
                $itemId
            );

            if (DB::count() === 0) {
                return [
                    'error' => true,
                    'error_message' => 'Item not found',
                    'error_header' => 'HTTP/1.1 404 Not Found',
                ];
            }

            // delete item consists in disabling it
            DB::update(
                prefixTable('items'),
                array(
                    'inactif' => '1',
                    'deleted_at' => time(),
                ),
                'id = %i',
                $itemId
            );

            logItems($SETTINGS, $itemId, $currentItem['label'], $userData['id'], 'at_delete', $userData['username']);

            // Success response
            return [
                'error' => false,
                'message' => 'Item deleted successfully',
                'item_id' => $itemId,
            ];

        } catch (Exception $e) {
            // Error response
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 500 Internal Server Error',
                'error_message' => $e->getMessage(),
            ];
        }
    }

}
