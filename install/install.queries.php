<?php
/**
 * @file          install.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
header("Content-type: text/html; charset=utf-8");
$session_db_encoding = "utf8";

function chmodRecursive($dir, $dirPermissions, $filePermissions)
{
    $pointer_dir = opendir($dir);
    $res = true;
    while ($file = readdir($pointer_dir)) {
        if (($file == ".") || ($file == "..")) {
            continue;
        }

        $fullPath = $dir."/".$file;

        if (is_dir($fullPath)) {
            if ($res = @chmod($fullPath, $dirPermissions)) {
                $res = @chmodRecursive($fullPath, $dirPermissions, $filePermissions);
            }
        } else {
            $res = chmod($fullPath, $filePermissions);
        }
        if (!$res) {
            closedir($pointer_dir);
            return false;
        }
    }
    closedir($pointer_dir);
    if (is_dir($dir) && $res) {
            $res = @chmod($dir, $dirPermissions);
    }

    return $res;
}

/**
 * genHash()
 *
 * Generate a hash for user login
 * @param string $password
 */
function bCrypt($password, $cost)
{
    $salt = sprintf('$2y$%02d$', $cost);
    if (function_exists('openssl_random_pseudo_bytes')) {
        $salt .= bin2hex(openssl_random_pseudo_bytes(11));
    } else {
        $chars = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 22; $i++) {
            $salt .= $chars[mt_rand(0, 63)];
        }
    }
    return crypt($password, $salt);
}

/**
 * Permits to encrypt a message using Defuse
 * @param  string $message   Message to encrypt
 * @param  string $ascii_key Key to hash
 * @return array             String + Error
 */
function encryptFollowingDefuse($message, $ascii_key)
{
    // load PhpEncryption library
    $path = '../includes/libraries/Encryption/Encryption/';
    require_once $path.'Crypto.php';
    require_once $path.'Encoding.php';
    require_once $path.'DerivedKeys.php';
    require_once $path.'Key.php';
    require_once $path.'KeyOrPassword.php';
    require_once $path.'File.php';
    require_once $path.'RuntimeTests.php';
    require_once $path.'KeyProtectedByPassword.php';
    require_once $path.'Core.php';

    // convert KEY
    $key = \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key);

    try {
        $text = \Defuse\Crypto\Crypto::encrypt($message, $key);
    } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        $err = "an attack! either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.";
    } catch (Defuse\Crypto\Exception\BadFormatException $ex) {
        $err = $ex;
    } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (Defuse\Crypto\Exception\CryptoException $ex) {
        $err = $ex;
    } catch (Defuse\Crypto\Exception\IOException $ex) {
        $err = $ex;
    }

    return array(
        'string' => isset($text) ? $text : "",
        'error' => $err
    );
}


// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_activity = filter_input(INPUT_POST, 'activity', FILTER_SANITIZE_STRING);
$post_task = filter_input(INPUT_POST, 'task', FILTER_SANITIZE_STRING);
$post_index = filter_input(INPUT_POST, 'index', FILTER_SANITIZE_NUMBER_INT);
$post_multiple = filter_input(INPUT_POST, 'multiple', FILTER_SANITIZE_STRING);
$post_db = filter_input(INPUT_POST, 'db', FILTER_SANITIZE_STRING);

// Load libraries
require_once '../includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare SESSION variables
$session_url_path = $superGlobal->get("url_path", "SESSION");
$session_abspath = $superGlobal->get("abspath", "SESSION");
$session_db_encoding = $superGlobal->get("db_encoding", "SESSION");

$superGlobal->put("CPM", 1, "SESSION");

