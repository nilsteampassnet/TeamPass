<?php
declare(strict_types=1);

namespace TeampassWebSocket;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * Validates WebSocket connection authentication
 *
 * Supports two authentication methods:
 * - Session-based (for web browser clients)
 * - JWT-based (for API clients)
 */
class AuthValidator
{
    /**
     * Path to TeamPass root directory
     */
    private string $rootPath;

    /**
     * JWT secret key (loaded from TeamPass config)
     */
    private ?string $jwtKey = null;

    /**
     * Database table prefix
     */
    private string $tablePrefix;

    /**
     * @param string $rootPath Path to TeamPass installation root
     */
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : 'teampass_';
        $this->loadJwtKey();
    }

    /**
     * Load JWT key from TeamPass configuration
     */
    private function loadJwtKey(): void
    {
        // JWT key is typically stored in the database or a config file
        // For now, we'll try to load it from the misc table
        try {
            $result = \DB::queryFirstRow(
                'SELECT valeur FROM %l WHERE intitule = %s',
                $this->tablePrefix . 'misc',
                'jwt_secret'
            );

            if ($result && !empty($result['valeur'])) {
                $this->jwtKey = $result['valeur'];
            }
        } catch (Exception $e) {
            // JWT key not available, JWT auth will be disabled
        }
    }

    /**
     * Validate authentication from a PHP session ID
     *
     * Note: This requires the session to be accessible from the WebSocket process.
     * The session data is read directly from the session storage.
     *
     * @param string $sessionId PHP session ID from cookie
     * @return array|null User data if valid, null otherwise
     */
    public function validateFromSession(string $sessionId): ?array
    {
        try {
            // Validate session ID format (prevent path traversal)
            if (!preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $sessionId)) {
                return null;
            }

            // Get session save path
            $sessionPath = session_save_path();
            if (empty($sessionPath)) {
                $sessionPath = sys_get_temp_dir();
            }

            $sessionFile = $sessionPath . '/sess_' . $sessionId;

            if (!file_exists($sessionFile)) {
                return null;
            }

            // Read and parse session data
            $sessionData = file_get_contents($sessionFile);
            if ($sessionData === false) {
                return null;
            }

            $userData = $this->parseSessionData($sessionData);

            if ($userData === null) {
                return null;
            }

            // Verify session is not expired
            if (isset($userData['user-session_duration'])) {
                if (time() > (int) $userData['user-session_duration']) {
                    return null;
                }
            }

            // Verify key_tempo matches (session validity check)
            if (isset($userData['user-id']) && isset($userData['key'])) {
                $dbUser = \DB::queryFirstRow(
                    'SELECT key_tempo FROM %l WHERE id = %i',
                    $this->tablePrefix . 'users',
                    (int) $userData['user-id']
                );

                if (!$dbUser || $dbUser['key_tempo'] !== $userData['key']) {
                    return null;
                }
            }

            return $this->formatUserData($userData);

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Parse PHP session data string
     *
     * @param string $sessionData Raw session data
     * @return array|null Parsed session data
     */
    private function parseSessionData(string $sessionData): ?array
    {
        // TeamPass uses encrypted sessions, try to decode
        // First, try standard PHP session format
        $result = [];

        // Handle Symfony session format (serialized)
        if (strpos($sessionData, '_sf2_attributes') !== false) {
            // Symfony session format
            $offset = 0;
            while ($offset < strlen($sessionData)) {
                if (!strstr(substr($sessionData, $offset), '|')) {
                    break;
                }
                $pos = strpos($sessionData, '|', $offset);
                $name = substr($sessionData, $offset, $pos - $offset);
                $offset = $pos + 1;
                $data = @unserialize(substr($sessionData, $offset));
                if ($data !== false) {
                    $result[$name] = $data;
                }
                $offset += strlen(serialize($data));
            }

            // Extract from Symfony structure
            if (isset($result['_sf2_attributes'])) {
                return $result['_sf2_attributes'];
            }
        }

        // Try standard PHP session decode
        $oldSession = $_SESSION ?? [];
        session_decode($sessionData);
        $result = $_SESSION;
        $_SESSION = $oldSession;

        return !empty($result) ? $result : null;
    }

    /**
     * Validate authentication from a WebSocket token
     *
     * This is the primary authentication method for web clients.
     * The token is generated by PHP when the page loads and stored in the database.
     *
     * @param string $token WebSocket authentication token
     * @return array|null User data if valid, null otherwise
     */
    public function validateFromToken(string $token): ?array
    {
        // Token must be 64 hex characters
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        try {
            // Find the token in database with user info
            $tokenData = \DB::queryFirstRow(
                'SELECT wt.*, u.login, u.admin
                 FROM %l wt
                 JOIN %l u ON wt.user_id = u.id
                 WHERE wt.token = %s AND wt.expires_at > NOW() AND u.disabled = 0',
                $this->tablePrefix . 'websocket_tokens',
                $this->tablePrefix . 'users',
                $token
            );

            if (!$tokenData) {
                return null;
            }

            $userId = (int) $tokenData['user_id'];

            // Get user's accessible folders from users_groups table
            $userGroups = \DB::queryFirstColumn(
                'SELECT group_id FROM %l WHERE user_id = %i',
                $this->tablePrefix . 'users_groups',
                $userId
            );
            $accessibleFolders = array_map('intval', $userGroups ?: []);

            // Get user's roles from users_roles table
            $userRoles = \DB::queryFirstColumn(
                'SELECT role_id FROM %l WHERE user_id = %i',
                $this->tablePrefix . 'users_roles',
                $userId
            );

            // Get folders accessible via roles
            if (!empty($userRoles)) {
                $roleFolders = \DB::queryFirstColumn(
                    'SELECT DISTINCT folder_id FROM %l WHERE role_id IN %ls',
                    $this->tablePrefix . 'roles_values',
                    $userRoles
                );
                $accessibleFolders = array_unique(array_merge($accessibleFolders, array_map('intval', $roleFolders ?: [])));
            }

            return [
                'user_id' => $userId,
                'user_login' => $tokenData['login'],
                'accessible_folders' => $accessibleFolders,
                'is_admin' => $tokenData['admin'] === '1',
                'auth_method' => 'ws_token',
                'permissions' => [
                    'read' => true,
                    'create' => true,
                    'update' => true,
                    'delete' => true,
                ],
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate authentication from a JWT token
     *
     * @param string $token JWT token
     * @return array|null User data if valid, null otherwise
     */
    public function validateFromJwt(string $token): ?array
    {
        if ($this->jwtKey === null) {
            return null;
        }

        try {
            $decoded = JWT::decode($token, new Key($this->jwtKey, 'HS256'));

            // Check expiration
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return null;
            }

            // Verify user still exists and is active
            $user = \DB::queryFirstRow(
                'SELECT id, login, admin, disabled FROM %l WHERE id = %i',
                $this->tablePrefix . 'users',
                (int) $decoded->sub
            );

            if (!$user || $user['disabled'] === '1') {
                return null;
            }

            return [
                'user_id' => (int) $decoded->sub,
                'user_login' => $decoded->username ?? $user['login'],
                'accessible_folders' => isset($decoded->allowed_folders)
                    ? array_map('intval', explode(',', $decoded->allowed_folders))
                    : [],
                'is_admin' => $user['admin'] === '1',
                'auth_method' => 'jwt',
                'permissions' => [
                    'read' => $decoded->allowed_to_read ?? false,
                    'create' => $decoded->allowed_to_create ?? false,
                    'update' => $decoded->allowed_to_update ?? false,
                    'delete' => $decoded->allowed_to_delete ?? false,
                ],
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Format user data from session into standard structure
     *
     * @param array $sessionData Raw session data
     * @return array|null Formatted user data
     */
    private function formatUserData(array $sessionData): ?array
    {
        if (!isset($sessionData['user-id'])) {
            return null;
        }

        $accessibleFolders = [];
        if (isset($sessionData['user-accessible_folders'])) {
            $folders = $sessionData['user-accessible_folders'];
            if (is_string($folders)) {
                $accessibleFolders = array_map('intval', explode(';', $folders));
            } elseif (is_array($folders)) {
                $accessibleFolders = array_map('intval', $folders);
            }
        }

        return [
            'user_id' => (int) $sessionData['user-id'],
            'user_login' => $sessionData['user-login'] ?? 'unknown',
            'accessible_folders' => $accessibleFolders,
            'is_admin' => ($sessionData['user-admin'] ?? '0') === '1',
            'auth_method' => 'session',
            'permissions' => [
                'read' => true,
                'create' => true,
                'update' => true,
                'delete' => true,
            ],
        ];
    }

    /**
     * Validate user has access to a specific folder
     *
     * @param array $userData User data from authentication
     * @param int $folderId Folder ID to check
     * @return bool True if user has access
     */
    public function canAccessFolder(array $userData, int $folderId): bool
    {
        // Admins have access to all folders
        if ($userData['is_admin'] ?? false) {
            return true;
        }

        $accessibleFolders = $userData['accessible_folders'] ?? [];
        return in_array($folderId, $accessibleFolders, true);
    }

    /**
     * Check if JWT authentication is available
     */
    public function isJwtAvailable(): bool
    {
        return $this->jwtKey !== null;
    }
}
