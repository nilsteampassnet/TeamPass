<?php

declare(strict_types=1);

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
 * @file      core.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use voku\helper\AntiXSS;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

require_once 'main.functions.php';

$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();


/**
 * Redirection management.
 *
 * @param string $url new url
 *
 * @return void refresh page to url
 */
function teampassRedirect($url)
{
    // Load AntiXSS
    $antiXss = new AntiXSS();
    if (! headers_sent()) {    //If headers not sent yet... then do php redirect
        header('Location: ' . $antiXss->xss_clean($url));
    }

    //If headers are sent... do java redirect... if java disabled, do html redirect.
    echo '<script type="text/javascript">';
    echo 'window.location.href="' . $antiXss->xss_clean($url) . '";';
    echo '</script>';
    echo '<noscript>';
    echo '<meta http-equiv="refresh" content="0;url=' . $antiXss->xss_clean($url) . '" />';
    echo '</noscript>';
}


// Prepare GET variables
$server = [];
$server['https'] = $request->isSecure();
$server['request_uri'] = $request->getRequestUri();
$server['http_host'] = $request->getHttpHost();
$server['ssl_server_cert'] = $request->server->get('ssl_server_cert');
$server['remote_addr'] = $request->server->get('remote_addr');
$server['http_user_agent'] = $request->server->get('http_user_agent');

$get = [];
$get['session'] = $request->query->get('session');
$get['action'] = $request->query->get('action');
$get['type'] = $request->query->get('type');
$get['page'] = $request->query->get('page');

// Redirect needed?
if (isset($server['https']) === true
    && $server['https'] !== 'on'
    && isset($SETTINGS['enable_sts']) === true
    && (int) $SETTINGS['enable_sts'] === 1
) {
    teampassRedirect('https://' . $server['http_host'] . $server['request_uri']);
}

// Load pwComplexity
if (defined('TP_PW_COMPLEXITY') === false) {
    // Pw complexity levels
    define(
        'TP_PW_COMPLEXITY',
        [
            TP_PW_STRENGTH_1 => [TP_PW_STRENGTH_1, $lang->get('complex_level1'), 'fas fa-thermometer-empty text-danger'],
            TP_PW_STRENGTH_2 => [TP_PW_STRENGTH_2, $lang->get('complex_level2'), 'fas fa-thermometer-quarter text-warning'],
            TP_PW_STRENGTH_3 => [TP_PW_STRENGTH_3, $lang->get('complex_level3'), 'fas fa-thermometer-half text-warning'],
            TP_PW_STRENGTH_4 => [TP_PW_STRENGTH_4, $lang->get('complex_level4'), 'fas fa-thermometer-three-quarters text-success'],
            TP_PW_STRENGTH_5 => [TP_PW_STRENGTH_5, $lang->get('complex_level5'), 'fas fa-thermometer-full text-success'],
        ]
    );
}

