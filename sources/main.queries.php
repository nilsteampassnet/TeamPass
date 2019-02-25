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
$debugLdap = 0; //Can be used in order to debug LDAP authentication

if (isset($_SESSION) === false) {
    include_once 'SecureHandler.php';
    session_name('teampass_session');
    session_start();
}

if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1) {
    $_SESSION['error']['code'] = '1004';
    include '../error.php';
    exit();
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// DO CHECKS
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
if (isset($post_type) === true
    && ($post_type === 'ga_generate_qr'
    || $post_type === 'recovery_send_pw_by_email'
    || $post_type === 'recovery_generate_new_password')
) {
    // continue
    mainQuery($SETTINGS);
} elseif (isset($_SESSION['user_id']) === true
    && checkUser($_SESSION['user_id'], $_SESSION['key'], 'home', $SETTINGS) === false
) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
} elseif ((isset($_SESSION['user_id']) === true
    && isset($_SESSION['key'])) === true
    || (isset($post_type) === true && $post_type === 'change_user_language'
    && null !== filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES))
) {
    // continue
    mainQuery($SETTINGS);
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

/**
 * Undocumented function.
 */
function mainQuery($SETTINGS)
{
    header('Content-type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    error_reporting(E_ERROR);

    // Load config
    if (file_exists('../includes/config/tp.config.php')) {
        include '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include './includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }

    /*
    * Define Timezone
    **/
    if (isset($SETTINGS['timezone']) === true) {
        date_default_timezone_set($SETTINGS['timezone']);
    } else {
        date_default_timezone_set('UTC');
    }

    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

    // Includes
    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    // Connect to mysql server
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$host = DB_HOST;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    // User's language loading
    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';

    // Prepare post variables
    $post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
    $post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
    $post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

    // Ensure Complexity levels are translated
    if (defined('TP_PW_COMPLEXITY') === false) {
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

    // Manage type of action asked
    switch ($post_type) {
        case 'change_pw':
            // Check KEY
            if (isset($post_key) === false || empty($post_key) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            $post_key = $_SESSION['key'];

            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            $post_request_origin = filter_var($dataReceived['change_pw_origine'], FILTER_SANITIZE_STRING);
            $post_new_password = filter_var($dataReceived['new_pw'], FILTER_SANITIZE_STRING);
            $post_current_password = isset($dataReceived['current_pw']) === true ?
                filter_var($dataReceived['current_pw'], FILTER_SANITIZE_STRING) : '';
            $post_password_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $post_reset_private_key = filter_var($dataReceived['reset_private_key'], FILTER_SANITIZE_NUMBER_INT);

            // Prepare variables
            $post_new_password_hashed = $pwdlib->createPasswordHash($post_new_password);

            // User has decided to change is PW
            if ($post_request_origin === 'user_change'
                && $_SESSION['user_admin'] !== '1'
            ) {
                // check if expected security level is reached
                $data_roles = DB::queryfirstrow(
                    'SELECT fonction_id
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $_SESSION['user_id']
                );

                // check if badly written
                $data_roles['fonction_id'] = array_filter(
                    explode(',', str_replace(';', ',', $data_roles['fonction_id']))
                );
                $data_roles['fonction_id'] = implode(',', $data_roles['fonction_id']);
                DB::update(
                    prefixTable('users'),
                    array(
                        'fonction_id' => $data_roles['fonction_id'],
                        ),
                    'id = %i',
                    $_SESSION['user_id']
                );

                $data = DB::queryFirstRow(
                    'SELECT complexity
                    FROM '.prefixTable('roles_title').'
                    WHERE id IN ('.$data_roles['fonction_id'].')
                    ORDER BY complexity DESC'
                );

                if (intval($post_password_complexity) < intval($data['complexity'])) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('complexity_level_not_reached').'.<br>'.
                                langHdl('expected_complexity_level').': <b>'.TP_PW_COMPLEXITY[$data['complexity']][1].'</b>',
                        ),
                        'encode'
                    );
                    break;
                }

                // Get a string with the old pw array
                $lastPw = explode(';', $_SESSION['last_pw']);
                // if size is bigger then clean the array
                if (sizeof($lastPw) > $SETTINGS['number_of_used_pw']
                    && $SETTINGS['number_of_used_pw'] > 0
                ) {
                    for ($x_counter = 0; $x_counter < $SETTINGS['number_of_used_pw']; ++$x_counter) {
                        unset($lastPw[$x_counter]);
                    }
                    // reinit SESSION
                    $_SESSION['last_pw'] = implode(';', $lastPw);
                // specific case where admin setting "number_of_used_pw"
                } elseif ($SETTINGS['number_of_used_pw'] == 0) {
                    $_SESSION['last_pw'] = '';
                    $lastPw = array();
                }

                // check if new pw is different that old ones
                if (in_array($post_new_password_hashed, $lastPw) && count($lastPw) > 0) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('password_already_used'),
                        ),
                        'encode'
                    );
                    break;
                }

                // update old pw with new pw
                if (sizeof($lastPw) == ($SETTINGS['number_of_used_pw'] + 1)) {
                    unset($lastPw[0]);
                } else {
                    array_push($lastPw, $newPw);
                }
                // create a list of last pw based on the table
                $oldPw = '';
                foreach ($lastPw as $elem) {
                    if (!empty($elem)) {
                        if (empty($oldPw)) {
                            $oldPw = $elem;
                        } else {
                            $oldPw .= ';'.$elem;
                        }
                    }
                }

                // update sessions
                $_SESSION['last_pw'] = $oldPw;
                $_SESSION['last_pw_change'] = mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y'));
                $_SESSION['validite_pw'] = true;

                // BEfore updating, check that the pwd is correct
                if ($pwdlib->verifyPasswordHash($post_new_password, $post_new_password_hashed) === true) {
                    // Should we reset the privateKey?
                    if ((int) $post_reset_private_key === 1) {
                        $userKeys = generateUserKeys($post_new_password);
                        $_SESSION['user']['public_key'] = $userKeys['public_key'];
                        $_SESSION['user']['private_key'] = $userKeys['private_key_clear'];
                        $special_action = 'reset_private_key';
                    } else {
                        $special_action = 'none';
                    }

                    // update DB
                    DB::update(
                        prefixTable('users'),
                        array(
                            'pw' => $post_new_password_hashed,
                            'last_pw_change' => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')),
                            'last_pw' => $oldPw,
                            'special' => $special_action,
                            'private_key' => encryptPrivateKey($post_new_password, $_SESSION['user']['private_key']),
                            ),
                        'id = %i',
                        $_SESSION['user_id']
                    );
                    // update LOG
                    logEvents('user_mngt', 'at_user_pwd_changed', $_SESSION['user_id'], $_SESSION['login'], $_SESSION['user_id']);

                    // Send back
                    echo prepareExchangedData(
                        array(
                            'error' => false,
                            'message' => '',
                        ),
                        'encode'
                    );
                    break;
                } else {
                    // Send back
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('pwd_hash_not_correct'),
                        ),
                        'encode'
                    );
                    break;
                }

                // ADMIN has decided to change the USER's PW
            } elseif (null !== filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING)
                && ((filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === 'admin_change'
                    || filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === 'user_change'
                    ) && ((int) $_SESSION['user_admin'] === 1 || (int) $_SESSION['user_manager'] === 1
                    || (int) $_SESSION['user_can_manage_all_users'] === 1)
                )
            ) {
                // check if user is admin / Manager
                $userInfo = DB::queryFirstRow(
                    'SELECT admin, gestionnaire
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $_SESSION['user_id']
                );
                if ((int) $userInfo['admin'] !== 1 && (int) $userInfo['gestionnaire'] !== 1) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('not_admin_or_manager'),
                        ),
                        'encode'
                    );
                    break;
                }

                // BEfore updating, check that the pwd is correct
                if ($pwdlib->verifyPasswordHash(htmlspecialchars_decode($dataReceived['new_pw']), $newPw) === true) {
                    // adapt
                    if (filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === 'user_change') {
                        $dataReceived['user_id'] = $_SESSION['user_id'];
                    }

                    // update DB
                    DB::update(
                        prefixTable('users'),
                        array(
                            'pw' => $newPw,
                            'last_pw_change' => mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('y')),
                            ),
                        'id = %i',
                        $dataReceived['user_id']
                    );

                    // update LOG
                    logEvents('user_mngt', 'at_user_pwd_changed', $_SESSION['user_id'], $_SESSION['login'], $dataReceived['user_id']);

                    /*
                    //Send email to user
                    if (filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) !== 'admin_change') {
                        $row = DB::queryFirstRow(
                            'SELECT email FROM '.prefixTable('users').'
                            WHERE id = %i',
                            $dataReceived['user_id']
                        );
                        if (empty($row['email']) && isset($SETTINGS['enable_email_notification_on_user_pw_change']) === false
                            && (int) $SETTINGS['enable_email_notification_on_user_pw_change'] === 1
                        ) {
                            sendEmail(
                                langHdl('forgot_pw_email_subject'),
                                langHdl('forgot_pw_email_body').' '.htmlspecialchars_decode($dataReceived['new_pw']),
                                $row[0],
                                $SETTINGS,
                                langHdl('forgot_pw_email_altbody_1').' '.htmlspecialchars_decode($dataReceived['new_pw'])
                            );
                        }
                    }
                    */

                    // Send back
                    echo prepareExchangedData(
                        array(
                            'error' => false,
                            'message' => '',
                        ),
                        'encode'
                    );
                    break;
                } else {
                    // Send back
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('pwd_hash_not_correct'),
                        ),
                        'encode'
                    );
                    break;
                }

                // ADMIN first login
            } else {
                // DEFAULT case
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                    ),
                    'encode'
                );
            }
            break;

        /*
         * This will generate the QR Google Authenticator
         */
        case 'ga_generate_qr':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_demand_origin = filter_var($dataReceived['demand_origin'], FILTER_SANITIZE_NUMBER_INT);
            $post_send_mail = filter_var($dataReceived['send_email'], FILTER_SANITIZE_STRING);
            $post_login = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
            $post_pwd = filter_var($dataReceived['pwd'], FILTER_SANITIZE_STRING);

            // is this allowed by setting
            if ((isset($SETTINGS['ga_reset_by_user']) === false || (int) $SETTINGS['ga_reset_by_user'] !== 1)
                && (null === $post_demand_origin || $post_demand_origin !== 'users_management_list')
            ) {
                // User cannot ask for a new code
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }
            $ldap_user_never_auth = false;

            // Check if user exists
            if (null === $post_id || empty($post_id) === true) {
                // Get data about user
                $data = DB::queryfirstrow(
                    'SELECT id, email, pw
                    FROM '.prefixTable('users').'
                    WHERE login = %s',
                    $post_login
                );
            } else {
                $data = DB::queryfirstrow(
                    'SELECT id, login, email, pw
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $post_id
                );
                $post_login = $data['login'];
                $post_pwd = $data['pw'];
            }
            // Get number of returned users
            $counter = DB::count();

            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', $SETTINGS['cpassman_dir'].'/includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();

            // If LDAP enabled and counter = 0 then perhaps new user to add
            if (isset($SETTINGS['ldap_mode']) === true && (int) $SETTINGS['ldap_mode'] === 1 && $counter === 0) {
                $ldap_info_user = json_decode(
                    connectLDAP($login, $pwd, $SETTINGS)
                );
                if ($ldap_info_user->{'user_found'} === true) {
                    $data['email'] = $ldap_info_user->{'email'};
                    $counter = 1;
                    $ldap_user_never_auth = true;
                }
            }

            // Do treatment
            if ($counter === 0) {
                // Not a registered user !
                logEvents('failed_auth', 'user_not_exists', '', stripslashes($login), stripslashes($login));
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('no_user'),
                    ),
                    'encode'
                );
            } elseif (isset($pwd) === true
                && isset($data['pw']) === true
                && $pwdlib->verifyPasswordHash($pwd, $data['pw']) === false
                && $post_demand_origin !== 'users_management_list'
            ) {
                // checked the given password
                logEvents('failed_auth', 'user_password_not_correct', '', stripslashes($login), stripslashes($login));
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('no_user'),
                    ),
                    'encode'
                );
            } else {
                if (empty($data['email']) === true) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('no_email'),
                        ),
                        'encode'
                    );
                } else {
                    // generate new GA user code
                    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php';
                    $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);
                    $gaSecretKey = $tfa->createSecret();
                    $gaTemporaryCode = GenerateCryptKey(12);

                    // save the code
                    if ($ldap_user_never_auth === false) {
                        DB::update(
                            prefixTable('users'),
                            array(
                                'ga' => $gaSecretKey,
                                'ga_temporary_code' => $gaTemporaryCode,
                                ),
                            'id = %i',
                            $data['id']
                        );
                    } else {
                        // save the code but also create an account in database
                        DB::insert(
                            prefixTable('users'),
                            array(
                                'login' => $login,
                                'pw' => $pwdlib->createPasswordHash($pwd),
                                'email' => $data['email'],
                                'name' => $ldap_info_user->{'name'},
                                'lastname' => $ldap_info_user->{'lastname'},
                                'admin' => '0',
                                'gestionnaire' => '0',
                                'can_manage_all_users' => '0',
                                'personal_folder' => $SETTINGS['enable_pf_feature'] === '1' ? '1' : '0',
                                'fonction_id' => isset($SETTINGS['ldap_new_user_role']) === true ? $SETTINGS['ldap_new_user_role'] : '0',
                                'groupes_interdits' => '',
                                'groupes_visibles' => '',
                                'last_pw_change' => time(),
                                'user_language' => $SETTINGS['default_language'],
                                'encrypted_psk' => '',
                                'isAdministratedByRole' => (isset($SETTINGS['ldap_new_user_is_administrated_by']) === true && empty($SETTINGS['ldap_new_user_is_administrated_by']) === false) ? $SETTINGS['ldap_new_user_is_administrated_by'] : 0,
                                'ga' => $gaSecretKey,
                                'ga_temporary_code' => $gaTemporaryCode,
                                'avatar_thumb' => '',
                                'avatar' => '',
                                'psk' => '',
                                'favourites' => '',
                                'session_end' => '',
                                'last_pw' => '',
                                'latest_items' => '',
                                'last_pw_change' => '',
                                'key_tempo' => '',
                                'derniers' => '',
                            )
                        );
                        $newUserId = DB::insertId();
                        // Create personnal folder
                        if (isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === '1') {
                            DB::insert(
                                prefixTable('nested_tree'),
                                array(
                                    'parent_id' => '0',
                                    'title' => $newUserId,
                                    'bloquer_creation' => '0',
                                    'bloquer_modification' => '0',
                                    'personal_folder' => '1',
                                )
                            );
                        }
                        $data['id'] = $newUserId;
                    }

                    // Log event
                    logEvents('user_connection', 'at_2fa_google_code_send_by_email', $data['id'], stripslashes($login), stripslashes($login));

                    // send mail?
                    if ((int) $post_send_mail === 1) {
                        json_decode(
                            sendEmail(
                                langHdl('email_ga_subject'),
                                str_replace(
                                    '#2FACode#',
                                    $gaTemporaryCode,
                                    langHdl('email_ga_text')
                                ),
                                $data['email'],
                                $SETTINGS
                            ),
                            true
                        );

                        // send back
                        echo prepareExchangedData(
                            array(
                                'error' => false,
                                'message' => '',
                                'email' => $data['email'],
                                'email_result' => str_replace(
                                    '#email#',
                                    '<b>'.obfuscateEmail($data['email']).'</b>',
                                    addslashes(langHdl('admin_email_result_ok'))
                                ),
                            ),
                            'encode'
                        );
                    } else {
                        // send back
                        echo prepareExchangedData(
                            array(
                                'error' => false,
                                'message' => '',
                                'email' => $data['email'],
                                'email_result' => str_replace(
                                    '#email#',
                                    '<b>'.obfuscateEmail($data['email']).'</b>',
                                    addslashes(langHdl('admin_email_result_ok'))
                                ),
                            ),
                            'encode'
                        );
                    }
                }
            }
            break;
        /*
         * Increase the session time of User
         */
        case 'increase_session_time':
            // check if session is not already expired.
            if ($_SESSION['sessionDuration'] > time()) {
                // Calculate end of session
                $_SESSION['sessionDuration'] = (int) ($_SESSION['sessionDuration'] + filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT));
                // Update table
                DB::update(
                    prefixTable('users'),
                    array(
                        'session_end' => $_SESSION['sessionDuration'],
                    ),
                    'id = %i',
                    $_SESSION['user_id']
                );
                // Return data
                echo '[{"new_value":"'.$_SESSION['sessionDuration'].'"}]';
            } else {
                echo '[{"new_value":"expired"}]';
            }
            break;
        /*
         * Hide maintenance message
         */
        case 'hide_maintenance':
            $_SESSION['hide_maintenance'] = 1;
            break;

        /*
         * Used in order to send the password to the user by email
         */
        case 'recovery_send_pw_by_email':
            // generate key
            $key = GenerateCryptKey(50);

            // Prepare post variables
            $post_login = mysqli_escape_string($link, filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING));

            // Get account and pw associated to email
            DB::query(
                'SELECT *
                FROM '.prefixTable('users').'
                WHERE login = %s',
                $post_login
            );
            $counter = DB::count();
            if ($counter != 0) {
                $data = DB::queryFirstRow(
                    'SELECT login, pw, email FROM '.prefixTable('users').' WHERE login = %s',
                    $post_login
                );
                $textMail = langHdl('forgot_pw_email_body_1').' <a href="'.
                    $SETTINGS['cpassman_url'].'/index.php?action=password_recovery&key='.$key.
                    '&login='.$post_login.'">'.$SETTINGS['cpassman_url'].
                    '/index.php?action=password_recovery&key='.$key.'&login='.$post_login.'</a>.<br><br>'.langHdl('thku');
                $textMailAlt = langHdl('forgot_pw_email_altbody_1').' '.langHdl('at_login').' : '.$post_login.' - '.
                    langHdl('index_password').' : '.md5($data['pw']);

                // Check if email has already a key in DB
                DB::query(
                    'SELECT * FROM '.prefixTable('misc').' WHERE intitule = %s AND type = %s',
                    $post_login,
                    'password_recovery'
                );
                $counter = DB::count();
                if ($counter != 0) {
                    DB::update(
                        prefixTable('misc'),
                        array(
                            'valeur' => $key,
                        ),
                        'type = %s and intitule = %s',
                        'password_recovery',
                        $post_login
                    );
                } else {
                    // store in DB the password recovery informations
                    DB::insert(
                        prefixTable('misc'),
                        array(
                            'type' => 'password_recovery',
                            'intitule' => $post_login,
                            'valeur' => $key,
                        )
                    );
                }

                $ret = json_decode(
                    sendEmail(
                        langHdl('forgot_pw_email_subject'),
                        $textMail,
                        $data['email'],
                        $SETTINGS,
                        $textMailAlt
                    ),
                    true
                );

                echo '[{"error":"'.$ret['error'].'" , "message":"'.$ret['message'].'"}]';
            } else {
                // no one has this email ... alert
                echo '[{"error":"error_email" , "message":"'.langHdl('forgot_my_pw_error_email_not_exist').'"}]';
            }
            break;

        // Send to user his new pw if key is conform
        case 'recovery_generate_new_password':
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );

            // Prepare variables
            $login = htmlspecialchars_decode($dataReceived['login']);
            $key = htmlspecialchars_decode($dataReceived['key']);

            // check if key is okay
            $data = DB::queryFirstRow(
                'SELECT valeur FROM '.prefixTable('misc').' WHERE intitule = %s AND type = %s',
                mysqli_escape_string($link, $login),
                'password_recovery'
            );
            if ($key == $data['valeur']) {
                // load passwordLib library
                $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                $pwdlib->register();
                $pwdlib = new PasswordLib\PasswordLib();

                // generate key
                $newPwNotCrypted = $pwdlib->getRandomToken(10);

                // Prepare variables
                $newPw = $pwdlib->createPasswordHash(($newPwNotCrypted));

                // update DB
                DB::update(
                    prefixTable('users'),
                    array(
                        'pw' => $newPw,
                        ),
                    'login = %s',
                    mysqli_escape_string($link, $login)
                );
                // Delete recovery in DB
                DB::delete(
                    prefixTable('misc'),
                    'type = %s AND intitule = %s AND valeur = %s',
                    'password_recovery',
                    mysqli_escape_string($link, $login),
                    $key
                );
                // Get email
                $dataUser = DB::queryFirstRow(
                    'SELECT email FROM '.prefixTable('users').' WHERE login = %s',
                    mysqli_escape_string($link, $login)
                );

                $_SESSION['validite_pw'] = false;
                // send to user
                $ret = json_decode(
                    sendEmail(
                        langHdl('forgot_pw_email_subject_confirm'),
                        langHdl('forgot_pw_email_body').' '.$newPwNotCrypted,
                        $dataUser['email'],
                        $SETTINGS,
                        strip_tags(langHdl('forgot_pw_email_body')).' '.$newPwNotCrypted
                    ),
                    true
                );
                // send email
                if (empty($ret['error'])) {
                    echo 'done';
                } else {
                    echo $ret['message'];
                }
            }
            break;
        /*
         * Store the personal saltkey
         */
        case 'store_personal_saltkey':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );
            $filter_score = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
            $filter_psk = filter_var($dataReceived['psk'], FILTER_SANITIZE_STRING);

            // manage store
            if ($filter_psk !== '') {
                // store in session the cleartext for psk
                $_SESSION['user_settings']['clear_psk'] = $filter_psk;

                // check if encrypted_psk is in database. If not, add it
                if (isset($_SESSION['user_settings']['encrypted_psk']) === false
                    || (isset($_SESSION['user_settings']['encrypted_psk']) === true && empty($_SESSION['user_settings']['encrypted_psk']) === true)
                ) {
                    // Check if security level is reach (if enabled)
                    if (isset($SETTINGS['personal_saltkey_security_level']) === true) {
                        // Did we received the pass score
                        if (empty($filter_score) === false) {
                            if (intval($SETTINGS['personal_saltkey_security_level']) > $filter_score) {
                                echo prepareExchangedData(
                                    array(
                                        'error' => true,
                                        'message' => langHdl('security_level_not_reached_but_psk_correct'),
                                    ),
                                    'encode'
                                );
                                break;
                            }
                        }
                    }
                    // generate it based upon clear psk
                    $_SESSION['user_settings']['encrypted_psk'] = defuse_generate_personal_key($filter_psk);

                    // store it in DB
                    DB::update(
                        prefixTable('users'),
                        array(
                            'encrypted_psk' => $_SESSION['user_settings']['encrypted_psk'],
                            ),
                        'id = %i',
                        $_SESSION['user_id']
                    );
                }

                // check if psk is correct.
                $user_key_encoded = defuse_validate_personal_key(
                    $filter_psk,
                    $_SESSION['user_settings']['encrypted_psk']
                );

                if (strpos($user_key_encoded, 'Error ') !== false) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('bad_psk'),
                        ),
                        'encode'
                    );
                    break;
                } else {
                    // Check if security level is reach (if enabled)
                    if (isset($SETTINGS['personal_saltkey_security_level']) === true) {
                        // Did we received the pass score
                        if (empty($filter_score) === false) {
                            if (intval($SETTINGS['personal_saltkey_security_level']) > intval($filter_score)) {
                                echo prepareExchangedData(
                                    array(
                                        'error' => true,
                                        'message' => langHdl('security_level_not_reached_but_psk_correct'),
                                    ),
                                    'encode'
                                );
                                break;
                            }
                        }
                    }
                    // Store PSK
                    $_SESSION['user_settings']['session_psk'] = $user_key_encoded;
                    setcookie(
                        'TeamPass_PFSK_'.md5($_SESSION['user_id']),
                        $user_key_encoded,
                        (!isset($SETTINGS['personal_saltkey_cookie_duration']) || $SETTINGS['personal_saltkey_cookie_duration'] == 0) ? time() + 60 * 60 * 24 : time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                        '/'
                    );
                }
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('psk_required'),
                    ),
                    'encode'
                );
                break;
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                    'encrypted_psk' => $user_key_encoded,
                ),
                'encode'
            );

            break;
        /*
         * Change the personal saltkey
         */
        case 'change_personal_saltkey':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            //init
            $list = '';
            $number = 0;

            // Get key from user
            $userInfo = DB::queryfirstrow(
                'SELECT encrypted_psk
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $_SESSION['user_id']
            );

            // If never set then exit
            if (empty($userInfo['encrypted_psk']) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('psk_never_set_by_user'),
                    ),
                    'encode'
                );
                break;
            }

            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );

            if (count($dataReceived) > 0) {
                // Prepare variables
                $post_psk = filter_var($dataReceived['new-saltkey'], FILTER_SANITIZE_STRING);
                $post_old_psk = filter_var($dataReceived['current-saltkey'], FILTER_SANITIZE_STRING);
                $post_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_STRING);
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_empty_data'),
                    ),
                    'encode'
                );
                break;
            }

            // check old psk
            $user_key_encoded = defuse_validate_personal_key(
                $post_old_psk,
                $userInfo['encrypted_psk']
            );
            if (strpos($user_key_encoded, 'Error ') !== false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('bad_psk'),
                    ),
                    'encode'
                );
                break;
            } else {
                // Store PSK
                $_SESSION['user_settings']['encrypted_oldpsk'] = $userInfo['encrypted_psk'];
            }

            // Check if security level is reach (if enabled)
            if (isset($SETTINGS['personal_saltkey_security_level']) === true) {
                // Did we received the pass score
                if (empty($post_complexity) === false) {
                    if (intval($SETTINGS['personal_saltkey_security_level']) > intval($post_complexity)) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => langHdl('security_level_not_reached_but_psk_correct'),
                            ),
                            'encode'
                        );
                        break;
                    }
                }
            }

            // generate the new encrypted psk based upon clear psk
            $_SESSION['user_settings']['session_psk'] = defuse_generate_personal_key($post_psk);

            // store it in DB
            DB::update(
                prefixTable('users'),
                array(
                    'encrypted_psk' => $_SESSION['user_settings']['session_psk'],
                    ),
                'id = %i',
                $_SESSION['user_id']
            );

            // Log event
            logEvents('user_mngt', 'at_user_psk_changed', $_SESSION['user_id'], $_SESSION['login'], $_SESSION['user_id']);

            // Change encryption
            // Build list of items to be re-encrypted
            $rows = DB::query(
                'SELECT i.id as id, i.pw as pw
                FROM '.prefixTable('items').' as i
                INNER JOIN '.prefixTable('log_items').' as l ON (i.id=l.id_item)
                WHERE i.perso = %i AND l.id_user= %i AND l.action = %s',
                '1',
                $_SESSION['user_id'],
                'at_creation'
            );
            $number = DB::count();
            foreach ($rows as $record) {
                if (!empty($record['pw'])) {
                    if (empty($list)) {
                        $list = $record['id'];
                    } else {
                        $list .= ','.$record['id'];
                    }
                }
            }

            // change salt
            setcookie(
                'TeamPass_PFSK_'.md5($_SESSION['user_id']),
                $_SESSION['user_settings']['session_psk'],
                time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                '/'
            );

            echo prepareExchangedData(
                array(
                    'list' => $list,
                    'error' => false,
                    'nb_total' => $number,
                ),
                'encode'
            );
            break;

        /*
         * Reset the personal saltkey
         */
        case 'reset_personal_saltkey':
            // Allowed?
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );

            if (count($dataReceived) > 0) {
                // Prepare variables
                $post_psk = filter_var($dataReceived['psk'], FILTER_SANITIZE_STRING);
                $post_complexity = filter_var($dataReceived['complexity'], FILTER_SANITIZE_NUMBER_INT);
                $post_delete_items = filter_var($dataReceived['delete_items'], FILTER_SANITIZE_NUMBER_INT);

                if (empty($_SESSION['user_id']) === false) {
                    // delete all previous items of this user
                    $rows = DB::query(
                        'SELECT i.id as id
                        FROM '.prefixTable('items').' as i
                        INNER JOIN '.prefixTable('log_items').' as l ON (i.id=l.id_item)
                        WHERE i.perso = %i AND l.id_user= %i AND l.action = %s',
                        '1',
                        $_SESSION['user_id'],
                        'at_creation'
                    );
                    foreach ($rows as $record) {
                        if ($post_delete_items === 1) {
                            // delete in ITEMS table
                            DB::delete(prefixTable('items'), 'id = %i', $record['id']);
                            // delete in LOGS table
                            DB::delete(prefixTable('log_items'), 'id_item = %i', $record['id']);
                            // delete from CACHE table
                            updateCacheTable('delete_value', $SETTINGS, $record['id']);
                        } else {
                            // Delete password
                            DB::update(
                                prefixTable('items'),
                                array(
                                    'pw' => '',
                                    ),
                                'id = %i',
                                $record['id']
                            );
                        }
                    }

                    // generate the new encrypted psk based upon clear psk
                    $_SESSION['user_settings']['session_psk'] = defuse_generate_personal_key($post_psk);

                    // store it in DB
                    DB::update(
                        prefixTable('users'),
                        array(
                            'encrypted_psk' => $_SESSION['user_settings']['session_psk'],
                            ),
                        'id = %i',
                        $_SESSION['user_id']
                    );

                    // Log event
                    logEvents('user_mngt', 'at_user_psk_changed', $_SESSION['user_id'], $_SESSION['login'], $_SESSION['user_id']);

                    // change salt
                    setcookie(
                        'TeamPass_PFSK_'.md5($_SESSION['user_id']),
                        $_SESSION['user_settings']['session_psk'],
                        time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                        '/'
                    );

                    // Log event
                    logEvents('user_mngt', 'at_user_psk_reseted', $_SESSION['user_id'], $_SESSION['login'], $_SESSION['user_id']);
                }
            }
            break;
        /*
         * Change the user's language
         */
        case 'change_user_language':
            if (!empty($_SESSION['user_id'])) {
                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData(
                    filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                    'decode'
                );
                // Prepare variables
                $language = $dataReceived['lang'];
                // update DB
                DB::update(
                    prefixTable('users'),
                    array(
                        'user_language' => $language,
                        ),
                    'id = %i',
                    $_SESSION['user_id']
                );
                $_SESSION['user_language'] = $language;
                echo 'done';
            } else {
                $_SESSION['user_language'] = '';
                echo 'done';
            }
            break;
        /*
         * Send emails not sent
         */
        case 'send_waiting_emails':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            if (isset($SETTINGS['enable_send_email_on_user_login'])
                && $SETTINGS['enable_send_email_on_user_login'] === '1'
            ) {
                $row = DB::queryFirstRow(
                    'SELECT valeur FROM '.prefixTable('misc').' WHERE type = %s AND intitule = %s',
                    'cron',
                    'sending_emails'
                );
                if ((time() - $row['valeur']) >= 300 || $row['valeur'] == 0) {
                    $rows = DB::query('SELECT * FROM '.prefixTable('emails').' WHERE status != %s', 'sent');
                    foreach ($rows as $record) {
                        // Send email
                        $ret = json_decode(
                            sendEmail(
                                $record['subject'],
                                $record['body'],
                                $record['receivers'],
                                $SETTINGS
                            ),
                            true
                        );

                        if ($ret['error'] === 'error_mail_not_send') {
                            $status = 'not_sent';
                        } else {
                            $status = 'sent';
                        }

                        // update item_id in files table
                        DB::update(
                            prefixTable('emails'),
                            array(
                                'status' => $status,
                                ),
                            'timestamp = %s',
                            $record['timestamp']
                        );
                    }
                }
                // update cron time
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => time(),
                        ),
                    'intitule = %s AND type = %s',
                    'sending_emails',
                    'cron'
                );
            }
            break;

        /*
         * Store error
         */
        case 'store_error':
            if (!empty($_SESSION['user_id'])) {
                // update DB
                logEvents(
                    'error',
                    urldecode(filter_input(INPUT_POST, 'error', FILTER_SANITIZE_STRING)),
                    $_SESSION['user_id'],
                    $_SESSION['login']
                );
            }
            break;

        /*
         * Generate a password generic
         */
        case 'generate_password':
            if (filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) > $SETTINGS['pwd_maximum_length']) {
                echo prepareExchangedData(
                    array(
                        'error_msg' => 'Password length is too long! ',
                        'error' => 'true',
                    ),
                    'encode'
                );
                break;
            }

            $generator = new SplClassLoader('PasswordGenerator\Generator', '../includes/libraries');
            $generator->register();
            $generator = new PasswordGenerator\Generator\ComputerPasswordGenerator();

            // Is PHP7 being used?
            if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
                $php7generator = new SplClassLoader('PasswordGenerator\RandomGenerator', '../includes/libraries');
                $php7generator->register();
                $generator->setRandomGenerator(new PasswordGenerator\RandomGenerator\Php7RandomGenerator());
            }

            // Manage size
            $size = (int) filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT);
            $generator->setLength(($size <= 0) ? 10 : $size);

            if (null !== filter_input(INPUT_POST, 'secure_pwd', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'secure_pwd', FILTER_SANITIZE_STRING) === 'true'
            ) {
                $generator->setSymbols(true);
                $generator->setLowercase(true);
                $generator->setUppercase(true);
                $generator->setNumbers(true);
            } else {
                $generator->setLowercase((filter_input(INPUT_POST, 'lowercase', FILTER_SANITIZE_STRING) === 'true') ? true : false);
                $generator->setUppercase((filter_input(INPUT_POST, 'capitalize', FILTER_SANITIZE_STRING) === 'true') ? true : false);
                $generator->setNumbers((filter_input(INPUT_POST, 'numerals', FILTER_SANITIZE_STRING) === 'true') ? true : false);
                $generator->setSymbols((filter_input(INPUT_POST, 'symbols', FILTER_SANITIZE_STRING) === 'true') ? true : false);
            }

            echo prepareExchangedData(
                array(
                    'key' => $generator->generatePasswords(),
                    'error' => '',
                ),
                'encode'
            );
            break;
        /*
         * Check if user exists and send back if psk is set
         */
        case 'check_login_exists':
            $data = DB::query(
                'SELECT login, psk FROM '.prefixTable('users').'
                WHERE login = %i',
                mysqli_escape_string($link, stripslashes(filter_input(INPUT_POST, 'userId', FILTER_SANITIZE_NUMBER_INT)))
            );
            if (empty($data['login'])) {
                $userOk = false;
            } else {
                $userOk = true;
            }

            echo '[{"login" : "'.$userOk.'", "psk":"0"}]';
            break;
        /*
         * Make statistics on item
         */
        case 'item_stat':
            if (null !== filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING) === 'item'
            ) {
                $data = DB::queryfirstrow(
                    'SELECT view FROM '.prefixTable('statistics').' WHERE scope = %s AND item_id = %i',
                    'item',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                if ($counter == 0) {
                    DB::insert(
                        prefixTable('statistics'),
                        array(
                            'scope' => 'item',
                            'view' => '1',
                            'item_id' => filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT),
                        )
                    );
                } else {
                    DB::update(
                        prefixTable('statistics'),
                        array(
                            'scope' => 'item',
                            'view' => $data['view'] + 1,
                        ),
                        'item_id = %i',
                        filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                    );
                }
            }

            break;
        /*
         * Refresh list of last items seen
         */
        case 'refresh_list_items_seen':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get list of last items seen
            $x_counter = 1;
            $arrTmp = array();
            $arr_html = array();
            $rows = DB::query(
                'SELECT i.id AS id, i.label AS label, i.id_tree AS id_tree, l.date, i.perso AS perso, i.restricted_to AS restricted
                FROM '.prefixTable('log_items').' AS l
                RIGHT JOIN '.prefixTable('items').' AS i ON (l.id_item = i.id)
                WHERE l.action = %s AND l.id_user = %i
                ORDER BY l.date DESC
                LIMIT 0, 100',
                'at_shown',
                $_SESSION['user_id']
            );
            if (DB::count() > 0) {
                foreach ($rows as $record) {
                    if (!in_array($record['id'], $arrTmp)) {
                        array_push(
                            $arr_html,
                            array(
                                'id' => $record['id'],
                                'label' => htmlspecialchars(stripslashes(htmlspecialchars_decode($record['label'], ENT_QUOTES)), ENT_QUOTES),
                                'tree_id' => $record['id_tree'],
                                'perso' => $record['perso'],
                                'restricted' => $record['restricted'],
                            )
                        );
                        ++$x_counter;
                        array_push($arrTmp, $record['id']);
                        if ($x_counter >= 10) {
                            break;
                        }
                    }
                }
            }

            // get wainting suggestions
            $nb_suggestions_waiting = 0;
            if (isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] == 1
                && ($_SESSION['user_admin'] == 1 || $_SESSION['user_manager'] == 1)
            ) {
                DB::query('SELECT * FROM '.prefixTable('suggestion'));
                $nb_suggestions_waiting = DB::count();
            }

            echo json_encode(
                array(
                    'error' => '',
                    'existing_suggestions' => $nb_suggestions_waiting,
                    'html_json' => $arr_html,
                ),
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
            break;

        /*
         * Generates a KEY with CRYPT
         */
        case 'generate_new_key':
            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();
            // generate key
            $key = $pwdlib->getRandomToken(filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT));
            echo '[{"key" : "'.htmlentities($key, ENT_QUOTES).'"}]';
            break;

        /*
         * Generates a TOKEN with CRYPT
         */
        case 'save_token':
            $token = GenerateCryptKey(
                null !== filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) ? filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) : 20,
                null !== filter_input(INPUT_POST, 'secure', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'secure', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'numeric', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'numeric', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false,
                null !== filter_input(INPUT_POST, 'symbols', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? filter_input(INPUT_POST, 'symbols', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false
            );

            // store in DB
            DB::insert(
                prefixTable('tokens'),
                array(
                    'user_id' => $_SESSION['user_id'],
                    'token' => $token,
                    'reason' => filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING),
                    'creation_timestamp' => time(),
                    'end_timestamp' => time() + filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT), // in secs
                )
            );

            echo '[{"token" : "'.$token.'"}]';
            break;

        /*
         * Create list of timezones
         */
        case 'generate_timezones_list':
            $array = array();
            foreach (timezone_identifiers_list() as $zone) {
                $array[$zone] = $zone;
            }

            echo json_encode($array);
            break;

        /*
         * Check if suggestions are existing
         */
        case 'is_existings_suggestions':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            if ($_SESSION['user_manager'] === '1' || $_SESSION['is_admin'] === '1') {
                $count = 0;
                DB::query('SELECT * FROM '.$pre.'items_change');
                $count += DB::count();
                DB::query('SELECT * FROM '.$pre.'suggestion');
                $count += DB::count();

                echo '[ { "error" : "" , "count" : "'.$count.'" , "show_sug_in_menu" : "0"} ]';
            } elseif (isset($_SESSION['nb_item_change_proposals']) && $_SESSION['nb_item_change_proposals'] > 0) {
                echo '[ { "error" : "" , "count" : "'.$_SESSION['nb_item_change_proposals'].'" , "show_sug_in_menu" : "1"} ]';
            } else {
                echo '[ { "error" : "" , "count" : "" , "show_sug_in_menu" : "0"} ]';
            }

            break;

        /*
         * Check if suggestions are existing
         */
        case 'sending_statistics':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            if (isset($SETTINGS['send_statistics_items']) && isset($SETTINGS['send_stats']) && isset($SETTINGS['send_stats_time'])
                && $SETTINGS['send_stats'] === '1'
                && ($SETTINGS['send_stats_time'] + $SETTINGS_EXT['one_day_seconds']) > time()
            ) {
                // get statistics data
                $stats_data = getStatisticsData();

                // get statistics items to share
                $statsToSend = [];
                $statsToSend['ip'] = $_SERVER['SERVER_ADDR'];
                $statsToSend['timestamp'] = time();
                foreach (array_filter(explode(';', $SETTINGS['send_statistics_items'])) as $data) {
                    if ($data === 'stat_languages') {
                        $tmp = '';
                        foreach ($stats_data[$data] as $key => $value) {
                            if (empty($tmp)) {
                                $tmp = $key.'-'.$value;
                            } else {
                                $tmp .= ','.$key.'-'.$value;
                            }
                        }
                        $statsToSend[$data] = $tmp;
                    } elseif ($data === 'stat_country') {
                        $tmp = '';
                        foreach ($stats_data[$data] as $key => $value) {
                            if (empty($tmp)) {
                                $tmp = $key.'-'.$value;
                            } else {
                                $tmp .= ','.$key.'-'.$value;
                            }
                        }
                        $statsToSend[$data] = $tmp;
                    } else {
                        $statsToSend[$data] = $stats_data[$data];
                    }
                }

                // connect to Teampass Statistics database
                $link2 = new MeekroDB(
                    'sql11.freemysqlhosting.net',
                    'sql11197223',
                    '3QzpXYQ9dZ',
                    'sql11197223',
                    '3306',
                    'utf8'
                );

                $link2->insert(
                    'statistics',
                    $statsToSend
                );

                // update table misc with current timestamp
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => time(),
                        ),
                    'type = %s AND intitule = %s',
                    'admin',
                    'send_stats_time'
                );

                //permits to test only once by session
                $_SESSION['temporary']['send_stats_done'] = true;
                $SETTINGS['send_stats_time'] = time();

                // save change in config file
                handleConfigFile('update', 'send_stats_time', $SETTINGS['send_stats_time']);

                echo '[ { "error" : "" , "done" : "1"} ]';
            } else {
                echo '[ { "error" : "" , "done" : "0"} ]';
            }

            break;

        /*
         * delete a file
         */
        case 'file_deletion':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            fileDelete(filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_STRING), $SETTINGS);

            break;

        /*
         * Generate BUG report
         */
        case 'generate_bug_report':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Read config file
            $list_of_options = '';
            $url_found = '';
            $anonym_url = '';
            $tp_config_file = '../includes/config/tp.config.php';
            $data = file($tp_config_file);
            foreach ($data as $line) {
                if (substr($line, 0, 4) === '    ') {
                    // Remove extra spaces
                    $line = str_replace('    ', '', $line);

                    // Identify url to anonymize it
                    if (strpos($line, 'cpassman_url') > 0 && empty($url_found) === true) {
                        $url_found = substr($line, 19, strlen($line) - 22);
                        $tmp = parse_url($url_found);
                        $anonym_url = $tmp['scheme'].'://<anonym_url>'.$tmp['path'];
                        $line = "'cpassman_url' => '".$anonym_url."\n";
                    }

                    // Anonymize all urls
                    if (empty($anonym_url) === false) {
                        $line = str_replace($url_found, $anonym_url, $line);
                    }

                    // Clear bck_script_passkey
                    if (strpos($line, 'bck_script_passkey') > 0) {
                        $line = "'bck_script_passkey' => '<removed>'\n";
                    }

                    // Complete line to display
                    $list_of_options .= $line;
                }
            }

            // Get error
            $err = error_get_last();

            // Get 10 latest errors in Teampass
            $teampass_errors = '';
            $rows = DB::query(
                'SELECT label, date AS error_date
                FROM '.prefixTable('log_system')."
                WHERE `type` LIKE 'error'
                ORDER BY `date` DESC
                LIMIT 0, 10"
            );
            if (DB::count() > 0) {
                foreach ($rows as $record) {
                    if (empty($teampass_errors) === true) {
                        $teampass_errors = ' * '.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['error_date']).' - '.$record['label'];
                    } else {
                        $teampass_errors .= '
 * '.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['error_date']).' - '.$record['label'];
                    }
                }
            }

            // Now prepare text
            $txt = '### Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen


