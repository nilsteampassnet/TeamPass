<?php
/**
 * @file          upgrade.ajax.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';

// manage settings.php file
if (!file_exists("../includes/config/settings.php")) {
    if (file_exists("../includes/settings.php")) {
        // since 2.1.27, this file has changed location
        if (copy("../includes/settings.php", "../includes/config/settings.php")) {
            unlink("../includes/settings.php");
        } else {
            echo 'document.getElementById("res_step1_error").innerHTML = '.
                '"Could not copy /includes/settings.php to /includes/config/settings.php! '.
                'Please do it manually and press button Launch.";';
            echo 'document.getElementById("loader").style.display = "none";';
            exit;
        }
    } else {
        echo 'document.getElementById("res_step1_error").innerHTML = '.
            '"File settings.php does not exist in folder includes/! '.
            'If it is an upgrade, it should be there, otherwise select install!";';
        echo 'document.getElementById("loader").style.display = "none";';
        exit;
    }
}
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

################
## Function permits to get the value from a line
################
function getSettingValue($val)
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";")-1)));
}

################
## Function permits to check if a column exists, and if not to add it
################
function addColumnIfNotExist($db, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $dbTmp;
    $exists = false;
    $columns = mysqli_query($dbTmp, "show columns from $db");
    while ($c = mysqli_fetch_assoc( $columns)) {
        if ($c['Field'] == $column) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        return mysqli_query($dbTmp, "ALTER TABLE `$db` ADD `$column`  $columnAttr");
    }
}

function addIndexIfNotExist($table, $index, $sql ) {
    global $dbTmp;

    $mysqli_result = mysqli_query($dbTmp, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query($dbTmp, "ALTER TABLE `$table` " . $sql);
    }

    return $res;
}

function tableExists($tablename, $database = false)
{
    global $dbTmp;

    $res = mysqli_query($dbTmp,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$_SESSION['database']."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) return true;
    else return false;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "step0":
            // erase session table
            $_SESSION = array();
            setcookie('pma_end_session');
            session_destroy();

            echo 'document.getElementById("res_step0").innerHTML = "";';
            require_once 'libs/aesctr.php';
            require_once "../includes/config/settings.php";

            $_SESSION['settings']['cpassman_dir'] = "..";
            require_once '../includes/libraries/PasswordLib/Random/Generator.php';
            require_once '../includes/libraries/PasswordLib/Random/Source.php';
            require_once '../includes/libraries/PasswordLib/Random/Source/MTRand.php';
            require_once '../includes/libraries/PasswordLib/Random/Source/Rand.php';
            require_once '../includes/libraries/PasswordLib/Random/Source/UniqID.php';
            require_once '../includes/libraries/PasswordLib/Random/Source/URandom.php';
            require_once '../includes/libraries/PasswordLib/Random/Source/MicroTime.php';
            require_once '../includes/libraries/PasswordLib/Random/Source/CAPICOM.php';
            require_once '../includes/libraries/PasswordLib/Random/Mixer.php';
            require_once '../includes/libraries/PasswordLib/Random/AbstractMixer.php';
            require_once '../includes/libraries/PasswordLib/Random/Mixer/Hash.php';
            require_once '../includes/libraries/PasswordLib/Password/AbstractPassword.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/Hash.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/Crypt.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/SHA256.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/SHA512.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/PHPASS.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/PHPBB.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/PBKDF.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/MediaWiki.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/MD5.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/Joomla.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/Drupal.php';
            require_once '../includes/libraries/PasswordLib/Password/Implementation/APR1.php';
            require_once '../includes/libraries/PasswordLib/PasswordLib.php';
            $pwdlib = new PasswordLib\PasswordLib();

            //connect to db and check user is granted
            $link = mysqli_connect(
                $server,
                $user,
                $pass,
                $database,
                $port
            );

            $user_info = mysqli_fetch_array(mysqli_query($link,
                "SELECT pw, admin FROM ".$pre."users
                WHERE login='".mysqli_escape_string($link, stripslashes($_POST['login']))."'")
            );

            if ($pwdlib->verifyPasswordHash(Encryption\Crypt\aesctr::decrypt(base64_decode($_POST['pwd']), "cpm", 128), $user_info['pw']) === true && $user_info['admin'] === "1") {
                echo 'document.getElementById("but_next").disabled = "";';
                echo 'document.getElementById("res_step0").innerHTML = "User is granted.";';
                echo 'document.getElementById("step").value = "1";';
                echo 'document.getElementById("user_granted").value = "1";';
                $_SESSION['user_granted'] = true;
            } else {
                echo 'document.getElementById("but_next").disabled = "disabled";';
                echo 'document.getElementById("res_step0").innerHTML = "This user is not allowed!";';
                echo 'document.getElementById("user_granted").value = "0";';
                $_SESSION['user_granted'] = false;
            }
            echo 'document.getElementById("loader").style.display = "none";';
            break;

        case "step1":

            if ($_SESSION['user_granted'] !== "1") {
                echo 'document.getElementById("res_step1").innerHTML = "User not connected anymore!";';
                echo 'document.getElementById("loader").style.display = "none";';
                break;
            }

            $_SESSION['fullurl'] = $_POST['fullurl'];
            $abspath = str_replace('\\', '/', $_POST['abspath']);
            $_SESSION['abspath'] = $abspath;
            if (substr($abspath, strlen($abspath)-1) == "/") {
                $abspath = substr($abspath, 0, strlen($abspath)-1);
            }
            $okWritable = true;
            $okExtensions = true;
            $txt = "";
            $x=1;
            $tab = array(
                $abspath."/includes/config/settings.php",
                $abspath."/includes/libraries/csrfp/libs/",
                $abspath."/install/",
                $abspath."/includes/",
                $abspath."/includes/config/",
                $abspath."/includes/avatars/",
                $abspath."/files/",
                $abspath."/upload/"
            );
            foreach ($tab as $elem) {
                // try to create it if not existing
                if(substr($elem, -1) === '/' && !is_dir($elem)) {
                    mkdir($elem);
                }
                // check if writable
                if (is_writable($elem)) {
                    $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">'.
                        $elem.'&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
                } else {
                    $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">'.
                        $elem.'&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
                    $okWritable = false;
                }
                $x++;
            }

            if (!extension_loaded('mcrypt')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mcrypt\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mcrypt\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('openssl')) {
                //$okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"openssl\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"openssl\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('gd')) {
                //$okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"gd\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"gd\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('mbstring')) {
                //$okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mbstring\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mbstring\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('bcmath')) {
                //$okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"bcmath\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"bcmath\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('iconv')) {
                //$okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"iconv\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"iconv\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('xml')) {
                //$okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"xml\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"xml\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('curl')) {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"curl\"'.
                    '&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"curl\"'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (ini_get('max_execution_time')<60) {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP \"Maximum '.
                    'execution time\" is set to '.ini_get('max_execution_time').' seconds.'.
                    ' Please try to set to 60s at least until Upgrade is finished.&nbsp;'.
                    '&nbsp;<img src=\"images/minus-circle.png\"></span> <br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP \"Maximum '.
                    'execution time\" is set to '.ini_get('max_execution_time').' seconds'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (version_compare(phpversion(), '5.5.0', '<')) {
                $okVersion = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.
                    phpversion().' is not OK (minimum is 5.5.0) &nbsp;&nbsp;'.
                    '<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.
                    phpversion().' is OK&nbsp;&nbsp;<img src=\"images/tick-circle.png\">'.
                    '</span><br />';
            }

            //get infos from SETTINGS.PHP file
            $filename = "../includes/config/settings.php";
            $events = "";
            if (file_exists($filename)) {
                //copy some constants from this existing file
                $settingsFile = file($filename);
                while (list($key,$val) = each($settingsFile)) {
                    if (substr_count($val, 'charset')>0) {
                        $_SESSION['charset'] = getSettingValue($val);
                    } elseif (substr_count($val, '@define(')>0 && substr_count($val, 'SALT')>0) {
                        $_SESSION['encrypt_key'] = substr($val, 17, strpos($val, "')")-17);
                    } elseif (substr_count($val, '$smtp_server')>0) {
                        $_SESSION['smtp_server'] = getSettingValue($val);
                    } elseif (substr_count($val, '$smtp_auth')>0) {
                        $_SESSION['smtp_auth'] = getSettingValue($val);
                    } elseif (substr_count($val, '$smtp_auth_username')>0) {
                        $_SESSION['smtp_auth_username'] = getSettingValue($val);
                    } elseif (substr_count($val, '$smtp_auth_password')>0) {
                        $_SESSION['smtp_auth_password'] = getSettingValue($val);
                    } elseif (substr_count($val, '$smtp_port')>0) {
                        $_SESSION['smtp_port'] = getSettingValue($val);
                    } elseif (substr_count($val, '$smtp_security')>0) {
                        $_SESSION['smtp_security'] = getSettingValue($val);
                    } elseif (substr_count($val, '$email_from')>0) {
                        $_SESSION['email_from'] = getSettingValue($val);
                    } elseif (substr_count($val, '$email_from_name')>0) {
                        $_SESSION['email_from_name'] = getSettingValue($val);
                    } elseif (substr_count($val, '$server')>0) {
                        $_SESSION['server'] = getSettingValue($val);
                    } elseif (substr_count($val, '$user')>0) {
                        $_SESSION['user'] = getSettingValue($val);
                    } elseif (substr_count($val, '$pass')>0) {
                        $_SESSION['pass'] = getSettingValue($val);
                    } elseif (substr_count($val, '$port')>0) {
                        $_SESSION['port'] = getSettingValue($val);
                    } elseif (substr_count($val, '$database')>0) {
                        $_SESSION['database'] = getSettingValue($val);
                    } elseif (substr_count($val, '$pre')>0) {
                        $_SESSION['pre'] = getSettingValue($val);
                    } elseif (substr_count($val, 'require_once "')>0 && substr_count($val, 'sk.php')>0) {
                        $_SESSION['sk_file'] = substr($val, 14, strpos($val, '";')-14);
                    }
                }
            }
            if (
                isset($_SESSION['sk_file']) && !empty($_SESSION['sk_file'])
                && file_exists($_SESSION['sk_file'])
            ) {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">sk.php file'.
                    ' found in \"'.addslashes($_SESSION['sk_file']).'\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\">'.
                    '</span><br />';
                //copy some constants from this existing file
                $skFile = file($_SESSION['sk_file']);
                while (list($key,$val) = each($skFile)) {
                    if (substr_count($val, "@define('SALT'")>0) {
                        $_SESSION['encrypt_key'] = substr($val, 17, strpos($val, "')")-17);
                        echo '$("#session_salt").val("'.$_SESSION['encrypt_key'].'");';
                    }
                }
            }

            if (!isset($_SESSION['encrypt_key']) || empty($_SESSION['encrypt_key'])) {
                // check if 2.1.27 already installed
                $defuse_file = substr($_SESSION['sk_file'], 0, strrpos($_SESSION['sk_file'], "/"))."/teampass-seckey.txt";
                if (file_exists($defuse_file)) {
                    $okEncryptKey = true;
                    $_SESSION['tp_defuse_installed'] = true;
                    $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">Defuse encryption key is defined&nbsp;&nbsp;<img src=\"images/tick-circle.png\">'.
                        '</span><br />';
                } else {
                    $okEncryptKey = false;
                    $_SESSION['tp_defuse_installed'] = false;
                    $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">Encryption Key (SALT) '.
                        ' could not be recovered &nbsp;&nbsp;'.
                        '<img src=\"images/minus-circle.png\"></span><br />';
                    }
            } else {
                $okEncryptKey = true;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">Encryption Key (SALT) is available&nbsp;&nbsp;<img src=\"images/tick-circle.png\">'.
                    '</span><br />';
            }

            if ($okWritable == true && $okExtensions == true && $okEncryptKey == true) {
                echo 'document.getElementById("but_next").disabled = "";';
                echo 'document.getElementById("res_step1").innerHTML = "Elements are OK.";';
            } else {
                echo 'document.getElementById("but_next").disabled = "disabled";';
                echo 'document.getElementById("res_step1").innerHTML = "Correct the shown '.
                    'errors and click on button Launch to refresh";';
            }

            echo 'document.getElementById("res_step1").innerHTML = "'.$txt.'";';
            echo 'document.getElementById("loader").style.display = "none";';
            break;

            #==========================
        case "step2":
            $res = "";

            if ($_SESSION['user_granted'] !== "1") {
                echo 'document.getElementById("res_step2").innerHTML = "User not connected anymore!";';
                echo 'document.getElementById("loader").style.display = "none";';
                break;
            }
            //decrypt the password
            // AES Counter Mode implementation
            require_once 'libs/aesctr.php';

            // connexion
            if (
                mysqli_connect(
                    $_SESSION['server'],
                    $_SESSION['user'],
                    $_SESSION['pass'],
                    $_SESSION['database'],
                    $_SESSION['port']
                )
            ) {
                $dbTmp = mysqli_connect(
                    $_SESSION['server'],
                    $_SESSION['user'],
                    $_SESSION['pass'],
                    $_SESSION['database'],
                    $_SESSION['port']
                );
                $res = "Connection is successful";
                echo 'document.getElementById("but_next").disabled = "";';

                // check in db if previous saltk exists
                $db_sk = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT count(*) FROM ".$_SESSION['pre']."misc
                WHERE type='admin' AND intitule = 'saltkey_ante_2127'"));
                if (!empty($_POST['previous_sk']) || !empty($_POST['session_salt'])) {
                    // get sk
                    if (!empty($_POST['session_salt'])) {
                        $sk_val = filter_var($_POST['session_salt'], FILTER_SANITIZE_STRING);
                    } else {
                        $sk_val = filter_var($_POST['previous_sk'], FILTER_SANITIZE_STRING);
                    }

                    // upidate
                    if (!empty($db_sk[0])) {
                        mysqli_query($dbTmp,
                            "UPDATE `".$_SESSION['pre']."misc`
                            SET `valeur` = '".$sk_val."'
                            WHERE type = 'admin' AND intitule = 'saltkey_ante_2127'"
                        );
                    } else {
                        mysqli_query($dbTmp,
                            "INSERT INTO `".$_SESSION['pre']."misc`
                            (`valeur`, `type`, `intitule`)
                            VALUES ('".$sk_val."', 'admin', 'saltkey_ante_2127')"
                        );
                    }
                } elseif (empty($db_sk[0])) {
                    $res = "Please provide the previous saltkey.";
                    echo 'document.getElementById("but_next").disabled = "disabled";';
                    echo 'document.getElementById("res_step2").innerHTML = "'.$res.'";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    echo 'document.getElementById("no_encrypt_key").style.display = "";';
                }

                //What CPM version
                if (@mysqli_query($dbTmp,
                    "SELECT valeur FROM ".$_POST['tbl_prefix']."misc
                    WHERE type='admin' AND intitule = 'cpassman_version'"
                )) {
                    $tmpResult = mysqli_query($dbTmp,
                        "SELECT valeur FROM ".$_POST['tbl_prefix']."misc
                        WHERE type='admin' AND intitule = 'cpassman_version'"
                    );
                    $cpmVersion = mysqli_fetch_row($tmpResult);
                    echo 'document.getElementById("actual_cpm_version").value = "'.
                        $cpmVersion[0].'";';
                } else {
                    echo 'document.getElementById("actual_cpm_version").value = "0";';
                }

                //Get some infos from DB
                if (@mysqli_fetch_row(
                        mysqli_query($dbTmp, "SELECT valeur FROM ".$_POST['tbl_prefix']."misc
                    WHERE type='admin' AND intitule = 'utf8_enabled'")
                    )
                ) {
                    $cpmIsUTF8 = mysqli_fetch_row(mysqli_query($dbTmp,
                        "SELECT valeur FROM ".$_POST['tbl_prefix']."misc
                        WHERE type='admin' AND intitule = 'utf8_enabled'")
                    );
                    echo 'document.getElementById("cpm_isUTF8").value = "'.$cpmIsUTF8[0].'";';
                    $_SESSION['utf8_enabled'] = $cpmIsUTF8[0];
                } else {
                    echo 'document.getElementById("cpm_isUTF8").value = "0";';
                    $_SESSION['utf8_enabled'] = 0;
                }

                // put TP in maintenance mode or not
                @mysqli_query($dbTmp,
                "UPDATE `".$_SESSION['pre']."misc`
                    SET `valeur` = 'maintenance_mode'
                    WHERE type = 'admin' AND intitule = '".$_POST['no_maintenance_mode']."'"
                );

                echo 'document.getElementById("dump").style.display = "";';
            } else {
                $res = "Impossible to get connected to server. Error is: ".addslashes(mysqli_connect_error());
                echo 'document.getElementById("but_next").disabled = "disabled";';
            }

            echo 'document.getElementById("res_step2").innerHTML = "'.$res.'";';
            echo 'document.getElementById("loader").style.display = "none";';
            break;

            #==========================
        case "step3":

            if ($_SESSION['user_granted'] !== "1") {
                echo 'document.getElementById("res_step3").innerHTML = "User not connected anymore!";';
                echo 'document.getElementById("loader").style.display = "none";';
                break;
            }

            mysqli_connect(
                $_SESSION['server'],
                $_SESSION['user'],
                $_SESSION['pass'],
                $_SESSION['database'],
                $_SESSION['port']
            );
            $dbTmp = mysqli_connect(
                $_SESSION['server'],
                $_SESSION['user'],
                $_SESSION['pass'],
                $_SESSION['database'],
                $_SESSION['port']
            );
            $status = "";

            //rename tables
            if (
                isset($_POST['prefix_before_convert']) && $_POST['prefix_before_convert'] == "true"
            ) {
                $tables =mysqli_query($dbTmp,'SHOW TABLES');
                while ($table = mysqli_fetch_row($tables)) {
                    if (tableExists("old_".$table[0]) != 1 && substr($table[0], 0, 4) != "old_") {
                        mysqli_query($dbTmp,"CREATE TABLE old_".$table[0]." LIKE ".$table[0]);
                        mysqli_query($dbTmp,"INSERT INTO old_".$table[0]." SELECT * FROM ".$table[0]);
                    }
                }
            }

            //convert database
            mysqli_query($dbTmp,
                "ALTER DATABASE `".$_SESSION['database']."`
                DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci"
            );

            //convert tables
            $res = mysqli_query($dbTmp,"SHOW TABLES FROM `".$_SESSION['database']."`");
            while ($table = mysqli_fetch_row($res)) {
                if (substr($table[0], 0, 4) != "old_") {
                    mysqli_query($dbTmp,
                        "ALTER TABLE ".$_SESSION['database'].".`{$table[0]}`
                        CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci"
                    );
                    mysqli_query($dbTmp,
                        "ALTER TABLE".$_SESSION['database'].".`{$table[0]}`
                        DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci"
                    );
                }
            }

            echo 'document.getElementById("res_step3").innerHTML = "Done!";';
            echo 'document.getElementById("loader").style.display = "none";';
            echo 'document.getElementById("but_next").disabled = "";';
            echo 'document.getElementById("but_launch").disabled = "disabled";';

            mysqli_close($dbTmp);
            break;

            #==========================


            //=============================
        case "step5":

            if ($_SESSION['user_granted'] !== "1") {
                echo 'document.getElementById("res_step5").innerHTML = "User not connected anymore!";';
                echo 'document.getElementById("loader").style.display = "none";';
                break;
            }

            $filename = "../includes/config/settings.php";
            $events = "";
            if (file_exists($filename)) {
                //Do a copy of the existing file
                if (!copy(
                    $filename,
                    $filename.'.'.date(
                        "Y_m_d",
                        mktime(0, 0, 0, date('m'), date('d'), date('y'))
                    )
                )) {
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"Setting.php file already exists and cannot be renamed. '.
                        'Please do it by yourself and click on button Launch.";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    break;
                } else {
                    $events .= "The file $filename already exist. A copy has been created.<br />";
                    unlink($filename);
                }

                //manage SK path
                if (isset($_POST['sk_path']) && !empty($_POST['sk_path'])) {
                    $skFile = str_replace('\\', '/', $_POST['sk_path'].'/sk.php');
                    $securePath = str_replace('\\', '/', $_POST['sk_path']);
                } else {
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"<img src=\"images/exclamation-red.png\"> The SK path must be indicated.";
                        document.getElementById("loader").style.display = "none";';
                    break;
                }

                //Check if path is ok
                if (is_dir($securePath)) {
                    if (is_writable($securePath)) {
                        //Do nothing
                    } else {
                        echo 'document.getElementById("res_step5").innerHTML = '.
                            '"<img src=\"images/exclamation-red.png\"> The SK path must be writable!";
                            document.getElementById("loader").style.display = "none";';
                        break;
                    }
                } else {
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"<img src=\"images/exclamation-red.png\"> '.
                        'Path for SK is not a Directory!";
                    document.getElementById("loader").style.display = "none";';
                    break;
                }

                $fh = fopen($filename, 'w');

                //prepare smtp_auth variable
                if (empty($_SESSION['smtp_auth'])) {
                    $_SESSION['smtp_auth'] = 'false';
                }
                if (empty($_SESSION['smtp_auth_username'])) {
                    $_SESSION['smtp_auth_username'] = 'false';
                }
                if (empty($_SESSION['smtp_auth_password'])) {
                    $_SESSION['smtp_auth_password'] = 'false';
                }
                if (empty($_SESSION['email_from_name'])) {
                    $_SESSION['email_from_name'] = 'false';
                }

                $result1 = fwrite(
                    $fh,
                    utf8_encode(
"<?php
global \$lang, \$txt, \$k, \$pathTeampas, \$urlTeampass, \$pwComplexity, \$mngPages;
global \$server, \$user, \$pass, \$database, \$pre, \$db, \$port, \$encoding;

### DATABASE connexion parameters ###
\$server = \"". $_SESSION['server'] ."\";
\$user = \"". $_SESSION['user'] ."\";
\$pass = \"". str_replace("$", "\\$", $_SESSION['pass']) ."\";
\$database = \"". $_SESSION['database'] ."\";
\$port = ". $_SESSION['port'] .";
\$pre = \"". $_SESSION['pre'] ."\";
\$encoding = \"".$_SESSION['db_encoding']."\";

@date_default_timezone_set(\$_SESSION['settings']['timezone']);
@define('SECUREPATH', '".substr($skFile, 0, strlen($skFile)-7)."');
require_once \"".$skFile."\";
@define('COST', '13'); // Don't change this."
                    )
                );

                fclose($fh);
                if ($result1 === false) {
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"Setting.php file could not be created. '.
                        'Please check the path and the rights.";';
                } else {
                    echo 'document.getElementById("step5_settingFile").innerHTML = '.
                        '"<img src=\"images/tick.png\">";';
                }

                //Create sk.php file
                if (!file_exists($skFile)) {
                    $fh = fopen($skFile, 'w');

                    $result2 = fwrite(
                        $fh,
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
                    fclose($fh);
                }

                // update CSRFP TOKEN
                $csrfp_file_sample = "../includes/libraries/csrfp/libs/csrfp.config.sample.php";
                $csrfp_file = "../includes/libraries/csrfp/libs/csrfp.config.php";
                if (file_exists($csrfp_file)) {
                    if (!copy($filename, $filename.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                        echo '[{"error" : "csrfp.config.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.", "result":"", "index" : "'.$_POST['index'].'", "multiple" : "'.$_POST['multiple'].'"}]';
                        break;
                    } else {
                        $events .= "The file $csrfp_file already exist. A copy has been created.<br />";
                    }
                }
                unlink($csrfp_file);    // delete existing csrfp.config file
                copy($csrfp_file_sample, $csrfp_file);  // make a copy of csrfp.config.sample file
                $data = file_get_contents("../includes/libraries/csrfp/libs/csrfp.config.php");
                $newdata = str_replace('"CSRFP_TOKEN" => ""', '"CSRFP_TOKEN" => "'.bin2hex(openssl_random_pseudo_bytes(25)).'"', $data);
                $newdata = str_replace('"tokenLength" => "25"', '"tokenLength" => "50"', $newdata);
                $jsUrl = $_SESSION['fullurl'].'/includes/libraries/csrfp/js/csrfprotector.js';
                $newdata = str_replace('"jsUrl" => ""', '"jsUrl" => "'.$jsUrl.'"', $newdata);
                $newdata = str_replace('"verifyGetFor" => array()', '"verifyGetFor" => array("*page=items&type=duo_check*")', $newdata);
                file_put_contents("../includes/libraries/csrfp/libs/csrfp.config.php", $newdata);


                // finalize
                if (isset($result2) && $result2 === false) {
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"$skFile could not be created. Please check the path and the rights.";';
                } else {
                    echo 'document.getElementById("step5_skFile").innerHTML = '.
                        '"<img src=\"images/tick.png\">";';
                }

                //Finished
                if (
                    $result1 != false
                    && (!isset($result2) || (isset($result2) && $result2 != false))
                ) {
                    echo 'document.getElementById("but_next").disabled = "";';
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"Operations are successfully completed.";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    echo 'document.getElementById("but_launch").disabled = "disabled";';
                }
            } else {
                //settings.php file doesn't exit => ERROR !!!!
                echo 'document.getElementById("res_step5").innerHTML = '.
                        '"<img src=\"images/error.png\">&nbsp;Setting.php '.
                        'file doesn\'t exist! Upgrade can\'t continue without this file.<br />'.
                        'Please copy your existing settings.php into the \"includes\" '.
                        'folder of your TeamPass installation ";';
                echo 'document.getElementById("loader").style.display = "none";';
            }

            break;

        case "perform_database_dump":
            $filename = "../includes/config/settings.php";

            $mtables = array();

            $mysqli = new mysqli($_SESSION['server'], $_SESSION['user'], $_SESSION['pass'], $_SESSION['database'], $_SESSION['port']);
            if ($mysqli->connect_error) {
                die('Error : ('. $mysqli->connect_errno .') '. $mysqli->connect_error);
            }

            $results = $mysqli->query("SHOW TABLES");

            while($row = $results->fetch_array()){
                $mtables[] = $row[0];
            }

            foreach($mtables as $table){
                $contents .= "-- Table `".$table."` --\n";

                $results = $mysqli->query("SHOW CREATE TABLE ".$table);
                while($row = $results->fetch_array()){
                    $contents .= $row[1].";\n\n";
                }

                $results = $mysqli->query("SELECT * FROM ".$table);
                $row_count = $results->num_rows;
                $fields = $results->fetch_fields();
                $fields_count = count($fields);

                $insert_head = "INSERT INTO `".$table."` (";
                for($i=0; $i < $fields_count; $i++){
                    $insert_head  .= "`".$fields[$i]->name."`";
                        if($i < $fields_count-1){
                                $insert_head  .= ', ';
                            }
                }
                $insert_head .=  ")";
                $insert_head .= " VALUES\n";

                if($row_count>0){
                    $r = 0;
                    while($row = $results->fetch_array()){
                        if(($r % 400)  == 0){
                            $contents .= $insert_head;
                        }
                        $contents .= "(";
                        for($i=0; $i < $fields_count; $i++){
                            $row_content =  str_replace("\n","\\n",$mysqli->real_escape_string($row[$i]));

                            switch($fields[$i]->type){
                                case 8: case 3:
                                    $contents .=  $row_content;
                                    break;
                                default:
                                    $contents .= "'". $row_content ."'";
                            }
                            if($i < $fields_count-1){
                                    $contents  .= ', ';
                                }
                        }
                        if(($r+1) == $row_count || ($r % 400) == 399){
                            $contents .= ");\n\n";
                        }else{
                            $contents .= "),\n";
                        }
                        $r++;
                    }
                }
            }

            $backup_file_name = "sql-backup-".date( "d-m-Y--h-i-s").".sql";

            $fp = fopen("../files/".$backup_file_name ,'w+');
            if (($result = fwrite($fp, $contents))) {
                echo '[{ "error" : "" , "file" : "files/'.$backup_file_name.'"}]';
            } else {
                echo '[{ "error" : "Backup fails - please do it manually."}]';
            }
            fclose($fp);

            break;
    }
}
