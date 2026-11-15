<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * This file is part of TeamPass, distributed under the GNU GPL version 3 or later.
 * Copyright 2009-2026 Teampass.net. See <https://www.gnu.org/licenses/>.
 */

define('TEAMPASS_FUNCTIONS_ONLY', true);
define('DB_PREFIX', 'acl_fixture_');
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/network_acl_db.php';
require_once __DIR__ . '/../../app/sources/main.functions.php';

$input = json_decode((string) ($argv[1] ?? ''), true, 32, JSON_THROW_ON_ERROR);
$routes = [
    'login' => '/public/index.php',
    'identify' => '/public/sources/identify.php',
    'items' => '/public/sources/items.queries.php',
    'admin' => '/public/sources/admin.queries.php',
    'download' => '/public/sources/downloadFile.php',
    'api' => '/public/api/index.php',
    'upgrade' => '/public/install/upgrade.php',
];
$route = $routes[$input['route']];
$_SERVER['SCRIPT_FILENAME'] = TEAMPASS_ROOT . $route;
$_SERVER['REQUEST_URI'] = $route . '?otv=1&install=1';
$_GET = ['otv' => '1', 'install' => '1'];
$_SERVER['REMOTE_ADDR'] = $input['ip'] ?? '192.168.7.66';
$_SERVER['SERVER_ADDR'] = '192.0.2.100';
$_SERVER['REQUEST_METHOD'] = $input['method'] ?? 'POST';
if ($input['recipient'] ?? false) {
    define('TEAMPASS_PUBLIC_SECURE_SEND', true);
}
if ($input['storage_failure'] ?? false) {
    DB::$tableExists = false;
}
DB::$rules = [['type' => 'whitelist', 'rule_definition' => '192.168.6.0/24', 'enabled' => 1]];
$session = \TeampassClasses\SessionManager\SessionManager::getSession();
$session->set('user-id', 42);
$session->set('user-admin', ($input['route'] === 'admin') ? 1 : 0);
$session->set('key', 'synthetic-session-key');
$session->set('user-session_duration', time() + 3600);
$log = tempnam(sys_get_temp_dir(), 'tp-acl-request-');
ini_set('error_log', $log);
ob_start();
register_shutdown_function(static function () use ($log): void {
    $body = (string) ob_get_clean();
    unlink($log);
    echo json_encode(['status' => http_response_code() ?: 200, 'body' => $body], JSON_THROW_ON_ERROR);
});
teampassEnforceNetworkAccess(['network_whitelist_enabled' => ($input['disabled'] ?? false) ? 0 : 1]);
echo 'ACTION_REACHED';
