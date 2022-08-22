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

define("API_ROOT_PATH", __DIR__ . "/..");

// include main configuration file
require API_ROOT_PATH . '/../includes/config/settings.php';
require API_ROOT_PATH . '/../includes/config/tp.config.php';
require API_ROOT_PATH . '/../sources/main.functions.php';

// Load superglobal
require API_ROOT_PATH. '/../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// include the base controller file
require API_ROOT_PATH . "/Controller/Api/BaseController.php";

// include the use model file
require API_ROOT_PATH . "/Model/UserModel.php";
require API_ROOT_PATH . "/Model/ItemModel.php";


/**
 * Launch expected action for ITEM
 *
 * @param array $actions
 * @param array $userData
 * @return void
 */
function itemAction(array $actions, array $userData)
{
    require API_ROOT_PATH . "/Controller/Api/ItemController.php";
    
    $objFeedController = new ItemController();
    $strMethodName = $actions[0] . 'Action';
    $objFeedController->{$strMethodName}($userData);
}


/**
 * Check if API usage is allowed in Teampass settings
 *
 * @return string
 */
function apiIsEnabled(): string
{
    require API_ROOT_PATH . '/../includes/config/tp.config.php';

    if ((int) $SETTINGS['api'] === 1) {
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
                'error_header' => 'HTTP/1.1 404 Not Found',
            ]
        );
    }
}


/**
 * Check if connection is authorized
 *
 * @return string
 */
function verifyAuth(): string
{
    include_once API_ROOT_PATH . '/inc/jwt_utils.php';
    $bearer_token = get_bearer_token();

    if (empty($bearer_token) === false && is_jwt_valid($bearer_token) === true) {
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
                'error_message' => 'Access denied',
                'error_header' => 'HTTP/1.1 404 Not Found',
            ]
        );
    }
}


/**
 * Get the payload from bearer
 *
 * @return string
 */
function getDataFromToken(): string
{
    include_once API_ROOT_PATH . '/inc/jwt_utils.php';
    $bearer_token = get_bearer_token();

    if (empty($bearer_token) === false) {
        return json_encode(
            [
                'data' => get_bearer_data($bearer_token),
                'error' => false,
                'error_message' => '',
                'error_header' => '',
            ]
        );
    } else {
        return json_encode(
            [
                'error' => true,
                'error_message' => 'Access denied',
                'error_header' => 'HTTP/1.1 404 Not Found',
            ]
        );
    }
}


/**
 * Send error output
 *
 * @param string $errorHeader
 * @param string $errorValues
 * @return void
 */
function errorHdl(string $errorHeader, string $errorValues)
{
    header_remove('Set-Cookie');

    header($errorHeader);

    echo $errorValues;
}