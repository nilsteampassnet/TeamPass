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
 * @file      bootstrap.php
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

use TeampassClasses\ConfigManager\ConfigManager;

define('API_ROOT_PATH', __DIR__ . '/..');

// Application root paths — API entry point is public/api/index.php; bootstrap is in app/api/inc/
if (!defined('TEAMPASS_ROOT')) {
    define('TEAMPASS_ROOT', dirname(__DIR__, 3)); // app/api/inc/ → app/api/ → app/ → TeamPass/
}
if (!defined('TEAMPASS_APP')) {
    define('TEAMPASS_APP', TEAMPASS_ROOT . '/app');
}
if (!defined('TEAMPASS_STORAGE')) {
    define('TEAMPASS_STORAGE', TEAMPASS_ROOT . '/storage');
}

// include main configuration file
require TEAMPASS_APP . '/sources/main.functions.php';

// include the base controller file
require API_ROOT_PATH . "/Controller/Api/BaseController.php";

// include the use model file
require API_ROOT_PATH . "/Model/UserModel.php";
require API_ROOT_PATH . "/Model/ItemModel.php";
require API_ROOT_PATH . "/Model/FolderModel.php";
require API_ROOT_PATH . "/Model/FolderAccessModel.php";

/**
 * Launch expected action for ITEM
 *
 * @param array $actions
 * @param array $userData
 * @return void
 */
function itemAction(array $actions, array $userData): void
{
    // Check if user has rights to perform the action
    if (checkUSerCRUDRights($userData, $actions[0]) === false) {
        errorHdl(
            'HTTP/1.1 403 Forbidden',
            json_encode(['error' => 'Access denied: insufficient permissions for this action'])
        );
        return;
    }
    // Perform the action
    require API_ROOT_PATH . "/Controller/Api/ItemController.php";
    $objFeedController = new ItemController();
    $strMethodName = $actions[0] . 'Action';
    $objFeedController->{$strMethodName}($userData);
}

/**
 * Launch expected action for FOLDER
 *
 * @param array $actions
 * @param array $userData
 * @return void
 */
function folderAction(array $actions, array $userData): void
{
    // Check if user has rights to perform the action
    if (checkUSerCRUDRights($userData, $actions[0]) === false) {
        errorHdl(
            'HTTP/1.1 403 Forbidden',
            json_encode(['error' => 'Access denied: insufficient permissions for this action'])
        );
        return;
    }
    // Perform the action
    require API_ROOT_PATH . "/Controller/Api/FolderController.php";
    $objFeedController = new FolderController();
    $strMethodName = $actions[0] . 'Action';
    $objFeedController->{$strMethodName}($userData);
}

function checkUSerCRUDRights($userData, $actionToPerform): bool
{
    if ($actionToPerform === 'create' && $userData['allowed_to_create'] === 1) {
        return true;
    } elseif (in_array($actionToPerform, ['read', 'get', 'inFolders', 'findByUrl', 'getOtp', 'allTags', 'listFolders', 'writableFolders']) === true && $userData['allowed_to_read'] === 1) {
        return true;
    } elseif ($actionToPerform === 'update' && $userData['allowed_to_update'] === 1) {
        return true;
    } elseif ($actionToPerform === 'delete' && $userData['allowed_to_delete'] === 1) {
        return true;
    } else {
        return false;
    }
}

/**
 * Check if API usage is allowed in Teampass settings
 *
 * @return string
 */
function apiIsEnabled(): string
{
    // Load config
    $configManager = new ConfigManager();
    $SETTINGS = $configManager->getAllSettings();

    if (isset($SETTINGS) === true && isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) {
        return json_encode(
            [
                'error' => false,
                'error_message' => '',
                'error_header' => '',
            ]
        );
    } else {
        return json_encode(
            [
                'error' => true,
                'error_message' => 'API usage is not allowed',
                'error_header' => 'HTTP/1.1 503 Service Unavailable',
            ]
        );
    }
}


/**
 * Check if connection is authorized.
 *
 * Single verified decode: on success the signature-checked JWT payload is
 * returned in 'data' — callers must use it instead of re-parsing the token.
 *
 * @return string
 */
