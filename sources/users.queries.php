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
require_once 'SecureHandler.php';
session_start();
if (isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
$isprofileupdate = filter_input(INPUT_POST, 'isprofileupdate', FILTER_SANITIZE_STRING);
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false
    || checkUser($_SESSION['user_id'], $_SESSION['key'], 'users', $SETTINGS) === false
) {
    if (null === $isprofileupdate
        || $isprofileupdate === false
    ) {
        $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
        include $SETTINGS['cpassman_dir'].'/error.php';
        exit();
    } else {
        // Do special check to allow user to change attributes of his profile
        if (empty($filtered_newvalue) === true
            || checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false
        ) {
            $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
            include $SETTINGS['cpassman_dir'].'/error.php';
            exit();
        }
    }
}

require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare post variables
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$password_do_not_change = 'do_not_change';

if (null !== $post_type) {
    switch ($post_type) {
        /*
         * ADD NEW USER
         */
        case 'add_new_user':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $login = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
            $email = filter_var($dataReceived['email'], FILTER_SANITIZE_EMAIL);
            $password = filter_var($dataReceived['pw'], FILTER_SANITIZE_STRING);
            $lastname = filter_var($dataReceived['lastname'], FILTER_SANITIZE_STRING);
            $name = filter_var($dataReceived['name'], FILTER_SANITIZE_STRING);
            $is_admin = filter_var($dataReceived['admin'], FILTER_SANITIZE_NUMBER_INT);
            $is_manager = filter_var($dataReceived['manager'], FILTER_SANITIZE_NUMBER_INT);
            $is_hr = filter_var($dataReceived['hr'], FILTER_SANITIZE_NUMBER_INT);
            $is_read_only = filter_var($dataReceived['read_only'], FILTER_SANITIZE_NUMBER_INT) || 0;
            $has_personal_folder = filter_var($dataReceived['personal_folder'], FILTER_SANITIZE_NUMBER_INT);
            $new_folder_role_domain = filter_var($dataReceived['new_folder_role_domain'], FILTER_SANITIZE_NUMBER_INT);
            $domain = filter_var($dataReceived['domain'], FILTER_SANITIZE_STRING);
            $is_administrated_by = filter_var($dataReceived['isAdministratedByRole'], FILTER_SANITIZE_STRING);
            $groups = filter_var_array($dataReceived['groups'], FILTER_SANITIZE_NUMBER_INT);
            $allowed_flds = filter_var_array($dataReceived['allowed_flds'], FILTER_SANITIZE_NUMBER_INT);
            $forbidden_flds = filter_var_array($dataReceived['forbidden_flds'], FILTER_SANITIZE_NUMBER_INT);

            // Empty user
            if (empty($login) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_empty_data'),
                    ),
                    'encode'
                );
                break;
            }
            // Check if user already exists
            $data = DB::query(
                'SELECT id, fonction_id, groupes_interdits, groupes_visibles
                FROM '.prefixTable('users').'
                WHERE login = %s',
                $login
            );

            if (DB::count() === 0) {
                // check if admin role is set. If yes then check if originator is allowed
                if ($dataReceived['admin'] === 'true' && $_SESSION['user_admin'] !== '1') {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_empty_data'),
                        ),
                        'encode'
                    );
                    break;
                }

                // load passwordLib library
                $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                $pwdlib->register();
                $pwdlib = new PasswordLib\PasswordLib();

                // Prepare variables
                $hashedPassword = $pwdlib->createPasswordHash($password);
                if ($pwdlib->verifyPasswordHash($password, $hashedPassword) === false) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('pwd_hash_not_correct'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Add user in DB
                DB::insert(
                    prefixTable('users'),
                    array(
                        'login' => $login,
                        'name' => $name,
                        'lastname' => $lastname,
                        'pw' => $hashedPassword,
                        'email' => $email,
                        'admin' => empty($is_admin) === true ? 0 : $is_admin,
                        'can_manage_all_users' => empty($is_hr) === true ? 0 : $is_hr,
                        'gestionnaire' => empty($is_manager) === true ? 0 : $is_manager,
                        'read_only' => empty($is_read_only) === true ? 0 : $is_read_only,
                        'personal_folder' => empty($has_personal_folder) === true ? 0 : $has_personal_folder,
                        'user_language' => $SETTINGS['default_language'],
                        'fonction_id' => implode(';', $groups),
                        'groupes_interdits' => implode(';', $forbidden_flds),
                        'groupes_visibles' => implode(';', $allowed_flds),
                        'isAdministratedByRole' => $is_administrated_by,
                        'encrypted_psk' => '',
                        )
                );
                $new_user_id = DB::insertId();
                // Create personnal folder
                if ($has_personal_folder === 1) {
                    DB::insert(
                        prefixTable('nested_tree'),
                        array(
                            'parent_id' => '0',
                            'title' => $new_user_id,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0',
                            'personal_folder' => '1',
                            )
                    );
                    $tree->rebuild();
                }
                // Create folder and role for domain
                if ($new_folder_role_domain === 1) {
                    // create folder
                    DB::insert(
                        prefixTable('nested_tree'),
                        array(
                            'parent_id' => 0,
                            'title' => $domain,
                            'personal_folder' => 0,
                            'renewal_period' => 0,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0',
                            )
                    );
                    $new_folder_id = DB::insertId();
                    // Add complexity
                    DB::insert(
                        prefixTable('misc'),
                        array(
                            'type' => 'complex',
                            'intitule' => $new_folder_id,
                            'valeur' => 50,
                            )
                    );
                    // Create role
                    DB::insert(
                        prefixTable('roles_title'),
                        array(
                            'title' => $domain,
                            )
                    );
                    $new_role_id = DB::insertId();
                    // Associate new role to new folder
                    DB::insert(
                        prefixTable('roles_values'),
                        array(
                            'folder_id' => $new_folder_id,
                            'role_id' => $new_role_id,
                            )
                    );
                    // Add the new user to this role
                    DB::update(
                        prefixTable('users'),
                        array(
                            'fonction_id' => is_int($new_role_id),
                            ),
                        'id=%i',
                        $new_user_id
                    );
                    // rebuild tree
                    $tree->rebuild();
                }

                // Create the API key
                DB::insert(
                    prefixTable('api'),
                    array(
                        'type' => 'user',
                        'label' => $new_user_id,
                        'value' => uniqidReal(39),
                        'timestamp' => time(),
                        )
                );

                // get links url
                if (empty($SETTINGS['email_server_url']) === true) {
                    $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                }
                // Send email to new user
                sendEmail(
                    langHdl('email_subject_new_user'),
                    str_replace(array('#tp_login#', '#tp_pw#', '#tp_link#'), array(' '.addslashes($login), addslashes($password), $SETTINGS['email_server_url']), langHdl('email_new_user_mail')),
                    $dataReceived['email'],
                    $SETTINGS
                );
                // update LOG
                logEvents(
                    'user_mngt',
                    'at_user_added',
                    $_SESSION['user_id'],
                    $_SESSION['login'],
                    $new_user_id
                );

                echo prepareExchangedData(
                    array(
                        'error' => 'no',
                        'message' => '',
                    ),
                    'encode'
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_user_exists'),
                    ),
                    'encode'
                );
            }
            break;

        /*
         * Delete the user
         */
        case 'delete_user':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                // delete user in database
                DB::delete(
                    prefixTable('users'),
                    'id = %i',
                    $post_id
                );
                // delete personal folder and subfolders
                $data = DB::queryfirstrow(
                    'SELECT id FROM '.prefixTable('nested_tree').'
                    WHERE title = %s AND personal_folder = %i',
                    $post_id,
                    '1'
                );
                // Get through each subfolder
                if (!empty($data['id'])) {
                    $folders = $tree->getDescendants($data['id'], true);
                    foreach ($folders as $folder) {
                        // delete folder
                        DB::delete(prefixTable('nested_tree'), 'id = %i AND personal_folder = %i', $folder->id, '1');
                        // delete items & logs
                        $items = DB::query(
                            'SELECT id FROM '.prefixTable('items').'
                            WHERE id_tree=%i AND perso = %i',
                            $folder->id,
                            '1'
                        );
                        foreach ($items as $item) {
                            // Delete item
                            DB::delete(prefixTable('items'), 'id = %i', $item['id']);
                            // log
                            DB::delete(prefixTable('log_items'), 'id_item = %i', $item['id']);
                        }
                    }
                    // rebuild tree
                    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                    $tree->rebuild();
                }
                // update LOG
                logEvents('user_mngt', 'at_user_deleted', $_SESSION['user_id'], $_SESSION['login'], $post_id);

                //Send back
                echo prepareExchangedData(
                    array(
                        'error' => 'no',
                        'message' => '',
                    ),
                    'encode'
                );
            } else {
                //Send back
                echo prepareExchangedData(
                    array(
                        'error' => 'no',
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
            }
            break;

        /*
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case 'can_create_root_folder':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => langHdl('error_not_allowed_to')), 'encode');
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'can_create_root_folder' => filter_input(INPUT_POST, 'value', FILTER_SANITIZE_STRING),
                        ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
        /*
         * UPDATE ADMIN RIGHTS FOR USER
         */
        case 'admin':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)
                || $_SESSION['is_admin'] !== '1'
            ) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => langHdl('error_not_allowed_to')), 'encode');
                exit();
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'admin' => $post_value,
                        'gestionnaire' => $post_value === 1 ? '0' : '0',
                        'read_only' => $post_value === 1 ? '0' : '0',
                        ),
                    'id = %i',
                    $post_id
                );

                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
        /*
         * UPDATE MANAGER RIGHTS FOR USER
         */
        case 'gestionnaire':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => langHdl('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, can_manage_all_users, gestionnaire
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'gestionnaire' => $post_value,
                        'can_manage_all_users' => ($data_user['can_manage_all_users'] === '0' && $post_value === '1') ? '0' : (
                            ($data_user['can_manage_all_users'] === '0' && $post_value === '0') ? '0' : (
                            ($data_user['can_manage_all_users'] === '1' && $post_value === '0') ? '0' : '1')
                        ),
                        'admin' => $post_value === 1 ? '0' : '0',
                        'read_only' => $post_value === 1 ? '0' : '0',
                        ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
        /*
         * UPDATE READ ONLY RIGHTS FOR USER
         */
        case 'read_only':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => langHdl('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'read_only' => $post_value,
                        'gestionnaire' => $post_value === 1 ? '0' : '0',
                        'admin' => $post_value === 1 ? 0 : '0',
                        ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
        /*
         * UPDATE CAN MANAGE ALL USERS RIGHTS FOR USER
         * Notice that this role must be also Manager
         */
        case 'can_manage_all_users':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => langHdl('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'can_manage_all_users' => $post_value,
                        'gestionnaire' => ($data_user['gestionnaire'] === '0' && $post_value === 1) ? '1' : (($data_user['gestionnaire'] === '1' && $post_value === 1) ? '1' : (($data_user['gestionnaire'] === '1' && $post_value === 0) ? '1' : '0')),
                        'admin' => $post_value === 1 ? '1' : '0',
                        'read_only' => $post_value === 1 ? '1' : '0',
                        ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;
        /*
         * UPDATE PERSONNAL FOLDER FOR USER
         */
        case 'personal_folder':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo prepareExchangedData(array('error' => 'not_allowed', 'error_text' => langHdl('error_not_allowed_to')), 'encode');
                break;
            }

            $post_value = filter_input(INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT);
            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'personal_folder' => $post_value === '1' ? '1' : '0',
                        ),
                    'id = %i',
                    $post_id
                );
                echo prepareExchangedData(array('error' => ''), 'encode');
            } else {
                echo prepareExchangedData(array('error' => 'not_allowed'), 'encode');
            }
            break;

        /*
         * Unlock user
         */
        case 'unlock_account':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'disabled' => 0,
                        'no_bad_attempts' => 0,
                        ),
                    'id = %i',
                    $post_id
                );
                // update LOG
                logEvents(
                    'user_mngt',
                    'at_user_unlocked',
                    $_SESSION['user_id'],
                    $_SESSION['login'],
                    $post_id
                );
            }
            break;

        /*
        * Check the domain
        */
        case 'check_domain':
            $return = array();
            // Check if folder exists
            $data = DB::query(
                'SELECT * FROM '.prefixTable('nested_tree').'
                WHERE title = %s AND parent_id = %i',
                filter_input(INPUT_POST, 'domain', FILTER_SANITIZE_STRING),
                '0'
            );
            $counter = DB::count();
            if ($counter != 0) {
                $return['folder'] = 'exists';
            } else {
                $return['folder'] = 'not_exists';
            }
            // Check if role exists
            $data = DB::query(
                'SELECT * FROM '.prefixTable('roles_title').'
                WHERE title = %s',
                filter_input(INPUT_POST, 'domain', FILTER_SANITIZE_STRING)
            );
            $counter = DB::count();
            if ($counter != 0) {
                $return['role'] = 'exists';
            } else {
                $return['role'] = 'not_exists';
            }

            echo json_encode($return);
            break;

        /*
        * Get logs for a user
        */
        case 'user_log_items':
            $nb_pages = 1;
            $logs = $sql_filter = '';
            $pages = '<table style=\'border-top:1px solid #969696;\'><tr><td>'.langHdl('pages').'&nbsp;:&nbsp;</td>';

            // Prepare POST variables
            $post_nb_items_by_page = filter_input(INPUT_POST, 'nb_items_by_page', FILTER_SANITIZE_NUMBER_INT);
            $post_scope = filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING);

            if (filter_input(INPUT_POST, 'scope', FILTER_SANITIZE_STRING) === 'user_activity') {
                if (null !== filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING)
                    && !empty(filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING))
                    && filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING) !== 'all'
                ) {
                    $sql_filter = " AND l.action = '".filter_input(INPUT_POST, 'filter', FILTER_SANITIZE_STRING)."'";
                }
                // get number of pages
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('log_items').' as l
                    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
                    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i '.$sql_filter,
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                // define query limits
                if (null !== filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)
                    && filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) > 1
                ) {
                    $start = (intval($post_nb_items_by_page)
                        * (intval(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)) - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    'SELECT l.date as date, u.login as login, i.label as label, l.action as action
                    FROM '.prefixTable('log_items').' as l
                    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
                    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
                    WHERE l.id_user = %i '.$sql_filter.'
                    ORDER BY date DESC
                    LIMIT '.intval($start).','.intval($post_nb_items_by_page),
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
            } else {
                // get number of pages
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('log_system').'
                    WHERE type = %s AND field_1=%i',
                    'user_mngt',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
                $counter = DB::count();
                // define query limits
                if (null !== filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)
                    && filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) > 1
                ) {
                    $start = (intval($post_nb_items_by_page)
                        * (intval(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT)) - 1)) + 1;
                } else {
                    $start = 0;
                }
                // launch query
                $rows = DB::query(
                    'SELECT *
                    FROM '.prefixTable('log_system').'
                    WHERE type = %s AND field_1=%i
                    ORDER BY date DESC
                    LIMIT '.mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)).', '.mysqli_real_escape_string($link, $post_nb_items_by_page),
                    'user_mngt',
                    filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                );
            }
            // generate data
            if (isset($counter) && $counter != 0) {
                $nb_pages = ceil($counter / intval($post_nb_items_by_page));
                for ($i = 1; $i <= $nb_pages; ++$i) {
                    $pages .= '<td onclick=\'displayLogs('.$i.',\"'.$post_scope.'\")\'><span style=\'cursor:pointer;'.(filter_input(INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT) === $i ? 'font-weight:bold;font-size:18px;\'>'.$i : '\'>'.$i).'</span></td>';
                }
            }
            $pages .= '</tr></table>';
            if (isset($rows)) {
                foreach ($rows as $record) {
                    if ($post_scope === 'user_mngt') {
                        $user = DB::queryfirstrow(
                            'SELECT login
                            from '.prefixTable('users').'
                            WHERE id=%i',
                            $record['qui']
                        );
                        $user_1 = DB::queryfirstrow(
                            'SELECT login
                            from '.prefixTable('users').'
                            WHERE id=%i',
                            filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT)
                        );
                        $tmp = explode(':', $record['label']);
                        // extract action done
                        $label = '';
                        if ($tmp[0] == 'at_user_initial_pwd_changed') {
                            $label = langHdl('log_user_initial_pwd_changed');
                        } elseif ($tmp[0] == 'at_user_email_changed') {
                            $label = langHdl('log_user_email_changed').$tmp[1];
                        } elseif ($tmp[0] == 'at_user_added') {
                            $label = langHdl('log_user_created');
                        } elseif ($tmp[0] == 'at_user_locked') {
                            $label = langHdl('log_user_locked');
                        } elseif ($tmp[0] == 'at_user_unlocked') {
                            $label = langHdl('log_user_unlocked');
                        } elseif ($tmp[0] == 'at_user_pwd_changed') {
                            $label = langHdl('log_user_pwd_changed');
                        }
                        // prepare log
                        $logs .= '<tr><td>'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date']).'</td><td align=\"center\">'.$label.'</td><td align=\"center\">'.$user['login'].'</td><td align=\"center\"></td></tr>';
                    } else {
                        $logs .= '<tr><td>'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date']).'</td><td align=\"center\">'.str_replace('"', '\"', $record['label']).'</td><td align=\"center\">'.$record['login'].'</td><td align=\"center\">'.langHdl($record['action']).'</td></tr>';
                    }
                }
            }

            echo '[ { "table_logs": "'.($logs).'", "pages": "'.($pages).'", "error" : "no" } ]';
            break;

        /*
        * Migrate the Admin PF to User
        */
        case 'migrate_admin_pf':
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES),
                'decode'
            );
            // Prepare variables
            $user_id = htmlspecialchars_decode($data_received['user_id']);
            $salt_user = htmlspecialchars_decode($data_received['salt_user']);

            if (!isset($_SESSION['user_settings']['clear_psk']) || $_SESSION['user_settings']['clear_psk'] == '') {
                echo '[ { "error" : "no_sk" } ]';
            } elseif ($salt_user == '') {
                echo '[ { "error" : "no_sk_user" } ]';
            } elseif ($user_id == '') {
                echo '[ { "error" : "no_user_id" } ]';
            } else {
                // Get folder id for Admin
                $admin_folder = DB::queryFirstRow(
                    'SELECT id FROM '.prefixTable('nested_tree').'
                    WHERE title = %i AND personal_folder = %i',
                    intval($_SESSION['user_id']),
                    '1'
                );
                // Get folder id for User
                $user_folder = DB::queryFirstRow(
                    'SELECT id FROM '.prefixTable('nested_tree').'
                    WHERE title=%i AND personal_folder = %i',
                    intval($user_id),
                    '1'
                );
                // Get through each subfolder
                foreach ($tree->getDescendants($admin_folder['id'], true) as $folder) {
                    // Get each Items in PF
                    $rows = DB::query(
                        'SELECT i.pw, i.label, l.id_user
                        FROM '.prefixTable('items').' as i
                        LEFT JOIN '.prefixTable('log_items').' as l ON (l.id_item=i.id)
                        WHERE l.action = %s AND i.perso=%i AND i.id_tree=%i',
                        'at_creation',
                        '1',
                        intval($folder->id)
                    );
                    foreach ($rows as $record) {
                        echo $record['label'].' - ';
                        // Change user
                        DB::update(
                            prefixTable('log_items'),
                            array(
                                'id_user' => $user_id,
                                ),
                            'id_item = %i AND id_user $ %i AND action = %s',
                            $record['id'],
                            $user_id,
                            'at_creation'
                        );
                    }
                }
                $tree->rebuild();
                echo '[ { "error" : "no" } ]';
            }

            break;

        /*
         * delete the timestamp value for specified user => disconnect
         */
        case 'disconnect_user':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            $post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole, gestionnaire
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_user_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                // Do
                DB::update(
                    prefixTable('users'),
                    array(
                        'timestamp' => '',
                        'key_tempo' => '',
                        'session_end' => '',
                        ),
                    'id = %i',
                    $post_user_id
                );
            }
            break;

        /*
         * delete the timestamp value for all users
         */
        case 'disconnect_all_users':
            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== filter_var($_SESSION['key'], FILTER_SANITIZE_STRING)) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Do
            $rows = DB::query(
                'SELECT id FROM '.prefixTable('users').'
                WHERE timestamp != %s AND admin != %i',
                '',
                '1'
            );
            foreach ($rows as $record) {
                // Get info about user to delete
                $data_user = DB::queryfirstrow(
                    'SELECT admin, isAdministratedByRole, gestionnaire
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $record['id']
                );

                // Is this user allowed to do this?
                if ((int) $_SESSION['is_admin'] === 1
                    || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                    || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
                ) {
                    DB::update(
                        prefixTable('users'),
                        array(
                            'timestamp' => '',
                            'key_tempo' => '',
                            'session_end' => '',
                            ),
                        'id = %i',
                        intval($record['id'])
                    );
                }
            }
            break;
        /*
         * Get user info
         */
        case 'get_user_info':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);

            // Get info about user
            $rowUser = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($rowUser['isAdministratedByRole'], $_SESSION['user_roles']) === true)
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && $rowUser['admin'] !== '1')
            ) {
                $arrData = array();
                $arrFunction = array();
                $arrMngBy = array();
                $arrFldForbidden = array();
                $arrFldAllowed = array();

                //Build tree
                $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
                $tree->register();
                $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

                // get FUNCTIONS
                $functionsList = array();
                $selected = '';
                $users_functions = explode(';', $rowUser['fonction_id']);
                // array of roles for actual user
                $my_functions = explode(';', $_SESSION['fonction_id']);

                $rows = DB::query('SELECT id,title,creator_id FROM '.prefixTable('roles_title'));
                foreach ($rows as $record) {
                    if ((int) $_SESSION['is_admin'] === 1
                        || (((int) $_SESSION['user_manager'] === 1 || (int) $_SESSION['user_can_manage_all_users'] === 1)
                        && (in_array($record['id'], $my_functions) || $record['creator_id'] == $_SESSION['user_id']))
                    ) {
                        if (in_array($record['id'], $users_functions)) {
                            $selected = 'selected';

                            array_push(
                                $arrFunction,
                                array(
                                    'title' => $record['title'],
                                    'id' => $record['id'],
                                )
                            );
                        } else {
                            $selected = '';
                        }

                        array_push(
                            $functionsList,
                            array(
                                'title' => $record['title'],
                                'id' => $record['id'],
                                'selected' => $selected,
                            )
                        );
                    }
                }

                // get MANAGEDBY
                $rolesList = array();
                $managedBy = array();
                $selected = '';
                $rows = DB::query('SELECT id,title FROM '.prefixTable('roles_title').' ORDER BY title ASC');
                foreach ($rows as $reccord) {
                    $rolesList[$reccord['id']] = array('id' => $reccord['id'], 'title' => $reccord['title']);
                }

                array_push(
                    $managedBy,
                    array(
                        'title' => langHdl('administrators_only'),
                        'id' => 0,
                    )
                );
                foreach ($rolesList as $fonction) {
                    if ($_SESSION['is_admin'] || in_array($fonction['id'], $_SESSION['user_roles'])) {
                        if ($rowUser['isAdministratedByRole'] == $fonction['id']) {
                            $selected = 'selected';

                            array_push(
                                $arrMngBy,
                                array(
                                    'title' => $fonction['title'],
                                    'id' => $fonction['id'],
                                )
                            );
                        } else {
                            $selected = '';
                        }

                        array_push(
                            $managedBy,
                            array(
                                'title' => langHdl('managers_of').' '.$fonction['title'],
                                'id' => $fonction['id'],
                                'selected' => $selected,
                            )
                        );
                    }
                }

                if (count($arrMngBy) === 0) {
                    array_push(
                        $arrMngBy,
                        array(
                            'title' => langHdl('administrators_only'),
                            'id' => '0',
                        )
                    );
                }

                // get FOLDERS FORBIDDEN
                $forbiddenFolders = array();
                $userForbidFolders = explode(';', $rowUser['groupes_interdits']);
                $tree_desc = $tree->getDescendants();
                foreach ($tree_desc as $t) {
                    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
                        $selected = '';
                        if (in_array($t->id, $userForbidFolders)) {
                            $selected = 'selected';

                            array_push(
                                $arrFldForbidden,
                                array(
                                    'title' => htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                                    'id' => $t->id,
                                )
                            );
                        }
                        array_push(
                            $forbiddenFolders,
                            array(
                                'id' => $t->id,
                                'selected' => $selected,
                                'title' => @htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                            )
                        );
                    }
                }

                // get FOLDERS ALLOWED
                $allowedFolders = array();
                $userAllowFolders = explode(';', $rowUser['groupes_visibles']);
                $tree_desc = $tree->getDescendants();
                foreach ($tree_desc as $t) {
                    if (in_array($t->id, $_SESSION['groupes_visibles']) === true
                        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
                    ) {
                        $selected = '';
                        if (in_array($t->id, $userAllowFolders)) {
                            $selected = 'selected';

                            array_push(
                                $arrFldAllowed,
                                array(
                                    'title' => htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                                    'id' => $t->id,
                                )
                            );
                        }

                        array_push(
                            $allowedFolders,
                            array(
                                'id' => $t->id,
                                'selected' => $selected,
                                'title' => @htmlspecialchars($t->title, ENT_COMPAT, 'UTF-8'),
                            )
                        );
                    }
                }

                // get USER STATUS
                if ($rowUser['disabled'] == 1) {
                    $arrData['info'] = langHdl('user_info_locked').'<br><input type="checkbox" value="unlock" name="1" class="chk">&nbsp;<label for="1">'.langHdl('user_info_unlock_question').'</label><br><input type="checkbox"  value="delete" id="account_delete" class="chk mr-2" name="2" onclick="confirmDeletion()">label for="2">'.langHdl('user_info_delete_question').'</label>';
                } else {
                    $arrData['info'] = langHdl('user_info_active').'<br><input type="checkbox" value="lock" class="chk">&nbsp;'.langHdl('user_info_lock_question');
                }

                $arrData['error'] = false;
                $arrData['login'] = $rowUser['login'];
                $arrData['name'] = htmlspecialchars_decode($rowUser['name'], ENT_QUOTES);
                $arrData['lastname'] = htmlspecialchars_decode($rowUser['lastname'], ENT_QUOTES);
                $arrData['email'] = $rowUser['email'];
                $arrData['function'] = $functionsList;
                $arrData['managedby'] = $managedBy;
                $arrData['foldersForbid'] = $forbiddenFolders;
                $arrData['foldersAllow'] = $allowedFolders;
                $arrData['share_function'] = $arrFunction;
                $arrData['share_managedby'] = $arrMngBy;
                $arrData['share_forbidden'] = $arrFldForbidden;
                $arrData['share_allowed'] = $arrFldAllowed;
                $arrData['disabled'] = (int) $rowUser['disabled'];
                $arrData['gestionnaire'] = (int) $rowUser['gestionnaire'];
                $arrData['read_only'] = (int) $rowUser['read_only'];
                $arrData['can_create_root_folder'] = (int) $rowUser['can_create_root_folder'];
                $arrData['personal_folder'] = (int) $rowUser['personal_folder'];
                $arrData['can_manage_all_users'] = (int) $rowUser['can_manage_all_users'];
                $arrData['admin'] = (int) $rowUser['admin'];
                $arrData['password'] = $password_do_not_change;

                echo prepareExchangedData(
                    $arrData,
                    'encode'
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
            }

            break;

        /*
         * EDIT user
         */
        case 'store_user_changes':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_STRING);
            $post_login = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
            $post_email = filter_var($dataReceived['email'], FILTER_SANITIZE_EMAIL);
            $post_password = filter_var($dataReceived['pw'], FILTER_SANITIZE_STRING);
            $post_lastname = filter_var($dataReceived['lastname'], FILTER_SANITIZE_STRING);
            $post_name = filter_var($dataReceived['name'], FILTER_SANITIZE_STRING);
            $post_is_admin = filter_var($dataReceived['admin'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_manager = filter_var($dataReceived['manager'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_hr = filter_var($dataReceived['hr'], FILTER_SANITIZE_NUMBER_INT);
            $post_is_read_only = filter_var($dataReceived['read_only'], FILTER_SANITIZE_NUMBER_INT);
            $post_has_personal_folder = filter_var($dataReceived['personal_folder'], FILTER_SANITIZE_NUMBER_INT);
            $post_new_folder_role_domain = filter_var($dataReceived['new_folder_role_domain'], FILTER_SANITIZE_NUMBER_INT);
            $post_domain = filter_var($dataReceived['domain'], FILTER_SANITIZE_STRING);
            $post_is_administrated_by = filter_var($dataReceived['isAdministratedByRole'], FILTER_SANITIZE_STRING);
            $post_groups = filter_var_array($dataReceived['groups'], FILTER_SANITIZE_NUMBER_INT);
            $post_allowed_flds = filter_var_array($dataReceived['allowed_flds'], FILTER_SANITIZE_NUMBER_INT);
            $post_forbidden_flds = filter_var_array($dataReceived['forbidden_flds'], FILTER_SANITIZE_NUMBER_INT);
            $post_root_level = filter_var($dataReceived['form-create-root-folder'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_disabled = filter_var($dataReceived['form-user-disabled'], FILTER_SANITIZE_NUMBER_INT);

            // Init post variables
            $post_action_to_perform = filter_var(htmlspecialchars_decode($dataReceived['action_on_user']), FILTER_SANITIZE_STRING);

            // Build array of update
            $changeArray = array(
                'login' => $post_login,
                'name' => $post_name,
                'lastname' => $post_lastname,
                'email' => $post_email,
                'disabled' => empty($post_user_disabled) === true ? 0 : $post_user_disabled,
                'admin' => empty($post_is_admin) === true ? 0 : $post_is_admin,
                'can_manage_all_users' => empty($post_is_hr) === true ? 0 : $post_is_hr,
                'gestionnaire' => empty($post_is_manager) === true ? 0 : $post_is_manager,
                'read_only' => empty($post_is_read_only) === true ? 0 : $post_is_read_only,
                'personal_folder' => empty($post_has_personal_folder) === true ? 0 : $post_has_personal_folder,
                'user_language' => $SETTINGS['default_language'],
                'fonction_id' => implode(';', $post_groups),
                'groupes_interdits' => implode(';', $post_forbidden_flds),
                'groupes_visibles' => implode(';', $post_allowed_flds),
                'isAdministratedByRole' => $post_is_administrated_by,
                'can_create_root_folder' => empty($post_root_level) === true ? 0 : $post_root_level,
            );

            if ($post_password !== $password_do_not_change) {
                // load passwordLib library
                $pwdlib = new SplClassLoader('PasswordLib', '../includes/libraries');
                $pwdlib->register();
                $pwdlib = new PasswordLib\PasswordLib();

                $changeArray['pw'] = $pwdlib->createPasswordHash($post_password);
                $changeArray['key_tempo'] = '';
            }

            // Empty user
            if (empty($post_login) === true) {
                echo '[ { "error" : "'.langHdl('error_empty_data').'" } ]';
                break;
            }

            // User has email?
            if (empty($post_email) === true) {
                echo '[ { "error" : "'.langHdl('error_no_email').'" } ]';
                break;
            }

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                // delete account
                // delete user in database
                if ($post_action_to_perform === 'delete') {
                    DB::delete(
                        prefixTable('users'),
                        'id = %i',
                        $post_id
                    );
                    // delete personal folder and subfolders
                    $data = DB::queryfirstrow(
                        'SELECT id FROM '.prefixTable('nested_tree').'
                        WHERE title = %s AND personal_folder = %i',
                        $post_id,
                        '1'
                    );
                    // Get through each subfolder
                    if (!empty($data['id'])) {
                        $folders = $tree->getDescendants($data['id'], true);
                        foreach ($folders as $folder) {
                            // delete folder
                            DB::delete(prefixTable('nested_tree'), 'id = %i AND personal_folder = %i', $folder->id, '1');
                            // delete items & logs
                            $items = DB::query(
                                'SELECT id FROM '.prefixTable('items').'
                                WHERE id_tree=%i AND perso = %i',
                                $folder->id,
                                '1'
                            );
                            foreach ($items as $item) {
                                // Delete item
                                DB::delete(prefixTable('items'), 'id = %i', $item['id']);
                                // log
                                DB::delete(prefixTable('log_items'), 'id_item = %i', $item['id']);
                            }
                        }
                        // rebuild tree
                        $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                        $tree->rebuild();
                    }
                    // update LOG
                    logEvents('user_mngt', 'at_user_deleted', $_SESSION['user_id'], $_SESSION['login'], $post_id);
                } else {
                    // Get old data about user
                    $oldData = DB::queryfirstrow(
                        'SELECT * FROM '.prefixTable('users').'
                        WHERE id = %i',
                        $post_id
                    );

                    // manage account status
                    if ($post_user_disabled === 1) {
                        $logDisabledText = 'at_user_locked';
                    } else {
                        $logDisabledText = 'at_user_unlocked';
                    }

                    // update user
                    DB::update(
                        prefixTable('users'),
                        $changeArray,
                        'id = %i',
                        $post_id
                    );

                    // update SESSION
                    if ($_SESSION['user_id'] === $post_id) {
                        $_SESSION['user_email'] = $post_email;
                        $_SESSION['name'] = $post_name;
                        $_SESSION['lastname'] = $post_lastname;
                    }

                    // update LOG
                    if ($oldData['email'] !== $post_email) {
                        logEvents('user_mngt', 'at_user_email_changed:'.$oldData['email'], intval($_SESSION['user_id']), $_SESSION['login'], $post_id);
                    }

                    if ((int) $oldData['disabled'] !== (int) $post_user_disabled) {
                        // update LOG
                        logEvents('user_mngt', $logDisabledText, $_SESSION['user_id'], $_SESSION['login'], $post_id);
                    }
                }
                echo prepareExchangedData(
                    array(
                        'error' => false,
                        'message' => '',
                    ),
                    'encode'
                );
            } else {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
            }
            break;

        /*
         * UPDATE CAN CREATE ROOT FOLDER RIGHT
         */
        case 'user_edit_login':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);

            // Get info about user to delete
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                DB::update(
                    prefixTable('users'),
                    array(
                        'login' => filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING),
                        'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING),
                        'lastname' => filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING),
                    ),
                    'id = %i',
                    $post_id
                );
            }
            break;

        /*
         * IS LOGIN AVAILABLE?
         */
        case 'is_login_available':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            DB::queryfirstrow(
                'SELECT * FROM '.prefixTable('users').'
                WHERE login = %s',
                filter_input(INPUT_POST, 'login', FILTER_SANITIZE_STRING)
            );

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'login_exists' => DB::count(),
                ),
                'encode'
            );

            break;

        /*
         * GET USER FOLDER RIGHT
         */
        case 'user_folders_rights':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Prepare variables
            $post_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

            $arrData = array();

            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // get User info
            $rowUser = DB::queryFirstRow(
                'SELECT login, name, lastname, email, disabled, fonction_id, groupes_interdits, groupes_visibles, isAdministratedByRole, avatar_thumb
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_id
            );

            // get rights
            $functionsList = '';
            $arrFolders = [];
            $html = '';

            $arrData['functions'] = array_filter(explode(';', $rowUser['fonction_id']));
            $arrData['allowed_folders'] = array_filter(explode(';', $rowUser['groupes_visibles']));
            $arrData['denied_folders'] = array_filter(explode(';', $rowUser['groupes_interdits']));

            // Exit if no roles
            if (count($arrData['functions']) > 0) {
                // refine folders based upon roles
                $rows = DB::query(
                    'SELECT folder_id, type
                    FROM '.prefixTable('roles_values').'
                    WHERE role_id IN %ls
                    ORDER BY folder_id ASC',
                    $arrData['functions']
                );
                foreach ($rows as $record) {
                    $bFound = false;
                    $x = 0;
                    foreach ($arrFolders as $fld) {
                        if ($fld['id'] === $record['folder_id']) {
                            // get the level of access on the folder
                            $arrFolders[$x]['type'] = evaluate_folder_acces_level($record['type'], $arrFolders[$x]['type']);
                            $bFound = true;
                            break;
                        }
                        ++$x;
                    }
                    if ($bFound === false && !in_array($record['folder_id'], $arrData['denied_folders'])) {
                        array_push($arrFolders, array('id' => $record['folder_id'], 'type' => $record['type']));
                    }
                }

                $tree_desc = $tree->getDescendants();
                foreach ($tree_desc as $t) {
                    foreach ($arrFolders as $fld) {
                        if ($fld['id'] === $t->id) {
                            // get folder name
                            $row = DB::queryFirstRow(
                                'SELECT title, nlevel
                                FROM '.prefixTable('nested_tree').'
                                WHERE id = %i',
                                $fld['id']
                            );

                            // manage indentation
                            $ident = '';
                            for ($y = 1; $y < $row['nlevel']; ++$y) {
                                $ident .= '<i class="fas fa-long-arrow-alt-right mr-2"></i>';
                            }

                            // manage right icon
                            if ($fld['type'] == 'W') {
                                $label = '<i class="fas fa-indent infotip text-success mr-2" title="'.langHdl('write').'"></i>'.
                                '<i class="fas fa-edit infotip text-success mr-2" title="'.langHdl('edit').'"></i>'.
                                '<i class="fas fa-eraser infotip text-success" title="'.langHdl('delete').'"></i>';
                            } elseif ($fld['type'] == 'ND') {
                                $label = '<i class="fas fa-indent infotip text-warning mr-2" title="'.langHdl('write').'"></i>'.
                                '<i class="fas fa-edit infotip text-success mr-2" title="'.langHdl('edit').'"></i>'.
                                '<i class="fas fa-edit infotip text-danger" title="'.langHdl('no_delete').'"></i>';
                            } elseif ($fld['type'] == 'NE') {
                                $label = '<i class="fas fa-indent infotip text-warning mr-2" title="'.langHdl('write').'"></i>'.
                                '<i class="fas fa-edit infotip text-danger mr-2" title="'.langHdl('no_edit').'"></i>'.
                                '<i class="fas fa-edit infotip text-success" title="'.langHdl('delete').'"></i>';
                            } elseif ($fld['type'] == 'NDNE') {
                                $label = '<i class="fas fa-indent infotip text-warning mr-2" title="'.langHdl('write').'"></i>'.
                                '<i class="fas fa-edit infotip text-danger mr-2" title="'.langHdl('no_edit').'"></i>'.
                                '<i class="fas fa-edit infotip text-danger" title="'.langHdl('no_delete').'"></i>';
                            } else {
                                $color = '#FEBC11';
                                $allowed = 'R';
                                $title = langHdl('read');
                                $label = '<i class="fas fa-eye infotip text-info mr-2" title="'.langHdl('read').'"></i>';
                            }

                            $html .= '<tr><td>'.$ident.$row['title'].'</td><td>'.$label.'</td></tr>';
                            break;
                        }
                    }
                }

                $html_full = '<table id="table-folders" class="table table-bordered table-striped dt-responsive nowrap" style="width:100%"><tbody>'.
                   $html.'</tbody></table>';
            } else {
                $html_full = '';
            }

            echo prepareExchangedData(
                array(
                    'html' => $html_full,
                    'error' => false,
                    'login' => $rowUser['login'],
                    'message' => '',
                ),
                'encode'
            );
            break;

        /*
         * GET LIST OF USERS
         */
        case 'get_list_of_users_for_sharing':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            $arrUsers = [];

            if (!$_SESSION['is_admin'] && !$_SESSION['user_can_manage_all_users']) {
                $rows = DB::query(
                    'SELECT *
                    FROM '.prefixTable('users').'
                    WHERE admin = %i AND isAdministratedByRole IN %ls',
                    '0',
                    array_filter($_SESSION['user_roles'])
                );
            } else {
                $rows = DB::query(
                    'SELECT *
                    FROM '.prefixTable('users').'
                    WHERE admin = %i',
                    '0'
                );
            }

            foreach ($rows as $record) {
                // Get roles
                $groups = [];
                $groupIds = [];
                foreach(explode(';', $record['fonction_id']) as $group) {
                    $tmp = DB::queryfirstrow(
                        'SELECT id, title FROM '.prefixTable('roles_title').'
                        WHERE id = %i',
                        $group
                    );
                    array_push($groups, $tmp['title']);
                    array_push($groupIds, $tmp['id']);
                }

                // Get managed_by
                $managedBy = DB::queryfirstrow(
                    'SELECT id, title FROM '.prefixTable('roles_title').'
                    WHERE id = %i',
                    $record['isAdministratedByRole']
                );

                // Get Allowed folders
                $foldersAllowed = [];
                $foldersAllowedIds = [];
                foreach(explode(';', $record['groupes_visibles']) as $role) {
                    $tmp = DB::queryfirstrow(
                        'SELECT id, title FROM '.prefixTable('nested_tree').'
                        WHERE id = %i',
                        $role
                    );
                    array_push($foldersAllowed, $tmp['title']);
                    array_push($foldersAllowedIds, $tmp['id']);
                }

                // Get denied folders
                $foldersForbidden = [];
                $foldersForbiddenIds = [];
                foreach(explode(';', $record['groupes_interdits']) as $role) {
                    $tmp = DB::queryfirstrow(
                        'SELECT id, title FROM '.prefixTable('nested_tree').'
                        WHERE id = %i',
                        $role
                    );
                    array_push($foldersForbidden, $tmp['title']);
                    array_push($foldersForbiddenIds, $tmp['id']);
                }


                // Store
                array_push(
                    $arrUsers,
                    array(
                        'id' => $record['id'],
                        'name' => $record['name'],
                        'lastname' => $record['lastname'],
                        'login' => $record['login'],
                        'groups' => implode(', ', $groups),
                        'groupIds' => $groupIds,
                        'managedBy' => $managedBy['title'] === null ? langHdl('administrator') : $managedBy['title'],
                        'managedById' => $managedBy['id'] === null ? 0 : $managedBy['id'],
                        'foldersAllowed' => implode(', ', $foldersAllowed),
                        'foldersAllowedIds' => $foldersAllowedIds,
                        'foldersForbidden' => implode(', ', $foldersForbidden),
                        'foldersForbiddenIds' => $foldersForbiddenIds,
                        'admin' => $record['admin'],
                        'manager' => $record['gestionnaire'],
                        'hr' => $record['can_manage_all_users'],
                        'readOnly' => $record['read_only'],
                        'personalFolder' => $record['personal_folder'],
                        'rootFolder' => $record['can_create_root_folder'],
                    )
                );
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'values' => $arrUsers,
                ),
                'encode'
            );

            break;

        /*
         * UPDATE USERS RIGHTS BY SHARING
         */
        case 'update_users_rights_sharing':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            $post_source_id = filter_var(htmlspecialchars_decode($dataReceived['source_id']), FILTER_SANITIZE_NUMBER_INT);
            $post_destination_ids = filter_var_array($dataReceived['destination_ids'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_functions = filter_var(htmlspecialchars_decode($dataReceived['user_functions']), FILTER_SANITIZE_STRING);
            $post_user_managedby = filter_var(htmlspecialchars_decode($dataReceived['user_managedby']), FILTER_SANITIZE_STRING);
            $post_user_fldallowed = filter_var(htmlspecialchars_decode($dataReceived['user_fldallowed']), FILTER_SANITIZE_STRING);
            $post_user_fldforbid = filter_var(htmlspecialchars_decode($dataReceived['user_fldforbid']), FILTER_SANITIZE_STRING);
            $post_user_admin = filter_var(htmlspecialchars_decode($dataReceived['user_admin']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_manager = filter_var(htmlspecialchars_decode($dataReceived['user_manager']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_hr = filter_var(htmlspecialchars_decode($dataReceived['user_hr']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_readonly = filter_var(htmlspecialchars_decode($dataReceived['user_readonly']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_personalfolder = filter_var(htmlspecialchars_decode($dataReceived['user_personalfolder']), FILTER_SANITIZE_NUMBER_INT);
            $post_user_rootfolder = filter_var(htmlspecialchars_decode($dataReceived['user_rootfolder']), FILTER_SANITIZE_NUMBER_INT);


            // Check send values
            if (empty($post_source_id) === true
                || $post_destination_ids === 0
            ) {
                // error
                exit();
            }

            // Get info about user
            $data_user = DB::queryfirstrow(
                'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_source_id
            );

            // Is this user allowed to do this?
            if ((int) $_SESSION['is_admin'] === 1
                || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
            ) {
                foreach ($post_destination_ids as $dest_user_id) {
                    // Is this user allowed to do this?
                    if ((int) $_SESSION['is_admin'] === 1
                        || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
                        || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
                    ) {
                        // update user
                        DB::update(
                            prefixTable('users'),
                            array(
                                'fonction_id' => $post_user_functions,
                                'isAdministratedByRole' => $post_user_managedby,
                                'groupes_visibles' => $post_user_fldallowed,
                                'groupes_interdits' => $post_user_fldforbid,
                                'gestionnaire' => $post_user_manager,
                                'read_only' => $post_user_readonly,
                                'can_create_root_folder' => $post_user_rootfolder,
                                'personal_folder' => $post_user_personalfolder,
                                'can_manage_all_users' => $post_user_hr,
                                'admin' => $post_user_admin,
                                ),
                            'id = %i',
                            $dest_user_id
                        );
                    }
                }
            }
            break;

        /*
         * UPDATE USER PROFILE
         */
        case 'user_profile_update':
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

            // Check user
            if (isset($_SESSION['user_id']) === false
                || empty($_SESSION['user_id']) === true
            ) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('no_user'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            if (empty($dataReceived) === false) {
                // update user
                DB::update(
                    prefixTable('users'),
                    array(
                        'email' => filter_var(htmlspecialchars_decode($dataReceived['email']), FILTER_SANITIZE_EMAIL),
                        'usertimezone' => filter_var(htmlspecialchars_decode($dataReceived['timezone']), FILTER_SANITIZE_STRING),
                        'user_language' => filter_var(htmlspecialchars_decode($dataReceived['language']), FILTER_SANITIZE_STRING),
                        'treeloadstrategy' => filter_var(htmlspecialchars_decode($dataReceived['treeloadstrategy']), FILTER_SANITIZE_STRING),
                        'agses-usercardid' => filter_var(htmlspecialchars_decode($dataReceived['agsescardid']), FILTER_SANITIZE_NUMBER_INT),
                        ),
                    'id = %i',
                    $_SESSION['user_id']
                );
            } else {
                // An error appears on JSON format
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('json_error_format'),
                    ),
                    'encode'
                );
            }

            // Encrypt data to return
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        //CASE where refreshing table
        case 'save_user_change':
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
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $post_user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_field = filter_var($dataReceived['field'], FILTER_SANITIZE_STRING);
            $post_new_value = filter_var($dataReceived['value'], FILTER_SANITIZE_STRING);

            DB::update(
                prefixTable('users'),
                array(
                    $post_field => $post_new_value,
                ),
                'id = %i',
                $post_user_id
            );
            $return = '';

            $return = array(
                'error' => false,
                'message' => '',
                'return' => $return,
            );

            // send data
            //echo prepareExchangedData($return, 'encode');
            echo json_encode($return);

            break;

        /*
         * STORE USER LOCATION
         */
        case 'save_user_location':
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

            // Manage 1st step - is this needed?
            if (filter_input(INPUT_POST, 'step', FILTER_SANITIZE_STRING) === 'refresh') {
                $record = DB::queryFirstRow(
                    'SELECT user_ip_lastdate
                    FROM '.prefixTable('users').'
                    WHERE id = %i',
                    $_SESSION['user_id']
                );

                if (empty($record['user_ip_lastdate']) === true
                    || (time() - $record['user_ip_lastdate']) > $SETTINGS_EXT['one_day_seconds']
                ) {
                    echo prepareExchangedData(
                        array(
                            'refresh' => true,
                            'error' => '',
                        ),
                        'encode'
                    );
                    break;
                }
            } elseif (filter_input(INPUT_POST, 'step', FILTER_SANITIZE_STRING) === 'perform') {
                $post_location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
                if (empty($post_location) === false) {
                    DB::update(
                        prefixTable('users'),
                        array(
                            'user_ip' => $post_location,
                            'user_ip_lastdate' => time(),
                            ),
                        'id = %i',
                        $_SESSION['user_id']
                    );

                    echo prepareExchangedData(
                        array(
                            'refresh' => false,
                            'error' => '',
                        ),
                        'encode'
                    );
                    break;
                }
            } else {
            }

            echo prepareExchangedData(
                array(
                    'refresh' => '',
                    'error' => '',
                ),
                'encode'
            );

            break;
    }
    // # NEW LOGIN FOR USER HAS BEEN DEFINED ##
} elseif (!empty(filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_STRING))) {
    // Prepare POST variables
    $value = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));
    $post_newValue = filter_input(INPUT_POST, 'newValue', FILTER_SANITIZE_STRING);

    // Get info about user
    $data_user = DB::queryfirstrow(
        'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
        WHERE id = %i',
        $value[1]
    );

    // Is this user allowed to do this?
    if ((int) $_SESSION['is_admin'] === 1
        || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
        || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
        || ($_SESSION['user_id'] === $value[1])
    ) {
        if ($value[0] === 'userlanguage') {
            $value[0] = 'user_language';
            $post_newValue = strtolower($post_newValue);
        }
        // Check that operation is allowed
        if (in_array(
            $value[0],
            array('login', 'pw', 'email', 'treeloadstrategy', 'usertimezone', 'user_api_key', 'yubico_user_key', 'yubico_user_id', 'agses-usercardid', 'user_language', 'psk')
        )
        ) {
            DB::update(
                prefixTable('users'),
                array(
                    $value[0] => $post_newValue,
                    ),
                'id = %i',
                $value[1]
            );
            // update LOG
            logEvents(
                'user_mngt',
                'at_user_new_'.$value[0].':'.$value[1],
                $_SESSION['user_id'],
                $_SESSION['login'],
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING)
            );
            // refresh SESSION if requested
            if ($value[0] === 'treeloadstrategy') {
                $_SESSION['user_settings']['treeloadstrategy'] = $post_newValue;
            } elseif ($value[0] === 'usertimezone') {
                // special case for usertimezone where session needs to be updated
                $_SESSION['user_settings']['usertimezone'] = $post_newValue;
            } elseif ($value[0] === 'userlanguage') {
                // special case for user_language where session needs to be updated
                $_SESSION['user_settings']['user_language'] = $post_newValue;
                $_SESSION['user_language'] = $post_newValue;
            } elseif ($value[0] === 'agses-usercardid') {
                // special case for agsescardid where session needs to be updated
                $_SESSION['user_settings']['agses-usercardid'] = $post_newValue;
            } elseif ($value[0] === 'email') {
                // store email change in session
                $_SESSION['user_email'] = $post_newValue;
            }
            // Display info
            echo htmlentities($post_newValue, ENT_QUOTES);
        }
    }
    // # ADMIN FOR USER HAS BEEN DEFINED ##
} elseif (null !== filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT)) {
    $id = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));

    // Get info about user
    $data_user = DB::queryfirstrow(
        'SELECT admin, isAdministratedByRole FROM '.prefixTable('users').'
        WHERE id = %i',
        $value[1]
    );

    // Is this user allowed to do this?
    if ((int) $_SESSION['is_admin'] === 1
        || (in_array($data_user['isAdministratedByRole'], $_SESSION['user_roles']))
        || ((int) $_SESSION['user_can_manage_all_users'] === 1 && (int) $data_user['admin'] !== 1)
        || ($_SESSION['user_id'] === $value[1])
    ) {
        DB::update(
            prefixTable('users'),
            array(
                'admin' => filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT),
                ),
            'id = %i',
            $id[1]
        );
        // Display info
        if (filter_input(INPUT_POST, 'newadmin', FILTER_SANITIZE_NUMBER_INT) === 1) {
            echo 'Oui';
        } else {
            echo 'Non';
        }
    }
}

/**
 * Return the level of access on a folder.
 *
 * @param string $new_val      New value
 * @param string $existing_val Current value
 *
 * @return string Returned index
 */
function evaluate_folder_acces_level($new_val, $existing_val)
{
    $levels = array(
        'W' => 4,
        'ND' => 3,
        'NE' => 3,
        'NDNE' => 2,
        'R' => 1,
    );

    if (empty($existing_val)) {
        $current_level_points = 0;
    } else {
        $current_level_points = $levels[$existing_val];
    }
    $new_level_points = $levels[$new_val];

    // check if new is > to current one (always keep the highest level)
    if (($new_val === 'ND' && $existing_val === 'NE')
        ||
        ($new_val === 'NE' && $existing_val === 'ND')
    ) {
        return 'NDNE';
    } else {
        if ($current_level_points > $new_level_points) {
            return  $existing_val;
        } else {
            return  $new_val;
        }
    }
}
