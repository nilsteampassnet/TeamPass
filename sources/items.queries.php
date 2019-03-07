<?php
/**
 * @author        Nils Laumaillé <nils@teampass.net>
 *
 * @version       2.1.27
 *
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 *
 * @see          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

/*
 * Define Timezone
**/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

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

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// Class loader
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// phpcrypt
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/phpcrypt/phpCrypt.php';
use PHP_Crypt\PHP_Crypt as PHP_Crypt;

// Prepare POST variables
$post_page = filter_input(INPUT_POST, 'page', FILTER_SANITIZE_STRING);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_label = filter_input(INPUT_POST, 'label', FILTER_SANITIZE_STRING);
$post_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
$post_cat = filter_input(INPUT_POST, 'cat', FILTER_SANITIZE_STRING);
$post_receipt = filter_input(INPUT_POST, 'receipt', FILTER_SANITIZE_STRING);
$post_item_id = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);
$post_id_tree = filter_input(INPUT_POST, 'id_tree', FILTER_SANITIZE_NUMBER_INT);
$post_folder_id = filter_input(INPUT_POST, 'folder_id', FILTER_SANITIZE_NUMBER_INT);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_destination = filter_input(INPUT_POST, 'destination', FILTER_SANITIZE_NUMBER_INT);
$post_source = filter_input(INPUT_POST, 'source', FILTER_SANITIZE_NUMBER_INT);
$post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
$post_iFolderId = filter_input(INPUT_POST, 'iFolderId', FILTER_SANITIZE_NUMBER_INT);

