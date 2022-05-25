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

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode( '/', $uri );


// Authorization handler
if ($uri[4] === 'authorize') {
    if (apiIsEnabled() === true) {
        require PROJECT_ROOT_PATH . "/Controller/Api/AuthController.php";
        $objFeedController = new AuthController();
        $strMethodName = $uri[4] . 'Action';
        $objFeedController->{$strMethodName}();
    }
} elseif ($uri[4] === 'user' && verifyAuth() === true) {
    require PROJECT_ROOT_PATH . "/Controller/Api/UserController.php";
    $objFeedController = new UserController();
    $strMethodName = $uri[5] . 'Action';
    $objFeedController->{$strMethodName}();
} elseif ($uri[4] === 'item' && verifyAuth() === true) {
    require PROJECT_ROOT_PATH . "/Controller/Api/ItemController.php";

    $objFeedController = new ItemController();
    $strMethodName = $uri[5] . 'Action';
    $objFeedController->{$strMethodName}();
} else {
    header("HTTP/1.1 404 Not Found");
    exit();
}


function apiIsEnabled()
{
    require '../includes/config/tp.config.php';

    if ((int) $SETTINGS['api'] === 1) {
        return true;
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(array('error' => 'API usage is not allowed'));
        exit();
    }
}

function verifyAuth()
{
    include_once PROJECT_ROOT_PATH . '/inc/jwt_utils.php';
    $bearer_token = get_bearer_token();

    if (empty($bearer_token) === false && is_jwt_valid($bearer_token) === true) {
        return true;
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(array('error' => 'Access denied'));
        exit();
    }
}