// LOAD CPASSMAN SETTINGS
if (
    isset($SETTINGS['cpassman_dir']) === true
    && is_dir($SETTINGS['cpassman_dir'] . '/install') === true
) {
    // Should we delete folder INSTALL?
    $row = DB::queryFirstRow(
        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s AND intitule=%s',
        'install',
        'clear_install_folder'
    );
    if (DB::count() > 0 && $row['valeur'] === 'true') {
        /**
         * Permits to delete files and folders recursively.
         *
         * @param string $dir Path
         *
         * @return bool
         */
        function delTree($dir)
        {
            $directories = scandir($dir);
            if ($directories !== false) {
                $files = array_diff($directories, ['.', '..']);
                foreach ($files as $file) {
                    if (is_dir($dir . '/' . $file)) {
                        delTree($dir . '/' . $file);
                    } else {
                        try {
                            unlink($dir . '/' . $file);
                        } catch (Exception $e) {
                            // do nothing... php will ignore and continue
                        }
                    }
                }

                return @rmdir($dir);
            }

            // else return false
            return false;
        }

        if (is_dir($SETTINGS['cpassman_dir'] . '/install')) {
            // Set the permissions on the install directory and delete
            // is server Windows or Linux?
            if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                recursiveChmod($SETTINGS['cpassman_dir'] . '/install', 0755, 0440);
            }
            delTree($SETTINGS['cpassman_dir'] . '/install');
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
    $languagesList = [];
    $rows = DB::query('SELECT * FROM ' . prefixTable('languages') . ' GROUP BY name, label, code, flag, id ORDER BY name ASC');
    foreach ($rows as $record) {
        array_push($languagesList, $record['name']);
        if ($session->get('user-language') === $record['name'] ) {
            $session->set('user-language_flag', $record['flag']);
            $session->set('user-language_code', $record['code']);
            //$session->set('user-language_label', $record['label']);
            //$session->set('user-language_id', $record['id']);
        }
    }
}

if ($session->has('user-timezone') && null !== $session->get('user-timezone') && $session->get('user-timezone') !== 'not_defined') {
    // use user timezone
    date_default_timezone_set($session->get('user-timezone'));
} elseif (isset($SETTINGS['timezone']) === false || $SETTINGS['timezone'] === null) {
    // use server timezone
    date_default_timezone_set('UTC');
    $SETTINGS['timezone'] = 'UTC';
} else {
    // use server timezone
    date_default_timezone_set($SETTINGS['timezone']);
}

// CHECK IF LOGOUT IS ASKED OR IF SESSION IS EXPIRED
if ((isset($get['session']) === true
        && $get['session'] === 'expired')
    || (filter_input(INPUT_POST, 'session', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null
        && filter_input(INPUT_POST, 'session', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'expired')
) {
    // Clear User tempo key
    if ($session->has('user-id') && null !== $session->get('user-id')) {
        DB::update(
            prefixTable('users'),
            [
                'key_tempo' => '',
                'timestamp' => '',
                'session_end' => '',
            ],
            'id=%i',
            $session->get('user-id')
        );
    }
    // CLear PHPSESSID
    $session->invalidate();
    
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
if (empty($session->get('user-session_duration')) === false) {
    $dataSession = DB::queryFirstRow(
        'SELECT key_tempo FROM ' . prefixTable('users') . ' WHERE id=%i',
        $session->get('user-id')
    );
} else {
    $dataSession['key_tempo'] = '';
}

if (
    $session->has('user-id') && null !== $session->get('user-id')
    && isset($get['type']) === false
    && isset($get['action']) === false
    && (int) $session->get('user-id') !== 0
    && (empty($session->get('user-session_duration')) === true
        || $session->get('user-session_duration') < time()
        || null === $session->get('key')
        || empty($dataSession['key_tempo']) === true)
) {
    // Update table by deleting ID
    DB::update(
        prefixTable('users'),
        [
            'key_tempo' => '',
            'timestamp' => '',
            'session_end' => '',
        ],
        'id=%i',
        $session->get('user-id')
    );
    //Log into DB the user's disconnection
    if (
        isset($SETTINGS['log_connections']) === true
        && (int) $SETTINGS['log_connections'] === 1
        && $session->has('user-login') && null !== $session->get('user-login')
        && empty($session->get('user-login')) === false
    ) {
        logEvents($SETTINGS, 'user_connection', 'disconnect', (string) $session->get('user-id'), $session->get('user-login'));
    }

    // erase session table
    $session->invalidate();
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
    && ($session->has('user-admin') && $session->get('user-admin') && null !== $session->get('user-admin') && $session->get('user-admin') === 1)
) {
    $row = DB::queryFirstRow(
        'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type=%s_type AND intitule=%s_intitule',
        [
            'type' => 'admin',
            'intitule' => 'teampass_version',
        ]
    );
    $count = DB::count();
    if ($count === 0 || $row['valeur'] !== TP_VERSION) {
        $SETTINGS['update_needed'] = true;
    } else {
        $SETTINGS['update_needed'] = false;
    }
}

/* CHECK IF MAINTENANCE MODE
* IF yes then authorize all ADMIN connections and
* reject all others
*/
if (isset($SETTINGS['maintenance_mode']) === true && (int) $SETTINGS['maintenance_mode'] === 1) {
    if ($session->has('user-admin') && (int) $session->get('user-admin') && null !== $session->get('user-admin') && (int) $session->get('user-admin') !== 1) {
        // Update table by deleting ID
        if ($session->has('user-id') && null !== $session->get('user-id')) {
            DB::update(
                prefixTable('users'),
                [
                    'key_tempo' => '',
                    'timestamp' => '',
                    'session_end' => '',
                ],
                'id=%i',
                $session->get('user-id')
            );
        }

        //Log into DB the user's disconnection
        if (isset($SETTINGS['log_connections']) === true && (int) $SETTINGS['log_connections'] === 1) {
            logEvents($SETTINGS, 'user_connection', 'disconnect', (string) $session->get('user-id'), $session->get('user-login'));
        }

        syslog(
            LOG_WARNING,
            'Unlog user: ' . date('Y/m/d H:i:s') . " {$server['remote_addr']} ({$server['http_user_agent']})"
        );
        // erase session table
        $session->invalidate();

        // REDIRECTION PAGE ERREUR
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        setTimeout(
            function() {
                document.location.href="./includes/core/logout.php"
            },
            10
        );
        -->
        </script>';
        exit;
    }
}

/* Force HTTPS Strict Transport Security */
if (
    isset($SETTINGS['enable_sts']) === true
    && (int) $SETTINGS['enable_sts'] === 1
    && isset($server['ssl_server_cert']) === true
) {
    // do a check to make sure that the certificate is not self signed.
    // In apache's SSL configuration make sure "SSLOptions +ExportCertData" in enabled
    $server_cert = openssl_x509_parse($server['ssl_server_cert']);
    $cert_name = $server_cert['name'];
    $cert_issuer = '';
    $issuer = (array) $server_cert['issuer']; // $issuer is always an array (according to the structure of $server_cert)

    foreach ($issuer as $key => $value) {
        if (is_array($value) === false) {
            $cert_issuer .= "/{$key}={$value}";
        }
    }
    
    if (isset($cert_name) === true && empty($cert_name) === false && $cert_name !== $cert_issuer) {
        if (isset($server['https'])) {
            header('Strict-Transport-Security: max-age=500');
            $session->set('system-error_sts', 0);
        }
    } elseif ($cert_name === $cert_issuer) {
        $session->set('system-error_sts', 1);
    }
}

/* LOAD INFORMATION CONCERNING USER */
if ($session->has('user-timezone') && null !== $session->get('user-id') && empty($session->get('user-id')) === false) {
    // query on user
    $data = DB::queryfirstrow(
        'SELECT login, admin, gestionnaire, can_manage_all_users, groupes_visibles, groupes_interdits, fonction_id, last_connexion, roles_from_ad_groups, auth_type, last_pw_change FROM ' . prefixTable('users') . ' WHERE id=%i',
        $session->get('user-id')
    );
    //Check if user has been deleted or unlogged
    if (empty($data) === true) {
        // erase session table
        $session->invalidate();
        //redirection to index
        echo '
        <script language="javascript" type="text/javascript">
        <!--
        setTimeout(function(){document.location.href="index.php"}, 10);
        -->
        </script>';
    } else {
        // update user's rights
        $session->set('user-admin', $data['admin']);
        $session->set('user-manager', $data['gestionnaire']);
        $session->set('user-can_manage_all_users', $data['can_manage_all_users']);
        $session->set('user-auth_type', $data['auth_type']);
        $session->set('user-last_pw_change', $data['last_pw_change']);

        $session->set('user-accessible_folders', []);
        $session->set('user-no_access_folders', []);
        if (empty($data['groupes_visibles']) === false) {
            $session->set('user-accessible_folders', array_filter(explode(';', $data['groupes_visibles'])));
        }
        if (empty($data['groupes_interdits']) === false) {
            $session->set('user-no_access_folders', array_filter(explode(';', $data['groupes_interdits'])));
        }

        if (null === $session->get('user-session_duration')) {
            DB::update(
                prefixTable('users'),
                [
                    'timestamp' => time(),
                ],
                'id=%i',
                $session->get('user-id')
            );
        }

        // get access rights
        identifyUserRights(
            $data['groupes_visibles'],
            $data['groupes_interdits'],
            $data['admin'],
            is_null($data['roles_from_ad_groups']) === true ? $data['fonction_id'] : (empty($data['roles_from_ad_groups']) === true ? $data['fonction_id'] : $data['fonction_id'] . ';' . $data['roles_from_ad_groups']),
            $SETTINGS
        );
        if ($session->has('user-can_create_root_folder') && (int) $session->get('user-can_create_root_folder') && null !== $session->get('user-can_create_root_folder') && (int) $session->get('user-can_create_root_folder') === 1) {
            SessionManager::addRemoveFromSessionArray('user-accessible_folders', [0], 'add');
        }

        // user type
        if (isset($LANG) === true) {
            if ((int) $session->get('user-admin') === 1) {
                $session->set('user-privilege', $LANG['god']);
            } elseif ((int) $session->get('user-manager') === 1) {
                $session->set('user-privilege', $LANG['gestionnaire']);
            } elseif ((int) $session->get('user-read_only') === 1) {
                $session->set('user-privilege', $LANG['read_only_account']);
            } else {
                $session->set('user-privilege', $LANG['user']);
            }
        }
    }
}

/*
* LOAD CATEGORIES
*/
if (
    isset($SETTINGS['item_extra_fields']) === true
    && (int) $SETTINGS['item_extra_fields'] === 1
    && isset($get['page']) === true
    && $get['page'] === 'items'
    && $session->has('user-roles') && null !== $session->get('user-roles')
) {
    $session->set('system-item_fields', []);
    $rows = DB::query(
        'SELECT *
            FROM ' . prefixTable('categories') . '
            WHERE level=%i',
        '0'
    );
    foreach ($rows as $record) {
        $arrFields = [];
        // get each field
        $rows2 = DB::query(
            'SELECT *
            FROM ' . prefixTable('categories') . '
            WHERE parent_id=%i
            ORDER BY `order` ASC',
            $record['id']
        );
        if (DB::count() > 0) {
            foreach ($rows2 as $field) {
                // Is this Field visibile by user?
                if (
                    $field['role_visibility'] === 'all'
                    || count(
                        array_intersect(
                            explode(';', $session->get('user-roles')),
                            explode(',', $field['role_visibility'])
                        )
                    ) > 0
                ) {
                    array_push(
                        $arrFields,
                        [
                            'id' => $field['id'],
                            'title' => addslashes($field['title']),
                            'encrypted_data' => $field['encrypted_data'],
                            'type' => $field['type'],
                            'masked' => $field['masked'],
                            'is_mandatory' => $field['is_mandatory'],
                            'regex' => $field['regex'],
                        ]
                    );
                }
            }
        }

        // store the categories
        SessionManager::addRemoveFromSessionAssociativeArray(
            'system-item_fields',
            [
                'id' => $record['id'],
                'title' => addslashes($record['title']),
                'fields' => $arrFields,
            ],
            'add'
        );
    }
}

/*
* CHECK PASSWORD VALIDITY
* Don't take into consideration if LDAP in use
*/
$session->set('user-num_days_before_exp', '');
//initiliaze variable
if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1 && $session->has('user-auth_type') && $session->get('user-auth_type') !== 'local') {
    $session->set('user-last_pw_change', 1);
    $session->set('user-validite_pw', 1);
} else {
    if ($session->has('user-last_pw_change') && null !== $session->get('user-last_pw_change')) {
        if ((int) $SETTINGS['pw_life_duration'] === 0) {
            $session->set('user-num_days_before_exp', 'infinite');
            $session->set('user-validite_pw', 1);
        } else {
            $session->set('user-num_days_before_exp', (int) $SETTINGS['pw_life_duration'] - round((mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')) - $session->get('user-last_pw_change')) / (24 * 60 * 60)));
            if ($session->get('user-num_days_before_exp') <= 0) {
                $session->set('user-validite_pw', 0);
            } else {
                $session->set('user-validite_pw', 1);
            }
        }
    } else {
        $session->set('user-validite_pw', 0);
    }
}

$session->set('user-can_printout', 0);
if (
    isset($SETTINGS['roles_allowed_to_print']) === true
    && $session->has('user-roles_array') && null !== $session->get('user-roles_array')
    && (null === $session->get('user-can_printout') || empty($session->get('user-can_printout')) === true)
) {
    foreach (explode(';', $SETTINGS['roles_allowed_to_print']) as $role) {
        if (in_array($role, $session->get('user-roles_array')) === true) {
            $session->set('user-can_printout', 1);
        }
    }
}

/* CHECK NUMBER OF USER ONLINE */
DB::query('SELECT * FROM ' . prefixTable('users') . ' WHERE timestamp>=%i', time() - 600);
$session->set('system-nb_users_online', DB::count());
