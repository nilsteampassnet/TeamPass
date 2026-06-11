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
 * @file      index.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2026 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

require __DIR__ . "/inc/bootstrap.php";

if (!isset($SETTINGS) || is_array($SETTINGS) === false) {
    $configManager = new \TeampassClasses\ConfigManager\ConfigManager();
    $SETTINGS = $configManager->getAllSettings();
}

// CORS — behaviour depends on api_cors_origins setting:
//   empty (default) → allow all origins (Access-Control-Allow-Origin: *)
//   populated       → restrict to the listed origins only
$requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$corsOriginsRaw = trim((string) ($SETTINGS['api_cors_origins'] ?? ''));

if ($corsOriginsRaw === '') {
    // No restriction configured — open to all origins (JWT is the auth layer)
    header('Access-Control-Allow-Origin: *');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $sameHostOrigin = $protocol . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $allowedOrigins = [$sameHostOrigin];
    foreach (explode(',', $corsOriginsRaw) as $corsOrigin) {
        $corsOrigin = trim($corsOrigin);
        if ($corsOrigin !== '') {
            $allowedOrigins[] = $corsOrigin;
        }
    }

    if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $requestOrigin);
        header('Vary: Origin');
    } elseif ($requestOrigin === '') {
        header('Access-Control-Allow-Origin: ' . $sameHostOrigin);
    }
    // Origin not in whitelist → no header → browser blocks the request
}

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Expose-Headers: X-Api-Version, X-Total-Count, Location, Allow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Api-Version: 1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

// Handle CORS preflight — browsers send OPTIONS before PUT/DELETE cross-origin requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiLanguage = 'english';
$acceptLanguage = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
if (preg_match('/(^|,)\s*fr([-_][a-z]{2})?(\s*;|,|$)/i', $acceptLanguage) === 1) {
    $apiLanguage = 'french';
}
$lang = new \TeampassClasses\Language\Language($apiLanguage);

// Enforce HTTPS when api_require_https is enabled (default on new installations).
// Credentials (/authorize*) and bearer tokens must never travel unencrypted.
// X-Forwarded-Proto is honoured for TLS-terminating reverse proxies.
if ((int) ($SETTINGS['api_require_https'] ?? 0) === 1) {
    $isRequestSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    if ($isRequestSecure === false) {
        errorHdl(
            'HTTP/1.1 403 Forbidden',
            json_encode(['error' => 'HTTPS is required for API requests (api_require_https is enabled)'])
        );
        exit;
    }
}

$networkAccessContext = teampassGetClientIpForSecurity($SETTINGS);
$networkAccessRules = teampassLoadNetworkAclRules(true);
$networkAccess = teampassEvaluateNetworkAclAccess(
    $SETTINGS,
    isset($networkAccessContext['detected_ip']) === true ? (string) $networkAccessContext['detected_ip'] : null,
    $networkAccessRules
);
if (($networkAccess['checked'] ?? false) === true && ($networkAccess['allowed'] ?? true) === false) {
    errorHdl(
        'HTTP/1.1 403 Forbidden',
        json_encode(
            ['error' => $lang->get('network_security_access_denied_message')],
            JSON_UNESCAPED_UNICODE
        )
    );
    exit;
}

// sanitize url segments
$base = new BaseController();
$uri = $base->getUriSegments();
if (!is_array($uri)) {
    $uri = [$uri];  // ensure $uril is table
}

// Prepare DB password
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', cryption(DB_PASSWD, '', 'decrypt', $SETTINGS)['string']);
}

// Do initial checks
$apiStatus = json_decode(apiIsEnabled(), true);
$jwtStatus = json_decode(verifyAuth(), true);

// Machine-readable API contract (OpenAPI 3.1) — no JWT required, gated by the api setting
if (isset($uri[0]) && $uri[0] === 'openapi.json') {
    if ($apiStatus['error'] === false) {
        header('Content-Type: application/json; charset=UTF-8');
        readfile(API_ROOT_PATH . '/openapi.json');
    } else {
        errorHdl(
            $apiStatus['error_header'],
            json_encode(['error' => $apiStatus['error_message']])
        );
    }
    exit;
}

