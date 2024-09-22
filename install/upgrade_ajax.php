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
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      upgrade_ajax.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\ConfigManager\ConfigManager;


$_SESSION = [];

function settingsConsistencyCheck(): array
{
    $settingsFile = __DIR__.'/../includes/config/settings.php';
    require_once $settingsFile;
    require_once 'tp.functions.php';

    if (defined('DB_PASSWD') === false && isset($pass) === true) {
        // We need to convert settings.php file from V2 to V3 format
        
        //Do a copy of the existing file
        if (!copy(
            $settingsFile,
            $settingsFile . '.' . date(
                'Y_m_d_H_i_s',
                mktime((int) date('H'), (int) date('i'), (int) date('s'), (int) date('m'), (int) date('d'), (int) date('y'))
            )
        )) {
            $error = error_get_last();
            $errorMessage = isset($error['message']) ? $error['message'] : 'Unknown error.';
            return [
                'error' => '[{
                    "error" : "Error: '.$errorMessage.'. Please do it by yourself and click on button Launch.",
                    "index" : ""
                }]',
                'value' => false
            ];
        }


        // Handle teampass-seckey.txt file
        if (file_exists(SECUREPATH.'/teampass-seckey.txt')) {
            // do a copy
            if (!copy(
                SECUREPATH.'/teampass-seckey.txt',
                SECUREPATH.'/teampass-seckey.txt' . '.' . date(
                    'Y_m_d_H_i_s',
                    mktime((int) date('H'), (int) date('i'), (int) date('s'), (int) date('m'), (int) date('d'), (int) date('y'))
                )
            )) {
                $error = error_get_last();
                $errorMessage = isset($error['message']) ? $error['message'] : 'Unknown error.';
                return [
                    'error' => '[{
                        "error" : "Error: '.$errorMessage.'. Please do it by yourself and click on button Launch.",
                        "index" : ""
                    }]',
                    'value' => false
                ];
            }

            // prepare new file
            $filesecure = generateRandomKey();
            define('SECUREFILE', $filesecure);
        } else {
            return [
                'error' => '[{
                    "error" : "'.SECUREPATH.'/teampass-seckey.txt file does not exist. Please recover it and click on button Launch.",
                    "index" : ""
                }]',
                'value' => false
            ];
        }


        // Ensure DB is read as UTF8
        if (defined('DB_ENCODING') === false) {
            define('DB_ENCODING', "utf8");
        }

        // Delete olf file
        unlink($settingsFile);
        // Now create new file
        $file_handled = fopen($settingsFile, 'w');
        
        $settingsTxt = '<?php
// DATABASE connexion parameters
define("DB_HOST", "' . trim($server) . '");
define("DB_USER", "' . trim($user) . '");
define("DB_PASSWD", "' . trim($pass) . '");
define("DB_NAME", "' . trim($database) . '");
define("DB_PREFIX", "' . trim($pre) . '");
define("DB_PORT", "' . trim($port) . '");
define("DB_ENCODING", "' . trim($encoding) . '");
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
define("SECUREPATH", "' . str_replace("\\", "\\\\", SECUREPATH) . '");
define("SECUREFILE", "' . SECUREFILE. '");';

		if (defined('IKEY') === true) $settingsTxt .= '
define("IKEY", "' . IKEY . '");';
		else $settingsTxt .= '
define("IKEY", "");';
		if (defined('SKEY') === true) $settingsTxt .= '
define("SKEY", "' . SKEY . '");';
		else $settingsTxt .= '
define("SKEY", "");';
		if (defined('HOST') === true) $settingsTxt .= '
define("HOST", "' . HOST . '");';
		else $settingsTxt .= '
define("HOST", "");';


        $settingsTxt .= '

if (isset($_SESSION[\'settings\'][\'timezone\']) === true) {
    date_default_timezone_set($_SESSION[\'settings\'][\'timezone\']);
}
';

        $fileCreation = fwrite(
            $file_handled,
            utf8_encode($settingsTxt)
        );

        fclose($file_handled);
        if ($fileCreation === false) {
            return [
                'error' => '[{
                    "error" : "Setting.php file could not be created in /includes/config/ folder. Please check the path and the rights.",
                    "index" : ""
                }]',
                'value' => false
            ];
        }
        
        define("DB_HOST", "' . $server . '");
        define("DB_USER", "' . $user . '");
        define("DB_PASSWD", "' . $pass . '");
        define("DB_NAME", "' . $database . '");
        define("DB_PREFIX", "' . $pre . '");
        define("DB_PORT", "' . $port . '");
        define("DB_SSL", "false");
        define("DB_CONNECT_OPTIONS", array(
            MYSQLI_OPT_CONNECT_TIMEOUT => 10
        ));

        return [
            'error' => '',
            'value' => true
        ];
    }

    // No need to create new settings.php file
    return [
        'error' => '',
        'value' => false
    ];
}