if (null !== $post_type) {
    switch ($post_type) {
        case "step_2":
            //decrypt
            require_once 'libs/aesctr.php'; // AES Counter Mode implementation
            $json = Encryption\Crypt\aesctr::decrypt($post_data, "cpm", 128);
            $data = json_decode($json, true);
            $json = Encryption\Crypt\aesctr::decrypt($post_activity, "cpm", 128);
            $data = array_merge($data, array("activity" => $json));
            $json = Encryption\Crypt\aesctr::decrypt($post_task, "cpm", 128);
            $data = array_merge($data, array("task" => $json));

            $abspath = str_replace('\\', '/', $data['root_path']);
            if (substr($abspath, strlen($abspath) - 1) == "/") {
                $abspath = substr($abspath, 0, strlen($abspath) - 1);
            }
            $session_abspath = $abspath;
            $session_url_path = $data['url_path'];

            if (isset($data['activity']) && $data['activity'] === "folder") {
                if (is_writable($abspath."/".$data['task']."/") === true) {
                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                } else {
                    echo '[{"error" : " Path '.$data['task'].' is not writable!", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
                break;
            }

            if (isset($data['activity']) && $data['activity'] === "extension") {
                if (extension_loaded($data['task'])) {
                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                } else {
                    echo '[{"error" : " Extension '.$data['task'].' is not loaded!", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
                break;
            }

            if (isset($data['activity']) && $data['activity'] === "function") {
                if (function_exists($data['task'])) {
                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                } else {
                    echo '[{"error" : " Function '.$data['task'].' is not available!", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
                break;
            }

            if (isset($data['activity']) && $data['activity'] === "version") {
                if (version_compare(phpversion(), '5.5.0', '>=')) {
                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                } else {
                    echo '[{"error" : "PHP version '.phpversion().' is not OK (minimum is 5.5.0)", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
                break;
            }

            if (isset($data['activity']) && $data['activity'] === "ini") {
                if (ini_get($data['task']) >= 60) {
                    echo '[{"error" : "", "index" : "'.$post_index.'"}]';
                } else {
                    echo '[{"error" : "PHP \"Maximum execution time\" is set to '.ini_get('max_execution_time').' seconds. Please try to set to 60s at least during installation.", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
                break;
            }
            break;

        case "step_3":
            //decrypt
            require_once 'libs/aesctr.php'; // AES Counter Mode implementation
            $json = Encryption\Crypt\aesctr::decrypt($post_data, "cpm", 128);
            $data = json_decode($json, true);
            $json = Encryption\Crypt\aesctr::decrypt($post_db, "cpm", 128);
            $db = json_decode($json, true);

            // launch
            if ($dbTmp = mysqli_connect($db['db_host'], $db['db_login'], $db['db_pw'], $db['db_bdd'], $db['db_port'])) {
                // create temporary INSTALL mysqli table
                $mysqli_result = mysqli_query(
                    $dbTmp,
                    "CREATE TABLE IF NOT EXISTS `_install` (
                    `key` varchar(100) NOT NULL,
                    `value` varchar(500) NOT NULL,
                    PRIMARY KEY (`key`)
                    ) CHARSET=utf8;"
                );
                // store values
                foreach ($data as $key => $value) {
                    $superGlobal->put($key, $value, "SESSION");
                    $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = '".$key."'"));
                    if (intval($tmp) === 0) {
                        mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('".$key."', '".$value."');");
                    } else {
                        mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '".$value."' WHERE `key` = '".$key."';");
                    }
                }
                $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = 'url_path'"));
                if (intval($tmp) === 0) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('url_path', '". empty($session_url_path) ? $db['url_path'] : $session_url_path. "');");
                } else {
                    mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '", empty($session_url_path) ? $db['url_path'] : $session_url_path, "' WHERE `key` = 'url_path';");
                }
                $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = 'abspath'"));
                if (intval($tmp) === 0) {
                    mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('abspath', '". empty($session_abspath) ? $db['abspath'] : $session_abspath. "');");
                } else {
                    mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '". empty($session_abspath) ? $db['abspath'] : $session_abspath. "' WHERE `key` = 'abspath';");
                }

                echo '[{"error" : "", "result" : "Connection is successful", "multiple" : ""}]';
            } else {
                echo '[{"error" : "'.addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_connect_error())).'", "result" : "Failed", "multiple" : ""}]';
            }
            mysqli_close($dbTmp);
            break;

        case "step_4":
            //decrypt
            require_once 'libs/aesctr.php'; // AES Counter Mode implementation
            $json = Encryption\Crypt\aesctr::decrypt($post_data, "cpm", 128);
            $data = json_decode($json, true);
            $json = Encryption\Crypt\aesctr::decrypt($post_db, "cpm", 128);
            $db = json_decode($json, true);

            $dbTmp = mysqli_connect($db['db_host'], $db['db_login'], $db['db_pw'], $db['db_bdd'], $db['db_port']);

            // prepare data
            foreach ($data as $key => $value) {
                $data[$key] = str_replace(array('&quot;', '&#92;'), array('""', '\\\\'), $value);
            }

            // check skpath
            if (empty($data['sk_path'])) {
                $data['sk_path'] = $session_abspath."/includes";
            } else {
                $data['sk_path'] = str_replace("&#92;", "/", $data['sk_path']);
            }
            if (substr($data['sk_path'], strlen($data['sk_path']) - 1) == "/" || substr($data['sk_path'], strlen($data['sk_path']) - 1) == "\"") {
                $data['sk_path'] = substr($data['sk_path'], 0, strlen($data['sk_path']) - 1);
            }
            if (is_dir($data['sk_path'])) {
                if (is_writable($data['sk_path'])) {
                    // store all variables in SESSION
                    foreach ($data as $key => $value) {
                        $superGlobal->put($key, $value, "SESSION");
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `_install` WHERE `key` = '".$key."'"));
                        if (intval($tmp) === 0) {
                            mysqli_query($dbTmp, "INSERT INTO `_install` (`key`, `value`) VALUES ('".$key."', '".$value."');");
                        } else {
                            mysqli_query($dbTmp, "UPDATE `_install` SET `value` = '".$value."' WHERE `key` = '".$key."';");
                        }
                    }
                    echo '[{"error" : "", "result" : "Information stored", "multiple" : ""}]';
                } else {
                    echo '[{"error" : "The Directory must be writable!", "result" : "Information stored", "multiple" : ""}]';
                }
            } else {
                echo '[{"error" : "'.$data['sk_path'].' is not a Directory!", "result" : "Information stored", "multiple" : ""}]';
            }
            mysqli_close($dbTmp);
            break;

        case "step_5":
            //decrypt
            require_once 'libs/aesctr.php'; // AES Counter Mode implementation
            $activity = Encryption\Crypt\aesctr::decrypt($post_activity, "cpm", 128);
            $task = Encryption\Crypt\aesctr::decrypt($post_task, "cpm", 128);
            $json = Encryption\Crypt\aesctr::decrypt($post_db, "cpm", 128);
            $db = json_decode($json, true);

            // launch
            $dbTmp = mysqli_connect($db['db_host'], $db['db_login'], $db['db_pw'], $db['db_bdd'], $db['db_port']);
            $dbBdd = $db['db_bdd'];
            if ($dbTmp) {
                $mysqli_result = "";

                // read install variables
                $result = mysqli_query($dbTmp, "SELECT * FROM `_install`");
                while ($row = $result->fetch_array()) {
                    $var[$row[0]] = $row[1];
                }

                if ($activity === "table") {
                    if ($task === "utf8") {
                        //FORCE UTF8 DATABASE
                        mysqli_query($dbTmp, "ALTER DATABASE `".$dbBdd."` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
                    } elseif ($task === "items") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."items` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `label` varchar(500) NOT NULL,
                            `description` text DEFAULT NULL,
                            `pw` text DEFAULT NULL,
                            `pw_iv` text DEFAULT NULL,
                            `pw_len` int(5) NOT NULL DEFAULT '0',
                            `url` varchar(500) DEFAULT NULL,
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
                            PRIMARY KEY (`id`),
                            KEY    `restricted_inactif_idx` (`restricted_to`,`inactif`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "log_items") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."log_items` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `id_item` int(8) NOT NULL,
                            `date` varchar(50) NOT NULL,
                            `id_user` int(8) NOT NULL,
                            `action` varchar(250) NULL,
                            `raison` text NULL,
                            `raison_iv` text NULL,
                            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                        // create index
                        mysqli_query(
                            $dbTmp,
                            "CREATE INDEX teampass_log_items_id_item_IDX ON ".$var['tbl_prefix']."log_items (id_item,date);"
                        );
                    } elseif ($task === "misc") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."misc` (
                            `increment_id` int(12) NOT null AUTO_INCREMENT,
                            `type` varchar(50) NOT NULL,
                            `intitule` varchar(100) NOT NULL,
                            `valeur` varchar(500) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );

                        // include constants
                        require_once "../includes/config/include.php";

                        // prepare config file
                        $tp_config_file = "../includes/config/tp.config.php";
                        if (file_exists($tp_config_file)) {
                            if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                                echo '[{"error" : "includes/config/tp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                                break;
                            } else {
                                unlink($tp_config_file);
                            }
                        }
                        $file_handler = fopen($tp_config_file, 'w');
                        $config_text = "<?php
global \$SETTINGS;
\$SETTINGS = array (";

                        // add by default settings
                        $aMiscVal = array(
                            array('admin', 'max_latest_items', '10'),
                            array('admin', 'enable_favourites', '1'),
                            array('admin', 'show_last_items', '1'),
                            array('admin', 'enable_pf_feature', '0'),
                            array('admin', 'log_connections', '0'),
                            array('admin', 'log_accessed', '1'),
                            array('admin', 'time_format', 'H:i:s'),
                            array('admin', 'date_format', 'd/m/Y'),
                            array('admin', 'duplicate_folder', '0'),
                            array('admin', 'item_duplicate_in_same_folder', '0'),
                            array('admin', 'duplicate_item', '0'),
                            array('admin', 'number_of_used_pw', '3'),
                            array('admin', 'manager_edit', '1'),
                            array('admin', 'cpassman_dir', $var['abspath']),
                            array('admin', 'cpassman_url', $var['url_path']),
                            array('admin', 'favicon', $var['url_path'].'/favicon.ico'),
                            array('admin', 'path_to_upload_folder', $var['abspath'].'/upload'),
                            array('admin', 'url_to_upload_folder', $var['url_path'].'/upload'),
                            array('admin', 'path_to_files_folder', $var['abspath'].'/files'),
                            array('admin', 'url_to_files_folder', $var['url_path'].'/files'),
                            array('admin', 'activate_expiration', '0'),
                            array('admin', 'pw_life_duration', '0'),
                            array('admin', 'maintenance_mode', '1'),
                            array('admin', 'enable_sts', '0'),
                            array('admin', 'encryptClientServer', '1'),
                            array('admin', 'cpassman_version', $SETTINGS_EXT['version']),
                            array('admin', 'ldap_mode', '0'),
                            array('admin', 'ldap_type', '0'),
                            array('admin', 'ldap_suffix', '0'),
                            array('admin', 'ldap_domain_dn', '0'),
                            array('admin', 'ldap_domain_controler', '0'),
                            array('admin', 'ldap_user_attribute', '0'),
                            array('admin', 'ldap_ssl', '0'),
                            array('admin', 'ldap_tls', '0'),
                            array('admin', 'ldap_elusers', '0'),
                            array('admin', 'ldap_search_base', '0'),
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
                            array('admin', 'email_auth_username', ''),
                            array('admin', 'email_auth_pwd', ''),
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
                            array('admin', 'ldap_object_class', '0'),
                            array('admin', 'bck_script_path', $var['abspath']."/backups"),
                            array('admin', 'bck_script_filename', 'bck_teampass'),
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
                            array('admin', 'ldap_and_local_authentication', '0')
                        );
                        foreach ($aMiscVal as $elem) {
                            //Check if exists before inserting
                            $tmp = mysqli_num_rows(
                                mysqli_query(
                                    $dbTmp,
                                    "SELECT * FROM `".$var['tbl_prefix']."misc`
                                    WHERE type='".$elem[0]."' AND intitule='".$elem[1]."'"
                                )
                            );
                            if (intval($tmp) === 0) {
                                $queryRes = mysqli_query(
                                    $dbTmp,
                                    "INSERT INTO `".$var['tbl_prefix']."misc`
                                    (`type`, `intitule`, `valeur`) VALUES
                                    ('".$elem[0]."', '".$elem[1]."', '".
                                    str_replace("'", "", $elem[2])."');"
                                ); // or die(mysqli_error($dbTmp))
                            }

                            // append new setting in config file
                            $config_text .= "
    '".$elem[1]."' => '".str_replace("'", "", $elem[2])."',";
                        }

                        // write to config file
                        $result = fwrite(
                            $file_handler,
                            utf8_encode(
                                $config_text."
);"
                            )
                        );
                        fclose($file_handler);
                    } elseif ($task === "nested_tree") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."nested_tree` (
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
                            PRIMARY KEY (`id`),
                            KEY `nested_tree_parent_id` (`parent_id`),
                            KEY `nested_tree_nleft` (`nleft`),
                            KEY `nested_tree_nright` (`nright`),
                            KEY `nested_tree_nlevel` (`nlevel`),
                            KEY `personal_folder_idx` (`personal_folder`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "rights") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."rights` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `tree_id` int(12) NOT NULL,
                            `fonction_id` int(12) NOT NULL,
                            `authorized` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "users") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."users` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `login` varchar(50) NOT NULL,
                            `pw` varchar(400) NOT NULL,
                            `groupes_visibles` varchar(250) NOT NULL,
                            `derniers` text NULL,
                            `key_tempo` varchar(100) NULL,
                            `last_pw_change` varchar(30) NULL,
                            `last_pw` text NULL,
                            `admin` tinyint(1) NOT null DEFAULT '0',
                            `fonction_id` varchar(255) NULL,
                            `groupes_interdits` varchar(255) NULL,
                            `last_connexion` varchar(30) NULL,
                            `gestionnaire` int(11) NOT null DEFAULT '0',
                            `email` varchar(300) NOT NULL DEFAULT 'none',
                            `favourites` varchar(300) NULL,
                            `latest_items` varchar(300) NULL,
                            `personal_folder` int(1) NOT null DEFAULT '0',
                            `disabled` tinyint(1) NOT null DEFAULT '0',
                            `no_bad_attempts` tinyint(1) NOT null DEFAULT '0',
                            `can_create_root_folder` tinyint(1) NOT null DEFAULT '0',
                            `read_only` tinyint(1) NOT null DEFAULT '0',
                            `timestamp` varchar(30) NOT null DEFAULT '0',
                            `user_language` varchar(50) NOT null DEFAULT '0',
                            `name` varchar(100) NULL,
                            `lastname` varchar(100) NULL,
                            `session_end` varchar(30) NULL,
                            `isAdministratedByRole` tinyint(5) NOT null DEFAULT '0',
                            `psk` varchar(400) NULL,
                            `ga` varchar(50) NULL,
                            `ga_temporary_code` VARCHAR(20) NOT NULL DEFAULT 'none',
                            `avatar` varchar(255) NULL,
                            `avatar_thumb` varchar(255) NULL,
                            `upgrade_needed` BOOLEAN NOT NULL DEFAULT FALSE,
                            `treeloadstrategy` varchar(30) NOT null DEFAULT 'full',
                            `can_manage_all_users` tinyint(1) NOT NULL DEFAULT '0',
                            `usertimezone` VARCHAR(50) NOT NULL DEFAULT 'not_defined',
                            `agses-usercardid` VARCHAR(50) NOT NULL DEFAULT '0',
                            `encrypted_psk` text NULL,
                            `user_ip` varchar(400) NOT null DEFAULT 'none',
                            `user_api_key` varchar(500) NOT null DEFAULT 'none',
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `login` (`login`)
                            ) CHARSET=utf8;"
                        );

                        require_once "../includes/config/include.php";
                        // check that admin accounts doesn't exist
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$var['tbl_prefix']."users` WHERE login = 'admin'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `".$var['tbl_prefix']."users` (`id`, `login`, `pw`, `admin`, `gestionnaire`, `personal_folder`, `groupes_visibles`, `email`, `encrypted_psk`, `last_pw_change`) VALUES ('1', 'admin', '".bCrypt($var['admin_pwd'], '13')."', '1', '0', '0', '', '', '', '".time()."')"
                            );
                        } else {
                            $mysqli_result = mysqli_query($dbTmp, "UPDATE `".$var['tbl_prefix']."users` SET `pw` = '".bCrypt($var['admin_pwd'], '13')."' WHERE login = 'admin' AND id = '1'");
                        }

                        // check that API doesn't exist
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$var['tbl_prefix']."users` WHERE id = '".API_USER_ID."'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `".$var['tbl_prefix']."users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`) VALUES ('".API_USER_ID."', 'API', '', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0')"
                            );
                        }

                        // check that OTV doesn't exist
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$var['tbl_prefix']."users` WHERE id = '".OTV_USER_ID."'"));
                        if ($tmp === 0) {
                            $mysqli_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `".$var['tbl_prefix']."users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`) VALUES ('".OTV_USER_ID."', 'OTV', '', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0')"
                            );
                        }
                    } elseif ($task === "tags") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."tags` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `tag` varchar(30) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "log_system") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."log_system` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `type` varchar(20) NOT NULL,
                            `date` varchar(30) NOT NULL,
                            `label` text NOT NULL,
                            `qui` varchar(255) NOT NULL,
                            `field_1` varchar(250) DEFAULT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "files") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."files` (
                            `id` int(11) NOT null AUTO_INCREMENT,
                            `id_item` int(11) NOT NULL,
                            `name` varchar(100) NOT NULL,
                            `size` int(10) NOT NULL,
                            `extension` varchar(10) NOT NULL,
                            `type` varchar(255) NOT NULL,
                            `file` varchar(50) NOT NULL,
                            `status` varchar(50) NOT NULL DEFAULT '0',
                            PRIMARY KEY (`id`)
                           ) CHARSET=utf8;"
                        );
                    } elseif ($task === "cache") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."cache` (
                            `increment_id`INT(12) NOT NULL AUTO_INCREMENT,
                            `id` int(12) NOT NULL,
                            `label` varchar(500) NOT NULL,
                            `description` text NOT NULL,
                            `tags` text DEFAULT NULL,
                            `id_tree` int(12) NOT NULL,
                            `perso` tinyint(1) NOT NULL,
                            `restricted_to` varchar(200) DEFAULT NULL,
                            `login` varchar(200) DEFAULT NULL,
                            `folder` varchar(300) NOT NULL,
                            `author` varchar(50) NOT NULL,
                            `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
                            `timestamp` varchar(50) DEFAULT NULL,
                            `url` varchar(500) NOT NULL DEFAULT '0',
                            `encryption_type` VARCHAR(50) DEFAULT NULL DEFAULT '0',
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "roles_title") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."roles_title` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `title` varchar(50) NOT NULL,
                            `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
                            `complexity` INT(5) NOT null DEFAULT '0',
                            `creator_id` int(11) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "roles_values") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."roles_values` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            `role_id` int(12) NOT NULL,
                            `folder_id` int(12) NOT NULL,
                            `type` varchar(5) NOT NULL DEFAULT 'R',
                            KEY `role_id_idx` (`role_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "kb") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."kb` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `category_id` int(12) NOT NULL,
                            `label` varchar(200) NOT NULL,
                            `description` text NOT NULL,
                            `author_id` int(12) NOT NULL,
                            `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "kb_categories") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."kb_categories` (
                            `id` int(12) NOT null AUTO_INCREMENT,
                            `category` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "kb_items") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."kb_items` (
                            `kb_id` int(12) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`kb_id`)
                           ) CHARSET=utf8;"
                        );
                    } elseif ($task == "restriction_to_roles") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."restriction_to_roles` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            `role_id` int(12) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "languages") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."languages` (
                            `id` INT(10) NOT null AUTO_INCREMENT PRIMARY KEY,
                            `name` VARCHAR(50) NOT null ,
                            `label` VARCHAR(50) NOT null ,
                            `code` VARCHAR(10) NOT null ,
                            `flag` VARCHAR(30) NOT NULL
                            ) CHARSET=utf8;"
                        );

                        // add lanaguages
                        $tmp = mysqli_num_rows(mysqli_query($dbTmp, "SELECT * FROM `".$var['tbl_prefix']."languages` WHERE name = 'french'"));
                        if ($tmp[0] == 0) {
                            $mysql_result = mysqli_query(
                                $dbTmp,
                                "INSERT INTO `".$var['tbl_prefix']."languages` (`name`, `label`, `code`, `flag`) VALUES
                                ('french', 'French' , 'fr', 'fr.png'),
                                ('english', 'English' , 'us', 'us.png'),
                                ('spanish', 'Spanish' , 'es', 'es.png'),
                                ('german', 'German' , 'de', 'de.png'),
                                ('czech', 'Czech' , 'cz', 'cz.png'),
                                ('italian', 'Italian' , 'it', 'it.png'),
                                ('russian', 'Russian' , 'ru', 'ru.png'),
                                ('turkish', 'Turkish' , 'tr', 'tr.png'),
                                ('norwegian', 'Norwegian' , 'no', 'no.png'),
                                ('japanese', 'Japanese' , 'ja', 'ja.png'),
                                ('portuguese', 'Portuguese' , 'pr', 'pr.png'),
                                ('portuguese_br', 'Portuguese (Brazil)' , 'pr-bt', 'pr-bt.png'),
                                ('chinese', 'Chinese' , 'cn', 'cn.png'),
                                ('swedish', 'Swedish' , 'se', 'se.png'),
                                ('dutch', 'Dutch' , 'nl', 'nl.png'),
                                ('catalan', 'Catalan' , 'ct', 'ct.png'),
                                ('bulgarian', 'Bulgarian' , 'bg', 'bg.png'),
                                ('greek', 'Greek' , 'gr', 'gr.png'),
                                ('hungarian', 'Hungarian' , 'hu', 'hu.png'),
                                ('polish', 'Polish' , 'pl', 'pl.png'),
                                ('romanian', 'Romanian' , 'ro', 'ro.png'),
                                ('ukrainian', 'Ukrainian' , 'ua', 'ua.png'),
                                ('vietnamese', 'Vietnamese' , 'vi', 'vi.png'),
                                ('estonian', 'Estonian' , 'ee', 'ee.png');"
                            );
                        }
                    } elseif ($task === "emails") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."emails` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `timestamp` INT(30) NOT null ,
                            `subject` VARCHAR(255) NOT null ,
                            `body` TEXT NOT null ,
                            `receivers` VARCHAR(255) NOT null ,
                            `status` VARCHAR(30) NOT NULL,
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "automatic_del") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."automatic_del` (
                            `item_id` int(11) NOT NULL,
                            `del_enabled` tinyint(1) NOT NULL,
                            `del_type` tinyint(1) NOT NULL,
                            `del_value` varchar(35) NOT NULL,
                            PRIMARY KEY (`item_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "items_edition") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."items_edition` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `item_id` int(11) NOT NULL,
                            `user_id` int(12) NOT NULL,
                            `timestamp` varchar(50) NOT NULL,
                            KEY `item_id_idx` (`item_id`),
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "categories") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."categories` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `parent_id` int(12) NOT NULL,
                            `title` varchar(255) NOT NULL,
                            `level` int(2) NOT NULL,
                            `description` text NULL,
                            `type` varchar(50) NULL default '',
                            `order` int(12) NOT NULL default '0',
                            `encrypted_data` tinyint(1) NOT NULL default '1',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "categories_items") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."categories_items` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `field_id` int(11) NOT NULL,
                            `item_id` int(11) NOT NULL,
                            `data` text NOT NULL,
                            `data_iv` text NOT NULL,
                            `encryption_type` VARCHAR(20) NOT NULL DEFAULT 'not_set',
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "categories_folders") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."categories_folders` (
                            `id_category` int(12) NOT NULL,
                            `id_folder` int(12) NOT NULL,
                            PRIMARY KEY (`id_category`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "api") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."api` (
                            `id` int(20) NOT NULL AUTO_INCREMENT,
                            `type` varchar(15) NOT NULL,
                            `label` varchar(255) NOT NULL,
                            `value` varchar(255) NOT NULL,
                            `timestamp` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "otv") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."otv` (
                            `id` int(10) NOT NULL AUTO_INCREMENT,
                            `timestamp` text NOT NULL,
                            `code` varchar(100) NOT NULL,
                            `item_id` int(12) NOT NULL,
                            `originator` int(12) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "suggestion") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."suggestion` (
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
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );

                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."export` (
                            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
                            `id` int(12) NOT NULL,
                            `label` varchar(500) NOT NULL,
                            `login` varchar(100) NOT NULL,
                            `description` text NOT NULL,
                            `pw` text NOT NULL,
                            `path` varchar(500) NOT NULL,
                            `email` varchar(500) NOT NULL default 'none',
                            `url` varchar(500) NOT NULL default 'none',
                            `kbs` varchar(500) NOT NULL default 'none',
                            `tags` varchar(500) NOT NULL default 'none',
                            PRIMARY KEY (`increment_id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "tokens") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."tokens` (
                            `id` int(12) NOT NULL AUTO_INCREMENT,
                            `user_id` int(12) NOT NULL,
                            `token` varchar(255) NOT NULL,
                            `reason` varchar(255) NOT NULL,
                            `creation_timestamp` varchar(50) NOT NULL,
                            `end_timestamp` varchar(50) NOT NULL,
                            PRIMARY KEY (`id`)
                            ) CHARSET=utf8;"
                        );
                    } elseif ($task === "items_change") {
                        $mysqli_result = mysqli_query(
                            $dbTmp,
                            "CREATE TABLE IF NOT EXISTS `".$var['tbl_prefix']."items_change` (
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
                    }
                }
                // answer back
                if ($mysqli_result) {
                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'", "task" : "'.$task.'", "activity" : "'.$activity.'"}]';
                } else {
                    echo '[{"error" : "'.addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_error())).'", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'", "table" : "'.$task.'"}]';
                }
            } else {
                echo '[{"error" : "'.addslashes(str_replace(array("'", "\n", "\r"), array('"', '', ''), mysqli_connect_error())).'", "result" : "Failed", "multiple" : ""}]';
            }

            mysqli_close($dbTmp);
            // Destroy session without writing to disk
            define('NODESTROY_SESSION', 'true');
            session_destroy();
            break;

        case "step_6":
            //decrypt
            require_once 'libs/aesctr.php'; // AES Counter Mode implementation
            $activity = Encryption\Crypt\aesctr::decrypt($post_activity, "cpm", 128);
            $data_sent = Encryption\Crypt\aesctr::decrypt($post_data, "cpm", 128);
            $data_sent = json_decode($data_sent, true);
            $task = Encryption\Crypt\aesctr::decrypt($post_task, "cpm", 128);
            $json = Encryption\Crypt\aesctr::decrypt($post_db, "cpm", 128);
            $db = json_decode($json, true);

            $dbTmp = mysqli_connect(
                $db['db_host'],
                $db['db_login'],
                $db['db_pw'],
                $db['db_bdd'],
                $db['db_port']
            );

            // read install variables
            $result = mysqli_query($dbTmp, "SELECT * FROM `_install`");
            while ($row = $result->fetch_array()) {
                $var[$row[0]] = $row[1];
            }

            // launch
            if (empty($var['sk_path'])) {
                $skFile = $var['abspath'].'/includes/sk.php';
                $securePath = $var['abspath'];
            } else {
                //ensure $var['sk_path'] has no trailing slash
                $var['sk_path'] = rtrim($var['sk_path'], '/\\');
                $skFile = $var['sk_path'].'/sk.php';
                $securePath = $var['sk_path'];
            }

            $events = "";

            if ($activity === "file") {
                if ($task === "settings.php") {
                    // first is to create teampass-seckey.txt
                    // 0- check if exists
                    $filename_seckey = $securePath."/teampass-seckey.txt";

                    if (file_exists($filename_seckey)) {
                        if (!copy($filename_seckey, $filename_seckey.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                            echo '[{"error" : "File `$filename_seckey` already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                            break;
                        } else {
                            unlink($filename);
                        }
                    }

                    // 1- generate saltkey
                    require_once '../includes/libraries/Encryption/Encryption/Crypto.php';
                    require_once '../includes/libraries/Encryption/Encryption/Encoding.php';
                    require_once '../includes/libraries/Encryption/Encryption/DerivedKeys.php';
                    require_once '../includes/libraries/Encryption/Encryption/Key.php';
                    require_once '../includes/libraries/Encryption/Encryption/KeyOrPassword.php';
                    require_once '../includes/libraries/Encryption/Encryption/File.php';
                    require_once '../includes/libraries/Encryption/Encryption/RuntimeTests.php';
                    require_once '../includes/libraries/Encryption/Encryption/KeyProtectedByPassword.php';
                    require_once '../includes/libraries/Encryption/Encryption/Core.php';

                    $key = \Defuse\Crypto\Key::createNewRandomKey();
                    $new_salt = $key->saveToAsciiSafeString();

                    // 2- store key in file
                    file_put_contents(
                        $filename_seckey,
                        $new_salt
                    );

                    // Now create settings file
                    $filename = "../includes/config/settings.php";

                    if (file_exists($filename)) {
                        if (!copy($filename, $filename.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                            echo '[{"error" : "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                            break;
                        } else {
                            unlink($filename);
                        }
                    }

                    // Encrypt the DB password
                    $encrypted_text = encryptFollowingDefuse(
                        $db['db_pw'],
                        $new_salt
                    )['string'];

                    // Open and write Settings file
                    $file_handler = fopen($filename, 'w');
                    $result = fwrite(
                        $file_handler,
                        utf8_encode(
                            "<?php
global \$lang, \$txt, \$pathTeampas, \$urlTeampass, \$pwComplexity, \$mngPages;
global \$server, \$user, \$pass, \$database, \$pre, \$db, \$port, \$encoding;

### DATABASE connexion parameters ###
\$server = \"".$db['db_host']."\";
\$user = \"".$db['db_login']."\";
\$pass = \"".str_replace("$", "\\$", $encrypted_text)."\";
\$database = \"".$db['db_bdd']."\";
\$pre = \"".$var['tbl_prefix']."\";
\$port = ".$db['db_port'].";
\$encoding = \"".$session_db_encoding."\";

@date_default_timezone_set(\$_SESSION['settings']['timezone']);
@define('SECUREPATH', '".$securePath."');
if (file_exists(\"".str_replace('\\', '/', $skFile)."\")) {
    require_once \"".str_replace('\\', '/', $skFile)."\";
}
"
                        )
                    );
                    fclose($file_handler);
                    if ($result === false) {
                        echo '[{"error" : "Setting.php file could not be created. Please check the path and the rights", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                    }
                } elseif ($task === "sk.php") {
//Create sk.php file
                    if (file_exists($skFile)) {
                        if (!copy($skFile, $skFile.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                            echo '[{"error" : "sk.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                            break;
                        } else {
                            unlink($skFile);
                        }
                    }
                    $file_handler = fopen($skFile, 'w');

                    $result = fwrite(
                        $file_handler,
                        utf8_encode(
                            "<?php
@define('COST', '13'); // Don't change this.
@define('AKEY', '');
@define('IKEY', '');
@define('SKEY', '');
@define('HOST', '');
?>"
                        )
                    );
                    fclose($file_handler);

                    // finalize
                    if ($result === false) {
                        echo '[{"error" : "sk.php file could not be created. Please check the path and the rights.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                    }
                } elseif ($task === "security") {
                    # Sort out the file permissions

                    // is server Windows or Linux?
                    if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
                        // Change directory permissions
                        $result = chmodRecursive($session_abspath, 0770, 0740);
                        if ($result) {
                            $result = chmodRecursive($session_abspath.'/files', 0770, 0770);
                        }
                        if ($result) {
                            $result = chmodRecursive($session_abspath.'/upload', 0770, 0770);
                        }
                    }

                    if ($result === false) {
                        echo '[{"error" : "Cannot change directory permissions - please fix manually", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                    } else {
                        echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                    }
                } elseif ($task === "csrfp-token") {
                    // update CSRFP TOKEN
                    $csrfp_file_sample = "../includes/libraries/csrfp/libs/csrfp.config.sample.php";
                    $csrfp_file = "../includes/libraries/csrfp/libs/csrfp.config.php";
                    if (file_exists($csrfp_file)) {
                        if (!copy($csrfp_file, $csrfp_file.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                            echo '[{"error" : "csrfp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                            break;
                        } else {
                            $events .= "The file $csrfp_file already exist. A copy has been created.<br />";
                        }
                    }
                    unlink($csrfp_file); // delete existing csrfp.config file
                    copy($csrfp_file_sample, $csrfp_file); // make a copy of csrfp.config.sample file
                    $data = file_get_contents($csrfp_file);
                    $newdata = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "'.bin2hex(openssl_random_pseudo_bytes(25)).'"', $data);
                    $jsUrl = $data_sent['url_path'].'/includes/libraries/csrfp/js/csrfprotector.js';
                    $newdata = str_replace('"jsUrl" => ""', '"jsUrl" => "'.$jsUrl.'"', $newdata);
                    file_put_contents("../includes/libraries/csrfp/libs/csrfp.config.php", $newdata);

                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
            } elseif ($activity === "install") {
                if ($task === "cleanup") {
                    // Mark a tag to force Install stuff (folders, files and table) to be cleanup while first login
                    mysqli_query($dbTmp, "INSERT INTO `".$var['tbl_prefix']."misc` (`type`, `intitule`, `valeur`) VALUES ('install', 'clear_install_folder', 'true')");

                    echo '[{"error" : "", "index" : "'.$post_index.'", "multiple" : "'.$post_multiple.'"}]';
                }
            }

            mysqli_close($dbTmp);
            // Destroy session without writing to disk
            define('NODESTROY_SESSION', 'true');
            session_destroy();
            break;
    }
}
