#!/usr/bin/env php
<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * ---
 * @file      install-cli.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

// Must be run from CLI
if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

define('TEAMPASS_ROOT', dirname(__DIR__));

// Bootstrap Composer autoloader
require_once TEAMPASS_ROOT . '/vendor/autoload.php';

// Load TeamPass constants
require_once TEAMPASS_ROOT . '/includes/config/include.php';

// Load install utility functions (pure functions, no side effects)
require_once TEAMPASS_ROOT . '/install/tp.functions.php';
require_once TEAMPASS_ROOT . '/install/install-steps/install.functions.php';

use Defuse\Crypto\Key;
use TeampassClasses\PasswordManager\PasswordManager;

// ============================================================
// Argument parsing
// ============================================================

/**
 * Parse CLI arguments of the form --key=value or --key value.
 *
 * @param array<int,string> $argv
 * @return array<string,string>
 */
function parseArgs(array $argv): array
{
    $args = [];
    $argc = count($argv);
    for ($i = 1; $i < $argc; $i++) {
        if (str_starts_with($argv[$i], '--')) {
            $part = substr($argv[$i], 2);
            if (str_contains($part, '=')) {
                [$key, $val] = explode('=', $part, 2);
                $args[$key] = $val;
            } elseif (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '--')) {
                $args[$part] = $argv[++$i];
            } else {
                $args[$part] = '';
            }
        }
    }
    return $args;
}

/**
 * Print a status line to stdout.
 *
 * @param string $status  One of 'ok', 'err', 'info'
 * @param string $message Human-readable message
 */
function printStatus(string $status, string $message): void
{
    $prefix = match ($status) {
        'ok'   => '[OK]  ',
        'err'  => '[ERR] ',
        default => '[..] ',
    };
    echo $prefix . $message . "\n";
}

/**
 * Exit with an error message.
 *
 * @param string $message
 */
function fatal(string $message): never
{
    echo '[FATAL] ' . $message . "\n";
    exit(1);
}

$args = parseArgs($argv);

// Required parameters
$required = ['db-host', 'db-name', 'db-user', 'db-password', 'admin-email', 'admin-pwd', 'url'];
foreach ($required as $key) {
    if (empty($args[$key])) {
        fatal("Missing required argument: --{$key}");
    }
}

// Defaults
$dbHost     = $args['db-host'];
$dbPort     = $args['db-port'] ?? '3306';
$dbName     = $args['db-name'];
$dbUser     = $args['db-user'];
$dbPassword = $args['db-password'];
$dbPrefix   = $args['db-prefix'] ?? 'teampass_';
$adminEmail = $args['admin-email'];
$adminPwd   = $args['admin-pwd'];
$teampassUrl = rtrim($args['url'], '/');

$absolutePath = TEAMPASS_ROOT;
$securePath   = TEAMPASS_ROOT . '/sk';

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  TeamPass CLI Installer\n";
echo "  Version: " . TP_VERSION . "." . TP_VERSION_MINOR . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// ============================================================
// Step 1 — Validate paths
// ============================================================
printStatus('info', 'Checking paths...');
if (!is_dir($absolutePath)) {
    fatal("Absolute path not found: {$absolutePath}");
}
if (!is_dir($securePath)) {
    fatal("Secure path not found: {$securePath}. Run create_directories first.");
}
if (!is_writable($securePath)) {
    fatal("Secure path is not writable: {$securePath}");
}
if (!is_writable($absolutePath . '/includes/config')) {
    fatal("Config directory is not writable: {$absolutePath}/includes/config");
}
printStatus('ok', 'Paths OK');

// ============================================================
// Step 2 — Database connection
// ============================================================
printStatus('info', 'Connecting to database...');
DB::$host   = $dbHost;
DB::$user   = $dbUser;
DB::$password = $dbPassword;
DB::$dbName = $dbName;
DB::$port   = (int) $dbPort;
DB::$encoding = 'utf8';
DB::$ssl    = null;
DB::$connect_options = [MYSQLI_OPT_CONNECT_TIMEOUT => 10];

try {
    DB::disconnect();
    DB::useDB($dbName);
} catch (Exception $e) {
    fatal('Database connection failed: ' . $e->getMessage());
}
printStatus('ok', "Connected to {$dbHost}:{$dbPort}/{$dbName}");

// ============================================================
// Step 3 — Populate _install table
// ============================================================
printStatus('info', 'Preparing installation metadata...');

DB::query(
    'CREATE TABLE IF NOT EXISTS `_install` (
        `key` varchar(100) NOT NULL,
        `value` varchar(500) NOT NULL,
        PRIMARY KEY (`key`)
    )'
);

