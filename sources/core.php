<?php
/**
 * @file          core.php
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

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Please login...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/**
 * redirection management
 * @param  [string] $url new url
 * @return refresh page to url
 */
function redirect($url)
{
    // Load AntiXSS
    require_once '../includes/libraries/protect/AntiXSS/AntiXss.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    if (!headers_sent()) {    //If headers not sent yet... then do php redirect
        header('Location: '.$antiXss->xss_clean($url));
        exit();
    }

    //If headers are sent... do java redirect... if java disabled, do html redirect.
    echo '<script type="text/javascript">';
    echo 'window.location.href="'.$antiXss->xss_clean($url).'";';
    echo '</script>';
    echo '<noscript>';
    echo '<meta http-equiv="refresh" content="0;url='.$antiXss->xss_clean($url).'" />';
    echo '</noscript>';
}

// Redirect needed?
if (isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'on' &&
    isset($SETTINGS['enable_sts']) &&
    $SETTINGS['enable_sts'] === "1"
) {
    redirect("https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
}

// LOAD CPASSMAN SETTINGS
if (!isset($SETTINGS_EXT['loaded']) || $SETTINGS_EXT['loaded'] !== "1") {
    $SETTINGS_EXT['loaded'] = 1;

    // Pw complexity levels
    if (isset($_SESSION['user_language']) && $_SESSION['user_language'] !== "0") {
        require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
        $SETTINGS_EXT['pwComplexity'] = array(
            0=>array(0, $LANG['complex_level0']),
            25=>array(25, $LANG['complex_level1']),
            50=>array(50, $LANG['complex_level2']),
            60=>array(60, $LANG['complex_level3']),
            70=>array(70, $LANG['complex_level4']),
            80=>array(80, $LANG['complex_level5']),
            90=>array(90, $LANG['complex_level6'])
        );
    }
}


if (isset($_SESSION['user_settings']['usertimezone']) && $_SESSION['user_settings']['usertimezone'] !== "not_defined") {
    // use user timezone
    date_default_timezone_set($_SESSION['user_settings']['usertimezone']);
} else {
    // use server timezone
    date_default_timezone_set($SETTINGS['timezone']);
}


// Load Languages stuff
if (empty($languagesDropmenu)) {
    $languagesList = array();
    $rows = DB::query("SELECT * FROM ".prefix_table("languages")." GROUP BY name, label, code, flag, id ORDER BY name ASC");
    foreach ($rows as $record) {
        array_push($languagesList, $record['name']);
        if (isset($_SESSION['user_language']) && $record['name'] == $_SESSION['user_language']) {
            $_SESSION['user_language_flag'] = $record['flag'];
            $_SESSION['user_language_code'] = $record['code'];
            $_SESSION['user_language_label'] = $record['label'];
            $_SESSION['user_language_id'] = $record['id'];
        }
    }
}

// CHECK IF LOGOUT IS ASKED OR IF SESSION IS EXPIRED
if ((isset($_GET['session']) && $_GET['session'] == "expired")
    || (null !== filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING) && filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING) === "expired")
) {
    // Clear User tempo key
    if (isset($_SESSION['user_id'])) {
        DB::update(
            prefix_table("users"),
            array(
                'key_tempo' => '',
                'timestamp' => '',
                'session_end' => ''
            ),
            "id=%i",
            $_SESSION['user_id']
        );
    }

    // REDIRECTION PAGE ERREUR
    echo '
    <script language="javascript" type="text/javascript">
    <!--
        sessionStorage.clear();
        window.location.href = "logout.php";
    -->
    </script>';
    exit;
}

// CHECK IF SESSION EXISTS AND IF SESSION IS VALID
if (!empty($_SESSION['fin_session'])) {
    $dataSession = DB::queryFirstRow(
        "SELECT key_tempo FROM ".prefix_table("users")." WHERE id=%i",
        $_SESSION['user_id']
    );
} else {
    $dataSession['key_tempo'] = "";
}

if (isset($_SESSION['user_id']) && isset($_GET['type']) === false && (
        empty($_SESSION['fin_session'])
        || $_SESSION['fin_session'] < time() || empty($_SESSION['key'])
        || empty($dataSession['key_tempo'])
    )
) {
    // Update table by deleting ID
    DB::update(
        prefix_table("users"),
        array(
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => ''
        ),
        "id=%i",
        $_SESSION['user_id']
    );

    //Log into DB the user's disconnection
    if (isset($SETTINGS['log_connections']) && $SETTINGS['log_connections'] === '1' && empty($_SESSION['login']) === false) {
        logEvents('user_connection', 'disconnection', $_SESSION['user_id'], $_SESSION['login']);
    }

    // erase session table
    session_destroy();
    $_SESSION = array();
    unset($_SESSION);

    //Redirection
    echo '
    <script language="javascript" type="text/javascript">
    <!--
    setTimeout(function(){document.location.href="index.php"}, 1);
    -->
    </script>';
}

