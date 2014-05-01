<?php

require_once('config.php');
require_once('functions.php');

header('Content-Type: application/json');

$teampass_api_enabled = teampass_api_enabled();
if (!in_array("1", $teampass_api_enabled)) {
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
