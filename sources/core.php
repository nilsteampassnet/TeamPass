<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2019 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
*
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Please login...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/**
 * Redirection management.
 *
 * @param string $url new url
 *
 * @return string refresh page to url
 */
function redirect($url)
{
    // Load AntiXSS
    include_once '../includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    if (!headers_sent()) {    //If headers not sent yet... then do php redirect
        header('Location: '.$antiXss->xss_clean($url));

        return;
    }

    //If headers are sent... do java redirect... if java disabled, do html redirect.
    echo '<script type="text/javascript">';
    echo 'window.location.href="'.$antiXss->xss_clean($url).'";';
    echo '</script>';
    echo '<noscript>';
    echo '<meta http-equiv="refresh" content="0;url='.$antiXss->xss_clean($url).'" />';
    echo '</noscript>';
}

// Include files
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare GET variables
$get_group = $superGlobal->get('group', 'GET');

// Redirect needed?
if (isset($_SERVER['HTTPS']) === true
    && $_SERVER['HTTPS'] !== 'on'
    && isset($SETTINGS['enable_sts']) === true
    && (int) $SETTINGS['enable_sts'] === 1
) {
    redirect('https://'.$superGlobal->get('HTTP_HOST', 'SERVER').$superGlobal->get('REQUEST_URI', 'SERVER'));
}

