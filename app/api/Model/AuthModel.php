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
 * @copyright 2009-2026 Teampass.net
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
        // IMPORTANT: Password should NOT be escaped/sanitized - treat as opaque binary data
        // Only trim whitespace which is safe and expected (fix 3.1.5.10)
        include_once API_ROOT_PATH . '/../sources/main.functions.php';
        $inputData = dataSanitizer(
            [
                'login' => $login,
                'password' => $password,
                'apikey' => $apikey,
            ],
            [
                'login' => 'trim|escape|strip_tags',
                'password' => 'trim', // Only trim, NO escape/sanitization
                'apikey' => 'trim|escape|strip_tags',
            ]
        );

        // Load config early — needed for bruteforce settings and logging
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

        // Check apikey and credentials
        if (empty($inputData['login']) === true || empty($inputData['apikey']) === true || empty($inputData['password']) === true) {
            return ["error" => "Login failed.", "info" => "Missing credentials"];
        }

        $clientIp = $this->getClientIp();

        // Anti-bruteforce: check for active lock before any DB lookup
        $lockUntil = $this->checkBruteforceProtection($inputData['login'], $clientIp);
        if ($lockUntil !== null) {
            logEvents($SETTINGS, 'failed_auth', 'bruteforce_account_locked', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Account temporarily locked"];
        }

        // case where it is a user api key
        // Check if user exists
        $userInfo = getUserCompleteData($inputData['login']);

        if ($userInfo === null || (int) $userInfo['api_enabled'] === 0) {
            // Uniform message — prevents user enumeration
            $this->recordFailedAttempt($inputData['login'], $clientIp, $SETTINGS);
            logEvents($SETTINGS, 'failed_auth', 'api_invalid_credentials', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Invalid credentials"];
        }

        // Check password
        $passwordManager = new PasswordManager();
        if ($passwordManager->verifyPassword($userInfo['pw'], $inputData['password']) === true) {
            // Correct credentials
            // get user keys
            $privateKeyClear = decryptPrivateKey($inputData['password'], (string) $userInfo['private_key']);

            // check API key — timing-safe comparison to prevent timing attacks
            $expectedApiKey = base64_decode(decryptUserObjectKey($userInfo['api_key'], $privateKeyClear));
            if (hash_equals($expectedApiKey, $inputData['apikey']) === false) {
                $this->recordFailedAttempt($inputData['login'], $clientIp, $SETTINGS);
                logEvents($SETTINGS, 'failed_auth', 'api_invalid_apikey', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
                return ["error" => "Login failed.", "info" => "Invalid credentials"];
            }

                // Correct credentials — issue the JWT (shared with the token auth path)
                return $this->issueJwtForUser($userInfo, $privateKeyClear, (string) $inputData['login'], $SETTINGS);
        } else {
            $this->recordFailedAttempt($inputData['login'], $clientIp, $SETTINGS);
            logEvents($SETTINGS, 'failed_auth', 'api_invalid_credentials', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Invalid credentials"];
        }
    }
    //end getUserAuth

    /**
     * Authenticate an API/extension client using a Personal Access Token (PAT).
     *
     * Reserved for OAuth2/SSO users, who have no usable cleartext password (their stored
     * pw is a hash of the non-secret Azure object id). The token unwraps a server-stored
     * copy of the user's private key — re-wrapped at generation time under a key derived
     * from the token (HKDF-SHA256) — then the standard JWT is issued.
     *
     * Gated by the admin setting oauth2_api_enabled. Local and LDAP users are rejected:
     * they keep using the password + API key path, which is left untouched.
     *
     * @param string $login Username
     * @param string $token Extension token (64 hex chars) generated in the web profile
     * @return array{token:string}|array{error:string,info:string}
     */
    public function getUserAuthByToken(string $login, string $token): array
    {
        include_once API_ROOT_PATH . '/../sources/main.functions.php';

        $inputData = dataSanitizer(
            ['login' => $login, 'token' => $token],
            ['login' => 'trim|escape|strip_tags', 'token' => 'trim'] // token is opaque
        );

        // Load config early — needed for the feature gate, bruteforce settings and logging.
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

        // Feature must be explicitly enabled by the administrator (OAuth2 settings page).
        if ((int) ($SETTINGS['oauth2_api_enabled'] ?? 0) !== 1) {
            return ["error" => "Login failed.", "info" => "OAuth2 API access is disabled"];
        }

        if (empty($inputData['login']) === true || empty($inputData['token']) === true) {
            return ["error" => "Login failed.", "info" => "Missing credentials"];
        }

        // Token must be exactly 64 hex chars (bin2hex(random_bytes(32))).
        if (preg_match('/^[a-f0-9]{64}$/', $inputData['token']) !== 1) {
            return ["error" => "Login failed.", "info" => "Invalid credentials"];
        }

        $clientIp = $this->getClientIp();

        // Anti-bruteforce: same table/thresholds as the password path.
        $lockUntil = $this->checkBruteforceProtection($inputData['login'], $clientIp);
        if ($lockUntil !== null) {
            logEvents($SETTINGS, 'failed_auth', 'bruteforce_account_locked', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Account temporarily locked"];
        }

        $userInfo = getUserCompleteData($inputData['login']);

        // User must exist, have API access enabled and be an OAuth2 user.
        // Uniform message — prevents user enumeration and auth_type probing.
        if ($userInfo === null
            || (int) $userInfo['api_enabled'] === 0
            || (string) ($userInfo['auth_type'] ?? '') !== 'oauth2'
        ) {
            $this->recordFailedAttempt($inputData['login'], $clientIp, $SETTINGS);
            logEvents($SETTINGS, 'failed_auth', 'api_invalid_credentials', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Invalid credentials"];
        }

        loadClasses('DB');
        $row = DB::queryFirstRow(
            'SELECT id, user_id, wrapped_private_key, salt, expires_at
             FROM ' . prefixTable('api_tokens') . ' WHERE token_hash = %s',
            hash('sha256', $inputData['token'])
        );

        $tokenValid = (
            $row !== null
            && (int) $row['user_id'] === (int) $userInfo['id']
            && ($row['expires_at'] === null || (int) $row['expires_at'] > time())
        );

        if ($tokenValid === false) {
            $this->recordFailedAttempt($inputData['login'], $clientIp, $SETTINGS);
            logEvents($SETTINGS, 'failed_auth', 'api_invalid_token', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Invalid credentials"];
        }

        // Derive the wrapping key from the token and unwrap the private key.
        require_once API_ROOT_PATH . '/inc/encryption_utils.php';
        $wrappingKey = hash_hkdf('sha256', $inputData['token'], 32, 'teampass-extension-token-v1', (string) hex2bin((string) $row['salt']));
        $privateKeyClear = decrypt_with_session_key((string) $row['wrapped_private_key'], $wrappingKey);

        if ($privateKeyClear === false || $privateKeyClear === '') {
            $this->recordFailedAttempt($inputData['login'], $clientIp, $SETTINGS);
            logEvents($SETTINGS, 'failed_auth', 'api_token_decrypt_failed', '', $inputData['login'], $inputData['login'] . ' | tp_src=api');
            return ["error" => "Login failed.", "info" => "Invalid credentials"];
        }

        DB::update(prefixTable('api_tokens'), ['last_used_at' => time()], 'id = %i', (int) $row['id']);

        return $this->issueJwtForUser($userInfo, $privateKeyClear, (string) $userInfo['login'], $SETTINGS);
    }
    //end getUserAuthByToken

    /**
     * Issue the API JWT for an already-authenticated user.
     *
     * Rotates key_tempo, re-wraps the cleartext private key under a fresh server-side
     * session_aes_key (stored in teampass_api, never in the JWT), refreshes the folders
     * cache, logs the connection and returns the signed JWT. Shared by the password and
     * the Personal Access Token authentication paths.
     *
     * @param array<string,mixed> $userInfo        Row from getUserCompleteData()
     * @param string              $privateKeyClear Cleartext RSA private key
     * @param string              $loginForJwt     Login to embed in the JWT username claim
     * @param array<string,mixed> $SETTINGS        Application settings
     * @return array{token:string}|array{error:string,info:string}
     */
    private function issueJwtForUser(array $userInfo, string $privateKeyClear, string $loginForJwt, array $SETTINGS): array
    {
        // Update user's key_tempo
        $keyTempo = getOrRotateKeyTempo($userInfo['id'], 3600);

        // Generate a unique session key for this API session (256 bits / 32 bytes).
        $sessionKey = random_bytes(32);
        $sessionKeySalt = bin2hex(random_bytes(16));

        // Encrypt the decrypted private key with the session key so it can be stored
        // securely server-side without ever being exposed in the JWT.
        require_once API_ROOT_PATH . '/inc/encryption_utils.php';
        $encryptedPrivateKey = encrypt_with_session_key($privateKeyClear, $sessionKey);

        if ($encryptedPrivateKey === false) {
            return ["error" => "Login failed.", "info" => "Failed to encrypt private key"];
        }

        // Store the ENCRYPTED private key and the AES session key server-side.
        // The session_aes_key never leaves the server (not embedded in JWT),
        // so a stolen JWT alone cannot decrypt the private key.
        DB::update(
            prefixTable('api'),
            [
                'encrypted_private_key' => $encryptedPrivateKey,
                'session_key_salt' => $sessionKeySalt,
                'session_key' => $keyTempo,
                'session_aes_key' => base64_encode($sessionKey),
                'timestamp' => time(),
            ],
            'user_id = %i',
            $userInfo['id']
        );

        // get user folders list and persist in cache_tree
        $ret = $this->buildUserFoldersList($userInfo);
        $this->storeFoldersCache((int) $userInfo['id'], $ret['folders']);

        // Log user (API / browser extension).
        // Prevent duplicate entries when the client retries within a very short window.
        loadClasses('DB');
        // Mark API-originated connections so they can be distinguished in logs UI
        $originForDb = 'api | tp_src=api';

        try {
            $recentCount = (int) DB::queryFirstField(
                'SELECT COUNT(*)
                FROM ' . prefixTable('log_system') . '
                WHERE type = %s AND label = %s AND qui = %i AND field_1 LIKE %ss AND date > %i',
                'user_connection',
                'user_connection',
                (int) $userInfo['id'],
                '%tp_src=api%',
                time() - 2
            );
        } catch (\Throwable $e) {
            // Logging checks must never break API auth
            $recentCount = 0;
        }

        if ($recentCount === 0) {
            logEvents(
                $SETTINGS,
                'user_connection',
                'user_connection',
                (string) $userInfo['id'],
                stripslashes($userInfo['login']),
                $originForDb
            );
        }

        // create JWT (session_aes_key is stored server-side, not in the token)
        return $this->createUserJWT(
            (int) $userInfo['id'],
            $loginForJwt,
            (string) $userInfo['email'],
            (int) $userInfo['personal_folder'],
            '', // folders_list — kept empty, extension uses writableFolders endpoint
            '', // restricted_items_list — kept empty, no longer computed at auth time
            (string) $keyTempo,
            (int) $userInfo['admin'],
            (int) $userInfo['gestionnaire'],
            (int) $userInfo['can_create_root_folder'],
            (int) $userInfo['can_manage_all_users'],
            (string) $userInfo['fonction_id'],
            (string) $userInfo['api_allowed_folders'],
            (int) $userInfo['api_allowed_to_create'],
            (int) $userInfo['api_allowed_to_read'],
            (int) $userInfo['api_allowed_to_update'],
            (int) $userInfo['api_allowed_to_delete'],
            (int) ($SETTINGS['api_token_duration'] ?? 60),
            (int) ($SETTINGS['pwd_maximum_length'] ?? 60),
            (int) ($SETTINGS['maintenance_mode'] ?? 0),
        );
    }
    //end issueJwtForUser

    /**
     * Return the client IP address for bruteforce tracking.
     *
     * @return string
     */
    private function getClientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Check if the login or IP is currently locked by anti-bruteforce.
     * Uses the same teampass_auth_failures table as the web interface.
     *
     * @param string $login
     * @param string $ip
     * @return string|null unlock_at timestamp if locked, null if OK
     */
    private function checkBruteforceProtection(string $login, string $ip): ?string
    {
        $unlockAt = DB::queryFirstField(
            'SELECT MAX(unlock_at)
             FROM ' . prefixTable('auth_failures') . '
             WHERE unlock_at > %s
             AND ((source = %s AND value = %s) OR (source = %s AND value = %s))',
            date('Y-m-d H:i:s', time()),
            'login',
            $login,
            'remote_ip',
            $ip
        );

        return $unlockAt ?: null;
    }

    /**
     * Record a failed authentication attempt in teampass_auth_failures.
     * Mirrors the web interface logic (same table, same thresholds from settings).
     *
     * @param string $login
     * @param string $ip
     * @param array  $SETTINGS
     */
    private function recordFailedAttempt(string $login, string $ip, array $SETTINGS): void
    {
        $userLimit = max(0, (int) ($SETTINGS['nb_bad_authentication'] ?? 10));
        $ipLimit   = max(0, (int) ($SETTINGS['nb_bad_authentication_by_ip'] ?? 30));
        $lockMin   = max(1, (int) ($SETTINGS['bruteforce_lock_duration'] ?? 10));

        if ($userLimit > 0 && !empty($login)) {
            $this->insertAuthFailure('login', $login, $userLimit, $lockMin);
        }
        if ($ipLimit > 0 && !empty($ip) && $ip !== '0.0.0.0') {
            $this->insertAuthFailure('remote_ip', $ip, $ipLimit, $lockMin);
        }

        // Purge stale unlocked entries to keep the table lean
        DB::delete(
            prefixTable('auth_failures'),
            'date < %s AND (unlock_at < %s OR unlock_at IS NULL)',
            date('Y-m-d H:i:s', time() - (24 * 3600)),
            date('Y-m-d H:i:s', time())
        );
    }

    /**
     * Insert one failure row and set a lock when the threshold is reached.
     *
     * @param string $source 'login' or 'remote_ip'
     * @param string $value  The login or IP
     * @param int    $limit  Failure threshold
     * @param int    $lockMin Lock duration in minutes
     */
    private function insertAuthFailure(string $source, string $value, int $limit, int $lockMin): void
    {
        $count = (int) DB::queryFirstField(
            'SELECT COUNT(*) FROM ' . prefixTable('auth_failures') . ' WHERE source = %s AND value = %s',
            $source,
            $value
        );
        $count++;
        $unlockAt = $count >= $limit ? date('Y-m-d H:i:s', time() + ($lockMin * 60)) : null;

        DB::insert(prefixTable('auth_failures'), [
            'source'      => $source,
            'value'       => $value,
            'unlock_at'   => $unlockAt,
            'unlock_code' => null,
        ]);
    }

    /**
     * Create a JWT
     *
     * The private key is encrypted with a per-session AES key (session_aes_key) stored
     * server-side in teampass_api. The JWT carries only key_tempo (a reference used to
     * validate the session and locate the row). The AES key never leaves the server,
     * so a stolen JWT alone cannot be used to decrypt the private key.
     *
     * @param integer $id
     * @param string $login
     * @param string $email
     * @param integer $pf_enabled
     * @param string $folders
     * @param string $items
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
     * @param integer $api_token_duration
     * @param integer $pwd_maximum_length
     * @param integer $maintenance_mode
     * @return array
     */
    private function createUserJWT(
        int $id,
        string $login,
        string $email,
        int $pf_enabled,
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
        int $api_token_duration,
        int $pwd_maximum_length,
        int $maintenance_mode
    ): array
    {
        // Load config
        $configManager = new ConfigManager();
        $SETTINGS = $configManager->getAllSettings();

		$payload = [
            'username' => $login,
            'id' => $id,
            // api_token_duration is in minutes (contract with the browser extension).
            // Clamp to [1, 1440] minutes so a zero/missing value never produces an
            // instantly-expired token, and tokens are capped at 24 hours.
            'exp' => (time() + min(max((int) ($SETTINGS['api_token_duration'] ?? 60), 1), 1440) * 60),
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
            'email' => $email,
            'api_token_duration' => $api_token_duration,
            'pwd_maximum_length' => $pwd_maximum_length,
            'maintenance_mode' => $maintenance_mode,
        ];

        include_once API_ROOT_PATH . '/inc/jwt_utils.php';
        return ['token' => JWT::encode($payload, getApiJwtSigningKey(), 'HS256')];
    }

    //end createUserJWT


    /**
     * Permit to build the list of folders the user can access
     *
     * @param array $userInfo
     * @return array
     */
    public function buildUserFoldersList(array $userInfo): array
    {
        //Build tree
        $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

        // Start by adding the manually added folders
        $allowedFolders = array_map('intval', explode(";", $userInfo['groupes_visibles']));
        $readOnlyFolders = [];
        $allowedFoldersByRoles = [];
        $personalFolders = [];

        $userFunctionId = explode(";", $userInfo['fonction_id']);
        $hasRoles = $userInfo['fonction_id'] !== '';

        // Get folders from the roles
        if ($hasRoles) {
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

        // Add all personal folders
        $rows = DB::queryFirstRow(
            'SELECT id
            FROM ' . prefixTable('nested_tree') . '
            WHERE title = %i AND personal_folder = 1',
            $userInfo['id']
        );
        if (empty($rows['id']) === false) {
            array_push($personalFolders, $rows['id']);
            // get all descendants
            $ids = $tree->getDescendants($rows['id'], false, false, true);
            foreach ($ids as $id) {
                array_push($personalFolders, $id);
            }
        }

        $folderAccessModel = new FolderAccessModel();

        // All accessible folders
        return [
            'folders' => $folderAccessModel->filterFoldersForUser(
                array_values(array_unique(
                    array_filter(
                        array_merge(
                            $allowedFolders,
                            $allowedFoldersByRoles,
                            $readOnlyFolders,
                            $personalFolders
                        )
                    )
                )),
                (int) $userInfo['id']
            ),
        ];
    }
    //end buildUserFoldersList

    /**
     * Store the accessible folders list in cache_tree for API use.
     * Called at authentication time so the list is ready for subsequent API requests.
     *
     * @param int   $userId
     * @param array $folders Array of integer folder IDs
     * @return void
     */
    private function storeFoldersCache(int $userId, array $folders): void
    {
        $foldersJson = json_encode($folders);

        $existing = DB::queryFirstRow(
            'SELECT increment_id FROM ' . prefixTable('cache_tree') . ' WHERE user_id = %i',
            $userId
        );

        if ($existing === null) {
            DB::insert(prefixTable('cache_tree'), [
                'user_id'         => $userId,
                'data'            => '[]',
                'visible_folders' => '[]',
                'folders'         => $foldersJson,
                'timestamp'       => time(),
                'invalidated_at'  => 0,
            ]);
        } else {
            DB::update(
                prefixTable('cache_tree'),
                ['folders' => $foldersJson, 'timestamp' => time(), 'invalidated_at' => 0],
                'increment_id = %i',
                (int) $existing['increment_id']
            );
        }
    }
    //end storeFoldersCache
}