// Authorization handler
if (isset($uri[0]) && ($uri[0] === 'authorize' || $uri[0] === 'authorizeToken')) {
    // Is API enabled in Teampass settings
    if ($apiStatus['error'] === false) {
        require API_ROOT_PATH . "/Controller/Api/AuthController.php";
        $objFeedController = new AuthController();
        $strMethodName = $uri[0] . 'Action';   // authorizeAction | authorizeTokenAction
        $objFeedController->{$strMethodName}();
    } else {
        // Error management
        errorHdl(
            $apiStatus['error_header'],
            json_encode(['error' => $apiStatus['error_message']])
        );
    }
} elseif ($jwtStatus['error'] === false) {
    // Payload comes from the signature-verified token (single decode in verifyAuth)
    $userData = ['data' => $jwtStatus['data'], 'error' => false];

    // Re-validate user status and CRUD rights on every request — JWT claims are a
    // snapshot at issuance: a user disabled or whose API rights were revoked must
    // lose access immediately, not at token expiry.
    $jwtUserId = (int) ($userData['data']['id'] ?? 0);
    $freshUser = $jwtUserId > 0 ? DB::queryFirstRow(
        'SELECT u.disabled, u.admin, u.gestionnaire,
            a.enabled AS api_enabled,
            a.allowed_to_create, a.allowed_to_read, a.allowed_to_update, a.allowed_to_delete
        FROM ' . prefixTable('users') . ' AS u
        LEFT JOIN ' . prefixTable('api') . ' AS a ON (a.user_id = u.id)
        WHERE u.id = %i AND u.deleted_at IS NULL',
        $jwtUserId
    ) : null;

    if ($freshUser === null
        || (int) $freshUser['disabled'] === 1
        || (int) $freshUser['api_enabled'] !== 1
    ) {
        errorHdl(
            'HTTP/1.1 401 Unauthorized',
            json_encode(['error' => 'Invalid or expired token'])
        );
        exit;
    }

    // Override the stale JWT claims with the fresh values
    $userData['data']['is_admin'] = (int) $freshUser['admin'];
    $userData['data']['is_manager'] = (int) $freshUser['gestionnaire'];
    $userData['data']['allowed_to_create'] = (int) $freshUser['allowed_to_create'];
    $userData['data']['allowed_to_read'] = (int) $freshUser['allowed_to_read'];
    $userData['data']['allowed_to_update'] = (int) $freshUser['allowed_to_update'];
    $userData['data']['allowed_to_delete'] = (int) $freshUser['allowed_to_delete'];

    // Rate limiting (sliding window, per user AND per IP) — applied to all
    // authenticated endpoints. 0 (default on upgraded instances) disables it.
    $rateLimitPerMinute = (int) ($SETTINGS['api_rate_limit_per_minute'] ?? 0);
    if ($rateLimitPerMinute > 0) {
        $rateLimitState = teampassApiRateLimitCheck(
            $jwtUserId,
            (string) ($networkAccessContext['detected_ip'] ?? ''),
            $rateLimitPerMinute
        );
        if ($rateLimitState['allowed'] === false) {
            header('Retry-After: ' . $rateLimitState['retry_after']);
            errorHdl(
                'HTTP/1.1 429 Too Many Requests',
                json_encode(['error' => 'Rate limit exceeded — retry in ' . $rateLimitState['retry_after'] . ' seconds'])
            );
            exit;
        }
    }

    // Per-token revocation: a token logged out via /auth/logout (or revoked from
    // the profile) is rejected on EVERY endpoint, not only key-needing ones.
    // Tokens issued before the api_sessions table existed have no row — they are
    // tolerated until expiry (legacy fallback, max 24h).
    $jwtJti = (string) ($userData['data']['jti'] ?? '');
    if ($jwtJti !== '') {
        $apiSession = DB::queryFirstRow(
            'SELECT revoked_at, expires_at FROM ' . prefixTable('api_sessions') . ' WHERE jti = %s AND user_id = %i',
            $jwtJti,
            $jwtUserId
        );
        if ($apiSession !== null
            && ($apiSession['revoked_at'] !== null || (int) $apiSession['expires_at'] < time())
        ) {
            errorHdl(
                'HTTP/1.1 401 Unauthorized',
                json_encode(['error' => 'Invalid or expired token'])
            );
            exit;
        }
    }

    // Populate folders_list from cache_tree (was removed from JWT to reduce token size).
    // On cache miss or invalidation, rebuild from DB and refresh the cache.
    if (empty($userData['data']['folders_list'])) {
        $userId = (int) ($userData['data']['id'] ?? 0);
        if ($userId > 0) {
            $cacheRow = DB::queryFirstRow(
                'SELECT folders, IFNULL(invalidated_at, 0) AS invalidated_at, timestamp
                FROM ' . prefixTable('cache_tree') . '
                WHERE user_id = %i',
                $userId
            );

            $cacheValid = (
                $cacheRow !== null
                && !empty($cacheRow['folders'])
                && $cacheRow['folders'] !== '[]'
                && (int) $cacheRow['invalidated_at'] <= (int) $cacheRow['timestamp']
            );

            if ($cacheValid) {
                $folderIds = json_decode($cacheRow['folders'], true);
                if (is_array($folderIds) && count($folderIds) > 0) {
                    $userData['data']['folders_list'] = implode(',', array_map('intval', $folderIds));
                }
            } else {
                // Rebuild: recompute from DB and refresh cache_tree.folders
                // groupes_visibles was migrated from teampass_users to teampass_users_groups in v3.1.5
                $userRow = DB::queryFirstRow(
                    'SELECT u.id,
                    GROUP_CONCAT(DISTINCT ug.group_id ORDER BY ug.group_id SEPARATOR ";") AS groupes_visibles,
                    GROUP_CONCAT(DISTINCT CASE WHEN ur.source = "manual" THEN ur.role_id END ORDER BY ur.role_id SEPARATOR ";") AS fonction_id
                    FROM ' . prefixTable('users') . ' AS u
                    LEFT JOIN ' . prefixTable('users_groups') . ' AS ug ON (u.id = ug.user_id)
                    LEFT JOIN ' . prefixTable('users_roles') . ' AS ur ON (u.id = ur.user_id)
                    WHERE u.id = %i
                    GROUP BY u.id',
                    $userId
                );
                if ($userRow !== null) {
                    require_once API_ROOT_PATH . "/Model/AuthModel.php";
                    $authModel = new AuthModel();
                    $ret = $authModel->buildUserFoldersList($userRow);
                    $foldersJson = json_encode($ret['folders']);

                    if ($cacheRow === null) {
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
                            'user_id = %i',
                            $userId
                        );
                    }

                    if (!empty($ret['folders'])) {
                        $userData['data']['folders_list'] = implode(',', array_map('intval', $ret['folders']));
                    }
                }
            }
        }
    }

    $userId = (int) ($userData['data']['id'] ?? 0);
    if ($userId > 0) {
        $folderAccessModel = new FolderAccessModel();
        $currentFolders = $folderAccessModel->normalizeFolderIds($userData['data']['folders_list'] ?? '');
        $filteredFolders = $folderAccessModel->filterFoldersForUser($currentFolders, $userId);

        if ($filteredFolders !== $currentFolders) {
            DB::update(
                prefixTable('cache_tree'),
                ['folders' => json_encode($filteredFolders), 'timestamp' => time(), 'invalidated_at' => 0],
                'user_id = %i',
                $userId
            );
        }

        $userData['data']['folders_list'] = implode(',', $filteredFolders);
    }



    // define the position of controller in $uri
    $controller = $uri[0] ?? '';
    $action = $uri[1] ?? '';

    if ($controller === '' || $action === '') {
        errorHdl(
            "HTTP/1.1 404 Not Found",
            json_encode(['error' => 'Unknown route'])
        );

    // action related to USER
    } elseif ($controller === 'user') {
        require API_ROOT_PATH . "/Controller/Api/UserController.php";
        $objFeedController = new UserController();
        $strMethodName = (string) $action . 'Action';
        $objFeedController->{$strMethodName}($userData['data']);

    // action related to ITEM
    } elseif ($controller === 'item') {
        // Manage requested action
        itemAction(
            array_slice($uri, 1),
            $userData['data']
        ); 

    // action related to FOLDER
    } elseif ($controller === 'folder') {
        // Manage requested action
        folderAction(
            array_slice($uri, 1),
            $userData['data']
        );

    // action related to MISC
    } elseif ($controller === 'misc') {
        require API_ROOT_PATH . "/Controller/Api/MiscController.php";
        $objFeedController = new MiscController();
        $strMethodName = (string) $action . 'Action';
        $objFeedController->{$strMethodName}();

    // action related to AUTH session lifecycle — only the logout action is routed
    // here (token issuance stays on the unauthenticated /authorize* routes above)
    } elseif ($controller === 'auth' && $action === 'logout') {
        require API_ROOT_PATH . "/Controller/Api/AuthController.php";
        $objFeedController = new AuthController();
        $objFeedController->logoutAction($userData['data']);
    } else {
        errorHdl(
            "HTTP/1.1 404 Not Found",
            json_encode(['error' => 'Unknown route'])
        );
    }
// manage error case
} else {
    if ($jwtStatus['error'] === true) {
        errorHdl(
            $jwtStatus['error_header'],
            json_encode(['error' => $jwtStatus['error_message']])
        );
    } else {
        errorHdl(
            "HTTP/1.1 404 Not Found",
            json_encode(['error' => 'Access denied'])
        );
    }
}
