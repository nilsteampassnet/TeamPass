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
 * @file      install.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
require '../vendor/autoload.php';
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception as CryptoException;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Encryption\Crypt\aesctr;

// Do initial test
// Ensure that the file settings.php exists
if (file_exists('../includes/config/settings.php') === false) {
    $settings_sample = 'includes/config/settings.sample.php';
    $settings = 'includes/config/settings.php';
    if (copy('../'.$settings_sample, '../'.$settings) === false) {
        echo '[{"error" : "File <i>' . $settings . '</i> could not be copied from <i>'.$settings_sample.'</i>. You have 2 possible actions:<br>'.
            '1- Manually perform a copy of file <i>' . $settings_sample . '</i> and rename it as <i>'.$settings.'</i>.<br>'.
            'or 2- Change the user rights to 0755 on <i>includes/config/</i> and its content.<br>'.
            'Then click START button.", "index" : "99", "multiple" : "' . $inputData['multiple'] . '"}]';
        exit();
    }

    $SECUREPATH = __DIR__.'/../includes/config';
    $SECUREFILE = generateRandomKey();

    // 1- generate saltkey
    $key = Key::createNewRandomKey();
    $new_salt = $key->saveToAsciiSafeString();

    // 2- store key in file
    file_put_contents(
        $SECUREPATH.'/'.$SECUREFILE,
        $new_salt
    );

    //3 - add to settings
    $newLine = '
define("SECUREPATH", "' . $SECUREPATH. '");
define("SECUREFILE", "' . $SECUREFILE. '");
';
    file_put_contents('../includes/config/settings.php', $newLine, FILE_APPEND);
}

// Load settings
include_once '../includes/config/settings.php';

// Reset SESSION
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 10, '/', '', false, true);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$superGlobal = new SuperGlobal();
$lang = new Language(); 

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR | E_PARSE);
// increase the maximum amount of time a script is allowed to run
set_time_limit(600);
$session_db_encoding = 'utf8';
define('MIN_PHP_VERSION', 8.1);

/**
 * Generates a random key.
 */
function generateRandomKey()
{
    $generator = new ComputerPasswordGenerator();
    $generator->setRandomGenerator(new Php7RandomGenerator());
    $generator->setLength(40);
    $generator->setSymbols(false);
    $generator->setLowercase(true);
    $generator->setUppercase(true);
    $generator->setNumbers(true);

    $key = $generator->generatePasswords();

    return $key[0];
}

/**
 * Permits to encrypt a message using Defuse.
 *
 * @param string $message   Message to encrypt
 * @param string $ascii_key Key to hash
 *
 * @return array String + Error
 */