function verifyAuth(): string
{
    include_once API_ROOT_PATH . '/inc/jwt_utils.php';
    $bearer_token = get_bearer_token();

    if (empty($bearer_token) === true) {
        return json_encode([
            'error' => true,
            'error_message' => 'Missing Authorization header',
            'error_header' => 'HTTP/1.1 401 Unauthorized',
        ]);
    }

    $payload = validate_and_get_jwt_payload($bearer_token);
    if ($payload === null) {
        return json_encode([
            'error' => true,
            'error_message' => 'Invalid or expired token',
            'error_header' => 'HTTP/1.1 401 Unauthorized',
        ]);
    }

    return json_encode([
        'data' => $payload,
        'error' => false,
        'error_message' => '',
        'error_header' => '',
    ]);
}


/**
 * Sliding-window rate limit check (per user id AND per client IP).
 *
 * Uses the api_rate_limit table with the classic two-bucket sliding-window
 * counter: rate = previous-minute hits weighted by the remaining overlap +
 * current-minute hits. Counters are upserted atomically; buckets older than
 * 3 minutes are dropped opportunistically.
 *
 * @param int    $userId         Authenticated user id (0 to skip the user scope)
 * @param string $clientIp       Trusted client IP ('' to skip the IP scope)
 * @param int    $limitPerMinute Allowed requests per minute (<= 0 disables the check)
 * @return array{allowed: bool, retry_after: int}
 */
function teampassApiRateLimitCheck(int $userId, string $clientIp, int $limitPerMinute): array
{
    if ($limitPerMinute <= 0) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    $now = time();
    $windowStart = $now - ($now % 60);
    $elapsed = $now - $windowStart;

    $scopes = [];
    if ($userId > 0) {
        $scopes[] = 'u:' . $userId;
    }
    if ($clientIp !== '') {
        $scopes[] = 'ip:' . $clientIp;
    }

    $allowed = true;
    foreach ($scopes as $scopeKey) {
        DB::query(
            'INSERT INTO ' . prefixTable('api_rate_limit') . ' (scope_key, window_start, hits)
            VALUES (%s, %i, 1)
            ON DUPLICATE KEY UPDATE hits = hits + 1',
            $scopeKey,
            $windowStart
        );

        $buckets = DB::query(
            'SELECT window_start, hits FROM ' . prefixTable('api_rate_limit') . '
            WHERE scope_key = %s AND window_start >= %i',
            $scopeKey,
            $windowStart - 60
        );

        $currentHits = $previousHits = 0;
        foreach ($buckets as $bucket) {
            if ((int) $bucket['window_start'] === $windowStart) {
                $currentHits = (int) $bucket['hits'];
            } else {
                $previousHits = (int) $bucket['hits'];
            }
        }

        $effectiveRate = $previousHits * ((60 - $elapsed) / 60) + $currentHits;
        if ($effectiveRate > $limitPerMinute) {
            $allowed = false;
        }
    }

    // Opportunistic cleanup of stale buckets (~2% of requests)
    if (random_int(1, 50) === 1) {
        DB::delete(prefixTable('api_rate_limit'), 'window_start < %i', $windowStart - 180);
    }

    return [
        'allowed' => $allowed,
        'retry_after' => $allowed ? 0 : (60 - $elapsed),
    ];
}

/**
 * Send error output as RFC 9457 application/problem+json.
 *
 * Accepts the legacy (status line, json body) pair used across the router and
 * rebuilds a problem document; the legacy 'error' member is kept alongside
 * 'title'/'detail' for backward compatibility.
 *
 * @param string $errorHeader Legacy status line, e.g. 'HTTP/1.1 403 Forbidden'
 * @param string $errorValues Legacy JSON body, e.g. '{"error":"..."}'
 * @return void
 */
function errorHdl(string $errorHeader, string $errorValues)
{
    header_remove('Set-Cookie');

    $status = preg_match('/\b([1-5]\d{2})\b/', $errorHeader, $matches) === 1
        ? (int) $matches[1]
        : 500;
    $title = BaseController::HTTP_REASONS[$status] ?? 'Error';

    $decoded = json_decode($errorValues, true);
    $detail = is_array($decoded) && isset($decoded['error'])
        ? (string) $decoded['error']
        : $errorValues;

    header('HTTP/1.1 ' . $status . ' ' . $title);
    header('Content-Type: application/problem+json; charset=UTF-8');

    echo json_encode([
        'type' => 'about:blank',
        'title' => $title,
        'status' => $status,
        'detail' => $detail,
        'error' => $detail,
    ]);
}
