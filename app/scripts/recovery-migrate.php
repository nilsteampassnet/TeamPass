<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery-migrate.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

// A separate process contains legacy scripts that still use exit(). Never serve it over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../sources/recovery_upgrade.php';

try {
    $options = getopt('', ['job:', 'release:']);
    $jobId = (string) ($options['job'] ?? '');
    $release = (string) ($options['release'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/D', $jobId) !== 1 || !in_array($release, ['3.2.2', '3.2.3'], true)) {
        throw new TeamPassRecoveryException('UPGRADE_REQUEST_INVALID');
    }
    $root = dirname(__DIR__, 2);
    $jobDir = $root . '/storage/recovery/' . $jobId;
    if (is_link($root . '/storage/recovery') || is_link($jobDir) || is_link($jobDir . '/job.json')) {
        throw new TeamPassRecoveryException('RECOVERY_PATH_INVALID');
    }
    $job = json_decode((string) file_get_contents($jobDir . '/job.json'), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($job) || ($job['status'] ?? '') !== 'upgrading') {
        throw new TeamPassRecoveryException('UPGRADE_REQUEST_INVALID');
    }
    define('TEAMPASS_SECRETS', $jobDir . '/keys');
    define('TEAMPASS_FUNCTIONS_ONLY', true);
    define('TEAMPASS_RECOVERY_MIGRATION', true);
    require_once $root . '/app/config/include.php';
    $connection = $job['connection'];
    $kit = $job['kit'];
    foreach (['DB_HOST' => $connection['host'], 'DB_USER' => $connection['user'],
        'DB_NAME' => $connection['database'], 'DB_PREFIX' => $kit['prefix'],
        'DB_PORT' => $connection['port'], 'DB_ENCODING' => 'utf8mb4',
        'DB_SSL' => false, 'DB_CONNECT_OPTIONS' => [], 'SECUREFILE' => $kit['secure_name']] as $name => $value) {
        define($name, $value);
    }
    $key = \Defuse\Crypto\Key::loadFromAsciiSafeString((string) file_get_contents(TEAMPASS_SECRETS . '/' . SECUREFILE));
    define('DB_PASSWD', \Defuse\Crypto\Crypto::encrypt($connection['password'], $key));
    require_once $root . '/app/sources/main.functions.php';
    require_once $root . '/app/includes/libraries/phpseclibV1_autoload.php';
    require_once $root . '/public/install/tp.functions.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = $connection['password'];
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db_link = new mysqli(DB_HOST, DB_USER, $connection['password'], DB_NAME, DB_PORT);
    $db_link->set_charset('utf8mb4');
    $pre = (string) DB_PREFIX;
    $SETTINGS = [];
    foreach (DB::query('SELECT intitule, valeur FROM ' . prefixTable('misc') . ' WHERE type=%s', 'admin') as $setting) {
        $SETTINGS[$setting['intitule']] = $setting['valeur'];
    }
    // Script names come exclusively from the shipped allowlist above.
    require $root . '/public/install/upgrade_run_' . $release . '.php';
} catch (Throwable $exception) {
    // SQL driver messages may contain a secret value from an INSERT. Never emit them.
    fwrite(STDERR, "Recovery database migration failed.\n");
    exit(1);
}