// Load pwComplexity
if (defined('TP_PW_COMPLEXITY') === false) {
    // Pw complexity levels
    if (isset($_SESSION['user_language']) === true && $_SESSION['user_language'] !== '0') {
        define(
            'TP_PW_COMPLEXITY',
            array(
                0 => array(0, langHdl('complex_level0'), 'fas fa-bolt text-danger'),
                25 => array(25, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'),
                50 => array(50, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
                60 => array(60, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'),
                70 => array(70, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
                80 => array(80, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'),
                90 => array(90, langHdl('complex_level6'), 'far fa-gem text-success'),
            )
        );
    }
}

// LOAD CPASSMAN SETTINGS
if (isset($SETTINGS['cpassman_dir']) === true
    && is_dir($SETTINGS['cpassman_dir'].'/install') === true
) {
    // Should we delete folder INSTALL?
    $row = DB::queryFirstRow(
        'SELECT valeur FROM '.prefixTable('misc').' WHERE type=%s AND intitule=%s',
        'install',
        'clear_install_folder'
    );
    if ($row['valeur'] === 'true') {
        /**
         * Permits to delete files and folders recursively.
         */
        function delTree($dir)
        {
            $directories = scandir($dir);
            if ($directories !== false) {
                $files = array_diff($directories, array('.', '..'));

                foreach ($files as $file) {
                    if (is_dir($dir.'/'.$file)) {
                        delTree($dir.'/'.$file);
                    } else {
                        try {
                            unlink($dir.'/'.$file);
                        } catch (Exception $e) {
                            // do nothing... php will ignore and continue
                        }
                    }
                }

                return @rmdir($dir);
            } else {
                return false;
            }
        }

        if (is_dir($SETTINGS['cpassman_dir'].'/install')) {
            // Set the permissions on the install directory and delete
            // is server Windows or Linux?
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                chmodRecursive($SETTINGS['cpassman_dir'].'/install', 0755, 0440);
            }
            delTree($SETTINGS['cpassman_dir'].'/install');
        }

        // Delete temporary install table
        DB::query('DROP TABLE IF EXISTS `_install`');

        // Delete tag
        DB::delete(
            prefixTable('misc'),
            'type=%s AND intitule=%s',
            'install',
            'clear_install_folder'
        );
    }
}

// Load Languages stuff
if (isset($languagesList) === false) {
    $languagesList = array();
    $rows = DB::query('SELECT * FROM '.prefixTable('languages').' GROUP BY name, label, code, flag, id ORDER BY name ASC');
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

if (isset($_SESSION['user_settings']['usertimezone']) === true && $_SESSION['user_settings']['usertimezone'] !== 'not_defined') {
    // use user timezone
    date_default_timezone_set($_SESSION['user_settings']['usertimezone']);
} elseif (isset($SETTINGS['timezone']) === false || $SETTINGS['timezone'] === null) {
    // use server timezone
    date_default_timezone_set('UTC');
    $SETTINGS['timezone'] = 'UTC';
} else {
    // use server timezone
    date_default_timezone_set($SETTINGS['timezone']);
}

// CHECK IF LOGOUT IS ASKED OR IF SESSION IS EXPIRED
if ((isset($_GET['session']) === true
    && $_GET['session'] == 'expired')
    || (null !== filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING)
    && filter_input(INPUT_POST, 'session', FILTER_SANITIZE_STRING) === 'expired')
) {
    // Clear User tempo key
    if (isset($_SESSION['user_id']) === true) {
        DB::update(
            prefixTable('users'),
            array(
                'key_tempo' => '',
                'timestamp' => '',
                'session_end' => '',
            ),
            'id=%i',
            $_SESSION['user_id']
        );
    }

    // REDIRECTION PAGE ERREUR
    echo '
    <script language="javascript" type="text/javascript">
    <!--
        sessionStorage.clear();
        window.location.href = "./includes/core/logout.php";
    -->
    </script>';
    exit;
}

// CHECK IF SESSION EXISTS AND IF SESSION IS VALID
if (empty($_SESSION['sessionDuration']) === false) {
    $dataSession = DB::queryFirstRow(
        'SELECT key_tempo FROM '.prefixTable('users').' WHERE id=%i',
        $_SESSION['user_id']
    );
} else {
    $dataSession['key_tempo'] = '';
}

// get some init
if (isset($_SESSION['user_id']) === false || (int) $_SESSION['user_id'] === 0) {
    $_SESSION['key'] = GenerateCryptKey(50, false, true, true, false);
    $_SESSION['user_id'] = 0;
    $_SESSION['id'] = 1;
}

if (isset($_SESSION['user_id']) === true
    && isset($_GET['type']) === false
    && isset($_GET['action']) === false
    && (int) $_SESSION['user_id'] !== 0
    && (empty($_SESSION['sessionDuration']) === true
    || $_SESSION['sessionDuration'] < time()
    || empty($_SESSION['key']) === true
    || empty($dataSession['key_tempo']) === true)
) {
    // Update table by deleting ID
    DB::update(
        prefixTable('users'),
        array(
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => '',
        ),
        'id=%i',
        $_SESSION['user_id']
    );

    //Log into DB the user's disconnection
    if (isset($SETTINGS['log_connections']) === true
        && (int) $SETTINGS['log_connections'] === 1
        && isset($_SESSION['login']) === true
        && empty($_SESSION['login']) === false
    ) {
        logEvents('user_connection', 'disconnection', $_SESSION['user_id'], $_SESSION['login']);
    }

    // erase session table
    session_unset();
    session_destroy();
    $_SESSION = array();

    //Redirection
    echo '
    <script language="javascript" type="text/javascript">
    <!--
    setTimeout(function(){document.location.href="index.php"}, 1);
    -->
    </script>';
}

// CHECK IF UPDATE IS NEEDED
if ((isset($SETTINGS['update_needed']) === true && ($SETTINGS['update_needed'] !== false
    || empty($SETTINGS['update_needed']) === true))
    && (isset($_SESSION['user_admin']) === true && $_SESSION['user_admin'] == 1)
) {
    $row = DB::queryFirstRow(
        'SELECT valeur FROM '.prefixTable('misc').' WHERE type=%s_type AND intitule=%s_intitule',
        array(
            'type' => 'admin',
            'intitule' => 'cpassman_version',
        )
    );
    if ($row['valeur'] != $SETTINGS_EXT['version']) {
        $SETTINGS['update_needed'] = true;
    } else {
        $SETTINGS['update_needed'] = false;
    }
}

/*
 * Set the personal SaltKey if authorized
 */
if (isset($SETTINGS['enable_personal_saltkey_cookie']) === true
    && $SETTINGS['enable_personal_saltkey_cookie'] == 1
    && isset($_SESSION['user_id']) === true
    && isset($_COOKIE['TeamPass_PFSK_'.md5($_SESSION['user_id'])]) === true
) {
    // Only defuse key
    if (substr($_COOKIE['TeamPass_PFSK_'.md5($_SESSION['user_id'])], 0, 3) === 'def') {
        $_SESSION['user_settings']['session_psk'] = $_COOKIE['TeamPass_PFSK_'.md5($_SESSION['user_id'])];
    } else {
        // Remove old cookie
        unset($_COOKIE['TeamPass_PFSK_'.md5($_SESSION['user_id'])]);
        setcookie('TeamPass_PFSK_'.md5($_SESSION['user_id']), '', time() - 3600, '/'); // empty value and old timestamp
    }
}

/* CHECK IF MAINTENANCE MODE
* IF yes then authorize all ADMIN connections and
* reject all others
*/
if (isset($SETTINGS['maintenance_mode']) === true && $SETTINGS['maintenance_mode'] == 1) {
    if (isset($_SESSION['user_admin']) === true && $_SESSION['user_admin'] != 1) {
        // Update table by deleting ID
        if (isset($_SESSION['user_id']) === true) {
            DB::update(
                prefixTable('users'),
                array(
                    'key_tempo' => '',
                    'timestamp' => '',
                    'session_end' => '',
                ),
                'id=%i',
                $_SESSION['user_id']
            );
        }

        //Log into DB the user's disconnection
        if (isset($SETTINGS['log_connections']) === true && $SETTINGS['log_connections'] == 1) {
            logEvents('user_connection', 'disconnection', $_SESSION['user_id'], $_SESSION['login']);
        }

        syslog(
            LOG_WARNING,
            'Unlog user: '.date('Y/m/d H:i:s')." {$_SERVER['REMOTE_ADDR']} ({$_SERVER['HTTP_USER_AGENT']})"
        );

        // erase session table
        $_SESSION = array();

        setcookie('pma_end_session');

        // REDIRECTION PAGE ERREUR
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        setTimeout(function(){document.location.href="./includes/core/logout.php"}, 10);
        -->
        </script>';
        exit;
    }
}

/* Force HTTPS Strict Transport Security */
if (isset($SETTINGS['enable_sts']) === true
    && (int) $SETTINGS['enable_sts'] === 1
    && isset($_SERVER['SSL_SERVER_CERT']) === true
) {
    // do a check to make sure that the certificate is not self signed.
    // In apache's SSL configuration make sure "SSLOptions +ExportCertData" in enabled
    $server_cert = openssl_x509_parse($_SERVER['SSL_SERVER_CERT']);
    $cert_name = $server_cert['name'];
    $cert_issuer = '';
    foreach ($server_cert['issuer'] as $key => $value) {
        if (is_array($value) === false) {
            $cert_issuer .= "/$key=$value";
        }
    }
    if (isset($cert_name) === true && empty($cert_name) === false && $cert_name != $cert_issuer) {
        if (isset($_SERVER['HTTPS'])) {
            header('Strict-Transport-Security: max-age=500');
            $_SESSION['error']['sts'] = 0;
        }
    } elseif ($cert_name == $cert_issuer) {
        $_SESSION['error']['sts'] = 1;
    }
}

/* LOAD INFORMATION CONCERNING USER */
if (isset($_SESSION['user_id']) === true && empty($_SESSION['user_id']) === false) {
    // query on user
    $data = DB::queryfirstrow(
        'SELECT login, admin, gestionnaire, can_manage_all_users, groupes_visibles, groupes_interdits, fonction_id, last_connexion FROM '.prefixTable('users').' WHERE id=%i',
        $_SESSION['user_id']
    );

    //Check if user has been deleted or unlogged
    if (empty($data) === true) {
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
        $_SESSION['no_access_folders'] = array();
        if (empty($data['groupes_visibles']) === false) {
            $_SESSION['groupes_visibles'] = array_filter(explode(';', $data['groupes_visibles']));
        }
        if (empty($data['groupes_interdits']) === false) {
            $_SESSION['no_access_folders'] = array_filter(explode(';', $data['groupes_interdits']));
        }

        if (isset($_SESSION['sessionDuration']) === false) {
            DB::update(
                prefixTable('users'),
                array(
                    'timestamp' => time(),
                ),
                'id=%i',
                $_SESSION['user_id']
            );
        }

        // get access rights
        identifyUserRights(
            $data['groupes_visibles'],
            $data['groupes_interdits'],
            $data['admin'],
            $data['fonction_id'],
            $SETTINGS
        );

        // user type
        if (isset($LANG) === true) {
            if ((int) $_SESSION['user_admin'] === 1) {
                $_SESSION['user_privilege'] = $LANG['god'];
            } elseif ((int) $_SESSION['user_manager'] === 1) {
                $_SESSION['user_privilege'] = $LANG['gestionnaire'];
            } elseif ((int) $_SESSION['user_read_only'] === 1) {
                $_SESSION['user_privilege'] = $LANG['read_only_account'];
            } else {
                $_SESSION['user_privilege'] = $LANG['user'];
            }
        }
    }
}

/*
* LOAD CATEGORIES
*/
if (isset($SETTINGS['item_extra_fields']) === true
    && (int) $SETTINGS['item_extra_fields'] === 1
    && isset($_GET['page']) === true
    && $_GET['page'] === 'items'
    && isset($_SESSION['fonction_id']) === true
) {
    $_SESSION['item_fields'] = array();
    $rows = DB::query(
        'SELECT *
        FROM '.prefixTable('categories').'
        WHERE level=%i',
        '0'
    );
    foreach ($rows as $record) {
        $arrFields = array();

        // get each field
        $rows2 = DB::query(
            'SELECT *
            FROM '.prefixTable('categories').'
            WHERE parent_id=%i
            ORDER BY `order` ASC',
            $record['id']
        );

        if (DB::count() > 0) {
            foreach ($rows2 as $field) {
                // Is this Field visibile by user?
                if ($field['role_visibility'] === 'all'
                    || count(
                        array_intersect(
                            explode(';', $_SESSION['fonction_id']),
                            explode(',', $field['role_visibility'])
                        )
                    ) > 0
                ) {
                    array_push(
                        $arrFields,
                        array(
                            'id' => $field['id'],
                            'title' => addslashes($field['title']),
                            'encrypted_data' => $field['encrypted_data'],
                            'type' => $field['type'],
                            'masked' => $field['masked'],
                            'is_mandatory' => $field['is_mandatory'],
                        )
                    );
                }
            }
        }

        // store the categories
        array_push(
            $_SESSION['item_fields'],
            array(
                'id' => $record['id'],
                'title' => addslashes($record['title']),
                'fields' => $arrFields,
            )
        );
    }
}

/*
* CHECK PASSWORD VALIDITY
* Don't take into consideration if LDAP in use
*/
$_SESSION['numDaysBeforePwExpiration'] = ''; //initiliaze variable
if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1) {
    $_SESSION['validite_pw'] = true;
    $_SESSION['last_pw_change'] = true;
} else {
    if (isset($_SESSION['last_pw_change']) === true) {
        if ((int) $SETTINGS['pw_life_duration'] === 0) {
            $_SESSION['numDaysBeforePwExpiration'] = 'infinite';
            $_SESSION['validite_pw'] = true;
        } else {
            $_SESSION['numDaysBeforePwExpiration'] = $SETTINGS['pw_life_duration'] - round(
                (mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $_SESSION['last_pw_change']) / (24 * 60 * 60)
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
if (isset($SETTINGS['roles_allowed_to_print']) === true
    && isset($_SESSION['user_roles']) === true
    && (!isset($_SESSION['temporary']['user_can_printout']) || empty($_SESSION['temporary']['user_can_printout']))
) {
    foreach (explode(';', $SETTINGS['roles_allowed_to_print']) as $role) {
        if (in_array($role, $_SESSION['user_roles']) === true) {
            $_SESSION['temporary']['user_can_printout'] = true;
        }
    }
}

/* CHECK NUMBER OF USER ONLINE */
DB::query('SELECT * FROM '.prefixTable('users').' WHERE timestamp>=%i', time() - 600);
$_SESSION['nb_users_online'] = DB::count();
