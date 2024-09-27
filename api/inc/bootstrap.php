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
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\ConfigManager\ConfigManager;

define("API_ROOT_PATH", __DIR__ . "/..");

// include main configuration file
require API_ROOT_PATH . '/../sources/main.functions.php';

// include the base controller file
require API_ROOT_PATH . "/Controller/Api/BaseController.php";

// include the use model file
require API_ROOT_PATH . "/Model/UserModel.php";
require API_ROOT_PATH . "/Model/ItemModel.php";
require API_ROOT_PATH . "/Model/FolderModel.php";

/**
 * Launch expected action for ITEM
 *
 * @param array $actions
 * @param array $userData
 * @return void
 */
function itemAction(array $actions, array $userData)
{
    // Check if user has rights to perform the action
    if (checkUSerCRUDRights($userData, $actions[0]) === false) {
        errorHdl(
            'HTTP/1.1 404 Not Found',
            json_encode(['error' => 'API requested action is not allowed for this user'])
        );
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
function folderAction(array $actions, array $userData)
{
    // Check if user has rights to perform the action
    if (checkUSerCRUDRights($userData, $actions[0]) === false) {
        errorHdl(
            'HTTP/1.1 404 Not Found',
            json_encode(['error' => 'API requested action is not allowed for this user'])
        );
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
    } elseif (in_array($actionToPerform, ['read', 'get', 'inFolders']) === true && $userData['allowed_to_read'] === 1) {
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
                'error_message' => 'Access denied2',
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