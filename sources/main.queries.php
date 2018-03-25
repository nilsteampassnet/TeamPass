<?php
/**
 * Teampass file 
 * @file          main.queries.php
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

$debugLdap = 0; //Can be used in order to debug LDAP authentication

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    $_SESSION['error']['code'] = "1004"; //Hacking attempt
    include '../error.php';
    exit();
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
if (isset($post_type) && ($post_type === "ga_generate_qr"
    || $post_type === "send_pw_by_email" || $post_type === "generate_new_password")
) {
    // continue
    mainQuery();
} elseif (isset($_SESSION['user_id']) && !checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
} elseif ((isset($_SESSION['user_id']) && isset($_SESSION['key'])) ||
    (isset($post_type) && $post_type === "change_user_language"
    && null !== filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES))
) {
    // continue
    mainQuery();
} else {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

/*
** Executes expected queries
*/
function mainQuery()
{
    global $server, $user, $pass, $database, $port, $encoding, $pre, $LANG;
    global $SETTINGS, $SETTINGS_EXT;

    include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    header("Content-type: text/html; charset=utf-8");
    header("Cache-Control: no-cache, must-revalidate");
    error_reporting(E_ERROR);
    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    // connect to the server
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // User's language loading
    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    // Manage type of action asked
    switch (filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) {
        case "change_pw":
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                "decode"
            );

            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();

            // Prepare variables
            $newPw = $pwdlib->createPasswordHash(htmlspecialchars_decode($dataReceived['new_pw']));

            // User has decided to change is PW
            if (null !== filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === "user_change"
                && $_SESSION['user_admin'] !== "1"
            ) {
                // check if expected security level is reached
                $data_roles = DB::queryfirstrow("SELECT fonction_id FROM ".prefix_table("users")." WHERE id = %i", $_SESSION['user_id']);

                // check if badly written
                $data_roles['fonction_id'] = array_filter(explode(',', str_replace(';', ',', $data_roles['fonction_id'])));
                if ($data_roles['fonction_id'][0] === "") {
                    $data_roles['fonction_id'] = implode(';', $data_roles['fonction_id']);
                    DB::update(
                        prefix_table("users"),
                        array(
                            'fonction_id' => $data_roles['fonction_id']
                            ),
                        "id = %i",
                        $_SESSION['user_id']
                    );
                }

                $data = DB::query(
                    "SELECT complexity
                    FROM ".prefix_table("roles_title")."
                    WHERE id IN (".implode(',', $data_roles['fonction_id']).")
                    ORDER BY complexity DESC"
                );
                if (intval(filter_input(INPUT_POST, 'complexity', FILTER_SANITIZE_NUMBER_INT)) < intval($data[0]['complexity'])) {
                    echo '[ { "error" : "complexity_level_not_reached" } ]';
                    break;
                }

                // Get a string with the old pw array
                $lastPw = explode(';', $_SESSION['last_pw']);
                // if size is bigger then clean the array
                if (sizeof($lastPw) > $SETTINGS['number_of_used_pw']
                        && $SETTINGS['number_of_used_pw'] > 0
                ) {
                    for ($x_counter = 0; $x_counter < $SETTINGS['number_of_used_pw']; $x_counter++) {
                        unset($lastPw[$x_counter]);
                    }
                    // reinit SESSION
                    $_SESSION['last_pw'] = implode(';', $lastPw);
                    // specific case where admin setting "number_of_used_pw"
                } elseif ($SETTINGS['number_of_used_pw'] == 0) {
                    $_SESSION['last_pw'] = "";
                    $lastPw = array();
                }

                // check if new pw is different that old ones
                if (in_array($newPw, $lastPw)) {
                    echo '[ { "error" : "already_used" } ]';
                    break;
                }

                // update old pw with new pw
                if (sizeof($lastPw) == ($SETTINGS['number_of_used_pw'] + 1)) {
                    unset($lastPw[0]);
                } else {
                    array_push($lastPw, $newPw);
                }
                // create a list of last pw based on the table
                $oldPw = "";
                foreach ($lastPw as $elem) {
                    if (!empty($elem)) {
                        if (empty($oldPw)) {
                            $oldPw = $elem;
                        } else {
                            $oldPw .= ";".$elem;
                        }
                    }
                }

                // update sessions
                $_SESSION['last_pw'] = $oldPw;
                $_SESSION['last_pw_change'] = mktime(0, 0, 0, date('m'), date('d'), date('y'));
                $_SESSION['validite_pw'] = true;

                // BEfore updating, check that the pwd is correct
                if ($pwdlib->verifyPasswordHash(htmlspecialchars_decode($dataReceived['new_pw']), $newPw) === true) {
                    // update DB
                    DB::update(
                        prefix_table("users"),
                        array(
                            'pw' => $newPw,
                            'last_pw_change' => mktime(0, 0, 0, date('m'), date('d'), date('y')),
                            'last_pw' => $oldPw
                            ),
                        "id = %i",
                        $_SESSION['user_id']
                    );
                    // update LOG
                    logEvents('user_mngt', 'at_user_pwd_changed', $_SESSION['user_id'], $_SESSION['login'], $_SESSION['user_id']);
                    echo '[ { "error" : "none" } ]';
                } else {
                     echo '[ { "error" : "pwd_hash_not_correct" } ]';
                }
                break;

            // ADMIN has decided to change the USER's PW
            } elseif (null !== filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING)
                && ((filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === "admin_change"
                    || filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === "user_change"
                    ) && ($_SESSION['user_admin'] === "1"|| $_SESSION['user_manager'] === "1"
                    || $_SESSION['user_can_manage_all_users'] === "1")
                )
            ) {
                // check if user is admin / Manager
                $userInfo = DB::queryFirstRow(
                    "SELECT admin, gestionnaire
                    FROM ".prefix_table("users")."
                    WHERE id = %i",
                    $_SESSION['user_id']
                );
                if ($userInfo['admin'] != 1 && $userInfo['gestionnaire'] != 1) {
                    echo '[ { "error" : "not_admin_or_manager" } ]';
                    break;
                }

                // BEfore updating, check that the pwd is correct
                if ($pwdlib->verifyPasswordHash(htmlspecialchars_decode($dataReceived['new_pw']), $newPw) === true) {
                    // adapt
                    if (filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) === "user_change") {
                        $dataReceived['user_id'] = $_SESSION['user_id'];
                    }

                    // update DB
                    DB::update(
                        prefix_table("users"),
                        array(
                            'pw' => $newPw,
                            'last_pw_change' => mktime(0, 0, 0, date('m'), date('d'), date('y'))
                            ),
                        "id = %i",
                        $dataReceived['user_id']
                    );

                    // update LOG
                    logEvents('user_mngt', 'at_user_pwd_changed', $_SESSION['user_id'], $_SESSION['login'], $dataReceived['user_id']);

                    //Send email to user
                    if (filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) !== "admin_change") {
                        $row = DB::queryFirstRow(
                            "SELECT email FROM ".prefix_table("users")."
                            WHERE id = %i",
                            $dataReceived['user_id']
                        );
                        if (!empty($row['email']) && isset($SETTINGS['enable_email_notification_on_user_pw_change']) && $SETTINGS['enable_email_notification_on_user_pw_change'] == 1) {
                            sendEmail(
                                $LANG['forgot_pw_email_subject'],
                                $LANG['forgot_pw_email_body']." ".htmlspecialchars_decode($dataReceived['new_pw']),
                                $row[0],
                                $LANG,
                                $SETTINGS,
                                $LANG['forgot_pw_email_altbody_1']." ".htmlspecialchars_decode($dataReceived['new_pw'])
                            );
                        }
                    }

                    echo '[ { "error" : "none" } ]';
                } else {
                    echo '[ { "error" : "pwd_hash_not_correct" } ]';
                }
                break;

                // ADMIN first login
            } elseif (null !== filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'change_pw_origine', FILTER_SANITIZE_STRING) == "first_change"
            ) {
                // update DB
                DB::update(
                    prefix_table("users"),
                    array(
                        'pw' => $newPw,
                        'last_pw_change' => mktime(0, 0, 0, date('m'), date('d'), date('y'))
                        ),
                    "id = %i",
                    $_SESSION['user_id']
                );

                // update sessions
                $_SESSION['last_pw'] = "";
                $_SESSION['last_pw_change'] = mktime(0, 0, 0, date('m'), date('d'), date('y'));
                $_SESSION['validite_pw'] = true;

                // update LOG
                logEvents('user_mngt', 'at_user_initial_pwd_changed', $_SESSION['user_id'], $_SESSION['login'], $_SESSION['user_id']);

                echo '[ { "error" : "none" } ]';
                break;
            } else {
                // DEFAULT case
                echo '[ { "error" : "nothing_to_do" } ]';
            }
            break;
        /**
         * This will generate the QR Google Authenticator
         */
        case "ga_generate_qr":
            // is this allowed by setting
            if ((isset($SETTINGS['ga_reset_by_user']) === false || $SETTINGS['ga_reset_by_user'] !== "1")
                && (null === filter_input(INPUT_POST, 'demand_origin', FILTER_SANITIZE_STRING)
                    || filter_input(INPUT_POST, 'demand_origin', FILTER_SANITIZE_STRING) !== "users_management_list")
            ) {
                // User cannot ask for a new code
                echo '[{"error" : "not_allowed"}]';
                break;
            }

            // Check if user exists
            if (null === filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                || empty(filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)) === true
            ) {
                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData(
                    filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                    "decode"
                );
                // Prepare variables
                $login = htmlspecialchars_decode($dataReceived['login']);

                // Get data about user
                $data = DB::queryfirstrow(
                    "SELECT id, email
                    FROM ".prefix_table("users")."
                    WHERE login = %s",
                    $login
                );
            } else {
                $data = DB::queryfirstrow(
                    "SELECT id, login, email
                    FROM ".prefix_table("users")."
                    WHERE id = %i",
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
            }
            $counter = DB::count();
            if ($counter == 0) {
                // not a registered user !
                echo '[{"error" : "no_user"}]';
            } else {
                if (empty($data['email'])) {
                    echo '[{"error" : "no_email"}]';
                } else {
                    // generate new GA user code
                    include_once($SETTINGS['cpassman_dir']."/includes/libraries/Authentication/TwoFactorAuth/TwoFactorAuth.php");
                    $tfa = new Authentication\TwoFactorAuth\TwoFactorAuth($SETTINGS['ga_website_name']);
                    $gaSecretKey = $tfa->createSecret();
                    $gaTemporaryCode = GenerateCryptKey(12);

                    // save the code
                    DB::update(
                        prefix_table("users"),
                        array(
                            'ga' => $gaSecretKey,
                            'ga_temporary_code' => $gaTemporaryCode
                            ),
                        "id = %i",
                        $data['id']
                    );

                    // send mail?
                    if (null !== filter_input(INPUT_POST, 'send_email', FILTER_SANITIZE_STRING)
                        && filter_input(INPUT_POST, 'send_email', FILTER_SANITIZE_STRING) === "1"
                    ) {
                        sendEmail(
                            $LANG['email_ga_subject'],
                            str_replace(
                                "#2FACode#",
                                $gaTemporaryCode,
                                $LANG['email_ga_text']
                            ),
                            $data['email'],
                            $LANG,
                            $SETTINGS
                        );
                    }

                    // send back
                    echo '[{ "error" : "0" , "email" : "'.$data['email'].'" , "msg" : "'.str_replace("#email#", "<b>".$data['email']."</b>", addslashes($LANG['admin_email_result_ok'])).'"}]';
                }
            }
            break;
        /**
         * Increase the session time of User
         */
        case "increase_session_time":
            // check if session is not already expired.
            if ($_SESSION['fin_session'] > time()) {
                // Calculate end of session
                $_SESSION['fin_session'] = (integer) ($_SESSION['fin_session'] + filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT));
                // Update table
                DB::update(
                    prefix_table("users"),
                    array(
                        'session_end' => $_SESSION['fin_session']
                    ),
                    "id = %i",
                    $_SESSION['user_id']
                );
                // Return data
                echo '[{"new_value":"'.$_SESSION['fin_session'].'"}]';
            } else {
                echo '[{"new_value":"expired"}]';
            }
            break;
        /**
         * Hide maintenance message
         */
        case "hide_maintenance":
            $_SESSION['hide_maintenance'] = 1;
            break;
        /**
         * Used in order to send the password to the user by email
         */
        case "send_pw_by_email":
            // generate key
            $key = GenerateCryptKey(50);

            // Prepare post variables
            $post_email = mysqli_escape_string($link, stripslashes(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_STRING)));
            $post_login = mysqli_escape_string($link, filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING));

            // Get account and pw associated to email
            DB::query(
                "SELECT * FROM ".prefix_table("users")." WHERE email = %s",
                $post_email
            );
            $counter = DB::count();
            if ($counter != 0) {
                $data = DB::query(
                    "SELECT login,pw FROM ".prefix_table("users")." WHERE email = %s",
                    $post_email
                );
                $textMail = $LANG['forgot_pw_email_body_1']." <a href=\"".
                    $SETTINGS['cpassman_url']."/index.php?action=password_recovery&key=".$key.
                    "&login=".$post_login."\">".$SETTINGS['cpassman_url'].
                    "/index.php?action=password_recovery&key=".$key."&login=".$post_login."</a>.<br><br>".$LANG['thku'];
                $textMailAlt = $LANG['forgot_pw_email_altbody_1']." ".$LANG['at_login']." : ".$post_login." - ".
                    $LANG['index_password']." : ".md5($data['pw']);

                // Check if email has already a key in DB
                DB::query(
                    "SELECT * FROM ".prefix_table("misc")." WHERE intitule = %s AND type = %s",
                    $post_login,
                    "password_recovery"
                );
                $counter = DB::count();
                if ($counter != 0) {
                    DB::update(
                        prefix_table("misc"),
                        array(
                            'valeur' => $key
                        ),
                        "type = %s and intitule = %s",
                        "password_recovery",
                        $post_login
                    );
                } else {
                    // store in DB the password recovery informations
                    DB::insert(
                        prefix_table("misc"),
                        array(
                            'type' => 'password_recovery',
                            'intitule' => $post_login,
                            'valeur' => $key
                        )
                    );
                }

                echo '[{'.sendEmail(
                    $LANG['forgot_pw_email_subject'],
                    $textMail,
                    $post_email,
                    $LANG,
                    $SETTINGS,
                    $textMailAlt
                ).'}]';
            } else {
                // no one has this email ... alert
                echo '[{"error":"error_email" , "message":"'.$LANG['forgot_my_pw_error_email_not_exist'].'"}]';
            }
            break;

        // Send to user his new pw if key is conform
        case "generate_new_password":
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                "decode"
            );

            // Prepare variables
            $login = htmlspecialchars_decode($dataReceived['login']);
            $key = htmlspecialchars_decode($dataReceived['key']);

            // check if key is okay
            $data = DB::queryFirstRow(
                "SELECT valeur FROM ".prefix_table("misc")." WHERE intitule = %s AND type = %s",
                mysqli_escape_string($link, $login),
                "password_recovery"
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
                    prefix_table("users"),
                    array(
                        'pw' => $newPw
                        ),
                    "login = %s",
                    mysqli_escape_string($link, $login)
                );
                // Delete recovery in DB
                DB::delete(
                    prefix_table("misc"),
                    "type = %s AND intitule = %s AND valeur = %s",
                    "password_recovery",
                    mysqli_escape_string($link, $login),
                    $key
                );
                // Get email
                $dataUser = DB::queryFirstRow(
                    "SELECT email FROM ".prefix_table("users")." WHERE login = %s",
                    mysqli_escape_string($link, $login)
                );

                $_SESSION['validite_pw'] = false;
                // send to user
                $ret = json_decode(
                    sendEmail(
                        $LANG['forgot_pw_email_subject_confirm'],
                        $LANG['forgot_pw_email_body']." ".$newPwNotCrypted,
                        $dataUser['email'],
                        $LANG,
                        $SETTINGS,
                        strip_tags($LANG['forgot_pw_email_body'])." ".$newPwNotCrypted
                    )
                );
                // send email
                if (empty($ret['error'])) {
                    echo 'done';
                } else {
                    echo $ret['message'];
                }
            }
            break;
        /**
         * Store the personal saltkey
         */
        case "store_personal_saltkey":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" , "status" : "nok" } ]';
                break;
            }

            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                "decode"
            );
            $filter_score = filter_var($dataReceived['score'], FILTER_SANITIZE_NUMBER_INT);
            $filter_psk = filter_var($dataReceived['psk'], FILTER_SANITIZE_STRING);

            // manage store
            if ($filter_psk !== "") {
                // store in session the cleartext for psk
                $_SESSION['user_settings']['clear_psk'] = $filter_psk;

                // check if encrypted_psk is in database. If not, add it
                if (!isset($_SESSION['user_settings']['encrypted_psk']) || (isset($_SESSION['user_settings']['encrypted_psk']) && empty($_SESSION['user_settings']['encrypted_psk']))) {
                    // Check if security level is reach (if enabled)
                    if (isset($SETTINGS['personal_saltkey_security_level']) === true) {
                        // Did we received the pass score
                        if (empty($filter_score) === false) {
                            if (intval($SETTINGS['personal_saltkey_security_level']) > $filter_score) {
                                echo '[ { "error" : "security_level_not_reached" , "status" : "" } ]';
                                break;
                            }
                        }
                    }
                    // generate it based upon clear psk
                    $_SESSION['user_settings']['encrypted_psk'] = defuse_generate_personal_key($filter_psk);

                    // store it in DB
                    DB::update(
                        prefix_table("users"),
                        array(
                            'encrypted_psk' => $_SESSION['user_settings']['encrypted_psk']
                            ),
                        "id = %i",
                        $_SESSION['user_id']
                    );
                }

                // check if psk is correct.
                $user_key_encoded = defuse_validate_personal_key(
                    $filter_psk,
                    $_SESSION['user_settings']['encrypted_psk']
                );

                if (strpos($user_key_encoded, "Error ") !== false) {
                    echo '[ { "error" : "psk_not_correct" , "status" : "" } ]';
                    break;
                } else {
                    // Check if security level is reach (if enabled)
                    if (isset($SETTINGS['personal_saltkey_security_level']) === true) {
                        // Did we received the pass score
                        if (empty($filter_score) === false) {
                            if (intval($SETTINGS['personal_saltkey_security_level']) > $filter_score) {
                                echo '[ { "error" : "" , "status" : "security_level_not_reached_but_psk_correct" } ]';
                                break;
                            }
                        }
                    }
                    // Store PSK
                    $_SESSION['user_settings']['session_psk'] = $user_key_encoded;
                    setcookie(
                        "TeamPass_PFSK_".md5($_SESSION['user_id']),
                        $user_key_encoded,
                        (!isset($SETTINGS['personal_saltkey_cookie_duration']) || $SETTINGS['personal_saltkey_cookie_duration'] == 0) ? time() + 60 * 60 * 24 : time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                        '/'
                    );
                }
            } else {
                echo '[ { "error" : "psk_is_empty" , "status" : "" } ]';
                break;
            }

            echo '[ { "error" : "" , "status" : "ok" } ]';

            break;
        /**
         * Change the personal saltkey
         */
        case "change_personal_saltkey":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            }

            //init
            $list = "";
            $number = 0;

            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data_to_share', FILTER_SANITIZE_STRING),
                "decode"
            );

            //Prepare variables
            $newPersonalSaltkey = htmlspecialchars_decode($dataReceived['sk']);
            $oldPersonalSaltkey = htmlspecialchars_decode($dataReceived['old_sk']);

            // check old psk
            $user_key_encoded = defuse_validate_personal_key(
                $oldPersonalSaltkey,
                $_SESSION['user_settings']['encrypted_psk']
            );
            if (strpos($user_key_encoded, "Error ") !== false) {
                echo prepareExchangedData(
                    array(
                        "list" => $list,
                        "error" => $user_key_encoded,
                        "nb_total" => $number
                    ),
                    "encode"
                );
                break;
            } else {
                // Store PSK
                $_SESSION['user_settings']['encrypted_oldpsk'] = $user_key_encoded;
            }

            // generate the new encrypted psk based upon clear psk
            $_SESSION['user_settings']['encrypted_psk'] = defuse_generate_personal_key($newPersonalSaltkey);

            // store it in DB
            DB::update(
                prefix_table("users"),
                array(
                    'encrypted_psk' => $_SESSION['user_settings']['encrypted_psk']
                    ),
                "id = %i",
                $_SESSION['user_id']
            );

            $user_key_encoded = defuse_validate_personal_key(
                $newPersonalSaltkey,
                $_SESSION['user_settings']['encrypted_psk']
            );
            $_SESSION['user_settings']['session_psk'] = $user_key_encoded;

            // Change encryption
            // Build list of items to be re-encrypted
            $rows = DB::query(
                "SELECT i.id as id, i.pw as pw
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("log_items")." as l ON (i.id=l.id_item)
                WHERE i.perso = %i AND l.id_user= %i AND l.action = %s",
                "1",
                $_SESSION['user_id'],
                "at_creation"
            );
            $number = DB::count();
            foreach ($rows as $record) {
                if (!empty($record['pw'])) {
                    if (empty($list)) {
                        $list = $record['id'];
                    } else {
                        $list .= ",".$record['id'];
                    }
                }
            }

            // change salt
            setcookie(
                "TeamPass_PFSK_".md5($_SESSION['user_id']),
                $user_key_encoded,
                time() + 60 * 60 * 24 * $SETTINGS['personal_saltkey_cookie_duration'],
                '/'
            );

            echo prepareExchangedData(
                array(
                    "list" => $list,
                    "error" => "no",
                    "nb_total" => $number
                ),
                "encode"
            );
            break;
        /**
         * Reset the personal saltkey
         */
        case "reset_personal_saltkey":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            }

            if (!empty($_SESSION['user_id'])) {
                // delete all previous items of this user
                $rows = DB::query(
                    "SELECT i.id as id
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("log_items")." as l ON (i.id=l.id_item)
                    WHERE i.perso = %i AND l.id_user= %i AND l.action = %s",
                    "1",
                    $_SESSION['user_id'],
                    "at_creation"
                );
                foreach ($rows as $record) {
                    // delete in ITEMS table
                    DB::delete(prefix_table("items"), "id = %i", $record['id']);
                    // delete in LOGS table
                    DB::delete(prefix_table("log_items"), "id_item = %i", $record['id']);
                    // delete from CACHE table
                    updateCacheTable("delete_value", $record['id']);
                }

                // remove from DB
                DB::update(
                    prefix_table("users"),
                    array(
                        'encrypted_psk' => ""
                        ),
                    "id = %i",
                    $_SESSION['user_id']
                );

                $_SESSION['user_settings']['session_psk'] = "";
            }
            break;
        /**
         * Change the user's language
         */
        case "change_user_language":
            if (!empty($_SESSION['user_id'])) {
                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData(
                    filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                    "decode"
                );
                // Prepare variables
                $language = $dataReceived['lang'];
                // update DB
                DB::update(
                    prefix_table("users"),
                    array(
                        'user_language' => $language
                        ),
                    "id = %i",
                    $_SESSION['user_id']
                );
                $_SESSION['user_language'] = $language;
                echo "done";
            } else {
                $_SESSION['user_language'] = "";
                echo "done";
            }
            break;
        /**
         * Send emails not sent
         */
        case "send_waiting_emails":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            if (isset($SETTINGS['enable_send_email_on_user_login'])
                && $SETTINGS['enable_send_email_on_user_login'] === "1"
            ) {
                $row = DB::queryFirstRow(
                    "SELECT valeur FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %s",
                    "cron",
                    "sending_emails"
                );
                if ((time() - $row['valeur']) >= 300 || $row['valeur'] == 0) {
                    $rows = DB::query("SELECT * FROM ".prefix_table("emails")." WHERE status != %s", "sent");
                    foreach ($rows as $record) {
                        // Send email
                        $ret = sendEmail(
                            $record['subject'],
                            $record['body'],
                            $record['receivers'],
                            $LANG,
                            $SETTINGS
                        );

                        if (strpos($ret, "error_mail_not_send") !== false) {
                            $status = "not_sent";
                        } else {
                            $status = "sent";
                        }

                        // update item_id in files table
                        DB::update(
                            prefix_table("emails"),
                            array(
                                'status' => $status
                                ),
                            "timestamp = %s",
                            $record['timestamp']
                        );
                    }
                }
                // update cron time
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => time()
                        ),
                    "intitule = %s AND type = %s",
                    "sending_emails",
                    "cron"
                );
            }
            break;

        /**
         * Store error
         */
        case "store_error":
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

        /**
         * Generate a password generic
         */
        case "generate_a_password":
            if (filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) > $SETTINGS['pwd_maximum_length']) {
                echo prepareExchangedData(
                    array(
                        "error_msg" => "Password length is too long!",
                        "error" => "true"
                    ),
                    "encode"
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

            $generator->setLength((int) filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT));

            if (null !== filter_input(INPUT_POST, 'secure_pwd', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'secure_pwd', FILTER_SANITIZE_STRING) === "true"
            ) {
                $generator->setSymbols(true);
                $generator->setLowercase(true);
                $generator->setUppercase(true);
                $generator->setNumbers(true);
            } else {
                $generator->setLowercase((filter_input(INPUT_POST, 'lowercase', FILTER_SANITIZE_STRING) === "true") ? true : false);
                $generator->setUppercase((filter_input(INPUT_POST, 'capitalize', FILTER_SANITIZE_STRING) === "true") ? true : false);
                $generator->setNumbers((filter_input(INPUT_POST, 'numerals', FILTER_SANITIZE_STRING) === "true") ? true : false);
                $generator->setSymbols((filter_input(INPUT_POST, 'symbols', FILTER_SANITIZE_STRING) === "true") ? true : false);
            }

            echo prepareExchangedData(
                array(
                    "key" => $generator->generatePasswords(),
                    "error" => ""
                ),
                "encode"
            );
            break;
        /**
         * Check if user exists and send back if psk is set
         */
        case "check_login_exists":
            $data = DB::query(
                "SELECT login, psk FROM ".prefix_table("users")."
                WHERE login = %i",
                mysqli_escape_string($link, stripslashes(filter_input(INPUT_POST, 'userId', FILTER_SANITIZE_NUMBER_INT)))
            );
            if (empty($data['login'])) {
                $userOk = false;
            } else {
                $userOk = true;
            }
            if (isset($SETTINGS['psk_authentication']) && $SETTINGS['psk_authentication'] == 1
                && !empty($data['psk'])
            ) {
                $pskSet = true;
            } else {
                $pskSet = false;
            }

            echo '[{"login" : "'.$userOk.'", "psk":"'.$pskSet.'"}]';
            break;
        /**
         * Make statistics on item
         */
        case "item_stat":
            if (null !== filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING)
                && filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING) === "item"
            ) {
                $data = DB::queryfirstrow(
                    "SELECT view FROM ".prefix_table("statistics")." WHERE scope = %s AND item_id = %i",
                    'item',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                if ($counter == 0) {
                    DB::insert(
                        prefix_table("statistics"),
                        array(
                            'scope' => 'item',
                            'view' => '1',
                            'item_id' => filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                        )
                    );
                } else {
                    DB::update(
                        prefix_table("statistics"),
                        array(
                            'scope' => 'item',
                            'view' => $data['view'] + 1
                        ),
                        "item_id = %i",
                        filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                    );
                }
            }

            break;
        /**
         * Refresh list of last items seen
         */
        case "refresh_list_items_seen":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get list of last items seen
            $x_counter = 1;
            $arrTmp = array();
            $arr_html = array();
            $rows = DB::query(
                "SELECT i.id AS id, i.label AS label, i.id_tree AS id_tree, l.date
                FROM ".prefix_table("log_items")." AS l
                RIGHT JOIN ".prefix_table("items")." AS i ON (l.id_item = i.id)
                WHERE l.action = %s AND l.id_user = %i
                ORDER BY l.date DESC
                LIMIT 0, 100",
                "at_shown",
                $_SESSION['user_id']
            );
            if (DB::count() > 0) {
                foreach ($rows as $record) {
                    if (!in_array($record['id'], $arrTmp)) {
                        array_push(
                            $arr_html,
                            array(
                                "id" => $record['id'],
                                "label" => htmlspecialchars(stripslashes(htmlspecialchars_decode($record['label'], ENT_QUOTES)), ENT_QUOTES),
                                "tree_id" => $record['id_tree']
                            )
                        );
                        $x_counter++;
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
                DB::query("SELECT * FROM ".prefix_table("suggestion"));
                $nb_suggestions_waiting = DB::count();
            }

            echo json_encode(
                array(
                    "error" => "",
                    "existing_suggestions" => $nb_suggestions_waiting,
                    "html_json" => $arr_html
                ),
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
            break;

        /**
         * Generates a KEY with CRYPT
         */
        case "generate_new_key":
            // load passwordLib library
            $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
            $pwdlib->register();
            $pwdlib = new PasswordLib\PasswordLib();
            // generate key
            $key = $pwdlib->getRandomToken(filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT));
            echo '[{"key" : "'.htmlentities($key, ENT_QUOTES).'"}]';
            break;

        /**
         * Generates a TOKEN with CRYPT
         */
        case "save_token":
            $token = GenerateCryptKey(
                null !== filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) ? filter_input(INPUT_POST, 'size', FILTER_SANITIZE_NUMBER_INT) : 20,
                null !== filter_input(INPUT_POST, 'secure', FILTER_SANITIZE_BOOLEAN) ? filter_input(INPUT_POST, 'secure', FILTER_SANITIZE_BOOLEAN) : false,
                null !== filter_input(INPUT_POST, 'numeric', FILTER_SANITIZE_BOOLEAN) ? filter_input(INPUT_POST, 'numeric', FILTER_SANITIZE_BOOLEAN) : false,
                null !== filter_input(INPUT_POST, 'capital', FILTER_SANITIZE_BOOLEAN) ? filter_input(INPUT_POST, 'capital', FILTER_SANITIZE_BOOLEAN) : false,
                null !== filter_input(INPUT_POST, 'symbols', FILTER_SANITIZE_BOOLEAN) ? filter_input(INPUT_POST, 'symbols', FILTER_SANITIZE_BOOLEAN) : false
            );

            // store in DB
            DB::insert(
                prefix_table("tokens"),
                array(
                    'user_id' => $_SESSION['user_id'],
                    'token' => $token,
                    'reason' => filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING),
                    'creation_timestamp' => time(),
                    'end_timestamp' => time() + filter_input(INPUT_POST, 'duration', FILTER_SANITIZE_NUMBER_INT)    // in secs
                )
            );

            echo '[{"token" : "'.$token.'"}]';
            break;

        /**
         * Create list of timezones
         */
        case "generate_timezones_list":
            $array = array();
            foreach (timezone_identifiers_list() as $zone) {
                $array[$zone] = $zone;
            }

            echo json_encode($array);
            break;

        /**
         * Check if suggestions are existing
         */
        case "is_existings_suggestions":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            if ($_SESSION['user_manager'] === "1" || $_SESSION['is_admin'] === "1") {
                $count = 0;
                DB::query("SELECT * FROM ".$pre."items_change");
                $count += DB::count();
                DB::query("SELECT * FROM ".$pre."suggestion");
                $count += DB::count();

                echo '[ { "error" : "" , "count" : "'.$count.'" , "show_sug_in_menu" : "0"} ]';
            } elseif (isset($_SESSION['nb_item_change_proposals']) && $_SESSION['nb_item_change_proposals'] > 0) {
                echo '[ { "error" : "" , "count" : "'.$_SESSION['nb_item_change_proposals'].'" , "show_sug_in_menu" : "1"} ]';
            } else {
                echo '[ { "error" : "" , "count" : "" , "show_sug_in_menu" : "0"} ]';
            }

            break;

        /**
         * Check if suggestions are existing
         */
        case "sending_statistics":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            if (isset($SETTINGS['send_statistics_items']) && isset($SETTINGS['send_stats']) && isset($SETTINGS['send_stats_time'])
                && $SETTINGS['send_stats'] === "1"
                && ($SETTINGS['send_stats_time'] + $SETTINGS_EXT['one_day_seconds']) > time()
            ) {
                // get statistics data
                $stats_data = getStatisticsData();


                // get statistics items to share
                $statsToSend = [];
                $statsToSend['ip'] = $_SERVER['SERVER_ADDR'];
                $statsToSend['timestamp'] = time();
                foreach (array_filter(explode(";", $SETTINGS['send_statistics_items'])) as $data) {
                    if ($data === "stat_languages") {
                        $tmp = "";
                        foreach ($stats_data[$data] as $key => $value) {
                            if (empty($tmp)) {
                                $tmp = $key."-".$value;
                            } else {
                                $tmp .= ",".$key."-".$value;
                            }
                        }
                        $statsToSend[$data] = $tmp;
                    } elseif ($data === "stat_country") {
                        $tmp = "";
                        foreach ($stats_data[$data] as $key => $value) {
                            if (empty($tmp)) {
                                $tmp = $key."-".$value;
                            } else {
                                $tmp .= ",".$key."-".$value;
                            }
                        }
                        $statsToSend[$data] = $tmp;
                    } else {
                        $statsToSend[$data] = $stats_data[$data];
                    }
                }

                // connect to Teampass Statistics database
                db::debugmode(true);
                $link2 = new MeekroDB(
                    "sql11.freemysqlhosting.net",
                    "sql11197223",
                    "3QzpXYQ9dZ",
                    "sql11197223",
                    "3306",
                    "utf8"
                );

                $link2->insert(
                    "statistics",
                    $statsToSend
                );

                // update table misc with current timestamp
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => time()
                        ),
                    "type = %s AND intitule = %s",
                    'admin',
                    'send_stats_time'
                );


                //permits to test only once by session
                $_SESSION['temporary']['send_stats_done'] = true;
                $SETTINGS['send_stats_time'] = time();

                // save change in config file
                handleConfigFile("update", 'send_stats_time', $SETTINGS['send_stats_time']);

                echo '[ { "error" : "" , "done" : "1"} ]';
            } else {
                echo '[ { "error" : "" , "done" : "0"} ]';
            }

            break;

        /**
         * delete a file
         */
        case "file_deletion":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            fileDelete(filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_STRING));

            break;

        /**
         * Generate BUG report
         */
        case "generate_bug_report":
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Read config file
            $tmp = '';
            $list_of_options = '';
            $url_found = '';
            $anonym_url = '';
            $tp_config_file = "../includes/config/tp.config.php";
            $data = file($tp_config_file);
            foreach ($data as $line) {
                if (substr($line, 0, 4) === '    ') {
                    // Remove extra spaces
                    $line = str_replace('    ', '', $line);

                    // Identify url to anonymize it
                    if (strpos($line, 'cpassman_url') > 0 && empty($url_found) === true) {
                        $url_found = substr($line, 19, strlen($line) - 22);//echo $url_found." ; ";
                        $tmp = parse_url($url_found);
                        $anonym_url = $tmp['scheme'] . '://<anonym_url>' . $tmp['path'];
                        $line = "'cpassman_url' => '" . $anonym_url . "\n";
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
                "SELECT label, date AS error_date
                FROM ".prefix_table("log_system")."
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
            $txt = "### Steps to reproduce
1.
2.
3.

### Expected behaviour
Tell us what should happen


### Actual behaviour
Tell us what happens instead

### Server configuration
**Operating system**: ".php_uname()."

**Web server:** ".$_SERVER['SERVER_SOFTWARE']."

**Database:** ".mysqli_get_server_info($link)."

**PHP version:** ".PHP_VERSION."

**Teampass version:** ".$SETTINGS_EXT['version_full']."

**Teampass configuration file:**
```
" . $list_of_options . "
```

**Updated from an older Teampass or fresh install:**

### Client configuration

**Browser:** ".filter_input(INPUT_POST, 'browser_name', FILTER_SANITIZE_STRING)." - ".filter_input(INPUT_POST, 'browser_version', FILTER_SANITIZE_STRING)."

**Operating system:** ".filter_input(INPUT_POST, 'os', FILTER_SANITIZE_STRING)." - ".filter_input(INPUT_POST, 'os_archi', FILTER_SANITIZE_STRING)."bits

### Logs

#### Web server error log
```
" . $err['message']." - ".$err['file']." (".$err['line'] .")
```

#### Teampass 10 last system errors
```
" . $teampass_errors ."
```

#### Log from the web-browser developer console (CTRL + SHIFT + i)
```
Insert the log here and especially the answer of the query that failed.
```
";

            echo prepareExchangedData(
                array(
                    "html" => $txt,
                    "error" => ""
                ),
                "encode"
            );

            break;

            case "update_user_field":
                // Check KEY
                if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                    echo '[ { "error" : "key_not_conform" } ]';
                    break;
                }

                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData(
                    filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                    "decode"
                );

                // Prepare variables
                $field = noHTML(htmlspecialchars_decode($dataReceived['field']));
                $new_value = noHTML(htmlspecialchars_decode($dataReceived['new_value']));
                $user_id = (htmlspecialchars_decode($dataReceived['user_id']));

                DB::update(
                    prefix_table("users"),
                    array(
                        $field => $new_value
                        ),
                    "id = %i",
                    $user_id
                );

                // Update session
                if ($field === 'user_api_key') {
                  $_SESSION['user_settings']['api-key'] = $new_value;
                }
            break;
    }
}