// Do asked action
if (null !== $post_type) {
    switch ($post_type) {
        /*
        * CASE
        * creating a new ITEM
        */
        case 'new_item':
            // Check KEY and rights
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

            // init
            $reloadPage = false;
            $returnValues = array();
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            if (count($dataReceived) > 0) {
                // Prepare variables
                $post_anyone_can_modify = filter_var($dataReceived['anyone_can_modify'], FILTER_SANITIZE_NUMBER_INT);
                $post_complexity_level = filter_var($dataReceived['complexity_level'], FILTER_SANITIZE_NUMBER_INT);
                $post_description = ($dataReceived['description']);
                $post_diffusion_list = json_decode(
                    filter_var(
                        $dataReceived['diffusion_list'],
                        FILTER_SANITIZE_STRING
                    )
                );
                $post_email = filter_var(htmlspecialchars_decode($dataReceived['email']), FILTER_SANITIZE_EMAIL);
                $post_fields = json_decode(
                    filter_var(
                        $dataReceived['fields'],
                        FILTER_SANITIZE_STRING
                    )
                );
                $post_folder_id = filter_var($dataReceived['folder'], FILTER_SANITIZE_NUMBER_INT);
                $post_folder_is_personal = filter_var($dataReceived['folder_is_personal'], FILTER_SANITIZE_NUMBER_INT);
                $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
                $post_login = filter_var($dataReceived['login'], FILTER_SANITIZE_STRING);
                $post_password = htmlspecialchars_decode($dataReceived['pw']);
                $post_restricted_to = json_decode(
                    filter_var(
                        $dataReceived['restricted_to'],
                        FILTER_SANITIZE_STRING
                    )
                );
                $post_restricted_to_roles = json_decode(
                    filter_var(
                        $dataReceived['restricted_to_roles'],
                        FILTER_SANITIZE_STRING
                    )
                );
                $post_salt_key_set = isset($_SESSION['user_settings']['session_psk']) === true
                    && empty($_SESSION['user_settings']['session_psk']) === false ? '1' : '0';
                $post_tags = htmlspecialchars_decode($dataReceived['tags']);
                $post_template_id = filter_var($dataReceived['template_id'], FILTER_SANITIZE_NUMBER_INT);
                $post_url = filter_var(htmlspecialchars_decode($dataReceived['url']), FILTER_SANITIZE_URL);
                $post_uploaded_file_id = filter_var($dataReceived['uploaded_file_id'], FILTER_SANITIZE_NUMBER_INT);
                $post_user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
                $post_to_be_deleted_after_date = filter_var($dataReceived['to_be_deleted_after_date'], FILTER_SANITIZE_STRING);
                $post_to_be_deleted_after_x_views = filter_var($dataReceived['to_be_deleted_after_x_views'], FILTER_SANITIZE_NUMBER_INT);

                //-> DO A SET OF CHECKS
                // Perform a check in case of Read-Only user creating an item in his PF
                if ($_SESSION['user_read_only'] === true
                    && (in_array($post_folder_id, $_SESSION['personal_folders']) === false
                    || $post_folder_is_personal !== 1)
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_not_allowed_to_access_this_folder'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Is author authorized to create in this folder
                if (count($_SESSION['list_folders_limited']) > 0) {
                    if (in_array($post_folder_id, array_keys($_SESSION['list_folders_limited'])) === false
                        && in_array($post_folder_id, $_SESSION['groupes_visibles']) === false
                        && in_array($post_folder_id, $_SESSION['personal_visible_groups_list']) === false
                    ) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => langHdl('error_not_allowed_to_access_this_folder'),
                            ),
                            'encode'
                        );
                        break;
                    }
                } else {
                    if (in_array($post_folder_id, $_SESSION['groupes_visibles']) === false) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => langHdl('error_not_allowed_to_access_this_folder'),
                            ),
                            'encode'
                        );
                        break;
                    }
                }

                // perform a check in case of Read-Only user creating an item in his PF
                if ($_SESSION['user_read_only'] === true
                    && in_array($post_folder_id, $_SESSION['personal_folders']) === false
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_not_allowed_to_access_this_folder'),
                        ),
                        'encode'
                    );
                    break;
                }

                // is pwd empty?
                if (empty($post_password)
                    && isset($_SESSION['user_settings']['create_item_without_password']) == true
                    && $_SESSION['user_settings']['create_item_without_password'] !== '1'
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('password_cannot_be_empty'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Check length
                if (strlen($post_password) > $SETTINGS['pwd_maximum_length']) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('password_too_long'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Check PSK is set
                if ((int) $post_folder_is_personal === 1
                    && (isset($_SESSION['user_settings']['session_psk']) === false
                    || empty($post_password) === true)
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('provide_your_personal_saltkey'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Need info in DB
                // About special settings
                $dataFolderSettings = DB::queryFirstRow(
                    'SELECT bloquer_creation, bloquer_modification, personal_folder
                    FROM '.prefixTable('nested_tree').' 
                    WHERE id = %i',
                    $post_folder_id
                );
                $itemInfos['personal_folder'] = $dataFolderSettings['personal_folder'];
                if ($itemInfos['personal_folder'] === '1') {
                    $itemInfos['no_complex_check_on_modification'] = 1;
                    $itemInfos['no_complex_check_on_creation'] = 1;
                } else {
                    $itemInfos['no_complex_check_on_modification'] = $dataFolderSettings['bloquer_modification'];
                    $itemInfos['no_complex_check_on_creation'] = $dataFolderSettings['bloquer_creation'];
                }
                // Get folder complexity
                $folderComplexity = DB::queryfirstrow(
                    'SELECT valeur
                    FROM '.prefixTable('misc').'
                    WHERE type = %s AND intitule = %i',
                    'complex',
                    $post_folder_id
                );
                $itemInfos['requested_folder_complexity'] = $folderComplexity['valeur'];

                // Check COMPLEXITY
                if ($post_complexity_level < $itemInfos['requested_folder_complexity']) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_security_level_not_reached'),
                        ),
                        'encode'
                    );
                    break;
                }

                // ./ END

                // check if element doesn't already exist
                $itemExists = 0;
                $newID = '';
                $data = DB::queryfirstrow(
                    'SELECT * FROM '.prefixTable('items').'
                    WHERE label = %s AND inactif = %i',
                    $post_label,
                    0
                );
                $counter = DB::count();
                if ($counter > 0) {
                    $itemExists = 1;
                } else {
                    $itemExists = 0;
                }

                // Manage case where item is personal.
                // In this case, duplication is allowed
                if (isset($SETTINGS['duplicate_item']) === true
                    && $SETTINGS['duplicate_item'] === '0'
                    && $post_salt_key_set === '1'
                    && isset($post_salt_key_set) === true
                    && $post_folder_is_personal === '1'
                    && isset($post_folder_is_personal) === true
                ) {
                    $itemExists = 0;
                }

                if ((isset($SETTINGS['duplicate_item']) === true
                    && $SETTINGS['duplicate_item'] === '0'
                    && (int) $itemExists === 0)
                    ||
                    (isset($SETTINGS['duplicate_item']) === true
                        && $SETTINGS['duplicate_item'] === '1')
                ) {
                    // Handle case where pw is empty
                    // if not allowed then warn user
                    if ((isset($_SESSION['user_settings']['create_item_without_password']) === true
                        && $_SESSION['user_settings']['create_item_without_password'] !== '1'
                        ) ||
                        empty($post_password) === false
                    ) {
                        // NEW ENCRYPTION
                        $cryptedStuff = doDataEncryption($post_password);
                    /*
                    // encrypt PW
                    if ($post_salt_key_set === '1'
                        && isset($post_salt_key_set) === true
                        && $post_folder_is_personal === '1'
                        && isset($post_folder_is_personal) === true
                    ) {
                        $passwd = cryption(
                            $post_password,
                            $_SESSION['user_settings']['session_psk'],
                            'encrypt',
                            $SETTINGS
                        );
                        $restictedTo = $_SESSION['user_id'];
                    } else {
                        $passwd = cryption(
                            $post_password,
                            '',
                            'encrypt',
                            $SETTINGS
                        );
                    }
                    */
                    } else {
                        $cryptedStuff['encrypted'] = '';
                    }

                    $post_password = $cryptedStuff['encrypted'];

                    // ADD item
                    DB::insert(
                        prefixTable('items'),
                        array(
                            'label' => $post_label,
                            'description' => $post_description,
                            'pw' => $post_password,
                            'pw_iv' => '',
                            'email' => $post_email,
                            'url' => $post_url,
                            'id_tree' => $post_folder_id,
                            'login' => $post_login,
                            'inactif' => 0,
                            'restricted_to' => empty($post_restricted_to) === true || count($post_restricted_to) === 0 ?
                                '' : implode(';', $post_restricted_to),
                            'perso' => (isset($post_folder_is_personal) === true && (int) $post_folder_is_personal === 1) ?
                                1 : 0,
                            'anyone_can_modify' => (isset($post_anyone_can_modify) === true
                                && $post_anyone_can_modify === 'on') ? 1 : 0,
                            'complexity_level' => $post_complexity_level,
                            'encryption_type' => 'teampass_aes',
                            )
                    );
                    $newID = DB::insertId();

                    // Prepare shareKey for users
                    if ((int) $post_folder_is_personal === 1 && isset($post_folder_is_personal) === true) {
                        // If this is a personal object
                        DB::insert(
                            prefixTable('sharekeys_items'),
                            array(
                                'object_id' => $newID,
                                'user_id' => $_SESSION['user_id'],
                                'share_key' => encryptUserObjectKey($cryptedStuff['objectKey'], $_SESSION['user']['public_key']),
                            )
                        );
                    } else {
                        // This is a public object
                        $users = DB::query(
                            'SELECT id, public_key
                            FROM '.prefixTable('users').'
                            WHERE id NOT IN ("'.OTV_USER_ID.'","'.SSH_USER_ID.'","'.API_USER_ID.'")
                            AND public_key != ""'
                        );
                        foreach ($users as $user) {
                            // Insert in DB the new object key for this item by user
                            DB::insert(
                                prefixTable('sharekeys_items'),
                                array(
                                    'object_id' => $newID,
                                    'user_id' => $user['id'],
                                    'share_key' => encryptUserObjectKey($cryptedStuff['objectKey'], $user['public_key']),
                                )
                            );
                        }
                    }

                    // update fields
                    if (isset($SETTINGS['item_extra_fields']) === true
                        && $SETTINGS['item_extra_fields'] === '1'
                    ) {
                        foreach (explode('_|_', $post_fields) as $field) {
                            $field_data = explode('~~', $field);
                            if (count($field_data) > 1 && empty($field_data[1]) === false) {
                                // should we encrypt the data
                                $dataTmp = DB::queryFirstRow(
                                    'SELECT encrypted_data
                                    FROM '.prefixTable('categories').'
                                    WHERE id = %i',
                                    $field_data[0]
                                );
                                if ($dataTmp['encrypted_data'] === '1') {
                                    $encrypt = cryption(
                                        $field_data[1],
                                        '',
                                        'encrypt',
                                        $SETTINGS
                                    );
                                    $enc_type = 'defuse';
                                } else {
                                    $encrypt['string'] = $field_data[1];
                                    $enc_type = 'not_set';
                                }

                                DB::insert(
                                    prefixTable('categories_items'),
                                    array(
                                        'item_id' => $newID,
                                        'field_id' => $field_data[0],
                                        'data' => $encrypt['string'],
                                        'data_iv' => '',
                                        'encryption_type' => $enc_type,
                                    )
                                );
                            }
                        }
                    }

                    // If template enable, is there a main one selected?
                    if (isset($SETTINGS['item_creation_templates']) === true
                        && $SETTINGS['item_creation_templates'] === '1'
                        && isset($post_template_id) === true
                        && empty($post_template_id) === false
                    ) {
                        DB::queryFirstRow(
                            'SELECT *
                            FROM '.prefixTable('templates').'
                            WHERE item_id = %i',
                            $newID
                        );
                        if (DB::count() === 0) {
                            // store field text
                            DB::insert(
                                prefixTable('templates'),
                                array(
                                    'item_id' => $newID,
                                    'category_id' => $post_template_id,
                                )
                            );
                        } else {
                            // Delete if empty
                            if (empty($post_template_id) === true) {
                                DB::delete(
                                    prefixTable('templates'),
                                    'item_id = %i',
                                    $newID
                                );
                            } else {
                                // Update value
                                DB::update(
                                    prefixTable('templates'),
                                    array(
                                        'category_id' => $post_template_id,
                                    ),
                                    'item_id = %i',
                                    $newID
                                );
                            }
                        }
                    }

                    // If automatic deletion asked
                    if (isset($SETTINGS['enable_delete_after_consultation']) === true
                        && $SETTINGS['enable_delete_after_consultation'] === '1'
                        && null !== $post_to_be_deleted_after_x_views
                        && null !== $post_to_be_deleted_after_date
                    ) {
                        if (empty($post_to_be_deleted_after_date) === false
                            || $post_to_be_deleted_after_x_views > 0
                        ) {
                            // Automatic deletion to be added
                            DB::insert(
                                prefixTable('automatic_del'),
                                array(
                                    'item_id' => $newID,
                                    'del_enabled' => 1,
                                    'del_type' => $post_to_be_deleted_after_x_views > 0 ? 1 : 2, //1 = numeric : 2 = date
                                    'del_value' => $post_to_be_deleted_after_x_views > 0 ? $post_to_be_deleted_after_x_views : dateToStamp($post_to_be_deleted_after_date, $SETTINGS),
                                    )
                            );
                        }
                    }

                    // Get readable list of restriction
                    $listOfRestricted = $oldRestrictionList = '';
                    if (is_array($post_restricted_to) === true
                        && count($post_restricted_to) > 0
                        && isset($SETTINGS['restricted_to']) === true
                        && $SETTINGS['restricted_to'] === '1'
                    ) {
                        foreach ($post_restricted_to as $userRest) {
                            if (empty($userRest) === false) {
                                $dataTmp = DB::queryfirstrow('SELECT login FROM '.prefixTable('users').' WHERE id= %i', $userRest);
                                if (empty($listOfRestricted)) {
                                    $listOfRestricted = $dataTmp['login'];
                                } else {
                                    $listOfRestricted .= ';'.$dataTmp['login'];
                                }
                            }
                        }
                    }
                    if ($data['restricted_to'] !== $post_restricted_to
                        && $SETTINGS['restricted_to'] === '1'
                    ) {
                        if (empty($data['restricted_to']) === false) {
                            foreach (explode(';', $data['restricted_to']) as $userRest) {
                                if (empty($userRest) === false) {
                                    $dataTmp = DB::queryfirstrow('SELECT login FROM '.prefixTable('users').' WHERE id= '.$userRest);
                                    if (empty($oldRestrictionList) === true) {
                                        $oldRestrictionList = $dataTmp['login'];
                                    } else {
                                        $oldRestrictionList .= ';'.$dataTmp['login'];
                                    }
                                }
                            }
                        }
                    }
                    // Manage retriction_to_roles
                    if (is_array($post_restricted_to_roles) === true
                        && count($post_restricted_to_roles) > 0
                        && isset($SETTINGS['restricted_to_roles']) === true
                        && $SETTINGS['restricted_to_roles'] === '1'
                    ) {
                        // add roles for item
                        if (is_array($post_restricted_to_roles) === true
                            && count($post_restricted_to_roles) > 0
                        ) {
                            foreach ($post_restricted_to_roles as $role) {
                                if (count($role) > 1) {
                                    $role = $role[1];
                                } else {
                                    $role = $role[0];
                                }
                                DB::insert(
                                    prefixTable('restriction_to_roles'),
                                    array(
                                        'role_id' => $role,
                                        'item_id' => $post_item_id,
                                        )
                                );
                            }
                        }
                    }

                    // log
                    logItems(
                        $SETTINGS,
                        $newID,
                        $post_label,
                        $_SESSION['user_id'],
                        'at_creation',
                        $_SESSION['login']
                    );

                    // Add tags
                    $tags = explode(' ', $post_tags);
                    foreach ($tags as $tag) {
                        if (empty($tag) === false) {
                            DB::insert(
                                prefixTable('tags'),
                                array(
                                    'item_id' => $newID,
                                    'tag' => strtolower($tag),
                                    )
                            );
                        }
                    }

                    // Check if any files have been added
                    if (empty($post_uploaded_file_id) === false) {
                        $rows = DB::query(
                            'SELECT id
                            FROM '.prefixTable('files').'
                            WHERE id_item = %s',
                            $post_uploaded_file_id
                        );
                        foreach ($rows as $record) {
                            // update item_id in files table
                            DB::update(
                                prefixTable('files'),
                                array(
                                    'id_item' => $newID,
                                    ),
                                'id=%i',
                                $record['id']
                            );
                        }
                    }

                    // Announce by email?
                    if (empty($post_diffusion_list) === false) {
                        // get links url
                        if (empty($SETTINGS['email_server_url'])) {
                            $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                        }

                        // Get path
                        $path = prepareEmaiItemPath(
                            $post_folder_id,
                            $label,
                            $SETTINGS
                        );

                        // send email
                        foreach (explode(';', $post_diffusion_list) as $emailAddress) {
                            if (empty($emailAddress) === false) {
                                // send it
                                sendEmail(
                                    langHdl('email_subject'),
                                    str_replace(
                                        array('#label', '#link'),
                                        array($path, $SETTINGS['email_server_url'].'/index.php?page=items&group='.$post_folder_id.'&id='.$newID.$txt['email_body3']),
                                        langHdl('new_item_email_body')
                                    ),
                                    $emailAddress,
                                    $SETTINGS,
                                    str_replace(
                                        array('#label', '#link'),
                                        array($path, $SETTINGS['email_server_url'].'/index.php?page=items&group='.$post_folder_id.'&id='.$newID.$txt['email_body3']),
                                        langHdl('new_item_email_body')
                                    )
                                );
                            }
                        }
                    }
                } elseif (isset($SETTINGS['duplicate_item']) === true
                    && $SETTINGS['duplicate_item'] === '0'
                    && (int) $itemExists === 1
                ) {
                    // Encrypt data to return
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_item_exists'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Add item to CACHE table if new item has been created
                if (isset($newID) === true) {
                    updateCacheTable(
                        'add_value',
                        $SETTINGS,
                        $newID
                    );
                }

                $arrData = array(
                    'error' => false,
                );
            } else {
                // an error appears on JSON format
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('json_error_format'),
                    ),
                    'encode'
                );
            }

            // Encrypt data to return
            echo prepareExchangedData($arrData, 'encode');
            break;

        /*
        * CASE
        * update an ITEM
        */
        case 'update_item':
            // Check KEY and rights
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

            // init
            $reloadPage = false;
            $returnValues = array();
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            if (count($dataReceived) > 0) {
                // Prepare variables
                $itemInfos = array();
                $post_label = filter_var(($dataReceived['label']), FILTER_SANITIZE_STRING);
                $post_url = filter_var(htmlspecialchars_decode($dataReceived['url']), FILTER_SANITIZE_URL);
                $post_password = $pw = $original_pw = $sentPw = htmlspecialchars_decode($dataReceived['pw']);
                $post_login = filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_STRING);
                $post_tags = htmlspecialchars_decode($dataReceived['tags']);
                $post_email = filter_var(htmlspecialchars_decode($dataReceived['email']), FILTER_SANITIZE_EMAIL);
                $post_template_id = filter_var($dataReceived['template_id'], FILTER_SANITIZE_NUMBER_INT);
                $post_item_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
                $post_anyone_can_modify = filter_var($dataReceived['anyone_can_modify'], FILTER_SANITIZE_NUMBER_INT);
                $post_complexity_level = filter_var($dataReceived['complexity_level'], FILTER_SANITIZE_NUMBER_INT);
                $post_folder_id = filter_var($dataReceived['folder'], FILTER_SANITIZE_NUMBER_INT);
                $post_salt_key_set = isset($_SESSION['user_settings']['session_psk']) === true
                    && empty($_SESSION['user_settings']['session_psk']) === false ? '1' : '0';
                $post_folder_is_personal = filter_var($dataReceived['folder_is_personal'], FILTER_SANITIZE_NUMBER_INT);
                $post_restricted_to = filter_var_array(
                    $dataReceived['restricted_to'],
                    FILTER_SANITIZE_STRING
                );
                $post_restricted_to_roles = filter_var_array(
                    $dataReceived['restricted_to_roles'],
                    FILTER_SANITIZE_STRING
                );
                $post_diffusion_list = filter_var_array(
                    $dataReceived['diffusion_list'],
                    FILTER_SANITIZE_STRING
                );
                $post_to_be_deleted_after_x_views = filter_var(
                    $dataReceived['to_be_deleted_after_x_views'],
                    FILTER_SANITIZE_NUMBER_INT
                );
                $post_to_be_deleted_after_date = filter_var(
                    $dataReceived['to_be_deleted_after_date'],
                    FILTER_SANITIZE_STRING
                );
                $post_fields = (
                    filter_var_array(
                        $dataReceived['fields'],
                        FILTER_SANITIZE_STRING
                    )
                );
                $post_description = ($dataReceived['description']);

                //-> DO A SET OF CHECKS
                // Perform a check in case of Read-Only user creating an item in his PF
                if ($_SESSION['user_read_only'] === true
                    && (in_array($post_folder_id, $_SESSION['personal_folders']) === false
                    || $post_folder_is_personal !== 1)
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_not_allowed_to_access_this_folder'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Check PWD EMPTY
                if (empty($pw) === true
                    && isset($_SESSION['user_settings']['create_item_without_password']) === true
                    && $_SESSION['user_settings']['create_item_without_password'] !== '1'
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_pw'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Need info in DB
                // About special settings
                $dataFolderSettings = DB::queryFirstRow(
                    'SELECT bloquer_creation, bloquer_modification, personal_folder
                    FROM '.prefixTable('nested_tree').' 
                    WHERE id = %i',
                    $post_folder_id
                );
                $itemInfos['personal_folder'] = $dataFolderSettings['personal_folder'];
                if ($itemInfos['personal_folder'] === '1') {
                    $itemInfos['no_complex_check_on_modification'] = 1;
                    $itemInfos['no_complex_check_on_creation'] = 1;
                } else {
                    $itemInfos['no_complex_check_on_modification'] = $dataFolderSettings['bloquer_modification'];
                    $itemInfos['no_complex_check_on_creation'] = $dataFolderSettings['bloquer_creation'];
                }
                // Get folder complexity
                $folderComplexity = DB::queryfirstrow(
                    'SELECT valeur
                    FROM '.prefixTable('misc').'
                    WHERE type = %s AND intitule = %i',
                    'complex',
                    $post_folder_id
                );
                $itemInfos['requested_folder_complexity'] = $folderComplexity['valeur'];

                // Check COMPLEXITY
                if ($post_complexity_level < $itemInfos['requested_folder_complexity']) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_security_level_not_reached'),
                        ),
                        'encode'
                    );
                    break;
                }

                // Check length
                if (strlen($pw) > $SETTINGS['pwd_maximum_length']) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_pw_too_long'),
                        ),
                        'encode'
                    );
                    break;
                }

                // ./ END

                // Get all informations for this item
                $dataItem = DB::queryfirstrow(
                    'SELECT *
                    FROM '.prefixTable('items').' as i
                    INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                    WHERE i.id=%i AND l.action = %s',
                    $post_item_id,
                    'at_creation'
                );
                // check that actual user can access this item
                $restrictionActive = true;
                $restrictedTo = array_filter(explode(';', $dataItem['restricted_to']));
                if (in_array($_SESSION['user_id'], $restrictedTo) === true) {
                    $restrictionActive = false;
                }
                if (empty($dataItem['restricted_to']) === true) {
                    $restrictionActive = false;
                }

                if ((in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) === true
                    && ((int) $dataItem['perso'] === 0
                    || ((int) $dataItem['perso'] === 1
                    && (int) $_SESSION['user_id'] === (int) $dataItem['id_user']))
                    && $restrictionActive === false
                    )
                    ||
                    (
                        isset($SETTINGS['anyone_can_modify']) === true
                        && (int) $SETTINGS['anyone_can_modify'] === 1
                        && (int) $dataItem['anyone_can_modify'] === 1
                        && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) === true
                        || (int) $_SESSION['is_admin'] === 1)
                        && $restrictionActive === false
                    )
                    ||
                    (null !== $post_folder_id
                    && isset($_SESSION['list_restricted_folders_for_items'][$post_folder_id]) === true
                    && in_array($post_id, $_SESSION['list_restricted_folders_for_items'][$post_folder_id]) === true
                    //&& $post_restricted === '1'
                    && $restrictionActive === false)
                ) {
                    // Get existing values
                    $data = DB::queryfirstrow(
                        'SELECT i.id as id, i.label as label, i.description as description, i.pw as pw, i.url as url, i.id_tree as id_tree, i.perso as perso, i.login as login,
                        i.inactif as inactif, i.restricted_to as restricted_to, i.anyone_can_modify as anyone_can_modify, i.email as email, i.notification as notification,
                        u.login as user_login, u.email as user_email
                        FROM '.prefixTable('items').' as i
                        INNER JOIN '.prefixTable('log_items').' as l ON (i.id=l.id_item)
                        INNER JOIN '.prefixTable('users').' as u ON (u.id=l.id_user)
                        WHERE i.id=%i',
                        $post_item_id
                    );

                    // encrypt PW
                    if ((isset($_SESSION['user_settings']['create_item_without_password']) === true
                        && $_SESSION['user_settings']['create_item_without_password'] !== '1')
                        || empty($pw) === false
                    ) {
                        //-----
                        // NEW ENCRYPTION
                        $cryptedStuff = doDataEncryption($post_password);

                        $post_password = $cryptedStuff['encrypted'];
                    } else {
                        $post_password = '';
                    }

                    // ---Manage tags
                    // deleting existing tags for this item
                    DB::delete(
                        prefixTable('tags'),
                        'item_id = %i',
                        $post_item_id
                    );

                    // Add new tags
                    $return_tags = '';
                    $post_tags = explode(' ', $post_tags);
                    foreach ($post_tags as $tag) {
                        if (empty($tag) === false) {
                            // save in DB
                            DB::insert(
                                prefixTable('tags'),
                                array(
                                    'item_id' => $post_item_id,
                                    'tag' => strtolower($tag),
                                )
                            );
                        }
                    }

                    // update item
                    DB::update(
                        prefixTable('items'),
                        array(
                            'label' => $post_label,
                            'description' => $post_description,
                            'pw' => $post_password,
                            'email' => $post_email,
                            'login' => $post_login,
                            'url' => $post_url,
                            'id_tree' => $post_folder_id === 'undefined' ? $dataItem['id_tree'] : $post_folder_id,
                            'restricted_to' => empty($post_restricted_to) === true || count($post_restricted_to) === 0 ? '' : implode(';', $post_restricted_to),
                            'anyone_can_modify' => (int) $post_anyone_can_modify,
                            'complexity_level' => (int) $post_complexity_level,
                            'encryption_type' => 'defuse',
                            'perso' => in_array($post_folder_id, $_SESSION['personal_folders']) === true ? 1 : 0,
                            ),
                        'id=%i',
                        $post_item_id
                    );

                    // Create sharekeys for users
                    if ((int) $post_folder_is_personal === 1
                        && in_array($post_folder_id, $_SESSION['personal_folders']) === true
                    ) {
                        // If this is a personal object
                        DB::insert(
                            prefixTable('sharekeys'),
                            array(
                                'type' => 'item',
                                'object_id' => $post_item_id,
                                'user_id' => $_SESSION['user_id'],
                                'share_key' => encryptUserObjectKey($cryptedStuff['objectKey'], $user['public_key']),
                            )
                        );
                    } else {
                        // This is a public object
                        $users = DB::query(
                            'SELECT id, public_key
                            FROM '.prefixTable('users').'
                            WHERE id NOT IN ("'.OTV_USER_ID.'","'.SSH_USER_ID.'","'.API_USER_ID.'")
                            AND public_key != ""'
                        );
                        foreach ($users as $user) {
                            // Insert in DB the new object key for this item by user
                            DB::insert(
                                prefixTable('sharekeys'),
                                array(
                                    'type' => 'item',
                                    'object_id' => $post_item_id,
                                    'user_id' => $user['id'],
                                    'share_key' => encryptUserObjectKey($cryptedStuff['objectKey'], $user['public_key']),
                                )
                            );
                        }
                    }

                    // update fields
                    if (isset($SETTINGS['item_extra_fields']) === true
                        && $SETTINGS['item_extra_fields'] === '1'
                        && empty($post_fields) === false
                    ) {
                        foreach ($post_fields as $field) {
                            if (empty($field['value']) === false) {
                                //echo $field['id'].' -- '.$field['value'];
                                $dataTmpCat = DB::queryFirstRow(
                                    'SELECT c.title AS title, i.data AS data, i.data_iv AS data_iv,
                                    i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data,
                                    c.masked AS masked
                                    FROM '.prefixTable('categories_items').' AS i
                                    INNER JOIN '.prefixTable('categories').' AS c ON (i.field_id=c.id)
                                    WHERE i.field_id = %i AND i.item_id = %i',
                                    $field['id'],
                                    $post_item_id
                                );
                                // store Field text in DB
                                if (DB::count() === 0) {
                                    // get info about this custom field
                                    $dataTmpCat = DB::queryFirstRow(
                                        'SELECT title, encrypted_data
                                        FROM '.prefixTable('categories').'
                                        WHERE id = %i',
                                        $field['id']
                                    );

                                    // should we encrypt the data
                                    if ($dataTmpCat['encrypted_data'] === '1') {
                                        $encrypt = cryption(
                                            $field['value'],
                                            '',
                                            'encrypt',
                                            $SETTINGS
                                        );
                                        $enc_type = 'defuse';
                                    } else {
                                        $encrypt['string'] = $field['value'];
                                        $enc_type = 'not_set';
                                    }

                                    // store field text
                                    DB::insert(
                                        prefixTable('categories_items'),
                                        array(
                                            'item_id' => $post_item_id,
                                            'field_id' => $field['id'],
                                            'data' => $encrypt['string'],
                                            'data_iv' => '',
                                            'encryption_type' => $enc_type,
                                        )
                                    );

                                    // update LOG
                                    logItems(
                                        $SETTINGS,
                                        $post_item_id,
                                        $post_label,
                                        $_SESSION['user_id'],
                                        'at_modification',
                                        $_SESSION['login'],
                                        'at_field : '.$dataTmpCat['title'].' : '.$field['value']
                                    );
                                } else {
                                    // compare the old and new value
                                    if ($dataTmpCat['encryption_type'] === 'defuse') {
                                        $oldVal = cryption(
                                            $dataTmpCat['data'],
                                            '',
                                            'decrypt',
                                            $SETTINGS
                                        );
                                    } else {
                                        $oldVal['string'] = $dataTmpCat['data'];
                                    }

                                    if ($field['value'] !== $oldVal['string']) {
                                        // should we encrypt the data
                                        if ($dataTmpCat['encrypted_data'] === '1') {
                                            $encrypt = cryption(
                                                $field['value'],
                                                '',
                                                'encrypt',
                                                $SETTINGS
                                            );
                                            $enc_type = 'defuse';
                                        } else {
                                            $encrypt['string'] = $field['value'];
                                            $enc_type = 'not_set';
                                        }

                                        // update value
                                        DB::update(
                                            prefixTable('categories_items'),
                                            array(
                                                'data' => $encrypt['string'],
                                                'data_iv' => '',
                                                'encryption_type' => $enc_type,
                                            ),
                                            'item_id = %i AND field_id = %i',
                                            $post_item_id,
                                            $field['id']
                                        );

                                        // update LOG
                                        logItems(
                                            $SETTINGS,
                                            $post_item_id,
                                            $post_label,
                                            $_SESSION['user_id'],
                                            'at_modification',
                                            $_SESSION['login'],
                                            'at_field : '.$dataTmpCat['title'].' => '.$oldVal['string']
                                        );
                                    }
                                }
                            } else {
                                if (empty($field_data[1])) {
                                    DB::delete(
                                        prefixTable('categories_items'),
                                        'item_id = %i AND field_id = %s',
                                        $post_item_id,
                                        $field['id']
                                    );
                                }
                            }
                        }
                    }

                    // If template enable, is there a main one selected?
                    if (isset($SETTINGS['item_creation_templates']) === true
                        && $SETTINGS['item_creation_templates'] === '1'
                        && isset($post_template_id) === true
                    ) {
                        DB::queryFirstRow(
                            'SELECT *
                            FROM '.prefixTable('templates').'
                            WHERE item_id = %i',
                            $post_item_id
                        );
                        if (DB::count() === 0 && empty($post_template_id) === false) {
                            // store field text
                            DB::insert(
                                prefixTable('templates'),
                                array(
                                    'item_id' => $post_item_id,
                                    'category_id' => $post_template_id,
                                )
                            );
                        } else {
                            // Delete if empty
                            if (empty($post_template_id) === true) {
                                DB::delete(
                                    prefixTable('templates'),
                                    'item_id = %i',
                                    $post_item_id
                                );
                            } else {
                                // Update value
                                DB::update(
                                    prefixTable('templates'),
                                    array(
                                        'category_id' => $post_template_id,
                                    ),
                                    'item_id = %i',
                                    $post_item_id
                                );
                            }
                        }
                    }

                    // Update automatic deletion - Only by the creator of the Item
                    if (isset($SETTINGS['enable_delete_after_consultation']) === true
                        && $SETTINGS['enable_delete_after_consultation'] === '1'
                    ) {
                        // check if elem exists in Table. If not add it or update it.
                        DB::query(
                            'SELECT *
                            FROM '.prefixTable('automatic_del').'
                            WHERE item_id = %i',
                            $post_item_id
                        );

                        if (DB::count() === 0) {
                            // No automatic deletion for this item
                            if (empty($post_to_be_deleted_after_date) === false
                                || $post_to_be_deleted_after_x_views > 0
                            ) {
                                // Automatic deletion to be added
                                DB::insert(
                                    prefixTable('automatic_del'),
                                    array(
                                        'item_id' => $post_item_id,
                                        'del_enabled' => 1,
                                        'del_type' => $post_to_be_deleted_after_x_views > 0 ? 1 : 2, //1 = numeric : 2 = date
                                        'del_value' => $post_to_be_deleted_after_x_views > 0 ? $post_to_be_deleted_after_x_views : dateToStamp($post_to_be_deleted_after_date, $SETTINGS),
                                        )
                                );
                                // update LOG
                                logItems(
                                    $SETTINGS,
                                    $post_item_id,
                                    $post_label,
                                    $_SESSION['user_id'],
                                    'at_modification',
                                    $_SESSION['login'],
                                    'at_automatic_del : '.$post_to_be_deleted_after_x_views > 0 ? $post_to_be_deleted_after_x_views : $post_to_be_deleted_after_date
                                );
                            }
                        } else {
                            // Automatic deletion exists for this item
                            if (empty($post_to_be_deleted_after_date) === false
                                && $post_to_be_deleted_after_x_views > 0
                            ) {
                                // Update automatic deletion
                                DB::update(
                                    prefixTable('automatic_del'),
                                    array(
                                        'del_type' => $post_to_be_deleted_after_x_views > 0 ? 1 : 2, //1 = numeric : 2 = date
                                        'del_value' => $post_to_be_deleted_after_x_views > 0 ? $post_to_be_deleted_after_x_views : dateToStamp($post_to_be_deleted_after_date, $SETTINGS),
                                        ),
                                    'item_id = %i',
                                    $post_item_id
                                );
                            } else {
                                // delete automatic deleteion for this item
                                DB::delete(prefixTable('automatic_del'), 'item_id = %i', $post_item_id);
                            }
                            // update LOG
                            logItems(
                                $SETTINGS,
                                $post_item_id,
                                $post_label,
                                $_SESSION['user_id'],
                                'at_modification',
                                $_SESSION['login'],
                                'at_automatic_del : '.$post_to_be_deleted_after_x_views > 0 ? $post_to_be_deleted_after_x_views : $post_to_be_deleted_after_date
                            );
                        }
                    }

                    // get readable list of restriction
                    $listOfRestricted = $oldRestrictionList = '';
                    if (is_array($post_restricted_to) === true
                        && count($post_restricted_to) > 0
                        && isset($SETTINGS['restricted_to']) === true
                        && $SETTINGS['restricted_to'] === '1'
                    ) {
                        foreach ($post_restricted_to as $userRest) {
                            if (empty($userRest) === false) {
                                $dataTmp = DB::queryfirstrow('SELECT login FROM '.prefixTable('users').' WHERE id= %i', $userRest);
                                if (empty($listOfRestricted)) {
                                    $listOfRestricted = $dataTmp['login'];
                                } else {
                                    $listOfRestricted .= ';'.$dataTmp['login'];
                                }
                            }
                        }
                    }
                    if ($data['restricted_to'] !== $post_restricted_to && $SETTINGS['restricted_to'] === '1') {
                        if (empty($data['restricted_to']) === false) {
                            foreach (explode(';', $data['restricted_to']) as $userRest) {
                                if (empty($userRest) === false) {
                                    $dataTmp = DB::queryfirstrow('SELECT login FROM '.prefixTable('users').' WHERE id= '.$userRest);
                                    if (empty($oldRestrictionList) === true) {
                                        $oldRestrictionList = $dataTmp['login'];
                                    } else {
                                        $oldRestrictionList .= ';'.$dataTmp['login'];
                                    }
                                }
                            }
                        }
                    }
                    // Manage retriction_to_roles
                    if (is_array($post_restricted_to_roles) === true
                        && count($post_restricted_to_roles) > 0
                        && isset($SETTINGS['restricted_to_roles']) === true
                        && $SETTINGS['restricted_to_roles'] === '1'
                    ) {
                        // get values before deleting them
                        $rows = DB::query(
                            'SELECT t.title
                            FROM '.prefixTable('roles_title').' as t
                            INNER JOIN '.prefixTable('restriction_to_roles').' as r ON (t.id=r.role_id)
                            WHERE r.item_id = %i
                            ORDER BY t.title ASC',
                            $post_item_id
                        );
                        foreach ($rows as $record) {
                            if (empty($oldRestrictionList) === true) {
                                $oldRestrictionList = $record['title'];
                            } else {
                                $oldRestrictionList .= ';'.$record['title'];
                            }
                        }
                        // delete previous values
                        DB::delete(prefixTable('restriction_to_roles'), 'item_id = %i', $post_item_id);

                        // add roles for item
                        if (is_array($post_restricted_to_roles) === true
                            && count($post_restricted_to_roles) > 0
                        ) {
                            foreach ($post_restricted_to_roles as $role) {
                                if (count($role) > 1) {
                                    $role = $role[1];
                                } else {
                                    $role = $role[0];
                                }
                                DB::insert(
                                    prefixTable('restriction_to_roles'),
                                    array(
                                        'role_id' => $role,
                                        'item_id' => $post_item_id,
                                        )
                                );
                                $dataTmp = DB::queryfirstrow(
                                    'SELECT title
                                    FROM '.prefixTable('roles_title').'
                                    WHERE id = %i',
                                    $role
                                );
                                if (empty($listOfRestricted)) {
                                    $listOfRestricted = $dataTmp['title'];
                                } else {
                                    $listOfRestricted .= ';'.$dataTmp['title'];
                                }
                            }
                        }
                    }
                    // Update CACHE table
                    updateCacheTable('update_value', $SETTINGS, $post_item_id);

                    // Log all modifications done
                    /*LABEL */
                    if ($data['label'] !== $post_label) {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_label : '.$data['label'].' => '.$post_label
                        );
                    }
                    /*LOGIN */
                    if ($data['login'] !== $post_login) {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_login : '.$data['login'].' => '.$post_login
                        );
                    }
                    /*EMAIL */
                    if ($data['email'] !== $post_email) {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_email : '.$data['email'].' => '.$post_email
                        );
                    }
                    /*URL */
                    if ($data['url'] !== $post_url && $post_url !== 'http://') {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_url : '.$data['url'].' => '.$post_url
                        );
                    }
                    /*DESCRIPTION */
                    if ($data['description'] !== $post_description) {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_description'
                        );
                    }
                    /*FOLDER */
                    if ($data['id_tree'] !== $post_folder_id) {
                        // Get name of folders
                        $dataTmp = DB::query('SELECT title FROM '.prefixTable('nested_tree').' WHERE id IN %li', array($data['id_tree'], $post_folder_id));

                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_category : '.$dataTmp[0]['title'].' => '.$dataTmp[1]['title']
                        );
                        // ask for page reloading
                        $reloadPage = true;
                    }
                    /*PASSWORD */
                    if ((int) $post_salt_key_set === 1
                        && isset($post_salt_key_set) === true
                        && (int) $post_folder_is_personal === 1
                        && isset($post_folder_is_personal) === true
                    ) {
                        $oldPw = $data['pw'];
                        $oldPwClear = cryption(
                            $oldPw,
                            $_SESSION['user_settings']['session_psk'],
                            'decrypt',
                            $SETTINGS
                        );
                    } else {
                        $oldPw = $data['pw'];
                        $oldPwClear = cryption(
                            $oldPw,
                            '',
                            'decrypt',
                            $SETTINGS
                        );
                    }
                    if ($sentPw !== $oldPwClear['string']) {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_pw :'.$oldPw,
                            'defuse'
                        );
                    }
                    /*RESTRICTIONS */
                    if ($data['restricted_to'] !== $listOfRestricted) {
                        logItems(
                            $SETTINGS,
                            $post_item_id,
                            $post_label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_restriction : '.$oldRestrictionList.' => '.$listOfRestricted
                        );
                    }
                    // Reload new values
                    $dataItem = DB::queryfirstrow(
                        'SELECT *
                        FROM '.prefixTable('items').' as i
                        INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                        WHERE i.id = %i AND l.action = %s',
                        $post_item_id,
                        'at_creation'
                    );
                    // Reload History
                    $history = '';
                    $rows = DB::query(
                        'SELECT l.date as date, l.action as action, l.raison as raison, u.login as login
                        FROM '.prefixTable('log_items').' as l
                        LEFT JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
                        WHERE l.action <> %s AND id_item=%s',
                        'at_shown',
                        $post_item_id
                    );
                    foreach ($rows as $record) {
                        $reason = explode(':', $record['raison']);
                        if (count($reason) > 0) {
                            $sentence = date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date']).' - '
                                .$record['login'].' - '.langHdl($record['action']).' - '
                                .(empty($record['raison']) === false ?
                                (count($reason) > 1 ? langHdl(trim($reason[0])).' : '.$reason[1]
                                : langHdl(trim($reason[0]))) : '');
                            if (empty($history)) {
                                $history = $sentence;
                            } else {
                                $history .= '<br />'.$sentence;
                            }
                        }
                    }
                    // decrypt PW
                    if (empty($dataReceived['salt_key'])) {
                        $encrypt = cryption(
                            $dataItem['pw'],
                            '',
                            'encrypt',
                            $SETTINGS
                        );
                    } else {
                        $encrypt = cryption(
                            $dataItem['pw'],
                            $_SESSION['user_settings']['session_psk'],
                            'encrypt',
                            $SETTINGS
                        );
                    }

                    $pw = cleanString($encrypt['string']);
                    // generate 2d key
                    $_SESSION['key_tmp'] = bin2hex(PHP_Crypt::createKey(PHP_Crypt::RAND, 16));

                    // Prepare files listing
                    $files = $filesEdit = '';
                    // launch query
                    $rows = DB::query('SELECT id, name, file, extension FROM '.prefixTable('files').' WHERE id_item=%i', $post_item_id);
                    foreach ($rows as $record) {
                        // get icon image depending on file format
                        $iconImage = fileFormatImage($record['extension']);

                        // If file is an image, then prepare lightbox. If not image, then prepare donwload
                        if (in_array($record['extension'], TP_IMAGE_FILE_EXT)) {
                            $files .= '<i class=\'fa fa-file-image-o\' /></i>&nbsp;<a class="image_dialog" href="#'.$record['id'].'" title="'.$record['name'].'">'.$record['name'].'</a><br />';
                        } else {
                            $files .= '<i class=\'fa fa-file-text-o\' /></i>&nbsp;<a href=\'sources/downloadFile.php?name='.urlencode($record['name']).'&type=sub&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'&fileid='.$record['id'].'\' target=\'_blank\'>'.$record['name'].'</a><br />';
                        }
                        // Prepare list of files for edit dialogbox
                        $filesEdit .= '<span id="span_edit_file_'.$record['id'].'"><span class="fa fa-'.$iconImage.'"></span>&nbsp;<span class="fa fa-eraser tip" style="cursor:pointer;"  onclick="delete_attached_file(\"'.$record['id'].'\")" title="'.langHdl('at_delete').'"></span>&nbsp;'.$record['name'].'</span><br />';
                    }
                    // Send email
                    if (is_array($post_diffusion_list) === true && count($post_diffusion_list) > 0) {
                        foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                            if (empty($emailAddress) === false) {
                                sendEmail(
                                    langHdl('email_subject_item_updated'),
                                    str_replace(
                                        array('#item_label#', '#item_category#', '#item_id#', '#url#'),
                                        array($post_label, $dataReceived['categorie'], $post_item_id, $SETTINGS['cpassman_url']),
                                        langHdl('email_body_item_updated')
                                    ),
                                    $emailAddress,
                                    $SETTINGS,
                                    str_replace('#item_label#', $post_label, langHdl('email_bodyalt_item_updated'))
                                );
                            }
                        }
                    }

                    // send email to user that what to be notified
                    $notification = DB::queryOneColumn(
                        'email',
                        'SELECT *
                        FROM '.prefixTable('notification').' AS n
                        INNER JOIN '.prefixTable('users').' AS u ON (n.user_id = u.id)
                        WHERE n.item_id = %i AND n.user_id != %i',
                        $post_item_id,
                        $_SESSION['user_id']
                    );

                    if (DB::count() > 0) {
                        // Get name of folders
                        $dataTmp = DB::queryFirstRow(
                            'SELECT title 
                            FROM '.prefixTable('nested_tree').' 
                            WHERE id = %i',
                            $post_folder_id
                        );

                        // send email
                        DB::insert(
                            prefixTable('emails'),
                            array(
                                'timestamp' => time(),
                                'subject' => langHdl('email_subject_item_updated'),
                                'body' => str_replace(
                                    array('#item_label#', '#folder_name#', '#item_id#', '#url#', '#name#', '#lastname#'),
                                    array($post_label, $dataTmp['title'], $post_item_id, $SETTINGS['cpassman_url'], $_SESSION['name'], $_SESSION['lastname']),
                                    langHdl('email_body_item_updated')
                                ),
                                'receivers' => implode(',', $notification),
                                'status' => '',
                            )
                        );
                    }

                    // Prepare some stuff to return
                    $arrData = array(
                        'error' => false,
                        'message' => '',
                        );
                } else {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_not_allowed_to_edit_item'),
                            'toto' => '1',
                        ),
                        'encode'
                    );
                    break;
                }
            } else {
                // an error appears on JSON format
                $arrData = array(
                    'error' => true,
                    'message' => 'ERR_JSON_FORMAT',
                );
            }
            // return data
            echo prepareExchangedData($arrData, 'encode');
            break;

        /*".."
          * CASE
          * Copy an Item
        */
        case 'copy_item':
            // Check KEY and rights
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
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare POST variables
            $post_new_label = filter_var($dataReceived['new_label'], FILTER_SANITIZE_STRING);
            $post_source_id = filter_var($dataReceived['source_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_dest_id = filter_var($dataReceived['dest_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_item_id = filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === '1'
                && (in_array($post_source_id, $_SESSION['personal_folders']) === false
                    || in_array($post_dest_id, $_SESSION['personal_folders']) === false)
            ) {
                echo '[{"error" : "not_allowed"}, {"error_text" : "'.langHdl('error_not_allowed_to').'"}]';
                break;
            }

            // Init
            $returnValues = '';
            $pw = '';
            $is_perso = 0;

            if (empty($post_item_id) === false
                && empty($post_dest_id) === false
            ) {
                // load the original record into an array
                $originalRecord = DB::queryfirstrow(
                    'SELECT * FROM '.prefixTable('items').'
                    WHERE id=%i',
                    $post_item_id
                );

                // Check if the folder where this item is, is accessible to the user
                if (in_array($originalRecord['id_tree'], $_SESSION['groupes_visibles']) === false) {
                    $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.langHdl('error_not_allowed_to').'"}]';
                    echo $returnValues;
                    break;
                }

                // Load the destination folder record into an array
                $dataDestination = DB::queryfirstrow(
                    'SELECT personal_folder FROM '.prefixTable('nested_tree').'
                    WHERE id=%i',
                    $post_dest_id
                );

                // previous is personal folder and public one
                if ($originalRecord['perso'] === '1'
                    && $dataDestination['personal_folder'] === '0'
                ) {
                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        'decrypt',
                        $SETTINGS
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        '',
                        'encrypt',
                        $SETTINGS
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // this item is now public
                    $is_perso = 0;
                // previous is public folder and personal one
                } elseif ($originalRecord['perso'] === '0'
                    && $dataDestination['personal_folder'] === '1'
                ) {
                    // check if PSK is set
                    if (isset($_SESSION['user_settings']['session_psk']) === false
                        || empty($_SESSION['user_settings']['session_psk']) === true
                    ) {
                        echo '[{"error" : "no_psk"}, {"error_text" : "'.langHdl('alert_message_personal_sk_missing').'"}]';
                        break;
                    }

                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        '',
                        'decrypt',
                        $SETTINGS
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        'encrypt',
                        $SETTINGS
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // this item is now private
                    $is_perso = 1;
                } elseif ($originalRecord['perso'] === '1'
                    && $dataDestination['personal_folder'] === '1'
                ) {
                    // previous is public folder and personal one
                    // check if PSK is set
                    if (!isset($_SESSION['user_settings']['session_psk']) || empty($_SESSION['user_settings']['session_psk'])) {
                        echo '[{"error" : "no_psk"}, {"error_text" : "'.langHdl('alert_message_personal_sk_missing').'"}]';
                        break;
                    }

                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        'decrypt',
                        $SETTINGS
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        'encrypt',
                        $SETTINGS
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // this item is now private
                    $is_perso = 1;
                } elseif ($originalRecord['perso'] === '0'
                    && $dataDestination['personal_folder'] === '0'
                ) {
                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        '',
                        'decrypt',
                        $SETTINGS
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        '',
                        'encrypt',
                        $SETTINGS
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // is public item
                    $is_perso = 0;
                } else {
                    echo '[{"error" : "case_not_managed"}, {"error_text" : "ERROR - case is not managed"}]';
                    break;
                }

                // insert the new record and get the new auto_increment id
                DB::insert(
                    prefixTable('items'),
                    array(
                        'label' => 'duplicate',
                    )
                );
                $newID = DB::insertId();
                // generate the query to update the new record with the previous values
                $aSet = array();
                foreach ($originalRecord as $key => $value) {
                    if ($key === 'id_tree') {
                        array_push($aSet, array('id_tree' => $post_dest_id));
                    } elseif ($key === 'label') {
                        array_push($aSet, array($key => $post_new_label));
                    } elseif ($key === 'viewed_no') {
                        array_push($aSet, array('viewed_no' => '0'));
                    } elseif ($key === 'pw' && empty($pw) === false) {
                        array_push($aSet, array('pw' => $originalRecord['pw']));
                        array_push($aSet, array('pw_iv' => ''));
                    } elseif ($key === 'perso') {
                        array_push($aSet, array('perso' => $is_perso));
                    } elseif ($key != 'id' && $key != 'key') {
                        array_push($aSet, array($key => $value));
                    }
                }

                DB::update(
                    prefixTable('items'),
                    $aSet,
                    'id = %i',
                    $newID
                );
                // Add attached itms
                $rows = DB::query('SELECT * FROM '.prefixTable('files').' WHERE id_item=%i', $post_item_id);
                foreach ($rows as $record) {
                    // Check if file still exists
                    if (file_exists($SETTINGS['path_to_upload_folder'].DIRECTORY_SEPARATOR.$record['file'])) {
                        // duplicate file
                        $fileRandomId = md5($record['name'].time());
                        copy(
                            $SETTINGS['path_to_upload_folder'].DIRECTORY_SEPARATOR.$record['file'],
                            $SETTINGS['path_to_upload_folder'].DIRECTORY_SEPARATOR.$fileRandomId
                        );

                        // store in DB
                        DB::insert(
                            prefixTable('files'),
                            array(
                                'id_item' => $newID,
                                'name' => $record['name'],
                                'size' => $record['size'],
                                'extension' => $record['extension'],
                                'type' => $record['type'],
                                'file' => $fileRandomId,
                                'status' => $record['status'],
                            )
                        );
                    }
                }

                // Add specific restrictions
                $rows = DB::query('SELECT * FROM '.prefixTable('restriction_to_roles').' WHERE item_id = %i', $post_item_id);
                foreach ($rows as $record) {
                    DB::insert(
                        prefixTable('restriction_to_roles'),
                        array(
                            'item_id' => $newID,
                            'role_id' => $record['role_id'],
                            )
                    );
                }

                // Add Tags
                $rows = DB::query('SELECT * FROM '.prefixTable('tags').' WHERE item_id = %i', $post_item_id);
                foreach ($rows as $record) {
                    DB::insert(
                        prefixTable('tags'),
                        array(
                            'item_id' => $newID,
                            'tag' => $record['tag'],
                            )
                    );
                }

                // Add this duplicate in logs
                logItems(
                    $SETTINGS,
                    $newID,
                    $originalRecord['label'],
                    $_SESSION['user_id'],
                    'at_creation',
                    $_SESSION['login']
                );
                // Add the fact that item has been copied in logs
                logItems(
                    $SETTINGS,
                    $newID,
                    $originalRecord['label'],
                    $_SESSION['user_id'],
                    'at_copy',
                    $_SESSION['login']
                );
                // reload cache table
                require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
                updateCacheTable('reload', $SETTINGS, '');

                $returnValues = '[{"error" : ""} , {"status" : "ok"}, {"new_id" : "'.$newID.'"}]';
            } else {
                // no item
                $returnValues = '[{"error" : "no_item"}, {"error_text" : "No item ID"}]';
            }
            // return data
            echo $returnValues;
            break;

        /*
          * CASE
          * Display informations of selected item
        */
        case 'show_details_item':
            // Check KEY and rights
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

            // Step #1
            $_SESSION['user_settings']['show_step2'] = false;

            // Decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // Init post variables
            $post_id = filter_var(($dataReceived['id']), FILTER_SANITIZE_NUMBER_INT);
            $post_folder_id = filter_var(($dataReceived['folder_id']), FILTER_SANITIZE_NUMBER_INT);
            $post_salt_key_required = filter_var(($dataReceived['salt_key_required']), FILTER_SANITIZE_STRING);
            $post_salt_key_set = isset($_SESSION['user_settings']['session_psk']) === true
                && empty($_SESSION['user_settings']['session_psk']) === false ? '1' : '0';
            $post_expired_item = filter_var(($dataReceived['expired_item']), FILTER_SANITIZE_STRING);
            $post_restricted = filter_var(($dataReceived['restricted']), FILTER_SANITIZE_STRING);
            $post_page = filter_var(($dataReceived['page']), FILTER_SANITIZE_STRING);
            $post_folder_access_level = isset($dataReceived['folder_access_level']) === true ?
                filter_var(($dataReceived['folder_access_level']), FILTER_SANITIZE_STRING)
                :
                '';

            $arrData = array();
            // return ID
            $arrData['id'] = $post_id;
            $arrData['id_user'] = API_USER_ID;
            $arrData['author'] = 'API';

            // Check if item is deleted
            // taking into account that item can be restored.
            // so if restoration timestamp is higher than the deletion one
            // then we can show it
            $item_deleted = DB::queryFirstRow(
                'SELECT *
                FROM '.prefixTable('log_items').'
                WHERE id_item = %i AND action = %s
                ORDER BY date DESC
                LIMIT 0, 1',
                $post_id,
                'at_delete'
            );
            $dataDeleted = DB::count();

            $item_restored = DB::queryFirstRow(
                'SELECT *
                FROM '.prefixTable('log_items').'
                WHERE id_item = %i AND action = %s
                ORDER BY date DESC
                LIMIT 0, 1',
                $post_id,
                'at_restored'
            );

            if ($dataDeleted != 0 && intval($item_deleted['date']) > intval($item_restored['date'])) {
                // This item is deleted => exit
                echo prepareExchangedData(array('show_detail_option' => 2), 'encode');
                break;
            }

            // Get all informations for this item
            $dataItem = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('items').' as i
                INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s',
                $post_id,
                'at_creation'
            );

            // Notification
            DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('notification').'
                WHERE item_id = %i AND user_id = %i',
                $post_id,
                $_SESSION['user_id']
            );
            if (DB::count() > 0) {
                $arrData['notification_status'] = true;
            } else {
                $arrData['notification_status'] = false;
            }

            // Get all USERS infos
            $listRest = array_filter(explode(';', $dataItem['restricted_to']));
            $_SESSION['listNotificationEmails'] = '';
            $listeRestriction = array();

            $user_in_restricted_list_of_item = false;
            $rows = DB::query(
                'SELECT id, login, email, admin, name, lastname
                FROM '.prefixTable('users')
            );
            foreach ($rows as $user) {
                // Get auhtor
                if ($user['id'] === $dataItem['id_user']) {
                    $arrData['author'] = $user['login'];
                    $arrData['author_email'] = $user['email'];
                    $arrData['id_user'] = $dataItem['id_user'];
                }

                // Get restriction list for users
                if (in_array($user['id'], $listRest) === true) {
                    array_push($listeRestriction, $user['id']);
                    if ($_SESSION['user_id'] === $user['id']) {
                        $user_in_restricted_list_of_item = true;
                    }
                }
                // Get notification list for users
                /*if (in_array($user['id'], $arrData['notification_list']) === true) {
                    $_SESSION['listNotificationEmails'] .= $user['email'].',';
                }*/

                // Add Admins to notification list if expected
                if (isset($SETTINGS['enable_email_notification_on_item_shown']) === true
                    && $SETTINGS['enable_email_notification_on_item_shown'] === '1'
                    && $user['admin'] === '1'
                ) {
                    $_SESSION['listNotificationEmails'] .= $user['email'].',';
                }
            }

            // manage case of API user
            if ($dataItem['id_user'] === API_USER_ID) {
                $arrData['author'] = 'API ['.$dataItem['description'].']';
                $arrData['id_user'] = API_USER_ID;
                $arrData['author_email'] = '';
                $arrData['notification_status'] = false;
            }

            // Get all tags for this item
            $tags = array();
            $rows = DB::query(
                'SELECT tag
                FROM '.prefixTable('tags').'
                WHERE item_id = %i',
                $post_id
            );
            foreach ($rows as $record) {
                array_push($tags, $record['tag']);
            }

            // TODO -> improve this check
            // check that actual user can access this item
            $restrictionActive = true;
            $restrictedTo = array_filter(explode(';', $dataItem['restricted_to']));
            if (in_array($_SESSION['user_id'], $restrictedTo) === true) {
                $restrictionActive = false;
            }
            if (empty($dataItem['restricted_to'])) {
                $restrictionActive = false;
            }

            // Check if user has a role that is accepted
            $rows_tmp = DB::query(
                'SELECT role_id
                FROM '.prefixTable('restriction_to_roles').'
                WHERE item_id=%i',
                $post_id
            );
            foreach ($rows_tmp as $rec_tmp) {
                if (in_array($rec_tmp['role_id'], explode(';', $_SESSION['fonction_id']))) {
                    $restrictionActive = false;
                }
            }

            // Uncrypt PW
            // Get the object key for the user
            $userKey = DB::queryFirstRow(
                'SELECT share_key
                FROM '.prefixTable('sharekeys_items').'
                WHERE user_id = %i AND object_id = %i',
                $_SESSION['user_id'],
                $post_id
            );
            if (DB::count() === 0) {
                // No share key found
                $pw = '';
            } else {
                $pw = doDataDecryption(
                    $dataItem['pw'],
                    decryptUserObjectKey($userKey['share_key'], $_SESSION['user']['private_key'])
                );
            }

            // check if item is expired
            if (null !== $post_expired_item
                && $post_expired_item === '1'
            ) {
                $item_is_expired = true;
            } else {
                $item_is_expired = false;
            }

           // echo $dataItem['id_tree']." ;; ";
            //print_r($_SESSION['groupes_visibles']);

            // check user is admin
            if ($_SESSION['user_admin'] === '1'
                && $dataItem['perso'] != 1
                && (null !== TP_ADMIN_FULL_RIGHT && TP_ADMIN_FULL_RIGHT === true)
                || null === TP_ADMIN_FULL_RIGHT
            ) {
                $arrData['show_details'] = 0;
            // Check if actual USER can see this ITEM
            } elseif ((
                (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] === '1') && ($dataItem['perso'] === '0' || ($dataItem['perso'] === '1' && in_array($dataItem['id_tree'], $_SESSION['personal_folders']) === true)) && $restrictionActive === false)
                ||
                (isset($SETTINGS['anyone_can_modify']) && $SETTINGS['anyone_can_modify'] === '1' && $dataItem['anyone_can_modify'] === '1' && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] === '1') && $restrictionActive === false)
                ||
                (null !== $post_folder_id
                    && isset($_SESSION['list_restricted_folders_for_items'][$post_folder_id])
                    && in_array($post_id, $_SESSION['list_restricted_folders_for_items'][$post_folder_id])
                    && $post_restricted === '1'
                    && $user_in_restricted_list_of_item === true)
                ||
                (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === '1'
                    && $restrictionActive === false
                )
            ) {
                // Allow show details
                $arrData['show_details'] = 1;

                // Regarding user's roles, what type of modification is allowed?
                /*$rows = DB::query(
                    'SELECT r.type
                    FROM '.prefixTable('roles_values').' AS r
                    WHERE r.folder_id = %i AND r.role_id IN %ls',
                    $dataItem['id_tree'],
                    $_SESSION['groupes_visibles']
                );
                foreach ($rows as $record) {
                    // TODO
                }*/

                // Display menu icon for deleting if user is allowed
                if ($dataItem['id_user'] == $_SESSION['user_id']
                    || (int) $_SESSION['is_admin'] === 1
                    || ((int) $_SESSION['user_manager'] === 1 && (int) $SETTINGS['manager_edit'] === 1)
                    || (int) $dataItem['anyone_can_modify'] === 1
                    || in_array($dataItem['id_tree'], $_SESSION['list_folders_editable_by_role']) === true
                    || in_array($_SESSION['user_id'], $restrictedTo) === true
                    //|| count($restrictedTo) === 0
                    || (int) $post_folder_access_level === 0
                ) {
                    $arrData['user_can_modify'] = 1;
                    $user_is_allowed_to_modify = true;
                } else {
                    $arrData['user_can_modify'] = 0;
                    $user_is_allowed_to_modify = false;
                }

                // Get restriction list for roles
                $listRestrictionRoles = array();
                if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === '1') {
                    // Add restriction if item is restricted to roles
                    $rows = DB::query(
                        'SELECT t.title, t.id
                        FROM '.prefixTable('roles_title').' AS t
                        INNER JOIN '.prefixTable('restriction_to_roles').' AS r ON (t.id=r.role_id)
                        WHERE r.item_id = %i
                        ORDER BY t.title ASC',
                        $post_id
                    );
                    foreach ($rows as $record) {
                        if (!in_array($record['title'], $listRestrictionRoles)) {
                            array_push($listRestrictionRoles, $record['id']);
                        }
                    }
                }
                // Check if any KB is linked to this item
                if (isset($SETTINGS['enable_kb']) && $SETTINGS['enable_kb'] === '1') {
                    $tmp = array();
                    $rows = DB::query(
                        'SELECT k.label, k.id
                        FROM '.prefixTable('kb_items').' as i
                        INNER JOIN '.prefixTable('kb').' as k ON (i.kb_id=k.id)
                        WHERE i.item_id = %i
                        ORDER BY k.label ASC',
                        $post_id
                    );
                    foreach ($rows as $record) {
                        array_push(
                            $tmp,
                            array(
                                'id' => $record['id'],
                                'label' => $record['label'],
                            )
                        );
                    }
                    $arrData['links_to_kbs'] = $tmp;
                }
                // Prepare DIalogBox data
                if ($item_is_expired === false) {
                    $arrData['show_detail_option'] = 0;
                } elseif ($user_is_allowed_to_modify === true && $item_is_expired === true) {
                    $arrData['show_detail_option'] = 1;
                } else {
                    $arrData['show_detail_option'] = 2;
                }

                $arrData['label'] = htmlspecialchars_decode($dataItem['label'], ENT_QUOTES);
                $arrData['pw'] = $pw;
                $arrData['email'] = (empty($dataItem['email']) === true || $dataItem['email'] === null) ? '' : $dataItem['email'];
                $arrData['url'] = empty($dataItem['url']) === true ? '' : $dataItem['url'];
                $arrData['folder'] = $dataItem['id_tree'];

                $arrData['description'] = $dataItem['description'];
                $arrData['login'] = htmlspecialchars_decode(str_replace(array('"'), array('&quot;'), $dataItem['login']), ENT_QUOTES);
                $arrData['id_restricted_to'] = $listeRestriction;
                $arrData['id_restricted_to_roles'] = $listRestrictionRoles;
                $arrData['tags'] = $tags;
                $arrData['folder'] = $dataItem['id_tree'];

                if (isset($SETTINGS['enable_server_password_change'])
                    && $SETTINGS['enable_server_password_change'] === '1') {
                    $arrData['auto_update_pwd_frequency'] = $dataItem['auto_update_pwd_frequency'];
                } else {
                    $arrData['auto_update_pwd_frequency'] = '0';
                }

                $arrData['anyone_can_modify'] = (int) $dataItem['anyone_can_modify'];

                // Add the fact that item has been viewed in logs
                if (isset($SETTINGS['log_accessed']) && $SETTINGS['log_accessed'] === '1') {
                    logItems(
                        $SETTINGS,
                        $post_id,
                        $dataItem['label'],
                        $_SESSION['user_id'],
                        'at_shown',
                        $_SESSION['login']
                    );
                }

                // statistics
                DB::update(
                    prefixTable('items'),
                    array(
                        'viewed_no' => $dataItem['viewed_no'] + 1,
                    ),
                    'id = %i',
                    $post_id
                );
                $arrData['viewed_no'] = $dataItem['viewed_no'] + 1;

                // get fields
                $fieldsTmp = array();
                $arrCatList = $template_id = '';
                if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                    // get list of associated Categories
                    $arrCatList = array();
                    $rows_tmp = DB::query(
                        'SELECT id_category
                        FROM '.prefixTable('categories_folders').'
                        WHERE id_folder=%i',
                        $post_folder_id
                    );
                    if (DB::count() > 0) {
                        foreach ($rows_tmp as $row) {
                            array_push($arrCatList, $row['id_category']);
                        }

                        // get fields for this Item
                        $rows_tmp = DB::query(
                            'SELECT i.id AS id, i.field_id AS field_id, i.data AS data, i.item_id AS item_id,
                            i.encryption_type AS encryption_type, c.encrypted_data, c.parent_id AS parent_id,
                            c.type as field_type, c.masked AS field_masked, c.role_visibility AS role_visibility
                            FROM '.prefixTable('categories_items').' AS i
                            INNER JOIN '.prefixTable('categories').' AS c ON (i.field_id=c.id)
                            WHERE i.item_id=%i AND c.parent_id IN %ls',
                            $post_id,
                            $arrCatList
                        );
                        foreach ($rows_tmp as $row) {
                            // Uncrypt data
                            // Get the object key for the user
                            $userKey = DB::queryFirstRow(
                                'SELECT share_key
                                FROM '.prefixTable('sharekeys_fields').'
                                WHERE user_id = %i AND object_id = %i',
                                $_SESSION['user_id'],
                                $row['id']
                            );
                            if (DB::count() === 0) {
                                // Not encrypted
                                $fieldText = $fieldText['string'];
                            } else {
                                $fieldText = doDataDecryption(
                                    $row['data'],
                                    decryptUserObjectKey(
                                        $userKey['share_key'],
                                        $_SESSION['user']['private_key']
                                    )
                                );
                            }

                            // Manage textarea string
                            if ($row['field_type'] === 'textarea') {
                                $fieldText = $fieldText;
                            }

                            // build returned list of Fields text
                            array_push(
                                $fieldsTmp,
                                array(
                                    'id' => $row['field_id'],
                                    'value' => $fieldText,
                                    'parent_id' => $row['parent_id'],
                                    'type' => $row['field_type'],
                                    'masked' => $row['field_masked'],
                                )
                            );
                        }
                    }
                }

                // Now get the selected template (if exists)
                if (isset($SETTINGS['item_creation_templates']) && $SETTINGS['item_creation_templates'] === '1') {
                    $rows_tmp = DB::queryfirstrow(
                        'SELECT category_id
                        FROM '.prefixTable('templates').'
                        WHERE item_id = %i',
                        $post_id
                    );
                    if (DB::count() > 0) {
                        $template_id = $rows_tmp['category_id'];
                    }
                }
                //}
                $arrData['fields'] = $fieldsTmp;
                $arrData['categories'] = $arrCatList;
                $arrData['template_id'] = $template_id;
                $arrData['to_be_deleted'] = '';

                // Manage user restriction
                if (null !== $post_restricted) {
                    $arrData['restricted'] = $post_restricted;
                } else {
                    $arrData['restricted'] = '';
                }
                // Decrement the number before being deleted
                if (isset($SETTINGS['enable_delete_after_consultation']) && $SETTINGS['enable_delete_after_consultation'] === '1') {
                    // Is the Item to be deleted?
                    $dataDelete = DB::queryfirstrow(
                        'SELECT * 
                        FROM '.prefixTable('automatic_del').'
                        WHERE item_id = %i',
                        $post_id
                    );
                    if (DB::count() > 0) {
                        $arrData['to_be_deleted'] = $dataDelete['del_value'];
                    }
                    $arrData['to_be_deleted_type'] = $dataDelete['del_type'];

                    // Now delete if required
                    if ($dataDelete['del_enabled'] === '1' || intval($arrData['id_user']) !== intval($_SESSION['user_id'])) {
                        if ($dataDelete['del_type'] === '1' && $dataDelete['del_value'] >= 1) {
                            // decrease counter
                            DB::update(
                                prefixTable('automatic_del'),
                                array(
                                    'del_value' => $dataDelete['del_value'] - 1,
                                    ),
                                'item_id = %i',
                                $post_id
                            );
                            // store value
                            $arrData['to_be_deleted'] = $dataDelete['del_value'] - 1;
                        } elseif ($dataDelete['del_type'] === '1' && $dataDelete['del_value'] <= 1 || $dataDelete['del_type'] === '2' && $dataDelete['del_value'] < time()
                        ) {
                            $arrData['show_details'] = 0;
                            // delete item
                            DB::delete(prefixTable('automatic_del'), 'item_id = %i', $post_id);
                            // make inactive object
                            DB::update(
                                prefixTable('items'),
                                array(
                                    'inactif' => '1',
                                    ),
                                'id = %i',
                                $post_id
                            );
                            // log
                            logItems(
                                $SETTINGS,
                                $post_id,
                                $dataItem['label'],
                                $_SESSION['user_id'],
                                'at_delete',
                                $_SESSION['login'],
                                'at_automatically_deleted'
                            );
                            $arrData['to_be_deleted'] = 0;
                        } elseif ($dataDelete['del_type'] === '2') {
                            $arrData['to_be_deleted'] = date($SETTINGS['date_format'], $dataDelete['del_value']);
                        }
                    } else {
                        $arrData['to_be_deleted'] = '';
                    }
                } else {
                    $arrData['to_be_deleted'] = 'not_enabled';
                }
            } else {
                $arrData['show_details'] = 0;
                // get readable list of restriction
                $listOfRestricted = '';
                if (empty($dataItem['restricted_to']) === false) {
                    foreach (explode(';', $dataItem['restricted_to']) as $userRest) {
                        if (empty($userRest) === false) {
                            $dataTmp = DB::queryfirstrow('SELECT login FROM '.prefixTable('users').' WHERE id= '.$userRest);
                            if (empty($listOfRestricted)) {
                                $listOfRestricted = $dataTmp['login'];
                            } else {
                                $listOfRestricted .= ';'.$dataTmp['login'];
                            }
                        }
                    }
                }
                $arrData['restricted_to'] = $listOfRestricted;
                $arrData['notification_list'] = '';
                $arrData['notification_status'] = '';
            }

            // Set a timestamp
            $arrData['timestamp'] = time();

            // Set temporary session variable to allow step2
            $_SESSION['user_settings']['show_step2'] = true;

            // Error
            $arrData['error'] = '';

            // Encrypt data to return
            echo prepareExchangedData($arrData, 'encode');
            break;

        /*
           * CASE
           * Display History of the selected Item
        */
        case 'showDetailsStep2':
            // Is this query expected (must be run after a step1 and not standalone)
            if ($_SESSION['user_settings']['show_step2'] !== true) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.langHdl('error_not_allowed_to').'"}]';
                echo prepareExchangedData($returnValues, 'encode');
                break;
            }
            $returnArray = array();

            // Load item data
            $dataItem = DB::queryFirstRow(
                'SELECT i.*, n.title AS folder_title
                FROM '.prefixTable('items').' AS i
                INNER JOIN '.prefixTable('nested_tree').' AS n ON (i.id_tree = n.id)
                WHERE i.id = %i',
                $post_id
            );

            // check that actual user can access this item
            $restrictionActive = true;
            $restrictedTo = array_filter(explode(';', $dataItem['restricted_to']));
            if (in_array($_SESSION['user_id'], $restrictedTo)) {
                $restrictionActive = false;
            }
            if (empty($dataItem['restricted_to'])) {
                $restrictionActive = false;
            }

            // Check if user has a role that is accepted
            $rows_tmp = DB::query(
                'SELECT role_id
                FROM '.prefixTable('restriction_to_roles').'
                WHERE item_id=%i',
                $post_id
            );
            foreach ($rows_tmp as $rec_tmp) {
                if (in_array($rec_tmp['role_id'], explode(';', $_SESSION['fonction_id']))) {
                    $restrictionActive = false;
                }
            }

            // check user is admin
            if ($_SESSION['user_admin'] === '1'
                && $dataItem['perso'] !== 1
                && (null !== TP_ADMIN_FULL_RIGHT && TP_ADMIN_FULL_RIGHT === true)
                || null == TP_ADMIN_FULL_RIGHT
            ) {
                $returnArray['show_details'] = 0;
            // Check if actual USER can see this ITEM
            } elseif ((
                (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || (int) $_SESSION['is_admin'] === 1) && ((int) $dataItem['perso'] === 0 || ((int) $dataItem['perso'] === 1 && in_array($dataItem['id_tree'], $_SESSION['personal_folders']) === true)) && $restrictionActive === false)
                ||
                (isset($SETTINGS['anyone_can_modify']) && $SETTINGS['anyone_can_modify'] === '1' && (int) $dataItem['anyone_can_modify'] === 1 && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || (int) $_SESSION['is_admin'] === 1) && $restrictionActive === false)
                ||
                (null !== $post_folder_id
                    && isset($_SESSION['list_restricted_folders_for_items'][$post_folder_id])
                    && in_array($post_id, $_SESSION['list_restricted_folders_for_items'][$post_folder_id])
                    && $post_restricted === '1'
                    && $user_in_restricted_list_of_item === true)
                ||
                (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === '1'
                    && $restrictionActive === false
                )
            ) {
                /*
                // GET Audit trail
                $history = array();
                $historyOfPws = array();
                $rows = DB::query(
                    'SELECT l.date as date, l.action as action, l.raison as raison, u.login as login, l.raison_iv AS raison_iv
                    FROM '.prefixTable('log_items').' as l
                    LEFT JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
                    WHERE id_item=%i AND action <> %s
                    ORDER BY date ASC',
                    $post_id,
                    'at_shown'
                );
                foreach ($rows as $record) {
                    $reason = explode(':', $record['raison']);
                    if ($record['action'] === 'at_modification' && $reason[0] === 'at_pw ') {
                        // check if item is PF
                        if ($dataItem['perso'] !== 1) {
                            $pw = cryption(
                                $reason[1],
                                '',
                                'decrypt',
                                $SETTINGS
                            );
                        } else {
                            if (isset($_SESSION['user_settings']['session_psk']) === true) {
                                $pw = cryption(
                                    $reason[1],
                                    $_SESSION['user_settings']['session_psk'],
                                    'decrypt',
                                    $SETTINGS
                                );
                            } else {
                                $pw['string'] = '';
                            }
                        }

                        $reason[1] = $pw['string'];
                        // if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
                        if (isUTF8($reason[1]) === false || is_array($reason[1]) === true) {
                            $reason[1] = '';
                        }
                    }
                    // imported via API
                    if (empty($record['login'])) {
                        $record['login'] = langHdl('imported_via_api');
                    }

                    if (empty($reason[1]) === false && in_array($record['action'], array('at_copy', 'at_creation', 'at_manual', 'at_modification', 'at_delete', 'at_restored')) === true) {
                        if (trim($reason[0]) === 'at_pw' && empty($reason[1]) === false) {
                            array_push($historyOfPws, $reason[1]);
                        }
                    }
                }
                $returnArray['historyOfPassword'] = $historyOfPws;
                */

                // generate 2d key
                $_SESSION['key_tmp'] = bin2hex(PHP_Crypt::createKey(PHP_Crypt::RAND, 16));

                // Prepare files listing
                $attachments = array();
                $files = $filesEdit = '';
                // launch query
                $rows = DB::query(
                    'SELECT id, name, file, extension, size
                    FROM '.prefixTable('files').'
                    WHERE id_item=%i',
                    $post_id
                );
                foreach ($rows as $record) {
                    array_push(
                        $attachments,
                        array(
                            'icon' => fileFormatImage($record['extension']),
                            'filename' => basename($record['name'], '.'.$record['extension']),
                            'extension' => $record['extension'],
                            'size' => formatSizeUnits($record['size']),
                            'is_image' => in_array($record['extension'], TP_IMAGE_FILE_EXT) === true ? 1 : 0,
                            'id' => $record['id'],
                            'key' => $_SESSION['key_tmp'],
                        )
                    );
                }
                $returnArray['attachments'] = $attachments;
                // display lists
                //$filesEdit = str_replace('"', '&quot;', $filesEdit);
                //$files_id = $files;

                // disable add bookmark if alread bookmarked
                $returnArray['favourite'] = in_array($post_id, $_SESSION['favourites']) === true ? 1 : 0;

                // Add this item to the latests list
                if (isset($_SESSION['latest_items']) && isset($SETTINGS['max_latest_items']) && !in_array($dataItem['id'], $_SESSION['latest_items'])) {
                    if (count($_SESSION['latest_items']) >= $SETTINGS['max_latest_items']) {
                        array_pop($_SESSION['latest_items']); //delete last items
                    }
                    array_unshift($_SESSION['latest_items'], $dataItem['id']);
                    // update DB
                    DB::update(
                        prefixTable('users'),
                        array(
                            'latest_items' => implode(';', $_SESSION['latest_items']),
                            ),
                        'id='.$_SESSION['user_id']
                    );
                }

                // get list of roles
                $listOptionsForUsers = array();
                $listOptionsForRoles = array();
                $rows = DB::query(
                    'SELECT r.role_id AS role_id, t.title AS title
                    FROM '.prefixTable('roles_values').' AS r
                    INNER JOIN '.prefixTable('roles_title').' AS t ON (r.role_id = t.id)
                    WHERE r.folder_id = %i',
                    $dataItem['id_tree']
                );
                foreach ($rows as $record) {
                    array_push(
                        $listOptionsForRoles,
                        array(
                            'id' => $record['role_id'],
                            'title' => $record['title'],
                        )
                    );
                    $rows2 = DB::query(
                        'SELECT id, login, fonction_id, email, name, lastname
                        FROM '.prefixTable('users').'
                        WHERE fonction_id LIKE %s',
                        '%'.$record['role_id'].'%'
                    );
                    foreach ($rows2 as $record2) {
                        foreach (explode(';', $record2['fonction_id']) as $role) {
                            if (array_search($record2['id'], array_column($listOptionsForUsers, 'id')) === false
                                && $role === $record['role_id']
                            ) {
                                array_push(
                                    $listOptionsForUsers,
                                    array(
                                        'id' => $record2['id'],
                                        'login' => $record2['login'],
                                        'name' => $record2['name'].' '.$record2['lastname'],
                                        'email' => $record2['email'],
                                    )
                                );
                            }
                        }
                    }
                }
                //print_r($listOptionsForUsers);
                $returnArray['users_list'] = $listOptionsForUsers;
                $returnArray['roles_list'] = $listOptionsForRoles;

                // send notification if enabled
                if (isset($SETTINGS['enable_email_notification_on_item_shown']) === true && $SETTINGS['enable_email_notification_on_item_shown'] === '1') {
                    // Get path
                    $arbo = $tree->getPath($dataItem['id_tree'], true);
                    $path = '';
                    foreach ($arbo as $elem) {
                        if (empty($path) === true) {
                            $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES).' ';
                        } else {
                            $path .= '&#8594; '.htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                        }
                    }
                    // Build text to show user
                    if (empty($path) === true) {
                        $path = addslashes($dataItem['label']);
                    } else {
                        $path = addslashes($dataItem['label']).' ('.$path.')';
                    }

                    // send back infos
                    DB::insert(
                        prefixTable('emails'),
                        array(
                            'timestamp' => time(),
                            'subject' => langHdl('email_on_open_notification_subject'),
                            'body' => str_replace(
                                array('#tp_user#', '#tp_item#', '#tp_link#'),
                                array(
                                    addslashes($_SESSION['login']),
                                    $path,
                                    $SETTINGS['cpassman_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.$dataItem['id'],
                                ),
                                langHdl('email_on_open_notification_mail')
                            ),
                            'receivers' => $_SESSION['listNotificationEmails'],
                            'status' => '',
                        )
                    );
                }

                // has this item a change proposal
                DB::query('SELECT * FROM '.prefixTable('items_change').' WHERE item_id = %i', $post_id);
                $returnArray['has_change_proposal'] = DB::count();

                // Setting
                $returnArray['setting_restricted_to_roles'] = isset($SETTINGS['restricted_to_roles']) === true
                    && $SETTINGS['restricted_to_roles'] === '1' ? 1 : 0;

                $_SESSION['user_settings']['show_step2'] = false;

                echo prepareExchangedData(
                    $returnArray,
                    'encode'
                );
            }
            break;

        /*
         * CASE
         * Delete an item
        */
        case 'delete_item':
            // Check KEY and rights
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
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare POST variables
            $post_label = filter_var($dataReceived['label'], FILTER_SANITIZE_STRING);
            $post_folder_id = filter_var($dataReceived['folder_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_item_id = filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === true
                && in_array($post_label, $_SESSION['personal_folders']) === false
            ) {
                echo prepareExchangedData(array('error' => 'ERR_FOLDER_NOT_ALLOWED'), 'encode');
                break;
            }

            // Check that user can access this item
            $granted = accessToItemIsGranted($post_item_id);
            if ($granted !== true) {
                echo prepareExchangedData(array('error' => $granted), 'encode');
                break;
            }

            // Load item data
            $data = DB::queryFirstRow(
                'SELECT id_tree
                FROM '.prefixTable('items').'
                WHERE id = %i',
                $post_item_id
            );

            // delete item consists in disabling it
            DB::update(
                prefixTable('items'),
                array(
                    'inactif' => '1',
                    ),
                'id = %i',
                $post_item_id
            );
            // log
            logItems(
                $SETTINGS,
                $post_item_id,
                $post_label,
                $_SESSION['user_id'],
                'at_delete',
                $_SESSION['login']
            );
            // Update CACHE table
            updateCacheTable('delete_value', $SETTINGS, $post_item_id);

            echo prepareExchangedData(array('error' => ''), 'encode');
            break;

        /*
        * CASE
        * Update a Group
        */
        case 'update_folder':
            // Check KEY and rights
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

            // Prepare variables
            $title = filter_var(htmlspecialchars_decode($dataReceived['title'], ENT_QUOTES), FILTER_SANITIZE_STRING);
            $post_folder_id = filter_var(htmlspecialchars_decode($dataReceived['folder']), FILTER_SANITIZE_NUMBER_INT);

            // Check if user is allowed to access this folder
            if (!in_array($post_folder_id, $_SESSION['groupes_visibles'])) {
                echo '[{"error" : "'.langHdl('error_not_allowed_to').'"}]';
                break;
            }

            // Check if title doesn't contains html codes
            if (preg_match_all('|<[^>]+>(.*)</[^>]+>|U', $title, $out)) {
                echo '[ { "error" : "'.langHdl('error_html_codes').'" } ]';
                break;
            }
            // check that title is not numeric
            if (is_numeric($title) === true) {
                echo '[{"error" : "ERR_TITLE_ONLY_WITH_NUMBERS"}]';
                break;
            }

            // Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($SETTINGS['duplicate_folder']) && $SETTINGS['duplicate_folder'] === '0') {
                $data = DB::queryFirstRow('SELECT id, title FROM '.prefixTable('nested_tree').' WHERE title = %s', $title);
                if (empty($data['id']) === false && $dataReceived['folder'] != $data['id']) {
                    echo '[ { "error" : "'.langHdl('error_group_exist').'" } ]';
                    break;
                }
            }

            // query on folder
            $data = DB::queryfirstrow(
                'SELECT parent_id, personal_folder
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_folder_id
            );

            // check if complexity level is good
            // if manager or admin don't care
            if ($_SESSION['is_admin'] != 1 && $_SESSION['user_manager'] != 1 && $data['personal_folder'] === '0') {
                $data = DB::queryfirstrow(
                    'SELECT valeur
                    FROM '.prefixTable('misc').'
                    WHERE intitule = %i AND type = %s',
                    $data['parent_id'],
                    'complex'
                );
                if (intval($dataReceived['complexity']) < intval($data['valeur'])) {
                    echo '[ { "error" : "'.langHdl('error_folder_complexity_lower_than_top_folder').' [<b>'.TP_PW_COMPLEXITY[$data['valeur']][1].'</b>]"} ]';
                    break;
                }
            }

            // update Folders table
            $tmp = DB::queryFirstRow(
                'SELECT title, parent_id, personal_folder FROM '.prefixTable('nested_tree').' WHERE id = %i',
                $dataReceived['folder']
            );
            if ($tmp['parent_id'] != 0 || $tmp['title'] != $_SESSION['user_id'] || $tmp['personal_folder'] != 1) {
                DB::update(
                    prefixTable('nested_tree'),
                    array(
                        'title' => $title,
                        ),
                    'id=%s',
                    $post_folder_id
                );
                // update complixity value
                DB::update(
                    prefixTable('misc'),
                    array(
                        'valeur' => $dataReceived['complexity'],
                        ),
                    'intitule = %s AND type = %s',
                    $post_folder_id,
                    'complex'
                );
                // rebuild fuild tree folder
                $tree->rebuild();
            }
            // send data
            echo '[{"error" : ""}]';
            break;

        /*
        * CASE
        * Move a Group including sub-folders
        */
        case 'move_folder':
            // Check KEY and rights
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
            $post_source_folder_id = filter_var(htmlspecialchars_decode($dataReceived['source_folder_id']), FILTER_SANITIZE_NUMBER_INT);
            $post_target_folder_id = filter_var(htmlspecialchars_decode($dataReceived['target_folder_id']), FILTER_SANITIZE_NUMBER_INT);

            // Check that user can access this folder
            if ((
                    in_array($post_source_folder_id, $_SESSION['groupes_visibles']) === false ||
                  in_array($post_target_folder_id, $_SESSION['groupes_visibles']) === false) &&
                  (
                        $post_target_folder_id === '0' &&
                      isset($SETTINGS['can_create_root_folder']) === true && $SETTINGS['can_create_root_folder'] === '1'
                    )
            ) {
                $returnValues = '[{"error" : "'.langHdl('error_not_allowed_to').'"}]';
                echo $returnValues;
                break;
            }

            $tmp_source = DB::queryFirstRow(
                'SELECT title, parent_id, personal_folder
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_source_folder_id
            );

            $tmp_target = DB::queryFirstRow(
                'SELECT title, parent_id, personal_folder
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_target_folder_id
            );

            // check if target is not a child of source
            if ($tree->isChildOf($post_target_folder_id, $post_source_folder_id) === true) {
                $returnValues = '[{"error" : "'.langHdl('error_not_allowed_to').'"}]';
                echo $returnValues;
                break;
            }

            // check if source or target folder is PF. If Yes, then cancel operation
            if ($tmp_source['personal_folder'] === '1' || $tmp_target['personal_folder'] === '1') {
                $returnValues = '[{"error" : "'.langHdl('error_not_allowed_to').'"}]';
                echo $returnValues;
                break;
            }

            // check if source or target folder is PF. If Yes, then cancel operation
            if ($tmp_source['title'] === $_SESSION['user_id'] || $tmp_target['title'] === $_SESSION['user_id']) {
                $returnValues = '[{"error" : "'.langHdl('error_not_allowed_to').'"}]';
                echo $returnValues;
                break;
            }

            // moving SOURCE folder
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'parent_id' => $post_target_folder_id,
                    ),
                'id=%s',
                $post_source_folder_id
            );
            $tree->rebuild();

            // send data
            echo '[{"error" : ""}]';
            break;

        /*
        * CASE
        * Store hierarchic position of Group
        */
        case 'save_position':
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'parent_id' => $post_destination,
                    ),
                'id = %i',
                $post_source
            );
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();
            break;

        /*
        * CASE
        * List items of a group
        */
        case 'do_items_list_in_folder':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.str_replace('"', '\"', langHdl('error_not_allowed_to')).'"}]';
                echo prepareExchangedData($returnValues, 'encode');
                break;
            }

            // Prepare POST variables
            $post_restricted = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_NUMBER_INT);
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_nb_items_to_display_once = filter_input(INPUT_POST, 'nb_items_to_display_once', FILTER_SANITIZE_NUMBER_INT);

            $arboHtml = $html = '';
            $arr_arbo = [];
            $folderIsPf = false;
            $showError = 0;
            $itemsIDList = $rights = $returnedData = $uniqueLoadData = $html_json = array();
            // Build query limits
            if (empty($post_start) === true) {
                $start = 0;
            } else {
                $start = $post_start;
            }

            // to do only on 1st iteration
            if ((int) $start === 0) {
                // Prepare tree
                $arbo = $tree->getPath($post_id, true);
                foreach ($arbo as $elem) {
                    if ($elem->title == $_SESSION['user_id'] && $elem->nlevel === '1') {
                        $elem->title = $_SESSION['login'];
                        $folderIsPf = true;
                    }
                    // Store path elements
                    array_push(
                        $arr_arbo,
                        array(
                            'id' => $elem->id,
                            'title' => htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES),
                            'visible' => in_array($elem->id, $_SESSION['groupes_visibles']) ? 1 : 0,
                        )
                    );
                }
                $uniqueLoadData['path'] = $arr_arbo;

                // store last folder accessed in cookie
                setcookie(
                    'jstree_select',
                    $post_id,
                    time() + TP_ONE_DAY_SECONDS * $SETTINGS['personal_saltkey_cookie_duration'],
                    '/'
                );

                // CHeck if roles have 'allow_pw_change' set to true
                $forceItemEditPrivilege = false;
                foreach ($_SESSION['user_roles'] as $role) {
                    $roleQ = DB::queryfirstrow(
                        'SELECT allow_pw_change
                        FROM '.prefixTable('roles_title').'
                        WHERE id = %i',
                        $role
                    );
                    if ((int) $roleQ['allow_pw_change'] === 1) {
                        $forceItemEditPrivilege = true;
                        break;
                    }
                }

                // check role access on this folder (get the most restrictive) (2.1.23)
                $accessLevel = 2;
                $arrTmp = [];
                foreach ($_SESSION['user_roles'] as $role) {
                    $access = DB::queryFirstRow(
                        'SELECT type FROM '.prefixTable('roles_values').' WHERE role_id = %i AND folder_id = %i',
                        $role,
                        $post_id
                    );
                    if ($access['type'] === 'R') {
                        array_push($arrTmp, 1);
                    } elseif ($access['type'] === 'W') {
                        array_push($arrTmp, 0);
                    } elseif ($access['type'] === 'ND'
                        || ($forceItemEditPrivilege === true && $access['type'] === 'NDNE')
                    ) {
                        array_push($arrTmp, 2);
                    } elseif ($access['type'] === 'NE') {
                        array_push($arrTmp, 1);
                    } elseif ($access['type'] === 'NDNE') {
                        array_push($arrTmp, 1);
                    } else {
                        // Ensure to give access Right if allowed folder
                        if (in_array($post_id, $_SESSION['groupes_visibles']) === true) {
                            array_push($arrTmp, 0);
                        } else {
                            array_push($arrTmp, 3);
                        }
                    }
                }
                $accessLevel = min($arrTmp);
                $uniqueLoadData['accessLevel'] = $accessLevel;

                /*
                // check if this folder is a PF. If yes check if saltket is set
                if ((!isset($_SESSION['user_settings']['encrypted_psk']) || empty($_SESSION['user_settings']['encrypted_psk'])) && $folderIsPf === true) {
                    $showError = 'is_pf_but_no_saltkey';
                }
                */
                $uniqueLoadData['showError'] = $showError;

                // check if items exist
                $where = new WhereClause('and');
                if (null !== $post_restricted && (int) $post_restricted === 1 && empty($_SESSION['list_folders_limited'][$post_id]) === false) {
                    $counter = count($_SESSION['list_folders_limited'][$post_id]);
                    $uniqueLoadData['counter'] = $counter;
                // check if this folder is visible
                } elseif (!in_array(
                    $post_id,
                    array_merge(
                        $_SESSION['groupes_visibles'],
                        @array_keys($_SESSION['list_restricted_folders_for_items']),
                        @array_keys($_SESSION['list_folders_limited'])
                    )
                )) {
                    echo prepareExchangedData(
                        array(
                            'error' => 'not_authorized',
                            'arborescence' => $arr_arbo,
                        ),
                        'encode'
                    );
                    break;
                } else {
                    DB::query(
                        'SELECT *
                        FROM '.prefixTable('items').'
                        WHERE inactif = %i',
                        0
                    );
                    $counter = DB::count();
                    $uniqueLoadData['counter'] = $counter;
                }

                /*
                // Identify if it is a personal folder
                if (in_array($post_id, $_SESSION['personal_visible_groups'])) {
                    $findPfGroup = 1;
                } else {
                    $findPfGroup = '';
                }
                $uniqueLoadData['findPfGroup'] = $findPfGroup;
                */

                // Get folder complexity
                $folderComplexity = DB::queryFirstRow(
                    'SELECT valeur FROM '.prefixTable('misc').' WHERE type = %s AND intitule = %i',
                    'complex',
                    $post_id
                );
                $folderComplexity = $folderComplexity['valeur'];
                $uniqueLoadData['folderComplexity'] = $folderComplexity;

                // Has this folder some categories to be displayed?
                $categoriesStructure = array();
                if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                    $folderRow = DB::query(
                        'SELECT id_category
                        FROM '.prefixTable('categories_folders').'
                        WHERE id_folder = %i',
                        $post_id
                    );
                    foreach ($folderRow as $category) {
                        array_push(
                            $categoriesStructure,
                            $category['id_category']
                        );
                    }
                }
                $uniqueLoadData['categoriesStructure'] = $categoriesStructure;

                /*$categoriesStructure = array();
                if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                    $folderRow = DB::query(
                        'SELECT f.id_category, c.title AS title
                        FROM '.prefixTable('categories_folders').' AS f
                        INNER JOIN '.prefixTable('categories').' AS c ON (c.id = f.id_category)
                        WHERE f.id_folder = %i',
                        $post_id
                    );
                    foreach ($folderRow as $category) {
                        $arrFields = array();
                        // Get each category definition with fields
                        $categoryRow = DB::query(
                            "SELECT *
                            FROM ".prefixTable("categories")."
                            WHERE parent_id=%i
                            ORDER BY `order` ASC",
                            $category['id_category']
                        );

                        if (DB::count() > 0) {
                            foreach ($categoryRow as $field) {
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
                                            $field['id'],
                                            $field['title'],
                                            $field['encrypted_data'],
                                            $field['type'],
                                            $field['masked'],
                                            $field['is_mandatory']
                                        )
                                    );
                                }
                            }
                        }

                        // store the categories
                        array_push(
                            $categoriesStructure,
                            array(
                                $category['id_category'],
                                $category['title'],
                                $arrFields
                            )
                        );
                    }
                }
                $uniqueLoadData['categoriesStructure'] = $categoriesStructure;
                */

                // is this folder a personal one
                $folder_is_personal = in_array($post_id, $_SESSION['personal_folders']);
                $uniqueLoadData['folder_is_personal'] = $folder_is_personal;

                $folder_is_in_personal = in_array($post_id, array_merge($_SESSION['personal_visible_groups'], $_SESSION['personal_folders']));
                $uniqueLoadData['folder_is_in_personal'] = $folder_is_in_personal;

                if (isset($_SESSION['list_folders_editable_by_role'])) {
                    $list_folders_editable_by_role = in_array($post_id, $_SESSION['list_folders_editable_by_role']);
                } else {
                    $list_folders_editable_by_role = '';
                }
                $uniqueLoadData['list_folders_editable_by_role'] = $list_folders_editable_by_role;
            } else {
                // get preloaded data
                $uniqueLoadData = json_decode(
                    filter_input(INPUT_POST, 'uniqueLoadData', FILTER_UNSAFE_RAW),
                    true
                );

                // initialize main variables
                $showError = $uniqueLoadData['showError'];
                $accessLevel = $uniqueLoadData['accessLevel'];
                $counter = $uniqueLoadData['counter'];
                //$findPfGroup = $uniqueLoadData['findPfGroup'];
                $counter_full = $uniqueLoadData['counter_full'];
                $categoriesStructure = $uniqueLoadData['categoriesStructure'];
                $folderComplexity = $uniqueLoadData['folderComplexity'];
                //$arboHtml = $uniqueLoadData['arboHtml'];
                $folder_is_personal = $uniqueLoadData['folder_is_personal'];
                $folder_is_in_personal = $uniqueLoadData['folder_is_in_personal'];
                $list_folders_editable_by_role = $uniqueLoadData['list_folders_editable_by_role'];
            }

            // prepare query WHere conditions
            $where = new WhereClause('and');
            if (null !== $post_restricted && (int) $post_restricted === 1 && empty($_SESSION['list_folders_limited'][$post_id]) === false) {
                $where->add('i.id IN %ls', $_SESSION['list_folders_limited'][$post_id]);
            } else {
                $where->add('i.id_tree=%i', $post_id);
            }

            // build the HTML for this set of Items
            if ($counter > 0 && empty($showError)) {
                // init variables
                $init_personal_folder = false;
                $expired_item = false;
                $limited_to_items = '';

                // List all ITEMS
                if ($folderIsPf === false) {
                    $where->add('i.inactif=%i', 0);
                    $where->add('l.date=%l', '(SELECT date FROM '.prefixTable('log_items')." WHERE action IN ('at_creation', 'at_modification') AND id_item=i.id ORDER BY date DESC LIMIT 1)");
                    if (empty($limited_to_items) === false) {
                        $where->add('i.id IN %ls', explode(',', $limited_to_items));
                    }

                    $query_limit = ' LIMIT '.
                        $start.','.
                        $post_nb_items_to_display_once;

                    $rows = DB::query(
                        'SELECT i.id AS id, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                        MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                        MIN(i.anyone_can_modify) AS anyone_can_modify, l.date AS date, i.id_tree AS tree_id,
                        MIN(n.renewal_period) AS renewal_period,
                        MIN(l.action) AS log_action, l.id_user AS log_user
                        FROM '.prefixTable('items').' AS i
                        INNER JOIN '.prefixTable('nested_tree').' AS n ON (i.id_tree = n.id)
                        INNER JOIN '.prefixTable('log_items').' AS l ON (i.id = l.id_item)
                        WHERE %l
                        GROUP BY i.id, l.date, l.id_user, l.action
                        ORDER BY i.label ASC, l.date DESC'.$query_limit,
                        $where
                    );
                } else {
                    $post_nb_items_to_display_once = 'max';
                    $where->add('i.inactif=%i', 0);

                    $rows = DB::query(
                        'SELECT i.id AS id, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                        MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                        MIN(i.anyone_can_modify) AS anyone_can_modify,l.date AS date, i.id_tree AS tree_id,
                        MIN(n.renewal_period) AS renewal_period,
                        MIN(l.action) AS log_action, l.id_user AS log_user
                        FROM '.prefixTable('items').' AS i
                        INNER JOIN '.prefixTable('nested_tree').' AS n ON (i.id_tree = n.id)
                        INNER JOIN '.prefixTable('log_items').' AS l ON (i.id = l.id_item)
                        WHERE %l
                        GROUP BY i.id, l.date, l.id_user, l.action
                        ORDER BY i.label ASC, l.date DESC',
                        $where
                    );
                }

                $idManaged = '';
                $i = 0;
                $arr_items_html = array();

                foreach ($rows as $record) {
                    // exclude all results except the first one returned by query
                    if (empty($idManaged) === true || $idManaged !== $record['id']) {
                        // Fix a bug on Personal Item creation - field `perso` must be set to `1`
                        if ((int) $record['perso'] !== 1 && (int) $folder_is_personal === 1) {
                            DB::update(
                                prefixTable('items'),
                                array(
                                    'perso' => 1,
                                ),
                                'id=%i',
                                $record['id']
                            );
                            $record['perso'] = 1;
                        }

                        // Does this item has restriction to groups of users?
                        $item_is_restricted_to_role = false;
                        DB::queryfirstrow(
                            'SELECT role_id
                            FROM '.prefixTable('restriction_to_roles').'
                            WHERE item_id = %i',
                            $record['id']
                        );
                        if (DB::count() > 0) {
                            $item_is_restricted_to_role = true;
                        }

                        // Has this item a restriction to Groups of Users
                        $user_is_included_in_role = false;
                        $roles = DB::query(
                            'SELECT role_id
                            FROM '.prefixTable('restriction_to_roles').'
                            WHERE item_id = %i AND role_id IN %ls',
                            $record['id'],
                            $_SESSION['user_roles']
                        );
                        if (DB::count() > 0) {
                            $user_is_included_in_role = true;
                        }

                        // Get Expiration date
                        $expired_item = 0;
                        if ($SETTINGS['activate_expiration'] === '1'
                            && $record['renewal_period'] > 0
                            && ($record['date'] + ($record['renewal_period'] * TP_ONE_MONTH_SECONDS)) < time()
                        ) {
                            $expired_item = 1;
                        }
                        // Init
                        $html_json[$record['id']]['expired'] = (int) $expired_item;
                        $html_json[$record['id']]['item_id'] = (int) $record['id'];
                        $html_json[$record['id']]['tree_id'] = (int) $record['tree_id'];
                        $html_json[$record['id']]['label'] = strip_tags($record['label']);
                        if (isset($SETTINGS['show_description']) === true && $SETTINGS['show_description'] === '1') {
                            $html_json[$record['id']]['desc'] = strip_tags((explode('<br>', $record['description'])[0]));
                        } else {
                            $html_json[$record['id']]['desc'] = '';
                        }
                        $html_json[$record['id']]['login'] = $record['login'];
                        $html_json[$record['id']]['anyone_can_modify'] = (int) $record['anyone_can_modify'];
                        $html_json[$record['id']]['is_result_of_search'] = 0;
                        $html_json[$record['id']]['is_favourited'] = in_array($record['id'], $_SESSION['favourites']) === true ? 1 : 0;

                        /*************** */
                        $showItem = 0;
                        // Possible values:
                        // 0 -> no access to item
                        // 10 -> appears in list but no view
                        // 20 -> can view without edit (no copy) or move
                        // 30 -> can view without edit (no copy) but can move
                        // 40 -> can edit but not move
                        // 50 -> can edit and move
                        $itemIsPersonal = false;

                        // Let's identify the rights belonging to this ITEM
                        if ((int) $record['perso'] === 1
                            && $record['log_action'] === 'at_creation'
                            && $record['log_user'] === $_SESSION['user_id']
                            && (int) $folder_is_in_personal === 1
                            && (int) $folder_is_personal === 1
                        ) {
                            // Case 1 - Is this item personal and user its owner?
                            // If yes then allow
                            // If no then continue
                            $itemIsPersonal = true;
                            $right = 70;

                        // ----- END CASE 1 -----
                        } elseif (((isset($_SESSION['user_manager']) === true && (int) $_SESSION['user_manager'] === 1)
                            || (isset($_SESSION['user_can_manage_all_users']) === true && (int) $_SESSION['user_can_manage_all_users'] === 1))
                            && (isset($SETTINGS['manager_edit']) === true && (int) $SETTINGS['manager_edit'] === 1)
                            && $record['perso'] !== 1
                        ) {
                            // Case 2 - Is user manager and option "manager_edit" set to true?
                            // Allow all rights
                            $right = 70;

                        // ----- END CASE 2 -----
                        } elseif ((int) $record['anyone_can_modify'] === 1
                            && $record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] !== 1
                        ) {
                            // Case 3 - Has this item the setting "anyone can modify" set to true?
                            // Allow all rights
                            $right = 70;

                        // ----- END CASE 3 -----
                        } elseif (empty($record['restricted_to']) === false
                            && in_array($_SESSION['user_id'], explode(';', $record['restricted_to'])) === true
                            && $record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] !== 1
                        ) {
                            // Case 4 - Is this item limited to Users? Is current user in this list?
                            // Allow all rights
                            $right = 70;

                        // ----- END CASE 4 -----
                        } elseif ($user_is_included_in_role === true
                            && $record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] !== 1
                        ) {
                            // Case 5 - Is this item limited to group of users? Is current user in one of those groups?
                            // Allow all rights
                            $right = 60;

                        // ----- END CASE 5 -----
                        } elseif ($record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] === 1
                        ) {
                            // Case 6 - Is user readonly?
                            // Allow limited rights
                            $right = 10;

                        // ----- END CASE 6 -----
                        } elseif ($record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] === 1
                        ) {
                            // Case 7 - Is user readonly?
                            // Allow limited rights
                            $right = 10;

                        // ----- END CASE 7 -----
                        } elseif ($record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] === 1
                        ) {
                            // Case 8 - Is user allowed to access?
                            // Allow rights
                            $right = 10;

                        // ----- END CASE 8 -----
                        } elseif (((empty($record['restricted_to']) === false
                            && in_array($_SESSION['user_id'], explode(';', $record['restricted_to'])) === false)
                            || ($user_is_included_in_role === false && $item_is_restricted_to_role === true))
                            && $record['perso'] !== 1
                            && (int) $_SESSION['user_read_only'] !== 1
                        ) {
                            // Case 9 - Is this item limited to Users or Groups? Is current user in this list?
                            // If no then Allow none
                            $right = 10;

                        // ----- END CASE 9 -----
                        } else {
                            // Define the access based upon setting on folder
                            // 0 -> no access to item
                            // 10 -> appears in list but no view
                            // 20 -> can view without edit (no copy) or move or delete
                            // 30 -> can view without edit (no copy) or delete but can move
                            // 40 -> can edit but not move and not delete
                            // 50 -> can edit and delete but not move
                            // 60 -> can edit and move but not delete
                            // 70 -> can edit and move
                            if ($accessLevel === 0) {
                                $right = 70;
                            } elseif ($accessLevel === 1) {
                                $right = 20;
                            } elseif ($accessLevel === 2) {
                                $right = 60;
                            } elseif ($accessLevel === 3) {
                                $right = 70;
                            } else {
                                $right = 10;
                            }
                        }

                        // Now finalize the data to send back
                        $html_json[$record['id']]['rights'] = $right;
                        $html_json[$record['id']]['perso'] = 'fa-tag mi-red';
                        $html_json[$record['id']]['sk'] = $itemIsPersonal === true ? 1 : 0;
                        $html_json[$record['id']]['display'] = $right > 0 ? 1 : 0;
                        $html_json[$record['id']]['open_edit'] = in_array($right, array(40, 50, 60, 70)) === true ? 1 : 0;
                        $html_json[$record['id']]['canMove'] = in_array($right, array(30, 60, 70)) === true ? 1 : 0;

                        //*************** */

                        // Build array with items
                        array_push(
                            $itemsIDList,
                            array(
                                'id' => (int) $record['id'],
                                //'display' => $displayItem,
                                'edit' => $html_json[$record['id']]['open_edit'],
                            )
                        );

                        ++$i;
                    }
                    $idManaged = $record['id'];
                }

                $rights = recupDroitCreationSansComplexite($post_id);
            }

            // DELETE - 2.1.19 - AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
            // count
            if ((int) $start === 0) {
                DB::query(
                    'SELECT i.id
                    FROM '.prefixTable('items').' as i
                    INNER JOIN '.prefixTable('nested_tree').' as n ON (i.id_tree = n.id)
                    INNER JOIN '.prefixTable('log_items').' as l ON (i.id = l.id_item)
                    WHERE %l
                    ORDER BY i.label ASC, l.date DESC',
                    $where
                );
                $counter_full = DB::count();
                $uniqueLoadData['counter_full'] = $counter_full;
            }

            // Check list to be continued status
            if ($post_nb_items_to_display_once !== 'max' && ($post_nb_items_to_display_once + $start) < $counter_full) {
                $listToBeContinued = 'yes';
            } else {
                $listToBeContinued = 'end';
            }

            // Prepare returned values
            $returnValues = array(
                'html_json' => $html_json,
                //'folder_requests_psk' => $findPfGroup,
                'arborescence' => $arr_arbo,
                'array_items' => $itemsIDList,
                'error' => $showError,
                //'saltkey_is_required' => $folderIsPf === true ? 1 : 0,
                'show_clipboard_small_icons' => isset($SETTINGS['copy_to_clipboard_small_icons']) && $SETTINGS['copy_to_clipboard_small_icons'] === '1' ? 1 : 0,
                'next_start' => intval($post_nb_items_to_display_once) + intval($start),
                'list_to_be_continued' => $listToBeContinued,
                'items_count' => $counter,
                'counter_full' => $counter_full,
                'folder_complexity' => $folderComplexity,
                'categoriesStructure' => $categoriesStructure,
                'access_level' => $accessLevel,
                'IsPersonalFolder' => $folderIsPf === true ? 1 : 0,
                'uniqueLoadData' => json_encode($uniqueLoadData),
            );
            // Check if $rights is not null
            if (count($rights) > 0) {
                $returnValues = array_merge($returnValues, $rights);
            }

            // Encrypt data to return
            echo prepareExchangedData($returnValues, 'encode');

            break;

        /*
        *
        *
        */
        case 'show_item_password':
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

            // Prepare POST variables
            $post_item_id = filter_input(INPUT_POST, 'item_id', FILTER_SANITIZE_NUMBER_INT);

            // Run query
            $dataItem = DB::queryfirstrow(
                'SELECT i.pw AS pw, s.share_key AS share_key
                FROM '.prefixTable('items').' AS i
                INNER JOIN '.prefixTable('sharekeys_items').' AS s ON (s.object_id = i.id)
                WHERE user_id = %i AND i.id = %i',
                $_SESSION['user_id'],
                $post_item_id
            );

            // Uncrypt PW
            if (DB::count() === 0) {
                // No share key found
                $pw = '';
            } else {
                $pw = doDataDecryption(
                    $dataItem['pw'],
                    decryptUserObjectKey(
                        $dataItem['share_key'],
                        $_SESSION['user']['private_key']
                    )
                );
            }

            $returnValues = array(
                'error' => false,
                'password' => $pw,
                'password_error' => '',
            );

            // Encrypt data to return
            echo prepareExchangedData($returnValues, 'encode');
            break;

        /*
        * CASE
        * Get complexity level of a group
        */
        case 'get_complixity_level':
            // Prepare POST variables
            $post_groupe = filter_input(INPUT_POST, 'groupe', FILTER_SANITIZE_STRING);
            $post_context = filter_input(INPUT_POST, 'context', FILTER_SANITIZE_STRING);

            // get some info about ITEM
            if (null !== $post_item_id && empty($post_item_id) === false) {
                $dataItem = DB::queryfirstrow(
                    'SELECT perso, anyone_can_modify
                    FROM '.prefixTable('items').'
                    WHERE id=%i',
                    $post_item_id
                );

                // is user allowed to access this folder - readonly
                if (null !== $post_groupe && empty($post_groupe) === false) {
                    if (in_array($post_groupe, $_SESSION['read_only_folders']) === true
                        || in_array($post_groupe, $_SESSION['groupes_visibles']) === false
                    ) {
                        // check if this item can be modified by anyone
                        if (isset($SETTINGS['anyone_can_modify']) && $SETTINGS['anyone_can_modify'] === '1') {
                            if ($dataItem['anyone_can_modify'] != 1) {
                                // else return not authorized
                                $returnValues = array(
                                    'error' => 'user_is_readonly',
                                    'message' => langHdl('error_not_allowed_to'),
                                );
                                echo prepareExchangedData($returnValues, 'encode');
                                break;
                            }
                        } else {
                            // else return not authorized
                            $returnValues = array(
                                'error' => 'user_is_readonly',
                                'message' => langHdl('error_not_allowed_to'),
                            );
                            echo prepareExchangedData($returnValues, 'encode');
                            break;
                        }
                    }
                }

                // Lock Item (if already locked), go back and warn
                $dataTmp = DB::queryFirstRow('SELECT timestamp, user_id FROM '.prefixTable('items_edition').' WHERE item_id = %i', $post_item_id);

                // If token is taken for this Item and delay is passed then delete it.
                if (isset($SETTINGS['delay_item_edition']) &&
                    $SETTINGS['delay_item_edition'] > 0 && empty($dataTmp['timestamp']) === false &&
                    round(abs(time() - $dataTmp['timestamp']) / 60, 2) > $SETTINGS['delay_item_edition']
                ) {
                    DB::delete(prefixTable('items_edition'), 'item_id = %i', $post_item_id);
                    //reload the previous data
                    $dataTmp = DB::queryFirstRow(
                        'SELECT timestamp, user_id FROM '.prefixTable('items_edition').' WHERE item_id = %i',
                        $post_item_id
                    );
                }

                // If edition by same user (and token not freed before for any reason, then update timestamp)
                if (empty($dataTmp['timestamp']) === false && $dataTmp['user_id'] == $_SESSION['user_id']) {
                    DB::update(
                        prefixTable('items_edition'),
                        array(
                            'timestamp' => time(),
                        ),
                        'user_id = %i AND item_id = %i',
                        $_SESSION['user_id'],
                        $post_item_id
                    );
                // If no token for this Item, then initialize one
                } elseif (empty($dataTmp[0])) {
                    DB::insert(
                        prefixTable('items_edition'),
                        array(
                            'timestamp' => time(),
                            'item_id' => $post_item_id,
                            'user_id' => $_SESSION['user_id'],
                        )
                    );
                // Edition not possible
                } else {
                    $returnValues = array(
                        'error' => 'no_edition_possible',
                        'message' => langHdl('error_no_edition_possible_locked'),
                    );
                    echo prepareExchangedData($returnValues, 'encode');
                    break;
                }
            }

            // do query on this folder
            $data_this_folder = DB::queryFirstRow(
                'SELECT id, personal_folder, title
                FROM '.prefixTable('nested_tree').'
                WHERE id = %s',
                $post_groupe
            );

            // check if user can perform this action
            if (null !== $post_context
                && empty($post_context) === false
            ) {
                if ($post_context === 'create_folder'
                    || $post_context === 'edit_folder'
                    || $post_context === 'delete_folder'
                    || $post_context === 'copy_folder'
                ) {
                    if ($_SESSION['is_admin'] !== '1'
                        && ($_SESSION['user_manager'] !== '1')
                        && (
                            isset($SETTINGS['enable_user_can_create_folders'])
                           && $SETTINGS['enable_user_can_create_folders'] !== '1'
                        )
                        && (
                            $data_this_folder['personal_folder'] !== '1' && $data_this_folder['title'] !== $_SESSION['user_id']
                        )   // take into consideration if this is a personal folder
                    ) {
                        $returnValues = array(
                            'error' => 'no_folder_creation_possible',
                            'message' => langHdl('error_not_allowed_to'),
                        );
                        echo prepareExchangedData($returnValues, 'encode');
                        break;
                    }
                }
            }

            // Get required Complexity for this Folder
            $visibilite = '';
            $data = DB::queryFirstRow(
                'SELECT m.valeur, n.personal_folder
                FROM '.prefixTable('misc').' AS m
                INNER JOIN '.prefixTable('nested_tree').' AS n ON (m.intitule = n.id)
                WHERE type=%s AND intitule = %s',
                'complex',
                $post_groupe
            );

            if (isset($data['valeur']) === true && (empty($data['valeur']) === false || $data['valeur'] === '0')) {
                $complexity = TP_PW_COMPLEXITY[$data['valeur']][1];
                $folder_is_personal = $data['personal_folder'];

                // Prepare Item actual visibility (what Users/Roles can see it)
                $rows = DB::query(
                    'SELECT t.title
                    FROM '.prefixTable('roles_values').' as v
                    INNER JOIN '.prefixTable('roles_title').' as t ON (v.role_id = t.id)
                    WHERE v.folder_id = %i
                    GROUP BY title',
                    $post_groupe
                );
                foreach ($rows as $record) {
                    if (empty($visibilite)) {
                        $visibilite = $record['title'];
                    } else {
                        $visibilite .= ' - '.$record['title'];
                    }
                }
            } else {
                $complexity = langHdl('not_defined');

                // if not defined, then previous query failed and personal_folder is null
                // do new query to know if current folder is pf
                $data_pf = DB::queryFirstRow(
                    'SELECT personal_folder
                    FROM '.prefixTable('nested_tree').'
                    WHERE id = %s',
                    $post_groupe
                );
                $folder_is_personal = $data_pf['personal_folder'];
                $visibilite = $_SESSION['name'].' '.$_SESSION['lastname'].' ('.$_SESSION['login'].')';
            }

            recupDroitCreationSansComplexite($post_groupe);

            // get list of roles
            $listOptionsForUsers = array();
            $listOptionsForRoles = array();
            $rows = DB::query(
                'SELECT r.role_id AS role_id, t.title AS title
                FROM '.prefixTable('roles_values').' AS r
                INNER JOIN '.prefixTable('roles_title').' AS t ON (r.role_id = t.id)
                WHERE r.folder_id = %i',
                $post_groupe
            );
            foreach ($rows as $record) {
                array_push(
                    $listOptionsForRoles,
                    array(
                        'id' => $record['role_id'],
                        'title' => $record['title'],
                    )
                );
                $rows2 = DB::query(
                    'SELECT id, login, fonction_id, email, name, lastname
                    FROM '.prefixTable('users').'
                    WHERE admin = 0'
                );
                foreach ($rows2 as $record2) {
                    foreach (explode(';', $record2['fonction_id']) as $role) {
                        if (array_search($record2['id'], array_column($listOptionsForUsers, 'id')) === false
                            && $role === $record['role_id']
                        ) {
                            array_push(
                                $listOptionsForUsers,
                                array(
                                    'id' => $record2['id'],
                                    'login' => $record2['login'],
                                    'name' => $record2['name'].' '.$record2['lastname'],
                                    'email' => $record2['email'],
                                )
                            );
                        }
                    }
                }
            }

            $returnValues = array(
                'folderId' => $post_groupe,
                'error' => '',
                'val' => $data['valeur'],
                'visibility' => $visibilite,
                'complexity' => $complexity,
                'personal' => $folder_is_personal,
                'usersList' => $listOptionsForUsers,
                'rolesList' => $listOptionsForRoles,
                'setting_restricted_to_roles' => isset($SETTINGS['restricted_to_roles']) === true
                    && $SETTINGS['restricted_to_roles'] === '1' ? 1 : 0,
            );
            echo prepareExchangedData($returnValues, 'encode');
            break;

        /*
        * CASE
        * DELETE attached file from an item
        */
        case 'delete_attached_file':
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

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );
            $fileId = filter_var($dataReceived['file_id'], FILTER_SANITIZE_NUMBER_INT);

            // Get some info before deleting
            $data = DB::queryFirstRow(
                'SELECT name, id_item, file
                FROM '.prefixTable('files').'
                WHERE id = %i',
                $fileId
            );

            // Load item data
            $data_item = DB::queryFirstRow(
                'SELECT id_tree
                FROM '.prefixTable('items').'
                WHERE id = %i',
                $data['id_item']
            );

            // Check that user can access this folder
            if (in_array($data_item['id_tree'], $_SESSION['groupes_visibles']) === false) {
                echo prepareExchangedData(array('error' => 'ERR_FOLDER_NOT_ALLOWED'), 'encode');
                break;
            }

            if (empty($data['id_item']) === false) {
                // Delete from FILES table
                DB::delete(
                    prefixTable('files'),
                    'id = %i',
                    $fileId
                );

                // Update the log
                logItems(
                    $SETTINGS,
                    $data['id_item'],
                    $data['name'],
                    $_SESSION['user_id'],
                    'at_modification',
                    $_SESSION['login'],
                    'at_del_file : '.$data['name']
                );

                // DElete sharekeys
                DB::delete(
                    prefixTable('sharekeys_files'),
                    'object_id = %i',
                    $fileId
                );

                // Delete file from server
                fileDelete($SETTINGS['path_to_upload_folder'].'/'.TP_FILE_PREFIX.base64_decode($data['file']), $SETTINGS);
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        /*
        * CASE
        * Clear HTML tags
        */
        case 'clear_html_tags':
            // Get information for this item
            $dataItem = DB::queryfirstrow(
                'SELECT description FROM '.prefixTable('items').' WHERE id=%i',
                filter_input(INPUT_POST, 'id_item', FILTER_SANITIZE_NUMBER_INT)
            );
            // Clean up the string
            echo json_encode(array('description' => strip_tags($dataItem['description'])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            break;

        /*
        * FUNCTION
        * Launch an action when clicking on a quick icon
        * $action = 0 => Make not favorite
        * $action = 1 => Make favorite
        */
        case 'action_on_quick_icon':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']
                || $_SESSION['user_read_only'] === true || !isset($SETTINGS['pwd_maximum_length'])
            ) {
                // error
                exit();
            }

            if (intval(filter_input(INPUT_POST, 'action', FILTER_SANITIZE_NUMBER_INT)) === 0) {
                // Add new favourite
                array_push($_SESSION['favourites'], $post_item_id);
                //print_r($_SESSION['favourites']);
                DB::update(
                    prefixTable('users'),
                    array(
                        'favourites' => implode(';', $_SESSION['favourites']),
                        ),
                    'id = %i',
                    $_SESSION['user_id']
                );
                // Update SESSION with this new favourite
                $data = DB::queryfirstrow(
                    'SELECT label,id_tree
                    FROM '.prefixTable('items').'
                    WHERE id = '.mysqli_real_escape_string($link, $post_item_id)
                );
                $_SESSION['favourites_tab'][$post_item_id] = array(
                    'label' => $data['label'],
                    'url' => 'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$post_item_id,
                    );
            } elseif ((int) filter_input(INPUT_POST, 'action', FILTER_SANITIZE_NUMBER_INT) === 1) {
                // delete from session
                foreach ($_SESSION['favourites'] as $key => $value) {
                    if ($_SESSION['favourites'][$key] === $post_item_id) {
                        unset($_SESSION['favourites'][$key]);
                        break;
                    }
                }
                // delete from DB
                DB::update(
                    prefixTable('users'),
                    array(
                        'favourites' => implode(';', $_SESSION['favourites']),
                    ),
                    'id = %i',
                    $_SESSION['user_id']
                );
                // refresh session fav list
                if (isset($_SESSION['favourites_tab'])) {
                    foreach ($_SESSION['favourites_tab'] as $key => $value) {
                        if ($key == $post_id) {
                            unset($_SESSION['favourites_tab'][$key]);
                            break;
                        }
                    }
                }
            }
            break;

        /*
        * CASE
        * Move an ITEM
        */
        case 'move_item':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true || isset($SETTINGS['pwd_maximum_length']) === false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // get data about item
            $dataSource = DB::queryfirstrow(
                'SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                FROM '.prefixTable('items').' as i
                INNER JOIN '.prefixTable('nested_tree').' as f ON (i.id_tree=f.id)
                WHERE i.id=%i',
                $post_item_id
            );

            // get data about new folder
            $dataDestination = DB::queryfirstrow(
                'SELECT personal_folder, title
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_folder_id
            );

            // Check that user can access this folder
            if (in_array($dataSource['id_tree'], $_SESSION['groupes_visibles']) === false
                || in_array($post_folder_id, $_SESSION['groupes_visibles']) === false
            ) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Manage possible cases
            if ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '0') {
                // Previous is non personal folder and new too
                // Just update is needed. Item key is the same
                DB::update(
                    prefixTable('items'),
                    array(
                        'id_tree' => $post_folder_id,
                        ),
                    'id=%i',
                    $post_item_id
                );
            // ---
                // ---
            } elseif ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '1') {
                // Source is public and destination is personal
                // Decrypt and remove all sharekeys (items, fields, files)
                // Encrypt only for the user

                // Remove all item sharekeys items
                DB::delete(
                    prefixTable('sharekeys_items'),
                    'object_id = %i AND user_id != %i',
                    $post_item_id,
                    $_SESSION['user_id']
                );

                // Remove all item sharekeys fields
                // Get fields for this Item
                $rows = DB::query(
                    'SELECT id
                    FROM '.prefixTable('categories_items').'
                    WHERE item_id = %i',
                    $post_item_id
                );
                foreach ($rows as $field) {
                    DB::delete(
                        prefixTable('sharekeys_fields'),
                        'object_id = %i AND user_id != %i',
                        $field['id'],
                        $_SESSION['user_id']
                    );
                }

                // Remove all item sharekeys files
                // Get FILES for this Item
                $rows = DB::query(
                    'SELECT id
                    FROM '.prefixTable('files').'
                    WHERE id_item = %i',
                    $post_item_id
                );
                foreach ($rows as $attachment) {
                    DB::delete(
                        prefixTable('sharekeys_files'),
                        'object_id = %i AND user_id != %i',
                        $attachment['id'],
                        $_SESSION['user_id']
                    );
                }

                // update pw
                DB::update(
                    prefixTable('items'),
                    array(
                        'id_tree' => $post_folder_id,
                        'perso' => 1,
                    ),
                    'id=%i',
                    $post_item_id
                );
            // ---
                // ---
            } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '1') {
                // If previous is personal folder and new is personal folder too => no key exist on item
                // just update is needed. Item key is the same
                DB::update(
                    prefixTable('items'),
                    array(
                        'id_tree' => $post_folder_id,
                    ),
                    'id=%i',
                    $post_item_id
                );
            // ---
                // ---
            } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '0') {
                // If previous is personal folder and new is not personal folder => no key exist on item => add new
                // Create keys for all users

                // Get the ITEM object key for the user
                $userKey = DB::queryFirstRow(
                    'SELECT share_key
                    FROM '.prefixTable('sharekeys_items').'
                    WHERE user_id = %i AND object_id = %i',
                    $_SESSION['user_id'],
                    $post_item_id
                );
                if (DB::count() > 0) {
                    $objectKey = decryptUserObjectKey($userKey['share_key'], $_SESSION['user']['private_key']);

                    // This is a public object
                    $users = DB::query(
                        'SELECT id, public_key
                        FROM '.prefixTable('users').'
                        WHERE id NOT IN ("'.OTV_USER_ID.'","'.SSH_USER_ID.'","'.API_USER_ID.'")
                        AND public_key != ""'
                    );
                    foreach ($users as $user) {
                        // Insert in DB the new object key for this item by user
                        DB::insert(
                            prefixTable('sharekeys_items'),
                            array(
                                'object_id' => $post_item_id,
                                'user_id' => $user['id'],
                                'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                            )
                        );
                    }
                }

                // Get the FIELDS object key for the user
                // Get fields for this Item
                $rows = DB::query(
                    'SELECT id
                    FROM '.prefixTable('categories_items').'
                    WHERE item_id = %i',
                    $post_item_id
                );
                foreach ($rows as $field) {
                    $userKey = DB::queryFirstRow(
                        'SELECT share_key
                        FROM '.prefixTable('sharekeys_fields').'
                        WHERE user_id = %i AND object_id = %i',
                        $_SESSION['user_id'],
                        $field['id']
                    );
                    if (DB::count() > 0) {
                        $objectKey = decryptUserObjectKey($userKey['share_key'], $_SESSION['user']['private_key']);

                        // This is a public object
                        $users = DB::query(
                            'SELECT id, public_key
                            FROM '.prefixTable('users').'
                            WHERE id NOT IN ("'.OTV_USER_ID.'","'.SSH_USER_ID.'","'.API_USER_ID.'","'.$_SESSION['user_id'].'")
                            AND public_key != ""'
                        );
                        foreach ($users as $user) {
                            // Insert in DB the new object key for this item by user
                            DB::insert(
                                prefixTable('sharekeys_fields'),
                                array(
                                    'object_id' => $field['id'],
                                    'user_id' => $user['id'],
                                    'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                                )
                            );
                        }
                    }
                }

                // Get the FILE object key for the user
                // Get FILES for this Item
                $rows = DB::query(
                    'SELECT id
                    FROM '.prefixTable('files').'
                    WHERE id_item = %i',
                    $post_item_id
                );
                foreach ($rows as $attachment) {
                    $userKey = DB::queryFirstRow(
                        'SELECT share_key
                        FROM '.prefixTable('sharekeys_files').'
                        WHERE user_id = %i AND object_id = %i',
                        $_SESSION['user_id'],
                        $attachment['id']
                    );
                    if (DB::count() > 0) {
                        $objectKey = decryptUserObjectKey($userKey['share_key'], $_SESSION['user']['private_key']);

                        // This is a public object
                        $users = DB::query(
                            'SELECT id, public_key
                            FROM '.prefixTable('users').'
                            WHERE id NOT IN ("'.OTV_USER_ID.'","'.SSH_USER_ID.'","'.API_USER_ID.'","'.$_SESSION['user_id'].'")
                            AND public_key != ""'
                        );
                        foreach ($users as $user) {
                            // Insert in DB the new object key for this item by user
                            DB::insert(
                                prefixTable('sharekeys_files'),
                                array(
                                    'object_id' => $attachment['id'],
                                    'user_id' => $user['id'],
                                    'share_key' => encryptUserObjectKey($objectKey, $user['public_key']),
                                )
                            );
                        }
                    }
                }

                // update item
                DB::update(
                    prefixTable('items'),
                    array(
                        'id_tree' => $post_folder_id,
                        'perso' => 0,
                    ),
                    'id=%i',
                    $post_item_id
                );
            }

            // Log item moved
            logItems(
                $SETTINGS,
                $post_item_id,
                $dataSource['label'],
                $_SESSION['user_id'],
                'at_modification',
                $_SESSION['login'],
                'at_moved : '.$dataSource['title'].' -> '.$dataDestination['title']
            );

            $returnValues = array(
                'error' => '',
                'message' => '',
                'from_folder' => $dataSource['id_tree'],
                'to_folder' => $post_folder_id,
            );
            echo prepareExchangedData($returnValues, 'encode');
            break;

        /*
        * CASE
        * MASSIVE Move an ITEM
        */
        case 'mass_move_items':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true || isset($SETTINGS['pwd_maximum_length']) === false) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // loop on items to move
            foreach (explode(';', filter_input(INPUT_POST, 'item_ids', FILTER_SANITIZE_STRING)) as $item_id) {
                if (empty($item_id) === false) {
                    // get data about item
                    $dataSource = DB::queryfirstrow(
                        'SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                        FROM '.prefixTable('items').' as i
                        INNER JOIN '.prefixTable('nested_tree').' as f ON (i.id_tree=f.id)
                        WHERE i.id=%i',
                        $item_id
                    );

                    // Check that user can access this folder
                    if (in_array($dataSource['id_tree'], $_SESSION['groupes_visibles']) === false
                        || in_array($post_folder_id, $_SESSION['groupes_visibles']) === false
                    ) {
                        echo '[{"error":"not_allowed" , "status":"ok"}]';
                        exit();
                    }

                    // get data about new folder
                    $dataDestination = DB::queryfirstrow(
                        'SELECT personal_folder, title FROM '.prefixTable('nested_tree').' WHERE id = %i',
                        $post_folder_id
                    );

                    // previous is non personal folder and new too
                    if ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '0') {
                        // just update is needed. Item key is the same
                        DB::update(
                            prefixTable('items'),
                            array(
                                'id_tree' => $post_folder_id,
                                ),
                            'id=%i',
                            $item_id
                        );
                    } elseif ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '1') {
                        $decrypt = cryption(
                            $dataSource['pw'],
                            '',
                            'decrypt',
                            $SETTINGS
                        );
                        $encrypt = cryption(
                            $decrypt['string'],
                            mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                            'encrypt',
                            $SETTINGS
                        );
                        // update pw
                        DB::update(
                            prefixTable('items'),
                            array(
                                'id_tree' => $post_folder_id,
                                'pw' => $encrypt['string'],
                                'pw_iv' => '',
                                'perso' => 1,
                            ),
                            'id=%i',
                            $item_id
                        );
                    // If previous is personal folder and new is personal folder too => no key exist on item
                    } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '1') {
                        // just update is needed. Item key is the same
                        DB::update(
                            prefixTable('items'),
                            array(
                                'id_tree' => $post_folder_id,
                            ),
                            'id=%i',
                            $item_id
                        );
                    // If previous is personal folder and new is not personal folder => no key exist on item => add new
                    } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '0') {
                        $decrypt = cryption(
                            $dataSource['pw'],
                            mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                            'decrypt',
                            $SETTINGS
                        );
                        $encrypt = cryption(
                            $decrypt['string'],
                            '',
                            'encrypt',
                            $SETTINGS
                        );

                        // update item
                        DB::update(
                            prefixTable('items'),
                            array(
                                'id_tree' => $post_folder_id,
                                'pw' => $encrypt['string'],
                                'pw_iv' => '',
                                'perso' => 0,
                            ),
                            'id=%i',
                            $item_id
                        );
                    }
                    // Log item moved
                    logItems(
                        $SETTINGS,
                        $item_id,
                        $dataSource['label'],
                        $_SESSION['user_id'],
                        'at_modification',
                        $_SESSION['login'],
                        'at_moved : '.$dataSource['title'].' -> '.$dataDestination['title']
                    );
                }
            }

            // reload cache table
            require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
            updateCacheTable('reload', $SETTINGS, '');

            echo '[{"error":"" , "status":"ok"}]';
            break;

        /*
         * CASE
         * MASSIVE Delete an item
        */
        case 'mass_delete_items':
            // Check KEY and rights
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

            // loop on items to move
            foreach (explode(';', filter_input(INPUT_POST, 'item_ids', FILTER_SANITIZE_STRING)) as $item_id) {
                if (empty($item_id) === false) {
                    // get info
                    $dataSource = DB::queryfirstrow(
                        'SELECT label, id_tree
                        FROM '.prefixTable('items').'
                        WHERE id=%i',
                        $item_id
                    );

                    // Check that user can access this folder
                    if (in_array($dataSource['id_tree'], $_SESSION['groupes_visibles']) === false
                    ) {
                        echo '[{"error":"'.langHdl('error_not_allowed_to').'" , "status":"nok"}]';
                        exit();
                    }

                    // perform a check in case of Read-Only user creating an item in his PF
                    if ($_SESSION['user_read_only'] === true) {
                        echo '[{"error":"'.langHdl('error_not_allowed_to').'" , "status":"nok"}]';
                        exit();
                    }

                    // delete item consists in disabling it
                    DB::update(
                        prefixTable('items'),
                        array(
                            'inactif' => '1',
                            ),
                        'id = %i',
                        $item_id
                    );

                    // log
                    logItems(
                        $SETTINGS,
                        $item_id,
                        $dataSource['label'],
                        $_SESSION['user_id'],
                        'at_delete',
                        $_SESSION['login']
                    );

                    // Update CACHE table
                    updateCacheTable('delete_value', $SETTINGS, $item_id);
                }
            }

            echo '[{"error":"" , "status":"ok"}]';

            break;

            /*
           * CASE
           * Send email
        */
        case 'send_email':
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
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
            $post_receipt = filter_var($dataReceived['receipt'], FILTER_SANITIZE_STRING);
            $post_cat = filter_var($dataReceived['cat'], FILTER_SANITIZE_STRING);

            // get links url
            if (empty($SETTINGS['email_server_url']) === true) {
                $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
            }
            if ($post_cat === 'request_access_to_author') {
                // Content
                if (empty(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING)) === false) {
                    $content = explode(',', filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING));
                }
                // Variables
                $dataAuthor = DB::queryfirstrow('SELECT email,login FROM '.prefixTable('users').' WHERE id= '.$content[1]);
                $dataItem = DB::queryfirstrow('SELECT label, id_tree FROM '.prefixTable('items').' WHERE id= '.$content[0]);

                // Get path
                $path = prepareEmaiItemPath(
                    $dataItem['id_tree'],
                    $dataItem['label'],
                    $SETTINGS
                );

                $ret = json_decode(
                    sendEmail(
                        langHdl('email_request_access_subject'),
                        str_replace(
                            array('#tp_item_author#', '#tp_user#', '#tp_item#'),
                            array(' '.addslashes($dataAuthor['login']), addslashes($_SESSION['login']), $path),
                            langHdl('email_request_access_mail')
                        ),
                        $dataAuthor['email'],
                        $SETTINGS
                    ),
                    true
                );
            } elseif ($post_cat === 'share_this_item') {
                $dataItem = DB::queryfirstrow(
                    'SELECT label,id_tree
                    FROM '.prefixTable('items').'
                    WHERE id= %i',
                    $post_id
                );

                // Get path
                $path = prepareEmaiItemPath(
                    $dataItem['id_tree'],
                    $dataItem['label'],
                    $SETTINGS
                );

                // send email
                $ret = json_decode(
                    sendEmail(
                        langHdl('email_share_item_subject'),
                        str_replace(
                            array(
                                '#tp_link#',
                                '#tp_user#',
                                '#tp_item#',
                            ),
                            array(
                                empty($SETTINGS['email_server_url']) === false ?
                                $SETTINGS['email_server_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.$post_id :
                                $SETTINGS['cpassman_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.$post_id,
                                addslashes($_SESSION['login']),
                                addslashes($path),
                            ),
                            langHdl('email_share_item_mail')
                        ),
                        $post_receipt,
                        $SETTINGS
                    ),
                    true
                );
            }

            echo prepareExchangedData(
                array(
                    'error' => empty($ret['error']) === true ? false : true,
                    'message' => $ret['message'],
                ),
                'encode'
            );

            break;

        /*
           * CASE
           * manage notification of an Item
        */
        case 'notify_a_user':
            if ($post_key !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            } else {
                if (filter_input(INPUT_POST, 'notify_type', FILTER_SANITIZE_STRING) === 'on_show') {
                    // Check if values already exist
                    $data = DB::queryfirstrow(
                        'SELECT notification FROM '.prefixTable('items').' WHERE id = %i',
                        $post_item_id
                    );
                    $notifiedUsers = explode(';', $data['notification']);
                    // User is not in actual notification list
                    if ($post_status === 'true' && !in_array($post_user_id, $notifiedUsers)) {
                        // User is not in actual notification list and wants to be notified
                        DB::update(
                            prefixTable('items'),
                            array(
                                'notification' => empty($data['notification']) ?
                                    filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT).';'
                                    : $data['notification'].filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT),
                                ),
                            'id=%i',
                            $post_item_id
                        );
                        echo '[{"error" : "", "new_status":"true"}]';
                        break;
                    } elseif ($post_status === false && in_array($post_user_id, $notifiedUsers)) {
                        // TODO : delete user from array and store in DB
                        // User is in actual notification list and doesn't want to be notified
                        DB::update(
                            prefixTable('items'),
                            array(
                                'notification' => empty($data['notification']) ?
                                    filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT)
                                    : $data['notification'].';'.filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT),
                                ),
                            'id=%i',
                            $post_item_id
                        );
                    }
                }
            }
            break;

        /*
        * CASE
        * Item History Log - add new entry
        */
        case 'history_entry_add':
            if ($post_key !== $_SESSION['key']) {
                $data = array('error' => 'key_is_wrong');
                echo prepareExchangedData($data, 'encode');
                break;
            } else {
                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData($post_data, 'decode');
                // Get all informations for this item
                $dataItem = DB::queryfirstrow(
                    'SELECT *
                    FROM '.prefixTable('items').' as i
                    INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                    WHERE i.id=%i AND l.action = %s',
                    $dataReceived['item_id'],
                    'at_creation'
                );
                // check that actual user can access this item
                $restrictionActive = true;
                $restrictedTo = array_filter(explode(';', $dataItem['restricted_to']));
                if (in_array($_SESSION['user_id'], $restrictedTo)) {
                    $restrictionActive = false;
                }
                if (empty($dataItem['restricted_to'])) {
                    $restrictionActive = false;
                }

                if ((
                        (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles'])) && ($dataItem['perso'] === '0' || ($dataItem['perso'] === '1' && $dataItem['id_user'] == $_SESSION['user_id'])) && $restrictionActive === false
                    )
                    ||
                    (
                        isset($SETTINGS['anyone_can_modify']) && $SETTINGS['anyone_can_modify'] === '1' && $dataItem['anyone_can_modify'] === '1' && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] === '1') && $restrictionActive === false
                    )
                    ||
                    (@in_array(
                        $post_id,
                        $_SESSION['list_folders_limited'][$post_folder_id]
                    ))
                ) {
                    $error = '';
                    // Query
                    logItems(
                        $SETTINGS,
                        $dataReceived['item_id'],
                        $dataItem['label'],
                        $_SESSION['user_id'],
                        'at_manual',
                        $_SESSION['login'],
                        htmlspecialchars_decode($dataReceived['label'], ENT_QUOTES)
                    );
                    // Prepare new line
                    $data = DB::queryfirstrow(
                        'SELECT * FROM '.prefixTable('log_items').' WHERE id_item = %i ORDER BY date DESC',
                        $dataReceived['item_id']
                    );
                    $historic = date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $data['date']).' - '.$_SESSION['login'].' - '.langHdl($data['action']).' - '.$data['raison'];
                    // send back
                    $data = array(
                        'error' => '',
                        'new_line' => '<br>'.addslashes($historic),
                    );
                    echo prepareExchangedData($data, 'encode');
                } else {
                    $data = array('error' => 'something_wrong');
                    echo prepareExchangedData($data, 'encode');
                    break;
                }
            }
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case 'free_item_for_edition':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // Do
            DB::delete(
                prefixTable('items_edition'),
                'item_id = %i',
                $post_id
            );
            break;

        /*
        * CASE
        * Check if Item has been changed since loaded
        */
        case 'is_item_changed':
            $data = DB::queryFirstRow(
                'SELECT date FROM '.prefixTable('log_items').' WHERE action = %s AND id_item = %i ORDER BY date DESC',
                'at_modification',
                $post_item_id
            );
            // Check if it's in a personal folder. If yes, then force complexity overhead.
            if ($data['date'] > filter_input(INPUT_POST, 'timestamp', FILTER_SANITIZE_STRING)) {
                echo '{ "modified" : "1" }';
            } else {
                echo '{ "modified" : "0" }';
            }
            break;

        /*
        * CASE
        * Check if Item has been changed since loaded
        */
        case 'generate_OTV_url':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // delete all existing old otv codes
            $rows = DB::query('SELECT id FROM '.prefixTable('otv').' WHERE timestamp < '.(time() - $SETTINGS['otv_expiration_period'] * 86400));
            foreach ($rows as $record) {
                DB::delete(prefixTable('otv'), 'id=%i', $record['id']);
            }

            // generate session
            $otv_code = GenerateCryptKey(32, false, true, true, false);

            DB::insert(
                prefixTable('otv'),
                array(
                    'id' => null,
                    'item_id' => $post_id,
                    'timestamp' => time(),
                    'originator' => intval($_SESSION['user_id']),
                    'code' => $otv_code,
                    )
            );
            $newID = DB::insertId();

            $otv_session = array(
                'code' => $otv_code,
                'stamp' => time(),
            );

            if (!isset($SETTINGS['otv_expiration_period'])) {
                $SETTINGS['otv_expiration_period'] = 7;
            }
            $url = $SETTINGS['cpassman_url'].'/index.php?otv=true&'.http_build_query($otv_session);
            $exp_date = date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], time() + (intval($SETTINGS['otv_expiration_period']) * 86400));

            echo json_encode(
                array(
                    'error' => '',
                    'url' => $url,
                    'text' => str_replace(
                        array('#URL#', '#DAY#'),
                        array('<span id=\'otv_link\'>'.$url.'</span>&nbsp;<span class=\'fa-stack tip" title=\''.langHdl('copy').'\' style=\'cursor:pointer;\' id=\'button_copy_otv_link\'><span class=\'fa fa-square fa-stack-2x\'></span><span class=\'fa fa-clipboard fa-stack-1x fa-inverse\'></span></span>', $exp_date),
                        langHdl('one_time_view_item_url_box')
                    ),
                )
            );
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case 'image_preview_preparation':
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

            // get file info
            $file_info = DB::queryfirstrow(
                'SELECT file, status, type, content, extension, name
                FROM '.prefixTable('files').'
                WHERE id=%i',
                $post_id
            );

            // prepare image info
            $post_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $image_code = $file_info['file'];
            $extension = $file_info['extension'];
            $file_to_display = $SETTINGS['url_to_upload_folder'].'/'.$image_code;
            $file_suffix = '';

            // should we encrypt/decrypt the file
            encryptOrDecryptFile(
                $file_info['file'],
                $file_info['status'],
                $SETTINGS
            );

            // should we decrypt the attachment?
            if (isset($file_info['status']) === true
                && $file_info['status'] === 'encrypted'
            ) {
                // Delete the file as viewed
                fileDelete($SETTINGS['path_to_upload_folder'].'/'.$image_code.'_delete.'.$extension, $SETTINGS);

                // Open the file
                if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$image_code) === true) {
                    // Should we encrypt or decrypt?
                    prepareFileWithDefuse(
                        'decrypt',
                        $SETTINGS['path_to_upload_folder'].'/'.$image_code,
                        $SETTINGS['path_to_upload_folder'].'/'.$image_code.'_delete.'.$extension,
                        $SETTINGS
                    );

                    // prepare variable
                    $file_to_display = $file_to_display.'_delete.'.$extension;
                    $file_suffix = '_delete.'.$extension;
                }
            }

            // Encrypt data to return
            echo prepareExchangedData(
                array(
                    'error' => '',
                    'new_file' => $file_to_display,
                    'filename' => $file_info['name'],
                    'file_type' => $file_info['type'],
                    'file_suffix' => $file_suffix,
                    'file_path' => $SETTINGS['path_to_upload_folder'].'/'.$image_code.'_delete.'.$extension,
                    'image_secure' => isset($SETTINGS['secure_display_image']) === true ? $SETTINGS['secure_display_image'] : '0',
                    'file_content' => base64_encode(file_get_contents($SETTINGS['path_to_upload_folder'].'/'.$image_code.'_delete.'.$extension)),
                ),
                'encode'
            );
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case 'delete_file':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get file info
            $result = DB::queryfirstrow(
                'SELECT file FROM '.prefixTable('files').' WHERE id=%i',
                intval(substr(filter_input(INPUT_POST, 'uri', FILTER_SANITIZE_STRING), 1))
            );

            fileDelete($SETTINGS['path_to_upload_folder'].'/'.$result['file'].filter_input(INPUT_POST, 'file_suffix', FILTER_SANITIZE_STRING), $SETTINGS);

            break;

        /*
        * CASE
        * Get list of users that have access to the folder
        */
        case 'check_for_title_duplicate':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            $error = '';
            $duplicate = 0;

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');
            // Prepare variables
            $label = htmlspecialchars_decode($dataReceived['label']);
            $idFolder = $dataReceived['idFolder'];

            // don't check if Personal Folder
            $data = DB::queryFirstRow('SELECT title FROM '.prefixTable('nested_tree').' WHERE id = %i', $idFolder);
            if ($data['title'] == $_SESSION['user_id']) {
                // send data
                echo '[{"duplicate" : "'.$duplicate.'" , error" : ""}]';
            } else {
                if (filter_input(INPUT_POST, 'option', FILTER_SANITIZE_STRING) === 'same_folder') {
                    // case unique folder
                    DB::query(
                        'SELECT label
                        FROM '.prefixTable('items').'
                        WHERE id_tree = %i AND label = %s',
                        $idFolder,
                        $label
                    );
                } else {
                    // case complete database

                    //get list of personal folders
                    $arrayPf = array();
                    $listPf = '';
                    if (empty($row['id']) === false) {
                        $rows = DB::query(
                            'SELECT id FROM '.prefixTable('nested_tree').' WHERE personal_folder = %i',
                            '1'
                        );
                        foreach ($rows as $record) {
                            if (!in_array($record['id'], $arrayPf)) {
                                array_push($arrayPf, $record['id']);
                            }
                        }
                    }

                    // build WHERE condition
                    $where = new WhereClause('and');
                    $where->add('id_tree = %i', $idFolder);
                    $where->add('label = %s', $label);
                    if (empty($arrayPf) === false) {
                        $where->add('id_tree NOT IN ('.implode(',', $arrayPf).')');
                    }

                    DB::query(
                        'SELECT label
                        FROM '.prefixTable('items').'
                        WHERE %l',
                        $where
                    );
                }

                // count results
                if (DB::count() > 0) {
                    $duplicate = 1;
                }

                // send data
                echo '[{"duplicate" : "'.$duplicate.'" , "error" : ""}]';
            }
            break;

        /*
        * CASE
        * Get list of users that have access to the folder
        */
        case 'refresh_visible_folders':
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

            // Init

            // Will we show the root folder?
            $arr_data['can_create_root_folder'] = isset($SETTINGS['can_create_root_folder']) && $SETTINGS['can_create_root_folder'] === '1' ? 1 : 0;

            // Build list of visible folders
            $selectVisibleFoldersOptions = $selectVisibleNonPersonalFoldersOptions = $selectVisibleActiveFoldersOptions = '';
            if (isset($SETTINGS['can_create_root_folder']) && $SETTINGS['can_create_root_folder'] === '1') {
                $selectVisibleFoldersOptions = '<option value="0">'.langHdl('root').'</option>';
            }

            if ($_SESSION['user_admin'] === '1'
                && (null !== TP_ADMIN_FULL_RIGHT && TP_ADMIN_FULL_RIGHT === true)
                || null === TP_ADMIN_FULL_RIGHT
            ) {
                $_SESSION['groupes_visibles'] = $_SESSION['personal_visible_groups'];
                $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
            }

            if (isset($_SESSION['list_folders_limited']) && count($_SESSION['list_folders_limited']) > 0) {
                $listFoldersLimitedKeys = @array_keys($_SESSION['list_folders_limited']);
            } else {
                $listFoldersLimitedKeys = array();
            }
            // list of items accessible but not in an allowed folder
            if (isset($_SESSION['list_restricted_folders_for_items'])
                && count($_SESSION['list_restricted_folders_for_items']) > 0) {
                $listRestrictedFoldersForItemsKeys = @array_keys($_SESSION['list_restricted_folders_for_items']);
            } else {
                $listRestrictedFoldersForItemsKeys = array();
            }

            //Build tree
            $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();
            $folders = $tree->getDescendants();
            $inc = 0;

            foreach ($folders as $folder) {
                // Be sure that user can only see folders he/she is allowed to
                if (in_array($folder->id, $_SESSION['forbiden_pfs']) === false
                    || in_array($folder->id, $_SESSION['groupes_visibles']) === true
                    || in_array($folder->id, $listFoldersLimitedKeys) === true
                    || in_array($folder->id, $listRestrictedFoldersForItemsKeys) === true
                ) {
                    // Init
                    $displayThisNode = false;
                    $hide_node = false;
                    $nbChildrenItems = 0;

                    // Check if any allowed folder is part of the descendants of this node
                    $nodeDescendants = $tree->getDescendants($folder->id, true, false, true);
                    foreach ($nodeDescendants as $node) {
                        // manage tree counters
                        /*if (isset($SETTINGS['tree_counters']) && $SETTINGS['tree_counters'] === '1') {
                            DB::query(
                                "SELECT * FROM ".prefixTable("items")."
                                WHERE inactif=%i AND id_tree = %i",
                                0,
                                $node
                            );
                            $nbChildrenItems += DB::count();
                        }*/
                        if (in_array($node, array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])) === true
                            || @in_array($node, $listFoldersLimitedKeys)
                            || @in_array($node, $listRestrictedFoldersForItemsKeys)
                        ) {
                            $displayThisNode = true;
                            //break;
                        }
                    }

                    if ($displayThisNode === true) {
                        // ALL FOLDERS
                        // Is this folder disabled?
                        $disabled = 0;
                        if (in_array($folder->id, $_SESSION['groupes_visibles']) === false
                            || in_array($folder->id, $_SESSION['read_only_folders']) === true
                            || ($_SESSION['user_read_only'] === '1' && in_array($folder->id, $_SESSION['personal_visible_groups']) === false)
                        ) {
                            $disabled = 1;
                        }

                        // Build path
                        $arbo = $tree->getPath($folder->id, false);
                        $arr_data['folders'][$inc]['path'] = '';
                        foreach ($arbo as $elem) {
                            if (empty($arr_data['folders'][$inc]['path']) === true) {
                                $arr_data['folders'][$inc]['path'] = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                            } else {
                                $arr_data['folders'][$inc]['path'] .= ' / '.htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
                            }
                        }

                        // Build array
                        $arr_data['folders'][$inc]['id'] = intval($folder->id);
                        $arr_data['folders'][$inc]['level'] = intval($folder->nlevel);
                        $arr_data['folders'][$inc]['title'] = ($folder->title == $_SESSION['user_id'] && $folder->nlevel === '1') ? htmlspecialchars_decode($_SESSION['login']) : htmlspecialchars_decode($folder->title, ENT_QUOTES);
                        $arr_data['folders'][$inc]['disabled'] = $disabled;
                        $arr_data['folders'][$inc]['parent_id'] = intval($folder->parent_id);
                        $arr_data['folders'][$inc]['perso'] = intval($folder->personal_folder);

                        // Is this folder an active folders? (where user can do something)
                        $is_visible_active = 0;
                        if (isset($_SESSION['read_only_folders']) === true
                            && in_array($folder->id, $_SESSION['read_only_folders']) === true) {
                            $is_visible_active = 1;
                        }
                        $arr_data['folders'][$inc]['is_visible_active'] = $is_visible_active;

                        ++$inc;
                    }
                }
            }

            $data = array(
                'error' => 'false',
                'html_json' => $arr_data,
            );
            // send data
            echo prepareExchangedData($data, 'encode');

            break;

        /*
        * CASE
        * Get list of users that have access to the folder
        */
        case 'refresh_folders_other_info':
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

            $arr_data = array();

            foreach (json_decode($post_data) as $folder) {
                // Do we have Categories
                if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                    // get list of associated Categories
                    $arrCatList = array();
                    $rows_tmp = DB::query(
                        'SELECT c.id, c.title, c.level, c.type, c.masked, c.order, c.encrypted_data, c.role_visibility, c.is_mandatory,
                        f.id_category AS category_id
                        FROM '.prefixTable('categories_folders').' AS f
                        INNER JOIN '.prefixTable('categories').' AS c ON (f.id_category = c.parent_id)
                        WHERE id_folder=%i',
                        $folder
                    );
                    if (DB::count() > 0) {
                        foreach ($rows_tmp as $row) {
                            $arrCatList[$row['id']] = array(
                                'id' => $row['id'],
                                'title' => $row['title'],
                                'level' => $row['level'],
                                'type' => $row['type'],
                                'masked' => $row['masked'],
                                'order' => $row['order'],
                                'encrypted_data' => $row['encrypted_data'],
                                'role_visibility' => $row['role_visibility'],
                                'is_mandatory' => $row['is_mandatory'],
                                'category_id' => $row['category_id'],
                            );
                        }
                    }
                    $arr_data[$folder]['categories'] = $arrCatList;
                }

                // Now get complexity
                $valTemp = '';
                $data = DB::queryFirstRow(
                    'SELECT valeur
                    FROM '.prefixTable('misc').'
                    WHERE type = %s AND intitule=%i',
                    'complex',
                    $folder
                );
                if (DB::count() > 0 && empty($data['valeur']) === false) {
                    $valTemp = array(
                        'value' => $data['valeur'],
                        'text' => TP_PW_COMPLEXITY[$data['valeur']][1],
                    );
                }
                $arr_data[$folder]['complexity'] = $valTemp;

                // Now get Roles
                $valTemp = '';
                $rows_tmp = DB::query(
                    'SELECT t.title
                    FROM '.prefixTable('roles_values').' as v
                    INNER JOIN '.prefixTable('roles_title').' as t ON (v.role_id = t.id)
                    WHERE v.folder_id = %i
                    GROUP BY title',
                    $folder
                );
                foreach ($rows_tmp as $record) {
                    if (empty($valTemp) === true) {
                        $valTemp = $record['title'];
                    } else {
                        $valTemp .= ' - '.$record['title'];
                    }
                }
                $arr_data[$folder]['visibilityRoles'] = $valTemp;
            }

            $data = array(
                'error' => '',
                'result' => $arr_data,
            );
            // send data
            echo prepareExchangedData($data, 'encode');

            break;

        /*
        * CASE
        * Load item history
        */
        case 'load_item_history':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array('error' => 'ERR_KEY_NOT_CORRECT'), 'encode');
                break;
            }

            // get item info
            $dataItem = DB::queryFirstRow(
                'SELECT *
                FROM '.prefixTable('items').'
                WHERE id=%i',
                $post_item_id
            );

            // get item history
            $history = array();
            $rows = DB::query(
                'SELECT l.date as date, l.action as action, l.raison as raison, l.raison_iv AS raison_iv,
                u.login as login, u.avatar_thumb as avatar_thumb, u.name as name, u.lastname as lastname
                FROM '.prefixTable('log_items').' as l
                LEFT JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
                WHERE id_item=%i AND action <> %s
                ORDER BY date DESC',
                $post_item_id,
                'at_shown'
            );
            foreach ($rows as $record) {
                $reason = explode(' :', $record['raison']);
                if ($reason[0] === 'at_pw') {
                    // check if item is PF
                    if ($dataItem['perso'] !== 1) {
                        $pw = cryption(
                            $reason[1],
                            '',
                            'decrypt',
                            $SETTINGS
                        );
                    } else {
                        if (isset($_SESSION['user_settings']['session_psk']) === true) {
                            $pw = cryption(
                                $reason[1],
                                $_SESSION['user_settings']['session_psk'],
                                'decrypt',
                                $SETTINGS
                            );
                        } else {
                            $pw['string'] = '';
                        }
                    }
                    $reason[1] = $pw['string'];
                    // if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
                    if (!isUTF8($reason[1]) || is_array($reason[1])) {
                        $reason[1] = '';
                    }
                }
                // imported via API
                if (empty($record['login'])) {
                    $record['login'] = langHdl('imported_via_api').' ['.$record['raison'].']';
                }

                if (in_array(
                        $record['action'],
                        array('at_password_copied', 'at_shown', 'at_password_shown')
                    ) === false
                ) {
                    // Prepare avatar
                    if (isset($record['avatar_thumb']) && empty($record['avatar_thumb']) === false) {
                        if (file_exists($SETTINGS['cpassman_dir'].'/includes/avatars/'.$record['avatar_thumb'])) {
                            $avatar = $SETTINGS['cpassman_url'].'/includes/avatars/'.$record['avatar_thumb'];
                        } else {
                            $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
                        }
                    } else {
                        $avatar = $SETTINGS['cpassman_url'].'/includes/images/photo.jpg';
                    }

                    // Prepare action
                    $action = '';
                    $detail = '';
                    if ($reason[0] === 'at_pw') {
                        $action = langHdl($reason[0]);
                        if (empty($reason[1]) === true) {
                            $detail = langHdl('no_previous_value');
                        } else {
                            $detail = langHdl('previous_value').': '.$reason[1];
                        }
                    } elseif (empty($record['raison']) === false && $reason[0] !== 'at_creation') {
                        if ($reason[0] === 'at_moved') {
                            $tmp = explode(' -> ', $reason[1]);
                            $detail = langHdl('from').' <b>'.$tmp[0].'</b> '.langHdl('to').' <b>'.$tmp[1].' </b>';
                        } elseif ($reason[0] === 'at_email') {
                            $tmp = explode(' => ', $reason[1]);
                            $detail = langHdl('from').' <b>'.$tmp[0].'</b> '.langHdl('to').' <b>'.$tmp[1].' </b>';
                        } elseif ($reason[0] === 'at_description') {
                            $detail = langHdl('description_has_changed');
                        } else {
                            $detail = $reason[0];
                        }
                        $action = langHdl($reason[0]);
                    } elseif ($record['action'] === 'at_manual') {
                        $detail = langHdl($record['action']);
                        $action = $reason[0];
                    } else {
                        $detail = langHdl($record['action']);
                        $action = '';
                    }

                    array_push(
                        $history,
                        array(
                            'avatar' => $avatar,
                            'login' => $record['login'],
                            'name' => $record['name'].' '.$record['lastname'],
                            'date' => date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date']),
                            'action' => $action,
                            'detail' => $detail,
                        )
                    );
                }
            }

            $data = array(
                'error' => '',
                'history' => $history,
            );

            // send data
            echo prepareExchangedData($data, 'encode');

            break;

        case 'suggest_item_change':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => 'key_not_conform',
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }
            // decrypt and retrieve data in JSON format
            $data_received = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $label = htmlspecialchars_decode($data_received['label'], ENT_QUOTES);
            $pwd = htmlspecialchars_decode($data_received['password']);
            $login = htmlspecialchars_decode($data_received['login'], ENT_QUOTES);
            $email = htmlspecialchars_decode($data_received['email']);
            $url = htmlspecialchars_decode($data_received['url']);
            $folder = htmlspecialchars_decode($data_received['folder_id']);
            $comment = htmlspecialchars_decode($data_received['comment']);
            $item_id = htmlspecialchars_decode($data_received['item_id']);

            if (empty($pwd)) {
                $encrypt['string'] = '';
            } else {
                $encrypt = cryption($pwd, '', 'encrypt', $SETTINGS);
            }

            // query
            DB::insert(
                prefixTable('items_change'),
                array(
                    'item_id' => $item_id,
                    'label' => $label,
                    'pw' => $encrypt['string'],
                    'login' => $login,
                    'email' => $email,
                    'url' => $url,
                    'description' => '',
                    'comment' => $comment,
                    'folder_id' => $folder,
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => time(),
                )
            );
            $newID = DB::insertId();

            // get some info to add to the notification email
            $resp_user = DB::queryfirstrow(
                'SELECT login FROM '.prefixTable('users').' WHERE id = %i',
                $_SESSION['user_id']
            );
            $resp_folder = DB::queryfirstrow(
                'SELECT title FROM '.prefixTable('nested_tree').' WHERE id = %i',
                $folder
            );

            // notify Managers
            $rows = DB::query(
                'SELECT email
                FROM '.prefixTable('users').'
                WHERE `gestionnaire` = %i AND `email` IS NOT NULL',
                1
            );
            foreach ($rows as $record) {
                sendEmail(
                    langHdl('suggestion_notify_subject'),
                    str_replace(array('#tp_label#', '#tp_user#', '#tp_folder#'), array(addslashes($label), addslashes($resp_user['login']), addslashes($resp_folder['title'])), langHdl('suggestion_notify_body')),
                    $record['email'],
                    $SETTINGS
                );
            }

            echo prepareExchangedData(
                array(
                    'error' => '',
                ),
                'encode'
            );
            break;

        case 'build_list_of_users':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Get list of users
            $usersList = array();
            $usersString = '';
            $rows = DB::query('SELECT id,login,email FROM '.prefixTable('users').' ORDER BY login ASC');
            foreach ($rows as $record) {
                $usersList[$record['login']] = array(
                    'id' => $record['id'],
                    'login' => $record['login'],
                    'email' => $record['email'],
                    );
                $usersString .= $record['id'].'#'.$record['login'].';';
            }

            $data = array(
                'error' => '',
                'list' => $usersString,
            );

            // send data
            echo prepareExchangedData($data, 'encode');
            break;

        case 'send_request_access':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => 'key_not_conform',
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }
            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $post_email_body = filter_var($dataReceived['email'], FILTER_SANITIZE_STRING);
            $post_item_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);

            // Send email
            $dataItem = DB::queryfirstrow(
                'SELECT label, id_tree
                FROM '.prefixTable('items').'
                WHERE id = %i',
                $post_item_id
            );
            $dataItemLog = DB::queryfirstrow(
                'SELECT id_user
                FROM '.prefixTable('log_items').'
                WHERE id_item = %i AND action = %s',
                $post_item_id,
                'at_creation'
            );
            $dataAuthor = DB::queryfirstrow(
                'SELECT email, login
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $dataItemLog['id_user']
            );

            // Get path
            $path = prepareEmaiItemPath(
                $dataItem['id_tree'],
                $dataItem['label'],
                $SETTINGS
            );

            /*$ret = sendEmail(
                langHdl('email_request_access_subject'),
                str_replace(
                    array(
                        '#tp_item_author#',
                        '#tp_user#',
                        '#tp_item#',
                        '#tp_reason#',
                    ),
                    array(
                        ' '.addslashes($dataAuthor['login']),
                        addslashes($_SESSION['login']),
                        $path,
                        nl2br(addslashes($post_email_body)),
                    ),
                    langHdl('email_request_access_mail')
                ),
                $dataAuthor['email'],
                $SETTINGS
            );*/

            // Do log
            logItems(
                $SETTINGS,
                $post_item_id,
                $dataItem['label'],
                $_SESSION['user_id'],
                'at_access',
                $_SESSION['login']
            );

            // Return
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );

            break;

        /*
        * CASE
        * GET CLIPBOARD DATA TO SHOW
        */
        case 'get_clipboard_data':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key'] || $_SESSION['user_read_only'] === true || !isset($SETTINGS['pwd_maximum_length'])) {
                // error
                exit();
            }

            // load the original record into an array
            $dataItem = DB::queryfirstrow(
                'SELECT label, pw, login, perso FROM '.prefixTable('items').'
                WHERE id=%i',
                $post_item_id
            );

            if (filter_input(INPUT_POST, 'show_type', FILTER_SANITIZE_STRING) === 'password') {
                // check if item is PF
                if ($dataItem['perso'] !== 1) {
                    $pw = cryption(
                        $dataItem['pw'],
                        '',
                        'decrypt',
                        $SETTINGS
                    );
                } else {
                    if (isset($_SESSION['user_settings']['session_psk']) === true) {
                        $pw = cryption(
                            $dataItem['pw'],
                            $_SESSION['user_settings']['session_psk'],
                            'decrypt',
                            $SETTINGS
                        );
                    } else {
                        $pw = '';
                    }
                }

                // if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
                if (isUTF8($pw['string']) === false) {
                    $px = '';
                }

                echo prepareExchangedData($pw['string'], 'encode');

                // Now log
                logItems(
                    $SETTINGS,
                    $post_item_id,
                    $dataItem['label'],
                    $_SESSION['user_id'],
                    'at_password_copied',
                    $_SESSION['login']
                );
                break;
            } else {
                echo prepareExchangedData($dataItem['login'], 'encode');
            }

            break;

        /*
        * CASE
        * save_notification_status
        */
        case 'save_notification_status':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => 'key_not_conform',
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }
            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $post_notification_status = (int) filter_var($dataReceived['notification_status'], FILTER_SANITIZE_NUMBER_INT);
            $post_item_id = (int) filter_var($dataReceived['item_id'], FILTER_SANITIZE_NUMBER_INT);

            DB::query(
                'SELECT *
                FROM '.prefixTable('notification').'
                WHERE item_id = %i AND user_id = %i',
                $post_item_id,
                $_SESSION['user_id']
            );
            if (DB::count() > 0) {
                // Notification is set for this user on this item
                if ($post_notification_status === 0) {
                    // Remove the notification
                    DB::delete(
                        prefixTable('notification'),
                        'item_id = %i AND user_id = %i',
                        $post_item_id,
                        $_SESSION['user_id']
                    );
                }
            } else {
                // Notification is not set on this item
                if ($post_notification_status === 1) {
                    // Add the notification
                    DB::insert(
                        prefixTable('notification'),
                        array(
                            'item_id' => $post_item_id,
                            'user_id' => $_SESSION['user_id'],
                        )
                    );
                }
            }

            $data = array(
                'error' => false,
                'message' => '',
            );

            // send data
            echo prepareExchangedData($data, 'encode');

            break;
    }
}
// Build the QUERY in case of GET
if (isset($_GET['type'])) {
    switch ($_GET['type']) {
        /*
        * CASE
        * Autocomplet for TAGS
        */
        case 'autocomplete_tags':
            // Get a list off all existing TAGS
            $listOfTags = '';
            $rows = DB::query('SELECT tag FROM '.prefixTable('tags').' WHERE tag LIKE %ss GROUP BY tag', $_GET['term']);
            foreach ($rows as $record) {
                if (empty($listOfTags)) {
                    $listOfTags = '"'.$record['tag'].'"';
                } else {
                    $listOfTags .= ', "'.$record['tag'].'"';
                }
            }
            echo '['.$listOfTags.']';
            break;
    }
}

