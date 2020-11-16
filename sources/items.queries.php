<?php
/**
 * @package       items.queries.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2019 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once 'SecureHandler.php';
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'home') === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

/**
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
if (isset($SETTINGS_EXT['pwComplexity']) === false) {
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

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
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

// Class loader
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

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
        case "new_item":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                "decode"
            );

            // Prepare variables
            $label = filter_var(($dataReceived['label']), FILTER_SANITIZE_STRING);
            $url = filter_var(htmlspecialchars_decode($dataReceived['url']), FILTER_SANITIZE_STRING);
            $pw = htmlspecialchars_decode($dataReceived['pw']);
            $login = filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_STRING);
            $tags = htmlspecialchars_decode($dataReceived['tags']);
            $post_template_id = filter_var(htmlspecialchars_decode($dataReceived['template_id']), FILTER_SANITIZE_NUMBER_INT);
            $post_anyone_can_modify = isset($dataReceived['anyone_can_modify']) === true
                && filter_var($dataReceived['anyone_can_modify'], FILTER_SANITIZE_STRING) === 'on' ? '1' : '0';

            // is author authorized to create in this folder
            if (count($_SESSION['list_folders_limited']) > 0) {
                if (!in_array($dataReceived['categorie'], array_keys($_SESSION['list_folders_limited']))
                    && !in_array($dataReceived['categorie'], $_SESSION['groupes_visibles'])
                    && !in_array($dataReceived['categorie'], $_SESSION['personal_visible_groups_list'])
                ) {
                    echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                    break;
                }
            } else {
                if (!in_array($dataReceived['categorie'], $_SESSION['groupes_visibles'])) {
                    echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                    break;
                }
            }

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === true
                && in_array($dataReceived['categorie'], $_SESSION['personal_folders']) === false
            ) {
                echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                break;
            }

            // is pwd empty?
            if (empty($pw)
                && isset($_SESSION['user_settings']['create_item_without_password'])
                && $_SESSION['user_settings']['create_item_without_password'] !== '1'
            ) {
                echo prepareExchangedData(array("error" => "ERR_PWD_EMPTY"), "encode");
                break;
            }

            // Check length
            if (strlen($pw) > $SETTINGS['pwd_maximum_length']) {
                echo prepareExchangedData(array("error" => "ERR_PWD_TOO_LONG"), "encode");
                break;
            }
            // check if element doesn't already exist
            $itemExists = 0;
            $newID = "";
            $data = DB::queryfirstrow(
                "SELECT * FROM ".prefix_table("items")."
                WHERE label = %s AND inactif = %i",
                $label,
                0
            );
            $counter = DB::count();
            if ($counter != 0) {
                $itemExists = 1;
            } else {
                $itemExists = 0;
            }

            // Manage case where item is personal.
            // In this case, duplication is allowed
            if (isset($SETTINGS['duplicate_item'])
                && $SETTINGS['duplicate_item'] === '0'
                && $dataReceived['salt_key_set'] === '1'
                && isset($dataReceived['salt_key_set'])
                && $dataReceived['is_pf'] === '1'
                && isset($dataReceived['is_pf'])
            ) {
                $itemExists = 0;
            }

            if ((isset($SETTINGS['duplicate_item']) && $SETTINGS['duplicate_item'] === '0' && $itemExists === 0)
                ||
                (isset($SETTINGS['duplicate_item']) && $SETTINGS['duplicate_item'] === '1')
            ) {
                // Handle case where pw is empty
                // if not allowed then warn user
                if ((isset($_SESSION['user_settings']['create_item_without_password'])
                    && $_SESSION['user_settings']['create_item_without_password'] !== '1'
                    ) ||
                    empty($pw) === false
                ) {
                    // encrypt PW
                    if ($dataReceived['salt_key_set'] === '1' &&
                        isset($dataReceived['salt_key_set']) &&
                        $dataReceived['is_pf'] === '1' &&
                        isset($dataReceived['is_pf'])
                    ) {
                        $passwd = cryption(
                            $pw,
                            $_SESSION['user_settings']['session_psk'],
                            "encrypt"
                        );
                        $restictedTo = $_SESSION['user_id'];
                    } else {
                        $passwd = cryption(
                            $pw,
                            "",
                            "encrypt"
                        );
                    }
                } else {
                    $passwd['string'] = '';
                }

                if (empty($passwd["error"]) === false) {
                    echo prepareExchangedData(array("error" => "ERR_ENCRYPTION", "msg" => $passwd["error"]), "encode");
                    break;
                }

                // ADD item
                DB::insert(
                    prefix_table("items"),
                    array(
                        'label' => $label,
                        'description' => $dataReceived['description'],
                        'pw' => $passwd['string'],
                        'pw_iv' => "",
                        'email' => noHTML($dataReceived['email']),
                        'url' => noHTML($url),
                        'id_tree' => $dataReceived['categorie'],
                        'login' => noHTML($login),
                        'inactif' => '0',
                        'restricted_to' => isset($dataReceived['restricted_to']) ? $dataReceived['restricted_to'] : '0',
                        'perso' => (isset($dataReceived['salt_key_set']) && $dataReceived['salt_key_set'] === '1' && isset($dataReceived['is_pf']) && $dataReceived['is_pf'] === '1') ? '1' : '0',
                        'anyone_can_modify' => $post_anyone_can_modify,
                        'complexity_level' => $dataReceived['complexity_level'],
                        'encryption_type' => 'defuse'
                        )
                );
                $newID = DB::insertId();
                $pw = $passwd['string'];

                // update fields
                if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                    foreach (explode("_|_", $dataReceived['fields']) as $field) {
                        $field_data = explode("~~", $field);
                        if (count($field_data) > 1 && empty($field_data[1]) === false) {
                            // should we encrypt the data
                            $dataTmp = DB::queryFirstRow(
                                "SELECT encrypted_data
                                FROM ".prefix_table("categories")."
                                WHERE id = %i",
                                $field_data[0]
                            );
                            if ($dataTmp['encrypted_data'] === '1') {
                                $encrypt = cryption(
                                    $field_data[1],
                                    "",
                                    "encrypt"
                                );
                                $enc_type = "defuse";
                            } else {
                                $encrypt['string'] = $field_data[1];
                                $enc_type = "not_set";
                            }


                            DB::insert(
                                prefix_table('categories_items'),
                                array(
                                    'item_id' => $newID,
                                    'field_id' => $field_data[0],
                                    'data' => $encrypt['string'],
                                    'data_iv' => "",
                                    'encryption_type' => $enc_type
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
                        "SELECT *
                        FROM ".prefix_table('templates')."
                        WHERE item_id = %i",
                        $newID
                    );
                    if (DB::count() === 0) {
                        // store field text
                        DB::insert(
                            prefix_table('templates'),
                            array(
                                'item_id' => $newID,
                                'category_id' => $post_template_id
                            )
                        );
                    } else {
                        // Delete if empty
                        if (empty($post_template_id) === true) {
                            DB::delete(
                                $pre."templates",
                                "item_id = %i",
                                $newID
                            );
                        } else {
                            // Update value
                            DB::update(
                                prefix_table('templates'),
                                array(
                                    'category_id' => $post_template_id
                                ),
                                "item_id = %i",
                                $newID
                            );
                        }
                    }
                }

                // If automatic deletion asked
                if ($dataReceived['to_be_deleted'] != 0 && empty($dataReceived['to_be_deleted']) === false) {
                    $date_stamp = dateToStamp($dataReceived['to_be_deleted']);
                    DB::insert(
                        prefix_table('automatic_del'),
                        array(
                            'item_id' => $newID,
                            'del_enabled' => 1, /* Possible values: 0=deactivated;1=activated */
                            'del_type' => $date_stamp !== false ? 2 : 1, /* Possible values:  1=counter;2=date */
                            'del_value' => $date_stamp !== false ? $date_stamp : $dataReceived['to_be_deleted']
                            )
                    );
                }

                // Manage retriction_to_roles
                if (isset($dataReceived['restricted_to_roles'])) {
                    foreach (array_filter(explode(';', $dataReceived['restricted_to_roles'])) as $role) {
                        DB::insert(
                            prefix_table('restriction_to_roles'),
                            array(
                                'role_id' => $role,
                                'item_id' => $newID
                                )
                        );
                    }
                }

                // log
                logItems(
                    $newID,
                    $label,
                    $_SESSION['user_id'],
                    'at_creation',
                    $_SESSION['login']
                );

                // Add tags
                $tags = explode(' ', $tags);
                foreach ($tags as $tag) {
                    if (empty($tag) === false) {
                        DB::insert(
                            prefix_table('tags'),
                            array(
                                'item_id' => $newID,
                                'tag' => strtolower($tag)
                                )
                        );
                    }
                }
                
                // Check if any files have been added
                if (empty($dataReceived['random_id_from_files']) === false) {
                    $rows = DB::query(
                        "SELECT id
                        FROM ".prefix_table("files")."
                        WHERE id_item = %s",
                        $dataReceived['random_id_from_files']
                    );
                    foreach ($rows as $record) {
                        // update item_id in files table
                        DB::update(
                            prefix_table('files'),
                            array(
                                'id_item' => $newID
                                ),
                            "id=%i",
                            $record['id']
                        );
                    }
                }

                // Announce by email?
                if (empty($dataReceived['diffusion']) === false) {
                    // get links url
                    if (empty($SETTINGS['email_server_url'])) {
                        $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                    }

                    // Get path
                    $path = prepareEmaiItemPath(
                        $dataReceived['categorie'],
                        $label,
                        $SETTINGS
                    );

                    // send email
                    foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                        if (empty($emailAddress) === false) {
                            // send it
                            sendEmail(
                                $LANG['email_subject'],
                                str_replace(
                                    array("#label", "#link"),
                                    array($path, $SETTINGS['email_server_url'].'/index.php?page=items&group='.$dataReceived['categorie'].'&id='.$newID.$txt['email_body3']),
                                    $LANG['new_item_email_body']
                                ),
                                $emailAddress,
                                $LANG,
                                $SETTINGS,
                                str_replace(
                                    array("#label", "#link"),
                                    array($path, $SETTINGS['email_server_url'].'/index.php?page=items&group='.$dataReceived['categorie'].'&id='.$newID.$txt['email_body3']),
                                    $LANG['new_item_email_body']
                                )
                            );
                        }
                    }
                }
                // Get Expiration date
                $expirationFlag = '';
                if ($SETTINGS['activate_expiration'] === '1') {
                    $expirationFlag = '<i class="fa fa-flag mi-green"></i>&nbsp;';
                }
                // Prepare full line
                $html = '<li class="item_draggable'
                .'" id="'.$newID.'" style="margin-left:-30px;">'
                .'<span style="cursor:hand;" class="grippy"><i class="fa fa-sm fa-arrows mi-grey-1"></i>&nbsp;</span>'
                .$expirationFlag.'<i class="fa fa-sm fa-warning mi-yellow"></i>&nbsp;'.
                '&nbsp;<a id="fileclass'.$newID.'" class="file" onclick="AfficherDetailsItem(\''.$newID.'\', \'0\', \'\', \'\', \'\', \'\', \'\')" ondblclick="AfficherDetailsItem(\''.$newID.'\', \'0\', \'\', \'\', \'\', true, \'\')">'.
                stripslashes($dataReceived['label']);
                if (empty($dataReceived['description']) === false && isset($SETTINGS['show_description']) && $SETTINGS['show_description'] === '1') {
                    $html .= '&nbsp;<font size=2px>['.strip_tags(stripslashes(substr(cleanString($dataReceived['description']), 0, 30))).']</font>';
                }
                $html .= '</a><span style="float:right;margin:2px 10px 0px 0px;">';
                // mini icon for collab
                if (isset($SETTINGS['anyone_can_modify']) && $SETTINGS['anyone_can_modify'] === '1') {
                    $itemCollab = '<i class="fa fa-pencil fa-sm mi-grey-1 pointer tip" title="'.$LANG['item_menu_collab_enable'].'" ondblclick="AfficherDetailsItem(\''.$newID.'\', \'0\', \'\', \'\', \'\', true, \'\')"></i>&nbsp;&nbsp;';
                }
                // display quick icon shortcuts ?
                if (isset($SETTINGS['copy_to_clipboard_small_icons']) && $SETTINGS['copy_to_clipboard_small_icons'] === '1') {
                    $itemLogin = $itemPw = "";

                    if (empty($dataReceived['login']) === false) {
                        $itemLogin = '<span id="iconlogin_'.$newID.'" class="copy_clipboard tip" title="'.$LANG['item_menu_copy_login'].'"><i class="fa fa-sm fa-user mi-black"></i>&nbsp;</span>';
                    }
                    if (empty($dataReceived['pw']) === false) {
                        $itemPw = '<span id="iconpw_'.$newID.'" class="copy_clipboard tip" title="'.$LANG['item_menu_copy_login'].'"><i class="fa fa-sm fa-lock mi-black"></i>&nbsp;</span>';
                    }
                    $html .= $itemLogin.'&nbsp;'.$itemPw;
                }
                // Prepare make Favorite small icon
                $html .= '&nbsp;<span id="quick_icon_fav_'.$newID.'" title="Manage Favorite" class="cursor">';
                if (in_array($newID, $_SESSION['favourites'])) {
                    $html .= '<i class="fa fa-sm fa-star mi-yellow" onclick="ActionOnQuickIcon('.$newID.',0)" class="tip"></i>';
                } else {
                    $html .= '<i class="fa fa-sm fa-star-o mi-black" onclick="ActionOnQuickIcon('.$newID.',1)" class="tip"></i>';
                }

                $html .= '</span></li>';
                // Build array with items
                $itemsIDList = array($newID, $dataReceived['pw'], $login);

                $returnValues = array(
                    "item_exists" => $itemExists,
                    "error" => "no",
                    "new_id" => $newID,
                    "new_pw" => $dataReceived['pw'],
                    "new_login" => $login,
                    "new_entry" => $html,
                    "array_items" => $itemsIDList,
                    "show_clipboard_small_icons" => (isset($SETTINGS['copy_to_clipboard_small_icons']) && $SETTINGS['copy_to_clipboard_small_icons'] === '1') ? 1 : 0
                );
            } elseif (isset($SETTINGS['duplicate_item']) && $SETTINGS['duplicate_item'] === '0' && (int) $itemExists === 1) {
                // Encrypt data to return
                echo prepareExchangedData(array("error" => "item_exists"), "encode");
                break;
            }

            // Add item to CACHE table if new item has been created
            if (isset($newID) === true) {
                updateCacheTable("add_value", $newID);
            }

            // Encrypt data to return
            echo prepareExchangedData($returnValues, "encode");
            break;

        /*
        * CASE
        * update an ITEM
        */
        case "update_item":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // init
            $reloadPage = false;
            $returnValues = array();
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                "decode"
            );

            if (count($dataReceived) > 0) {
                // Prepare variables
                $label = filter_var(($dataReceived['label']), FILTER_SANITIZE_STRING);
                $url = filter_var(htmlspecialchars_decode($dataReceived['url']), FILTER_SANITIZE_STRING);
                $pw = $original_pw = $sentPw = htmlspecialchars_decode($dataReceived['pw']);
                $login = filter_var(htmlspecialchars_decode($dataReceived['login']), FILTER_SANITIZE_STRING);
                $tags = htmlspecialchars_decode($dataReceived['tags']);
                $email = filter_var(htmlspecialchars_decode($dataReceived['email']), FILTER_SANITIZE_STRING);
                $post_category = filter_var(htmlspecialchars_decode($dataReceived['categorie']), FILTER_SANITIZE_NUMBER_INT);
                $post_template_id = filter_var(htmlspecialchars_decode($dataReceived['template_id']), FILTER_SANITIZE_NUMBER_INT);
                $post_anyone_can_modify = isset($dataReceived['anyone_can_modify']) === true
                    && filter_var($dataReceived['anyone_can_modify'], FILTER_SANITIZE_STRING) === 'on' ? '1' : '0';

                // perform a check in case of Read-Only user creating an item in his PF
                if ($_SESSION['user_read_only'] === true && (!in_array($dataReceived['categorie'], $_SESSION['personal_folders']) || $dataReceived['is_pf'] !== '1')) {
                    echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                    break;
                }

                // check if element doesn't already exist
                DB::queryfirstrow(
                    "SELECT * FROM ".prefix_table("items")."
                    WHERE label = %s AND inactif = %i",
                    $label,
                    0
                );
                $counter = DB::count();
                $itemExists = 0;
                $counter = DB::count();
                if ($counter > 1) {
                    $itemExists = 1;
                } else {
                    $itemExists = 0;
                }

                // Manage case where item is personal.
                // In this case, duplication is allowed
                if (isset($SETTINGS['duplicate_item']) === true
                    && $SETTINGS['duplicate_item'] === '0'
                    && $dataReceived['salt_key_set'] === '1'
                    && isset($dataReceived['salt_key_set']) === true
                    && $dataReceived['is_pf'] === '1'
                    && isset($dataReceived['is_pf']) === true
                ) {
                    $itemExists = 0;
                }

                if (isset($SETTINGS['duplicate_item']) === true
                    && $SETTINGS['duplicate_item'] === '0'
                    && (int) $itemExists === 1
                ) {
                    // Encrypt data to return
                    echo prepareExchangedData(array("error" => "item_exists"), "encode");
                    break;
                }

                // Get all informations for this item
                $dataItem = DB::queryfirstrow(
                    "SELECT *
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("log_items")." as l ON (l.id_item = i.id)
                    WHERE i.id=%i AND l.action = %s",
                    $dataReceived['id'],
                    "at_creation"
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

                // perform a check in case of Read-Only user creating an item in his PF
                if ($_SESSION['user_read_only'] === true && !in_array($dataReceived['categorie'], $_SESSION['personal_folders'])) {
                    echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                    break;
                }

                if ((
                        in_array($dataItem['id_tree'], $_SESSION['groupes_visibles'])
                        && ($dataItem['perso'] === '0' || ($dataItem['perso'] === '1' && $dataItem['id_user'] == $_SESSION['user_id']))
                        && $restrictionActive === false
                    )
                    ||
                    (
                        isset($SETTINGS['anyone_can_modify'])
                        && $SETTINGS['anyone_can_modify'] === '1'
                        && $dataItem['anyone_can_modify'] === '1'
                        && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] === '1')
                        && $restrictionActive === false
                    )
                    ||
                    (null !== $post_folder_id
                    && isset($_SESSION['list_restricted_folders_for_items'][$post_folder_id])
                    && in_array($post_id, $_SESSION['list_restricted_folders_for_items'][$post_folder_id])
                    && $post_restricted === '1'
                    && $restrictionActive === false)
                ) {
                    // Is pwd empty?
                    if (empty($pw) && isset($_SESSION['user_settings']['create_item_without_password']) && $_SESSION['user_settings']['create_item_without_password'] !== '1') {
                        echo prepareExchangedData(array("error" => "ERR_PWD_EMPTY"), "encode");
                        break;
                    }

                    // Check length
                    if (strlen($pw) > $SETTINGS['pwd_maximum_length']) {
                        echo prepareExchangedData(array("error" => "ERR_PWD_TOO_LONG"), "encode");
                        break;
                    }
                    // Get existing values
                    $data = DB::queryfirstrow(
                        "SELECT i.id as id, i.label as label, i.description as description, i.pw as pw, i.url as url, i.id_tree as id_tree, i.perso as perso, i.login as login,
                        i.inactif as inactif, i.restricted_to as restricted_to, i.anyone_can_modify as anyone_can_modify, i.email as email, i.notification as notification,
                        u.login as user_login, u.email as user_email
                        FROM ".prefix_table("items")." as i
                        INNER JOIN ".prefix_table("log_items")." as l ON (i.id=l.id_item)
                        INNER JOIN ".prefix_table("users")." as u ON (u.id=l.id_user)
                        WHERE i.id=%i",
                        $dataReceived['id']
                    );

                    // encrypt PW
                    if ((isset($_SESSION['user_settings']['create_item_without_password']) && $_SESSION['user_settings']['create_item_without_password'] !== '1') || empty($pw) === false) {
                        if ($dataReceived['salt_key_set'] === '1' && isset($dataReceived['salt_key_set'])
                            && in_array($post_category, $_SESSION['personal_folders']) === true
                        ) {
                            $sentPw = $pw;
                            $passwd = cryption(
                                $pw,
                                $_SESSION['user_settings']['session_psk'],
                                "encrypt"
                            );
                            $restictedTo = $_SESSION['user_id'];
                        } else {
                            $passwd = cryption(
                                $pw,
                                "",
                                "encrypt"
                            );
                        }

                        if (empty($passwd["error"]) === false) {
                            echo prepareExchangedData(array("error" => $passwd["error"]), "encode");
                            break;
                        }
                    } else {
                        $passwd['string'] = "";
                    }

                    // ---Manage tags
                    // deleting existing tags for this item
                    DB::delete($pre."tags", "item_id = %i", $dataReceived['id']);

                    // Add new tags
                    $return_tags = "";
                    $tags = explode(' ', $tags);
                    foreach ($tags as $tag) {
                        if (empty($tag) === false) {
                            // save in DB
                            DB::insert(
                                prefix_table('tags'),
                                array(
                                    'item_id' => $dataReceived['id'],
                                    'tag' => strtolower($tag)
                                )
                            );
                            // prepare display
                            if (empty($tags)) {
                                $return_tags = "<span class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".strtolower($tag)."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".strtolower($tag)."</span></span>";
                            } else {
                                $return_tags .= "&nbsp;&nbsp;<span class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".strtolower($tag)."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".strtolower($tag)."</span></span>";
                            }
                        }
                    }

                    // update item
                    DB::update(
                        prefix_table("items"),
                        array(
                            'label' => $label,
                            'description' => $dataReceived['description'],
                            'pw' => $passwd['string'],
                            'pw_iv' => "",
                            'email' => $email,
                            'login' => $login,
                            'url' => $url,
                            'id_tree' => (!isset($dataReceived['categorie']) || $dataReceived['categorie'] === "undefined") ? $dataItem['id_tree'] : $dataReceived['categorie'],
                            'restricted_to' => isset($dataReceived['restricted_to']) ? $dataReceived['restricted_to'] : '0',
                            'anyone_can_modify' => $post_anyone_can_modify,
                            'complexity_level' => $dataReceived['complexity_level'],
                            'encryption_type' => 'defuse',
                            'perso' => in_array($post_category, $_SESSION['personal_folders']) === true ? 1 : 0,
                            ),
                        "id=%i",
                        $dataReceived['id']
                    );
                    // update fields
                    if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                        foreach (explode("_|_", $dataReceived['fields']) as $field) {
                            $field_data = explode("~~", $field);
                            if (count($field_data) > 1 && empty($field_data[1]) === false) {
                                $dataTmpCat = DB::queryFirstRow(
                                    "SELECT c.title AS title, i.data AS data, i.data_iv AS data_iv,
                                    i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data,
                                    c.masked AS masked
                                    FROM ".prefix_table("categories_items")." AS i
                                    INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id=c.id)
                                    WHERE i.field_id = %i AND i.item_id = %i",
                                    $field_data[0],
                                    $dataReceived['id']
                                );
                                // store Field text in DB
                                if (DB::count() === 0) {
                                    // get info about this custom field
                                    $dataTmpCat = DB::queryFirstRow(
                                        "SELECT title, encrypted_data
                                        FROM ".prefix_table("categories")."
                                        WHERE id = %i",
                                        $field_data[0]
                                    );

                                    // should we encrypt the data
                                    if ($dataTmpCat['encrypted_data'] === '1') {
                                        $encrypt = cryption(
                                            $field_data[1],
                                            "",
                                            "encrypt"
                                        );
                                        $enc_type = "defuse";
                                    } else {
                                        $encrypt['string'] = $field_data[1];
                                        $enc_type = "not_set";
                                    }

                                    // store field text
                                    DB::insert(
                                        prefix_table('categories_items'),
                                        array(
                                            'item_id' => $dataReceived['id'],
                                            'field_id' => $field_data[0],
                                            'data' => $encrypt['string'],
                                            'data_iv' => "",
                                            'encryption_type' => $enc_type
                                        )
                                    );

                                    // update LOG
                                    logItems(
                                        $dataReceived['id'],
                                        $label,
                                        $_SESSION['user_id'],
                                        'at_modification',
                                        $_SESSION['login'],
                                        'at_field : '.$dataTmpCat['title'].' : '.$field_data[1]
                                    );
                                } else {
                                    // compare the old and new value
                                    if ($dataTmpCat['encryption_type'] === "defuse") {
                                        $oldVal = cryption(
                                            $dataTmpCat['data'],
                                            "",
                                            "decrypt"
                                        );
                                    } else {
                                        $oldVal['string'] = $dataTmpCat['data'];
                                    }

                                    if ($field_data[1] !== $oldVal['string']) {
                                        // should we encrypt the data
                                        if ($dataTmpCat['encrypted_data'] === '1') {
                                            $encrypt = cryption(
                                                $field_data[1],
                                                "",
                                                "encrypt"
                                            );
                                            $enc_type = "defuse";
                                        } else {
                                            $encrypt['string'] = $field_data[1];
                                            $enc_type = "not_set";
                                        }

                                        // update value
                                        DB::update(
                                            prefix_table('categories_items'),
                                            array(
                                                'data' => $encrypt['string'],
                                                'data_iv' => "",
                                                'encryption_type' => $enc_type
                                            ),
                                            "item_id = %i AND field_id = %i",
                                            $dataReceived['id'],
                                            $field_data[0]
                                        );

                                        // update LOG
                                        logItems(
                                            $dataReceived['id'],
                                            $label,
                                            $_SESSION['user_id'],
                                            'at_modification',
                                            $_SESSION['login'],
                                            'at_field : '.$dataTmpCat['title'].' => '.$oldVal['string']
                                        );
                                    }
                                }
                            } else {
                                if (empty($field_data[1]) === true) {
                                    DB::delete(
                                        $pre."categories_items",
                                        "item_id = %i AND field_id = %s",
                                        $dataReceived['id'],
                                        $field_data[0]
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
                            "SELECT *
                            FROM ".prefix_table('templates')."
                            WHERE item_id = %i",
                            $dataReceived['id']
                        );
                        if (DB::count() === 0 && empty($post_template_id) === false) {
                            // store field text
                            DB::insert(
                                prefix_table('templates'),
                                array(
                                    'item_id' => $dataReceived['id'],
                                    'category_id' => $post_template_id
                                )
                            );
                        } else {
                            // Delete if empty
                            if (empty($post_template_id) === true) {
                                DB::delete(
                                    $pre."templates",
                                    "item_id = %i",
                                    $dataReceived['id']
                                );
                            } else {
                                // Update value
                                DB::update(
                                    prefix_table('templates'),
                                    array(
                                        'category_id' => $post_template_id
                                    ),
                                    "item_id = %i",
                                    $dataReceived['id']
                                );
                            }
                        }
                    }

                    // Update automatic deletion - Only by the creator of the Item
                    if (isset($SETTINGS['enable_delete_after_consultation']) && $SETTINGS['enable_delete_after_consultation'] === '1') {
                        // check if elem exists in Table. If not add it or update it.
                        DB::query("SELECT * FROM ".prefix_table("automatic_del")." WHERE item_id = %i", $dataReceived['id']);
                        if (DB::count() === 0) {
                            // No automatic deletion for this item
                            if (empty($dataReceived['to_be_deleted']) === false || ($dataReceived['to_be_deleted'] > 0 && is_numeric($dataReceived['to_be_deleted']))) {
                                // Automatic deletion to be added
                                DB::insert(
                                    prefix_table('automatic_del'),
                                    array(
                                        'item_id' => $dataReceived['id'],
                                        'del_enabled' => 1,
                                        'del_type' => is_numeric($dataReceived['to_be_deleted']) ? 1 : 2,
                                        'del_value' => is_numeric($dataReceived['to_be_deleted']) ? $dataReceived['to_be_deleted'] : dateToStamp($dataReceived['to_be_deleted'])
                                        )
                                );
                                // update LOG
                                logItems(
                                    $dataReceived['id'],
                                    $label,
                                    $_SESSION['user_id'],
                                    'at_modification',
                                    $_SESSION['login'],
                                    'at_automatic_del : '.$dataReceived['to_be_deleted']
                                );
                            }
                        } else {
                            // Automatic deletion exists for this item
                            if (empty($dataReceived['to_be_deleted']) === false || ($dataReceived['to_be_deleted'] > 0 && is_numeric($dataReceived['to_be_deleted']))) {
                                // Update automatic deletion
                                DB::update(
                                    $pre."automatic_del",
                                    array(
                                        'del_type' => is_numeric($dataReceived['to_be_deleted']) ? 1 : 2,
                                        'del_value' => is_numeric($dataReceived['to_be_deleted']) ? $dataReceived['to_be_deleted'] : dateToStamp($dataReceived['to_be_deleted'])
                                        ),
                                    "item_id = %i",
                                    $dataReceived['id']
                                );
                            } else {
                                // delete automatic deleteion for this item
                                DB::delete($pre."automatic_del", "item_id = %i", $dataReceived['id']);
                            }
                            // update LOG
                            logItems(
                                $dataReceived['id'],
                                $label,
                                $_SESSION['user_id'],
                                'at_modification',
                                $_SESSION['login'],
                                'at_automatic_del : '.$dataReceived['to_be_deleted']
                            );
                        }
                    }

                    // get readable list of restriction
                    $listOfRestricted = $oldRestrictionList = "";
                    if (empty($dataReceived['restricted_to']) === false && $SETTINGS['restricted_to'] === '1') {
                        foreach (explode(';', $dataReceived['restricted_to']) as $userRest) {
                            if (empty($userRest) === false) {
                                $dataTmp = DB::queryfirstrow("SELECT login FROM ".prefix_table("users")." WHERE id= %i", $userRest);
                                if (empty($listOfRestricted)) {
                                    $listOfRestricted = $dataTmp['login'];
                                } else {
                                    $listOfRestricted .= ";".$dataTmp['login'];
                                }
                            }
                        }
                    }
                    if ($data['restricted_to'] != $dataReceived['restricted_to'] && $SETTINGS['restricted_to'] === '1') {
                        if (empty($data['restricted_to']) === false) {
                            foreach (explode(';', $data['restricted_to']) as $userRest) {
                                if (empty($userRest) === false) {
                                    $dataTmp = DB::queryfirstrow("SELECT login FROM ".prefix_table("users")." WHERE id= ".$userRest);
                                    if (empty($oldRestrictionList)) {
                                        $oldRestrictionList = $dataTmp['login'];
                                    } else {
                                        $oldRestrictionList .= ";".$dataTmp['login'];
                                    }
                                }
                            }
                        }
                    }
                    // Manage retriction_to_roles
                    if (isset($dataReceived['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === '1') {
                        // get values before deleting them
                        $rows = DB::query(
                            "SELECT t.title
                            FROM ".prefix_table("roles_title")." as t
                            INNER JOIN ".prefix_table("restriction_to_roles")." as r ON (t.id=r.role_id)
                            WHERE r.item_id = %i
                            ORDER BY t.title ASC",
                            $dataReceived['id']
                        );
                        foreach ($rows as $record) {
                            if (empty($oldRestrictionList)) {
                                $oldRestrictionList = $record['title'];
                            } else {
                                $oldRestrictionList .= ";".$record['title'];
                            }
                        }
                        // delete previous values
                        DB::delete(prefix_table("restriction_to_roles"), "item_id = %i", $dataReceived['id']);
                        // add roles for item
                        foreach (array_filter(explode(';', $dataReceived['restricted_to_roles'])) as $role) {
                            $role = explode("role_", $role);
                            if (count($role) > 1) {
                                $role = $role[1];
                            } else {
                                $role = $role[0];
                            }
                            DB::insert(
                                prefix_table('restriction_to_roles'),
                                array(
                                    'role_id' => $role,
                                    'item_id' => $dataReceived['id']
                                    )
                            );
                            $dataTmp = DB::queryfirstrow("SELECT title FROM ".prefix_table("roles_title")." WHERE id= ".$role);
                            if (empty($listOfRestricted)) {
                                $listOfRestricted = $dataTmp['title'];
                            } else {
                                $listOfRestricted .= ";".$dataTmp['title'];
                            }
                        }
                    }
                    // Update CACHE table
                    updateCacheTable("update_value", $dataReceived['id']);
                    // Log all modifications done
                    /*LABEL */
                    if ($data['label'] != $label) {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_label : '.$data['label'].' => '.$label
                        );
                    }
                    /*LOGIN */
                    if ($data['login'] != $login) {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_login : '.$data['login'].' => '.$login
                        );
                    }
                    /*EMAIL */
                    if ($data['email'] != $dataReceived['email']) {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_email : '.$data['email'].' => '.$dataReceived['email']
                        );
                    }
                    /*URL */
                    if ($data['url'] != $url && $url != "http://") {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_url : '.$data['url'].' => '.$url
                        );
                    }
                    /*DESCRIPTION */
                    if ($data['description'] != $dataReceived['description']) {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_description'
                        );
                    }
                    /*FOLDER */
                    if ($data['id_tree'] != $dataReceived['categorie']) {
                        // Get name of folders
                        $dataTmp = DB::query("SELECT title FROM ".prefix_table("nested_tree")." WHERE id IN %li", array($data['id_tree'], $dataReceived['categorie']));

                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_category : '.$dataTmp[0]['title'].' => '.$dataTmp[1]['title']
                        );
                        // ask for page reloading
                        $reloadPage = true;
                    }
                    /*PASSWORD */
                    if ($dataReceived['salt_key_set'] === '1' && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] === '1' && isset($dataReceived['is_pf'])) {
                        $oldPw = $data['pw'];
                        $oldPwClear = cryption(
                            $oldPw,
                            $_SESSION['user_settings']['session_psk'],
                            "decrypt"
                        );
                    } else {
                        $oldPw = $data['pw'];
                        $oldPwClear = cryption(
                            $oldPw,
                            "",
                            "decrypt"
                        );
                    }
                    if ($sentPw != $oldPwClear['string']) {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_pw :'.$oldPw,
                            "defuse"
                        );
                    }
                    /*RESTRICTIONS */
                    if ($data['restricted_to'] != $dataReceived['restricted_to']) {
                        logItems(
                            $dataReceived['id'],
                            $label,
                            $_SESSION['user_id'],
                            'at_modification',
                            $_SESSION['login'],
                            'at_restriction : '.$oldRestrictionList.' => '.$listOfRestricted
                        );
                    }
                    // Reload new values
                    $dataItem = DB::queryfirstrow(
                        "SELECT *
                        FROM ".prefix_table("items")." as i
                        INNER JOIN ".prefix_table("log_items")." as l ON (l.id_item = i.id)
                        WHERE i.id = %i AND l.action = %s",
                        $dataReceived['id'],
                        "at_creation"
                    );
                    // Reload History
                    $history = "";
                    $rows = DB::query(
                        "SELECT l.date as date, l.action as action, l.raison as raison, u.login as login
                        FROM ".prefix_table("log_items")." as l
                        LEFT JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                        WHERE l.action <> %s AND id_item=%s",
                        "at_shown",
                        $dataReceived['id']
                    );
                    foreach ($rows as $record) {
                        $reason = explode(':', $record['raison']);
                        if (count($reason) > 0) {
                            $sentence = date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $record['date'])." - "
                                .$record['login']." - ".$LANG[$record['action']]." - "
                                .(empty($record['raison']) === false ?
                                (count($reason) > 1 ? $LANG[trim($reason[0])].' : '.$reason[1]
                                : $LANG[trim($reason[0])]) : '');
                            if (empty($history)) {
                                $history = $sentence;
                            } else {
                                $history .= "<br />".$sentence;
                            }
                        }
                    }
                    // decrypt PW
                    if (empty($dataReceived['salt_key'])) {
                        $encrypt = cryption(
                            $dataItem['pw'],
                            "",
                            "encrypt"
                        );
                    } else {
                        $encrypt = cryption(
                            $dataItem['pw'],
                            $_SESSION['user_settings']['session_psk'],
                            "encrypt"
                        );
                    }

                    $pw = cleanString($encrypt['string']);
                    // generate 2d key
                    $_SESSION['key_tmp'] = bin2hex(PHP_Crypt::createKey(PHP_Crypt::RAND, 16));

                    // Prepare files listing
                    $files = $filesEdit = "";
                    // launch query
                    $rows = DB::query("SELECT id, name, file, extension FROM ".prefix_table("files")." WHERE id_item=%i", $dataReceived['id']);
                    foreach ($rows as $record) {
                        // get icon image depending on file format
                        $iconImage = fileFormatImage($record['extension']);

                        // If file is an image, then prepare lightbox. If not image, then prepare donwload
                        if (in_array($record['extension'], $SETTINGS_EXT['image_file_ext'])) {
                            $files .= '<i class=\'fa fa-file-image-o\' /></i>&nbsp;<a class="image_dialog" href="#'.$record['id'].'" title="'.$record['name'].'">'.$record['name'].'</a><br />';
                        } else {
                            $files .= '<i class=\'fa fa-file-text-o\' /></i>&nbsp;<a href=\'sources/downloadFile.php?name='.urlencode($record['name']).'&type=sub&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'&fileid='.$record['id'].'\' target=\'_blank\'>'.$record['name'].'</a><br />';
                        }
                        // Prepare list of files for edit dialogbox
                        $filesEdit .= '<span id="span_edit_file_'.$record['id'].'"><span class="fa fa-'.$iconImage.'"></span>&nbsp;<span class="fa fa-eraser tip" style="cursor:pointer;"  onclick="delete_attached_file(\"'.$record['id'].'\")" title="'.$LANG['at_delete'].'"></span>&nbsp;'.$record['name']."</span><br />";
                    }
                    // Send email
                    if (empty($dataReceived['diffusion']) === false) {
                        foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                            if (empty($emailAddress) === false) {
                                sendEmail(
                                    $LANG['email_subject_item_updated'],
                                    str_replace(
                                        array("#item_label#", "#item_category#", "#item_id#", "#url#"),
                                        array($label, $dataReceived['categorie'], $dataReceived['id'], $SETTINGS['cpassman_url']),
                                        $LANG['email_body_item_updated']
                                    ),
                                    $emailAddress,
                                    $LANG,
                                    $SETTINGS,
                                    str_replace("#item_label#", $label, $LANG['email_bodyalt_item_updated'])
                                );
                            }
                        }
                    }

                    // send email to user that whant to be notified
                    if ($dataItem['notification'] !== null && empty($dataItem['notification']) === false) {
                        $users_to_be_notified = array_filter(explode(";", $dataItem['notification']));

                        // perform query to get emails
                        $users_email = DB::QUERY(
                            "SELECT id, email
                            FROM ".prefix_table("users")."
                            WHERE id IN %li",
                            $users_to_be_notified
                        );

                        // build emails list
                        $mailing = "";
                        foreach ($users_email as $record) {
                            if (empty($mailing)) {
                                $mailing = $record['email'];
                            } else {
                                $mailing = ",".$record['email'];
                            }
                        }

                        // send email
                        DB::insert(
                            prefix_table('emails'),
                            array(
                                'timestamp' => time(),
                                'subject' => $LANG['email_subject_item_updated'],
                                'body' =>
                                    str_replace(
                                        array("#item_label#", "#item_category#", "#item_id#", "#url#"),
                                        array($label, $dataReceived['categorie'], $dataReceived['id'], $SETTINGS['cpassman_url']),
                                        $LANG['email_body_item_updated']
                                    ),
                                'receivers' => $mailing,
                                'status' => ''
                                )
                        );
                    }

                    // Prepare some stuff to return
                    $arrData = array(
                        "files" => $files,
                        "history" => str_replace('"', '&quot;', $history),
                        "files_edit" => $filesEdit,
                        "id_tree" => $dataItem['id_tree'],
                        "id" => $dataItem['id'],
                        "reload_page" => $reloadPage,
                        "restriction_to" => $dataReceived['restricted_to'].$dataReceived['restricted_to_roles'],
                        "list_of_restricted" => $listOfRestricted,
                        "tags" => $return_tags,
                        "error" => ""
                        );
                } else {
                    echo prepareExchangedData(array("error" => "ERR_NOT_ALLOWED_TO_EDIT"), "encode");
                    break;
                }
            } else {
                // an error appears on JSON format
                $arrData = array("error" => "ERR_JSON_FORMAT");
            }
            // return data
            echo prepareExchangedData($arrData, "encode");
            break;

        /*".."
          * CASE
          * Copy an Item
        */
        case "copy_item":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "1'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // Prepare POST variables
            $post_source_id = filter_input(INPUT_POST, 'source_id', FILTER_SANITIZE_NUMBER_INT);
            $post_dest_id = filter_input(INPUT_POST, 'dest_id', FILTER_SANITIZE_NUMBER_INT);

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === '1'
                && (!in_array($post_source_id, $_SESSION['personal_folders'])
                    || !in_array($post_dest_id, $_SESSION['personal_folders']))
            ) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            $returnValues = $pw = "";
            $is_perso = 0;

            if (empty($post_item_id) === false
                && empty($post_dest_id) === false
            ) {
                // load the original record into an array
                $originalRecord = DB::queryfirstrow(
                    "SELECT * FROM ".prefix_table("items")."
                    WHERE id=%i",
                    $post_item_id
                );

                // Check if the folder where this item is, is accessible to the user
                if (in_array($originalRecord['id_tree'], $_SESSION['groupes_visibles']) === false) {
                    $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                    echo $returnValues;
                    break;
                }

                // Load the destination folder record into an array
                $dataDestination = DB::queryfirstrow(
                    "SELECT personal_folder FROM ".prefix_table("nested_tree")."
                    WHERE id=%i",
                    $post_dest_id
                );

                // previous is personal folder and public one
                if ($originalRecord['perso'] === '1' && $dataDestination['personal_folder'] === '0') {
                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        "decrypt"
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        "",
                        "encrypt"
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // this item is now public
                    $is_perso = 0;
                // previous is public folder and personal one
                } elseif ($originalRecord['perso'] === '0' && $dataDestination['personal_folder'] === '1') {
                    // check if PSK is set
                    if (!isset($_SESSION['user_settings']['session_psk']) || empty($_SESSION['user_settings']['session_psk'])) {
                        $returnValues = '[{"error" : "no_psk"}, {"error_text" : "'.addslashes($LANG['alert_message_personal_sk_missing']).'"}]';
                        echo $returnValues;
                        break;
                    }

                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        "",
                        "decrypt"
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        "encrypt"
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // this item is now private
                    $is_perso = 1;
                } elseif ($originalRecord['perso'] === '1' && $dataDestination['personal_folder'] === '1') {
                // previous is public folder and personal one
                    // check if PSK is set
                    if (!isset($_SESSION['user_settings']['session_psk']) || empty($_SESSION['user_settings']['session_psk'])) {
                        $returnValues = '[{"error" : "no_psk"}, {"error_text" : "'.addslashes($LANG['alert_message_personal_sk_missing']).'"}]';
                        echo $returnValues;
                        break;
                    }

                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        "decrypt"
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                        "encrypt"
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // this item is now private
                    $is_perso = 1;
                } elseif ($originalRecord['perso'] === '0' && $dataDestination['personal_folder'] === '0') {
                    // decrypt and re-encrypt password
                    $decrypt = cryption(
                        $originalRecord['pw'],
                        "",
                        "decrypt"
                    );
                    $encrypt = cryption(
                        $decrypt['string'],
                        "",
                        "encrypt"
                    );

                    // reaffect pw
                    $originalRecord['pw'] = $encrypt['string'];

                    // is public item
                    $is_perso = 0;
                } else {
                    $returnValues = '[{"error" : "case_not_managed"}, {"error_text" : "ERROR - case is not managed"}]';
                        echo $returnValues;
                        break;
                }

                // insert the new record and get the new auto_increment id
                DB::insert(
                    prefix_table("items"),
                    array(
                        'label' => "duplicate"
                    )
                );
                $newID = DB::insertId();
                // generate the query to update the new record with the previous values
                $aSet = array();
                foreach ($originalRecord as $key => $value) {
                    if ($key === "id_tree") {
                        array_push($aSet, array("id_tree" => $post_dest_id));
                    } elseif ($key === "viewed_no") {
                        array_push($aSet, array("viewed_no" => "0"));
                    } elseif ($key === "pw" && empty($pw) === false) {
                        array_push($aSet, array("pw" => $originalRecord['pw']));
                        array_push($aSet, array("pw_iv" => ""));
                    } elseif ($key === "perso") {
                        array_push($aSet, array("perso" => $is_perso));
                    } elseif ($key != "id" && $key != "key") {
                        array_push($aSet, array($key => $value));
                    }
                }

                DB::update(
                    prefix_table("items"),
                    $aSet,
                    "id = %i",
                    $newID
                );
                // Add attached itms
                $rows = DB::query("SELECT * FROM ".prefix_table("files")." WHERE id_item=%i", $post_item_id);
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
                            prefix_table('files'),
                            array(
                                'id_item' => $newID,
                                'name' => $record['name'],
                                'size' => $record['size'],
                                'extension' => $record['extension'],
                                'type' => $record['type'],
                                'file' => $fileRandomId,
                                'status' => $record['status']
                            )
                        );
                    }
                }

                // Add specific restrictions
                $rows = DB::query("SELECT * FROM ".prefix_table("restriction_to_roles")." WHERE item_id = %i", $post_item_id);
                foreach ($rows as $record) {
                    DB::insert(
                        prefix_table('restriction_to_roles'),
                        array(
                            'item_id' => $newID,
                            'role_id' => $record['role_id']
                            )
                    );
                }

                // Add Tags
                $rows = DB::query("SELECT * FROM ".prefix_table("tags")." WHERE item_id = %i", $post_item_id);
                foreach ($rows as $record) {
                    DB::insert(
                        prefix_table('tags'),
                        array(
                            'item_id' => $newID,
                            'tag' => $record['tag']
                            )
                    );
                }

                // Add this duplicate in logs
                logItems(
                    $newID,
                    $originalRecord['label'],
                    $_SESSION['user_id'],
                    'at_creation',
                    $_SESSION['login']
                );
                // Add the fact that item has been copied in logs
                logItems(
                    $newID,
                    $originalRecord['label'],
                    $_SESSION['user_id'],
                    'at_copy',
                    $_SESSION['login']
                );
                // reload cache table
                require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
                updateCacheTable("reload", "");

                $returnValues = '[{"status" : "ok"}, {"new_id" : "'.$newID.'"}]';
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
        case "show_details_item":
            // Check KEY and rights
            $_SESSION['user_settings']['show_step2'] = false;
            if ($post_key !== $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo prepareExchangedData($returnValues, "encode");
                break;
            }

            // Decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, "decode");
            // Init post variables
            $post_id = filter_var(htmlspecialchars_decode($dataReceived['id']), FILTER_SANITIZE_NUMBER_INT);
            $post_folder_id = filter_var(htmlspecialchars_decode($dataReceived['folder_id']), FILTER_SANITIZE_NUMBER_INT);
            $post_salt_key_required = filter_var(htmlspecialchars_decode($dataReceived['salt_key_required']), FILTER_SANITIZE_STRING);
            $post_salt_key_set = filter_var(htmlspecialchars_decode($dataReceived['salt_key_set']), FILTER_SANITIZE_STRING);
            $post_expired_item = filter_var(htmlspecialchars_decode($dataReceived['expired_item']), FILTER_SANITIZE_STRING);
            $post_restricted = filter_var(htmlspecialchars_decode($dataReceived['restricted']), FILTER_SANITIZE_STRING);
            $post_page = filter_var(htmlspecialchars_decode($dataReceived['page']), FILTER_SANITIZE_STRING);

            $arrData = array();
            // return ID
            $arrData['id'] = $post_id;
            $arrData['id_user'] = API_USER_ID;
            $arrData['author'] = "API";

            // Check if item is deleted
            // taking into account that item can be restored.
            // so if restoration timestamp is higher than the deletion one
            // then we can show it
            $item_deleted = DB::queryFirstRow(
                "SELECT *
                FROM ".prefix_table("log_items")."
                WHERE id_item = %i AND action = %s
                ORDER BY date DESC
                LIMIT 0, 1",
                $post_id,
                "at_delete"
            );
            $dataDeleted = DB::count();

            $item_restored = DB::queryFirstRow(
                "SELECT *
                FROM ".prefix_table("log_items")."
                WHERE id_item = %i AND action = %s
                ORDER BY date DESC
                LIMIT 0, 1",
                $post_id,
                "at_restored"
            );

            if ($dataDeleted != 0 && intval($item_deleted['date']) > intval($item_restored['date'])) {
                // This item is deleted => exit
                echo prepareExchangedData(array('show_detail_option' => 2), "encode");
                break;
            }

            // Get all informations for this item
            $dataItem = DB::queryfirstrow(
                "SELECT *
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("log_items")." as l ON (l.id_item = i.id)
                WHERE i.id = %i AND l.action = %s",
                $post_id,
                "at_creation"
            );

            // LEFT JOIN ".$pre."categories_items as c ON (c.id_item = i.id)
            // INNER JOIN ".$pre."automatic_del as d ON (d.item_id = i.id)
            // Get all USERS infos
            $listNotif = array_filter(explode(";", $dataItem['notification']));
            $listRest = array_filter(explode(";", $dataItem['restricted_to']));
            $listeRestriction = $listNotification = $_SESSION['listNotificationEmails'] = "";

            $user_in_restricted_list_of_item = false;
            $rows = DB::query("SELECT id, login, email, admin FROM ".prefix_table("users"));
            foreach ($rows as $record) {
                // Get auhtor
                if ($record['id'] === $dataItem['id_user']) {
                    $arrData['author'] = $record['login'];
                    $arrData['author_email'] = $record['email'];
                    $arrData['id_user'] = $dataItem['id_user'];
                    if (in_array($record['id'], $listNotif)) {
                        $arrData['notification_status'] = true;
                    } else {
                        $arrData['notification_status'] = false;
                    }
                }

                // Get restriction list for users
                if (in_array($record['id'], $listRest)) {
                    $listeRestriction .= $record['login'].";";
                    if ($_SESSION['user_id'] === $record['id']) {
                        $user_in_restricted_list_of_item = true;
                    }
                }
                // Get notification list for users
                if (in_array($record['id'], $listNotif)) {
                    $listNotification .= $record['login'].";";
                    $_SESSION['listNotificationEmails'] .= $record['email'].",";
                }

                // Add Admins to notification list if expected
                if (isset($SETTINGS['enable_email_notification_on_item_shown']) === true && $SETTINGS['enable_email_notification_on_item_shown'] === "1"
                    && $record['admin'] === "1"
                ) {
                    $listNotification .= $record['login'].";";
                    $_SESSION['listNotificationEmails'] .= $record['email'].",";
                }
            }
            // manage case of API user
            if ($dataItem['id_user'] === API_USER_ID) {
                $arrData['author'] = "API [".$dataItem['description']."]";
                $arrData['id_user'] = API_USER_ID;
                $arrData['author_email'] = "";
                $arrData['notification_status'] = false;
            }

            // Get all tags for this item
            $tags = "";
            $rows = DB::query("SELECT tag FROM ".prefix_table("tags")." WHERE item_id=%i", $post_id);
            foreach ($rows as $record) {
                if (empty($tags)) {
                    $tags = "<span style='' class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".$record['tag']."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".$record['tag']."</span></span>";
                } else {
                    $tags .= "&nbsp;&nbsp;<span style='' class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".$record['tag']."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".$record['tag']."</span></span>";
                }
            }

            // TODO -> improve this check
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
                "SELECT role_id
                FROM ".prefix_table("restriction_to_roles")."
                WHERE item_id=%i",
                $post_id
            );
            foreach ($rows_tmp as $rec_tmp) {
                if (in_array($rec_tmp['role_id'], explode(';', $_SESSION['fonction_id']))) {
                    $restrictionActive = false;
                }
            }

            // Uncrypt PW
            if (null !== $post_salt_key_required
                && $post_salt_key_required === '1'
                && null !== $post_salt_key_set
                && $post_salt_key_set === '1'
            ) {
                $pw = cryption(
                    $dataItem['pw'],
                    $_SESSION['user_settings']['session_psk'],
                    "decrypt"
                );
                $arrData['edit_item_salt_key'] = 1;
            } else {
                $pw = cryption(
                    $dataItem['pw'],
                    "",
                    "decrypt"
                );
                $arrData['edit_item_salt_key'] = 0;
            }

            $pw = @$pw['string'];
            if (!isUTF8($pw)) {
                $pw = '';
            }

            // check if item is expired
            if (null !== $post_expired_item
                && $post_expired_item === '1'
            ) {
                $item_is_expired = true;
            } else {
                $item_is_expired = false;
            }

            // check user is admin
            if ($_SESSION['user_admin'] === '1' && $dataItem['perso'] != 1 && (isset($SETTINGS_EXT['admin_full_right']) && $SETTINGS_EXT['admin_full_right'] === true) || !isset($SETTINGS_EXT['admin_full_right'])) {
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
                // Display menu icon for deleting if user is allowed
                if ($dataItem['id_user'] == $_SESSION['user_id']
                    || $_SESSION['is_admin'] === '1'
                    || ($_SESSION['user_manager'] === '1' && $SETTINGS['manager_edit'] === '1')
                    || $dataItem['anyone_can_modify'] === '1'
                    || in_array($dataItem['id_tree'], $_SESSION['list_folders_editable_by_role'])
                    || in_array($_SESSION['user_id'], $restrictedTo)
                    || count($restrictedTo) === 0
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
                        "SELECT t.title
                        FROM ".prefix_table("roles")."_title as t
                        INNER JOIN ".prefix_table("restriction_to_roles")." as r ON (t.id=r.role_id)
                        WHERE r.item_id = %i
                        ORDER BY t.title ASC",
                        $post_id
                    );
                    foreach ($rows as $record) {
                        if (!in_array($record['title'], $listRestrictionRoles)) {
                            array_push($listRestrictionRoles, $record['title']);
                        }
                    }
                }
                // Check if any KB is linked to this item
                if (isset($SETTINGS['enable_kb']) && $SETTINGS['enable_kb'] === '1') {
                    $tmp = "";
                    $rows = DB::query(
                        "SELECT k.label, k.id
                        FROM ".prefix_table("kb_items")." as i
                        INNER JOIN ".prefix_table("kb")." as k ON (i.kb_id=k.id)
                        WHERE i.item_id = %i
                        ORDER BY k.label ASC",
                        $post_id
                    );
                    foreach ($rows as $record) {
                        if (empty($tmp)) {
                            $tmp = "<a class='round-grey' href='".$SETTINGS['cpassman_url']."/index.php?page=kb&id=".$record['id']."'><i class='fa fa-map-pin fa-sm'></i>&nbsp;".$record['label']."</a>";
                        } else {
                            $tmp .= "&nbsp;<a class='round-grey' href='".$SETTINGS['cpassman_url']."/index.php?page=kb&id=".$record['id']."'><i class='fa fa-map-pin fa-sm'></i>&nbsp;".$record['label']."</a>";
                        }
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
                $arrData['email'] = $dataItem['email'];
                $arrData['url'] = htmlspecialchars_decode($dataItem['url']);
                $arrData['folder'] = $dataItem['id_tree'];
                if (empty($dataItem['url']) === false) {
                    $arrData['link'] = "&nbsp;<a href='".$dataItem['url']."' target='_blank'>&nbsp;<i class='fa fa-link tip' title='".$LANG['open_url_link']."'></i></a>";
                }

                $arrData['description'] = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags($dataItem['description'], $SETTINGS_EXT['allowedTags']));
                $arrData['login'] = htmlspecialchars_decode(str_replace(array('"'), array('&quot;'), $dataItem['login']), ENT_QUOTES);
                $arrData['id_restricted_to'] = $listeRestriction;
                $arrData['id_restricted_to_roles'] = count($listRestrictionRoles) > 0 ? implode(";", $listRestrictionRoles).";" : "";
                $arrData['tags'] = $tags;
                $arrData['folder'] = $dataItem['id_tree'];

                if (isset($SETTINGS['enable_server_password_change'])
                    && $SETTINGS['enable_server_password_change'] === '1') {
                    $arrData['auto_update_pwd_frequency'] = $dataItem['auto_update_pwd_frequency'];
                } else {
                    $arrData['auto_update_pwd_frequency'] = "0";
                }

                $arrData['anyone_can_modify'] = $dataItem['anyone_can_modify'];

                // Add the fact that item has been viewed in logs
                if (isset($SETTINGS['log_accessed']) && $SETTINGS['log_accessed'] === '1') {
                    logItems(
                        $post_id,
                        $dataItem['label'],
                        $_SESSION['user_id'],
                        'at_shown',
                        $_SESSION['login']
                    );
                }

                // statistics
                DB::update(
                    prefix_table("items"),
                    array(
                        'viewed_no' => $dataItem['viewed_no'] + 1,
                    ),
                    "id = %i",
                    $post_id
                );
                $arrData['viewed_no'] = $dataItem['viewed_no'] + 1;

                // get fields
                $fieldsTmp = $arrCatList = $template_id = "";
                if (null !== $post_page && $post_page === "items") {
                    if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                        // get list of associated Categories
                        $arrCatList = array();
                        $rows_tmp = DB::query(
                            "SELECT id_category
                            FROM ".prefix_table("categories_folders")."
                            WHERE id_folder=%i",
                            $post_folder_id
                        );
                        if (DB::count() > 0) {
                            foreach ($rows_tmp as $row) {
                                array_push($arrCatList, $row['id_category']);
                            }

                            // get fields for this Item
                            $rows_tmp = DB::query(
                                "SELECT i.field_id AS field_id, i.data AS data, i.data_iv AS data_iv,
                                i.encryption_type AS encryption_type, c.encrypted_data, c.parent_id AS parent_id,
                                c.type as field_type, c.masked AS field_masked, c.role_visibility AS role_visibility
                                FROM ".prefix_table("categories_items")." AS i
                                INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id=c.id)
                                WHERE i.item_id=%i AND c.parent_id IN %ls",
                                $post_id,
                                $arrCatList
                            );
                            foreach ($rows_tmp as $row) {
                                // Uncrypt data
                                if ($row['encryption_type'] === "defuse") {
                                    $fieldText = cryption(
                                        $row['data'],
                                        "",
                                        "decrypt"
                                    );
                                    $fieldText = $fieldText['string'];
                                } else {
                                    $fieldText = $row['data'];
                                }

                                // Manage textarea string
                                if ($row['field_type'] === 'textarea') {
                                    $fieldText = nl2br($fieldText);
                                }
                                
                                // build returned list of Fields text
                                if (empty($fieldsTmp) === true) {
                                    $fieldsTmp = $row['field_id'].
                                        "~~".str_replace('"', '&quot;', $fieldText)."~~".$row['parent_id'].
                                        "~~".$row['field_type']."~~".$row['field_masked'];
                                } else {
                                    $fieldsTmp .= "_|_".$row['field_id'].
                                    "~~".str_replace('"', '&quot;', $fieldText)."~~".$row['parent_id'].
                                    "~~".$row['field_type']."~~".$row['field_masked'];
                                }
                            }
                        }
                    }

                    // Now get the selected template (if exists)
                    if (isset($SETTINGS['item_creation_templates']) && $SETTINGS['item_creation_templates'] === '1') {
                        $rows_tmp = DB::queryfirstrow(
                            "SELECT category_id
                            FROM ".prefix_table("templates")."
                            WHERE item_id = %i",
                            $post_id
                        );
                        if (DB::count() > 0) {
                            $template_id = $rows_tmp['category_id'];
                        }
                    }
                }
                $arrData['fields'] = $fieldsTmp;
                $arrData['categories'] = $arrCatList;
                $arrData['template_id'] = $template_id;

                // Manage user restriction
                if (null !== $post_restricted) {
                    $arrData['restricted'] = $post_restricted;
                } else {
                    $arrData['restricted'] = "";
                }
                // Decrement the number before being deleted
                if (isset($SETTINGS['enable_delete_after_consultation']) && $SETTINGS['enable_delete_after_consultation'] === '1') {
                    // Is the Item to be deleted?
                    $dataDelete = DB::queryfirstrow("SELECT * FROM ".prefix_table("automatic_del")." WHERE item_id=%i", $post_id);
                    $arrData['to_be_deleted'] = $dataDelete['del_value'];
                    $arrData['to_be_deleted_type'] = $dataDelete['del_type'];

                    // Now delete if required
                    if ($dataDelete['del_enabled'] === '1' || intval($arrData['id_user']) !== intval($_SESSION['user_id'])) {
                        if ($dataDelete['del_type'] === '1' && $dataDelete['del_value'] >= 1) {
                            // decrease counter
                            DB::update(
                                $pre."automatic_del",
                                array(
                                    'del_value' => $dataDelete['del_value'] - 1
                                    ),
                                "item_id = %i",
                                $post_id
                            );
                            // store value
                            $arrData['to_be_deleted'] = $dataDelete['del_value'] - 1;
                        } elseif ($dataDelete['del_type'] === '1' && $dataDelete['del_value'] <= 1 || $dataDelete['del_type'] === '2' && $dataDelete['del_value'] < time()
                        ) {
                            $arrData['show_details'] = 0;
                            // delete item
                            DB::delete($pre."automatic_del", "item_id = %i", $post_id);
                            // make inactive object
                            DB::update(
                                prefix_table("items"),
                                array(
                                    'inactif' => '1',
                                    ),
                                "id = %i",
                                $post_id
                            );
                            // log
                            logItems(
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
                        $arrData['to_be_deleted'] = "";
                    }
                } else {
                    $arrData['to_be_deleted'] = "not_enabled";
                }

                $arrData['notification_list'] = "";
                $arrData['notification_status'] = "";
            } else {
                $arrData['show_details'] = 0;
                // get readable list of restriction
                $listOfRestricted = "";
                if (empty($dataItem['restricted_to']) === false) {
                    foreach (explode(';', $dataItem['restricted_to']) as $userRest) {
                        if (empty($userRest) === false) {
                            $dataTmp = DB::queryfirstrow("SELECT login FROM ".prefix_table("users")." WHERE id= ".$userRest);
                            if (empty($listOfRestricted)) {
                                $listOfRestricted = $dataTmp['login'];
                            } else {
                                $listOfRestricted .= ";".$dataTmp['login'];
                            }
                        }
                    }
                }
                $arrData['restricted_to'] = $listOfRestricted;
            }

            // Set a timestamp
            $arrData['timestamp'] = time();

            // Set temporary session variable to allow step2
            $_SESSION['user_settings']['show_step2'] = true;
            
            // Encrypt data to return
            echo prepareExchangedData($arrData, "encode");
            break;

        /*
           * CASE
           * Display History of the selected Item
        */
        case "showDetailsStep2":
            // Is this query expected (must be run after a step1 and not standalone)
            if ($_SESSION['user_settings']['show_step2'] !== true) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo prepareExchangedData($returnValues, "encode");
                break;
            }

            // Load item data
            $dataItem = DB::queryFirstRow(
                "SELECT i.*, n.title AS folder_title
                FROM ".prefix_table("items")." AS i
                INNER JOIN ".prefix_table("nested_tree")." AS n ON (i.id_tree = n.id)
                WHERE i.id = %i",
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
                "SELECT role_id
                FROM ".prefix_table("restriction_to_roles")."
                WHERE item_id=%i",
                $post_id
            );
            foreach ($rows_tmp as $rec_tmp) {
                if (in_array($rec_tmp['role_id'], explode(';', $_SESSION['fonction_id']))) {
                    $restrictionActive = false;
                }
            }

            // check user is admin
            if ($_SESSION['user_admin'] === '1' && $dataItem['perso'] != 1 && (isset($SETTINGS_EXT['admin_full_right']) && $SETTINGS_EXT['admin_full_right'] === true) || !isset($SETTINGS_EXT['admin_full_right'])) {
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
                // GET Audit trail
                $history = "";
                $historyOfPws = "";
                $rows = DB::query(
                    "SELECT l.date as date, l.action as action, l.raison as raison, u.login as login, l.raison_iv AS raison_iv
                    FROM ".prefix_table("log_items")." as l
                    LEFT JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                    WHERE id_item=%i AND action <> %s
                    ORDER BY date ASC",
                    $post_id,
                    "at_shown"
                );
                foreach ($rows as $record) {
                    $reason = explode(':', $record['raison']);
                    if ($record['action'] === "at_modification" && $reason[0] === "at_pw ") {
                        // check if item is PF
                        if ($dataItem['perso'] != 1) {
                            $reason[1] = cryption(
                                $reason[1],
                                "",
                                "decrypt"
                            );
                        } else {
                            if (isset($_SESSION['user_settings']['session_psk']) === true) {
                                $reason[1] = cryption(
                                    $reason[1],
                                    $_SESSION['user_settings']['session_psk'],
                                    "decrypt"
                                );
                            } else {
                                $reason[1] = '';
                            }
                        }
                        $reason[1] = @$reason[1]['string'];
                        // if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
                        if (!isUTF8($reason[1]) || is_array($reason[1])) {
                            $reason[1] = "";
                        }
                    }
                    // imported via API
                    if (empty($record['login'])) {
                        $record['login'] = $LANG['imported_via_api'];
                    }

                    if (empty($reason[1]) === false || $record['action'] === "at_copy" || $record['action'] === "at_creation" || $record['action'] === "at_manual" || $record['action'] === "at_modification" || $record['action'] === "at_delete" || $record['action'] === "at_restored") {
                        if (trim($reason[0]) === "at_pw" && empty($reason[1]) === false) {
                            if (empty($historyOfPws)) {
                                $historyOfPws = $LANG['previous_pw']."\n".$reason[1];
                            } else {
                                $historyOfPws .= "\n".$reason[1];
                            }
                        }
                    }
                }

                // generate 2d key
                $_SESSION['key_tmp'] = bin2hex(PHP_Crypt::createKey(PHP_Crypt::RAND, 16));

                // Prepare files listing
                $files = $filesEdit = "";
                // launch query
                $rows = DB::query("SELECT id, name, file, extension FROM ".prefix_table("files")." WHERE id_item=%i", $post_id);
                foreach ($rows as $record) {
                    // get icon image depending on file format
                    $iconImage = fileFormatImage($record['extension']);

                    // prepare text to display
                    if (strlen($record['name']) > 60 && strrpos($record['name'], ".") >= 56) {
                        $filename = substr($record['name'], 0, 50)."(...)".substr($record['name'], strrpos($record['name'], "."));
                    } else {
                        $filename = $record['name'];
                    }

                    // If file is an image, then prepare lightbox. If not image, then prepare donwload
                    if (in_array($record['extension'], $SETTINGS_EXT['image_file_ext'])) {
                        $files .= '<div class=\'small_spacing\'><i class=\'fa fa-file-image-o\' /></i>&nbsp;<a class=\'image_dialog\' href=\'#'.$record['id'].'\' title=\''.$record['name'].'\'>'.$filename.'</a></div>';
                    } else {
                        $files .= '<div class=\'small_spacing\'><i class=\'fa fa-file-text-o\' /></i>&nbsp;<a href=\'sources/downloadFile.php?name='.urlencode($record['name']).'&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'&fileid='.$record['id'].'\' class=\'small_spacing\'>'.$filename.'</a></div>';
                    }
                    // Prepare list of files for edit dialogbox
                    $filesEdit .= '<span id=\'span_edit_file_'.$record['id'].'\'><span class=\'fa fa-'.$iconImage.'\'></span>&nbsp;<span id=\'delete-edit-file_'.$record['id'].'\' class=\'fa fa-eraser tip file-eraser_icon\' style=\'cursor:pointer;\' onclick=\'delete_attached_file("'.$record['id'].'", "0")\' title=\''.$LANG['at_delete'].'\'></span>&nbsp;'.$filename."</span><br />";
                }
                // display lists
                $filesEdit = str_replace('"', '&quot;', $filesEdit);
                $files_id = $files;

                // disable add bookmark if alread bookmarked
                if (in_array($post_id, $_SESSION['favourites'])) {
                    $favourite = 1;
                } else {
                    $favourite = 0;
                }

                // Add this item to the latests list
                if (isset($_SESSION['latest_items']) && isset($SETTINGS['max_latest_items']) && !in_array($dataItem['id'], $_SESSION['latest_items'])) {
                    if (count($_SESSION['latest_items']) >= $SETTINGS['max_latest_items']) {
                        array_pop($_SESSION['latest_items']); //delete last items
                    }
                    array_unshift($_SESSION['latest_items'], $dataItem['id']);
                    // update DB
                    DB::update(
                        prefix_table("users"),
                        array(
                            'latest_items' => implode(';', $_SESSION['latest_items'])
                            ),
                        "id=".$_SESSION['user_id']
                    );
                }

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
                        prefix_table('emails'),
                        array(
                            'timestamp' => time(),
                            'subject' => $LANG['email_on_open_notification_subject'],
                            'body' => str_replace(
                                array('#tp_user#', '#tp_item#', '#tp_link#'),
                                array(
                                    addslashes($_SESSION['login']),
                                    $path,
                                    $SETTINGS['cpassman_url']."/index.php?page=items&group=".$dataItem['id_tree']."&id=".$dataItem['id']
                                ),
                                $LANG['email_on_open_notification_mail']
                            ),
                            'receivers' => $_SESSION['listNotificationEmails'],
                            'status' => ''
                        )
                    );
                }

                // has this item a change proposal
                DB::query("SELECT * FROM ".$pre."items_change WHERE item_id = %i", $post_id);

                $_SESSION['user_settings']['show_step2'] = false;

                echo prepareExchangedData(
                    array(
                        "history" => htmlspecialchars($history, ENT_QUOTES, 'UTF-8'),
                        "history_of_pwds" => htmlspecialchars($historyOfPws, ENT_QUOTES, 'UTF-8'),
                        "favourite" => $favourite,
                        "files_edit" => $filesEdit,
                        "files_id" => $files_id,
                        "has_change_proposal" => DB::count(),
                        "error" => ""
                    ),
                    "encode"
                );
            }
            break;

        /*
         * CASE
         * Delete an item
        */
        case "del_item":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === true && !in_array($dataReceived['categorie'], $_SESSION['personal_folders'])) {
                echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                break;
            }

            // Check that user can access this item
            $granted = accessToItemIsGranted($post_id);
            if ($granted !== true) {
                echo prepareExchangedData(array("error" => $granted), "encode");
                break;
            }

            // Load item data
            $data = DB::queryFirstRow(
                "SELECT id_tree
                FROM ".prefix_table("items")."
                WHERE id = %i",
                $post_id
            );

            // delete item consists in disabling it
            DB::update(
                prefix_table("items"),
                array(
                    'inactif' => '1',
                    ),
                "id = %i",
                $post_id
            );
            // log
            logItems(
                $post_id,
                $post_label,
                $_SESSION['user_id'],
                'at_delete',
                $_SESSION['login']
            );
            // Update CACHE table
            updateCacheTable("delete_value", $post_id);
            break;

        /*
        * CASE
        * Update a Group
        */
        case "update_folder":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, "decode");

            // Prepare variables
            $title = filter_var(htmlspecialchars_decode($dataReceived['title'], ENT_QUOTES), FILTER_SANITIZE_STRING);
            $post_folder_id = filter_var(htmlspecialchars_decode($dataReceived['folder']), FILTER_SANITIZE_NUMBER_INT);

            // Check if user is allowed to access this folder
            if (!in_array($post_folder_id, $_SESSION['groupes_visibles'])) {
                echo '[{"error" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                break;
            }

            // Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                echo '[ { "error" : "'.addslashes($LANG['error_html_codes']).'" } ]';
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
                $data = DB::queryFirstRow("SELECT id, title FROM ".prefix_table("nested_tree")." WHERE title = %s", $title);
                if (empty($data['id']) === false && $dataReceived['folder'] != $data['id']) {
                    echo '[ { "error" : "'.addslashes($LANG['error_group_exist']).'" } ]';
                    break;
                }
            }

            // query on folder
            $data = DB::queryfirstrow(
                "SELECT parent_id, personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $post_folder_id
            );

            // check if complexity level is good
            // if manager or admin don't care
            if ($_SESSION['is_admin'] != 1 && $_SESSION['user_manager'] != 1 && $data['personal_folder'] === '0') {
                $data = DB::queryfirstrow(
                    "SELECT valeur
                    FROM ".prefix_table("misc")."
                    WHERE intitule = %i AND type = %s",
                    $data['parent_id'],
                    "complex"
                );
                if (intval($dataReceived['complexity']) < intval($data['valeur'])) {
                    echo '[ { "error" : "'.addslashes($LANG['error_folder_complexity_lower_than_top_folder']." [<b>".$SETTINGS_EXT['pwComplexity'][$data['valeur']][1]).'</b>]"} ]';
                    break;
                }
            }

            // update Folders table
            $tmp = DB::queryFirstRow(
                "SELECT title, parent_id, personal_folder FROM ".prefix_table("nested_tree")." WHERE id = %i",
                $dataReceived['folder']
            );
            if ($tmp['parent_id'] != 0 || $tmp['title'] != $_SESSION['user_id'] || $tmp['personal_folder'] != 1) {
                DB::update(
                    prefix_table("nested_tree"),
                    array(
                        'title' => $title
                        ),
                    'id=%s',
                    $post_folder_id
                );
                // update complixity value
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => $dataReceived['complexity']
                        ),
                    'intitule = %s AND type = %s',
                    $post_folder_id,
                    "complex"
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
        case "move_folder":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                $returnValues = '[{"error" :  "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, "decode");
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
                $returnValues = '[{"error" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            $tmp_source = DB::queryFirstRow(
                "SELECT title, parent_id, personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $post_source_folder_id
            );

            $tmp_target = DB::queryFirstRow(
                "SELECT title, parent_id, personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $post_target_folder_id
            );

            // check if target is not a child of source
            if ($tree->isChildOf($post_target_folder_id, $post_source_folder_id) === true) {
                $returnValues = '[{"error" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // check if source or target folder is PF. If Yes, then cancel operation
            if ($tmp_source['personal_folder'] === '1' || $tmp_target['personal_folder'] === '1') {
                $returnValues = '[{"error" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // check if source or target folder is PF. If Yes, then cancel operation
            if ($tmp_source['title'] === $_SESSION['user_id'] || $tmp_target['title'] === $_SESSION['user_id']) {
                $returnValues = '[{"error" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }


            // moving SOURCE folder
            DB::update(
                prefix_table("nested_tree"),
                array(
                    'parent_id' => $post_target_folder_id
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
                prefix_table("nested_tree"),
                array(
                    'parent_id' => $post_destination
                    ),
                'id = %i',
                $post_source
            );
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
            $tree->rebuild();
            break;

        /*
        * CASE
        * List items of a group
        */
        case 'lister_items_groupe':
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.str_replace('"', '\"', $LANG['error_not_allowed_to']).'"}]';
                echo prepareExchangedData($returnValues, "encode");
                break;
            }

            // Prepare POST variables
            $post_restricted = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_NUMBER_INT);
            $post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);
            $post_nb_items_to_display_once = filter_input(INPUT_POST, 'nb_items_to_display_once', FILTER_SANITIZE_NUMBER_INT);

            $arboHtml = $html = "";
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
            if (intval($start) === 0) {
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
                            "id" => $elem->id,
                            "title" => htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES),
                            "visible" => in_array($elem->id, $_SESSION['groupes_visibles']) ? 1 : 0
                        )
                    );
                }

                // store last folder accessed in cookie
                setcookie(
                    "jstree_select",
                    $post_id,
                    time() + $SETTINGS_EXT['one_day_seconds'] * $SETTINGS['personal_saltkey_cookie_duration'],
                    '/'
                );

                // check role access on this folder (get the most restrictive) (2.1.23)
                $accessLevel = 2;
                $arrTmp = array();
                foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                    if (empty($role) === false) {
                        $access = DB::queryFirstRow(
                            "SELECT type FROM ".prefix_table("roles_values")." WHERE role_id = %i AND folder_id = %i",
                            $role,
                            $post_id
                        );
                        if ($access['type'] === "R") {
                            array_push($arrTmp, 1);
                        } elseif ($access['type'] === "W") {
                            array_push($arrTmp, 0);
                        } elseif ($access['type'] === "ND") {
                            array_push($arrTmp, 2);
                        } else {
                            // Ensure to give access Right if allowed folder
                            if (in_array($post_id, $_SESSION['groupes_visibles']) === true) {
                                array_push($arrTmp, 0);
                            } else {
                                array_push($arrTmp, 3);
                            }
                        }
                    }
                }
                $accessLevel = $uniqueLoadData['accessLevel'] = count($arrTmp) > 0 ? min($arrTmp) : $accessLevel;

                // check if this folder is a PF. If yes check if saltket is set
                if ((!isset($_SESSION['user_settings']['encrypted_psk']) || empty($_SESSION['user_settings']['encrypted_psk'])) && $folderIsPf === true) {
                    $showError = "is_pf_but_no_saltkey";
                }
                $uniqueLoadData['showError'] = $showError;

                // check if items exist
                $where = new WhereClause('and');
                if (null !== $post_restricted && $post_restricted === 1 && empty($_SESSION['list_folders_limited'][$post_id]) === false) {
                    $counter = count($_SESSION['list_folders_limited'][$post_id]);
                    $uniqueLoadData['counter'] = $counter;
                // check if this folder is visible
                } elseif (in_array(
                    $post_id,
                    array_merge(
                        $_SESSION['groupes_visibles'],
                        @array_keys($_SESSION['list_restricted_folders_for_items']),
                        @array_keys($_SESSION['list_folders_limited'])
                    )
                ) === false) {
                    echo prepareExchangedData(
                        array(
                            "error" => "not_authorized",
                            "arborescence" => $arr_arbo
                        ),
                        "encode"
                    );
                    break;
                } else {
                    DB::query("SELECT * FROM ".prefix_table("items")." WHERE inactif = %i", 0);
                    $counter = DB::count();
                    $uniqueLoadData['counter'] = $counter;
                }

                // Identify if it is a personal folder
                if (in_array($post_id, $_SESSION['personal_visible_groups'])) {
                    $findPfGroup = 1;
                } else {
                    $findPfGroup = "";
                }
                $uniqueLoadData['findPfGroup'] = $findPfGroup;


                // Get folder complexity
                $folderComplexity = DB::queryFirstRow(
                    "SELECT valeur FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %i",
                    "complex",
                    $post_id
                );
                $folderComplexity = $folderComplexity['valeur'];
                $uniqueLoadData['folderComplexity'] = $folderComplexity;

                // Has this folder some categories to be displayed?
                $displayCategories = "";
                if (isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1') {
                    $catRow = DB::query(
                        "SELECT id_category FROM ".prefix_table("categories_folders")." WHERE id_folder = %i",
                        $post_id
                    );
                    if (count($catRow) > 0) {
                        foreach ($catRow as $cat) {
                            if (empty($displayCategories)) {
                                $displayCategories = $cat['id_category'];
                            } else {
                                $displayCategories .= ";".$cat['id_category'];
                            }
                        }
                    }
                }
                $uniqueLoadData['displayCategories'] = $displayCategories;

                // is this folder a personal one
                $folder_is_personal = in_array($post_id, $_SESSION['personal_folders']);
                $uniqueLoadData['folder_is_personal'] = $folder_is_personal;

                //
                $folder_is_in_personal = in_array($post_id, array_merge($_SESSION['personal_visible_groups'], $_SESSION['personal_folders']));
                $uniqueLoadData['folder_is_in_personal'] = $folder_is_in_personal;

                //
                if (isset($_SESSION['list_folders_editable_by_role'])) {
                    $list_folders_editable_by_role = in_array($post_id, $_SESSION['list_folders_editable_by_role']);
                } else {
                    $list_folders_editable_by_role = "";
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
                $findPfGroup = $uniqueLoadData['findPfGroup'];
                $counter_full = $uniqueLoadData['counter_full'];
                $displayCategories = $uniqueLoadData['displayCategories'];
                $folderComplexity = $uniqueLoadData['folderComplexity'];
                //$arboHtml = $uniqueLoadData['arboHtml'];
                $folder_is_personal = $uniqueLoadData['folder_is_personal'];
                $folder_is_in_personal = $uniqueLoadData['folder_is_in_personal'];
                $list_folders_editable_by_role = $uniqueLoadData['list_folders_editable_by_role'];
            }

            // prepare query WHere conditions
            $where = new WhereClause('and');
            if (null !== $post_restricted && $post_restricted === 1 && empty($_SESSION['list_folders_limited'][$post_id]) === false) {
                $where->add('i.id IN %ls', $_SESSION['list_folders_limited'][$post_id]);
            } else {
                $where->add('i.id_tree=%i', $post_id);
            }
            
            // build the HTML for this set of Items
            if ($counter > 0 && empty($showError) === true) {
                // init variables
                $init_personal_folder = false;
                $expired_item = false;
                $limited_to_items = "";
                // List all ITEMS
                if ($folderIsPf === false) {
                    $where->add('i.inactif=%i', 0);
                    $where->add('l.date=%l', "(SELECT date FROM ".prefix_table("log_items")." WHERE action IN ('at_creation', 'at_modification') AND id_item=i.id ORDER BY date DESC LIMIT 1)");
                    if (empty($limited_to_items) === false) {
                        $where->add('i.id IN %ls', explode(",", $limited_to_items));
                    }

                    // Prepare limit for query
                    if (isset($SETTINGS['nb_items_by_query']) === true
                        && $SETTINGS['nb_items_by_query'] !== 'max'
                    ) {
                        $query_limit = " LIMIT ".
                            $start.",".
                            $post_nb_items_to_display_once;
                    } else {
                        $query_limit = '';
                    }
                        
                    $rows = DB::query(
                        "SELECT i.id AS id, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                        MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                        MIN(i.anyone_can_modify) AS anyone_can_modify, l.date AS date, i.id_tree AS tree_id,
                        MIN(n.renewal_period) AS renewal_period,
                        MIN(l.action) AS log_action, l.id_user AS log_user
                        FROM ".prefix_table("items")." AS i
                        INNER JOIN ".prefix_table("nested_tree")." AS n ON (i.id_tree = n.id)
                        INNER JOIN ".prefix_table("log_items")." AS l ON (i.id = l.id_item)
                        WHERE %l
                        GROUP BY i.id, l.date, l.id_user, l.action
                        ORDER BY i.label ASC, l.date DESC".$query_limit, //
                        $where
                    );
                } else {
                    $post_nb_items_to_display_once = -999;
                    $where->add('i.inactif=%i', 0);

                    $rows = DB::query(
                        "SELECT i.id AS id, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                        MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                        MIN(i.anyone_can_modify) AS anyone_can_modify,l.date AS date, i.id_tree AS tree_id,
                        MIN(n.renewal_period) AS renewal_period,
                        MIN(l.action) AS log_action, l.id_user AS log_user
                        FROM ".prefix_table("items")." AS i
                        INNER JOIN ".prefix_table("nested_tree")." AS n ON (i.id_tree = n.id)
                        INNER JOIN ".prefix_table("log_items")." AS l ON (i.id = l.id_item)
                        WHERE %l
                        GROUP BY i.id, l.date, l.id_user, l.action
                        ORDER BY i.label ASC, l.date DESC",
                        $where
                    );
                }

                $idManaged = '';
                $i = 0;
                $arr_items_html = array();
                
                foreach ($rows as $record) {
                    // exclude all results except the first one returned by query
                    if (empty($idManaged) === true || $idManaged !== $record['id']) {
                        // Get Expiration date
                        $expired_item = 0;
                        if ((int) $SETTINGS['activate_expiration'] === 1) {
                            if ($record['renewal_period'] > 0 &&
                                ($record['date'] + ($record['renewal_period'] * $SETTINGS_EXT['one_month_seconds'])) < time()
                            ) {
                                $html_json[$record['id']]['expiration_flag'] = "mi-red";
                                $expired_item = 1;
                            } else {
                                if ((int) $record['perso'] !== 1) {
                                    $html_json[$record['id']]['expiration_flag'] = "mi-green";
                                } else {
                                    $html_json[$record['id']]['expiration_flag'] = "";
                                }
                            }
                        }
                        // Init
                        $html_json[$record['id']]['expired'] = $expired_item;
                        $html_json[$record['id']]['item_id'] = $record['id'];
                        $html_json[$record['id']]['tree_id'] = $record['tree_id'];
                        $html_json[$record['id']]['label'] = mb_convert_encoding($record['label'], "UTF-8", "auto");
                        if (isset($SETTINGS['show_description']) === true && $SETTINGS['show_description'] === '1') {
                            $html_json[$record['id']]['desc'] = strip_tags(cleanString(explode("<br>", $record['description'])[0]));
                        } else {
                            $html_json[$record['id']]['desc'] = "";
                        }

                        // list of restricted users
                        $is_user_in_restricted_list = in_array($_SESSION['user_id'], explode(';', $record['restricted_to']));

                        $itemPw = $itemLogin = "";
                        $displayItem = false;
                        $need_sk = false;
                        $canMove = false;
                        $item_is_restricted_to_role = false;

                        // TODO: Element is restricted to a group. Check if element can be seen by user
                        // => récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
                        $user_is_included_in_role = false;
                        $roles = DB::query(
                            "SELECT role_id FROM ".prefix_table("restriction_to_roles")." WHERE item_id=%i",
                            $record['id']
                        );
                        if (DB::count() > 0) {
                            $item_is_restricted_to_role = true;
                            foreach ($roles as $val) {
                                if (in_array($val['role_id'], $_SESSION['user_roles'])) {
                                    $user_is_included_in_role = true;
                                    break;
                                }
                            }
                        }

                        // Manage the restricted_to variable
                        if (null !== $post_restricted) {
                            $restrictedTo = $post_restricted;
                        } else {
                            $restrictedTo = "";
                        }

                        if ($list_folders_editable_by_role === true) {
                            if (empty($restrictedTo)) {
                                $restrictedTo = $_SESSION['user_id'];
                            } else {
                                $restrictedTo .= ','.$_SESSION['user_id'];
                            }
                        }
                        $html_json[$record['id']]['restricted'] = $restrictedTo;

                        // Can user modify it?
                        if (($record['anyone_can_modify'] === '1' && $_SESSION['user_read_only'] !== '1')
                            || $_SESSION['user_id'] === $record['log_user']
                            || ($_SESSION['user_read_only'] === '1' && $folderIsPf === false)
                            || (isset($SETTINGS['manager_edit']) && $SETTINGS['manager_edit'] === '1') // force draggable if user is manager
                        ) {
                            $canMove = true;
                        }

                        // Fix a bug on Personal Item creation - field `perso` must be set to `1`
                        if ($record['perso'] !== '1' && (int) $folder_is_personal === 1) {
                            DB::update(
                                prefix_table("items"),
                                array(
                                    'perso' => 1
                                ),
                                "id=%i",
                                $record['id']
                            );
                            $record['perso'] = '1';
                        }

                        // Now check 'list_restricted_folders_for_items'
                        $item_limited_access = false;
                        if (in_array($post_id, array_keys($_SESSION['list_restricted_folders_for_items'])) === true
                            && in_array($post_id, array_keys($_SESSION['list_folders_limited'])) === false
                        ) {
                            if (in_array($record['id'], $_SESSION['list_restricted_folders_for_items'][$post_id]) === false) {
                                $item_limited_access = true;
                            }
                        }

                        // CASE where item is restricted to a role to which the user is not associated
                        if (isset($user_is_included_in_role) === true
                            && $user_is_included_in_role === false
                            && isset($item_is_restricted_to_role) === true
                            && $item_is_restricted_to_role === true
                            && $is_user_in_restricted_list === false
                            && (int) $folder_is_personal !== 1
                        ) {
                            $html_json[$record['id']]['perso'] = "fa-tag mi-red";
                            $html_json[$record['id']]['sk'] = 0;
                            $html_json[$record['id']]['display'] = "no_display";
                            $html_json[$record['id']]['open_edit'] = 0;
                            $html_json[$record['id']]['reload'] = "";
                            $html_json[$record['id']]['accessLevel'] = 3;
                            $html_json[$record['id']]['canMove'] = 0;
                            $findPfGroup = 0;
                            $displayItem = false;
                            $need_sk = false;
                            $canMove = false;
                        // Case where item is in own personal folder
                        } elseif ((int) $folder_is_in_personal === 1
                            && (int) $record['perso'] === 1
                        ) {
                            $html_json[$record['id']]['perso'] = "fa-user-secret mi-grey-1";
                            $findPfGroup = 1;
                            $displayItem = true;
                            $need_sk = true;
                            $canMove = true;

                            $html_json[$record['id']]['sk'] = 1;
                            $html_json[$record['id']]['display'] = "";
                            $html_json[$record['id']]['open_edit'] = 1;
                            $html_json[$record['id']]['reload'] = "";
                            $html_json[$record['id']]['accessLevel'] = 0;
                            $html_json[$record['id']]['canMove'] = 1;
                        // CAse where item is restricted to a group of users included user
                        } elseif ((empty($record['restricted_to']) === false
                            || $list_folders_editable_by_role === true)
                            && $is_user_in_restricted_list === true
                        ) {
                            $html_json[$record['id']]['perso'] = "fa-tag mi-yellow";
                            $findPfGroup = 0;
                            $displayItem = true;
                            $canMove = true;
                            $html_json[$record['id']]['sk'] = 0;
                            $html_json[$record['id']]['display'] = "";
                            $html_json[$record['id']]['open_edit'] = 1;
                            $html_json[$record['id']]['reload'] = "";
                            $html_json[$record['id']]['accessLevel'] = 0;
                            $html_json[$record['id']]['canMove'] = ($_SESSION['user_read_only'] === '1' && $folderIsPf === false) ? 0 : 1;
                        // CAse where item is restricted to a group of users not including user
                        } elseif ((int) $record['perso'] === 1
                            ||
                            (
                                empty($record['restricted_to']) === false
                                && $is_user_in_restricted_list === false
                            )
                            ||
                            (
                                isset($user_is_included_in_role) === true
                                && isset($item_is_restricted_to_role) === true
                                && $user_is_included_in_role === false
                                && $item_is_restricted_to_role === true
                            )
                        ) {
                            if (isset($user_is_included_in_role) === true
                                && isset($item_is_restricted_to_role) === true
                                && $user_is_included_in_role === false
                                && $item_is_restricted_to_role === true
                            ) {
                                $html_json[$record['id']]['perso'] = "fa-tag mi-red";
                                $displayItem = false;
                                $need_sk = true;
                                $canMove = false;

                                $html_json[$record['id']]['sk'] = 0;
                                $html_json[$record['id']]['display'] = "no_display";
                                $html_json[$record['id']]['open_edit'] = 0;
                                $html_json[$record['id']]['reload'] = "";
                                $html_json[$record['id']]['accessLevel'] = 3;
                                $html_json[$record['id']]['canMove'] = 0;
                            } else {
                                $html_json[$record['id']]['perso'] = "fa-tag mi-yellow";
                                // reinit in case of not personal group
                                if ($init_personal_folder === false) {
                                    $findPfGroup = "";
                                    $init_personal_folder = true;
                                }

                                if (empty($record['restricted_to']) === false && $is_user_in_restricted_list === true) {
                                    $displayItem = true;
                                }

                                $html_json[$record['id']]['sk'] = 0;
                                $html_json[$record['id']]['display'] = "";
                                $html_json[$record['id']]['open_edit'] = 0;
                                $html_json[$record['id']]['reload'] = "";
                                $html_json[$record['id']]['accessLevel'] = 0;
                                $html_json[$record['id']]['canMove'] = 0;
                            }
                        } else {
                            $html_json[$record['id']]['perso'] = "fa-tag mi-green";
                            $displayItem = true;
                            // reinit in case of not personal group
                            if ($init_personal_folder === false) {
                                $findPfGroup = "";
                                $init_personal_folder = true;
                            }

                            $html_json[$record['id']]['sk'] = 0;
                            $html_json[$record['id']]['display'] = $item_limited_access === true ? "no_display" : "";
                            $html_json[$record['id']]['open_edit'] = 1;
                            $html_json[$record['id']]['reload'] = "";
                            $html_json[$record['id']]['accessLevel'] = $item_limited_access === true ? 0 : $accessLevel;
                            $html_json[$record['id']]['canMove'] = $accessLevel === 0 ? (($_SESSION['user_read_only'] === '1' && $folderIsPf === false) ? 0 : 1) : $canMove;
                        }

                        $html_json[$record['id']]['pw_status'] = "";

                        // increment array for icons shortcuts (don't do if option is not enabled)
                        if (isset($SETTINGS['copy_to_clipboard_small_icons']) && $SETTINGS['copy_to_clipboard_small_icons'] === '1') {
                            if ($need_sk === true && isset($_SESSION['user_settings']['session_psk'])) {
                                $pw = cryption(
                                    $record['pw'],
                                    $_SESSION['user_settings']['session_psk'],
                                    "decrypt"
                                );
                            } else {
                                $pw = cryption(
                                    $record['pw'],
                                    "",
                                    "decrypt"
                                );
                            }

                            // test charset => may cause a json error if is not utf8
                            $pw = $pw['string'];
                            if (isUTF8($pw) === false) {
                                $pw = "";
                                $html_json[$record['id']]['pw_status'] = "encryption_error";
                            }
                        } else {
                            $pw = "";
                        }
                        $html_json[$record['id']]['pw'] = $pw;
                        $html_json[$record['id']]['login'] = utf8_encode($record['login']);
                        $html_json[$record['id']]['anyone_can_modify'] = isset($SETTINGS['anyone_can_modify']) ? $SETTINGS['anyone_can_modify'] : '0';
                        $html_json[$record['id']]['copy_to_clipboard_small_icons'] = isset($SETTINGS['copy_to_clipboard_small_icons']) ? $SETTINGS['copy_to_clipboard_small_icons'] : '0';
                        $html_json[$record['id']]['display_item'] = $displayItem === true ? 1 : 0;
                        $html_json[$record['id']]['enable_favourites'] = isset($SETTINGS['enable_favourites']) ? $SETTINGS['enable_favourites'] : '0';

                        // Prepare make Favorite small icon
                        if (in_array($record['id'], $_SESSION['favourites'])) {
                            $html_json[$record['id']]['in_favorite'] = 1;
                        } else {
                            $html_json[$record['id']]['in_favorite'] = 0;
                        }

                        // Build array with items
                        array_push($itemsIDList, array($record['id'], $pw, utf8_encode($record['login']), $displayItem));//

                        $i++;
                    }
                    $idManaged = $record['id'];
                }

                $rights = recupDroitCreationSansComplexite($post_id);
            }

            // DELETE - 2.1.19 - AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
            // count
            if (intval($start) === 0) {
                DB::query(
                    "SELECT i.id
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                    INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                    WHERE %l
                    ORDER BY i.label ASC, l.date DESC",
                    $where
                );
                $counter_full = DB::count();
                $uniqueLoadData['counter_full'] = $counter_full;
            }
            
            // Check list to be continued status
            if ((int) $post_nb_items_to_display_once !== -999 && ($post_nb_items_to_display_once + $start) < $counter_full) {
                $listToBeContinued = "yes";
            } else {
                $listToBeContinued = "end";
            }
            // Prepare returned values
            $returnValues = array(
                "html_json" => $html_json,
                "recherche_group_pf" => $findPfGroup,
                "arborescence" => $arr_arbo,
                "array_items" => $itemsIDList,
                "error" => $showError,
                "saltkey_is_required" => $folderIsPf === true ? 1 : 0,
                "show_clipboard_small_icons" => isset($SETTINGS['copy_to_clipboard_small_icons']) && $SETTINGS['copy_to_clipboard_small_icons'] === '1' ? 1 : 0,
                "next_start" => intval($post_nb_items_to_display_once) + intval($start),
                "list_to_be_continued" => $listToBeContinued,
                "items_count" => $counter,
                "counter_full" => $counter_full,
                'folder_complexity' => $folderComplexity,
                'displayCategories' => $displayCategories,
                'access_level' => $accessLevel,
                'IsPersonalFolder' => $folderIsPf === true ? 1 : 0,
                'uniqueLoadData' => json_encode($uniqueLoadData)
            );
            // Check if $rights is not null
            if (count($rights) > 0) {
                $returnValues = array_merge($returnValues, $rights);
            }
            //print_r($returnValues);
            
            // Encrypt data to return
            echo prepareExchangedData($returnValues, "encode");

            break;

        /*
        * CASE
        * Get complexity level of a group
        */
        case "get_complixity_level":
            // Prepare POST variables
            $post_groupe = filter_input(INPUT_POST, 'groupe', FILTER_SANITIZE_STRING);
            $post_context = filter_input(INPUT_POST, 'context', FILTER_SANITIZE_STRING);

            // get some info about ITEM
            $dataItem = DB::queryfirstrow(
                "SELECT perso, anyone_can_modify
                FROM ".prefix_table("items")."
                WHERE id=%i",
                $post_item_id
            );
            // is user allowed to access this folder - readonly
            if (null !== $post_groupe && empty($post_groupe) === false) {
                if (in_array($post_groupe, $_SESSION['read_only_folders']) || !in_array($post_groupe, $_SESSION['groupes_visibles'])) {
                    // check if this item can be modified by anyone
                    if (isset($SETTINGS['anyone_can_modify']) && $SETTINGS['anyone_can_modify'] === '1') {
                        if ($dataItem['anyone_can_modify'] != 1) {
                            // else return not authorized
                            $returnValues = array(
                                "error" => "user_is_readonly",
                                "message" => $LANG['error_not_allowed_to']
                            );
                            echo prepareExchangedData($returnValues, "encode");
                            break;
                        }
                    } else {
                        // else return not authorized
                        $returnValues = array(
                            "error" => "user_is_readonly",
                            "message" => $LANG['error_not_allowed_to']
                        );
                        echo prepareExchangedData($returnValues, "encode");
                        break;
                    }
                }
            }

            if (null !== $post_item_id && empty($post_item_id) === false) {
                // Lock Item (if already locked), go back and warn
                $dataTmp = DB::queryFirstRow("SELECT timestamp, user_id FROM ".prefix_table("items_edition")." WHERE item_id = %i", $post_item_id);

                // If token is taken for this Item and delay is passed then delete it.
                if (isset($SETTINGS['delay_item_edition']) &&
                    $SETTINGS['delay_item_edition'] > 0 && empty($dataTmp['timestamp']) === false &&
                    round(abs(time() - $dataTmp['timestamp']) / 60, 2) > $SETTINGS['delay_item_edition']
                ) {
                    DB::delete(prefix_table("items_edition"), "item_id = %i", $post_item_id);
                    //reload the previous data
                    $dataTmp = DB::queryFirstRow(
                        "SELECT timestamp, user_id FROM ".prefix_table("items_edition")." WHERE item_id = %i",
                        $post_item_id
                    );
                }

                // If edition by same user (and token not freed before for any reason, then update timestamp)
                if (empty($dataTmp['timestamp']) === false && $dataTmp['user_id'] == $_SESSION['user_id']) {
                    DB::update(
                        prefix_table("items_edition"),
                        array(
                            "timestamp" => time()
                        ),
                        "user_id = %i AND item_id = %i",
                        $_SESSION['user_id'],
                        $post_item_id
                    );
                    // If no token for this Item, then initialize one
                } elseif (empty($dataTmp[0])) {
                    DB::insert(
                        prefix_table("items_edition"),
                        array(
                            'timestamp' => time(),
                            'item_id' => $post_item_id,
                            'user_id' => $_SESSION['user_id']
                        )
                    );
                    // Edition not possible
                } else {
                    $returnValues = array(
                        "error" => "no_edition_possible",
                        "error_msg" => addslashes($LANG['error_no_edition_possible_locked'])
                    );
                    echo prepareExchangedData($returnValues, "encode");
                    break;
                }
            }

            // do query on this folder
            $data_this_folder = DB::queryFirstRow(
                "SELECT id, personal_folder, title
                FROM ".prefix_table("nested_tree")."
                WHERE id = %s",
                $post_groupe
            );

            // check if user can perform this action
            if (null !== $post_context
                && empty($post_context) === false
            ) {
                if ($post_context === "create_folder"
                    || $post_context === "edit_folder"
                    || $post_context === "delete_folder"
                    || $post_context === "copy_folder"
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
                            "error" => "no_folder_creation_possible",
                            "error_msg" => addslashes($LANG['error_not_allowed_to'])
                        );
                        echo prepareExchangedData($returnValues, "encode");
                        break;
                    }
                }
            }

            // Get required Complexity for this Folder
            $data = DB::queryFirstRow(
                "SELECT m.valeur, n.personal_folder
                FROM ".prefix_table("misc")." AS m
                INNER JOIN ".prefix_table("nested_tree")." AS n ON (m.intitule = n.id)
                WHERE type=%s AND intitule = %s",
                "complex",
                $post_groupe
            );

            if (isset($data['valeur']) === true && (empty($data['valeur']) === false || $data['valeur'] === '0')) {
                $complexity = $SETTINGS_EXT['pwComplexity'][$data['valeur']][1];
                $folder_is_personal = $data['personal_folder'];
            } else {
                $complexity = $LANG['not_defined'];

                // if not defined, then previous query failed and personal_folder is null
                // do new query to know if current folder is pf
                $data_pf = DB::queryFirstRow(
                    "SELECT personal_folder
                    FROM ".prefix_table("nested_tree")."
                    WHERE id = %s",
                    $post_groupe
                );
                $folder_is_personal = $data_pf['personal_folder'];
            }
            // Prepare Item actual visibility (what Users/Roles can see it)
            $visibilite = "";
            if (empty($dataPf[0]) === false) {
                $visibilite = $_SESSION['login'];
            } else {
                $rows = DB::query(
                    "SELECT t.title
                    FROM ".prefix_table("roles_values")." as v
                    INNER JOIN ".prefix_table("roles_title")." as t ON (v.role_id = t.id)
                    WHERE v.folder_id = %i
                    GROUP BY title",
                    $post_groupe
                );
                foreach ($rows as $record) {
                    if (empty($visibilite)) {
                        $visibilite = $record['title'];
                    } else {
                        $visibilite .= " - ".$record['title'];
                    }
                }
            }

            recupDroitCreationSansComplexite($post_groupe);

            $returnValues = array(
                "error" => "",
                "val" => $data['valeur'],
                "visibility" => $visibilite,
                "complexity" => $complexity,
                "personal" => $folder_is_personal
            );
            echo prepareExchangedData($returnValues, "encode");
            break;

        /*
        * CASE
        * DELETE attached file from an item
        */
        case "delete_attached_file":
            // Get some info before deleting
            $data = DB::queryFirstRow(
                "SELECT name, id_item, file
                FROM ".prefix_table("files")."
                WHERE id = %i",
                filter_input(INPUT_POST, 'file_id', FILTER_SANITIZE_NUMBER_INT)
            );

            // Load item data
            $data_item = DB::queryFirstRow(
                "SELECT id_tree
                FROM ".prefix_table("items")."
                WHERE id = %i",
                $data['id_item']
            );

            // Check that user can access this folder
            if (!in_array($data_item['id_tree'], $_SESSION['groupes_visibles'])) {
                echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                break;
            }

            if (empty($data['id_item']) === false) {
                // Delete from FILES table
                DB::delete(
                    prefix_table("files"),
                    "id = %i",
                    filter_input(INPUT_POST, 'file_id', FILTER_SANITIZE_NUMBER_INT)
                );
                // Update the log
                logItems(
                    $data['id_item'],
                    $data['name'],
                    $_SESSION['user_id'],
                    'at_modification',
                    $_SESSION['login'],
                    'at_del_file : '.$data['name']
                );
                // Delete file from server
                fileDelete($SETTINGS['path_to_upload_folder']."/".$data['file']);
            }
            break;

        /*
        * CASE
        * Clear HTML tags
        */
        case "clear_html_tags":
            // Get information for this item
            $dataItem = DB::queryfirstrow(
                "SELECT description FROM ".prefix_table("items")." WHERE id=%i",
                filter_input(INPUT_POST, 'id_item', FILTER_SANITIZE_NUMBER_INT)
            );
            // Clean up the string
            echo json_encode(array("description" => strip_tags($dataItem['description'])), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            break;

        /*
        * FUNCTION
        * Launch an action when clicking on a quick icon
        * $action = 0 => Make not favorite
        * $action = 1 => Make favorite
        */
        case "action_on_quick_icon":
            if (filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) === '1') {
                // Add new favourite
                array_push($_SESSION['favourites'], $post_id);
                DB::update(
                    prefix_table("users"),
                    array(
                        'favourites' => implode(';', $_SESSION['favourites'])
                        ),
                    'id = %i',
                    $_SESSION['user_id']
                );
                // Update SESSION with this new favourite
                $data = DB::queryfirstrow(
                    "SELECT label,id_tree
                    FROM ".prefix_table("items")."
                    WHERE id = ".mysqli_real_escape_string($link, $post_id)
                );
                $_SESSION['favourites_tab'][$post_id] = array(
                    'label' => $data['label'],
                    'url' => 'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$post_id
                    );
            } elseif (filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) === '0') {
                // delete from session
                foreach ($_SESSION['favourites'] as $key => $value) {
                    if ($_SESSION['favourites'][$key] === $post_id) {
                        unset($_SESSION['favourites'][$key]);
                        break;
                    }
                }
                // delete from DB
                DB::update(
                    prefix_table("users"),
                    array(
                        "favourites" =>implode(';', $_SESSION['favourites'])
                    ),
                    "id = %i",
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
        case "move_item":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']
                || $_SESSION['user_read_only'] === true || !isset($SETTINGS['pwd_maximum_length'])
            ) {
                // error
                exit();
            }

            // get data about item
            $dataSource = DB::queryfirstrow(
                "SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("nested_tree")." as f ON (i.id_tree=f.id)
                WHERE i.id=%i",
                $post_item_id
            );

            // get data about new folder
            $dataDestination = DB::queryfirstrow(
                "SELECT personal_folder, title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                $post_folder_id
            );

            // Do quick check if psh is required
            if ((isset($_SESSION['user_settings']['session_psk']) === false || empty($_SESSION['user_settings']['session_psk']) === true)
                && ($dataSource['personal_folder'] === '1' || $dataDestination['personal_folder'] === '1')
            ) {
                    echo '[{"error" : "ERR_PSK_REQUIRED"}]';
                break;
            }

            // Check that user can access this folder
            if (!in_array($dataSource['id_tree'], $_SESSION['groupes_visibles'])
                || !in_array($post_folder_id, $_SESSION['groupes_visibles'])
            ) {
                echo '[{"error" : "ERR_FOLDER_NOT_ALLOWED"}]';
                break;
            }

            // previous is non personal folder and new too
            if ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '0') {
                // just update is needed. Item key is the same
                DB::update(
                    prefix_table("items"),
                    array(
                        'id_tree' => $post_folder_id
                        ),
                    "id=%i",
                    $post_item_id
                );
            } elseif ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '1') {
                $decrypt = cryption(
                    $dataSource['pw'],
                    "",
                    "decrypt"
                );
                $encrypt = cryption(
                    $decrypt['string'],
                    mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                    "encrypt"
                );
                // update pw
                DB::update(
                    prefix_table("items"),
                    array(
                        'id_tree' => $post_folder_id,
                        'pw' => $encrypt['string'],
                        'pw_iv' => "",
                        'perso' => 1
                    ),
                    "id=%i",
                    $post_item_id
                );
            // If previous is personal folder and new is personal folder too => no key exist on item
            } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '1') {
                // just update is needed. Item key is the same
                DB::update(
                    prefix_table("items"),
                    array(
                        'id_tree' => $post_folder_id
                    ),
                    "id=%i",
                    $post_item_id
                );
            // If previous is personal folder and new is not personal folder => no key exist on item => add new
            } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '0') {
                $decrypt = cryption(
                    $dataSource['pw'],
                    mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                    "decrypt"
                );
                $encrypt = cryption(
                    $decrypt['string'],
                    "",
                    "encrypt"
                );

                // update item
                DB::update(
                    prefix_table("items"),
                    array(
                        'id_tree' => $post_folder_id,
                        'pw' => $encrypt['string'],
                        'pw_iv' => "",
                        'perso' => 0
                    ),
                    "id=%i",
                    $post_item_id
                );
            }
            // Log item moved
            logItems(
                $post_item_id,
                $dataSource['label'],
                $_SESSION['user_id'],
                'at_modification',
                $_SESSION['login'],
                'at_moved : '.$dataSource['title'].' -> '.$dataDestination['title']
            );

            echo '[{"from_folder":"'.$dataSource['id_tree'].'" , "to_folder":"'.$post_folder_id.'" , "error" : ""}]';
            break;

        /*
        * CASE
        * MASSIVE Move an ITEM
        */
        case "mass_move_items":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key'] || $_SESSION['user_read_only'] === true || !isset($SETTINGS['pwd_maximum_length'])) {
                // error
                exit();
            }

            // loop on items to move
            foreach (explode(";", filter_input(INPUT_POST, 'item_ids', FILTER_SANITIZE_STRING)) as $item_id) {
                if (empty($item_id) === false) {
                    // get data about item
                    $dataSource = DB::queryfirstrow(
                        "SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                        FROM ".prefix_table("items")." as i
                        INNER JOIN ".prefix_table("nested_tree")." as f ON (i.id_tree=f.id)
                        WHERE i.id=%i",
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
                        "SELECT personal_folder, title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                        $post_folder_id
                    );

                    // previous is non personal folder and new too
                    if ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '0') {
                        // just update is needed. Item key is the same
                        DB::update(
                            prefix_table("items"),
                            array(
                                'id_tree' => $post_folder_id
                                ),
                            "id=%i",
                            $item_id
                        );
                    } elseif ($dataSource['personal_folder'] === '0' && $dataDestination['personal_folder'] === '1') {
                        $decrypt = cryption(
                            $dataSource['pw'],
                            "",
                            "decrypt"
                        );
                        $encrypt = cryption(
                            $decrypt['string'],
                            mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                            "encrypt"
                        );
                        // update pw
                        DB::update(
                            prefix_table("items"),
                            array(
                                'id_tree' => $post_folder_id,
                                'pw' => $encrypt['string'],
                                'pw_iv' => "",
                                'perso' => 1
                            ),
                            "id=%i",
                            $item_id
                        );
                    // If previous is personal folder and new is personal folder too => no key exist on item
                    } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '1') {
                        // just update is needed. Item key is the same
                        DB::update(
                            prefix_table("items"),
                            array(
                                'id_tree' => $post_folder_id
                            ),
                            "id=%i",
                            $item_id
                        );
                    // If previous is personal folder and new is not personal folder => no key exist on item => add new
                    } elseif ($dataSource['personal_folder'] === '1' && $dataDestination['personal_folder'] === '0') {
                        $decrypt = cryption(
                            $dataSource['pw'],
                            mysqli_escape_string($link, stripslashes($_SESSION['user_settings']['session_psk'])),
                            "decrypt"
                        );
                        $encrypt = cryption(
                            $decrypt['string'],
                            "",
                            "encrypt"
                        );

                        // update item
                        DB::update(
                            prefix_table("items"),
                            array(
                                'id_tree' => $post_folder_id,
                                'pw' => $encrypt['string'],
                                'pw_iv' => "",
                                'perso' => 0
                            ),
                            "id=%i",
                            $item_id
                        );
                    }
                    // Log item moved
                    logItems(
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
            updateCacheTable("reload", "");

            echo '[{"error":"" , "status":"ok"}]';
            break;

        /*
         * CASE
         * MASSIVE Delete an item
        */
        case "mass_delete_items":
            // Check KEY and rights
            if ($post_key !== $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // loop on items to move
            foreach (explode(";", filter_input(INPUT_POST, 'item_ids', FILTER_SANITIZE_STRING)) as $item_id) {
                if (empty($item_id) === false) {
                    // get info
                    $dataSource = DB::queryfirstrow(
                        "SELECT label, id_tree
                        FROM ".prefix_table("items")."
                        WHERE id=%i",
                        $item_id
                    );

                    // Check that user can access this folder
                    if (in_array($dataSource['id_tree'], $_SESSION['groupes_visibles']) === false
                    ) {
                        echo '[{"error":"'.addslashes($LANG['error_not_allowed_to']).'" , "status":"nok"}]';
                        exit();
                    }

                    // perform a check in case of Read-Only user creating an item in his PF
                    if ($_SESSION['user_read_only'] === true) {
                        echo '[{"error":"'.addslashes($LANG['error_not_allowed_to']).'" , "status":"nok"}]';
                        exit();
                    }

                    // delete item consists in disabling it
                    DB::update(
                        prefix_table("items"),
                        array(
                            'inactif' => '1',
                            ),
                        "id = %i",
                        $item_id
                    );
                    
                    // log
                    logItems(
                        $item_id,
                        $dataSource['label'],
                        $_SESSION['user_id'],
                        'at_delete',
                        $_SESSION['login']
                    );

                    // Update CACHE table
                    updateCacheTable("delete_value", $item_id);
                }
            }

            echo '[{"error":"" , "status":"ok"}]';

            break;

            /*
           * CASE
           * Send email
        */
        case "send_email":
            if ($post_key !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            } else {
                if (empty(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING)) === false) {
                    $content = explode(',', filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING));
                }
                // get links url
                if (empty($SETTINGS['email_server_url'])) {
                    $SETTINGS['email_server_url'] = $SETTINGS['cpassman_url'];
                }
                if ($post_cat === "request_access_to_author") {
                    $dataAuthor = DB::queryfirstrow("SELECT email,login FROM ".prefix_table("users")." WHERE id= ".$content[1]);
                    $dataItem = DB::queryfirstrow("SELECT label, id_tree FROM ".prefix_table("items")." WHERE id= ".$content[0]);

                    // Get path
                    $path = prepareEmaiItemPath(
                        $dataItem['id_tree'],
                        $dataItem['label'],
                        $SETTINGS
                    );
                    
                    $ret = json_decode(
                        sendEmail(
                            $LANG['email_request_access_subject'],
                            str_replace(
                                array('#tp_item_author#', '#tp_user#', '#tp_item#'),
                                array(" ".addslashes($dataAuthor['login']), addslashes($_SESSION['login']), $path),
                                $LANG['email_request_access_mail']
                            ),
                            $dataAuthor['email'],
                            $LANG,
                            $SETTINGS
                        ),
                        true
                    );
                } elseif ($post_cat === "share_this_item") {
                    $dataItem = DB::queryfirstrow(
                        "SELECT label,id_tree
                        FROM ".prefix_table("items")."
                        WHERE id= %i",
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
                            $LANG['email_share_item_subject'],
                            str_replace(
                                array('#tp_link#', '#tp_user#', '#tp_item#'),
                                array($SETTINGS['email_server_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.$post_id, addslashes($_SESSION['login']), addslashes($path)),
                                $LANG['email_share_item_mail']
                            ),
                            $post_receipt,
                            $LANG,
                            $SETTINGS
                        ),
                        true
                    );
                }
                echo '[{"error":"'.$ret['error'].'" , "message":"'.$ret['message'].'"}]';
            }
            break;

        /*
           * CASE
           * manage notification of an Item
        */
        case "notify_a_user":
            if ($post_key !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            } else {
                if (filter_input(INPUT_POST, 'notify_type', FILTER_SANITIZE_STRING) === "on_show") {
                    // Check if values already exist
                    $data = DB::queryfirstrow(
                        "SELECT notification FROM ".prefix_table("items")." WHERE id = %i",
                        $post_item_id
                    );
                    $notifiedUsers = explode(';', $data['notification']);
                    // User is not in actual notification list
                    if ($post_status === "true" && !in_array($post_user_id, $notifiedUsers)) {
                        // User is not in actual notification list and wants to be notified
                        DB::update(
                            prefix_table("items"),
                            array(
                                'notification' => empty($data['notification']) ?
                                    filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT).";"
                                    : $data['notification'].filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT)
                                ),
                            "id=%i",
                            $post_item_id
                        );
                        echo '[{"error" : "", "new_status":"true"}]';
                        break;
                    } elseif ($post_status === false && in_array($post_user_id, $notifiedUsers)) {
                        // TODO : delete user from array and store in DB
                        // User is in actual notification list and doesn't want to be notified
                        DB::update(
                            prefix_table("items"),
                            array(
                                'notification' => empty($data['notification']) ?
                                    filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT)
                                    : $data['notification'].";".filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT)
                                ),
                            "id=%i",
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
        case "history_entry_add":
            if ($post_key !== $_SESSION['key']) {
                $data = array("error" => "key_is_wrong");
                echo prepareExchangedData($data, "encode");
                break;
            } else {
                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData($post_data, "decode");
                // Get all informations for this item
                $dataItem = DB::queryfirstrow(
                    "SELECT *
                    FROM ".prefix_table("items")." as i
                    INNER JOIN ".prefix_table("log_items")." as l ON (l.id_item = i.id)
                    WHERE i.id=%i AND l.action = %s",
                    $dataReceived['item_id'],
                    "at_creation"
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
                    $error = "";
                    // Query
                    logItems(
                        $dataReceived['item_id'],
                        $dataItem['label'],
                        $_SESSION['user_id'],
                        'at_manual',
                        $_SESSION['login'],
                        htmlspecialchars_decode($dataReceived['label'], ENT_QUOTES)
                    );
                    // Prepare new line
                    $data = DB::queryfirstrow(
                        "SELECT * FROM ".prefix_table("log_items")." WHERE id_item = %i ORDER BY date DESC",
                        $dataReceived['item_id']
                    );
                    $historic = date($SETTINGS['date_format']." ".$SETTINGS['time_format'], $data['date'])." - ".$_SESSION['login']." - ".$LANG[$data['action']]." - ".$data['raison'];
                    // send back
                    $data = array(
                        "error" => "",
                        "new_line" => "<br>".addslashes($historic)
                    );
                    echo prepareExchangedData($data, "encode");
                } else {
                    $data = array("error" => "something_wrong");
                    echo prepareExchangedData($data, "encode");
                    break;
                }
            }
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case "free_item_for_edition":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // Do
            DB::delete(
                prefix_table("items_edition"),
                "item_id = %i",
                $post_id
            );
            break;

        /*
        * CASE
        * Check if Item has been changed since loaded
        */
        case "is_item_changed":
            $data = DB::queryFirstRow(
                "SELECT date FROM ".prefix_table("log_items")." WHERE action = %s AND id_item = %i ORDER BY date DESC",
                "at_modification",
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
        case "generate_OTV_url":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // delete all existing old otv codes
            $rows = DB::query("SELECT id FROM ".prefix_table("otv")." WHERE timestamp < ".(time() - $SETTINGS['otv_expiration_period'] * 86400));
            foreach ($rows as $record) {
                DB::delete(prefix_table('otv'), "id=%i", $record['id']);
            }

            // generate session
            $otv_code = GenerateCryptKey(32, false, true, true, false);

            DB::insert(
                prefix_table("otv"),
                array(
                    'id' => null,
                    'item_id' => $post_id,
                    'timestamp' => time(),
                    'originator' => intval($_SESSION['user_id']),
                    'code' => $otv_code
                    )
            );
            $newID = DB::insertId();

            $otv_session = array(
                "code"      => $otv_code,
                "stamp" => time()
            );

            if (!isset($SETTINGS['otv_expiration_period'])) {
                $SETTINGS['otv_expiration_period'] = 7;
            }
            $url = $SETTINGS['cpassman_url']."/index.php?otv=true&".http_build_query($otv_session);
            $exp_date = date($SETTINGS['date_format']." ".$SETTINGS['time_format'], time() + (intval($SETTINGS['otv_expiration_period']) * 86400));
            $element_id = "clipboard-button-".mt_rand(0, 1000);

            echo json_encode(
                array(
                    "error" => "",
                    'url' => $url,
                    'date' => $exp_date,
                )
            );
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case "image_preview_preparation":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get file info
            $file_info = DB::queryfirstrow(
                "SELECT file, status, type, content FROM ".prefix_table("files")." WHERE id=%i",
                intval(substr(filter_input(INPUT_POST, 'uri', FILTER_SANITIZE_STRING), 1))
            );

            // prepare image info
            $post_title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $image_code = $file_info['file'];
            $extension = substr($post_title, strrpos($post_title, '.') + 1);
            $file_to_display = $SETTINGS['url_to_upload_folder'].'/'.$image_code;
            $file_suffix = "";

            // should we encrypt/decrypt the file
            encrypt_or_decrypt_file($file_info['file'], $file_info['status']);

            // should we decrypt the attachment?
            if (isset($file_info['status']) && $file_info['status'] === "encrypted") {
                // Delete the file as viewed
                fileDelete($SETTINGS['path_to_upload_folder'].'/'.$image_code."_delete.".$extension);

                // Open the file
                if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$image_code)) {
                    // Should we encrypt or decrypt?
                    prepareFileWithDefuse(
                        'decrypt',
                        $SETTINGS['path_to_upload_folder'].'/'.$image_code,
                        $SETTINGS['path_to_upload_folder'].'/'.$image_code."_delete.".$extension
                    );

                    // prepare variable
                    $file_to_display = $file_to_display."_delete.".$extension;
                    $file_suffix = "_delete.".$extension;
                }
            }

            // Encrypt data to return
            echo prepareExchangedData(
                array(
                    "error" => "",
                    "new_file" => $file_to_display,
                    "file_type" => $file_info['type'],
                    "file_suffix" => $file_suffix,
                    "file_path" => $SETTINGS['path_to_upload_folder'].'/'.$image_code."_delete.".$extension,
                    "image_secure" => isset($SETTINGS['secure_display_image']) === true ? $SETTINGS['secure_display_image'] : '0',
                    "file_content" => base64_encode(file_get_contents($SETTINGS['path_to_upload_folder'].'/'.$image_code."_delete.".$extension))
                ),
                "encode"
            );
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case "delete_file":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get file info
            $result = DB::queryfirstrow(
                "SELECT file FROM ".prefix_table("files")." WHERE id=%i",
                intval(substr(filter_input(INPUT_POST, 'uri', FILTER_SANITIZE_STRING), 1))
            );

            fileDelete($SETTINGS['path_to_upload_folder'].'/'.$result['file'].filter_input(INPUT_POST, 'file_suffix', FILTER_SANITIZE_STRING));

            break;

        /*
        * CASE
        * Get list of users that have access to the folder
        */
        case "get_refined_list_of_users":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            $error = "";

            // get list of users
            $aList = array();
            $selOptionsUsers = "";
            $selOptionsRoles = "";
            $selEOptionsUsers = "";
            $selEOptionsRoles = "";
            $rows = DB::query(
                "SELECT r.role_id AS role_id, t.title AS title
                FROM ".prefix_table("roles_values")." AS r
                INNER JOIN ".prefix_table("roles_title")." AS t ON (r.role_id = t.id)
                WHERE r.folder_id = %i",
                $post_iFolderId
            );
            foreach ($rows as $record) {
                $selOptionsRoles .= '<option value="role_'.$record['role_id'].'" class="folder_rights_role">'.$record['title'].'</option>';
                $selEOptionsRoles .= '<option value="role_'.$record['role_id'].'" class="folder_rights_role_edit">'.$record['title'].'</option>';
                $rows2 = DB::query("SELECT id, login, fonction_id FROM ".prefix_table("users")." WHERE fonction_id LIKE '%".$record['role_id']."%'");
                foreach ($rows2 as $record2) {
                    foreach (explode(";", $record2['fonction_id']) as $role) {
                        if (!in_array($record2['id'], $aList) && $role == $record['role_id']) {
                            array_push($aList, $record2['id']);
                            $selOptionsUsers .= '<option value="'.$record2['id'].'" class="folder_rights_user">'.$record2['login'].'</option>';
                            $selEOptionsUsers .= '<option value="'.$record2['id'].'" class="folder_rights_user_edit">'.$record2['login'].'</option>';
                        }
                    }
                }
            }

            // export data
            $data = array(
                'error' => $error,
                'selOptionsUsers' => $selOptionsUsers,
                'selOptionsRoles' => $selOptionsRoles,
                'selEOptionsUsers' => $selEOptionsUsers,
                'selEOptionsRoles' => $selEOptionsRoles
            );
            echo prepareExchangedData($data, "encode");

            break;

        /*
        * CASE
        * Get list of users that have access to the folder
        */
        case "check_for_title_duplicate":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            $error = "";
            $duplicate = 0;

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, "decode");
            // Prepare variables
            $label = htmlspecialchars_decode($dataReceived['label']);
            $idFolder = $dataReceived['idFolder'];

            // don't check if Personal Folder
            $data = DB::queryFirstRow("SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i", $idFolder);
            if ($data['title'] == $_SESSION['user_id']) {
                // send data
                echo '[{"duplicate" : "'.$duplicate.'" , error" : ""}]';
            } else {
                if (filter_input(INPUT_POST, 'option', FILTER_SANITIZE_STRING) === "same_folder") {
                // case unique folder
                    DB::query(
                        "SELECT label
                        FROM ".prefix_table("items")."
                        WHERE id_tree = %i AND label = %s",
                        $idFolder,
                        $label
                    );
                } else {
                // case complete database

                    //get list of personal folders
                    $arrayPf = array();
                    $listPf = "";
                    if (empty($row['id']) === false) {
                        $rows = DB::query(
                            "SELECT id FROM ".prefix_table("nested_tree")." WHERE personal_folder = %i",
                            "1"
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
                        $where->add("id_tree NOT IN (".implode(',', $arrayPf).")");
                    }

                    DB::query(
                        "SELECT label
                        FROM ".prefix_table("items")."
                        WHERE %l",
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
        case "refresh_visible_folders":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Init

            // Will we show the root folder?
            $arr_data['can_create_root_folder'] = isset($SETTINGS['can_create_root_folder']) && $SETTINGS['can_create_root_folder'] === '1' ? 1 : 0;

            // Build list of visible folders
            $selectVisibleFoldersOptions = $selectVisibleNonPersonalFoldersOptions = $selectVisibleActiveFoldersOptions = "";
            if (isset($SETTINGS['can_create_root_folder']) && $SETTINGS['can_create_root_folder'] === '1') {
                $selectVisibleFoldersOptions = '<option value="0">'.$LANG['root'].'</option>';
            }

            if ($_SESSION['user_admin'] === '1' && (isset($SETTINGS_EXT['admin_full_right'])
                && $SETTINGS_EXT['admin_full_right'] === true) || !isset($SETTINGS_EXT['admin_full_right'])) {
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
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            try {
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
                                    "SELECT * FROM ".prefix_table("items")."
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
                            // resize title if necessary
                            $fldTitle = str_replace("&", "&amp;", $folder->title);

                            // rename personal folder with user login
                            if ($folder->title == $_SESSION['user_id'] && $folder->nlevel === '1') {
                                $fldTitle = $_SESSION['login'];
                            }

                            // ALL FOLDERS
                            // Is this folder disabled?
                            $disabled = 0;
                            if (in_array($folder->id, $_SESSION['groupes_visibles']) === false
                                || in_array($folder->id, $_SESSION['read_only_folders']) === true
                                || ($_SESSION['user_read_only'] === '1' && in_array($folder->id, $_SESSION['personal_visible_groups']) === false)
                            ) {
                                $disabled = 1;
                            }
                            // Build array
                            $arr_data['folders'][$inc]['id'] = $folder->id;
                            $arr_data['folders'][$inc]['level'] = $folder->nlevel;
                            $arr_data['folders'][$inc]['title'] = htmlspecialchars_decode($fldTitle, ENT_QUOTES);
                            $arr_data['folders'][$inc]['disabled'] = $disabled;


                            // Is this folder an active folders? (where user can do something)
                            $is_visible_active = 0;
                            if (isset($_SESSION['read_only_folders']) === true
                                && in_array($folder->id, $_SESSION['read_only_folders']) === true) {
                                $is_visible_active = 1;
                            }
                            $arr_data['folders'][$inc]['is_visible_active'] = $is_visible_active;

                            $inc++;
                        }
                    }
                }
            } catch (Exception $e) {
                // Nothing done
            }
            

            $data = array(
                'error' => "",
                'html_json' => $arr_data
            );
            // send data
            echo prepareExchangedData($data, "encode");

            break;

        /*
        * CASE
        * Load item history
        */
        case "load_item_history":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($post_data, "decode");

            // Prepare variables
            $id = noHTML(htmlspecialchars_decode($dataReceived['id']));

            // get item info
            $dataItem = DB::queryFirstRow(
                "SELECT *
                FROM ".prefix_table("items")."
                WHERE id=%i",
                $id
            );

            // get item history
            $history = '<table style="margin:0px; width:100%; border-collapse: collapse; background-color:#D4D5D5;" cellspacing="0" cellpadding="1">';
            $rows = DB::query(
                "SELECT l.date as date, l.action as action, l.raison as raison, l.raison_iv AS raison_iv,
                u.login as login, u.avatar_thumb as avatar_thumb
                FROM ".prefix_table("log_items")." as l
                LEFT JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                WHERE id_item=%i AND action <> %s
                ORDER BY date ASC",
                $id,
                "at_shown"
            );
            foreach ($rows as $record) {
                $reason = explode(':', $record['raison']);
                if ($record['action'] === "at_modification" && $reason[0] === "at_pw ") {
                    // check if item is PF
                    if ($dataItem['perso'] != 1) {
                        $reason[1] = cryption(
                            $reason[1],
                            "",
                            "decrypt"
                        );
                    } else {
                        if (isset($_SESSION['user_settings']['session_psk']) === true) {
                            $reason[1] = cryption(
                                $reason[1],
                                $_SESSION['user_settings']['session_psk'],
                                "decrypt"
                            );
                        } else {
                            $reason[1] = '';
                        }
                    }
                    $reason[1] = @$reason[1]['string'];
                    // if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
                    if (!isUTF8($reason[1]) || is_array($reason[1])) {
                        $reason[1] = "";
                    }
                }
                // imported via API
                if (empty($record['login'])) {
                    $record['login'] = $LANG['imported_via_api']." [".$record['raison']."]";
                }

                if (empty($reason[1]) === false
                    || $record['action'] === "at_copy"
                    || $record['action'] === "at_creation"
                    || $record['action'] === "at_manual"
                    || $record['action'] === "at_modification"
                    || $record['action'] === "at_delete"
                    || $record['action'] === "at_restored") {
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

                    $history .= '<tr style="">'.
                        '<td rowspan="2" style="width:40px;"><img src="'.$avatar.'" style="border-radius:20px; height:35px;"></td>'.
                        '<td colspan="2" style="font-size:11px;"><i>'.$LANG['by'].' '.$record['login'].' '.$LANG['at'].' '.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], $record['date']).'</i></td></tr>'.
                        '<tr style="border-bottom:3px solid #C9C9C9;"><td style="width:100px;"><b>'.$LANG[$record['action']].'</b></td>'.
                        '<td style="">'.(empty($record['raison']) === false && $record['action'] !== "at_creation" ? (count($reason) > 1 ? $LANG[trim($reason[0])].' : '.handleBackslash($reason[1]) : ($record['action'] === "at_manual" ? $reason[0] : $LANG[trim($reason[0])])) : '').'</td>'.
                        '</tr>'.
                        '<tr></tr>';
                }
            }
            $history .= "</table>";

            $data = array(
                'error' => "",
                'new_html' => $history
            );

            // send data
            echo prepareExchangedData($data, "encode");

            break;

        case "suggest_item_change":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // decrypt and retrieve data in JSON format
            $data_received = prepareExchangedData($post_data, "decode");

            // prepare variables
            $label = htmlspecialchars_decode($data_received['label'], ENT_QUOTES);
            $pwd = htmlspecialchars_decode($data_received['pwd']);
            $login = htmlspecialchars_decode($data_received['login'], ENT_QUOTES);
            $email = htmlspecialchars_decode($data_received['email']);
            $url = htmlspecialchars_decode($data_received['url']);
            $folder = htmlspecialchars_decode($data_received['folder']);
            $comment = htmlspecialchars_decode($data_received['comment']);
            $item_id = htmlspecialchars_decode($data_received['item_id']);

            if (empty($pwd)) {
                $encrypt['string'] = "";
            } else {
                $encrypt = cryption($pwd, "", "encrypt");
            }

            // query
            DB::insert(
                prefix_table("items_change"),
                array(
                    'item_id' => $item_id,
                    'label' => $label,
                    'pw' => $encrypt['string'],
                    'login' => $login,
                    'email' => $email,
                    'url' => $url,
                    'description' => "",
                    'comment' => $comment,
                    'folder_id' => $folder,
                    'user_id' => $_SESSION['user_id'],
                    'timestamp' => time()
                )
            );
            $newID = DB::insertId();

            // get some info to add to the notification email
            $resp_user = DB::queryfirstrow(
                "SELECT login FROM ".prefix_table("users")." WHERE id = %i",
                $_SESSION['user_id']
            );
            $resp_folder = DB::queryfirstrow(
                "SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                $folder
            );

            // notify Managers
            $rows = DB::query(
                "SELECT email
                FROM ".prefix_table("users")."
                WHERE `gestionnaire` = %i AND `email` IS NOT NULL",
                1
            );
            foreach ($rows as $record) {
                sendEmail(
                    $LANG['suggestion_notify_subject'],
                    str_replace(array('#tp_label#', '#tp_user#', '#tp_folder#'), array(addslashes($label), addslashes($resp_user['login']), addslashes($resp_folder['title'])), $LANG['suggestion_notify_body']),
                    $record['email'],
                    $LANG,
                    $SETTINGS
                );
            }

            echo '[ { "error" : "" } ]';
            break;

        case "build_list_of_users":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Get list of users
            $usersList = array();
            $usersString = "";
            $rows = DB::query("SELECT id,login,email FROM ".$pre."users ORDER BY login ASC");
            foreach ($rows as $record) {
                $usersList[$record['login']] = array(
                    "id" => $record['id'],
                    "login" => $record['login'],
                    "email" => $record['email'],
                    );
                $usersString .= $record['id']."#".$record['login'].";";
            }

            $data = array(
                'error' => "",
                'list' => $usersString
            );

            // send data
            echo prepareExchangedData($data, "encode");
            break;

        case "send_request_access":
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // decrypt and retrieve data in JSON format
            $data_received = prepareExchangedData($post_data, "decode");

            // prepare variables
            $emailText = htmlspecialchars_decode($data_received['text'], ENT_QUOTES);
            $item_id = htmlspecialchars_decode($data_received['item_id']);
            $user_id = htmlspecialchars_decode($data_received['user_id']);

            // Send email
            $dataAuthor = DB::queryfirstrow("SELECT email,login FROM ".prefix_table("users")." WHERE id= ".$user_id);
            $dataItem = DB::queryfirstrow("SELECT label, id_tree FROM ".prefix_table("items")." WHERE id= ".$item_id);

            // Get path
            $path = prepareEmaiItemPath(
                $dataItem['id_tree'],
                $dataItem['label'],
                $SETTINGS
            );

            $ret = sendEmail(
                $LANG['email_request_access_subject'],
                str_replace(
                    array(
                        '#tp_item_author#',
                        '#tp_user#',
                        '#tp_item#',
                        '#tp_reason#'
                    ),
                    array(
                        " ".addslashes($dataAuthor['login']),
                        addslashes($_SESSION['login']),
                        $path,
                        nl2br(addslashes($emailText))
                    ),
                    $LANG['email_request_access_mail']
                ),
                $dataAuthor['email'],
                $LANG,
                $SETTINGS
            );

            // Do log
            logItems(
                $item_id,
                $dataItem['label'],
                $_SESSION['user_id'],
                'at_access',
                $_SESSION['login']
            );

            // Return
            echo '[ { "error" : "" } ]';

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
        case "autocomplete_tags":
            // Get a list off all existing TAGS
            $listOfTags = "";
            $rows = DB::query("SELECT tag FROM ".prefix_table("tags")." WHERE tag LIKE %ss GROUP BY tag", $_GET['term']);
            foreach ($rows as $record) {
                if (empty($listOfTags)) {
                    $listOfTags = '"'.$record['tag'].'"';
                } else {
                    $listOfTags .= ', "'.$record['tag'].'"';
                }
            }
            echo "[".$listOfTags."]";
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
        "SELECT bloquer_creation, bloquer_modification, personal_folder FROM ".prefix_table("nested_tree")." WHERE id = %i",
        $groupe
    );
    // Check if it's in a personal folder. If yes, then force complexity overhead.
    if ($data['personal_folder'] === '1') {
        return array("bloquer_modification_complexite" => 1, "bloquer_creation_complexite" => 1);
    }

    return array("bloquer_modification_complexite" => $data['bloquer_modification'], "bloquer_creation_complexite" => $data['bloquer_creation']);
}

/**
 * Permits to identify what icon to display depending on file extension
 *
 * @param  string $ext Extension
 * @return string
 */
function fileFormatImage($ext)
{
    global $SETTINGS_EXT;
    if (in_array($ext, $SETTINGS_EXT['office_file_ext'])) {
        $image = "file-word-o";
    } elseif ($ext === "pdf") {
        $image = "file-pdf-o";
    } elseif (in_array($ext, $SETTINGS_EXT['image_file_ext'])) {
        $image = "file-image-o";
    } elseif ($ext === "txt") {
        $image = "file-text-o";
    } else {
        $image = "file-o";
    }

    return $image;
}

/**
 * Returns a cleaned up password
 *
 * @param  string $pwd String for pwd
 * @return string
 */
function passwordReplacement($pwd)
{
    $pwPatterns = array('/ETCOMMERCIAL/', '/SIGNEPLUS/');
    $pwRemplacements = array('&', '+');

    return preg_replace($pwPatterns, $pwRemplacements, $pwd);
}

/**
 * Returns the Item + path
 *
 * @param integer $id_tree
 * @param string $label
 * @param array $SETTINGS
 * @return string
 */
function prepareEmaiItemPath($id_tree, $label, $SETTINGS) {
    // Class loader
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

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
