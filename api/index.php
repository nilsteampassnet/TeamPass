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

// Determine the protocol used
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

// Validate and filter the host
$host = filter_var($_SERVER['HTTP_HOST'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);

// Allocate the correct CORS header
if ($host !== false) {
    header("Access-Control-Allow-Origin: $protocol$host");
} else {
    header("Access-Control-Allow-Origin: 'null'");
}
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require __DIR__ . "/inc/bootstrap.php";

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

// Authorization handler
if (isset($uri[0]) && $uri[0] === 'authorize') {
    // Is API enabled in Teampass settings
    if ($apiStatus['error'] === false) {
        require API_ROOT_PATH . "/Controller/Api/AuthController.php";
        $objFeedController = new AuthController();
        $strMethodName = $uri[0] . 'Action';
        $objFeedController->{$strMethodName}();
    } else {
        // Error management
        errorHdl(
            $apiStatus['error_header'],
            json_encode(['error' => $apiStatus['error_message']])
        );
    }
} elseif ($jwtStatus['error'] === false) {
    // get infos from JWT parameters
    $userData = json_decode(getDataFromToken(), true);

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
                $userRow = DB::queryFirstRow(
                    'SELECT id, groupes_visibles, fonction_id
                    FROM ' . prefixTable('users') . '
                    WHERE id = %i',
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



    // define the position of controller in $uri
    $controller = $uri[0];
    $action = $uri[1];
    
    if ($userData['error'] === true) {
        // Error management
        errorHdl(
            $userData['error_header'],
            json_encode(['error' => $userData['error_message']])
        );

    // action related to USER
    } elseif ($controller === 'user') {
        require API_ROOT_PATH . "/Controller/Api/UserController.php";
        $objFeedController = new UserController();
        $strMethodName = (string) $action . 'Action';
        $objFeedController->{$strMethodName}();

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
    } else {
        errorHdl(
            "HTTP/1.1 404 Not Found",
            json_encode(['error' => 'No action provided'])
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