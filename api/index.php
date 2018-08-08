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
require_once 'functions.php';

header('Content-Type: application/json');

if (teampassApiEnabled() != "1") {
    echo '{"err":"API access not allowed."}';
    exit;
}

teampassWhitelist();

if (isset($_GET['apikey']) === false) {
    restError('UNKNOWN');
} else {
    $GLOBALS['apikey'] = $_GET['apikey'];
}

$method = $_SERVER['REQUEST_METHOD'];
$request = explode("/", substr(@$_SERVER['PATH_INFO'], 1));

switch ($method) {
    case 'POST':
        restGet();
        break;
    case 'GET':
        restGet();
        break;
    case 'PUT':
        restPut();
        break;
    case 'DELETE':
        restDelete();
        break;
    case 'HEAD':
        restHead();
        break;
    default:
        restError('UNKNOWN');
        break;
}