// Generate a random filename for the secure key file
$secureFile = generateRandomKey();

$installData = [
    'teampassAbsolutePath' => $absolutePath,
    'teampassUrl'          => $teampassUrl,
    'teampassSecurePath'   => $securePath,
    'tablePrefix'          => $dbPrefix,
    'adminPassword'        => $adminPwd,
    'adminEmail'           => $adminEmail,
    'adminName'            => 'admin',
    'adminLastname'        => 'admin',
    'teampassSecureFile'   => $secureFile,
];

foreach ($installData as $key => $value) {
    DB::insertUpdate('_install', ['key' => $key, 'value' => $value]);
}
printStatus('ok', 'Installation metadata stored');

// ============================================================
// Step 4 — Create database tables (via run.step5.php)
// ============================================================
// We include run.step5.php once (in an output buffer) to define the
// DatabaseInstaller class and the checks() function, then call checks()
// directly for all remaining table-creation actions.

printStatus('info', 'Creating database tables...');

// The step5 file uses a relative require for the autoloader; change to its
// directory so that relative paths resolve correctly.
$previousDir = getcwd();
chdir(TEAMPASS_ROOT . '/install/install-steps');

/**
 * Build the $inputData array expected by the install step files.
 *
 * @param string $action
 * @return array<string,string>
 */
function buildStepInputData(string $action): array
{
    global $dbHost, $dbPort, $dbName, $dbUser, $dbPassword, $dbPrefix;
    return [
        'action'      => $action,
        'dbHost'      => $dbHost,
        'dbPort'      => $dbPort,
        'dbName'      => $dbName,
        'dbLogin'     => $dbUser,
        'dbPw'        => $dbPassword,
        'tablePrefix' => $dbPrefix,
    ];
}

// Include step5 (suppress its one-time output; defines DatabaseInstaller + checks())
$_POST = array_merge(buildStepInputData('utf8'), ['_cli' => '1']);
ob_start();
require TEAMPASS_ROOT . '/install/install-steps/run.step5.php';
$firstResult = json_decode((string) ob_get_clean(), true);
if (empty($firstResult['success'])) {
    fatal('Table creation failed (utf8): ' . ($firstResult['message'] ?? 'unknown error'));
}
printStatus('ok', 'Table utf8');

// All remaining step-5 actions
$step5Actions = [
    'migrateToUtf8mb4',
    'checkAndSetInnoDBEngine',
    'api',
    'automatic_del',
    'cache',
    'cache_tree',
    'categories',
    'categories_folders',
    'categories_items',
    'defuse_passwords',
    'emails',
    'export',
    'files',
    'items',
    'items_change',
    'items_edition',
    'items_otp',
    'kb',
    'kb_categories',
    'kb_items',
    'ldap_groups_roles',
    'languages',
    'log_items',
    'log_system',
    'misc',
    'nested_tree',
    'notification',
    'otv',
    'background_tasks',
    'background_subtasks',
    'background_tasks_logs',
    'restriction_to_roles',
    'rights',
    'roles_title',
    'roles_values',
    'sharekeys_fields',
    'sharekeys_files',
    'sharekeys_items',
    'sharekeys_logs',
    'sharekeys_suggestions',
    'suggestion',
    'tags',
    'templates',
    'tokens',
    'users',
    'auth_failures',
    'items_importations',
    'user_private_keys',
    'users_groups',
    'users_groups_forbidden',
    'users_roles',
    'users_favorites',
    'users_latest_items',
    'users_options_favorites',
    'encryption_migration_stats',
    'websocket_events',
    'websocket_connections',
    'websocket_tokens',
    'network_acl',
];

foreach ($step5Actions as $action) {
    $result = checks(buildStepInputData($action));
    if (empty($result['success'])) {
        fatal("Table creation failed ({$action}): " . ($result['message'] ?? 'unknown error'));
    }
    printStatus('ok', "Table {$action}");
}

// Restore working directory
chdir((string) $previousDir);

// ============================================================
// Step 5 — Generate Defuse encryption key (secure file)
// ============================================================
printStatus('info', 'Generating encryption key...');

$key       = Key::createNewRandomKey();
$newSalt   = $key->saveToAsciiSafeString();
$secureFilePath = $securePath . '/' . $secureFile;

if (file_put_contents($secureFilePath, $newSalt) === false) {
    fatal("Failed to write encryption key to: {$secureFilePath}");
}
printStatus('ok', "Encryption key written to {$secureFilePath}");

// ============================================================
// Step 6 — Create settings.php
// ============================================================
printStatus('info', 'Writing settings.php...');

$settingsFile    = $absolutePath . '/includes/config/settings.php';
$encryptedDbPwd  = encryptFollowingDefuseForInstall($dbPassword, $newSalt);