// CHECK IF UPDATE IS NEEDED
if ((isset($SETTINGS['update_needed']) && ($SETTINGS['update_needed'] !== false
    || empty($SETTINGS['update_needed'])))
    && (isset($_SESSION['user_admin']) && $_SESSION['user_admin'] == 1)
) {
    $row = DB::queryFirstRow(
        "SELECT valeur FROM ".prefix_table("misc")." WHERE type=%s_type AND intitule=%s_intitule",
        array(
            "type" => "admin",
            "intitule" => "cpassman_version"
        )
    );
    if ($row['valeur'] != $SETTINGS_EXT['version']) {
        $SETTINGS['update_needed'] = true;
    } else {
        $SETTINGS['update_needed'] = false;
    }
}

/**
 * Set the personal SaltKey if authorized
 */
if (isset($SETTINGS['enable_personal_saltkey_cookie'])
    && $SETTINGS['enable_personal_saltkey_cookie'] == 1
    && isset($_SESSION['user_id'])
    && isset($_COOKIE['TeamPass_PFSK_'.md5($_SESSION['user_id'])])
) {
    $_SESSION['user_settings']['clear_psk'] = decrypt($_COOKIE['TeamPass_PFSK_'.md5($_SESSION['user_id'])], '');
}

/* CHECK IF MAINTENANCE MODE
* IF yes then authorize all ADMIN connections and
* reject all others
*/
if (isset($SETTINGS['maintenance_mode']) && $SETTINGS['maintenance_mode'] == 1) {
    if (isset($_SESSION['user_admin']) && $_SESSION['user_admin'] != 1) {
        // Update table by deleting ID
        if (isset($_SESSION['user_id'])) {
            DB::update(
                prefix_table("users"),
                array(
                    'key_tempo' => '',
                    'timestamp' => '',
                    'session_end' => ''
                ),
                "id=%i",
                $_SESSION['user_id']
            );
        }

        //Log into DB the user's disconnection
        if (isset($SETTINGS['log_connections']) && $SETTINGS['log_connections'] == 1) {
            logEvents('user_connection', 'disconnection', $_SESSION['user_id'], $_SESSION['login']);
        }

        syslog(
            LOG_WARNING,
            "Unlog user: ".date("Y/m/d H:i:s")." {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})"
        );

        // erase session table
        $_SESSION = array();

        setcookie('pma_end_session');

        // REDIRECTION PAGE ERREUR
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        setTimeout(function(){document.location.href="logout.php"}, 10);
        -->
        </script>';
        exit;
    }
}

/* Force HTTPS Strict Transport Security */
if (isset($SETTINGS['enable_sts']) &&
    $SETTINGS['enable_sts'] == 1
) {
    // do a check to make sure that the certificate is not self signed.
    // In apache's SSL configuration make sure "SSLOptions +ExportCertData" in enabled
    $server_cert = openssl_x509_parse($_SERVER['SSL_SERVER_CERT']);
    $cert_name = $server_cert['name'];
    $cert_issuer = "";
    foreach ($server_cert['issuer'] as $key => $value) {
        $cert_issuer .= "/$key=$value";
    }
    if (isset($cert_name) && !empty($cert_name) && $cert_name != $cert_issuer) {
        if (isset($_SERVER['HTTPS'])) {
            header('Strict-Transport-Security: max-age=500');
            $_SESSION['error']['sts'] = 0;
        }
    } elseif ($cert_name == $cert_issuer) {
        $_SESSION['error']['sts'] = 1;
    }
}

