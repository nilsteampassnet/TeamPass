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
 * @file      bootstrap.php
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
define("PROJECT_ROOT_PATH", __DIR__ . "/..");
// include main configuration file
require __DIR__ . '/../../includes/config/settings.php';
require __DIR__ . '/../../includes/config/tp.config.php';
require __DIR__ . '/../../sources/main.functions.php';

// include the base controller file
require PROJECT_ROOT_PATH . "/Controller/Api/BaseController.php";

// include the use model file
require PROJECT_ROOT_PATH . "/Model/UserModel.php";
require PROJECT_ROOT_PATH . "/Model/ItemModel.php";


function itemAction($actions, $userData)
{
    require PROJECT_ROOT_PATH . "/Controller/Api/ItemController.php";
    
    $objFeedController = new ItemController();
    $strMethodName = $actions[0] . 'Action';
    $objFeedController->{$strMethodName}($userData);
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

function getDataFromToken()
{
    include_once PROJECT_ROOT_PATH . '/inc/jwt_utils.php';
    $bearer_token = get_bearer_token();

    if (empty($bearer_token) === false) {
        return get_bearer_data($bearer_token);
    } else {
        header("HTTP/1.1 404 Not Found");
        echo json_encode(array('error' => 'Access denied'));
        exit();
    }
}