### Actual behaviour
Tell us what happens instead

### Server configuration
**Operating system**: '.php_uname().'

**Web server:** '.$_SERVER['SERVER_SOFTWARE'].'

**Database:** '.mysqli_get_server_info($link).'

**PHP version:** '.PHP_VERSION.'

**Teampass version:** '.TP_VERSION_FULL.'

**Teampass configuration file:**
```
'.$list_of_options.'
```

**Updated from an older Teampass or fresh install:**

### Client configuration

**Browser:** '.filter_input(INPUT_POST, 'browser_name', FILTER_SANITIZE_STRING).' - '.filter_input(INPUT_POST, 'browser_version', FILTER_SANITIZE_STRING).'

**Operating system:** '.filter_input(INPUT_POST, 'os', FILTER_SANITIZE_STRING).' - '.filter_input(INPUT_POST, 'os_archi', FILTER_SANITIZE_STRING).'bits

### Logs

#### Web server error log
```
'.$err['message'].' - '.$err['file'].' ('.$err['line'].')
```

#### Teampass 10 last system errors
```
'.$teampass_errors.'
```

#### Log from the web-browser developer console (CTRL + SHIFT + i)
```
Insert the log here and especially the answer of the query that failed.
```
';

            echo prepareExchangedData(
                array(
                    'html' => $txt,
                    'error' => '',
                ),
                'encode'
            );

            break;

            case 'update_user_field':
                // Check KEY
                if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                    echo '[ { "error" : "key_not_conform" } ]';
                    break;
                }

                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData(
                    filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                    'decode'
                );

                // Prepare variables
                $field = noHTML(htmlspecialchars_decode($dataReceived['field']));
                $new_value = noHTML(htmlspecialchars_decode($dataReceived['new_value']));
                $user_id = (htmlspecialchars_decode($dataReceived['user_id']));

                DB::update(
                    prefixTable('users'),
                    array(
                        $field => $new_value,
                        ),
                    'id = %i',
                    $user_id
                );

                // Update session
                if ($field === 'user_api_key') {
                    $_SESSION['user_settings']['api-key'] = $new_value;
                }
            break;

        /*
            * get_teampass_settings
            */
        case 'get_teampass_settings':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // Encrypt data to return
            echo prepareExchangedData($SETTINGS, 'encode');

            break;

        /*
         * User's password has to be initialized
         */
        case 'initialize_user_password':
            // Allowed?
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );

            // Variables
            $post_user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_special = filter_var($dataReceived['special'], FILTER_SANITIZE_STRING);

            if (is_null($post_user_id) === false && isset($post_user_id) === true && empty($post_user_id) === false) {
                // Get user info
                $userData = DB::queryFirstRow(
                    'SELECT email
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $post_user_id
                );
                if (empty($userData['email']) === false) {
                    // Generate new password
                    $newPassword = generateQuickPassword();

                    // load passwordLib library
                    $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                    $pwdlib->register();
                    $pwdlib = new PasswordLib\PasswordLib();

                    // Send email to user
                    $emailStatus = json_decode(
                        sendEmail(
                            langHdl('email_new_user_password'),
                            str_replace(
                                array('#tp_password#'),
                                array($newPassword),
                                langHdl('email_new_user_password_body')
                            ),
                            $userData['email'],
                            $SETTINGS,
                            ''
                        ),
                        true
                    );

                    if ($emailStatus['error'] === false) {
                        // Only change if email is successfull
                        // Update user account
                        DB::update(
                            prefixTable('users'),
                            array(
                                'special' => $post_special,
                                'pw' => $pwdlib->createPasswordHash($newPassword),
                            ),
                            'id = %i',
                            $post_user_id
                        );

                        // Return
                        echo prepareExchangedData(
                            array(
                                'error' => false,
                                'message' => '',
                                'debug' => $newPassword,
                            ),
                            'encode'
                        );
                    } else {
                        // Return error
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => $emailStatus['message'],
                                'debug' => $newPassword,
                            ),
                            'encode'
                        );
                    }
                    break;
                } else {
                    // Error
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('no_email_set'),
                        ),
                        'encode'
                    );
                    break;
                }
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_no_user'),
                    ),
                    'encode'
                );
                break;
            }

            break;


        /*
        * user_sharekeys_reencryption_start
        */
        case 'user_sharekeys_reencryption_start':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            $post_user_id = filter_var($_POST['userId'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_id = is_null($post_user_id) === true ? $_SESSION['user_id'] : $post_user_id;

            // Check if user has to reencrpt
            $userData = DB::queryFirstRow(
                'SELECT special
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_user_id
            );
            if (empty($userData['special']) === false && $userData['special'] === 'reset_private_key') {
                // Include 
                include_once $SETTINGS['cpassman_dir'].'/sources/aes.functions.php';

                // CLear old sharekeys
                deleteUserObjetsKeys($post_user_id, $SETTINGS);

                // Continu with next step
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                        'action' => 'step_1',
                    ),
                    'encode'
                );
                break;
            } else {
                // Nothing to do
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_no_user'),
                    ),
                    'encode'
                );
                break;
            }

            break;


            /*
            * user_sharekeys_reencryption_next
            */
            case 'user_sharekeys_reencryption_next':
                if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('key_is_not_correct'),
                        ),
                        'encode'
                    );
                    break;
                }
    
                $post_user_id = filter_var($_POST['userId'], FILTER_SANITIZE_NUMBER_INT);
                $post_user_id = is_null($post_user_id) === true ? $_SESSION['user_id'] : $post_user_id;
                $post_start = filter_var($_POST['start'], FILTER_SANITIZE_NUMBER_INT);
                $post_length = filter_var($_POST['length'], FILTER_SANITIZE_NUMBER_INT);
                $post_action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
    
                // Check if user has to reencrpt
                $userData = DB::queryFirstRow(
                    'SELECT special
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $post_user_id
                );
                if (empty($userData['special']) === false && $userData['special'] === 'reset_private_key') {
                    // Include 
                    include_once $SETTINGS['cpassman_dir'].'/sources/aes.functions.php';
    
                    // STEP 1 - ITEMS
                    if ($post_action === 'step1') {
                        // Loop on items
                        $rows = DB::query(
                            'SELECT id, pw, encryption_type
                            FROM '.prefixTable('items').'
                            WHERE perso = 0
                            LIMIT '.$post_start.', '.$post_length
                        );
                        foreach ($rows as $record) {

                        }

                        // SHould we change step?
                        $next_start = (int) $post_start + (int) $post_length;
                        if ($next_start > DB::count()) {
                            $post_action = 'step2';
                        }
                    }
    
                    // Continu with next step
                    echo prepareExchangedData(
                        array(
                            'error' => false,
                            'message' => '',
                            'action' => $post_action,
                            'start' => $next_start,
                        ),
                        'encode'
                    );
                    break;
                } else {
                    // Nothing to do
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_no_user'),
                        ),
                        'encode'
                    );
                    break;
                }
    
                break;
    }
}