if ($encryptedDbPwd['error'] !== false) {
    fatal('Failed to encrypt DB password: ' . $encryptedDbPwd['error']);
}

$encryptedPwdStr = str_replace('$', '\$', $encryptedDbPwd['string']);

$settingsContent = '<?php
// DATABASE connexion parameters
define("DB_HOST", "' . $dbHost . '");
define("DB_USER", "' . $dbUser . '");
define("DB_PASSWD", "' . $encryptedPwdStr . '");
define("DB_NAME", "' . $dbName . '");
define("DB_PREFIX", "' . $dbPrefix . '");
define("DB_PORT", "' . $dbPort . '");
define("DB_ENCODING", "utf8mb4");
define("DB_SSL", false); // if DB over SSL then comment this line
// if DB over SSL then uncomment the following lines
//define("DB_SSL", array(
//    "key" => "",
//    "cert" => "",
//    "ca_cert" => "",
//    "ca_path" => "",
//    "cipher" => ""
//));
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
define("SECUREPATH", "' . $securePath . '");
define("SECUREFILE", "' . $secureFile . '");

if (isset($_SESSION[\'settings\'][\'timezone\']) === true) {
    date_default_timezone_set($_SESSION[\'settings\'][\'timezone\']);
}
';

if (file_put_contents($settingsFile, $settingsContent) === false) {
    fatal("Failed to write settings.php to: {$settingsFile}");
}
printStatus('ok', 'settings.php written');

// ============================================================
// Step 7 — CSRF configuration
// ============================================================
printStatus('info', 'Configuring CSRF protection...');

$csrfpDir        = $absolutePath . '/includes/libraries/csrfp/libs/';
$csrfpSampleFile = $csrfpDir . 'csrfp.config.sample.php';
$csrfpFile       = $csrfpDir . 'csrfp.config.php';

if (!is_readable($csrfpSampleFile)) {
    // Non-fatal: CSRF config is optional if already present
    printStatus('info', 'CSRF sample file not found, skipping.');
} else {
    if (file_exists($csrfpFile)) {
        @copy($csrfpFile, $csrfpFile . '.' . date('Y_m_d') . '.bak');
    }
    $csrfData = file_get_contents($csrfpSampleFile);
    if ($csrfData === false) {
        fatal("Cannot read CSRF sample file: {$csrfpSampleFile}");
    }

    $csrfToken   = bin2hex(openssl_random_pseudo_bytes(25));
    $secureCookie = str_starts_with($teampassUrl, 'https://') ? 'true' : 'false';

    $csrfData = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "' . $csrfToken . '"', $csrfData);
    $csrfData = str_replace('"jsUrl" => ""', '"jsUrl" => "./includes/libraries/csrfp/js/csrfprotector.js"', $csrfData);
    $csrfData = str_replace('"secure" => true', '"secure" => ' . $secureCookie, $csrfData);

    if (file_put_contents($csrfpFile, $csrfData) === false) {
        fatal("Failed to write CSRF config to: {$csrfpFile}");
    }
    printStatus('ok', 'CSRF config written');
}

// ============================================================
// Step 8 — Initialize background task files
// ============================================================
$triggerFile = $absolutePath . '/files/teampass_background_tasks.trigger';
$lockFile    = $absolutePath . '/files/teampass_background_tasks.lock';
if (!file_exists($triggerFile)) {
    @file_put_contents($triggerFile, (string) time());
    @chmod($triggerFile, 0640); // owner=rw, group=r, world=none
}
if (!file_exists($lockFile)) {
    @file_put_contents($lockFile, (string) time());
    @chmod($lockFile, 0640); // owner=rw, group=r, world=none
}

// ============================================================
// Step 9 — Create admin user
// ============================================================
printStatus('info', 'Creating admin user...');

// Re-connect now that settings.php exists and _install data is available
DB::$host     = $dbHost;
DB::$user     = $dbUser;
DB::$password = $dbPassword;
DB::$dbName   = $dbName;
DB::$port     = (int) $dbPort;
DB::$encoding = 'utf8';
DB::$ssl      = null;
DB::$connect_options = [MYSQLI_OPT_CONNECT_TIMEOUT => 10];
DB::disconnect();
DB::useDB($dbName);

$passwordManager = new PasswordManager();
$hashedPwd       = $passwordManager->hashPassword($adminPwd);

$adminExists = (int) DB::queryFirstField(
    'SELECT COUNT(*) FROM %busers WHERE login = %s',
    $dbPrefix,
    'admin'
);

if ($adminExists === 0) {
    DB::insert($dbPrefix . 'users', [
        'id'                     => 1,
        'login'                  => 'admin',
        'pw'                     => $hashedPwd,
        'admin'                  => 1,
        'gestionnaire'           => 0,
        'personal_folder'        => 0,
        'email'                  => $adminEmail,
        'encrypted_psk'          => '',
        'last_pw_change'         => time(),
        'name'                   => 'admin',
        'lastname'               => 'admin',
        'can_create_root_folder' => 1,
        'public_key'             => 'none',
        'private_key'            => 'none',
        'is_ready_for_usage'     => 1,
        'otp_provided'           => 1,
        'created_at'             => time(),
    ]);
    printStatus('ok', 'Admin user created');
} else {
    DB::update($dbPrefix . 'users', ['pw' => $hashedPwd], 'login = %s AND id = %i', 'admin', 1);
    printStatus('ok', 'Admin user password updated');
}

// TP internal user
$tpUserExists = (int) DB::queryFirstField(
    'SELECT COUNT(*) FROM %busers WHERE id = %i',
    $dbPrefix,
    TP_USER_ID
);
if ($tpUserExists === 0) {
    $tpPwd  = GenerateCryptKeyForInstall(25, true, true, true, true);
    $encTpPwd = cryptionForInstall($tpPwd, $newSalt, 'encrypt')['string'];
    $tpKeys = generateUserKeysForInstall($tpPwd);

    DB::insert($dbPrefix . 'users', [
        'id'                     => TP_USER_ID,
        'login'                  => 'TP',
        'pw'                     => $encTpPwd,
        'derniers'               => '',
        'key_tempo'              => '',
        'last_pw_change'         => '',
        'last_pw'                => '',
        'admin'                  => 1,
        'last_connexion'         => '',
        'gestionnaire'           => 0,
        'email'                  => 'none',
        'user_ip'                => 'none',
        'personal_folder'        => 0,
        'public_key'             => $tpKeys['public_key'],
        'private_key'            => $tpKeys['private_key'],
        'is_ready_for_usage'     => 1,
        'otp_provided'           => 0,
        'created_at'             => time(),
    ]);

    DB::insert($dbPrefix . 'user_private_keys', [
        'user_id'     => TP_USER_ID,
        'private_key' => $tpKeys['private_key'],
        'is_current'  => true,
    ]);
    printStatus('ok', 'TP internal user created');
}

// API user
$apiUserExists = (int) DB::queryFirstField(
    'SELECT COUNT(*) FROM %busers WHERE id = %i',
    $dbPrefix,
    API_USER_ID
);
if ($apiUserExists === 0) {
    DB::insert($dbPrefix . 'users', [
        'id'              => API_USER_ID,
        'login'           => 'API',
        'pw'              => '',
        'derniers'        => '',
        'key_tempo'       => '',
        'last_pw_change'  => '',
        'last_pw'         => '',
        'admin'           => 1,
        'last_connexion'  => '',
        'gestionnaire'    => 0,
        'email'           => '',
        'personal_folder' => 0,
        'is_ready_for_usage' => 1,
        'otp_provided'    => 0,
        'created_at'      => time(),
    ]);
    printStatus('ok', 'API user created');
}

// OTV user
$otvUserExists = (int) DB::queryFirstField(
    'SELECT COUNT(*) FROM %busers WHERE id = %i',
    $dbPrefix,
    OTV_USER_ID
);
if ($otvUserExists === 0) {
    DB::insert($dbPrefix . 'users', [
        'id'              => OTV_USER_ID,
        'login'           => 'OTV',
        'pw'              => '',
        'derniers'        => '',
        'key_tempo'       => '',
        'last_pw_change'  => '',
        'last_pw'         => '',
        'admin'           => 1,
        'last_connexion'  => '',
        'gestionnaire'    => 0,
        'email'           => '',
        'personal_folder' => 0,
        'is_ready_for_usage' => 1,
        'otp_provided'    => 0,
        'created_at'      => time(),
    ]);
    printStatus('ok', 'OTV user created');
}

// ============================================================
// Step 10 — Clean up _install table
// ============================================================
printStatus('info', 'Cleaning up...');

DB::query('DROP TABLE IF EXISTS `_install`');
DB::query(
    'INSERT INTO %bmisc (`type`, `intitule`, `valeur`) VALUES (%s, %s, %s)',
    $dbPrefix,
    'install',
    'clear_install_folder',
    'true'
);
printStatus('ok', 'Installation metadata cleaned up');

// ============================================================
// Done
// ============================================================
echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Installation completed successfully!\n";
echo "  TeamPass is ready at: {$teampassUrl}\n";
echo "  Login: admin / [your admin password]\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
exit(0);