/*
* FUNCTION
* Identify if this group authorize creation of item without the complexit level reached
*/
function recupDroitCreationSansComplexite($groupe)
{
    $data = DB::queryFirstRow(
        'SELECT bloquer_creation, bloquer_modification, personal_folder FROM '.prefixTable('nested_tree').' WHERE id = %i',
        $groupe
    );
    // Check if it's in a personal folder. If yes, then force complexity overhead.
    if ($data['personal_folder'] === '1') {
        return array('bloquer_modification_complexite' => 1, 'bloquer_creation_complexite' => 1);
    }

    return array('bloquer_modification_complexite' => $data['bloquer_modification'], 'bloquer_creation_complexite' => $data['bloquer_creation']);
}

/**
 * Permits to identify what icon to display depending on file extension.
 *
 * @param string $ext Extension
 *
 * @return string
 */
function fileFormatImage($ext)
{
    if (in_array($ext, TP_OFFICE_FILE_EXT)) {
        $image = 'fas fa-file-word';
    } elseif ($ext === 'pdf') {
        $image = 'fas fa-file-pdf';
    } elseif (in_array($ext, TP_IMAGE_FILE_EXT)) {
        $image = 'fas fa-file-image';
    } elseif ($ext === 'txt') {
        $image = 'fas fa-file-alt';
    } else {
        $image = 'fas fa-file';
    }

    return $image;
}

/**
 * Returns a cleaned up password.
 *
 * @param string $pwd String for pwd
 *
 * @return string
 */
function passwordReplacement($pwd)
{
    $pwPatterns = array('/ETCOMMERCIAL/', '/SIGNEPLUS/');
    $pwRemplacements = array('&', '+');

    return preg_replace($pwPatterns, $pwRemplacements, $pwd);
}

/**
 * Returns the Item + path.
 *
 * @param int    $id_tree
 * @param string $label
 * @param array  $SETTINGS
 *
 * @return string
 */
function prepareEmaiItemPath($id_tree, $label, $SETTINGS)
{
    // Class loader
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    $arbo = $tree->getPath($id_tree, true);
    $path = '';
    foreach ($arbo as $elem) {
        if (empty($path) === true) {
            $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES).' ';
        } else {
            $path .= '&#8594; '.htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
        }
    }
    // Build text to show user
    if (empty($path) === true) {
        $path = addslashes($label);
    } else {
        $path = addslashes($label).' ('.$path.')';
    }

    return $path;
}
