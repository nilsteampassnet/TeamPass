<?php
/**
 *
 * @package       (api)index.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.0
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link		   * @package       
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

//require_once('config.php');
require_once('functions.php');

header('Content-Type: application/json');

if (teampass_api_enabled() != "1") {
    echo '{"err":"API access not allowed."}';
    exit;
}

teampass_whitelist();

if (!isset($_GET['apikey'])) {
    rest_error('UNKNOWN');
} else {
    $GLOBALS['apikey'] = $_GET['apikey'];
}

$method = $_SERVER['REQUEST_METHOD'];
$request = explode("/", substr(@$_SERVER['PATH_INFO'], 1));

switch ($method) {
    case 'GET':
    rest_get();
    break;
    case 'PUT':
    rest_put();
    break;
    case 'DELETE':
    rest_delete();
    break;
    case 'HEAD':
    rest_head();
    break;
    default:
    rest_error('UNKNOWN');
    break;
}