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
use voku\helper\AntiXSS;

class ItemModel
{

    /**
     * Get the list of items to return
     *
     * @param string $sqlExtra WHERE clause, may contain MeekroDB placeholders (%s, ...)
     * @param integer $limit
     * @param string $userPrivateKey
     * @param integer $userId
     * @param bool $showItem Kept for caller compatibility — access is now logged on every path
     * @param integer $offset Pagination offset — only applied when a limit is set
     * @param array $sqlParams Values bound to the placeholders in $sqlExtra, in order
     *
     * @return array
     */
    public function getItems(string $sqlExtra, int $limit, string $userPrivateKey, int $userId, bool $showItem = false, int $offset = 0, array $sqlParams = []): array
    {
        // Fetch user's public key (migration-aware decryption) once.
        $userPublicKey = '';
        $userRoles = '';
        $userKeyRow = DB::queryFirstRow(
            'SELECT public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );
        if ($userKeyRow !== null) {
            $userPublicKey = (string) $userKeyRow['public_key'];
        }
        // Roles (field visibility) live in the dedicated users_roles table; the
        // ';'-separated 'fonction_id' exposed elsewhere (api/index.php, core.php) is a
        // derived GROUP_CONCAT alias, not a physical column on the users table.
        $userRolesRow = DB::queryFirstRow(
            'SELECT GROUP_CONCAT(DISTINCT role_id ORDER BY role_id SEPARATOR ";") AS fonction_id
             FROM ' . prefixTable('users_roles') . ' WHERE user_id = %i AND source = %s',
            $userId,
            'manual'
        );
        $userRoles = $userRolesRow !== null ? (string) ($userRolesRow['fonction_id'] ?? '') : '';

        // Load settings once to know whether custom fields are enabled
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();
        $itemExtraFields = isset($SETTINGS['item_extra_fields']) && (int) $SETTINGS['item_extra_fields'] === 1;

        // Get items
        $rows = DB::query(
            "SELECT i.id, i.label, i.description, i.pw, i.pw_iv, i.url, i.id_tree, i.login, i.email,
                i.viewed_no, i.fa_icon, i.inactif, i.perso, i.favicon_url, i.anyone_can_modify,
                i.revision,
                t.title as folder_label,
                io.secret as otp_secret,
                io.algorithm as otp_algorithm,
                io.digits as otp_digits,
                io.period as otp_period,
                (SELECT GROUP_CONCAT(tg.tag SEPARATOR ', ') 
                 FROM " . prefixTable('tags') . " AS tg 
                 WHERE tg.item_id = i.id) as tags
            FROM " . prefixTable('items') . " AS i
            LEFT JOIN " . prefixTable('nested_tree') . " AS t ON (t.id = i.id_tree)
            LEFT JOIN " . prefixTable('items_otp') . " AS io ON (io.item_id = i.id)".
            $sqlExtra .
            " ORDER BY i.id ASC" .
            ($limit > 0 ? " LIMIT " . ($offset > 0 ? $offset . ", " : "") . $limit : ''),
            ...$sqlParams
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
                        ),
                        (string) ($row['pw_iv'] ?? '')
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
                ? $this->getItemCustomFields((int) $row['id'], (int) $row['id_tree'], $userId, $userPrivateKey, $userPublicKey, $userRoles)
                : [];

            array_push(
                $ret,
                [
                    'id' => (int) $row['id'],
                    'revision' => (int) $row['revision'],
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
                    'totp_algorithm' => empty($row['otp_algorithm']) === false
                        ? (string) $row['otp_algorithm']
                        : ITEM_TOTP_DEFAULT_ALGORITHM,
                    'totp_digits' => empty($row['otp_digits']) === false
                        ? (int) $row['otp_digits']
                        : ITEM_TOTP_DEFAULT_DIGITS,
                    'totp_period' => empty($row['otp_period']) === false
                        ? (int) $row['otp_period']
                        : ITEM_TOTP_DEFAULT_PERIOD,
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
     * Count items matching a WHERE clause — pagination total (X-Total-Count).
     *
     * The count is taken before the per-item sharekey filtering performed in
     * getItems(): it is the number of matching items in accessible folders,
     * not the number of items the user can currently decrypt.
     *
     * @param string $sqlExtra WHERE clause referencing the 'i' items alias only,
     *                         may contain MeekroDB placeholders (%s, ...)
     * @param array $sqlParams Values bound to the placeholders in $sqlExtra, in order
     *
     * @return int
     */
    public function countItems(string $sqlExtra, array $sqlParams = []): int
    {
        return (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('items') . ' AS i ' . $sqlExtra,
            ...$sqlParams
        );
    }
    //end countItems()

    /**
     * Bounds of the change journal.
     *
     * The lowest revision still stored tells whether a client cursor is old enough to have
     * lost entries to retention; the highest is the cursor a full resynchronization adopts.
     *
     * @return array{min: int|null, max: int} Null min when the journal is empty
     */
    public function getRevisionBounds(): array
    {
        $row = DB::queryFirstRow(
            'SELECT MIN(revision) AS min_revision, MAX(revision) AS max_revision
            FROM ' . prefixTable('items_revisions')
        );

        if ($row === null || $row['min_revision'] === null) {
            return ['min' => null, 'max' => 0];
        }

        return [
            'min' => (int) $row['min_revision'],
            'max' => (int) $row['max_revision'],
        ];
    }
    //end getRevisionBounds()

    /**
     * Changes a client must apply to its cache since a given cursor.
     *
     * The journal is the scan target — not the items table — because it is the only place
     * where an item that was hard deleted, or that left the caller's folders, still leaves
     * a trace. Items are joined afterwards to resolve what the caller can currently read.
     *
     * @param int    $since             Client cursor, exclusive
     * @param int    $limit             Maximum journal entries scanned
     * @param string $journalScopeSql   Scope clause on the journal alias 'r', starting with AND
     * @param string $itemVisibilitySql Access clause on the items alias 'i', starting with AND
     * @param string $userPrivateKey    Caller private key, to decrypt the payloads
     * @param int    $userId            Caller id
     *
     * @return array{cursor: int, has_more: bool, changed: array, removed: array}
     */
    public function getItemChanges(
        int $since,
        int $limit,
        string $journalScopeSql,
        string $itemVisibilitySql,
        string $userPrivateKey,
        int $userId
    ): array {
        // 1. Scan the journal window.
        $journalRows = DB::query(
            'SELECT r.revision, r.item_id, r.action
            FROM ' . prefixTable('items_revisions') . ' AS r
            WHERE r.revision > %i' . $journalScopeSql . '
            ORDER BY r.revision ASC
            LIMIT %i',
            $since,
            $limit
        );

        $scannedRevisions = array_map(static fn (array $row): int => (int) $row['revision'], $journalRows);
        $hasMore = count($journalRows) >= $limit;

        if ($journalRows === []) {
            return [
                'cursor' => $since,
                'has_more' => false,
                'changed' => [],
                'removed' => [],
            ];
        }

        // 2. One entry per item: three edits since the cursor are one download.
        $winners = itemRevisionDedupeScan($journalRows);
        $candidateIds = array_keys($winners);

        // 3. Resolve the current state. Two queries: what the caller can read, and what
        //    still exists at all — the difference tells a purge from a lost access.
        $visibleItemIds = [];
        $deletedItemIds = [];
        $readableRows = DB::query(
            'SELECT i.id, i.deleted_at
            FROM ' . prefixTable('items') . ' AS i
            WHERE i.id IN %li' . $itemVisibilitySql,
            $candidateIds
        );
        foreach ($readableRows as $row) {
            $visibleItemIds[(int) $row['id']] = true;
            if (empty($row['deleted_at']) === false) {
                $deletedItemIds[(int) $row['id']] = true;
            }
        }

        $existingItemIds = [];
        $existingRows = DB::query(
            'SELECT id FROM ' . prefixTable('items') . ' WHERE id IN %li',
            $candidateIds
        );
        foreach ($existingRows as $row) {
            $existingItemIds[(int) $row['id']] = true;
        }

        // 4. Classify.
        $removed = [];
        $toDeliver = [];
        foreach ($winners as $itemId => $row) {
            $verdict = itemRevisionClassifyScanRow(
                (int) $itemId,
                $visibleItemIds,
                isset($existingItemIds[(int) $itemId]),
                isset($deletedItemIds[(int) $itemId])
            );

            if ($verdict['classification'] === 'removed') {
                $removed[] = [
                    'id' => (int) $itemId,
                    'revision' => (int) $row['revision'],
                    'reason' => $verdict['reason'],
                ];
                continue;
            }

            $toDeliver[(int) $itemId] = (int) $row['revision'];
        }

        // 5. Materialize the payloads, reusing the very code path item/get uses so the feed
        //    stays byte-consistent with it: sharekeys, custom fields, TOTP and audit log.
        $changed = [];
        if ($toDeliver !== []) {
            $changed = $this->getItems(
                'WHERE i.id IN %li' . $itemVisibilitySql,
                0,
                $userPrivateKey,
                $userId,
                false,
                0,
                [array_keys($toDeliver)]
            );
        }

        // 6. An item whose sharekeys are still being distributed cannot be handed over yet.
        //    Its revision must hold the cursor back, or the client would never see it.
        $delivered = [];
        foreach ($changed as $item) {
            $delivered[(int) $item['id']] = true;
        }
        $undeliverable = [];
        foreach ($toDeliver as $itemId => $revision) {
            if (isset($delivered[$itemId]) === false) {
                $undeliverable[] = $revision;
            }
        }

        $cursor = itemRevisionResolveCursor($since, $scannedRevisions, $undeliverable);

        // Entries held back are offered again on the next call.
        if ($undeliverable !== []) {
            $hasMore = true;
            $changed = array_values(array_filter(
                $changed,
                static fn (array $item): bool => (int) ($item['revision'] ?? 0) <= $cursor
            ));
            $removed = array_values(array_filter(
                $removed,
                static fn (array $entry): bool => (int) $entry['revision'] <= $cursor
            ));
        }

        return [
            'cursor' => $cursor,
            'has_more' => $hasMore,
            'changed' => $changed,
            'removed' => $removed,
        ];
    }
    //end getItemChanges()

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
            $data = $this->validateData($data); // Step 2: Sanitize the data

            if (empty($data['totp']) === false) {
                $otpConfiguration = normalizeItemTotpConfiguration(
                    $data['totp'],
                    $data['totpAlgorithm'],
                    $data['totpDigits'],
                    $data['totpPeriod']
                );
                $data['totp'] = $otpConfiguration['secret'];
                $data['totpAlgorithm'] = $otpConfiguration['algorithm'];
                $data['totpDigits'] = $otpConfiguration['digits'];
                $data['totpPeriod'] = $otpConfiguration['period'];
            }

            // Realign the values extracted above on the sanitized ones: they feed the duplicate
            // check, the tags and the WebSocket event, which must all see what is actually stored.
            $label = $data['label'];
            $tags = $data['tags'];

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
            // Keep the plaintext for the post-insert security-posture refresh (reuse hashing).
            $plaintextPasswordForHealth = $password;
            $cryptedData = $this->encryptPassword($password);
            $passwordKey = $cryptedData['passwordKey'];
            $password = $cryptedData['encrypted'];
            $passwordIv = $cryptedData['meta'] ?? '';

            // Generate favicon URL if URL is provided and favicon_url is empty
            if (empty($data['url']) === false) {
                $data['favicon_url'] = $this->getFaviconUrl($data['url']);
            }

            // Step 8: Insert the new item into the database
            $newID = $this->insertNewItem($data, $password, $itemInfos, $complexityLevel, $passwordIv);

            // Step 9: Handle post-insert tasks (logging, sharing, tagging, custom fields)
            $this->handlePostInsertTasks($newID, $itemInfos, $folderId, $passwordKey, $userId, $username, $tags, $fields, $data, $SETTINGS);

            updateCacheTable('add_value', $newID, $userId);

            // Notify WebSocket subscribers so other users viewing this folder see the new item
            emitItemEvent('created', $newID, $folderId, $label, $username, $userId);

            // Refresh the caller's security posture so a reused/weak password is flagged without
            // waiting for a manual dashboard scan (no-op when the dashboard is disabled).
            refreshItemHealthAfterSave((int) $newID, $userId, (string) $plaintextPasswordForHealth, $SETTINGS);

            // Success response
            return [
                'error' => false,
                'message' => 'Item added successfully',
                'newId' => $newID,
                'revision' => getItemRevision((int) $newID),
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
            // Constrain the icon to safe Font Awesome class characters (letters, digits, space, underscore, hyphen)
            'icon' => (string) preg_replace('/[^a-zA-Z0-9 _-]/', '', (string) ($arrItemParams['icon'] ?? '')),
            'totp' => (string) ($arrItemParams['totp'] ?? ''),
            'totpAlgorithm' => (string) ($arrItemParams['totp_algorithm'] ?? ITEM_TOTP_DEFAULT_ALGORITHM),
            'totpDigits' => (int) ($arrItemParams['totp_digits'] ?? ITEM_TOTP_DEFAULT_DIGITS),
            'totpPeriod' => (int) ($arrItemParams['totp_period'] ?? ITEM_TOTP_DEFAULT_PERIOD),
            'favicon_url' => '',
        ];
    }

    /**
     * Neutralizes the item fields that are later rendered as HTML.
     *
     * This method used to hand its data to dataSanitizer() and throw the result away, so the
     * API stored every field exactly as the client sent it. The web form encodes the same
     * fields before storing them (see items.queries.php), and the renderers - items list,
     * recycle bin - rely on that: a label is inserted into the page markup as-is. An API
     * client could therefore store an item label carrying an event handler and have it execute
     * in the browser of anyone listing the folder or opening the recycle bin
     * (GHSA-r298-6mxv-j9hc).
     *
     * Each field gets the same treatment as its web counterpart, so both paths store the same
     * bytes. 'password' and 'totp' are deliberately left untouched: they are secrets, never
     * rendered as markup, and must keep their exact value.
     *
     * @param array $data - Data to be sanitized
     * @return array - The sanitized data, to be used in place of the input
     */
    private function validateData(array $data) : array
    {
        // Fully encoded: rendered as plain text by the clients.
        foreach (['label', 'login'] as $field) {
            $data[$field] = (string) filter_var($data[$field], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $data['tags'] = htmlspecialchars($data['tags']);
        $data['email'] = (string) filter_var(htmlspecialchars_decode($data['email']), FILTER_SANITIZE_EMAIL);
        $data['url'] = $this->sanitizeItemUrl($data['url']);

        // Rich text: keep the markup the editor produces, drop what is executable.
        $antiXss = new AntiXSS();
        $data['description'] = (string) $antiXss->xss_clean($data['description']);

        return $data;
    }

    /**
     * Cleans a user-supplied URL before it is stored.
     *
     * Mirrors the web path: a javascript:/data:/vbscript: URL is script execution as soon as
     * the item card renders it as a link, so it is dropped rather than stored.
     *
     * @param string $url - Raw URL
     * @return string - URL safe to store, empty when the scheme is dangerous
     */
    private function sanitizeItemUrl(string $url) : string
    {
        $url = (string) filter_var(htmlspecialchars_decode($url), FILTER_SANITIZE_URL);

        if ($url !== '' && preg_match('#^\s*(?:javascript|data|vbscript)\s*:#i', $url) === 1) {
            return '';
        }

        return $url;
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
     * Decrypt an item password for a change-detection comparison only.
     *
     * Used by the LAPR ownership guard so a client resending the unchanged
     * password is not reported as a conflict. Returns a sentinel that can never
     * equal a submitted password when the value cannot be recovered, so an
     * undecryptable item fails closed (the update is treated as a change).
     *
     * @param int    $itemId         Item id
     * @param int    $userId         Caller id
     * @param string $userPrivateKey Caller RSA private key (cleartext)
     * @param array  $currentItem    Current items row
     * @return string Cleartext password, or a non-matchable sentinel
     */
    private function getItemPasswordForComparison(
        int $itemId,
        int $userId,
        string $userPrivateKey,
        array $currentItem
    ): string {
        if (empty($currentItem['pw']) === true) {
            return '';
        }

        $userKey = DB::queryFirstRow(
            'SELECT share_key, increment_id
            FROM ' . prefixTable('sharekeys_items') . '
            WHERE user_id = %i AND object_id = %i',
            $userId,
            $itemId
        );
        if (DB::count() === 0) {
            return "\0lapr-undecryptable";
        }

        $userPublicKey = (string) DB::queryFirstField(
            'SELECT public_key FROM ' . prefixTable('users') . ' WHERE id = %i',
            $userId
        );

        try {
            return teampassDecryptPasswordValue(
                (string) $currentItem['pw'],
                decryptUserObjectKeyWithMigration(
                    $userKey['share_key'],
                    $userPrivateKey,
                    $userPublicKey,
                    (int) $userKey['increment_id'],
                    'sharekeys_items'
                ),
                (int) ($currentItem['pw_len'] ?? 0),
                (string) ($currentItem['pw_iv'] ?? '')
            );
        } catch (Exception $e) {
            error_log('[API] ItemModel LAPR password comparison failed for item ' . $itemId . ': ' . $e->getMessage());

            return "\0lapr-undecryptable";
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
     * @throws Exception - If the password is unassessable or its complexity is insufficient
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

        $passwordStrength = evaluatePasswordStrengthSafely($password);
        if ($passwordStrength['success'] === false) {
            throw new InvalidArgumentException(
                'Password strength could not be evaluated. The password must be valid UTF-8 text.'
            );
        }
        $passwordStrengthScore = convertPasswordStrength((int) $passwordStrength['score']);

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
            'meta' => $cryptedStuff['meta'],
        ];
    }

    /**
     * Inserts the new item into the database with all its associated data.
     * @param array $data - The item data to insert
     * @param string $password - The encrypted password
     * @param array $itemInfos - Folder-specific settings
     * @param int $complexityLevel - Complexity level computed from the plaintext password by checkPasswordComplexity()
     * @param string $passwordIv - v2 metadata (pw_iv) returned by encryptPassword(); empty for legacy data
     * @return int - Returns the ID of the newly created item
     */
    private function insertNewItem(array $data, string $password, array $itemInfos, int $complexityLevel, string $passwordIv = '') : int
    {
        include_once API_ROOT_PATH . '/../sources/main.functions.php';

        DB::insert(
            prefixTable('items'),
            [
                'label' => $data['label'],
                'description' => $data['description'],
                'pw' => $password,
                'pw_iv' => $passwordIv,
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
                    'algorithm' => $data['totpAlgorithm'],
                    'digits' => $data['totpDigits'],
                    'period' => $data['totpPeriod'],
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
     * @param string $userRoles      Requesting user roles (';'-separated, from fonction_id)
     *
     * @return array<int, array{id:int, title:string, type:string, masked:int, value:string}>
     */
    private function getItemCustomFields(int $itemId, int $folderId, int $userId, string $userPrivateKey, string $userPublicKey, string $userRoles = ''): array
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
            'SELECT i.id AS object_id, i.field_id AS field_id, i.data AS data, i.data_iv AS data_iv,
                i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data,
                c.title AS title, c.type AS type, c.masked AS masked,
                c.role_visibility AS role_visibility
            FROM ' . prefixTable('categories_items') . ' AS i
            INNER JOIN ' . prefixTable('categories') . ' AS c ON (i.field_id = c.id)
            WHERE i.item_id = %i AND c.parent_id IN %li',
            $itemId,
            $arrCatList
        );

        $fields = [];
        foreach ($rows as $row) {
            // Enforce field role-based visibility (same rule as the web item card and
            // core.php "LOAD CATEGORIES"): never return a field restricted to roles the
            // requesting user does not hold, otherwise its decrypted value leaks (#5176).
            // 'all' = visible to every role.
            if (
                $row['role_visibility'] !== 'all'
                && count(
                    array_intersect(
                        explode(';', $userRoles),
                        explode(',', (string) $row['role_visibility'])
                    )
                ) === 0
            ) {
                continue;
            }

            $value = '';
            // The stored row is what decides, not the category flag: a value encrypted while
            // the field was flagged "encrypted" stays encrypted after the flag is turned off
            // (#5161). Trusting the flag returns the raw ciphertext as the field value, which a
            // read-modify-write client then writes back as plaintext (#5342).
            $isEncrypted = $row['encryption_type'] !== 'not_set';

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
                                ),
                                (string) ($row['data_iv'] ?? '')
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
                        'data_iv' => $cryptedStuff['meta'],
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
                'SELECT i.id AS object_id, i.data AS data, i.data_iv AS data_iv, i.encryption_type AS encryption_type,
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
                            'data_iv' => $cryptedStuff['meta'],
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
                            ),
                            (string) ($existing['data_iv'] ?? '')
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
                        'data_iv' => $cryptedStuff['meta'],
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
            include_once API_ROOT_PATH . '/../sources/lapr.functions.php';

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

            // Optimistic concurrency. A client that edited offline sends the revision its
            // edit was based on; if the server has moved on since, the write is refused
            // rather than silently overwriting whatever changed in the meantime.
            // Omitting the field keeps the previous last-writer-wins behaviour.
            if (isset($params['revision']) === true && $params['revision'] !== '') {
                $expectedRevision = (int) $params['revision'];
                $currentRevision = (int) ($currentItem['revision'] ?? 0);

                if ($expectedRevision !== $currentRevision) {
                    return [
                        'error' => true,
                        'error_message' => 'The item was modified since revision ' . $expectedRevision
                            . '. Current revision is ' . $currentRevision . '.',
                        'error_header' => 'HTTP/1.1 409 Conflict',
                    ];
                }
            }

            $laprRelations = laprGetItemRelations([$itemId], $SETTINGS);
            $laprRelation = $laprRelations[$itemId] ?? [];
            $laprIsManaged = (bool) ($laprRelation['is_managed'] ?? false);
            $laprIsCredential = (bool) ($laprRelation['is_credential'] ?? false);

            if ($laprIsManaged === true) {
                $laprLoginChanged = isset($params['login'])
                    && (string) filter_var((string) $params['login'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)
                        !== (string) ($currentItem['login'] ?? '');
                // Mirror the web handler: only an actual change is a conflict, so a
                // read-modify-write client resending the unchanged password is not
                // rejected. A password that cannot be decrypted fails closed.
                $laprPasswordChanged = isset($params['password'])
                    && (string) $params['password'] !== ''
                    && (string) $params['password'] !== $this->getItemPasswordForComparison(
                        $itemId,
                        (int) $userData['id'],
                        $userPrivateKey,
                        $currentItem
                    );
                if ($laprLoginChanged === true || $laprPasswordChanged === true) {
                    return [
                        'error' => true,
                        'error_message' => 'This item is managed by LAPR. Rotate its password through LAPR or remove the managed account first.',
                        'error_header' => 'HTTP/1.1 409 Conflict',
                    ];
                }
            }

            // LAPR reads item passwords server-side through the TP_USER key chain, which
            // never covers personal items — moving a linked item into a personal folder
            // would silently break every future rotation or endpoint connection.
            if (($laprIsManaged === true || $laprIsCredential === true)
                && isset($params['folder_id'])
                && (int) $params['folder_id'] !== (int) $currentItem['id_tree']
                && (int) ($this->getFolderSettings((int) $params['folder_id'])['personal_folder'] ?? 0) === 1
            ) {
                return [
                    'error' => true,
                    'error_message' => 'This item is linked to LAPR and cannot be moved to a personal folder. Remove the managed account or linked endpoint first.',
                    'error_header' => 'HTTP/1.1 409 Conflict',
                ];
            }

            // Prepare update data
            $updateData = [];
            $passwordKey = null;
            $newPassword = null;
            $hasTotpUpdate = array_key_exists('totp', $params)
                || array_key_exists('totp_algorithm', $params)
                || array_key_exists('totp_digits', $params)
                || array_key_exists('totp_period', $params);
            // Set when the request actually relocates the item, whatever the transition type.
            $moveContext = null;

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

                $sourceFolderId = (int) $currentItem['id_tree'];
                $sourceItemInfos = $this->getFolderSettings($sourceFolderId);
                $targetItemInfos = $this->getFolderSettings($newFolderId);
                // A folder_id equal to the current one is a no-op, not a move.
                $isActualMove = $newFolderId !== $sourceFolderId;

                if (
                    $isActualMove === true
                    && (int) $sourceItemInfos['personal_folder'] === 1
                    && (int) $targetItemInfos['personal_folder'] === 0
                ) {
                    // This transition commits its own transaction, so a validation failure on
                    // another field could no longer be undone afterwards. Only fields that would
                    // actually be written conflict — unknown extra keys in the payload are ignored
                    // here exactly as they are by the rest of updateItem().
                    $conflictingUpdateFields = array_intersect(
                        array_keys($params),
                        [
                            'label', 'password', 'description', 'login', 'email', 'url', 'tags',
                            'anyone_can_modify', 'icon', 'fields',
                            'totp', 'totp_algorithm', 'totp_digits', 'totp_period',
                        ]
                    );
                    if (empty($conflictingUpdateFields) === false) {
                        return [
                            'error' => true,
                            'error_message' => 'A personal-to-shared move must be requested separately from other item updates. '
                                . 'Remove: ' . implode(', ', $conflictingUpdateFields) . '.',
                            'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                        ];
                    }

                    try {
                        $moveResult = movePersonalItemToSharedFolderSynchronously(
                            $itemId,
                            $newFolderId,
                            (int) $userData['id'],
                            $userPrivateKey
                        );
                    } catch (UnexpectedValueException $exception) {
                        error_log(
                            '[API] Personal-to-shared item move rejected for item ' . $itemId
                            . ': ' . $exception->getMessage()
                        );
                        return [
                            'error' => true,
                            'error_message' => 'The item cannot be moved because one or more encryption keys are missing or invalid.',
                            'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                        ];
                    } catch (RuntimeException $exception) {
                        // Concurrent change detected under the row lock. Must stay AFTER the
                        // UnexpectedValueException catch, which is a RuntimeException subclass.
                        error_log(
                            '[API] Personal-to-shared item move conflicted for item ' . $itemId
                            . ': ' . $exception->getMessage()
                        );
                        return [
                            'error' => true,
                            'error_message' => 'The item was modified by another request. Please retry the move.',
                            'error_header' => 'HTTP/1.1 409 Conflict',
                        ];
                    } catch (InvalidArgumentException $exception) {
                        error_log(
                            '[API] Personal-to-shared item move refused for item ' . $itemId
                            . ': ' . $exception->getMessage()
                        );
                        return [
                            'error' => true,
                            'error_message' => 'This item cannot be moved to the requested folder.',
                            'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                        ];
                    }

                    $moveContext = [
                        'source_folder_id' => (int) $moveResult['source_folder_id'],
                        'source_folder_title' => (string) $moveResult['source_folder_title'],
                        'target_folder_id' => (int) $moveResult['target_folder_id'],
                        'target_folder_title' => (string) $moveResult['target_folder_title'],
                    ];

                    // Keep the in-memory row aligned for the standard response and cache refresh.
                    $currentItem['id_tree'] = $newFolderId;
                    $currentItem['perso'] = 0;
                } else {
                    $updateData['id_tree'] = $newFolderId;
                    $updateData['perso'] = (int) $targetItemInfos['personal_folder'];

                    // Every other transition is a plain folder change, but it is still a move:
                    // it deserves the same audit trail, counters and WebSocket events.
                    if ($isActualMove === true) {
                        $moveContext = [
                            'source_folder_id' => $sourceFolderId,
                            'source_folder_title' => $this->getFolderTitle($sourceFolderId),
                            'target_folder_id' => $newFolderId,
                            'target_folder_title' => $this->getFolderTitle($newFolderId),
                        ];
                    }
                }
            }

            // Generate favicon URL if URL is provided and favicon_url is empty
            if (empty($currentItem['url']) === false) {
                $updateData['favicon_url'] = $this->getFaviconUrl($currentItem['url']);
            }

            // Each field is stored the way the web form stores it, so an update cannot
            // reintroduce the markup that validateData() strips on creation
            // (GHSA-r298-6mxv-j9hc).
            $fieldsDefinitions = [
                'label'             => ['db_key' => 'label', 'type' => 'encoded'],
                'description'       => ['db_key' => 'description', 'type' => 'richtext'],
                'login'             => ['db_key' => 'login', 'type' => 'encoded'],
                'email'             => ['db_key' => 'email', 'type' => 'email'],
                'url'               => ['db_key' => 'url', 'type' => 'url'],
                'icon'              => ['db_key' => 'fa_icon', 'type' => 'icon'],
                'anyone_can_modify' => ['db_key' => 'anyone_can_modify', 'type' => 'int'],
                'favicon_url' => ['db_key' => 'favicon_url', 'type' => 'url']
            ];
            foreach ($fieldsDefinitions as $paramKey => $def) {
                if (isset($params[$paramKey])) {
                    // No default arm on purpose: every type above is handled, and a type added
                    // later without its own arm must fail loudly rather than silently store the
                    // client value verbatim.
                    $updateData[$def['db_key']] = match($def['type']) {
                        'int'      => (int) $params[$paramKey],
                        'icon'     => (string) preg_replace('/[^a-zA-Z0-9 _-]/', '', (string) $params[$paramKey]),
                        'encoded'  => (string) filter_var((string) $params[$paramKey], FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                        'email'    => (string) filter_var(htmlspecialchars_decode((string) $params[$paramKey]), FILTER_SANITIZE_EMAIL),
                        'url'      => $this->sanitizeItemUrl((string) $params[$paramKey]),
                        'richtext' => (string) (new AntiXSS())->xss_clean((string) $params[$paramKey]),
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
                $updateData['pw_iv'] = $cryptedData['meta'] ?? '';
                $updateData['pw_len'] = strlen($newPassword);
                $updateData['complexity_level'] = $complexityLevel;
            }

            $otpConfiguration = null;
            $currentOtp = [];
            $currentOtpExists = false;
            if ($hasTotpUpdate === true && !(array_key_exists('totp', $params) && empty($params['totp']))) {
                $currentOtp = DB::queryFirstRow(
                    'SELECT secret, algorithm, digits, period, enabled, phone_number
                    FROM ' . prefixTable('items_otp') . '
                    WHERE item_id = %i',
                    $itemId
                );
                $currentOtpExists = DB::count() > 0;
                if (isset($params['totp']) && empty($params['totp']) === false) {
                    $totpInput = (string) $params['totp'];
                } elseif ($currentOtpExists === true) {
                    $decryptedSecret = cryption(
                        (string) $currentOtp['secret'],
                        '',
                        'decrypt'
                    );
                    $totpInput = (string) ($decryptedSecret['string'] ?? '');
                } else {
                    throw new InvalidArgumentException('A TOTP secret is required before its profile can be configured.');
                }

                $otpConfiguration = normalizeItemTotpConfiguration(
                    $totpInput,
                    (string) ($params['totp_algorithm']
                        ?? $currentOtp['algorithm']
                        ?? ITEM_TOTP_DEFAULT_ALGORITHM),
                    (int) ($params['totp_digits']
                        ?? $currentOtp['digits']
                        ?? ITEM_TOTP_DEFAULT_DIGITS),
                    (int) ($params['totp_period']
                        ?? $currentOtp['period']
                        ?? ITEM_TOTP_DEFAULT_PERIOD)
                );
            }
            
            // Update the item
            if (!empty($updateData) || $hasTotpUpdate === true) {
                $updateData['updated_at'] = time();

                DB::update(
                    prefixTable('items'),
                    $updateData,
                    'id = %i',
                    $itemId
                );

                // Handle TOTP update. Profile-only updates reuse the encrypted
                // secret already attached to the item.
                if ($otpConfiguration !== null) {
                    $encryptedSecret = cryption(
                        $otpConfiguration['secret'],
                        '',
                        'encrypt'
                    );
                    
                    DB::insertUpdate(
                        prefixTable('items_otp'),
                        [
                            'item_id' => $itemId,
                            'secret' => $encryptedSecret['string'],
                            'algorithm' => $otpConfiguration['algorithm'],
                            'digits' => $otpConfiguration['digits'],
                            'period' => $otpConfiguration['period'],
                            'phone_number' => $currentOtpExists === true
                                ? (string) $currentOtp['phone_number']
                                : '',
                            'timestamp' => time(),
                            'enabled' => $currentOtpExists === true
                                ? (int) $currentOtp['enabled']
                                : 1,
                        ]
                    );
                }
                if (array_key_exists('totp', $params) && empty($params['totp'])) {
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

                // Add new tags — encoded like the web form does, a tag is rendered as markup
                $this->addTags($itemId, htmlspecialchars((string) $params['tags']));
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

                // Refresh the caller's security posture so the "needs attention" shield reflects
                // the new password without waiting for a manual dashboard scan (no-op when off).
                refreshItemHealthAfterSave(
                    $itemId,
                    (int) $userData['id'],
                    (string) $newPassword,
                    $SETTINGS
                );
            }

            $label = isset($updateData['label']) ? $updateData['label'] : $currentItem['label'];
            if (is_array($moveContext)) {
                finalizeItemMoveSideEffects(
                    $SETTINGS,
                    $itemId,
                    (string) $label,
                    (int) $userData['id'],
                    (string) $userData['username'],
                    $moveContext['source_folder_id'],
                    $moveContext['source_folder_title'],
                    $moveContext['target_folder_id'],
                    $moveContext['target_folder_title']
                );
            } else {
                logItems($SETTINGS, $itemId, $label, $userData['id'], 'at_modification', $userData['username']);
                updateCacheTable('update_value', $itemId, (int) $userData['id']);
            }

            // Success response
            return [
                'error' => false,
                'message' => 'Item updated successfully',
                'item_id' => $itemId,
                'revision' => getItemRevision($itemId),
            ];

        } catch (InvalidArgumentException | UnexpectedValueException $e) {
            // Validation failures carry a message that is safe and useful to the client.
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 422 Unprocessable Entity',
                'error_message' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            // Anything else (database errors above all) must not surface its message to the
            // client: it can expose SQL fragments or schema details. Log it server-side instead.
            error_log('[API] ItemModel::updateItem failed for item ' . $itemId . ': ' . $e->getMessage());
            return [
                'error' => true,
                'error_header' => 'HTTP/1.1 500 Internal Server Error',
                'error_message' => 'An internal error occurred while updating the item.',
            ];
        }
    }

    /**
     * Return a folder title, or an empty string when the folder no longer exists.
     *
     * @param int $folderId Folder identifier
     * @return string
     */
    private function getFolderTitle(int $folderId): string
    {
        $folder = DB::queryFirstRow(
            'SELECT title FROM ' . prefixTable('nested_tree') . ' WHERE id = %i',
            $folderId
        );

        return $folder === null ? '' : (string) $folder['title'];
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
            include_once API_ROOT_PATH . '/../sources/lapr.functions.php';

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

            $laprRelations = laprGetItemRelations([$itemId], $SETTINGS);
            $laprRelation = $laprRelations[$itemId] ?? [];
            if ((bool) ($laprRelation['is_managed'] ?? false) === true
                || (bool) ($laprRelation['is_credential'] ?? false) === true
            ) {
                return [
                    'error' => true,
                    'error_message' => 'This item is linked to LAPR. Remove the managed account or linked endpoint first.',
                    'error_header' => 'HTTP/1.1 409 Conflict',
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

            updateCacheTable('delete_value', $itemId);

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