function encryptFollowingDefuse($message, $ascii_key)
{
    // convert KEY
    $key = Key::loadFromAsciiSafeString($ascii_key);

    try {
        $text = Crypto::encrypt($message, $key);
    } catch (CryptoException\WrongKeyOrModifiedCiphertextException $ex) {
        $err = 'an attack! either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
    } catch (CryptoException\BadFormatException $ex) {
        $err = $ex;
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (CryptoException\CryptoException $ex) {
        $err = $ex;
    } catch (CryptoException\IOException $ex) {
        $err = $ex;
    }

    return array(
        'string' => isset($text) ? $text : '',
        'error' => $err,
    );
}


// Prepare SESSION variables
$session_url_path = $superGlobal->get('url_path', 'SESSION');
$session_abspath = $superGlobal->get('absolute_path', 'SESSION');
$session_db_encoding = $superGlobal->get('db_encoding', 'SESSION');
if (empty($session_db_encoding) === true) {
    $session_db_encoding = 'utf8';
}
$superGlobal->put('CPM', 1, 'SESSION');

// Prepare POST variables
$postValues = [
    'type' => filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    'data' => filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES),
    'activity' => filter_input(INPUT_POST, 'activity', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    'task' => filter_input(INPUT_POST, 'task', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    'index' => filter_input(INPUT_POST, 'index', FILTER_SANITIZE_NUMBER_INT),
    'multiple' => filter_input(INPUT_POST, 'multiple', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    'db' => filter_input(INPUT_POST, 'db', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
    'skFile' => filter_input(INPUT_POST, 'skFile', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
];

// Decrypt POST values
require_once 'libs/aesctr.php';
foreach ($postValues as $key => $value) {
    if ($key === 'data' || $key === 'activity' || $key === 'task' || $key === 'db' || $key === 'skFile') {
        $postValues[$key] = aesctr::decrypt($value, 'cpm', 128);
    }
}

$inputData['data'] = [
    'type' => isset($postValues['type']) === true ? $postValues['type'] : '',
    'data' => isset($postValues['data']) === true ? $postValues['data'] : '',
    'activity' => isset($postValues['activity']) === true ? $postValues['activity'] : '',
    'task' => isset($postValues['task']) === true ? $postValues['task'] : '',
    'index' => isset($postValues['index']) === true ? (int) $postValues['index'] : 0,
    'multiple' => isset($postValues['multiple']) === true ? $postValues['multiple'] : '',
    'db' => isset($postValues['db']) === true ? $postValues['db'] : '',
    'skFile' => isset($postValues['skFile']) === true ? $postValues['skFile'] : '',
];
$filters = [
    'type' => 'trim|escape',
    'data' => 'cast:array',
    'activity' => 'trim|escape',
    'task' => 'trim|escape',
    'index' => 'cast:integer',
    'multiple' => 'trim|escape',
    'db' => 'cast:array',
    'skFile' => 'cast:array',
];
$inputData = dataSanitizer(
    $inputData['data'],
    $filters,
    $session_abspath
);

// Prepare variables
$session_abspath = rtrim($inputData['data']['absolute_path'], '/');
$session_url_path = rtrim($inputData['data']['url_path'], '/');
$session_sk_path = rtrim($inputData['skFile']['sk_path'], '/');
$session_sk_filename = $inputData['skFile']['sk_filename'];
$session_sk_key = $inputData['skFile']['sk_key'];

if (null !== $inputData['type']) {
    switch ($inputData['type']) {
        case 'step_2':   
            // Check FOLDERS
            if (isset($inputData['activity']) && $inputData['activity'] === 'folder') {
                // Handle specific case of "secure path"
                if ($inputData['task'] === 'secure') {
                    // Is SK path a folder?
                    if (!is_dir($session_sk_path)) {
                        echo '[{"error" : " Path ' . $session_sk_path . ' is not a folder!", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                        break;
                    }

                    // Is SK path writable?
                    if (is_writable($session_sk_path) === false) {
                        echo '[{"error" : " Path ' . $session_sk_path . ' is not writable!", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                        break;
                    }         

                    // Handle the SK file to correct folder
                    $filename_seckey = $session_sk_path . '/' . SECUREFILE;
        
                    if (!file_exists($filename_seckey)) {
                        // Move file
                        if (!copy(__DIR__.'/../includes/config/'.SECUREFILE, $filename_seckey)) {
                            echo '[{"error" : "File `'.__DIR__.'/../includes/config/'.SECUREFILE.'` could not be copied to `'.$filename_seckey.'`. Please check the path and the rights", "result":"", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                            break;
                        }
                    }
                    define('SECUREPATH', $session_sk_path);
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                } else {
                    $targetPath = $session_abspath . '/' . $inputData['task'] . '/';
                    if (is_writable($targetPath) === true) {
                        echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                    } else {
                        echo '[{"error" : " Path ' . $targetPath . ' is not writable!", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                    }
                }
                break;
            }

            // Check EXTENSIONS
            if (isset($inputData['activity']) && $inputData['activity'] === 'extension') {
                if (extension_loaded($inputData['task'])) {
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                } else {
                    echo '[{"error" : " Extension ' . $inputData['task'] . ' is not loaded!", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                }
                break;
            }

            // Check FUNCTION
            if (isset($inputData['activity']) && $inputData['activity'] === 'function') {
                if (function_exists($inputData['task'])) {
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                } else {
                    echo '[{"error" : " Function ' . $inputData['task'] . ' is not available!", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                }
                break;
            }

            // Check VERSIONS
            if (isset($inputData['activity']) && $inputData['activity'] === 'version') {
                if (version_compare(phpversion(), MIN_PHP_VERSION, '>=')) {
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                } else {
                    echo '[{"error" : "PHP version ' . phpversion() . ' is not OK (minimum is '.MIN_PHP_VERSION.')", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                }
                break;
            }

            // Check INI
            if (isset($inputData['activity']) && $inputData['activity'] === 'ini') {
                if (ini_get($inputData['task']) >= 30) {
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '"}]';
                } else {
                    echo '[{"error" : "PHP \"Maximum execution time\" is set to ' . ini_get('max_execution_time') . ' seconds. Please try to set to 30s at least during installation.", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                }
                break;
            }

            break;

        case 'step_3':            
            // launch
            try {
                $dbTmp = mysqli_connect(
                    $inputData['db']['db_host'], 
                    $inputData['db']['db_login'], 
                    $inputData['db']['db_pw'], 
                    $inputData['db']['db_bdd'], 
                    $inputData['db']['db_port']
                );
            } catch (Exception $e) {
                echo '[{"error" : "Cannot connect to Database - '.$e->getMessage().'"}]';
                break;
            }
            
            if ($dbTmp) {
                // create temporary INSTALL mysqli table
                $mysqli_result = mysqli_query(
                    $dbTmp,
                    'CREATE TABLE IF NOT EXISTS `_install` (
                    `key` varchar(100) NOT NULL,
                    `value` varchar(500) NOT NULL,
                    PRIMARY KEY (`key`)
                    ) CHARSET=utf8;'
                );
                
                // store values
                foreach ($inputData['data'] as $key => $value) {
                    // Escape values to prevent SQL injections
                    $escapedKey = mysqli_real_escape_string($dbTmp, $key);
                    $escapedValue = mysqli_real_escape_string($dbTmp, $value);

                    $superGlobal->put($key, $value, 'SESSION');
                    $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = '" . $escapedKey . "'"));
                    if (intval($tmp) === 0) {
                        mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('" . $escapedKey . "', '" . $escapedValue . "');");
                    } else {
                        mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '" . $escapedValue . "' WHERE `key` = '" . $escapedKey . "';");
                    }
                }

                // For other queries with `url_path` and `absolute_path`
                $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = 'url_path'"));
                if (intval($tmp) === 0) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('url_path', '" . mysqli_real_escape_string($dbTmp, $session_url_path) . "');");
                }

                $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = 'absolute_path'"));
                if (intval($tmp) === 0) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('absolute_path', '" . mysqli_real_escape_string($dbTmp, $session_abspath) . "');");
                }
                
                echo '[{"error" : "", "result" : "Connection is successful", "multiple" : ""}]';
            } else {
                echo '[{"error" : "' . addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_connect_error())) . '", "result" : "Failed", "multiple" : ""}]';
            }
            mysqli_close($dbTmp);
            break;

        case 'step_4':          
            // launch
            try {
                $dbTmp = mysqli_connect(
                    $inputData['db']['db_host'], 
                    $inputData['db']['db_login'], 
                    $inputData['db']['db_pw'], 
                    $inputData['db']['db_bdd'], 
                    $inputData['db']['db_port']
                );
            } catch (Exception $e) {
                echo '[{"error" : "Cannot connect to Database - '.$e->getMessage().'"}]';
                break;
            }
            
            // prepare data
            foreach ($inputData['data'] as $key => $value) {
                $escapedKey = mysqli_real_escape_string($dbTmp, $key);
                $escapedValue = mysqli_real_escape_string($dbTmp, str_replace(array('&quot;', '&#92;'), array('""', '\\\\'), $value));
                $inputData['data'][$escapedKey] = $escapedValue;
            }

            // store all variables in SESSION
            foreach ($inputData['data'] as $key => $value) {
                $superGlobal->put($key, $value, 'SESSION');
        
                // Use mysqli_real_escape_string to escape keys and values
                $escapedKey = mysqli_real_escape_string($dbTmp, $key);
                $escapedValue = mysqli_real_escape_string($dbTmp, $value);

                $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = '" . $escapedKey . "'"));
                if (intval($tmp) === 0) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('" . $escapedKey . "', '" . $escapedValue . "');");
                } else {
                    mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '" . $escapedValue . "' WHERE `key` = '" . $escapedKey . "';");
                }
            }
            mysqli_close($dbTmp);

            echo '[{"error" : "", "result" : "Information stored", "multiple" : ""}]';            

            break;

        case 'step_5':
            // launch
            $dbTmp = mysqli_connect(
                $inputData['db']['db_host'], 
                $inputData['db']['db_login'], 
                $inputData['db']['db_pw'], 
                $inputData['db']['db_bdd'], 
                $inputData['db']['db_port']
            );
            $dbBdd = $inputData['db']['db_bdd'];
            if ($dbTmp) {
                $mysqli_result = '';

                // read install variables
                $result = mysqli_query($dbTmp, 'SELECT * FROM `_install`');
                while ($row = $result->fetch_array()) {
                    $var[$row[0]] = $row[1];
                }
                
                if ($inputData['activity'] === 'table') {
                    if ($inputData['task'] === 'utf8') {
                        //FORCE UTF8 DATABASE
                        mysqli_query($dbTmp, 'ALTER DATABASE `' . $dbBdd . '` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci');
                    } elseif ($inputData['task'] === 'defuse_passwords') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'defuse_passwords` (
								`increment_id` int(12) NOT NULL AUTO_INCREMENT,
								`type` varchar(100) NOT NULL,
								`object_id` int(12) NOT NULL,
								`password` text NOT NULL,
								PRIMARY KEY (`increment_id`)
							) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'notification') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'notification` (
								`increment_id` INT(12) NOT NULL AUTO_INCREMENT,
								`item_id` INT(12) NOT NULL,
								`user_id` INT(12) NOT NULL,
								PRIMARY KEY (`increment_id`)
							) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'sharekeys_items') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'sharekeys_items` (
								`increment_id` int(12) NOT NULL AUTO_INCREMENT,
								`object_id` int(12) NOT NULL,
								`user_id` int(12) NOT NULL,
								`share_key` text NOT NULL,
								PRIMARY KEY (`increment_id`),
                                INDEX idx_object_user (`object_id`, `user_id`)
							) CHARSET=utf8;'
                        );
                        
                        // Requête pour vérifier si les clés existent
                        $keyCheckQuery = "
                        SELECT COUNT(1) as key_exists 
                        FROM information_schema.STATISTICS 
                        WHERE table_schema = DATABASE() 
                        AND table_name = '" . $var['tbl_prefix'] . "sharekeys_items' 
                        AND index_name IN ('object_id_idx', 'user_id_idx')
                        ";
                        $result = mysqli_query($dbTmp, $keyCheckQuery);
                        if ($result) {
                            $row = mysqli_fetch_assoc($result);
                            if ($row['key_exists'] == 0) {
                                // Les clés n'existent pas, exécutez la requête ALTER TABLE
                                $alterQuery = "
                                    ALTER TABLE `" . $var['tbl_prefix'] . "sharekeys_items` 
                                        ADD KEY `object_id_idx` (`object_id`),
                                        ADD KEY `user_id_idx` (`user_id`);
                                ";
                            }
                        }
                    } elseif ($inputData['task'] === 'sharekeys_logs') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'sharekeys_logs` (
								`increment_id` int(12) NOT NULL AUTO_INCREMENT,
								`object_id` int(12) NOT NULL,
								`user_id` int(12) NOT NULL,
								`share_key` text NOT NULL,
								PRIMARY KEY (`increment_id`)
							) CHARSET=utf8;'
                        );
                        // Requête pour vérifier si les clés existent
                        $keyCheckQuery = "
                        SELECT COUNT(1) as key_exists 
                        FROM information_schema.STATISTICS 
                        WHERE table_schema = DATABASE() 
                        AND table_name = '" . $var['tbl_prefix'] . "sharekeys_logs' 
                        AND index_name IN ('object_id_idx', 'user_id_idx')
                        ";
                        $result = mysqli_query($dbTmp, $keyCheckQuery);
                        if ($result) {
                            $row = mysqli_fetch_assoc($result);
                            if ($row['key_exists'] == 0) {
                                // Les clés n'existent pas, exécutez la requête ALTER TABLE
                                $alterQuery = "
                                    ALTER TABLE `" . $var['tbl_prefix'] . "sharekeys_logs` 
                                        ADD KEY `object_id_idx` (`object_id`),
                                        ADD KEY `user_id_idx` (`user_id`);
                                ";
                            }
                        }
                    } elseif ($inputData['task'] === 'sharekeys_fields') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'sharekeys_fields` (
								`increment_id` int(12) NOT NULL AUTO_INCREMENT,
								`object_id` int(12) NOT NULL,
								`user_id` int(12) NOT NULL,
								`share_key` text NOT NULL,
								PRIMARY KEY (`increment_id`)
							) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'sharekeys_suggestions') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'sharekeys_suggestions` (
								`increment_id` int(12) NOT NULL AUTO_INCREMENT,
								`object_id` int(12) NOT NULL,
								`user_id` int(12) NOT NULL,
								`share_key` text NOT NULL,
								PRIMARY KEY (`increment_id`)
							) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'sharekeys_files') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'sharekeys_files` (
								`increment_id` int(12) NOT NULL AUTO_INCREMENT,
								`object_id` int(12) NOT NULL,
								`user_id` int(12) NOT NULL,
								`share_key` text NOT NULL,
								PRIMARY KEY (`increment_id`)
							) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'items') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "items` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `label` varchar(500) NOT NULL,
                            `description` text DEFAULT NULL,
                            `pw` text DEFAULT NULL,
                            `pw_iv` text DEFAULT NULL,
                            `pw_len` int(5) NOT NULL DEFAULT '0',
                            `url` text DEFAULT NULL,
                            `id_tree` varchar(10) DEFAULT NULL,
                            `perso` tinyint(1) NOT null DEFAULT '0',
                            `login` varchar(200) DEFAULT NULL,
                            `inactif` tinyint(1) NOT null DEFAULT '0',
                            `restricted_to` varchar(200) DEFAULT NULL,
                            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                            `email` varchar(100) DEFAULT NULL,
                            `notification` varchar(250) DEFAULT NULL,
                            `viewed_no` int(12) NOT null DEFAULT '0',
                            `complexity_level` varchar(3) NOT null DEFAULT '-1',
                            `auto_update_pwd_frequency` tinyint(2) NOT null DEFAULT '0',
                            `auto_update_pwd_next_date` varchar(100) NOT null DEFAULT '0',
                            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
                            `fa_icon` varchar(100) DEFAULT NULL,
                            `item_key` varchar(500) NOT NULL DEFAULT '-1',
                            `created_at` varchar(30) NULL,
                            `updated_at` varchar(30) NULL,
                            `deleted_at` varchar(30) NULL,
                            PRIMARY KEY (`id`),
                            KEY `restricted_inactif_idx` (`restricted_to`,`inactif`),
                            INDEX items_perso_id_idx (`perso`, `id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'log_items') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "log_items` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `id_item` int(8) NOT NULL,
                            `date` varchar(50) NOT NULL,
                            `id_user` int(8) NOT NULL,
                            `action` varchar(250) NULL,
                            `raison` text NULL,
                            `old_value` MEDIUMTEXT NULL DEFAULT NULL,
                            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
                            PRIMARY KEY (`increment_id`),
                            INDEX log_items_item_action_user_idx (`id_item`, `action`, `id_user`)
                            ) CHARSET=utf8;"
                        );
                        // create index
                        mysqli_query(
                            $dbTmp,
                            'CREATE INDEX teampass_log_items_id_item_IDX ON ' . $var['tbl_prefix'] . 'log_items (id_item,date);'
                        );
                    } elseif ($inputData['task'] === 'misc') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "misc` (
                            `increment_id` int(12) NOT null AUTO_INCREMENT,
                            `type` varchar(50) NOT NULL,
                            `intitule` varchar(100) NOT NULL,
                            `valeur` varchar(500) NOT NULL,
                            `created_at` varchar(255) NULL DEFAULT NULL,
                            `updated_at` varchar(255) NULL DEFAULT NULL,
                            `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );

                        // include constants
                        require_once '../includes/config/include.php';

                        // add by default settings
                        $aMiscVal = array(
                            array('admin', 'max_latest_items', '10'),
                            array('admin', 'enable_favourites', '1'),
                            array('admin', 'show_last_items', '1'),
                            array('admin', 'enable_pf_feature', '0'),
                            array('admin', 'log_connections', '1'),
                            array('admin', 'log_accessed', '1'),
                            array('admin', 'time_format', 'H:i:s'),
                            array('admin', 'date_format', 'd/m/Y'),
                            array('admin', 'duplicate_folder', '0'),
                            array('admin', 'item_duplicate_in_same_folder', '0'),
                            array('admin', 'duplicate_item', '0'),
                            array('admin', 'number_of_used_pw', '3'),
                            array('admin', 'manager_edit', '1'),
                            array('admin', 'cpassman_dir', $var['absolute_path']),
                            array('admin', 'cpassman_url', $var['url_path']),
                            array('admin', 'favicon', $var['url_path'] . '/favicon.ico'),
                            array('admin', 'path_to_upload_folder', $var['absolute_path'] . '/upload'),
                            array('admin', 'path_to_files_folder', $var['absolute_path'] . '/files'),
                            array('admin', 'url_to_files_folder', $var['url_path'] . '/files'),
                            array('admin', 'activate_expiration', '0'),
                            array('admin', 'pw_life_duration', '0'),
                            array('admin', 'maintenance_mode', '1'),
                            array('admin', 'enable_sts', '0'),
                            array('admin', 'encryptClientServer', '1'),
                            array('admin', 'teampass_version', TP_VERSION),
                            array('admin', 'ldap_mode', '0'),
                            array('admin', 'ldap_type', '0'),
                            array('admin', 'ldap_user_attribute', '0'),
                            array('admin', 'ldap_ssl', '0'),
                            array('admin', 'ldap_tls', '0'),
                            array('admin', 'ldap_port', '389'),
                            array('admin', 'richtext', '0'),
                            array('admin', 'allow_print', '0'),
                            array('admin', 'roles_allowed_to_print', '0'),
                            array('admin', 'show_description', '1'),
                            array('admin', 'anyone_can_modify', '0'),
                            array('admin', 'anyone_can_modify_bydefault', '0'),
                            array('admin', 'nb_bad_authentication', '0'),
                            array('admin', 'utf8_enabled', '1'),
                            array('admin', 'restricted_to', '0'),
                            array('admin', 'restricted_to_roles', '0'),
                            array('admin', 'enable_send_email_on_user_login', '0'),
                            array('admin', 'enable_user_can_create_folders', '0'),
                            array('admin', 'insert_manual_entry_item_history', '0'),
                            array('admin', 'enable_kb', '0'),
                            array('admin', 'enable_email_notification_on_item_shown', '0'),
                            array('admin', 'enable_email_notification_on_user_pw_change', '0'),
                            array('admin', 'custom_logo', ''),
                            array('admin', 'custom_login_text', ''),
                            array('admin', 'default_language', 'english'),
                            array('admin', 'send_stats', '0'),
                            array('admin', 'send_statistics_items', 'stat_country;stat_users;stat_items;stat_items_shared;stat_folders;stat_folders_shared;stat_admins;stat_managers;stat_ro;stat_mysqlversion;stat_phpversion;stat_teampassversion;stat_languages;stat_kb;stat_suggestion;stat_customfields;stat_api;stat_2fa;stat_agses;stat_duo;stat_ldap;stat_syslog;stat_stricthttps;stat_fav;stat_pf;'),
                            array('admin', 'send_stats_time', time() - 2592000),
                            array('admin', 'get_tp_info', '1'),
                            array('admin', 'send_mail_on_user_login', '0'),
                            array('cron', 'sending_emails', '0'),
                            array('admin', 'nb_items_by_query', 'auto'),
                            array('admin', 'enable_delete_after_consultation', '0'),
                            array('admin', 'enable_personal_saltkey_cookie', '0'),
                            array('admin', 'personal_saltkey_cookie_duration', '31'),
                            array('admin', 'email_smtp_server', ''),
                            array('admin', 'email_smtp_auth', ''),
                            array('admin', 'email_auth_username', '', '1'),
                            array('admin', 'email_auth_pwd', '', '1'),
                            array('admin', 'email_port', ''),
                            array('admin', 'email_security', ''),
                            array('admin', 'email_server_url', ''),
                            array('admin', 'email_from', ''),
                            array('admin', 'email_from_name', ''),
                            array('admin', 'pwd_maximum_length', '40'),
                            array('admin', 'google_authentication', '0'),
                            array('admin', 'delay_item_edition', '0'),
                            array('admin', 'allow_import', '0'),
                            array('admin', 'proxy_ip', ''),
                            array('admin', 'proxy_port', ''),
                            array('admin', 'upload_maxfilesize', '10mb'),
                            array('admin', 'upload_docext', 'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx'),
                            array('admin', 'upload_imagesext', 'jpg,jpeg,gif,png'),
                            array('admin', 'upload_pkgext', '7z,rar,tar,zip'),
                            array('admin', 'upload_otherext', 'sql,xml'),
                            array('admin', 'upload_imageresize_options', '1'),
                            array('admin', 'upload_imageresize_width', '800'),
                            array('admin', 'upload_imageresize_height', '600'),
                            array('admin', 'upload_imageresize_quality', '90'),
                            array('admin', 'use_md5_password_as_salt', '0'),
                            array('admin', 'ga_website_name', 'TeamPass for ChangeMe'),
                            array('admin', 'api', '0'),
                            array('admin', 'subfolder_rights_as_parent', '0'),
                            array('admin', 'show_only_accessible_folders', '0'),
                            array('admin', 'enable_suggestion', '0'),
                            array('admin', 'otv_expiration_period', '7'),
                            array('admin', 'default_session_expiration_time', '60'),
                            array('admin', 'duo', '0'),
                            array('admin', 'enable_server_password_change', '0'),
                            array('admin', 'bck_script_path', $var['absolute_path'] . '/backups'),
                            array('admin', 'bck_script_filename', 'bck_teampass', '1'),
                            array('admin', 'syslog_enable', '0'),
                            array('admin', 'syslog_host', 'localhost'),
                            array('admin', 'syslog_port', '514'),
                            array('admin', 'manager_move_item', '0'),
                            array('admin', 'create_item_without_password', '0'),
                            array('admin', 'otv_is_enabled', '0'),
                            array('admin', 'agses_authentication_enabled', '0'),
                            array('admin', 'item_extra_fields', '0'),
                            array('admin', 'saltkey_ante_2127', 'none'),
                            array('admin', 'migration_to_2127', 'done'),
                            array('admin', 'files_with_defuse', 'done'),
                            array('admin', 'timezone', 'UTC'),
                            array('admin', 'enable_attachment_encryption', '1'),
                            array('admin', 'personal_saltkey_security_level', '50'),
                            array('admin', 'ldap_new_user_is_administrated_by', '0'),
                            array('admin', 'disable_show_forgot_pwd_link', '0'),
                            array('admin', 'offline_key_level', '0'),
                            array('admin', 'enable_http_request_login', '0'),
                            array('admin', 'ldap_and_local_authentication', '0'),
                            array('admin', 'secure_display_image', '1'),
                            array('admin', 'upload_zero_byte_file', '0'),
                            array('admin', 'upload_all_extensions_file', '0'),
                            array('admin', 'bck_script_passkey', '', '1'),
                            array('admin', 'admin_2fa_required', '1'),
                            array('admin', 'password_overview_delay', '4'),
                            array('admin', 'copy_to_clipboard_small_icons', '1'),
                            array('admin', 'duo_ikey', '', '1'),
                            array('admin', 'duo_skey', '', '1'),
                            array('admin', 'duo_host', '', '1'),
                            array('admin', 'duo_failmode', 'secure'),
                            array('admin', 'roles_allowed_to_print_select', ''),
                            array('admin', 'clipboard_life_duration', '30'),
                            array('admin', 'mfa_for_roles', ''),
                            array('admin', 'tree_counters', '0'),
                            array('admin', 'settings_offline_mode', '0'),
                            array('admin', 'settings_tree_counters', '0'),
                            array('admin', 'enable_massive_move_delete', '0'),
                            array('admin', 'email_debug_level', '0'),
                            array('admin', 'ga_reset_by_user', ''),
                            array('admin', 'onthefly-backup-key', ''),
                            array('admin', 'onthefly-restore-key', ''),
                            array('admin', 'ldap_user_dn_attribute', ''),
                            array('admin', 'ldap_dn_additional_user_dn', ''),
                            array('admin', 'ldap_user_object_filter', ''),
                            array('admin', 'ldap_bdn', '', '1'),
                            array('admin', 'ldap_hosts', '', '1'),
                            array('admin', 'ldap_password', '', '1'),
                            array('admin', 'ldap_username', ''),
                            array('admin', 'api_token_duration', '60'),
                            array('timestamp', 'last_folder_change', ''),
                            array('admin', 'enable_tasks_manager', '1'),
                            array('admin', 'task_maximum_run_time', '300'),
                            array('admin', 'tasks_manager_refreshing_period', '20'),
                            array('admin', 'maximum_number_of_items_to_treat', '100'),
                            array('admin', 'number_users_build_cache_tree', '10'),
                            array('admin', 'ldap_tls_certifacte_check', 'LDAP_OPT_X_TLS_NEVER'),
                            array('admin', 'enable_tasks_log', '0'),
                            array('admin', 'upgrade_timestamp', time()),
                            array('admin', 'enable_ad_users_with_ad_groups', '0'),
                            array('admin', 'enable_ad_user_auto_creation', '0'),
                            array('admin', 'ldap_guid_attibute', 'objectguid'),
                            array('admin', 'sending_emails_job_frequency', '2'),
                            array('admin', 'user_keys_job_frequency', '1'),
                            array('admin', 'items_statistics_job_frequency', '5'),
                            array('admin', 'users_personal_folder_task', ''),
                            array('admin', 'clean_orphan_objects_task', ''),
                            array('admin', 'purge_temporary_files_task', ''),
                            array('admin', 'rebuild_config_file', ''),
                            array('admin', 'reload_cache_table_task', ''),
                            array('admin', 'maximum_session_expiration_time', '60'),
                            array('admin', 'items_ops_job_frequency', '1'),
                            array('admin', 'enable_refresh_task_last_execution', '1'),
                            array('admin', 'ldap_group_objectclasses_attibute', 'top,groupofuniquenames'),
                            array('admin', 'pwd_default_length', '14'),
                            array('admin', 'tasks_log_retention_delay', '30'),
                            array('admin', 'oauth2_enabled', '0'),
                            array('admin', 'oauth2_client_id', '', '1'),
                            array('admin', 'oauth2_client_secret', '', '1'),
                            array('admin', 'oauth2_client_endpoint', ''),
                            array('admin', 'oauth2_client_urlResourceOwnerDetails', ''),
                            array('admin', 'oauth2_client_token', '', '1'),
                            array('admin', 'oauth2_client_scopes', 'openid,profile,email,User.Read,Group.Read.All'),
                            array('admin', 'oauth2_client_appname', 'Login with Azure'),
                            array('admin', 'show_item_data', '0'),
                            array('admin', 'oauth2_tenant_id', '', '1'),
                            array('admin', 'limited_search_default', '0'),
                            array('admin', 'highlight_selected', '0'),
                            array('admin', 'highlight_favorites', '0'),
                        );
                        foreach ($aMiscVal as $elem) {
                            //Check if exists before inserting
                            $tmp = mysqli_num_rows(
                                mysqli_query(
                                    $dbTmp,
                                    "SELECT * FROM `" . $var['tbl_prefix'] . "misc`
                                    WHERE type='" . $elem[0] . "' AND intitule='" . $elem[1] . "'"
                                )
                            );
                            if (intval($tmp) === 0) {
                                $value = isset($elem[3]) ? $elem[3] : 0;
                                $queryRes = mysqli_query(
                                    $dbTmp,
                                    "INSERT INTO `" . $var['tbl_prefix'] . "misc`
                                    (`type`, `intitule`, `valeur`, `created_at`, `is_encrypted`) VALUES
                                    ('" . $elem[0] . "', '" . $elem[1] . "', '" .
                                        str_replace("'", '', $elem[2]) . "', '" . $elem[1] . "', '" . $value . "');"
                                );
                            }
                        }

                        // --
                    } elseif ($inputData['task'] === 'nested_tree') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "nested_tree` (
                            `id` bigint(20) unsigned NOT null AUTO_INCREMENT,
                            `parent_id` int(11) NOT NULL,
                            `title` varchar(255) NOT NULL,
                            `nleft` int(11) NOT NULL DEFAULT '0',
                            `nright` int(11) NOT NULL DEFAULT '0',
                            `nlevel` int(11) NOT NULL DEFAULT '0',
                            `bloquer_creation` tinyint(1) NOT null DEFAULT '0',
                            `bloquer_modification` tinyint(1) NOT null DEFAULT '0',
                            `personal_folder` tinyint(1) NOT null DEFAULT '0',
                            `renewal_period` int(5) NOT null DEFAULT '0',
                            `fa_icon` VARCHAR(100) NOT NULL DEFAULT 'fas fa-folder',
                            `fa_icon_selected` VARCHAR(100) NOT NULL DEFAULT 'fas fa-folder-open',
                            `categories` longtext NOT NULL,
                            `nb_items_in_folder` int(10) NOT NULL DEFAULT '0',
                            `nb_subfolders` int(10) NOT NULL DEFAULT '0',
                            `nb_items_in_subfolders` int(10) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`),
                            KEY `nested_tree_parent_id` (`parent_id`),
                            KEY `nested_tree_nleft` (`nleft`),
                            KEY `nested_tree_nright` (`nright`),
                            KEY `nested_tree_nlevel` (`nlevel`),
                            KEY `personal_folder_idx` (`personal_folder`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'rights') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "rights` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `tree_id` int(12) NOT NULL,
                            `fonction_id` int(12) NOT NULL,
                            `authorized` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'users') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "users` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `login` varchar(500) NOT NULL,
                            `pw` varchar(400) NOT NULL,
                            `groupes_visibles` varchar(1000) NOT NULL,
                            `derniers` text NULL DEFAULT NULL,
                            `key_tempo` varchar(100) NULL DEFAULT NULL,
                            `last_pw_change` varchar(30) NULL DEFAULT NULL,
                            `last_pw` text NULL DEFAULT NULL,
                            `admin` tinyint(1) NOT null DEFAULT '0',
                            `fonction_id` varchar(1000) NULL DEFAULT NULL,
                            `groupes_interdits` varchar(1000) NULL DEFAULT NULL,
                            `last_connexion` varchar(30) NULL DEFAULT NULL,
                            `gestionnaire` int(11) NOT null DEFAULT '0',
                            `email` varchar(300) NOT NULL DEFAULT 'none',
                            `favourites` varchar(1000) NULL DEFAULT NULL,
                            `latest_items` varchar(1000) NULL DEFAULT NULL,
                            `personal_folder` int(1) NOT null DEFAULT '0',
                            `disabled` tinyint(1) NOT null DEFAULT '0',
                            `can_create_root_folder` tinyint(1) NOT null DEFAULT '0',
                            `read_only` tinyint(1) NOT null DEFAULT '0',
                            `timestamp` varchar(30) NOT null DEFAULT '0',
                            `user_language` varchar(50) NOT null DEFAULT '0',
                            `name` varchar(100) NULL DEFAULT NULL,
                            `lastname` varchar(100) NULL DEFAULT NULL,
                            `session_end` varchar(30) NULL DEFAULT NULL,
                            `isAdministratedByRole` tinyint(5) NOT null DEFAULT '0',
                            `psk` varchar(400) NULL DEFAULT NULL,
                            `ga` varchar(50) NULL DEFAULT NULL,
                            `ga_temporary_code` VARCHAR(20) NOT NULL DEFAULT 'none',
                            `avatar` varchar(1000) NULL DEFAULT NULL,
                            `avatar_thumb` varchar(1000) NULL DEFAULT NULL,
                            `upgrade_needed` BOOLEAN NOT NULL DEFAULT FALSE,
                            `treeloadstrategy` varchar(30) NOT null DEFAULT 'full',
                            `can_manage_all_users` tinyint(1) NOT NULL DEFAULT '0',
                            `usertimezone` VARCHAR(50) NOT NULL DEFAULT 'not_defined',
                            `agses-usercardid` VARCHAR(50) NOT NULL DEFAULT '0',
                            `encrypted_psk` text NULL DEFAULT NULL,
                            `user_ip` varchar(400) NOT null DEFAULT 'none',
                            `user_ip_lastdate` varchar(50) NULL DEFAULT NULL,
                            `yubico_user_key` varchar(100) NOT null DEFAULT 'none',
                            `yubico_user_id` varchar(100) NOT null DEFAULT 'none',
                            `public_key` TEXT NULL DEFAULT NULL,
                            `private_key` TEXT NULL DEFAULT NULL,
                            `special` VARCHAR(250) NOT NULL DEFAULT 'none',
                            `auth_type` VARCHAR(200) NOT NULL DEFAULT 'local',
                            `is_ready_for_usage` BOOLEAN NOT NULL DEFAULT FALSE,
                            `otp_provided` BOOLEAN NOT NULL DEFAULT FALSE,
                            `roles_from_ad_groups` varchar(1000) NULL DEFAULT NULL,
                            `ongoing_process_id` VARCHAR(100) NULL DEFAULT NULL,
                            `mfa_enabled` tinyint(1) NOT null DEFAULT '1',
                            `created_at` varchar(30) NULL DEFAULT NULL,
                            `updated_at` varchar(30) NULL DEFAULT NULL,
                            `deleted_at` varchar(30) NULL DEFAULT NULL,
                            `keys_recovery_time` VARCHAR(500) NULL DEFAULT NULL,
                            `aes_iv` TEXT NULL DEFAULT NULL,
                            `split_view_mode` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `login` (`login`)
                            ) CHARSET=utf8;"
                        );

                        require_once '../includes/config/include.php';

                        // Hash password
                        $passwordManager = new PasswordManager();
                        $hashedPassword = $passwordManager->hashPassword($var['admin_pwd']);

                        // check that admin accounts doesn't exist
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `" . $var['tbl_prefix'] . "users` WHERE login = 'admin'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `" . $var['tbl_prefix'] . "users` (`id`, `login`, `pw`, `admin`, `gestionnaire`, `personal_folder`, `groupes_visibles`, `email`, `encrypted_psk`, `last_pw_change`, `name`, `lastname`, `can_create_root_folder`, `public_key`, `private_key`, `is_ready_for_usage`, `otp_provided`, `created_at`) VALUES ('1', 'admin', '" . $hashedPassword . "', '1', '0', '0', '0', '" . $var['admin_email'] . "', '', '" . time() . "', 'Change me', 'Change me', '1', 'none', 'none', '1', '1', '" . time() . "')"
                            );
                        } else {
                            $mysqli_result = mysqli_query($dbTmp, 'UPDATE `' . $var['tbl_prefix'] . "users` SET `pw` = '" . $hashedPassword . "' WHERE login = 'admin' AND id = '1'");
                        }

                        // check that API doesn't exist
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `" . $var['tbl_prefix'] . "users` WHERE id = '" . API_USER_ID . "'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `" . $var['tbl_prefix'] . "users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`, `is_ready_for_usage`, `otp_provided`, `created_at`) VALUES ('" . API_USER_ID . "', 'API', '', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0', '0', '1', '" . time() . "')"
                            );
                        }

                        // check that OTV doesn't exist
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `" . $var['tbl_prefix'] . "users` WHERE id = '" . OTV_USER_ID . "'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `" . $var['tbl_prefix'] . "users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`, `is_ready_for_usage`, `otp_provided`, `created_at`) VALUES ('" . OTV_USER_ID . "', 'OTV', '', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0', '0', '1', '" . time() . "')"
                            );
                        }
                    } elseif ($inputData['task'] === 'tags') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'tags` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `tag` varchar(30) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'log_system') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'log_system` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `type` varchar(20) NOT NULL,
                            `date` varchar(30) NOT NULL,
                            `label` text NOT NULL,
                            `qui` varchar(255) NOT NULL,
                            `field_1` varchar(250) DEFAULT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'files') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "files` (
                            `id` int(11) NOT null AUTO_INCREMENT,
                            `id_item` int(11) NOT NULL,
                            `name` TEXT NOT NULL,
                            `size` int(10) NOT NULL,
                            `extension` varchar(10) NOT NULL,
                            `type` varchar(255) NOT NULL,
                            `file` varchar(50) NOT NULL,
                            `status` varchar(50) NOT NULL DEFAULT '0',
                            `content` longblob DEFAULT NULL,
							`confirmed` INT(1) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'cache') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "cache` (
                            `increment_id`INT(12) NOT NULL AUTO_INCREMENT,
                            `id` int(12) NOT NULL,
                            `label` varchar(500) NOT NULL,
                            `description` MEDIUMTEXT NULL DEFAULT NULL,
                            `tags` text DEFAULT NULL,
                            `id_tree` int(12) NOT NULL,
                            `perso` tinyint(1) NOT NULL,
                            `restricted_to` varchar(200) DEFAULT NULL,
                            `login` text DEFAULT NULL,
                            `folder` text NOT NULL,
                            `author` varchar(50) NOT NULL,
                            `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
                            `timestamp` varchar(50) DEFAULT NULL,
                            `url` text NULL DEFAULT NULL,
                            `encryption_type` VARCHAR(50) DEFAULT NULL DEFAULT '0',
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'roles_title') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "roles_title` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `title` varchar(50) NOT NULL,
                            `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
                            `complexity` INT(5) NOT null DEFAULT '0',
                            `creator_id` int(11) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );

                        // create Default role
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `" . $var['tbl_prefix'] . "roles_title` WHERE id = '0'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `" . $var['tbl_prefix'] . "roles_title` (`id`, `title`, `allow_pw_change`, `complexity`, `creator_id`) VALUES (NULL, 'Default', '0', '48', '0')"
                            );
                        }
                    } elseif ($inputData['task'] === 'roles_values') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "roles_values` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            `role_id` int(12) NOT NULL,
                            `folder_id` int(12) NOT NULL,
                            `type` varchar(5) NOT NULL DEFAULT 'R',
                            KEY `role_id_idx` (`role_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'kb') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "kb` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `category_id` int(12) NOT NULL,
                            `label` varchar(200) NOT NULL,
                            `description` text NOT NULL,
                            `author_id` int(12) NOT NULL,
                            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'kb_categories') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'kb_categories` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `category` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'kb_items') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'kb_items` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `kb_id` int(12) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] == 'restriction_to_roles') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'restriction_to_roles` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `role_id` int(12) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'languages') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'languages` (
                            `id` INT(10) NOT null AUTO_INCREMENT,
                            `name` VARCHAR(50) NOT null ,
                            `label` VARCHAR(50) NOT null ,
                            `code` VARCHAR(10) NOT null ,
                            `flag` VARCHAR(50) NOT NULL,
                            `code_poeditor` VARCHAR(30) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;'
                        );

                        // add lanaguages
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `" . $var['tbl_prefix'] . "languages` WHERE name = 'french'"));
                        if ($tmp === 0) {
                            $mysql_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `" . $var['tbl_prefix'] . "languages` (`id`, `name`, `label`, `code`, `flag`, `code_poeditor`) VALUES
                                (1, 'french', 'French', 'fr', 'fr.png', 'fr'),
                                (2, 'english', 'English', 'us', 'us.png', 'en'),
                                (3, 'spanish', 'Spanish', 'es', 'es.png', 'es'),
                                (4, 'german', 'German', 'de', 'de.png', 'de'),
                                (5, 'czech', 'Czech', 'cs', 'cz.png', 'cs'),
                                (6, 'italian', 'Italian', 'it', 'it.png', 'it'),
                                (7, 'russian', 'Russian', 'ru', 'ru.png', 'ru'),
                                (8, 'turkish', 'Turkish', 'tr', 'tr.png', 'tr'),
                                (9, 'norwegian', 'Norwegian', 'no', 'no.png', 'no'),
                                (10, 'japanese', 'Japanese', 'ja', 'ja.png', 'ja'),
                                (11, 'portuguese', 'Portuguese', 'pr', 'pr.png', 'pt'),
                                (12, 'portuguese_br', 'Portuguese (Brazil)', 'pr-bt', 'pr-bt.png', 'pt-br'),
                                (13, 'chinese', 'Chinese', 'zh-Hans', 'cn.png', 'zh-Hans'),
                                (14, 'swedish', 'Swedish', 'se', 'se.png', 'sv'),
                                (15, 'dutch', 'Dutch', 'nl', 'nl.png', 'nl'),
                                (16, 'catalan', 'Catalan', 'ca', 'ct.png', 'ca'),
                                (17, 'bulgarian', 'Bulgarian', 'bg', 'bg.png', 'bg'),
                                (18, 'greek', 'Greek', 'gr', 'gr.png', 'el'),
                                (19, 'hungarian', 'Hungarian', 'hu', 'hu.png', 'hu'),
                                (20, 'polish', 'Polish', 'pl', 'pl.png', 'pl'),
                                (21, 'romanian', 'Romanian', 'ro', 'ro.png', 'ro'),
                                (22, 'ukrainian', 'Ukrainian', 'ua', 'ua.png', 'uk'),
                                (23, 'vietnamese', 'Vietnamese', 'vi', 'vi.png', 'vi'),
                                (24, 'estonian', 'Estonian', 'et', 'ee.png', 'et');"
                            );
                        }
                    } elseif ($inputData['task'] === 'emails') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'emails` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `timestamp` INT(30) NOT null ,
                            `subject` TEXT NOT null ,
                            `body` TEXT NOT null ,
                            `receivers` TEXT NOT null ,
                            `status` VARCHAR(30) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'automatic_del') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'automatic_del` (
                            `item_id` int(11) NOT NULL,
                            `del_enabled` tinyint(1) NOT NULL,
                            `del_type` tinyint(1) NOT NULL,
                            `del_value` varchar(35) NOT NULL,
                            PRIMARY KEY (`item_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'items_edition') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'items_edition` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `item_id` int(11) NOT NULL,
                            `user_id` int(12) NOT NULL,
                            `timestamp` varchar(50) NOT NULL,
                            KEY `item_id_idx` (`item_id`),
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'categories') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "categories` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `parent_id` int(12) NOT NULL,
                            `title` varchar(255) NOT NULL,
                            `level` int(2) NOT NULL,
                            `description` text NULL,
                            `type` varchar(50) NULL default '',
                            `masked` tinyint(1) NOT NULL default '0',
                            `order` int(12) NOT NULL default '0',
                            `encrypted_data` tinyint(1) NOT NULL default '1',
                            `role_visibility` varchar(255) NOT NULL DEFAULT 'all',
                            `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
                            `regex` varchar(255) NULL default '',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'categories_items') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "categories_items` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `field_id` int(11) NOT NULL,
                            `item_id` int(11) NOT NULL,
                            `data` text NOT NULL,
                            `data_iv` text NOT NULL,
                            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
                            `is_mandatory` BOOLEAN NOT NULL DEFAULT FALSE ,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'categories_folders') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'categories_folders` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `id_category` int(12) NOT NULL,
                            `id_folder` int(12) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'api') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "api` (
                            `increment_id` int(20) NOT NULL AUTO_INCREMENT,
                            `type` varchar(15) NOT NULL,
                            `label` varchar(255) DEFAULT NULL,
                            `value` text DEFAULT NULL,
                            `timestamp` varchar(50) NOT NULL,
                            `user_id` int(13) DEFAULT NULL,
                            `allowed_folders` text NULL DEFAULT NULL,
                            `enabled` int(1) NOT NULL DEFAULT '0',
                            `allowed_to_create` int(1) NOT NULL DEFAULT '0',
                            `allowed_to_read` int(1) NOT NULL DEFAULT '1',
                            `allowed_to_update` int(1) NOT NULL DEFAULT '0',
                            `allowed_to_delete` int(1) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`increment_id`),
                            KEY `USER` (`user_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'otv') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "otv` (
                            `id` int(10) NOT NULL AUTO_INCREMENT,
                            `timestamp` text NOT NULL,
                            `code` varchar(100) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            `originator` int(12) NOT NULL,
                            `encrypted` text NOT NULL,
                            `views` INT(10) NOT NULL DEFAULT '0',
                            `max_views` INT(10) NULL DEFAULT NULL,
                            `time_limit` varchar(100) DEFAULT NULL,
                            `shared_globaly` INT(1) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'suggestion') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "suggestion` (
                            `id` tinyint(12) NOT NULL AUTO_INCREMENT,
                            `label` varchar(255) NOT NULL,
                            `pw` text NOT NULL,
                            `pw_iv` text NOT NULL,
                            `pw_len` int(5) NOT NULL,
                            `description` text NOT NULL,
                            `author_id` int(12) NOT NULL,
                            `folder_id` int(12) NOT NULL,
                            `comment` text NOT NULL,
                            `suggestion_type` varchar(10) NOT NULL default 'new',
                            `encryption_type` varchar(20) NOT NULL default 'not_set',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );

                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "export` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `export_tag` varchar(20) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            `label` varchar(500) NOT NULL,
                            `login` varchar(100) NOT NULL,
                            `description` text NOT NULL,
                            `pw` text NOT NULL,
                            `path` varchar(500) NOT NULL,
                            `email` varchar(500) NOT NULL default 'none',
                            `url` varchar(500) NOT NULL default 'none',
                            `kbs` varchar(500) NOT NULL default 'none',
                            `tags` varchar(500) NOT NULL default 'none',
                            `folder_id` varchar(10) NOT NULL,
                            `perso` tinyint(1) NOT NULL default '0',
                            `restricted_to` varchar(200) DEFAULT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'tokens') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'tokens` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `user_id` int(12) NOT NULL,
                            `token` varchar(255) NOT NULL,
                            `reason` varchar(255) NOT NULL,
                            `creation_timestamp` varchar(50) NOT NULL,
                            `end_timestamp` varchar(50) DEFAULT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'items_change') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "items_change` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `item_id` int(12) NOT NULL,
                            `label` varchar(255) NOT NULL DEFAULT 'none',
                            `pw` text NOT NULL,
                            `login` varchar(255) NOT NULL DEFAULT 'none',
                            `email` varchar(255) NOT NULL DEFAULT 'none',
                            `url` varchar(255) NOT NULL DEFAULT 'none',
                            `description` text NOT NULL,
                            `comment` text NOT NULL,
                            `folder_id` tinyint(12) NOT NULL,
                            `user_id` int(12) NOT NULL,
                            `timestamp` varchar(50) NOT NULL DEFAULT 'none',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($inputData['task'] === 'templates') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            'CREATE TABLE IF NOT EXISTS `' . $var['tbl_prefix'] . 'templates` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `item_id` int(12) NOT NULL,
                            `category_id` int(12) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;'
                        );
                    } elseif ($inputData['task'] === 'cache_tree') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "cache_tree` (
                            `increment_id` smallint(32) NOT NULL AUTO_INCREMENT,
                            `data` longtext DEFAULT NULL CHECK (json_valid(`data`)),
                            `visible_folders` longtext NOT NULL,
                            `timestamp` varchar(50) NOT NULL,
                            `user_id` int(12) NOT NULL,
                            `folders` longtext DEFAULT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($inputData['task'] === 'background_subtasks') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "background_subtasks` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `task_id` int(12) NOT NULL,
                            `created_at` varchar(50) NOT NULL,
                            `updated_at` varchar(50) DEFAULT NULL,
                            `finished_at` varchar(50) DEFAULT NULL,
                            `task` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`task`)),
                            `process_id` varchar(100) NULL DEFAULT NULL,
                            `is_in_progress` tinyint(1) NOT NULL DEFAULT 0,
                            `sub_task_in_progress` tinyint(1) NOT NULL DEFAULT 0,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );

                        // Requête pour vérifier si les clés existent
                        $keyCheckQuery = "
                        SELECT COUNT(1) as key_exists 
                        FROM information_schema.STATISTICS 
                        WHERE table_schema = DATABASE() 
                        AND table_name = '" . $var['tbl_prefix'] . "background_subtasks' 
                        AND index_name IN ('task_id_idx')
                        ";
                        $result = mysqli_query($dbTmp, $keyCheckQuery);
                        if ($result) {
                            $row = mysqli_fetch_assoc($result);
                            if ($row['key_exists'] == 0) {
                                // Les clés n'existent pas, exécutez la requête ALTER TABLE
                                $alterQuery = "
                                    ALTER TABLE `" . $var['tbl_prefix'] . "background_subtasks` 
                                        ADD KEY `task_id_idx` (`task_id`);
                                ";
                            }
                        }
                    } else if ($inputData['task'] === 'background_tasks') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "background_tasks` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `created_at` varchar(50) NOT NULL,
                            `started_at` varchar(50) DEFAULT NULL,
                            `updated_at` varchar(50) DEFAULT NULL,
                            `finished_at` varchar(50) DEFAULT NULL,
                            `process_id` int(12) DEFAULT NULL,
                            `process_type` varchar(100) NOT NULL,
                            `output` text DEFAULT NULL,
                            `arguments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`arguments`)),
                            `is_in_progress` tinyint(1) NOT NULL DEFAULT 0,
                            `item_id` INT(12) NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($inputData['task'] === 'background_tasks_logs') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "background_tasks_logs` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `created_at` INT NOT NULL,
                            `job` varchar(50) NOT NULL,
                            `status` varchar(10) NOT NULL,
                            `updated_at` INT DEFAULT NULL,
                            `finished_at` INT DEFAULT NULL,
                            `treated_objects` varchar(20) DEFAULT NULL,
                            PRIMARY KEY (`increment_id`),
                            INDEX idx_created_at (`created_at`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($inputData['task'] === 'ldap_groups_roles') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "ldap_groups_roles` (
                            `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
                            `role_id` INT(12) NOT NULL,
                            `ldap_group_id` VARCHAR(500) NOT NULL,
                            `ldap_group_label` VARCHAR(255) NOT NULL,
                            PRIMARY KEY (`increment_id`),
                            KEY `ROLE` (`role_id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($inputData['task'] === 'items_otp') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "items_otp` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `item_id` int(12) NOT NULL,
                            `secret` text NOT NULL,
                            `timestamp` varchar(100) NOT NULL,
                            `enabled` tinyint(1) NOT NULL DEFAULT 0,
                            `phone_number` varchar(25) NOT NULL,
                            PRIMARY KEY (`increment_id`),
                            KEY `ITEM` (`item_id`)
                            ) CHARSET=utf8;"
                        );
                    } else if ($inputData['task'] === 'auth_failures') {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `" . $var['tbl_prefix'] . "auth_failures` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `source` ENUM('login', 'remote_ip') NOT NULL,
                            `value` VARCHAR(500) NOT NULL,
                            `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `unlock_at` TIMESTAMP NULL DEFAULT NULL,
                            `unlock_code` VARCHAR(50) NULL DEFAULT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    }
                    // CARREFULL - WHEN ADDING NEW TABLE
                    // Add the command inside install.js file
                    // in task array at step 5
                }
                // answer back
                if ($mysqli_result) {
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '", "task" : "' . $inputData['task'] . '", "activity" : "' . $inputData['activity'] . '"}]';
                } else {
                    echo '[{"error" : "' . addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_error($dbTmp))) . '", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '", "table" : "' . $inputData['task'] . '"}]';
                }
            } else {
                echo '[{"error" : "' . addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_connect_error())) . '", "result" : "Failed", "multiple" : ""}]';
            }

            mysqli_close($dbTmp);
            // Destroy session without writing to disk
            define('NODESTROY_SESSION', 'true');
            session_destroy();
            break;

        case 'step_6':
            $dbTmp = mysqli_connect(
                $inputData['db']['db_host'],
                $inputData['db']['db_login'],
                $inputData['db']['db_pw'],
                $inputData['db']['db_bdd'],
                $inputData['db']['db_port']
            );

            // read install variables
            $result = mysqli_query($dbTmp, 'SELECT * FROM `_install`');
            while ($row = $result->fetch_array()) {
                $var[$row[0]] = $row[1];
            }

            // launch
            $events = '';

            if ($inputData['activity'] === 'file') {
                if ($inputData['task'] === 'settings.php') {
                    // Now create settings file
                    $filename = '../includes/config/settings.php';

                    if (!copy($filename, $filename . '.' . date('Y_m_d', mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')))."_".time())) {
                        echo '[{"error" : "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                        break;
                    }

                    // get key
                    $encryptionKey = file_get_contents(SECUREPATH . "/" . SECUREFILE);
                    
                    // Encrypt the DB password
                    $encrypted_text = encryptFollowingDefuse(
                        $inputData['db']['db_pw'],
                        $encryptionKey
                    )['string'];

                    // Open and write Settings file
                    $file_handler = fopen($filename, 'w');
                    $result = fwrite(
                        $file_handler,
                        utf8_encode(
                            '<?php
// DATABASE connexion parameters
define("DB_HOST", "' . $inputData['db']['db_host'] . '");
define("DB_USER", "' . $inputData['db']['db_login'] . '");
define("DB_PASSWD", "' . str_replace('$', '\$', $encrypted_text) . '");
define("DB_NAME", "' . $inputData['db']['db_bdd'] . '");
define("DB_PREFIX", "' . $var['tbl_prefix'] . '");
define("DB_PORT", "' . $inputData['db']['db_port'] . '");
define("DB_ENCODING", "' . $session_db_encoding . '");
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
define("SECUREPATH", "' . str_replace('"', '', $session_sk_path) . '");
define("SECUREFILE", "' . SECUREFILE. '");

if (isset($_SESSION[\'settings\'][\'timezone\']) === true) {
    date_default_timezone_set($_SESSION[\'settings\'][\'timezone\']);
}
'
                        )
                    );
                    fclose($file_handler);

                    // Create TP USER
                    require_once '../includes/config/include.php';
                    $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `" . $var['tbl_prefix'] . "users` WHERE id = '" . TP_USER_ID . "'"));
                    if ($tmp === 0) {
                        // generate key for password
                        $pwd = GenerateCryptKey(25, true, true, true, true);
                        $encrypted_pwd = cryption(
                            $pwd,
                            $encryptionKey,
                            'encrypt'
                        )['string'];

                        // GEnerate new public and private keys
                        $userKeys = generateUserKeys($pwd);

                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "INSERT INTO `" . $var['tbl_prefix'] . "users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`, `public_key`, `private_key`, `is_ready_for_usage`, `otp_provided`, `created_at`) VALUES ('" . TP_USER_ID . "', 'TP', '".$encrypted_pwd."', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0', '".$userKeys['public_key']."', '".$userKeys['private_key']."', '1', '1', '" . time() . "')"
                        );
                    }

                    // Destroy session without writing to disk
                    // Requested to reset CONST values
                    if (isset($_COOKIE['PHPSESSID'])) {
                        setcookie('PHPSESSID', '', time() - 10, '/', '', false, true);
                    }
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_unset();
                        session_destroy();
                    }

                    if ($result === false) {
                        echo '[{"error" : "Setting.php file could not be created. Please check the path and the rights", "result":"", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                    } else {
                        echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                    }
                } elseif ($inputData['task'] === 'security') {
                    // Sort out the file permissions

                    // is server Windows or Linux?
                    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                        // Change directory permissions
                        if (is_null($session_abspath) === false) {
                            $result = recursiveChmod($session_abspath, 0770, 0740);
                            if ($result) {
                                $result = recursiveChmod($session_abspath . '/files', 0770, 0770);
                            }
                            if ($result) {
                                $result = recursiveChmod($session_abspath . '/upload', 0770, 0770);
                            }
                        }
                    }
                    $result = true;
                    if ($result === false) {
                        echo '[{"error" : "Cannot change directory permissions - please fix manually", "result":"", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                    } else {
                        echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                    }
                } elseif ($inputData['task'] === 'csrfp-token') {
                    // update CSRFP TOKEN
                    $csrfp_file_sample = '../includes/libraries/csrfp/libs/csrfp.config.sample.php';
                    $csrfp_file = '../includes/libraries/csrfp/libs/csrfp.config.php';
                    if (file_exists($csrfp_file)) {
                        if (!copy($csrfp_file, $csrfp_file . '.' . date('Y_m_d', mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y'))))) {
                            echo '[{"error" : "csrfp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                            break;
                        } else {
                            $events .= "The file $csrfp_file already exist. A copy has been created.<br />";
                        }
                    }
                    unlink($csrfp_file); // delete existing csrfp.config file
                    copy($csrfp_file_sample, $csrfp_file); // make a copy of csrfp.config.sample file
                    $data = file_get_contents($csrfp_file);
                    $newdata = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "' . bin2hex(openssl_random_pseudo_bytes(25)) . '"', $data);
                    $jsUrl = './includes/libraries/csrfp/js/csrfprotector.js';
                    $newdata = str_replace('"jsUrl" => ""', '"jsUrl" => "' . $jsUrl . '"', $newdata);
                    file_put_contents('../includes/libraries/csrfp/libs/csrfp.config.php', $newdata);

                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                }
            } elseif ($inputData['activity'] === 'install') {
                if ($inputData['task'] === 'cleanup') {
                    // Mark a tag to force Install stuff (folders, files and table) to be cleanup while first login
                    mysqli_query($dbTmp, "INSERT INTO `" . $var['tbl_prefix'] . "misc` (`type`, `intitule`, `valeur`) VALUES ('install', 'clear_install_folder', 'true')");

                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                } elseif ($inputData['task'] === 'init') {
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                } elseif ($inputData['task'] === 'cronJob') {
                    // Create cronjob
                    // get php location
                    require_once 'tp.functions.php';
                    $phpLocation = findPhpBinary();
                    if ($phpLocation['error'] === false) {
                        // Instantiate the adapter and repository
                        try {
                            $crontabRepository = new CrontabRepository(new CrontabAdapter());
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
                                    ->setTaskCommandLine($phpLocation . ' ' . $SETTINGS['cpassman_dir'] . '/sources/scheduler.php')
                                    ->setComments('Teampass scheduler');
                                
                                $crontabRepository->addJob($crontabJob);
                                $crontabRepository->persist();

                                // Now remove old file
                                if (file_exists(__DIR__.'/../includes/config/'.SECUREFILE)) {
                                    unlink(__DIR__.'/../includes/config/'.SECUREFILE);
                                }
                            }
                        } catch (Exception $e) {
                            // do nothing
                        }
                    } else {
                        echo '[{"error" : "Cannot find PHP binary location. Please add a cronjob manually (see documentation).", "result":"", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                        break;
                    }
                    echo '[{"error" : "", "index" : "' . $inputData['index'] . '", "multiple" : "' . $inputData['multiple'] . '"}]';
                }
            }

            mysqli_close($dbTmp);
            // Destroy session without writing to disk
            define('NODESTROY_SESSION', 'true');
            session_destroy();
            break;
    }
}
