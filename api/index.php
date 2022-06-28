<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass API
 *
 * @file      index.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
require __DIR__ . "/inc/bootstrap.php";

// sanitize url segments
$base = new BaseController();
$uri = $base->getUriSegments();

// Prepare DB password
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', cryption(DB_PASSWD, '', 'decrypt', $SETTINGS)['string']);
}

// Do initial checks
$apiStatus = json_decode(apiIsEnabled(), true);
$jwtStatus = json_decode(verifyAuth(), true);

// Authorization handler
if ($uri[4] === 'authorize') {
    // Is API enabled in Teampass settings
    if ($apiStatus['error'] === false) {
        require PROJECT_ROOT_PATH . "/Controller/Api/AuthController.php";
        $objFeedController = new AuthController();
        $strMethodName = $uri[4] . 'Action';
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

    if ($userData['error'] === true) {
        // Error management
        errorHdl(
            $userData['error_header'],
            json_encode(['error' => $userData['error_message']])
        );

    // action related to USER
    } elseif ($uri[4] === 'user') {
        require PROJECT_ROOT_PATH . "/Controller/Api/UserController.php";
        $objFeedController = new UserController();
        $strMethodName = $uri[5] . 'Action';
        $objFeedController->{$strMethodName}();

    // action related to ITEM
    } elseif ($uri[4] === 'item') {        
        // Manage requested action
        itemAction(
            array_slice($uri, 5),
            $userData['data']
        ); 

    // action related to FOLDER
    } elseif ($uri[4] === 'folder') {


    // no action where find
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
