<?php
/**
 *
 * @file          indexapi.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link		  http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

//require_once('config.php');
require_once('functions.php');

header('Content-Type: application/json');

$teampass_api_enabled = teampass_api_enabled();
if (!@in_array("1", $teampass_api_enabled)) {
	echo '{"err":"API access not allowed."}';
	exit;
}

teampass_whitelist();

parse_str($_SERVER['QUERY_STRING']);
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

?>
