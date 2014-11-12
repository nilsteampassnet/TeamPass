<?php
require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";

require_once '../includes/language/english.php';
require_once '../includes/include.php';
if (!file_exists("../includes/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
}
require_once '../includes/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['CPM'] = 1;
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

function tableExists($tablename, $database = false)
{
    global $dbTmp;
    if (!$database) {
        $res = mysqli_query($dbTmp, "SELECT DATABASE()");
        $database = mysql_result($res, 0);
    }

    $res = mysqli_query($dbTmp,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '$database'
        AND table_name = '$tablename'"
    );

    return mysql_result($res, 0) == 1;
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "step1":
            // erase session table
            $_SESSION = array();
            setcookie('pma_end_session');
            session_destroy();

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
                $abspath."/includes/settings.php",
                $abspath."/install/",
                $abspath."/includes/",
                $abspath."/files/",
                $abspath."/upload/"
            );
            foreach ($tab as $elem) {
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
            if (version_compare(phpversion(), '5.3.0', '<')) {
                $okVersion = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.
                    phpversion().' is not OK (minimum is 5.3.0) &nbsp;&nbsp;'.
                    '<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.
                    phpversion().' is OK&nbsp;&nbsp;<img src=\"images/tick-circle.png\">'.
                    '</span><br />';
            }

            //get infos from SETTINGS.PHP file
            $filename = "../includes/settings.php";
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
                $okEncryptKey = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">Encryption Key (SALT) '.
                    ' could not be recovered &nbsp;&nbsp;'.
                    '<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $okEncryptKey = true;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">Encryption Key (SALT) is <b>'.
                    $_SESSION['encrypt_key'].'</b>&nbsp;&nbsp;<img src=\"images/tick-circle.png\">'.
                    '</span><br />';
            }

            if ($okWritable == true && $okExtensions == true && $okEncryptKey == true) {
                echo 'document.getElementById("but_next").disabled = "";';
                echo 'document.getElementById("res_step1").innerHTML = "Elements are OK.";';
                //echo 'gauge.modify($("pbar"),{values:[0.25,1]});';
            } else {
                echo 'document.getElementById("but_next").disabled = "disabled";';
                echo 'document.getElementById("res_step1").innerHTML = "Correct the shown '.
                    'errors and click on button Launch to refresh";';
                //echo 'gauge.modify($("pbar"),{values:[0.25,1]});';
            }

            echo 'document.getElementById("res_step1").innerHTML = "'.$txt.'";';
            echo 'document.getElementById("loader").style.display = "none";';
            break;

            #==========================
        case "step2":
            $res = "";
            //decrypt the password
            // AES Counter Mode implementation
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';
            $dbPassword = Encryption\Crypt\aesctr::decrypt($_POST['db_password'], "cpm", 128);

            // connexion
            if (
                mysqli_connect(
                    $_POST['db_host'],
                    $_POST['db_login'],
                    $dbPassword,
                    $_POST['db_bdd'],
                    $_POST['db_port']
                )
            ) {
                $dbTmp = mysqli_connect(
                    $_POST['db_host'],
                    $_POST['db_login'],
                    $dbPassword,
                    $_POST['db_bdd'],
                    $_POST['db_port']
                );
                //echo 'gauge.modify($("pbar"),{values:[0.50,1]});';
                $res = "Connection is successful";
                echo 'document.getElementById("but_next").disabled = "";';

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
                "UPDATE `".$_SESSION['tbl_prefix']."misc`
                    SET `valeur` = 'maintenance_mode'
                    WHERE type = 'admin' AND intitule = '".$_POST['no_maintenance_mode']."'"
                );
            } else {
                //echo 'gauge.modify($("pbar"),{values:[0.50,1]});';
                $res = "Impossible to get connected to server. Error is: ".addslashes(mysqli_connect_error());
                echo 'document.getElementById("but_next").disabled = "disabled";';
            }

            echo 'document.getElementById("res_step2").innerHTML = "'.$res.'";';
            echo 'document.getElementById("loader").style.display = "none";';
            break;

            #==========================
        case "step3":
            mysqli_connect(
                $_SESSION['db_host'],
                $_SESSION['db_login'],
                $_SESSION['db_pw'],
                $_SESSION['db_bdd'],
                $_SESSION['db_port']
            );
            $dbTmp = mysqli_connect(
                $_SESSION['db_host'],
                $_SESSION['db_login'],
                $_SESSION['db_pw'],
                $_SESSION['db_bdd'],
                $_SESSION['db_port']
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
                "ALTER DATABASE `".$_SESSION['db_bdd']."`
                DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci"
            );

            //convert tables
            $res = mysqli_query($dbTmp,"SHOW TABLES FROM `".$_SESSION['db_bdd']."`");
            while ($table = mysqli_fetch_row($res)) {
                if (substr($table[0], 0, 4) != "old_") {
                    mysqli_query($dbTmp,
                        "ALTER TABLE ".$_SESSION['db_bdd'].".`{$table[0]}`
                        CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci"
                    );
                    mysqli_query($dbTmp,
                        "ALTER TABLE".$_SESSION['db_bdd'].".`{$table[0]}`
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
        case "step4":
            //include librairies
            require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

            //Build tree
            $tree = new Tree\NestedTree\NestedTree(
                $_SESSION['tbl_prefix'].'nested_tree',
                'id',
                'parent_id',
                'title'
            );

            // dataBase
            $res = "";

            @mysqli_connect(
                $_SESSION['db_host'],
                $_SESSION['db_login'],
                $_SESSION['db_pw'],
                $_SESSION['db_bdd'],
                $_SESSION['db_port']
            );
            $dbTmp = mysqli_connect(
                $_SESSION['db_host'],
                $_SESSION['db_login'],
                $_SESSION['db_pw'],
                $_SESSION['db_bdd'],
                $_SESSION['db_port']
            );

            ## Populate table MISC
            $val = array(
                array('admin', 'max_latest_items', '10',0),
                array('admin', 'enable_favourites', '1',0),
                array('admin', 'show_last_items', '1',0),
                array('admin', 'enable_pf_feature', '0',0),
                array('admin', 'menu_type', 'context',0),
                array('admin', 'log_connections', '0',0),
                array('admin', 'time_format', 'H:i:s',0),
                array('admin', 'date_format', 'd/m/Y',0),
                array('admin', 'duplicate_folder', '0',0),
                array('admin', 'duplicate_item', '0',0),
                array('admin', 'item_duplicate_in_same_folder', '0',0),
                array('admin', 'number_of_used_pw', '3',0),
                array('admin', 'manager_edit', '1',0),
                array('admin', 'cpassman_dir', '',0),
                array('admin', 'cpassman_url', '',0),
                array('admin', 'favicon', '',0),
                array('admin', 'activate_expiration', '0',0),
                array('admin', 'pw_life_duration','30',0),
                //array('admin', 'maintenance_mode','1',1),
                array('admin', 'cpassman_version',$k['version'],1),
                array('admin', 'ldap_mode','0',0),
                array('admin','ldap_type','0',0),
                array('admin','ldap_suffix','0',0),
                array('admin','ldap_domain_dn','0',0),
                array('admin','ldap_domain_controler','0',0),
                array('admin','ldap_user_attribute','0',0),
                array('admin','ldap_ssl','0',0),
                array('admin','ldap_tls','0',0),
                array('admin','ldap_elusers','0',0),
                array('admin', 'richtext',0,0),
                array('admin', 'allow_print',0,0),
                array('admin', 'roles_allowed_to_print',0,0),
                array('admin', 'show_description',1,0),
                array('admin', 'anyone_can_modify',0,0),
                array('admin', 'anyone_can_modify_bydefault',0,0),
                array('admin', 'nb_bad_authentication',0,0),
                array('admin', 'restricted_to',0,0),
                array('admin', 'restricted_to_roles',0,0),
                array('admin', 'utf8_enabled',1,0),
                array('admin', 'custom_logo','',0),
                array('admin', 'custom_login_text','',0),
                array('admin', 'log_accessed', '1',1),
                array('admin', 'default_language', 'english',0),
                array(
                    'admin',
                    'send_stats',
                    empty($_SESSION['send_stats']) ? '0' : $_SESSION['send_stats'],
                    1
                ),
                array('admin', 'get_tp_info', '1', 0),
                array('admin', 'send_mail_on_user_login', '0', 0),
                array('cron', 'sending_emails', '0', 0),
                array('admin', 'nb_items_by_query', 'auto', 0),
                array('admin', 'enable_delete_after_consultation', '0', 0),
                array(
                    'admin',
                    'path_to_upload_folder',
                    strrpos($_SERVER['DOCUMENT_ROOT'], "/") == 1 ?
                        (strlen($_SERVER['DOCUMENT_ROOT'])-1).substr(
                            $_SERVER['PHP_SELF'],
                            0,
                            strlen($_SERVER['PHP_SELF'])-25
                        ).'/upload'
                    :
                    $_SERVER['DOCUMENT_ROOT'].substr(
                        $_SERVER['PHP_SELF'],
                        0,
                        strlen($_SERVER['PHP_SELF'])-25
                    ).'/upload',
                    0
                ),
                array(
                    'admin',
                    'url_to_upload_folder',
                    'http://'.$_SERVER['HTTP_HOST'].substr(
                        $_SERVER['PHP_SELF'],
                        0,
                        strrpos($_SERVER['PHP_SELF'], '/')-8
                    ).'/upload',
                    0
                ),
                array('admin', 'enable_personal_saltkey_cookie', '0', 0),
                array('admin', 'personal_saltkey_cookie_duration', '31', 0),
                array(
                    'admin',
                    'path_to_files_folder',
                    strrpos($_SERVER['DOCUMENT_ROOT'], "/") == 1 ?
                    (strlen($_SERVER['DOCUMENT_ROOT'])-1).substr(
                        $_SERVER['PHP_SELF'],
                        0,
                        strlen($_SERVER['PHP_SELF'])-25
                    ).'/files'
                    :
                    $_SERVER['DOCUMENT_ROOT'].substr(
                        $_SERVER['PHP_SELF'],
                        0,
                        strlen($_SERVER['PHP_SELF'])-25
                    ).'/files',
                    0
                ),
                array(
                    'admin',
                    'url_to_files_folder',
                    'http://'.$_SERVER['HTTP_HOST'].substr(
                        $_SERVER['PHP_SELF'],
                        0,
                        strrpos($_SERVER['PHP_SELF'], '/')-8
                    ).'/files',
                    0
                ),
                array('admin', 'pwd_maximum_length','40',0),
                array('admin', 'ga_website_name','TeamPass for ChangeMe',0),
                array('admin', 'email_smtp_server', @$_SESSION['smtp_server'], 0),
                array('admin', 'email_smtp_auth', @$_SESSION['smtp_auth'], 0),
                array('admin', 'email_auth_username', @$_SESSION['smtp_auth_username'], 0),
                array('admin', 'email_auth_pwd', @$_SESSION['smtp_auth_password'], 0),
                array('admin', 'email_post', '25', 0),
                array('admin', 'email_from', @$_SESSION['email_from'], 0),
                array('admin', 'email_from_name', @$_SESSION['email_from_name'], 0),
                array('admin', '2factors_authentication', 0, 0),
                array('admin', 'delay_item_edition', 0, 0),
                array('admin', 'allow_import',0,0),
                array('admin', 'proxy_port',0,0),
                array('admin', 'proxy_port',0,0),
                array('admin','upload_maxfilesize','10mb',0),
                array(
                    'admin',
                    'upload_docext',
                    'doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx',
                    0
                ),
                array('admin','upload_imagesext','jpg,jpeg,gif,png',0),
                array('admin','upload_pkgext','7z,rar,tar,zip',0),
                array('admin','upload_otherext','sql,xml',0),
                array('admin','upload_imageresize_options','1',0),
                array('admin','upload_imageresize_width','800',0),
                array('admin','upload_imageresize_height','600',0),
                array('admin','upload_imageresize_quality','90',0),
                array('admin','enable_send_email_on_user_login','0', 0),
                array('admin','enable_user_can_create_folders','0', 0),
                array('admin','insert_manual_entry_item_history','0', 0),
                array('admin','enable_kb','0', 0),
                array('admin','enable_email_notification_on_item_shown','0', 0),
                array('admin','enable_email_notification_on_user_pw_change','0', 0),
                array('admin','enable_sts','0', 0),
                array('admin','encryptClientServer','1', 0),
	            array('admin','use_md5_password_as_salt','0', 0),
	            array('admin','api','0', 0),
                array('admin', 'subfolder_rights_as_parent', '0', 0),
                array('admin', 'show_only_accessible_folders', '0', 0),
                array('admin', 'enable_suggestion', '0', 0),
                array('admin', 'email_server_url', '', 0),
                array('admin','otv_expiration_period','7', 0)
            );
            $res1 = "na";
            foreach ($val as $elem) {
                //Check if exists before inserting
                $queryRes = mysqli_query($dbTmp,
                    "SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."misc
                    WHERE type='".$elem[0]."' AND intitule='".$elem[1]."'"
                );
                if (mysqli_error($dbTmp)) {
                    echo 'document.getElementById("res_step4").innerHTML = "MySQL Error! '.
                        addslashes($queryError).'";';
                    echo 'document.getElementById("tbl_1").innerHTML = "'.
                        '<img src=\"images/exclamation-red.png\">";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    break;
                } else {
                    $resTmp = mysqli_fetch_row($queryRes);
                    if ($resTmp[0] == 0) {
                        $queryRes = mysqli_query($dbTmp,
                            "INSERT INTO `".$_SESSION['tbl_prefix']."misc`
                            (`type`, `intitule`, `valeur`) VALUES
                            ('".$elem[0]."', '".$elem[1]."', '".
                            str_replace("'", "", $elem[2])."');"
                        );
                        if (!$queryRes) {
                            break;
                        }
                    } else {
                        // Force update for some settings
                        if ($elem[3] == 1) {
                            $queryRes = mysqli_query($dbTmp,
                                "UPDATE `".$_SESSION['tbl_prefix']."misc`
                                SET `valeur` = '".$elem[2]."'
                                WHERE type = '".$elem[0]."' AND intitule = '".$elem[1]."'"
                            );
                            if (!$queryRes) {
                                break;
                            }
                        }
                    }
                }
            }

            if ($queryRes) {
                echo 'document.getElementById("tbl_1").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears when inserting datas! '.addslashes($queryError).'";';
                echo 'document.getElementById("tbl_1").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                break;
            }

            ## Alter ITEMS table
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."items",
                "anyone_can_modify",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."items",
                "email",
                "VARCHAR(100) DEFAULT NULL"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."items",
                "notification",
                "VARCHAR(250) DEFAULT NULL"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."items",
                "viewed_no",
                "INT(12) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."roles_values",
                "type",
                "VARCHAR(1) NOT NULL DEFAULT 'R'"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."items MODIFY pw VARCHAR(400)"
            );

            # Alter tables
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."log_items MODIFY id_user INT(8)"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."restriction_to_roles MODIFY role_id INT(12)"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."restriction_to_roles MODIFY item_id INT(12)"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."items MODIFY pw TEXT"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."users MODIFY pw VARCHAR(400)"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE ".$_SESSION['tbl_prefix']."cache CHANGE `login` `login` VARCHAR( 200 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL"
            );

            ## Alter USERS table
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "favourites",
                "VARCHAR(300)"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "latest_items",
                "VARCHAR(300)"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "personal_folder",
                "INT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "disabled",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "no_bad_attempts",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "can_create_root_folder",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "read_only",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "timestamp",
                "VARCHAR(30) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "user_language",
                "VARCHAR(30) NOT null DEFAULT 'english'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "name",
                "VARCHAR(100) DEFAULT NULL"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "lastname",
                "VARCHAR(100) DEFAULT NULL"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "session_end",
                "VARCHAR(30) DEFAULT NULL"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "isAdministratedByRole",
                "TINYINT(5) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."users",
                "psk",
                "VARCHAR(400) DEFAULT NULL"
            );
        	$res2 = addColumnIfNotExist(
        	    $_SESSION['tbl_prefix']."users",
        	    "ga",
        	    "VARCHAR(50) DEFAULT NULL"
        	);
            echo 'document.getElementById("tbl_2").innerHTML = "<img src=\"images/tick.png\">";';

            // Clean timestamp for users table
            mysqli_query($dbTmp,"UPDATE ".$_SESSION['tbl_prefix']."users SET timestamp = ''");

            ## Alter nested_tree table
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."nested_tree",
                "personal_folder",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            $res2 = addColumnIfNotExist(
                $_SESSION['tbl_prefix']."nested_tree",
                "renewal_period",
                "TINYINT(4) NOT null DEFAULT '0'"
            );
            echo 'document.getElementById("tbl_5").innerHTML = "<img src=\"images/tick.png\">";';

            #to 1.08
            //include('upgrade_db_1.08.php');

            ## TABLE TAGS
            $res8 = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."tags` (
                `id` int(12) NOT null AUTO_INCREMENT,
                `tag` varchar(30) NOT NULL,
                `item_id` int(12) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `id` (`id`)
                );"
            );
            if ($res8) {
                echo 'document.getElementById("tbl_3").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table TAGS!";';
                echo 'document.getElementById("tbl_3").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE LOG_SYSTEM
            $res8 = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."log_system` (
                `id` int(12) NOT null AUTO_INCREMENT,
                `type` varchar(20) NOT NULL,
                `date` varchar(30) NOT NULL,
                `label` text NOT NULL,
                `qui` varchar(30) NOT NULL,
                PRIMARY KEY (`id`)
                );"
            );
            if ($res8) {
                mysqli_query($dbTmp,
                    "ALTER TABLE ".$_SESSION['tbl_prefix']."log_system
                    ADD `field_1` VARCHAR(250) NOT NULL"
                );
                echo 'document.getElementById("tbl_4").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table LOG_SYSTEM!";';
                echo 'document.getElementById("tbl_4").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE 10 - FILES
            $res9 = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."files` (
                `id` int(11) NOT null AUTO_INCREMENT,
                `id_item` int(11) NOT NULL,
                `name` varchar(100) NOT NULL,
                `size` int(10) NOT NULL,
                `extension` varchar(10) NOT NULL,
                `type` varchar(50) NOT NULL,
                `file` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
                );"
            );
            if ($res9) {
                echo 'document.getElementById("tbl_6").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table FILES!";';
                echo 'document.getElementById("tbl_6").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }
            mysqli_query($dbTmp,
                "ALTER TABLE `".$_SESSION['tbl_prefix']."files`
                CHANGE id id INT(11) AUTO_INCREMENT PRIMARY KEY;"
            );
            mysqli_query($dbTmp,
                "ALTER TABLE `".$_SESSION['tbl_prefix']."files`
                CHANGE name name VARCHAR(100) NOT NULL;"
            );

            ## TABLE CACHE
            mysqli_query($dbTmp,"DROP TABLE IF EXISTS `".$_SESSION['tbl_prefix']."cache`");
            $res8 = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."cache` (
                `id` int(12) NOT NULL,
                `label` varchar(50) NOT NULL,
                `description` text NOT NULL,
                `tags` text NOT NULL,
                `id_tree` int(12) NOT NULL,
                `perso` tinyint(1) NOT NULL,
                `restricted_to` varchar(200) NOT NULL,
                `login` varchar(200) NOT NULL,
                `folder` varchar(300) NOT NULL,
                `author` varchar(50) NOT NULL
                );"
            );
            if ($res8) {
                //ADD VALUES
                $sql = "SELECT *
                        FROM ".$_SESSION['tbl_prefix']."items as i
                        INNER JOIN ".$_SESSION['tbl_prefix']."log_items as l ON (l.id_item = i.id)
                        AND l.action = 'at_creation'
                        WHERE i.inactif=0";
                $rows = mysqli_query($dbTmp,$sql);
                while ($reccord = mysqli_fetch_array($rows)) {
                    //Get all TAGS
                    $tags = "";
                    $itemsRes = mysqli_query($dbTmp,
                        "SELECT tag FROM ".$_SESSION['tbl_prefix']."tags
                        WHERE item_id=".$reccord['id']
                    ) or die(mysqli_error($dbTmp));
                    $itemTags = mysqli_fetch_array($itemsRes);
                    if (!empty($itemTags)) {
                        foreach ($itemTags as $itemTag) {
                            if (!empty($itemTag['tag'])) {
                                $tags .= $itemTag['tag']. " ";
                            }
                        }
                    }
                    //form id_tree to full foldername
                    $folder = "";
                    $arbo = $tree->getPath($reccord['id_tree'], true);
                    foreach ($arbo as $elem) {
                        $folder .= htmlspecialchars(stripslashes($elem->title), ENT_QUOTES)." > ";
                    }

                    //store data
                    mysqli_query($dbTmp,
                        "INSERT INTO ".$_SESSION['tbl_prefix']."cache
                        VALUES (
                        '".$reccord['id']."',
                        '".$reccord['label']."',
                        '".$reccord['description']."',
                        '".$tags."',
                        '".$reccord['id_tree']."',
                        '".$reccord['perso']."',
                        '".$reccord['restricted_to']."',
                        '".$reccord['login']."',
                        '".$folder."',
                        '".$reccord['id_user']."'
                        )"
                    );
                }
                echo 'document.getElementById("tbl_7").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table CACHE!";';
                echo 'document.getElementById("tbl_7").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            /*
               *  Change table FUNCTIONS
               *  By 2 tables ROLES
            */
            $res9 = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_title` (
                `id` int(12) NOT NULL,
                `title` varchar(50) NOT NULL,
                `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
                `complexity` INT(5) NOT null DEFAULT '0',
                `creator_id` int(11) NOT null DEFAULT '0'
                );"
            );
            addColumnIfNotExist(
                $_SESSION['tbl_prefix']."roles_title",
                "allow_pw_change",
                "TINYINT(1) NOT null DEFAULT '0'"
            );
            addColumnIfNotExist(
                $_SESSION['tbl_prefix']."roles_title",
                "complexity",
                "INT(5) NOT null DEFAULT '0'"
            );
            addColumnIfNotExist(
                $_SESSION['tbl_prefix']."roles_title",
                "creator_id",
                "INT(11) NOT null DEFAULT '0'"
            );

            $res10 = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_values` (
                `role_id` int(12) NOT NULL,
                `folder_id` int(12) NOT NULL
                );"
            );
            if (tableExists($_SESSION['tbl_prefix']."functions")) {
                $tableFunctionExists = true;
            } else {
                $tableFunctionExists = false;
            }
            if ($res9 && $res10 && $tableFunctionExists == true) {
                //Get data from tables FUNCTIONS and populate new ROLES tables
                $rows = mysqli_query($dbTmp,
                    "SELECT * FROM ".$_SESSION['tbl_prefix']."functions"
                );
                while ($reccord = mysqli_fetch_array($rows)) {
                    //Add new role title
                    mysqli_query($dbTmp,
                        "INSERT INTO ".$_SESSION['tbl_prefix']."roles_title
                        VALUES (
                            '".$reccord['id']."',
                            '".$reccord['title']."'
                       )"
                    );

                    //Add each folder in roles_values
                    foreach (explode(';', $reccord['groupes_visibles']) as $folderId) {
                        if (!empty($folderId)) {
                            mysqli_query($dbTmp,
                                "INSERT INTO ".$_SESSION['tbl_prefix']."roles_values
                                VALUES (
                                '".$reccord['id']."',
                                '".$folderId."'
                               )"
                            );
                        }
                    }
                }

                //Now alter table roles_title in order to create a primary index
                mysqli_query($dbTmp,
                    "ALTER TABLE `".$_SESSION['tbl_prefix']."roles_title`
                    ADD PRIMARY KEY(`id`)"
                );
                mysqli_query($dbTmp,
                    "ALTER TABLE `".$_SESSION['tbl_prefix']."roles_title`
                    CHANGE `id` `id` INT(12) NOT null AUTO_INCREMENT "
                );
                addColumnIfNotExist(
                    $_SESSION['tbl_prefix']."roles_title",
                    "allow_pw_change",
                    "TINYINT(1) NOT null DEFAULT '0'"
                );

                //Drop old table
                mysqli_query($dbTmp,"DROP TABLE ".$_SESSION['tbl_prefix']."functions");

                echo 'document.getElementById("tbl_9").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } elseif ($tableFunctionExists == false) {
                echo 'document.getElementById("tbl_9").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on tables ROLES creation!";';
                echo 'document.getElementById("tbl_9").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE KB
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb` (
                `id` int(12) NOT null AUTO_INCREMENT,
                `category_id` int(12) NOT NULL,
                `label` varchar(200) NOT NULL,
                `description` text NOT NULL,
                `author_id` int(12) NOT NULL,
                `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                PRIMARY KEY (`id`)
                );"
            );
            if ($res) {
                echo 'document.getElementById("tbl_10").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table KB!";';
                echo 'document.getElementById("tbl_10").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE KB_CATEGORIES
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb_categories` (
                `id` int(12) NOT null AUTO_INCREMENT,
                `category` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
                );"
            );
            if ($res) {
                echo 'document.getElementById("tbl_11").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table KB_CATEGORIES!";';
                echo 'document.getElementById("tbl_11").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE KB_ITEMS
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb_items` (
                `kb_id` tinyint(12) NOT NULL,
                `item_id` tinyint(12) NOT NULL
                 );"
            );
            if ($res) {
                echo 'document.getElementById("tbl_12").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table KB_ITEMS!";';
                echo 'document.getElementById("tbl_12").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE restriction_to_roles
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."restriction_to_roles` (
                `role_id` tinyint(12) NOT NULL,
                `item_id` tinyint(12) NOT NULL
                ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_13").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table restriction_to_roles!";';
                echo 'document.getElementById("tbl_13").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE keys
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."keys` (
                `table` varchar(25) NOT NULL,
                `id` int(20) NOT NULL,
                `rand_key` varchar(25) NOT NULL
                ) CHARSET=utf8;"
            );
            $resTmp = mysqli_fetch_row(
                mysqli_query($dbTmp,
                    "SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."keys"
                )
            );
            if ($res && $resTmp[0] == 0) {
                echo 'document.getElementById("tbl_14").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';

                //increase size of PW field in ITEMS table
                mysqli_query($dbTmp,
                    "ALTER TABLE ".$_SESSION['tbl_prefix']."items MODIFY pw VARCHAR(400)"
                );

                //Populate table KEYS
                //create all keys for all items
                $rows = mysqli_query($dbTmp,
                    "SELECT * FROM ".$_SESSION['tbl_prefix']."items WHERE perso = 0"
                );
                while ($reccord = mysqli_fetch_array($rows)) {
                    if (!empty($reccord['pw'])) {
                        //get pw
                        $pw = trim(
                            mcrypt_decrypt(
                                MCRYPT_RIJNDAEL_256,
                                SALT,
                                base64_decode($reccord['pw']),
                                MCRYPT_MODE_ECB,
                                mcrypt_create_iv(
                                    mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
                                    MCRYPT_RAND
                                )
                            )
                        );

                        //generate random key
                        $randomKey = substr(md5(rand().rand()), 0, 15);

                        //Store generated key
                        mysqli_query($dbTmp,
                            "INSERT INTO ".$_SESSION['tbl_prefix']."keys
                            VALUES('items', '".$reccord['id']."', '".$randomKey."')"
                        );

                        //encrypt
                        $encryptedPw = trim(
                            base64_encode(
                                mcrypt_encrypt(
                                    MCRYPT_RIJNDAEL_256,
                                    SALT,
                                    $randomKey.$pw,
                                    MCRYPT_MODE_ECB,
                                    mcrypt_create_iv(
                                        mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
                                        MCRYPT_RAND
                                    )
                                )
                            )
                        );

                        //update pw in ITEMS table
                        mysqli_query($dbTmp,
                            "UPDATE ".$_SESSION['tbl_prefix']."items
                            SET pw = '".$encryptedPw."'
                            WHERE id='".$reccord['id']."'"
                        ) or die(mysqli_error($dbTmp));
                    }
                }
                echo 'document.getElementById("tbl_15").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';

            } else {
                echo 'document.getElementById("tbl_14").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
                echo 'document.getElementById("tbl_15").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            }

            ## TABLE Languages
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."languages` (
                `id` INT(10) NOT null AUTO_INCREMENT PRIMARY KEY ,
                `name` VARCHAR(50) NOT null ,
                `label` VARCHAR(50) NOT null ,
                `code` VARCHAR(10) NOT null ,
                `flag` VARCHAR(30) NOT NULL
                ) CHARSET=utf8;"
            );
            $resTmp = mysqli_fetch_row(
                mysqli_query($dbTmp,"SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."languages")
            );
            mysqli_query($dbTmp,"TRUNCATE TABLE ".$_SESSION['tbl_prefix']."languages");
            mysqli_query($dbTmp,
                "INSERT IGNORE INTO `".$_SESSION['tbl_prefix']."languages`
                (`id`, `name`, `label`, `code`, `flag`) VALUES
                ('', 'french', 'French' , 'fr', 'fr.png'),
                ('', 'english', 'English' , 'us', 'us.png'),
                ('', 'spanish', 'Spanish' , 'es', 'es.png'),
                ('', 'german', 'German' , 'de', 'de.png'),
                ('', 'czech', 'Czech' , 'cz', 'cz.png'),
                ('', 'italian', 'Italian' , 'it', 'it.png'),
                ('', 'russian', 'Russian' , 'ru', 'ru.png'),
                ('', 'turkish', 'Turkish' , 'tr', 'tr.png'),
                ('', 'norwegian', 'Norwegian' , 'no', 'no.png'),
                ('', 'japanese', 'Japanese' , 'ja', 'ja.png'),
                ('', 'portuguese', 'Portuguese' , 'pr', 'pr.png'),
                ('', 'chinese', 'Chinese' , 'cn', 'cn.png'),
                ('', 'swedish', 'Swedish' , 'se', 'se.png'),
                ('', 'dutch', 'Dutch' , 'nl', 'nl.png'),
                ('', 'catalan', 'Catalan' , 'ct', 'ct.png');"
            );
            if ($res) {
                echo 'document.getElementById("tbl_16").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table LANGUAGES!";';
                echo 'document.getElementById("tbl_13").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE EMAILS
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."emails` (
                `timestamp` INT(30) NOT null ,
                `subject` VARCHAR(255) NOT null ,
                `body` TEXT NOT null ,
                `receivers` VARCHAR(255) NOT null ,
                `status` VARCHAR(30) NOT NULL
                ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_17").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table EMAILS!";';
                echo 'document.getElementById("tbl_17").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE AUTOMATIC DELETION
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."automatic_del` (
                `item_id` int(11) NOT NULL,
                `del_enabled` tinyint(1) NOT NULL,
                `del_type` tinyint(1) NOT NULL,
                `del_value` varchar(35) NOT NULL
                ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_18").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table AUTOMATIC_DEL!";';
                echo 'document.getElementById("tbl_18").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE items_edition
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."items_edition` (
                `item_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `timestamp` varchar(50) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_19").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table items_edition! '.mysqli_error($dbTmp).'";';
                echo 'document.getElementById("tbl_19").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE categories
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."categories` (
                `id` int(12) NOT NULL AUTO_INCREMENT,
                `parent_id` int(12) NOT NULL,
                `title` varchar(255) NOT NULL,
                `level` int(2) NOT NULL,
                `description` text NOT NULL,
                `type` varchar(50) NOT NULL,
                `order` int(12) NOT NULL,
                PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_20").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table categories! '.mysqli_error($dbTmp).'";';
                echo 'document.getElementById("tbl_20").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE categories_items
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."categories_items` (
                `id` int(12) NOT NULL AUTO_INCREMENT,
                `field_id` int(11) NOT NULL,
                `item_id` int(11) NOT NULL,
                `data` text NOT NULL,
                PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_21").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table categories_items! '.mysqli_error($dbTmp).'";';
                echo 'document.getElementById("tbl_21").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

        	## TABLE categories_folders
        	$res = mysqli_query($dbTmp,
        	"CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."categories_folders` (
                `id_category` int(12) NOT NULL,
                `id_folder` int(12) NOT NULL
               ) CHARSET=utf8;"
        	);
        	if ($res) {
        		echo 'document.getElementById("tbl_22").innerHTML = '.
        		    '"<img src=\"images/tick.png\">";';
        	} else {
        		echo 'document.getElementById("res_step4").innerHTML = '.
        		    '"An error appears on table categories_folders! '.mysqli_error($dbTmp).'";';
        		echo 'document.getElementById("tbl_22").innerHTML = '.
        		    '"<img src=\"images/exclamation-red.png\">";';
        		echo 'document.getElementById("loader").style.display = "none";';
        		mysqli_close($dbTmp);
        		break;
        	}

            ## TABLE api
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."api` (
                `id` int(20) NOT NULL AUTO_INCREMENT,
                `type` varchar(15) NOT NULL,
                `label` varchar(255) NOT NULL,
                `value` varchar(255) NOT NULL,
                `timestamp` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_23").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table API! '.mysqli_error($dbTmp).'";';
                echo 'document.getElementById("tbl_23").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE otv
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."otv` (
                `id` int(10) NOT NULL AUTO_INCREMENT,
                `timestamp` text NOT NULL,
                `code` varchar(100) NOT NULL,
                `item_id` int(12) NOT NULL,
                `originator` tinyint(12) NOT NULL,
                PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_24").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table OTV! '.mysqli_error($dbTmp).'";';
                echo 'document.getElementById("tbl_24").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            ## TABLE suggestion
            $res = mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."suggestion` (
                `id` tinyint(12) NOT NULL AUTO_INCREMENT,
                `label` varchar(255) NOT NULL,
                `password` text NOT NULL,
                `description` text NOT NULL,
                `author_id` int(12) NOT NULL,
                `folder_id` int(12) NOT NULL,
                `comment` text NOT NULL,
                `key` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_25").innerHTML = '.
                    '"<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = '.
                    '"An error appears on table SUGGESTION! '.mysqli_error($dbTmp).'";';
                echo 'document.getElementById("tbl_25").innerHTML = '.
                    '"<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysqli_close($dbTmp);
                break;
            }

            # TABLE EXPORT
            mysqli_query($dbTmp,
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."export` (
                `id` int(12) NOT NULL,
                `label` varchar(255) NOT NULL,
                `login` varchar(100) NOT NULL,
                `description` text NOT NULL,
                `pw` text NOT NULL,
                `path` varchar(255) NOT NULL
                ) CHARSET=utf8;"
            );

            //CLEAN UP ITEMS TABLE
            $allowedTags = '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul>'.
                '<blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>';
            $cleanRes = mysqli_query($dbTmp,
                "SELECT id,description FROM `".$_SESSION['tbl_prefix']."items`"
            );
            while ($cleanData = mysqli_fetch_array($cleanRes)) {
                mysqli_query($dbTmp,
                    "UPDATE `".$_SESSION['tbl_prefix']."items`
                    SET description = '".strip_tags($cleanData['description'], $allowedTags).
                    "' WHERE id = ".$cleanData['id']
                );
            }

            //Encrypt passwords in log_items
            $resTmp = mysqli_fetch_row(
                mysqli_query($dbTmp,
                    "SELECT COUNT(*) FROM ".$pre."misc
                    WHERE type = 'update' AND intitule = 'encrypt_pw_in_log_items'
                    AND valeur = 1"
                )
            );
            if ($resTmp[0] == 0) {
                // AES Counter Mode implementation
                require_once '../includes/libraries/Encryption/Crypt/aesctr.php';
                $tmpRes = mysqli_query($dbTmp,
                    "SELECT * FROM ".$pre."log_items
                    WHERE action = 'at_modification' AND raison LIKE 'at_pw %'"
                );
                while ($tmpData = mysqli_fetch_array($tmpRes)) {
                    $reason = explode(':', $tmpData['raison']);
                    $text = Encryption\Crypt\aesctr::encrypt(
                        trim($reason[1]),
                        $_SESSION['encrypt_key'],
                        256
                    );
                }
                mysqli_query($dbTmp,
                    "INSERT INTO `".$_SESSION['tbl_prefix']."misc`
                    VALUES ('update', 'encrypt_pw_in_log_items',1)"
                );
            }

            // Since 2.1.17, encrypt process is changed.
            // Previous PW need to be re-encrypted
            if (@mysqli_query($dbTmp,
                "SELECT valeur FROM ".$_SESSION['tbl_prefix']."misc
                WHERE type='admin' AND intitule = 'encryption_protocol'"
            )) {
                $tmpResult = mysqli_query($dbTmp,
                    "SELECT valeur FROM ".$_SESSION['tbl_prefix']."misc
                    WHERE type='admin' AND intitule = 'encryption_protocol'"
                );
                $tmp = mysqli_fetch_row($tmpResult);
                if ($tmp[0] != "ctr") {
                    //count elem
                    $res = mysqli_query($dbTmp,
                        "SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."items
                        WHERE perso = '0'"
                    );
                    $data = mysqli_fetch_row($res);
                    if ($data[0] > 0) {
                        echo '$("#change_pw_encryption, #change_pw_encryption_progress").show();';
                        echo '$("#change_pw_encryption_progress").html('.
                            '"Number of Passwords to re-encrypt: '.$data[0].'");';
                        echo '$("#change_pw_encryption_total").val("'.$data[0].'")';
                        break;
                    }

                }
            }

            /* Unlock this step */
            //echo 'gauge.modify($("pbar"),{values:[0.75,1]});';
            echo 'document.getElementById("but_next").disabled = "";';
            echo 'document.getElementById("but_launch").disabled = "disabled";';
            echo 'document.getElementById("res_step4").innerHTML = "dataBase has been populated";';
            echo 'document.getElementById("loader").style.display = "none";';
            mysqli_close($dbTmp);
            break;

            //=============================
        case "step5":
            $filename = "../includes/settings.php";
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
global \$server, \$user, \$pass, \$database, \$pre, \$db;

### DATABASE connexion parameters ###
\$server = \"". $_SESSION['db_host'] ."\";
\$user = \"". $_SESSION['db_login'] ."\";
\$pass = \"". str_replace("$", "\\$", $_SESSION['db_pw']) ."\";
\$database = \"". $_SESSION['db_bdd'] ."\";
\$port = ". $_SESSION['db_port'] .";
\$pre = \"". $_SESSION['tbl_prefix'] ."\";
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
@define('SALT', '".$_SESSION['session_salt']."'); //Never Change it once it has been used !!!!!
?>"
                        )
                    );
                    fclose($fh);
                }
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
                    //echo 'gauge.modify($("pbar"),{values:[1,1]});';
                    echo 'document.getElementById("but_next").disabled = "";';
                    echo 'document.getElementById("res_step5").innerHTML = '.
                        '"Operations are successfully completed.";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    echo 'document.getElementById("but_launch").disabled = "disabled";';
                }
            } else {
                //settings.php file doesn't exit => ERROR !!!!
                echo 'document.getElementById("res_step5").innerHTML = '.
                        '"<img src=\"../includes/images/error.png\">&nbsp;Setting.php '.
                        'file doesn\'t exist! Upgrade can\'t continue without this file.<br />'.
                        'Please copy your existing settings.php into the \"includes\" '.
                        'folder of your TeamPass installation ";';
                echo 'document.getElementById("loader").style.display = "none";';
            }

            break;

        case "new_encryption_of_pw":
            $finish = false;
            $next = ($_POST['nb']+$_POST['start']);

            @mysqli_connect(
                $_SESSION['db_host'],
                $_SESSION['db_login'],
                $_SESSION['db_pw'],
                $_SESSION['db_bdd'],
                $_SESSION['db_port']
            );
            $dbTmp = mysqli_connect(
                $_SESSION['db_host'],
                $_SESSION['db_login'],
                $_SESSION['db_pw'],
                $_SESSION['db_bdd'],
                $_SESSION['db_port']
            );
            @mysqli_select_db($dbTmp, $_SESSION['db_bdd']);
            mysqli_select_db(
                $dbTmp,
                $_SESSION['db_bdd']
            );

            $res = mysqli_query($dbTmp,
                "SELECT * FROM ".$_SESSION['tbl_prefix']."items
                WHERE perso = '0' LIMIT ".$_POST['start'].", ".$_POST['nb']
            ) or die(mysqli_error($dbTmp));
            while ($data = mysqli_fetch_array($res)) {
                // check if pw already well encrypted
                $pw = decrypt($data['pw']);
                if (empty($pw)) {
                    $pw = decryptOld($data['pw']);

                    // if no key ... then add it
                    $resData = mysqli_query($dbTmp,
                        "SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."keys
                        WHERE `table` = 'items' AND id = ".$data['id']
                    ) or die(mysqli_error($dbTmp));
                    $dataTemp = mysqli_fetch_row($resData);
                    if ($dataTemp[0] == 0) {
                        // generate Key and encode PW
                        $randomKey = generateKey();
                        $pw = $randomKey.$pw;
                    }
                    $pw = encrypt($pw, $_SESSION['session_start']);

                    // store Password
                    mysqli_query($dbTmp,
                        "UPDATE ".$_SESSION['tbl_prefix']."items
                        SET pw = '".$pw."' WHERE id=".$data['id']
                    );

                    // Item Key
                    mysqli_query($dbTmp,
                        "INSERT INTO `".$_SESSION['tbl_prefix']."keys`
                        (`table`, `id`, `rand_key`) VALUES
                        ('items', '".$data['id']."', '".$randomKey."'"
                    );
                } else {
                    // if PW exists but no key ... then add it
                    $resData = mysqli_query($dbTmp,
                        "SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."keys
                        WHERE `table` = 'items' AND id = ".$data['id']
                    ) or die(mysqli_error($dbTmp));
                    $dataTemp = mysqli_fetch_row($resData);
                    if ($dataTemp[0] == 0) {
                        // generate Key and encode PW
                        $randomKey = generateKey();
                        $pw = $randomKey.$pw;
                        $pw = encrypt($pw, $_SESSION['session_start']);

                        // store Password
                        mysqli_query($dbTmp,
                            "UPDATE ".$_SESSION['tbl_prefix']."items
                            SET pw = '".$pw."' WHERE id=".$data['id']
                        );

                        // Item Key
                        mysqli_query($dbTmp,
                            "INSERT INTO `".$_SESSION['tbl_prefix']."keys`
                            (`table`, `id`, `rand_key`) VALUES
                            ('items', '".$data['id']."', '".$randomKey."'"
                        );
                    }
                }
            }

            if ($next >= $_POST['total']) {
                $finish = true;
            }
            echo '[{"finish":"'.$finish.'" , "next":"'.$next.'" '.
                ', "progress":"'.round($next*100/$_POST['total'], 0).'"}]';
            break;
    }
}