// Check and build if necessary the new settings.php file
$check = settingsConsistencyCheck();
$settingsFileNewlyCreated = $check['value'];

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language(); 

error_reporting(E_ERROR | E_PARSE);
$_SESSION['CPM'] = 1;
define('MIN_PHP_VERSION', 8.1);
define('MIN_MYSQL_VERSION', '8.0.13');
define('MIN_MARIADB_VERSION', '10.2.1');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once 'tp.functions.php';


// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_index = filter_input(INPUT_POST, 'index', FILTER_SANITIZE_NUMBER_INT);
$post_multiple = filter_input(INPUT_POST, 'multiple', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_pwd = filter_input(INPUT_POST, 'pwd', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_fullurl = filter_input(INPUT_POST, 'fullurl', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_abspath = filter_input(INPUT_POST, 'abspath', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_no_previous_sk = filter_input(INPUT_POST, 'no_previous_sk', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_session_salt = filter_input(INPUT_POST, 'session_salt', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_previous_sk = filter_input(INPUT_POST, 'previous_sk', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_no_maintenance_mode = filter_input(INPUT_POST, 'no_maintenance_mode', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_prefix_before_convert = filter_input(INPUT_POST, 'prefix_before_convert', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_sk_path = filter_input(INPUT_POST, 'sk_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_url_path = filter_input(INPUT_POST, 'url_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);


// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
$server = DB_HOST;
$pre = DB_PREFIX;
$database = DB_NAME;
$port = intval(DB_PORT);
$user = DB_USER;

try {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
    $res = 'Connection is successful';
    $db_link->set_charset(DB_ENCODING);
} catch (Exception $e) {
    echo '[{
        "error" : "Impossible to get connected to server. Ensure that file includes/config/settings.php exists and is correct.",
        "index" : ""
    }]';
    exit;
}

// Set Session
$superGlobal->put('CPM', 1, 'SESSION');
$superGlobal->put('db_encoding', 'utf8', 'SESSION');
$_SESSION['settings']['loaded'] = '';
if (empty($post_fullurl) === false) {
    $superGlobal->put('fullurl', $post_fullurl, 'SESSION');
}
if (empty($abspath) === false) {
    $superGlobal->put('abspath', $abspath, 'SESSION');
}

// Get Sessions
$session_url_path = $superGlobal->get('url_path', 'SESSION');

if (isset($post_type)) {
    switch ($post_type) {
        case 'step0':
            // erase session table
            $_SESSION = array();
            setcookie('pma_end_session');
            session_destroy();

            require_once 'libs/aesctr.php';
            
            // check if path in settings.php are consistent
            if (defined(SECUREPATH) === true) {
                if (!is_dir(SECUREPATH)) {
                    echo '[{'.
                        '"error" : "Error in settings.php file!<br>Check correctness of path indicated in file `includes/config/settings.php`.<br>Reload this page and retry.",'.
                        '"index" : ""'.
                    '}]';
                    break;
                }
                if (!file_exists(SECUREPATH . '/sk.php')) {
                    echo '[{'.
                        '"error" : "Error in settings.php file!<br>Check correctness of path indicated in file `includes/config/settings.php`.<br>Reload this page and retry.",'.
                        '"index" : ""'.
                    '}]';
                    break;
                }
            }
            
            $_SESSION['settings']['cpassman_dir'] = '..';
            $passwordManager = new PasswordManager();

            // Connect to db and check user is granted
            $user_info = mysqli_fetch_array(
                mysqli_query(
                    $db_link,
                    'SELECT id, pw, admin FROM ' . $pre . "users
                    WHERE login='" . mysqli_escape_string($db_link, stripslashes($post_login)) . "'"
                )
            );
            
            if (empty($user_info['pw']) || $user_info['pw'] === null) {
                echo '[{'.
                    '"error" : "User is not allowed",'.
                    '"index" : ""'.
                '}]';
                $superGlobal->put('user_granted', false, 'SESSION');
            } else {
                if ($passwordManager->verifyPassword($user_info['pw'], Encryption\Crypt\aesctr::decrypt(base64_decode($post_pwd), 'cpm', 128)) === true && $user_info['admin'] === '1') {
                    $superGlobal->put('user_granted', true, 'SESSION');
                    $superGlobal->put('user_login', mysqli_escape_string($db_link, stripslashes($post_login)), 'SESSION');
                    $superGlobal->put('user_password', Encryption\Crypt\aesctr::decrypt(base64_decode($post_pwd), 'cpm', 128), 'SESSION');
                    $superGlobal->put('user_id', $user_info['id'], 'SESSION');
                    echo '[{'.
                        '"error" : "",'.
                        '"index" : 1,'.
                        '"info" : "' . base64_encode(json_encode(
                            array(mysqli_escape_string($db_link, stripslashes($post_login)), $post_pwd, $user_info['id'])
                        )) . '"'.
                    '}]';
                } else {
                    $superGlobal->put('user_granted', false, 'SESSION');
                    echo '[{'.
                        '"error" : "User is not allowed",'.
                        '"index" : ""'.
                    '}]';
                }
            }

            break;

        case 'step1':
            $session_user_granted = $superGlobal->get('user_granted', 'SESSION');

            if (intval($session_user_granted) !== 1) {
                echo '[{'.
                    '"error" : "User not connected anymore",'.
                    '"index" : ""'.
                '}]';
                break;
            }

            $abspath = str_replace('\\', '/', $post_abspath);
            if (substr($abspath, strlen($abspath) - 1) == '/') {
                $abspath = substr($abspath, 0, strlen($abspath) - 1);
            }
            $okWritable = true;
            $okExtensions = true;
            $okTasksManager = true;
            $okTUsersPasswordsSymfony = true;
            $txt = '';
            $var_x = 1;
            $tab = array(
                $abspath . '/includes/config/settings.php',
                $abspath . '/includes/libraries/csrfp/libs/',
                $abspath . '/install/',
                $abspath . '/includes/',
                $abspath . '/includes/config/',
                $abspath . '/includes/avatars/',
                $abspath . '/files/',
                $abspath . '/upload/',
            );
            foreach ($tab as $elem) {
                // try to create it if not existing
                if (substr($elem, -1) === '/' && !is_dir($elem)) {
                    mkdir($elem);
                }
                // check if writable
                if (is_writable($elem)) {
                    $txt .= '<span>' .
                        $elem . '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
                } else {
                    $txt .= '<span>' .
                        $elem . '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
                    $okWritable = false;
                }
                ++$var_x;
            }

            if (!extension_loaded('openssl')) {
                //$okExtensions = false;
                $txt .= '<span>PHP extension \"openssl\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"openssl\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('gd')) {
                //$okExtensions = false;
                $txt .= '<span>PHP extension \"gd\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"gd\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('mbstring')) {
                //$okExtensions = false;
                $txt .= '<span>PHP extension \"mbstring\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"mbstring\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('bcmath')) {
                //$okExtensions = false;
                $txt .= '<span>PHP extension \"bcmath\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"bcmath\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('iconv')) {
                //$okExtensions = false;
                $txt .= '<span>PHP extension \"iconv\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"iconv\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('xml')) {
                //$okExtensions = false;
                $txt .= '<span>PHP extension \"xml\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"xml\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('curl')) {
                $txt .= '<span>PHP extension \"curl\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"curl\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (!extension_loaded('gmp')) {
                $txt .= '<span>PHP extension \"gmp\"' .
                    '<i class=\"fa-solid fa-circle-minus text-danger ml-2\"></i></span><br />';
            } else {
                $txt .= '<span>PHP extension \"gmp\"' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (ini_get('max_execution_time') < 30) {
                $txt .= '<span>PHP \"Maximum ' .
                    'execution time\" is set to ' . ini_get('max_execution_time') . ' seconds.' .
                    ' Please try to set to 60s at least until Upgrade is finished.&nbsp;' .
                    '&nbsp;<img src=\"images/minus-circle.png\"></span> <br />';
            } else {
                $txt .= '<span>PHP \"Maximum ' .
                    'execution time\" is set to ' . ini_get('max_execution_time') . ' seconds' .
                    '<i class=\"fa-solid fa-circle-check text-success ml-2\"></i></span><br />';
            }
            if (version_compare(phpversion(), MIN_PHP_VERSION, '<')) {
                $txt .= '<span>PHP version ' .
                    phpversion() . ' is not OK (minimum is '.MIN_PHP_VERSION.') &nbsp;&nbsp;' .
                    '<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span>PHP version ' .
                    phpversion() . ' is OK<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                    '</span><br />';
            }
            $mysqlVersion = version_compare($db_link -> server_version, MIN_MYSQL_VERSION, '<') ;
            $mariadbVersion = version_compare($db_link -> server_version, MIN_MARIADB_VERSION, '<') ;
            if ($mysqlVersion && $mariadbVersion) {
                if ($mariadbVersion === '') {
                    $txt .= '<span>MySQL version ' .
                        $db_link -> server_version . ' is not OK (minimum is '.MIN_MYSQL_VERSION.') &nbsp;&nbsp;' .
                        '<img src=\"images/minus-circle.png\"></span><br />';
                } else {
                    $txt .= '<span>MySQL version ' .
                        $db_link -> server_version . ' is not OK (minimum is '.MIN_MARIADB_VERSION.') &nbsp;&nbsp;' .
                        '<img src=\"images/minus-circle.png\"></span><br />';
                }
            } else {
                if ($mariadbVersion === '') {
                    $txt .= '<span>MySQL version ' .
                        $db_link -> server_info . ' is OK<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                        '</span><br />';
                } else {
                    $txt .= '<span>MySQL version ' .
                        $db_link -> server_info . ' is OK<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                        '</span><br />';
                }
                
            }
            

            // check if 2.1.27 already installed
            // Test non necessaire si on a deja fait l'upgrade
            if (defined(SECUREPATH) === true) {
                // Check if we are in version 3
                if (@mysqli_fetch_row(
                    mysqli_query(
                        $db_link,
                        'SELECT valeur FROM ' . $pre . "misc
                        WHERE type='admin' AND intitule = 'enable_tasks_manager'"
                    )
                )) {
                    $okEncryptKey = true;
                } else {
                    // We are not in version 3
                    $okEncryptKey = false;
                    $defuse_file = SECUREPATH . '/teampass-seckey.txt';
                    if (file_exists($defuse_file)) {
                        $okEncryptKey = true;
                        $superGlobal->put('tp_defuse_installed', true, 'SESSION');
                        $txt .= '<span>Defuse encryption key is defined<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                            '</span><br />';
                    }

                    if ($okEncryptKey === false) {
                        $superGlobal->put('tp_defuse_installed', false, 'SESSION');
                        $txt .= '<span>Encryption Key (SALT) ' .
                            ' could not be recovered from ' . $defuse_file . '&nbsp;&nbsp;' .
                            '<img src=\"images/minus-circle.png\"></span><br />';
                            $okEncryptKey = false;
                    } else {
                        $okEncryptKey = true;
                        $txt .= '<span>Encryption Key (SALT) is available<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                            '</span><br />';
                    }
                }                
                
            } else {
                $okEncryptKey = true;
            }

            if ($okWritable === true && $okExtensions === true && $okEncryptKey === true) {
                $error = "";
                $nextStep = 2;
            } else {
                $error = "Something went wrong. Please check messages.";
                $nextStep = 1;
            }

            // Is tasks manager empty?
            $tableProcessesExists = mysqli_query(
                $db_link,
                "SELECT * FROM information_schema.tables
                WHERE table_schema = '$database'
                AND table_name = '" . $pre . "processes'"
            );
            if ($tableProcessesExists === true) {
                @mysqli_query(
                    $db_link,
                    "SELECT * FROM `" . $pre . "processes`
                    WHERE finished_at = ''"
                );
                if (@mysqli_affected_rows($db_link) > 0) {
                    $txt .= '<span>Tasks manager is not empty. Please empty it before starting next step.&nbsp;&nbsp;' .
                            '<img src=\"images/minus-circle.png\"></span><br />';
                    $okTasksManager = false;
                } else {
                    $okTasksManager = true;
                    $txt .= '<span>Tasks manager is empty<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                        '</span><br />';
                }
            } else {
                $okTasksManager = true;
                $txt .= '<span>Tasks manager is empty<i class=\"fa-solid fa-circle-check text-success ml-2\"></i>' .
                        '</span><br />';
            }

            // are users passwords encrypted with new Symfony library?
            @mysqli_query(
                $db_link,
                "SELECT * FROM `" . $pre . "users`
                WHERE pw LIKE '$2y$10$%'"
            );
            if (@mysqli_affected_rows($db_link) > 0) {
                $txt .= '<span>Users password not encrypted with new library. Is a blocker to upgrade to 3.2.0' .
                '&nbsp;<a target=\"_blank\" href=\"https://github.com/nilsteampassnet/TeamPass/discussions/4020\">[Read more]</a>'.
                '&nbsp;&nbsp;<i class=\"fa-solid fa-triangle-exclamation text-warning ml-2\"></i>'.
                '</span><br />';
                if (TP_VERSION === '3.2.0') $okTUsersPasswordsSymfony = false;
            }

            if ($okWritable === true && $okExtensions === true && $okEncryptKey === true && $okTasksManager === true && $okTUsersPasswordsSymfony === true) {
                $error = "";
                $nextStep = 2;
            } else {
                $error = "Something went wrong. Please check messages.";
                $nextStep = 1;
            }

            echo '[{'.
                '"error" : "' . $error . '",'.
                '"info" : "' . $txt . '",'.
                '"index" : "'.($error === "" ? "" : $nextStep).'",'.
                '"infos" : "' . $okWritable." ; ".$okExtensions." ; ".$okEncryptKey." ; ".$okTasksManager." ; " . '"'.
            '}]';
            break;

            //==========================
        case 'step2':
            $res = '';
            $session_user_granted = $superGlobal->get('user_granted', 'SESSION');

            if ($session_user_granted !== '1') {
                echo '[{'.
                    '"error" : "User not connected anymore",'.
                    '"index" : ""'.
                '}]';
                break;
            }
            //decrypt the password
            // AES Counter Mode implementation
            require_once 'libs/aesctr.php';

            //Get some infos from DB
            $cpmIsUTF8[0] = 0;
            if (@mysqli_fetch_row(
                mysqli_query(
                    $db_link,
                    'SELECT valeur FROM ' . $pre . "misc
                    WHERE type='admin' AND intitule = 'utf8_enabled'"
                )
            )) {
                $cpmIsUTF8 = mysqli_fetch_row(
                    mysqli_query(
                        $db_link,
                        'SELECT valeur FROM ' . $pre . "misc
                        WHERE type='admin' AND intitule = 'utf8_enabled'"
                    )
                );
            }
            $superGlobal->put('utf8_enabled', $cpmIsUTF8[0], 'SESSION');

            // put TP in maintenance mode or not
            @mysqli_query(
                $db_link,
                "UPDATE `" . $pre . "misc`
                SET `valeur` = 'maintenance_mode'
                WHERE type = 'admin' AND intitule = '" . $post_no_maintenance_mode . "'"
            );

            echo '[{'.
                '"error" : "",'.
                '"index" : "",'.
                '"info" : "'.$res.'",'.
                '"isUtf8" : "'.$cpmIsUTF8[0].'"'.
            '}]';
            break;

            //==========================
        case 'step3':
            $session_user_granted = $superGlobal->get('user_granted', 'SESSION');

            if ($session_user_granted !== '1') {
                echo '[{'.
                    '"error" : "User not connected anymore",'.
                    '"index" : ""'.
                '}]';
                break;
            }

            //rename tables
            if (isset($post_prefix_before_convert) && $post_prefix_before_convert == 'true') {
                $tables = mysqli_query($db_link, 'SHOW TABLES');
                while ($table = mysqli_fetch_row($tables)) {
                    if (tableExists('old_' . $table[0]) != 1 && substr($table[0], 0, 4) != 'old_') {
                        mysqli_query($db_link, 'CREATE TABLE old_' . $table[0] . ' LIKE ' . $table[0]);
                        mysqli_query($db_link, 'INSERT INTO old_' . $table[0] . ' SELECT * FROM ' . $table[0]);
                    }
                }
            }

            //convert database
            mysqli_query(
                $db_link,
                'ALTER DATABASE `' . $database . '`
                DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci'
            );

            //convert tables
            $res = mysqli_query($db_link, 'SHOW TABLES FROM `' . $database . '`');
            while ($table = mysqli_fetch_row($res)) {
                if (substr($table[0], 0, 4) != 'old_') {
                    mysqli_query(
                        $db_link,
                        'ALTER TABLE ' . $database . '.`{$table[0]}`
                        CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci'
                    );
                    mysqli_query(
                        $db_link,
                        'ALTER TABLE' . $database . '.`{$table[0]}`
                        DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci'
                    );
                }
            }

            echo '[{'.
                '"error" : "",'.
                '"index" : ""'.
            '}]';

            mysqli_close($db_link);
            break;

            //==========================

            //=============================
        case 'step5':
            $session_user_granted = $superGlobal->get('user_granted', 'SESSION');

            if (intVal($session_user_granted) !== 1) {
                echo '[{'.
                    '"error" : "User not connected anymore",'.
                    '"index" : ""'.
                '}]';
                break;
            }

            $returnStatus = array();
            // If settings.php file doesn't contain DB_HOST then regenerate it
            $settingsFile = '../includes/config/settings.php';
            include_once $settingsFile;

            if (defined('DB_SSL') === false) {
                //Do a copy of the existing file
                if (!copy(
                    $settingsFile,
                    $settingsFile . '.' . date(
                        'Y_m_d_H_i_s',
                        mktime((int) date('H'), (int) date('i'), (int) date('s'), (int) date('m'), (int) date('d'), (int) date('y'))
                    )
                )) {
                    echo '[{'.
                        '"error" : "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch",'.
                        '"index" : ""'.
                    '}]';
                    break;
                } else {
                    unlink($settingsFile);
                }

                // CHeck if old sk.php exists.
                // If yes then get keys to database and delete it
                if (empty($post_sk_path) === false || defined('SECUREPATH') === true) {
                    $filename = (empty($post_sk_path) === false ? $post_sk_path : SECUREPATH) . '/sk.php';
                    if (file_exists($filename)) {
                        include_once $filename;
                        unlink($filename);

                        $filesecure = generateRandomKey();
                        define('SECUREFILE', $filesecure);

                        // Using the new Duo Web SDK akey is deprecated, not keeping track of it.
                        // SKEY
                        $tmp = mysqli_query(
                            $db_link,
                            "SELECT INTO `" . $pre . "misc`
                            WHERE type = 'admin' AND intitule = 'duo_skey'"
                        );
                        if ($tmp) {
                            mysqli_query(
                                $db_link,
                                "UPDATE `" . $pre . "misc`
                                set valeur = '" . SKEY . "', type = 'admin', intitule = 'duo_skey'"
                            );
                        } else {
                            mysqli_query(
                                $db_link,
                                "INSERT INTO `" . $pre . "misc`
                                (`valeur`, `type`, `intitule`)
                                VALUES ('" . SKEY . "', 'admin', 'duo_skey')"
                            );
                        }

                        // IKEY
                        $tmp = mysqli_query(
                            $db_link,
                            "SELECT INTO `" . $pre . "misc`
                            WHERE type = 'admin' AND intitule = 'duo_ikey'"
                        );
                        if ($tmp) {
                            mysqli_query(
                                $db_link,
                                "UPDATE `" . $pre . "misc`
                                set valeur = '" . IKEY . "', type = 'admin', intitule = 'duo_ikey'"
                            );
                        } else {
                            mysqli_query(
                                $db_link,
                                "INSERT INTO `" . $pre . "misc`
                                (`valeur`, `type`, `intitule`)
                                VALUES ('" . IKEY . "', 'admin', 'duo_ikey')"
                            );
                        }

                        // HOST
                        $tmp = mysqli_query(
                            $db_link,
                            "SELECT INTO `" . $pre . "misc`
                            WHERE type = 'admin' AND intitule = 'duo_host'"
                        );
                        if ($tmp) {
                            mysqli_query(
                                $db_link,
                                "UPDATE `" . $pre . "misc`
                                set valeur = '" . HOST . "', type = 'admin', intitule = 'duo_host'"
                            );
                        } else {
                            mysqli_query(
                                $db_link,
                                "INSERT INTO `" . $pre . "misc`
                                (`valeur`, `type`, `intitule`)
                                VALUES ('" . HOST . "', 'admin', 'duo_host')"
                            );
                        }
                    }
                }

                // Ensure DB is read as UTF8
                if (DB_ENCODING === "") {
                    define('DB_ENCODING', "utf8");
                }

                // Now create new file
                $file_handled = fopen($settingsFile, 'w');

                $fileCreation = fwrite(
                    $file_handled,
                    utf8_encode(
                        '<?php
    // DATABASE connexion parameters
    define("DB_HOST", "' . DB_HOST . '");
    define("DB_USER", "' . DB_USER . '");
    define("DB_PASSWD", "' . defuse_return_decrypted(DB_PASSWD) . '");
    define("DB_NAME", "' . DB_NAME . '");
    define("DB_PREFIX", "' . DB_PREFIX . '");
    define("DB_PORT", "' . DB_PORT . '");
    define("DB_ENCODING", "' . DB_ENCODING . '");
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
    define("SECUREPATH", "' . SECUREPATH. '");

    if (isset($_SESSION[\'settings\'][\'timezone\']) === true) {
    date_default_timezone_set($_SESSION[\'settings\'][\'timezone\']);
    }
    '
                    )
                );

                fclose($file_handled);
                if ($fileCreation === false) {
                    array_push(
                        $returnStatus, 
                        array(
                            'id' => 'step5_settingFile', 
                            'html' => '<i class="far fa-times-circle fa-lg text-danger ml-2 mr-2"></i><span class="text-info font-italic">Setting.php file could not be created in /includes/config/ folder. Please check the path and the rights.</span>',
                        )
                    );
                } else {
                    array_push(
                        $returnStatus, 
                        array(
                            'id' => 'step5_settingFile', 
                            'html' => '<i class="fa-solid fa-circle-check fa-lg text-success ml-2"></i>',
                        )
                    );
                }
                // reload the file
                include '../includes/config/settings.php';

                // ensure the new constant is set
                define("DB_SSL", false);
                define("DB_CONNECT_OPTIONS", array(
                    MYSQLI_OPT_CONNECT_TIMEOUT => 10
                ));
            }

            // Manage saltkey.txt file
            if (empty($post_sk_path) === false || defined('SECUREPATH') === true) {
                array_push(
                    $returnStatus, 
                    array(
                        'id' => 'step5_saltkeyFile', 
                        'html' => '<i class="fa-solid fa-circle-check fa-lg text-success ml-2 mr-2"></i><span class="text-info font-italic">Nothing done</span>',
                    )
                );
            } else {
                array_push(
                    $returnStatus, 
                    array(
                        'id' => 'step5_saltkeyFile', 
                        'html' => '<i class="fa-solid fa-circle-check fa-lg text-success ml-2 mr-2"></i><span class="text-info font-italic">Nothing done</span>',
                    )
                );
            }

            // Do csrfp.config.php file
            $csrfp_file_sample = '../includes/libraries/csrfp/libs/csrfp.config.sample.php';
            if (file_exists($csrfp_file_sample) === true) {
                // update CSRFP TOKEN
                $csrfp_file = '../includes/libraries/csrfp/libs/csrfp.config.php';
                if (file_exists($csrfp_file) === true) {
                    if (
                        copy(
                            $csrfp_file,
                            $csrfp_file . '.' . date(
                                'Y_m_d_H_i_s',
                                mktime((int) date('H'), (int) date('i'), (int) date('s'), (int) date('m'), (int) date('d'), (int) date('y'))
                            )
                        ) === false
                    ) {
                        array_push(
                            $returnStatus, 
                            array(
                                'id' => 'step5_csrfpFile', 
                                'html' => '<i class="fas fa-times-circle fa-lg text-danger ml-2 mr-2"></i><span class="text-info font-italic">The file could not be renamed. Please rename it by yourself and restart operation.</span>',
                            )
                        );
                        break;
                    }
                }
                unlink($csrfp_file); // delete existing csrfp.config file
                copy($csrfp_file_sample, $csrfp_file); // make a copy of csrfp.config.sample file
                $data = file_get_contents('../includes/libraries/csrfp/libs/csrfp.config.php');
                $newdata = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "' . bin2hex(openssl_random_pseudo_bytes(25)) . '"', $data);
                $newdata = str_replace('"tokenLength" => "25"', '"tokenLength" => "50"', $newdata);
                $jsUrl = $post_url_path . '/includes/libraries/csrfp/js/csrfprotector.js';
                $newdata = str_replace('"jsUrl" => ""', '"jsUrl" => "' . $jsUrl . '"', $newdata);
                $newdata = str_replace('"verifyGetFor" => array()', '"verifyGetFor" => array("*page=items&type=duo_check*")', $newdata);
                file_put_contents('../includes/libraries/csrfp/libs/csrfp.config.php', $newdata);

                // Mark a tag to force Install stuff (folders, files and table) to be cleanup while first login
                mysqli_query(
                    $db_link,
                    'INSERT INTO `' . $pre . 'misc` (`type`, `intitule`, `valeur`) VALUES ("install", "clear_install_folder", "true")'
                );

                array_push(
                    $returnStatus, 
                    array(
                        'id' => 'step5_csrfpFile', 
                        'html' => '<i class="fa-solid fa-circle-check fa-lg text-success ml-2 mr-2"></i><span class="text-info font-italic">Nothing done</span>',
                    )
                );
            } else {
                array_push(
                    $returnStatus, 
                    array(
                        'id' => 'step5_csrfpFile', 
                        'html' => '<i class=\"fa-solid fa-circle-check fa-lg text-success ml-2 mr-2\"></i><span class=\"text-info font-italic\">Nothing done</span>',
                    )
                );
            }

            // update with correct version
            // 1st - check if teampass_version exists
            $data = mysqli_fetch_row(mysqli_query($db_link, "SELECT COUNT(*) FROM ".$pre . "misc WHERE type = 'admin' AND intitule = 'teampass_version';"));
            if ((int) $data[0] === 0) {
                // change variable name and put version
                mysqli_query(
                    $db_link,
                    "UPDATE `" . $pre . "misc`
                    SET `valeur` = '".TP_VERSION."', `intitule` = 'teampass_version'
                    WHERE intitule = 'cpassman_version' AND type = 'admin';"
                );
            } else {
                mysqli_query(
                    $db_link,
                    "UPDATE `" . $pre . "misc`
                    SET `valeur` = '".TP_VERSION."'
                    WHERE intitule = 'teampass_version' AND type = 'admin';"
                );
            }

            //<-- Add cronjob if not exist
            // get php location
            require_once 'tp.functions.php';
            $phpLocation = findPhpBinary();
            if ($phpLocation['error'] === false) {
                // Instantiate the adapter and repository
                try {
                    $crontabAdapter = new CrontabAdapter();
                    $crontabRepository = new CrontabRepository($crontabAdapter);
                    $results = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
                    if (count($results) === 0) {
                        // Add the job
                        $crontabJob = new CrontabJob();
                        $crontabJob
                            ->setMinutes('*')
                            ->setHours('*')
                            ->setDayOfMonth('*')
                            ->setMonths('*')
                            ->setDayOfWeek('*')
                            ->setTaskCommandLine($phpLocation['path'] . ' ' . $SETTINGS['cpassman_dir'] . '/sources/scheduler.php')
                            ->setComments('Teampass scheduler');
                        
                        $crontabRepository->addJob($crontabJob);
                        $crontabRepository->persist();

                        array_push(
                            $returnStatus, 
                            array(
                                'id' => 'step5_cronJob', 
                                'html' => '<i class="fa-solid fa-circle-check fa-lg text-success ml-2 mr-2"></i> <b>If you had manually defined a job in crontab then you should remove it now.</b>',
                            )
                        );
                    } else {
                        array_push(
                            $returnStatus, 
                            array(
                                'id' => 'step5_cronJob', 
                                'html' => '<i class="fa-solid fa-circle-check fa-lg text-success ml-2 mr-2"></i><span class="text-info font-italic">Nothing done</span>',
                            )
                        );
                    }
                } catch (Exception $e) {
                    // do nothing
                }
            } else {
                array_push(
                    $returnStatus, 
                    array(
                        'id' => 'step5_cronJob', 
                        'html' => '<i class="fa-solid fa-times-circle fa-lg text-danger ml-2 mr-2"></i> <b>The PHP path could not be found, please create manually the cron job (see documentation).</b>',
                    )
                );
            }

            
            //-->

            echo '[{'.
                '"error" : "",'.
                '"info" : "'.base64_encode(json_encode($returnStatus)).'",'.
                '"index" : ""'.
            '}]';

            break;

        case 'perform_database_dump':
            $filename = '../includes/config/settings.php';

            include_once '../sources/main.functions.php';
            $pass = defuse_return_decrypted($pass);

            $mtables = array();

            $mysqli = new mysqli($server, $user, $pass, $database, $port);
            if ($mysqli->connect_error) {
                die('Error : (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
            }

            $results = $mysqli->query('SHOW TABLES');

            while ($row = $results->fetch_array()) {
                $mtables[] = $row[0];
            }

            // Prepare file
            $backup_file_name = 'sql-backup-' . date('d-m-Y--h-i-s') . '.sql';
            $fp = fopen('../files/' . $backup_file_name, 'a');

            foreach ($mtables as $table) {
                $contents = '-- Table `' . $table . "` --\n";
                if (fwrite($fp, $contents) === false) {
                    echo '[{'.
                        '"error" : "Backup fails - please do it manually",'.
                        '"index" : ""'.
                    '}]';
                    fclose($fp);
                    return false;
                }

                $results = $mysqli->query('SHOW CREATE TABLE ' . $table);
                while ($row = $results->fetch_array()) {
                    $contents = $row[1] . ";\n\n";
                    if (fwrite($fp, $contents) === false) {
                        echo '[{'.
                            '"error" : "Backup fails - please do it manually",'.
                            '"index" : ""'.
                        '}]';
                        fclose($fp);
                        return false;
                    }
                }

                $results = $mysqli->query('SELECT * FROM ' . $table);
                $row_count = $results->num_rows;
                $fields = $results->fetch_fields();
                $fields_count = count($fields);

                $insert_head = 'INSERT INTO `' . $table . '` (';
                for ($i = 0; $i < $fields_count; ++$i) {
                    $insert_head .= '`' . $fields[$i]->name . '`';
                    if ($i < $fields_count - 1) {
                        $insert_head .= ', ';
                    }
                }
                $insert_head .= ')';
                $insert_head .= " VALUES\n";

                if ($row_count > 0) {
                    $r = 0;
                    while ($row = $results->fetch_array()) {
                        if (($r % 400) == 0) {
                            //$contents .= $insert_head;
                            if (fwrite($fp, $insert_head) === false) {
                                echo '[{'.
                                    '"error" : "Backup fails - please do it manually",'.
                                    '"index" : ""'.
                                '}]';
                                fclose($fp);
                                return false;
                            }
                        }
                        //$contents .= '(';
                        if (fwrite($fp, '(') === false) {
                            echo '[{'.
                                '"error" : "Backup fails - please do it manually",'.
                                '"index" : ""'.
                            '}]';
                            fclose($fp);
                            return false;
                        }
                        for ($i = 0; $i < $fields_count; ++$i) {
                            $row_content = str_replace("\n", '\\n', $mysqli->real_escape_string($row[$i]));

                            switch ($fields[$i]->type) {
                                case 8:
                                case 3:
                                    //$contents .= $row_content;
                                    if (fwrite($fp, $row_content) === false) {
                                        echo '[{'.
                                            '"error" : "Backup fails - please do it manually",'.
                                            '"index" : ""'.
                                        '}]';
                                        fclose($fp);
                                        return false;
                                    }
                                    break;
                                default:
                                    //$contents .= "'".$row_content."'";
                                    if (fwrite($fp, "'" . $row_content . "'") === false) {
                                        echo '[{'.
                                            '"error" : "Backup fails - please do it manually",'.
                                            '"index" : ""'.
                                        '}]';
                                        fclose($fp);
                                        return false;
                                    }
                            }
                            if ($i < $fields_count - 1) {
                                //$contents .= ', ';
                                if (fwrite($fp, ', ') === false) {
                                    echo '[{'.
                                        '"error" : "Backup fails - please do it manually",'.
                                        '"index" : ""'.
                                    '}]';
                                    fclose($fp);
                                    return false;
                                }
                            }
                        }
                        if (($r + 1) == $row_count || ($r % 400) == 399) {
                            //$contents .= ");\n\n";
                            if (fwrite($fp, ");\n\n") === false) {
                                echo '[{'.
                                    '"error" : "Backup fails - please do it manually",'.
                                    '"index" : ""'.
                                '}]';
                                fclose($fp);
                                return false;
                            }
                        } else {
                            //$contents .= "),\n";
                            if (fwrite($fp, "),\n") === false) {
                                echo '[{'.
                                    '"error" : "Backup fails - please do it manually",'.
                                    '"index" : ""'.
                                '}]';
                                fclose($fp);
                                return false;
                            }
                        }
                        ++$r;
                    }
                }
            }

            fclose($fp);
			
            echo '[{'.
                '"error" : "",'.
                '"filename" : "'.$backup_file_name.'",'.
                '"index" : ""'.
            '}]';

            break;

        case 'perform_nestedtree_categories_population_3.0.0.18':
            include_once 'upgrade_operations.php';
            installHandleFoldersCategories(
                [],
                $pre
            );

            echo '[{"error" : ""}]';

            break;
    
    }
}
//