/* LOAD INFORMATION CONCERNING USER */
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // query on user
    $data = DB::queryfirstrow(
        "SELECT admin, gestionnaire, can_manage_all_users, groupes_visibles, groupes_interdits, fonction_id FROM ".prefix_table("users")." WHERE id=%i",
        $_SESSION['user_id']
    );

    //Check if user has been deleted or unlogged
    if (empty($data)) {
        // erase session table
        $_SESSION = array();

        // Kill session
        session_destroy();

        //redirection to index
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        setTimeout(function(){document.location.href="index.php"}, 10);
        -->
        </script>';
    } else {
        // update user's rights
        $_SESSION['user_admin'] = $data['admin'];
        $_SESSION['user_manager'] = $data['gestionnaire'];
        $_SESSION['user_can_manage_all_users'] = $data['can_manage_all_users'];
        $_SESSION['groupes_visibles'] = array();
        $_SESSION['groupes_interdits'] = array();
        if (!empty($data['groupes_visibles'])) {
            $_SESSION['groupes_visibles'] = @implode(';', $data['groupes_visibles']);
        }
        if (!empty($data['groupes_interdits'])) {
            $_SESSION['groupes_interdits'] = @implode(';', $data['groupes_interdits']);
        }

        if (!isset($_SESSION['fin_session'])) {
            DB::update(
                prefix_table("users"),
                array(
                    'timestamp'=>time()
                ),
                "id=%i",
                $_SESSION['user_id']
            );
        }

        // get access rights
        identifyUserRights(
            $data['groupes_visibles'],
            $data['groupes_interdits'],
            $data['admin'],
            $data['fonction_id']
        );

        // user type
        if (isset($LANG)) {
            if ($_SESSION['user_admin'] == 1) {
                $_SESSION['user_privilege'] = $LANG['god'];
            } elseif ($_SESSION['user_manager'] == 1) {
                $_SESSION['user_privilege'] = $LANG['gestionnaire'];
            } elseif ($_SESSION['user_read_only'] == 1) {
                $_SESSION['user_privilege'] = $LANG['read_only_account'];
            } else {
                $_SESSION['user_privilege'] = $LANG['user'];
            }
        }

        /*
        * LOAD CATEGORIES
        */
        if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] == 1 && empty($_SESSION['item_fields'])) {
            $_SESSION['item_fields'] = array();
            $rows = DB::query(
                "SELECT *
                FROM ".prefix_table("categories")."
                WHERE level=%i",
                "0"
            );
            foreach ($rows as $record) {
                $arrFields = array();

                // get each field
                $rows2 = DB::query(
                    "SELECT *
                    FROM ".prefix_table("categories")."
                    WHERE parent_id=%i
                    ORDER BY `order` ASC",
                    $record['id']
                );
                if (DB::count() > 0) {
                    foreach ($rows2 as $field) {
                        array_push(
                            $arrFields,
                            array(
                                $field['id'],
                                addslashes($field['title']),
                                $field['encrypted_data'],
                                $field['type']
                            )
                        );
                    }
                }

                // store the categories
                array_push(
                    $_SESSION['item_fields'],
                    array(
                        $record['id'],
                        addslashes($record['title']),
                        $arrFields
                    )
                );
            }
        }
    }
}

/*
* CHECK PASSWORD VALIDITY
* Don't take into consideration if LDAP in use
*/
$_SESSION['numDaysBeforePwExpiration'] = ""; //initiliaze variable
if (isset($SETTINGS['ldap_mode']) && $SETTINGS['ldap_mode'] == 1) {
    $_SESSION['validite_pw'] = true;
    $_SESSION['last_pw_change'] = true;
} else {
    if (isset($_SESSION['last_pw_change'])) {
        if ($SETTINGS['pw_life_duration'] == 0) {
            $_SESSION['numDaysBeforePwExpiration'] = "infinite";
            $_SESSION['validite_pw'] = true;
        } else {
            $_SESSION['numDaysBeforePwExpiration'] = $SETTINGS['pw_life_duration'] - round(
                (mktime(0, 0, 0, date('m'), date('d'), date('y')) - $_SESSION['last_pw_change']) / (24 * 60 * 60)
            );
            if ($_SESSION['numDaysBeforePwExpiration'] <= 0) {
                $_SESSION['validite_pw'] = false;
            } else {
                $_SESSION['validite_pw'] = true;
            }
        }
    } else {
        $_SESSION['validite_pw'] = false;
    }
}

/*
**
*/
$_SESSION['temporary']['user_can_printout'] = false;
if (isset($SETTINGS['roles_allowed_to_print']) && isset($_SESSION['user_roles']) && (!isset($_SESSION['temporary']['user_can_printout']) || empty($_SESSION['temporary']['user_can_printout']))) {
    foreach (explode(";", $SETTINGS['roles_allowed_to_print']) as $role) {
        if (in_array($role, $_SESSION['user_roles'])) {
            $_SESSION['temporary']['user_can_printout'] = true;
        }
    }
}


/* CHECK NUMBER OF USER ONLINE */
DB::query("SELECT * FROM ".prefix_table("users")." WHERE timestamp>=%i", time() - 600);
$_SESSION['nb_users_online'] = DB::count();
