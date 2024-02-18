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
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

header("Access-Control-Allow-Origin: ".$_SERVER['HTTP_HOST']);
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require __DIR__ . "/inc/bootstrap.php";

// sanitize url segments
$base = new BaseController();
$uri = $base->getUriSegments();
if (is_array($uri) === false || is_string($uri) === true) {
    $uri = [$uri];  // ensure $uril is table
}

// Prepare DB password
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', cryption(DB_PASSWD, '', 'decrypt', $SETTINGS)['string']);
}

// Do initial checks
$apiStatus = json_decode(apiIsEnabled(), true);
$jwtStatus = json_decode(verifyAuth(), true);

file_put_contents(__DIR__ . '/api.log', date("Y-m-d H:i:s")." - ".print_r($jwtStatus, true), FILE_APPEND | LOCK_EX);
// Authorization handler
if ($uri[0] === 'authorize') {
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

    // define the position of controller in $uri
    $controller = $uri[0];
    $action = $uri[1];
    error_log("API - controller: ".$controller." | action: ".$action." || ".print_r($userData, true));
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