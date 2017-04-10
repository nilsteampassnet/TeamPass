<?php
/**
 * @file          items.queries.php
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
require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

/**
 * Define Timezone
 */
if (isset($_SESSION['settings']['timezone'])) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once 'main.functions.php';
// pw complexity levels
$_SESSION['settings']['pwComplexity'] = array(
    0 => array(0, $LANG['complex_level0']),
    25 => array(25, $LANG['complex_level1']),
    50 => array(50, $LANG['complex_level2']),
    60 => array(60, $LANG['complex_level3']),
    70 => array(70, $LANG['complex_level4']),
    80 => array(80, $LANG['complex_level5']),
    90 => array(90, $LANG['complex_level6'])
   );

//Class loader
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// phpcrypt
require_once $_SESSION['settings']['cpassman_dir'] . '/includes/libraries/phpcrypt/phpCrypt.php';
use PHP_Crypt\PHP_Crypt as PHP_Crypt;
use PHP_Crypt\Cipher as Cipher;

// prepare Encryption class calls
use \Defuse\Crypto\Crypto;
use \Defuse\Crypto\Exception as Ex;


// Do asked action
if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        /*
        * CASE
        * creating a new ITEM
        */
        case "new_item":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            // Prepare variables
            $label = noHTML(htmlspecialchars_decode($dataReceived['label']));
            $url = htmlspecialchars_decode($dataReceived['url']);
            $pw = htmlspecialchars_decode($dataReceived['pw']);
            $login = htmlspecialchars_decode($dataReceived['login']);
            $tags = htmlspecialchars_decode($dataReceived['tags']);

            // is author authorized to create in this folder
            if (count($_SESSION['list_folders_limited']) > 0) {
                if (
                    !in_array($dataReceived['categorie'], array_keys($_SESSION['list_folders_limited']))
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
            if ($_SESSION['user_read_only'] === true && !in_array($dataReceived['categorie'], $_SESSION['personal_folders'])) {
                echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                break;
            }

            // is pwd empty?
            if (empty($pw) && isset($_SESSION['user_settings']['create_item_without_password']) && $_SESSION['user_settings']['create_item_without_password'] !== "1") {
                echo prepareExchangedData(array("error" => "ERR_PWD_EMPTY"), "encode");
                break;
            }

            // Check length
            if (strlen($pw) > $_SESSION['settings']['pwd_maximum_length']) {
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

            if (
                (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 0 && $itemExists == 0)
                ||
                (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1)
            ) {
                if ((isset($_SESSION['user_settings']['create_item_without_password']) && $_SESSION['user_settings']['create_item_without_password'] !== "1") || !empty($pw)) {
                    // encrypt PW
                    if ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) {
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
                    $passwd['string'] = "";
                }

                if (!empty($passwd["error"])) {
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
                        'restricted_to' => isset($dataReceived['restricted_to']) ? $dataReceived['restricted_to'] : '',
                        'perso' => ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) ? '1' : '0',
                        'anyone_can_modify' => (isset($dataReceived['anyone_can_modify']) && $dataReceived['anyone_can_modify'] == "on") ? '1' : '0',
                        'complexity_level' => $dataReceived['complexity_level']
                       )
                );
                $newID = DB::insertId();
                $pw = $passwd['string'];

                // update fields
                if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
                    foreach (explode("_|_", $dataReceived['fields']) as $field) {
                        $field_data = explode("~~", $field);
                        if (count($field_data)>1 && !empty($field_data[1])) {
                            $encrypt = cryption(
                                $field_data[1],
                                "",
                                "encrypt"
                            );
                            DB::insert(
                                prefix_table('categories_items'),
                                array(
                                    'item_id' => $newID,
                                    'field_id' => $field_data[0],
                                    'data' => $encrypt['string'],
                                    'data_iv' => $encrypt['iv']
                                )
                            );
                        }
                    }
                }

                // If automatic deletion asked
                if ($dataReceived['to_be_deleted'] != 0 && !empty($dataReceived['to_be_deleted'])) {
                    $date_stamp = dateToStamp($dataReceived['to_be_deleted']);
                    DB::insert(
                        prefix_table('automatic_del'),
                        array(
                            'item_id' => $newID,
                            'del_enabled' => 1, // 0=deactivated;1=activated
                            'del_type' => $date_stamp != false ? 2 : 1, // 1=counter;2=date
                            'del_value' => $date_stamp != false ? $date_stamp : $dataReceived['to_be_deleted']
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
                logItems($newID, $label, $_SESSION['user_id'], 'at_creation', $_SESSION['login']);
                // Add tags
                $tags = explode(' ', $tags);
                foreach ($tags as $tag) {
                    if (!empty($tag)) {
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
                if (!empty($dataReceived['random_id_from_files'])) {
                    $rows = DB::query("SELECT id
                            FROM ".prefix_table("files")."
                            WHERE id_item = %s", $dataReceived['random_id_from_files']
                    );
                    foreach ($rows as $record) {
                        // update item_id in files table
                        DB::update(
                            prefix_table('files'),
                            array(
                                'id_item' => $newID
                               ),
                            "id=%i", $record['id']
                        );
                    }
                }

                // Announce by email?
                if ($dataReceived['annonce'] == 1) {
                    // get links url
                    if (empty($_SESSION['settings']['email_server_url'])) {
                        $_SESSION['settings']['email_server_url'] = $_SESSION['settings']['cpassman_url'];
                    }
                    // send email
                    foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                        if (!empty($emailAddress)) {
                            // send it
                            @sendEmail(
                                $LANG['email_subject'],
                                str_replace(
                                    array("#label", "#link"),
                                    array(stripslashes($label), $_SESSION['settings']['email_server_url'].'/index.php?page=items&group='.$dataReceived['categorie'].'&id='.$newID.$txt['email_body3']),
                                    $LANG['new_item_email_body']
                                ),
                                $emailAddress,
                                str_replace(
                                    array("#label", "#link"),
                                    array(stripslashes($label), $_SESSION['settings']['email_server_url'].'/index.php?page=items&group='.$dataReceived['categorie'].'&id='.$newID.$txt['email_body3']),
                                    $LANG['new_item_email_body']
                                )
                            );
                        }
                    }
                }
                // Get Expiration date
                $expirationFlag = '';
                if ($_SESSION['settings']['activate_expiration'] == 1) {
                    $expirationFlag = '<i class="fa fa-flag mi-green"></i>&nbsp;';
                }
                // Prepare full line
                $html = '<li class="item_draggable'
                .'" id="'.$newID.'" style="margin-left:-30px;">'
                .'<span style="cursor:hand;" class="grippy"><i class="fa fa-sm fa-arrows mi-grey-1"></i>&nbsp;</span>'
                .$expirationFlag.'<i class="fa fa-sm fa-warning mi-yellow"></i>&nbsp;' .
                '&nbsp;<a id="fileclass'.$newID.'" class="file" onclick="AfficherDetailsItem(\''.$newID.'\', \'0\', \'\', \'\', \'\', \'\', \'\')" ondblclick="AfficherDetailsItem(\''.$newID.'\', \'0\', \'\', \'\', \'\', true, \'\')">' .
                stripslashes($dataReceived['label']);
                if (!empty($dataReceived['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1) {
                    $html .= '&nbsp;<font size=2px>['.strip_tags(stripslashes(substr(cleanString($dataReceived['description']), 0, 30))).']</font>';
                }
                $html .= '</a><span style="float:right;margin:2px 10px 0px 0px;">';
                // mini icon for collab
                if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
                    if ($dataReceived['anyone_can_modify'] == 1) {
                        $itemCollab = '<i class="fa fa-pencil fa-sm mi-grey-1 tip" title="'.$LANG['item_menu_collab_enable'].'"></i>&nbsp;&nbsp;';
                    }
                }
                // display quick icon shortcuts ?
                if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                    $itemLogin = $itemPw = "";

                    if (!empty($dataReceived['login'])) {
                        $itemLogin = '<span id="iconlogin_'.$newID.'" class="copy_clipboard tip" title="'.$LANG['item_menu_copy_login'].'"><i class="fa fa-sm fa-user mi-black"></i>&nbsp;</span>';
                    }
                    if (!empty($dataReceived['pw'])) {
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
                    "show_clipboard_small_icons" => (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) ? 1 : 0
                   );
            } elseif (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 0 && $itemExists == 1) {
                $returnValues = array("error" => "item_exists");
            }

            // Update CACHE table
            updateCacheTable("add_value", $newID);

            // Encrypt data to return
            echo prepareExchangedData($returnValues, "encode");
            break;

        /*
        * CASE
        * update an ITEM
        */
        case "update_item":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // init
            $reloadPage = false;
            $returnValues = array();
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            if (count($dataReceived) > 0) {
                // Prepare variables
                $label = noHTML(($dataReceived['label']));
                $url = noHTML(htmlspecialchars_decode($dataReceived['url']));
                $pw = $original_pw = $sentPw = htmlspecialchars_decode($dataReceived['pw']);
                $login = noHTML(htmlspecialchars_decode($dataReceived['login']));
                $tags = htmlspecialchars_decode($dataReceived['tags']);
                $email = noHTML(htmlspecialchars_decode($dataReceived['email']));

                // perform a check in case of Read-Only user creating an item in his PF
                if ($_SESSION['user_read_only'] === true && (!in_array($dataReceived['categorie'], $_SESSION['personal_folders']) || $dataReceived['is_pf'] !== "1")) {
                    echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
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

                if (
                    (
                        in_array($dataItem['id_tree'], $_SESSION['groupes_visibles'])
                        && ($dataItem['perso'] == 0 || ($dataItem['perso'] == 1 && $dataItem['id_user'] == $_SESSION['user_id']))
                        && $restrictionActive == false
                    )
                    ||
                    (
                        isset($_SESSION['settings']['anyone_can_modify'])
                        && $_SESSION['settings']['anyone_can_modify'] == 1
                        && $dataItem['anyone_can_modify'] == 1
                        && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] == 1)
                        && $restrictionActive == false
                    )
                    ||
                    (@in_array($_POST['id'], $_SESSION['list_folders_limited'][$_POST['folder_id']]))
                 ) {

                    // is pwd empty?
                    if (empty($pw) && isset($_SESSION['user_settings']['create_item_without_password']) && $_SESSION['user_settings']['create_item_without_password'] !== "1") {
                        echo prepareExchangedData(array("error" => "ERR_PWD_EMPTY"), "encode");
                        break;
                    }

                    // Check length
                    if (strlen($pw) > $_SESSION['settings']['pwd_maximum_length']) {
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
                    if ((isset($_SESSION['user_settings']['create_item_without_password']) && $_SESSION['user_settings']['create_item_without_password'] !== "1") || !empty($pw)) {
                        if ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) {
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

                        if (!empty($passwd["error"])) {
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
                        if (!empty($tag)) {
                            // save in DB
                            DB::insert(
                                prefix_table('tags'),
                                array(
                                    'item_id' => $dataReceived['id'],
                                    'tag' => strtolower($tag)
                                )
                            );
                            // prepare display
                            if (empty($tags)) $return_tags = "<span class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".strtolower($tag)."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".strtolower($tag)."</span></span>";
                            else $return_tags .= "&nbsp;&nbsp;<span class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".strtolower($tag)."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".strtolower($tag)."</span></span>";
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
                            'id_tree' => (!isset($dataReceived['categorie']) || $dataReceived['categorie'] == "undefined") ? $dataItem['id_tree'] : $dataReceived['categorie'],
                            'restricted_to' => $dataReceived['restricted_to'],
                            'anyone_can_modify' => (isset($dataReceived['anyone_can_modify']) && $dataReceived['anyone_can_modify'] == "on") ? '1' : '0',
                            'complexity_level' => $dataReceived['complexity_level']
                           ),
                        "id=%i",
                        $dataReceived['id']
                    );
                    // update fields
                    if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
                        foreach (explode("_|_", $dataReceived['fields']) as $field) {
                            $field_data = explode("~~", $field);
                            if (count($field_data)>1 && !empty($field_data[1])) {
                                $dataTmp = DB::queryFirstRow(
                                    "SELECT c.title AS title, i.data AS data, i.data_iv AS data_iv, c.encrypted_data AS encrypted_data
                                    FROM ".prefix_table("categories_items")." AS i
                                    INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id=c.id)
                                    WHERE i.field_id = %i AND i.item_id = %i",
                                    $field_data[0],
                                    $dataReceived['id']
                                );
                                // store Field text in DB
                                if (count($dataTmp['title']) == 0) {
                                    $encrypt = cryption(
                                        $field_data[1],
                                        "",
                                        "encrypt"
                                    );
                                    // store field text
                                    DB::insert(
                                        prefix_table('categories_items'),
                                        array(
                                            'item_id' => $dataReceived['id'],
                                            'field_id' => $field_data[0],
                                            'data' => $encrypt['string'],
                                            'data_iv' => ""
                                            //'data_iv' => $encrypt['iv']
                                        )
                                    );
                                    // update LOG
                                    logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_creation', $_SESSION['login'], 'at_field : '.$dataTmp['title'].' : '.$field_data[1]);
                                } else {
                                    // compare the old and new value
                                    $oldVal = cryption(
                                        $dataTmp['data'],
                                        "",
                                        "decrypt"
                                    );
                                    if ($field_data[1] != $oldVal['string']) {
                                        $encrypt = cryption(
                                            $field_data[1],
                                            "",
                                            "encrypt"
                                        );
                                        // update value
                                        DB::update(
                                            prefix_table('categories_items'),
                                            array(
                                                'data' => $encrypt['string'],
                                                'data_iv' => ""
                                                //'data_iv' => $encrypt['iv']
                                            ),
                                            "item_id = %i AND field_id = %i",
                                            $dataReceived['id'],
                                            $field_data[0]
                                        );

                                        // update LOG
                                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_field : '.$dataTmp['title'].' => '.$oldVal['string']);
                                    }
                                }
                            } else {
                                if (empty($field_data[1])) {
                                    DB::delete(
                                        $pre."categories_items", "item_id = %i AND field_id = %s",
                                        $dataReceived['id'],
                                        $field_data[0]
                                    );
                                }
                            }
                        }
                    }

                    // Update automatic deletion - Only by the creator of the Item
                    if (isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1) {
                        // check if elem exists in Table. If not add it or update it.
                        DB::query("SELECT * FROM ".prefix_table("automatic_del")." WHERE item_id = %i", $dataReceived['id']);
                        $counter = DB::count();
                        if ($counter == 0) {
                            // No automatic deletion for this item
                            if (!empty($dataReceived['to_be_deleted']) || ($dataReceived['to_be_deleted'] > 0 && is_numeric($dataReceived['to_be_deleted']))) {
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
                                logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_automatic_del : '.$dataReceived['to_be_deleted']);
                            }
                        } else {
                            // Automatic deletion exists for this item
                            if (!empty($dataReceived['to_be_deleted']) || ($dataReceived['to_be_deleted'] > 0 && is_numeric($dataReceived['to_be_deleted']))) {
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
                            logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_automatic_del : '.$dataReceived['to_be_deleted']);
                        }
                    }
                    // get readable list of restriction
                    $listOfRestricted = $oldRestrictionList = "";
                    if (!empty($dataReceived['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
                        foreach (explode(';', $dataReceived['restricted_to']) as $userRest) {
                            if (!empty($userRest)) {
                                $dataTmp = DB::queryfirstrow("SELECT login FROM ".prefix_table("users")." WHERE id= %i", $userRest);
                                if (empty($listOfRestricted)) {
                                    $listOfRestricted = $dataTmp['login'];
                                } else {
                                    $listOfRestricted .= ";".$dataTmp['login'];
                                }
                            }
                        }
                    }
                    if ($data['restricted_to'] != $dataReceived['restricted_to'] && $_SESSION['settings']['restricted_to'] == 1) {
                        if (!empty($data['restricted_to'])) {
                            foreach (explode(';', $data['restricted_to']) as $userRest) {
                                if (!empty($userRest)) {
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
                    if (isset($dataReceived['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) {
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
                            if (count($role) > 1) $role = $role[1];
                            else $role = $role[0];
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
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_label : '.$data['label'].' => '.$label);
                    }
                    /*LOGIN */
                    if ($data['login'] != $login) {
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_login : '.$data['login'].' => '.$login);
                    }
                    /*EMAIL */
                    if ($data['email'] != $dataReceived['email']) {
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_email : '.$data['email'].' => '.$dataReceived['email']);
                    }
                    /*URL */
                    if ($data['url'] != $url && $url != "http://") {
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_url : '.$data['url'].' => '.$url);
                    }
                    /*DESCRIPTION */
                    if ($data['description'] != $dataReceived['description']) {
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_description');
                    }
                    /*FOLDER */
                    if ($data['id_tree'] != $dataReceived['categorie']) {
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_category : '.$data['id_tree'].' => '.$dataReceived['categorie']);
                        // ask for page reloading
                        $reloadPage = true;
                    }
                    /*PASSWORD */
                    if ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) {
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
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_pw :'.$oldPw, "");
                    }
                    /*RESTRICTIONS */
                    if ($data['restricted_to'] != $dataReceived['restricted_to']) {
                        logItems($dataReceived['id'], $label, $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_restriction : '.$oldRestrictionList.' => '.$listOfRestricted);
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
                        if (empty($history)) {
                            $history = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date'])." - ".$record['login']." - ".$LANG[$record['action']] .
                            " - ".(!empty($record['raison']) ? (count($reason) > 1 ? $LANG[trim($reason[0])].' : '.$reason[1] : $LANG[trim($reason[0])]):'');
                        } else {
                            $history .= "<br />".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date'])." - " .
                            $record['login']." - ".$LANG[$record['action']]." - " .
                            (!empty($record['raison']) ? (count($reason) > 1 ? $LANG[trim($reason[0])].' => '.$reason[1] : ($record['action'] != "at_manual" ? $LANG[trim($reason[0])] : trim($reason[0]))):'');
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
                        if (in_array($record['extension'], $k['image_file_ext'])) {
                            $files .= '<i class=\'fa fa-file-image-o\' /></i>&nbsp;<a class="image_dialog" href="#'.$record['id'].'" title="'.$record['name'].'">'.$record['name'].'</a><br />';
                        } else {
                            $files .= '<i class=\'fa fa-file-text-o\' /></i>&nbsp;<a href=\'sources/downloadFile.php?name='.urlencode($record['name']).'&type=sub&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'&fileid='.$record['id'].'\' target=\'_blank\'>'.$record['name'].'</a><br />';
                        }
                        // Prepare list of files for edit dialogbox
                        $filesEdit .= '<span id="span_edit_file_'.$record['id'].'"><span class="fa fa-'.$iconImage.'"></span>&nbsp;<span class="fa fa-eraser tip" style="cursor:pointer;"  onclick="delete_attached_file(\"'.$record['id'].'\")" title="'.$LANG['at_delete'].'"></span>&nbsp;'.$record['name']."</span><br />";
                    }
                    // Send email
                    if (!empty($dataReceived['diffusion'])) {
                        foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                            if (!empty($emailAddress)) {
                                @sendEmail(
                                    $LANG['email_subject_item_updated'],
                                    str_replace(
                                        array("#item_label#", "#item_category#", "#item_id#", "#url#"),
                                        array($label, $dataReceived['categorie'], $dataReceived['id'], $_SESSION['settings']['cpassman_url']),
                                        $LANG['email_body_item_updated']
                                    ),
                                    $emailAddress,
                                    str_replace("#item_label#", $label, $LANG['email_bodyalt_item_updated'])
                                );
                            }
                        }
                    }

                    // send email to user that whant to be notified
                    if ($dataItem['notification'] !== null && !empty($dataItem['notification'])) {
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
                                        array($label, $dataReceived['categorie'], $dataReceived['id'], $_SESSION['settings']['cpassman_url']),
                                        $LANG['email_body_item_updated']
                                    ),
                                'receivers' => $mailing,
                                'status' => ''
                               )
                        );
                    }

                    // Prepare some stuff to return
                    $arrData = array(
                        "files" => $files,//str_replace('"', '&quot;', $files),
                        "history" => str_replace('"', '&quot;', $history),
                        "files_edit" => $filesEdit,//str_replace('"', '&quot;', $filesEdit),
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
            if ($_POST['key'] != $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "1'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === "1" && (!in_array($_POST['source_id'], $_SESSION['personal_folders']) || !in_array($_POST['des_id'], $_SESSION['personal_folders']))) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "2'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            $returnValues = $pw = "";
            $is_perso = 0;

            if (isset($_POST['item_id']) && !empty($_POST['item_id']) && !empty($_POST['dest_id'])) {
                // load the original record into an array
                $originalRecord = DB::queryfirstrow(
                    "SELECT * FROM ".prefix_table("items")."
                    WHERE id=%i",
                    $_POST['item_id']
                );
                $dataDestination = DB::queryfirstrow(
                    "SELECT personal_folder FROM ".prefix_table("nested_tree")."
                    WHERE id=%i",
                    $_POST['dest_id']
                );

                // previous is personal folder and public one
                if ($originalRecord['perso'] === "1" && $dataDestination['personal_folder'] === "0") {
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
                }
                // previous is public folder and personal one
                else if ($originalRecord['perso'] === "0" && $dataDestination['personal_folder'] === "1") {
                    // check if PSK is set
                    if (!isset($_SESSION['user_settings']['session_psk']) || empty($_SESSION['user_settings']['session_psk'])){
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
                } else if ($originalRecord['perso'] === "1" && $dataDestination['personal_folder'] === "1") {
                // previous is public folder and personal one
                    // check if PSK is set
                    if (!isset($_SESSION['user_settings']['session_psk']) || empty($_SESSION['user_settings']['session_psk'])){
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
                    if ($key == "id_tree") {
                        array_push($aSet, array("id_tree" => $_POST['dest_id']));
                    } elseif ($key == "viewed_no") {
                        array_push($aSet, array("viewed_no" => "0"));
                    } elseif ($key == "pw" && !empty($pw)) {
                        array_push($aSet, array("pw" => $originalRecord['pw']));
                        array_push($aSet, array("pw_iv" => ""));
                    } elseif ($key == "perso") {
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
                $rows = DB::query("SELECT * FROM ".prefix_table("files")." WHERE id_item=%i",$newID);
                foreach ($rows as $record) {
                    DB::insert(
                        prefix_table('files'),
                        array(
                            'id_item' => $newID,
                            'name' => $record['name'],
                            'size' => $record['size'],
                            'extension' => $record['extension'],
                            'type' => $record['type'],
                            'file' => $record['file']
                           )
                    );
                }
                // Add this duplicate in logs
                logItems($newID, $originalRecord['label'], $_SESSION['user_id'], 'at_creation', $_SESSION['login']);
                // Add the fact that item has been copied in logs
                logItems($newID, $originalRecord['label'], $_SESSION['user_id'], 'at_copy', $_SESSION['login']);
                // reload cache table
                require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
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
            if ($_POST['key'] != $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo prepareExchangedData($returnValues, "encode");
                break;
            }

            $arrData = array();
            // return ID
            $arrData['id'] = $_POST['id'];
            $arrData['id_user'] = "9999999";
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
                    $_POST['id'],
                    "at_delete"
                );
                $dataDeleted = DB::count();

                $item_restored = DB::queryFirstRow(
                    "SELECT *
                    FROM ".prefix_table("log_items")."
                    WHERE id_item = %i AND action = %s
                    ORDER BY date DESC
                    LIMIT 0, 1",
                    $_POST['id'],
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
                $_POST['id'],
                "at_creation"
            );
            // LEFT JOIN ".$pre."categories_items as c ON (c.id_item = i.id)
            // INNER JOIN ".$pre."automatic_del as d ON (d.item_id = i.id)
            // Get all USERS infos
            $listNotif = array_filter(explode(";", $dataItem['notification']));
            $listRest = array_filter(explode(";", $dataItem['restricted_to']));
            $listeRestriction = $listNotification = $listNotificationEmails = "";
            $rows = DB::query("SELECT id, login, email FROM ".prefix_table("users"));
            foreach ($rows as $record) {
                // Get auhtor
                if ($record['id'] == $dataItem['id_user']) {
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
                }
                // Get notification list for users
                if (in_array($record['id'], $listNotif)) {
                    $listNotification .= $record['login'].";";
                    $listNotificationEmails .= $record['email'].",";
                }
            }

            // manage case of API user
            if (empty($dataItem['l.description'])) {
                $arrData['author'] = "API [".$dataItem['description']."]";
                $arrData['id_user'] = API_USER_ID;
                $arrData['author_email'] = "";
                $arrData['notification_status'] = false;
            }

            /*
            //Get auhtor
            $dataTmp = DB::queryfirstrow("SELECT login, email FROM ".$pre."users WHERE id= ".$dataItem['id_user']);
            $arrData['author'] = $dataTmp['login'];
            $arrData['author_email'] = $dataTmp['email'];
            $arrData['id_user'] = $dataItem['id_user'];
            */

            // Get all tags for this item
            $tags = "";
            $rows = DB::query("SELECT tag FROM ".prefix_table("tags")." WHERE item_id=%i", $_POST['id']);
            foreach ($rows as $record) {
                if (empty($tags)) $tags = "<span style='' class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".$record['tag']."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".$record['tag']."</span></span>";
                else $tags .= "&nbsp;&nbsp;<span style='' class='round-grey pointer tip' title='".addslashes($LANG['list_items_with_tag'])."' onclick='searchItemsWithTags(\"".$record['tag']."\")'><i class='fa fa-tag fa-sm'></i>&nbsp;<span class=\"item_tag\">".$record['tag']."</span></span>";
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
            $rows_tmp = DB::query("SELECT role_id FROM ".prefix_table("restriction_to_roles")." WHERE item_id=%i", $_POST['id']);
            $myTest = 0;
            if (in_array($_SESSION['user_id'], $rows_tmp)) {
                $myTest = 1;
            }
            // Uncrypt PW
            if (isset($_POST['salt_key_required']) && $_POST['salt_key_required'] == 1 && isset($_POST['salt_key_set']) && $_POST['salt_key_set'] == 1) {
                $pw = cryption(
                    $dataItem['pw'],
                    $_SESSION['user_settings']['session_psk'],
                    "decrypt",
                    "perso"
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
            if (isset($_POST['expired_item']) && $_POST['expired_item'] == 1) {
                $item_is_expired = true;
            } else {
                $item_is_expired = false;
            }
            // check user is admin
            if ($_SESSION['user_admin'] == 1 && $dataItem['perso'] != 1 && (isset($k['admin_full_right']) && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])) {
                $arrData['show_details'] = 0;
            }
            // Check if actual USER can see this ITEM
            elseif (
                ((in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] == 1) && ($dataItem['perso'] == 0 || ($dataItem['perso'] == 1 && $dataItem['id_user'] == $_SESSION['user_id'])) && $restrictionActive == false)
                ||
                (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 && $dataItem['anyone_can_modify'] == 1 && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] == 1) && $restrictionActive == false)
                ||
                (isset($_POST['folder_id']) && in_array($_POST['id'], $_SESSION['list_folders_limited'][$_POST['folder_id']]))
            ) {
                // Allow show details
                $arrData['show_details'] = 1;
                // Display menu icon for deleting if user is allowed
                if (
                    $dataItem['id_user'] == $_SESSION['user_id']
                    || $_SESSION['is_admin'] == 1
                    || ($_SESSION['user_manager'] == 1 && $_SESSION['settings']['manager_edit'] == 1)
                    || $dataItem['anyone_can_modify'] == 1
                    || in_array($dataItem['id_tree'], $_SESSION['list_folders_editable_by_role'])
                    || in_array($_SESSION['user_id'], $restrictedTo)
                ) {
                    $arrData['user_can_modify'] = 1;
                    $user_is_allowed_to_modify = true;
                } else {
                    $arrData['user_can_modify'] = 0;
                    $user_is_allowed_to_modify = false;
                }

                // Get restriction list for roles
                $listRestrictionRoles = array();
                if (isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) {
                    // Add restriction if item is restricted to roles
                    $rows = DB::query(
                        "SELECT t.title
                        FROM ".prefix_table("roles")."_title as t
                        INNER JOIN ".prefix_table("restriction_to_roles")." as r ON (t.id=r.role_id)
                        WHERE r.item_id = %i
                        ORDER BY t.title ASC",
                        $_POST['id']
                    );
                    foreach ($rows as $record) {
                        if (!in_array($record['title'], $listRestrictionRoles)) {
                            array_push($listRestrictionRoles, $record['title']);
                        }
                    }
                }
                // Check if any KB is linked to this item
                if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
                    $tmp = "";
                    $rows = DB::query(
                        "SELECT k.label, k.id
                        FROM ".prefix_table("kb_items")." as i
                        INNER JOIN ".prefix_table("kb")." as k ON (i.kb_id=k.id)
                        WHERE i.item_id = %i
                        ORDER BY k.label ASC",
                        $_POST['id']
                    );
                    foreach ($rows as $record) {
                        if (empty($tmp)) $tmp = "<a class='round-grey' href='".$_SESSION['settings']['cpassman_url']."/index.php?page=kb&id=".$record['id']."'><i class='fa fa-map-pin fa-sm'></i>&nbsp;".$record['label']."</a>";
                        else $tmp .= "&nbsp;<a class='round-grey' href='".$_SESSION['settings']['cpassman_url']."/index.php?page=kb&id=".$record['id']."'><i class='fa fa-map-pin fa-sm'></i>&nbsp;".$record['label']."</a>";
                    }
                    $arrData['links_to_kbs'] = $tmp;
                }
                // Prepare DIalogBox data
                if ($item_is_expired == false) {
                    $arrData['show_detail_option'] = 0;
                } elseif ($user_is_allowed_to_modify == true && $item_is_expired == true) {
                    $arrData['show_detail_option'] = 1;
                } else {
                    $arrData['show_detail_option'] = 2;
                }

                $arrData['label'] = htmlspecialchars_decode($dataItem['label']);
                $arrData['pw'] = $pw;
                $arrData['email'] = $dataItem['email'];
                $arrData['url'] = htmlspecialchars_decode($dataItem['url']);
                $arrData['folder'] = $dataItem['id_tree'];
                if (!empty($dataItem['url'])) {
                    $arrData['link'] = "&nbsp;<a href='".$dataItem['url']."' target='_blank'>&nbsp;<i class='fa fa-link tip' title='".$LANG['open_url_link']."'></i></a>";
                }

                $arrData['description'] = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags($dataItem['description'], $k['allowedTags']));
                $arrData['login'] = htmlspecialchars_decode(str_replace(array('"'), array('&quot;'), $dataItem['login']));
                $arrData['id_restricted_to'] = $listeRestriction;
                $arrData['id_restricted_to_roles'] = count($listRestrictionRoles) > 0 ? implode(";", $listRestrictionRoles).";" : "";
                $arrData['tags'] = $tags;
                $arrData['folder'] = $dataItem['id_tree'];

                if (isset($_SESSION['settings']['enable_server_password_change'])
                    && $_SESSION['settings']['enable_server_password_change'] == 1) {
                    $arrData['auto_update_pwd_frequency'] = $dataItem['auto_update_pwd_frequency'];
                } else {
                    $arrData['auto_update_pwd_frequency'] = "0";
                }

                if (isset($_SESSION['settings']['anyone_can_modify_bydefault'])
                    && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1) {
                    $arrData['anyone_can_modify'] = 1;
                } else {
                    $arrData['anyone_can_modify'] = $dataItem['anyone_can_modify'];
                }

                // statistics
                DB::update(
                    prefix_table("items"),
                    array(
                        'viewed_no' => $dataItem['viewed_no']+1,
                    ),
                    "id = %i",
                    $_POST['id']
                );
                $arrData['viewed_no'] = $dataItem['viewed_no']+1;

                // get fields
                $fieldsTmp = $arrCatList = "";
                if (
                    isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1
                    && isset($_POST['page']) && $_POST['page'] == "items"
                ) {
                    // get list of associated Categories
                    $arrCatList = array();
                    $rows_tmp = DB::query(
                        "SELECT id_category
                        FROM ".prefix_table("categories_folders")."
                        WHERE id_folder=%i",
                        $_POST['folder_id']
                    );
                    if (DB::count() > 0) {
                        foreach ($rows_tmp as $row) {
                            array_push($arrCatList, $row['id_category']);
                        }

                        // get fields for this Item
                        $rows_tmp = DB::query(
                            "SELECT i.field_id AS field_id, i.data AS data, i.data_iv AS data_iv, c.encrypted_data
                            FROM ".prefix_table("categories")."_items AS i
                            INNER JOIN ".prefix_table("categories")." AS c ON (i.field_id=c.id)
                            WHERE i.item_id=%i AND c.parent_id IN %ls",
                            $_POST['id'],
                            $arrCatList
                        );
                        foreach ($rows_tmp as $row) {
                            $fieldText = cryption(
                                $row['data'],
                                "",
                                "decrypt"
                            );
                            $fieldText = $fieldText['string'];
                            // build returned list of Fields text
                            if (empty($fieldsTmp)) {
                                $fieldsTmp = $row['field_id']."~~".str_replace('"', '&quot;', $fieldText);
                            } else {
                                $fieldsTmp .= "_|_".$row['field_id']."~~".str_replace('"', '&quot;', $fieldText);
                            }
                        }
                    }
                }
                $arrData['fields'] = $fieldsTmp;
                $arrData['categories'] = $arrCatList;

                // Manage user restriction
                if (isset($_POST['restricted'])) {
                    $arrData['restricted'] = $_POST['restricted'];
                } else {
                    $arrData['restricted'] = "";
                }
                // Decrement the number before being deleted
                $dataDelete = DB::queryfirstrow("SELECT * FROM ".prefix_table("automatic_del")." WHERE item_id=%i", $_POST['id']);
                $arrData['to_be_deleted'] = $dataDelete['del_value'];
                $arrData['to_be_deleted_type'] = $dataDelete['del_type'];
                if (isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1) {
                    if ($dataDelete['del_enabled'] == 1 || $arrData['id_user'] != $_SESSION['user_id']) {
                        if ($dataDelete['del_type'] == 1 && $dataDelete['del_value'] >= 1) {
                            // decrease counter
                            DB::update(
                                $pre."automatic_del",
                                array(
                                    'del_value' => $dataDelete['del_value'] - 1
                                   ),
                                "item_id = %i",
                                $_POST['id']
                            );
                            // store value
                            $arrData['to_be_deleted'] = $dataDelete['del_value'] - 1;
                        } elseif ($dataDelete['del_type'] == 1 && $dataDelete['del_value'] <= 1 || $dataDelete['del_type'] == 2 && $dataDelete['del_value'] < time()
                               )
                        {
                            $arrData['show_details'] = 0;
                            // delete item
                            DB::delete($pre."automatic_del", "item_id = %i", $_POST['id']);
                            // make inactive object
                            DB::update(
                                prefix_table("items"),
                                array(
                                    'inactif' => '1',
                                   ),
                                "id = %i",
                                $_POST['id']
                            );
                            // log
                            logItems($_POST['id'], $dataItem['label'], $_SESSION['user_id'], 'at_delete', $_SESSION['login'], 'at_automatically_deleted');
                            $arrData['to_be_deleted'] = 0;
                        } elseif ($dataDelete['del_type'] == 2) {
                            $arrData['to_be_deleted'] = date($_SESSION['settings']['date_format'], $dataDelete['del_value']);
                        }
                    } else {
                        $arrData['to_be_deleted'] = "";
                    }
                } else {
                    $arrData['to_be_deleted'] = "not_enabled";
                }
                // send notification if enabled
                if (isset($_SESSION['settings']['enable_email_notification_on_item_shown']) && $_SESSION['settings']['enable_email_notification_on_item_shown'] == 1) {
                    // send back infos
                    //$arrData['notification_list'] = $listNotification;
                    // Send email if activated
                    //if (!empty($listNotificationEmails) && !in_array($_SESSION['login'], explode(';', $listNotification))) {
                        DB::insert(
                            prefix_table('emails'),
                            array(
                                'timestamp' => time(),
                                'subject' => $LANG['email_on_open_notification_subject'],
                                'body' => str_replace(array('#tp_item_author#', '#tp_user#', '#tp_item#'), array(" ".addslashes($arrData['author']), addslashes($_SESSION['login']), addslashes($dataItem['label'])), $LANG['email_on_open_notification_mail']),
                                'receivers' => $listNotificationEmails,
                                'status' => ''
                               )
                        );
                    //}
                } else {
                    $arrData['notification_list'] = "";
                    $arrData['notification_status'] = "";
                }
            } else {
                $arrData['show_details'] = 0;
                // get readable list of restriction
                $listOfRestricted = "";
                if (!empty($dataItem['restricted_to'])) {
                    foreach (explode(';', $dataItem['restricted_to']) as $userRest) {
                        if (!empty($userRest)) {
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
            $arrData['timestamp'] = time();
            //print_r($arrData);
            // Encrypt data to return
            echo prepareExchangedData($arrData, "encode");
            break;

        /*
           * CASE
           * Display History of the selected Item
        */
        case "showDetailsStep2":
            // get Item info
            $dataItem = DB::queryfirstrow("SELECT * FROM ".prefix_table("items")." WHERE id=%i", $_POST['id']);

            // GET Audit trail
            $history = "";
            $historyOfPws = "";
            $rows = DB::query(
            "SELECT l.date as date, l.action as action, l.raison as raison, u.login as login, l.raison_iv AS raison_iv
                FROM ".prefix_table("log_items")." as l
                LEFT JOIN ".prefix_table("users")." as u ON (l.id_user=u.id)
                WHERE id_item=%i AND action <> %s
                ORDER BY date ASC",
                $_POST['id'],
                "at_shown"
            );
            foreach ($rows as $record) {
                $reason = explode(':', $record['raison']);
                if ($record['action'] == "at_modification" && $reason[0] == "at_pw ") {
                    // check if item is PF
                    if ($dataItem['perso'] != 1) {
                        $reason[1] = cryption(
                            $reason[1],
                            "",
                            "decrypt"
                        );
                    } else {
                        $reason[1] = cryption(
                            $reason[1],
                            $_SESSION['user_settings']['session_psk'],
                            "decrypt"
                        );
                    }
                    $reason[1] = @$reason[1]['string'];
                    // if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
                    if (!isUTF8($reason[1]) || is_array($reason[1])) {
                        $reason[1] = "";
                    }
                }
                // imported via API
                if ($record['login'] == "") {
                    $record['login'] = $LANG['imported_via_api'];
                }

                if (!empty($reason[1]) || $record['action'] == "at_copy" || $record['action'] == "at_creation" || $record['action'] == "at_manual" || $record['action'] == "at_modification" || $record['action'] == "at_delete" || $record['action'] == "at_restored") {
                    /*if (empty($history)) {
                        $history = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date'])." - ".$record['login']." - ".$LANG[$record['action']]." - ".(!empty($record['raison']) ? (count($reason) > 1 ? $LANG[trim($reason[0])].' : '.$reason[1] : ($record['action'] == "at_manual" ? $reason[0] : $LANG[trim($reason[0])])):'');
                    } else {
                        $history .= "<br />".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $record['date'])." - ".$record['login']." - ".$LANG[$record['action']]." - ".(!empty($record['raison']) ? (count($reason) > 1 ? $LANG[trim($reason[0])].' => '.$reason[1] : ($record['action'] == "at_manual" ? $reason[0] : $LANG[trim($reason[0])])):'');
                    }*/
                    if (trim($reason[0]) == "at_pw") {
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
            $rows = DB::query("SELECT id, name, file, extension FROM ".prefix_table("files")." WHERE id_item=%i", $_POST['id']);
            foreach ($rows as $record) {
                // get icon image depending on file format
                $iconImage = fileFormatImage($record['extension']);

                // prepare text to display
                if (strlen($record['name']) > 60 && strrpos($record['name'], ".") >= 56) {
                    $filename = substr($record['name'], 0, 50)."(truncated)".substr($record['name'], strrpos($record['name'], "."));
                } else {
                    $filename = $record['name'];
                }

                // If file is an image, then prepare lightbox. If not image, then prepare donwload
                if (in_array($record['extension'], $k['image_file_ext'])) {
                    $files .= '<div class=\'small_spacing\'><i class=\'fa fa-file-image-o\' /></i>&nbsp;<a class=\'image_dialog\' href=\'#'.$record['id'].'\' title=\''.$record['name'].'\'>'.$filename.'</a></div>';
                } else {
                    $files .= '<div class=\'small_spacing\'><i class=\'fa fa-file-text-o\' /></i>&nbsp;<a href=\'sources/downloadFile.php?name='.urlencode($record['name']).'&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'&fileid='.$record['id'].'\' class=\'small_spacing\'>'.$filename.'</a></div>';
                }
                // Prepare list of files for edit dialogbox
                $filesEdit .= '<span id=\'span_edit_file_'.$record['id'].'\'><span class=\'fa fa-'.$iconImage.'\'></span>&nbsp;<span class=\'fa fa-eraser tip\' style=\'cursor:pointer;\' onclick=\'delete_attached_file("'.$record['id'].'")\' title=\''.$LANG['at_delete'].'\'></span>&nbsp;'.$record['name']."</span><br />";
            }
            // display lists
            $filesEdit = str_replace('"', '&quot;', $filesEdit);
            $files_id = $files;

            // disable add bookmark if alread bookmarked
            if (in_array($_POST['id'], $_SESSION['favourites'])) {
                $favourite = 1;
            } else {
                $favourite = 0;
            }

            // Add the fact that item has been viewed in logs
            if (isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1) {
                logItems($_POST['id'], $dataItem['label'], $_SESSION['user_id'], 'at_shown', $_SESSION['login']);
            }

            // Add this item to the latests list
            if (isset($_SESSION['latest_items']) && isset($_SESSION['settings']['max_latest_items']) && !in_array($dataItem['id'], $_SESSION['latest_items'])) {
                if (count($_SESSION['latest_items']) >= $_SESSION['settings']['max_latest_items']) {
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

            // has this item a change proposal
            DB::query("SELECT * FROM ".$pre."items_change WHERE item_id = %i", $_POST['id']);

            echo prepareExchangedData(
                array(
                    "history" => htmlspecialchars($history, ENT_QUOTES, 'UTF-8'),
                    "history_of_pwds" => htmlspecialchars($historyOfPws, ENT_QUOTES, 'UTF-8'),
                    "favourite" => $favourite,
                    "files_edit" => $filesEdit,
                    "files_id" => $files_id,
                    "has_change_proposal" => DB::count()
                ),
                "encode"
            );
            break;

        /*
         * CASE
         * Delete an item
        */
        case "del_item":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // perform a check in case of Read-Only user creating an item in his PF
            if ($_SESSION['user_read_only'] === true && !in_array($dataReceived['categorie'], $_SESSION['personal_folders'])) {
                echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                break;
            }

            // delete item consists in disabling it
            DB::update(
                prefix_table("items"),
                array(
                    'inactif' => '1',
                   ),
                "id = %i",
                $_POST['id']
            );
            // log
            logItems($_POST['id'], $_POST['label'], $_SESSION['user_id'], 'at_delete', $_SESSION['login']);
            // Update CACHE table
            updateCacheTable("delete_value", $_POST['id']);
            break;

        /*
        * CASE
        * Update a Group
        */
        case "update_rep":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            // Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            // Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                echo '[ { "error" : "'.addslashes($LANG['error_html_codes']).'" } ]';
                break;
            }
            // check that title is not numeric
            if (is_numeric($title) === true) {
                echo '[{"error" : "ERR_TITLE_ONLY_WITH_NUMBERS"}]';;
                break;
            }

            // Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                $data = DB::queryFirstRow("SELECT id, title FROM ".prefix_table("nested_tree")." WHERE title = %s", $title);
                if (!empty($data['id']) && $dataReceived['folder'] != $data['id']) {
                    echo '[ { "error" : "'.addslashes($LANG['error_group_exist']).'" } ]';
                    break;
                }
            }

            // query on folder
            $data = DB::queryfirstrow(
                "SELECT parent_id, personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $dataReceived['folder']
            );

            // check if complexity level is good
            // if manager or admin don't care
            if ($_SESSION['is_admin'] != 1 && $_SESSION['user_manager'] != 1 && $data['personal_folder'] == "0") {
                $data = DB::queryfirstrow(
                    "SELECT valeur
                    FROM ".prefix_table("misc")."
                    WHERE intitule = %i AND type = %s",
                    $data['parent_id'],
                    "complex"
                );
                if (intval($dataReceived['complexity']) < intval($data['valeur'])) {
                    echo '[ { "error" : "'.addslashes($LANG['error_folder_complexity_lower_than_top_folder']." [<b>".$_SESSION['settings']['pwComplexity'][$data['valeur']][1]).'</b>]"} ]';
                    break;
                }
            }

            // update Folders table
            $tmp = DB::queryFirstRow(
                "SELECT title, parent_id, personal_folder FROM ".prefix_table("nested_tree")." WHERE id = %i",
                $dataReceived['folder']
            );
            if ( $tmp['parent_id'] != 0 || $tmp['title'] != $_SESSION['user_id'] || $tmp['personal_folder'] != 1 ) {
                DB::update(
                    prefix_table("nested_tree"),
                    array(
                        'title' => $title
                       ),
                    'id=%s',
                    $dataReceived['folder']
                );
                // update complixity value
                DB::update(
                    prefix_table("misc"),
                    array(
                        'valeur' => $dataReceived['complexity']
                       ),
                    'intitule = %s AND type = %s',
                    $dataReceived['folder'],
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
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                $returnValues = '[{"error" :  "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            $tmp_source = DB::queryFirstRow(
                "SELECT title, parent_id, personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $dataReceived['source_folder_id']
            );

            $tmp_target = DB::queryFirstRow(
                "SELECT title, parent_id, personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $dataReceived['target_folder_id']
            );

            // check if source or target folder is PF. If Yes, then cancel operation
            if (
                !($tmp_source['personal_folder'] == 1 && $tmp_target['personal_folder'] == 1)
                && !($tmp_source['personal_folder'] == 0 && $tmp_target['personal_folder'] == 0)
            ) {
                $returnValues = '[{"error" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // check Custom Fields consistency between both folders
            /*if (checkCFconsistency($dataReceived['source_folder_id'], $dataReceived['target_folder_id']) === false) {
                $returnValues = '[{"error" : "'.addslashes($LANG['error_custom_fields_not_similar_in_source_and_target_folders']).'"}]';
                echo $returnValues;
                break;
            }*/

            if ( $tmp_source['parent_id'] != 0 || $tmp_source['title'] != $_SESSION['user_id'] || $tmp_source['personal_folder'] != 1 || $tmp_target['title'] != $_SESSION['user_id'] || $tmp_target['personal_folder'] != 1) {

                // moving SOURCE folder
                DB::update(
                    prefix_table("nested_tree"),
                    array(
                        'parent_id' => $dataReceived['target_folder_id']
                       ),
                    'id=%s',
                    $dataReceived['source_folder_id']
                );
                $tree->rebuild();
            }


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
                    'parent_id' => $_POST['destination']
                   ),
                'id = %i',
                $_POST['source']
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
            if ($_POST['key'] != $_SESSION['key']) {    // || $_SESSION['user_read_only'] == true
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.str_replace('"', '\"', $LANG['error_not_allowed_to']).'"}]';
                echo prepareExchangedData($returnValues, "encode");
                break;
            }

            $arboHtml = $html = "";
            $folderIsPf = $showError = 0;
            $itemsIDList = $rights = $returnedData = array();
            // Build query limits
            if (empty($_POST['start'])) {
                $start = 0;
            } else {
                $start = $_POST['start'];
            }
            // Prepare tree
            $arbo = $tree->getPath($_POST['id'], true);
            foreach ($arbo as $elem) {
                if ($elem->title == $_SESSION['user_id'] && $elem->nlevel == 1) {
                    $elem->title = $_SESSION['login'];
                    $folderIsPf = 1;
                }
                $arboHtml_tmp = '<a class="path_element" id="path_elem_'.$elem->id.'"';
                if (in_array($elem->id, $_SESSION['groupes_visibles'])) {
                    $arboHtml_tmp .= ' style="cursor:pointer;" onclick="ListerItems('.$elem->id.', \'\', 0)"';
                }
                $arboHtml_tmp .= '>'. htmlspecialchars(stripslashes($elem->title), ENT_QUOTES). '</a>';
                if (empty($arboHtml)) {
                    $arboHtml = $arboHtml_tmp;
                } else {
                    $arboHtml .= '&nbsp;<i class="fa fa-caret-right"></i>&nbsp;'.$arboHtml_tmp;
                }
            }

            // check if this folder is a PF. If yes check if saltket is set
            if ((!isset($_SESSION['user_settings']['encrypted_psk']) || empty($_SESSION['user_settings']['encrypted_psk'])) && $folderIsPf == 1) {
                $showError = "is_pf_but_no_saltkey";
            }
            // check if items exist
            $where = new WhereClause('and');
            if (isset($_POST['restricted']) && $_POST['restricted'] == 1 && !empty($_SESSION['list_folders_limited'][$_POST['id']])) {
                $counter = count($_SESSION['list_folders_limited'][$_POST['id']]);
                $where->add('i.id IN %ls', $_SESSION['list_folders_limited'][$_POST['id']]);
            }
            // check if this folder is visible
            elseif (!in_array(
                $_POST['id'],
                array_merge(
                    $_SESSION['groupes_visibles'],
                    @array_keys($_SESSION['list_restricted_folders_for_items']),
                    @array_keys($_SESSION['list_folders_limited'])
                )
            )) {
                echo prepareExchangedData(array("error" => "not_authorized"), "encode");
                break;
            } else {
                DB::query("SELECT * FROM ".prefix_table("items")." WHERE inactif = %i", 0);
                $counter = DB::count();
                $where->add('i.id_tree=%i', $_POST['id']);
            }

            // store last folder accessed in cookie
            setcookie(
                "jstree_select",
                $_POST['id'],
                time() + 60 * 60 * 24 * $_SESSION['settings']['personal_saltkey_cookie_duration'],
                '/'
            );


            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];
            foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                $access = DB::queryFirstRow(
                    "SELECT type FROM ".prefix_table("roles_values")." WHERE role_id = %i AND folder_id = %i",
                    $role,
                    $_POST['id']
                );
                if ($access['type'] == "R") array_push($arrTmp, 1);
                else if ($access['type'] == "W") array_push($arrTmp, 0);
                else array_push($arrTmp, 3);
            }
            $accessLevel = min($arrTmp);
//echo $_POST['id']." - ".$accessLevel." - ";
            // if expiration is enable then catch lost recent password change
            /*if ($_SESSION['settings']['activate_expiration'] == 1) {
                $tmp_query = DB::query(
                    "SELECT `date`
                    FROM ".prefix_table("log_items")."
                    WHERE `action` IN ('at_creation, 'at_modification') AND `id_item`=2 ORDER BY `date` DESC LIMIT 1"
                );
            }*/

            $items_to_display_once = $_POST['nb_items_to_display_once'];

            if ($counter > 0 && empty($showError)) {
                // init variables
                $init_personal_folder = false;
                $expired_item = false;
                $limited_to_items = "";

                // List all ITEMS
                if ($folderIsPf == 0) {
                    $where->add('i.inactif=%i', 0);
                    $where->add('l.date=%l', "(SELECT date FROM ".prefix_table("log_items")." WHERE action IN ('at_creation', 'at_modification') AND id_item=i.id ORDER BY date DESC LIMIT 1)");
                    if (!empty($limited_to_items)) {
                        $where->add('i.id IN %ls', explode(",", $limited_to_items));
                    }

                    $query_limit = " LIMIT ".
                        mysqli_real_escape_string($link, filter_var($start, FILTER_SANITIZE_NUMBER_INT)).",".
                        mysqli_real_escape_string($link, filter_var($items_to_display_once, FILTER_SANITIZE_NUMBER_INT));

                    $rows = DB::query(
                        "SELECT i.id AS id, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                        MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                        MIN(i.anyone_can_modify) AS anyone_can_modify, l.date AS date,
                        MIN(n.renewal_period) AS renewal_period,
                        MIN(l.action) AS log_action, l.id_user AS log_user
                        FROM ".prefix_table("items")." AS i
                        INNER JOIN ".prefix_table("nested_tree")." AS n ON (i.id_tree = n.id)
                        INNER JOIN ".prefix_table("log_items")." AS l ON (i.id = l.id_item)
                        WHERE %l
                        GROUP BY i.id, l.date, l.id_user, l.action
                        ORDER BY i.label ASC, l.date DESC".$query_limit,//
                        $where
                    );
                } else {
                    $items_to_display_once = "max";
                    $where->add('i.inactif=%i',0);

                    $rows = DB::query(
                        "SELECT i.id AS id, MIN(i.restricted_to) AS restricted_to, MIN(i.perso) AS perso,
                        MIN(i.label) AS label, MIN(i.description) AS description, MIN(i.pw) AS pw, MIN(i.login) AS login,
                        MIN(i.anyone_can_modify) AS anyone_can_modify,l.date AS date,
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
                // REMOVED:  OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%')
                $idManaged = '';
                $i = 0;

                foreach ($rows as $record) {
                    // exclude all results except the first one returned by query
                    if (empty($idManaged) || $idManaged != $record['id']) {
                        // $returnedData[$i] = array('id' => $record['id']);
                        // Get Expiration date
                        $expirationFlag = '';
                        $expired_item = 0;
                        if ($_SESSION['settings']['activate_expiration'] == 1) {
                            if (
                                $record['renewal_period'] > 0 &&
                                ($record['date'] + ($record['renewal_period'] * $k['one_month_seconds'])) < time()
                            ) {
                                $expirationFlag = '<i class="fa fa-flag mi-red fa-sm"></i>&nbsp;';
                                $expired_item = 1;
                                //echo $record['renewal_period']." ,, ";
                            } else {
                                $expirationFlag = '<i class="fa fa-flag mi-green fa-sm"></i>&nbsp;';
                            }
                        }
                        // list of restricted users
                        $restricted_users_array = explode(';', $record['restricted_to']);
                        $itemPw = $itemLogin = "";
                        $displayItem = $need_sk = $canMove = $item_is_restricted_to_role = 0;
                        // TODO: Element is restricted to a group. Check if element can be seen by user
                        // => récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
                        $user_is_included_in_role = 0;
                        $roles = DB::query(
                            "SELECT role_id FROM ".prefix_table("restriction_to_roles")." WHERE item_id=%i",
                            $record['id']
                        );
                        if (count($roles) > 0) {
                            $item_is_restricted_to_role = 1;
                            foreach ($roles as $val) {
                                if (in_array($val['role_id'], $_SESSION['user_roles'])) {
                                    $user_is_included_in_role = 1;
                                    break;
                                }
                            }
                        }
                        // Manage the restricted_to variable
                        if (isset($_POST['restricted'])) {
                            $restrictedTo = $_POST['restricted'];
                        } else {
                            $restrictedTo = "";
                        }

                        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($_POST['id'], $_SESSION['list_folders_editable_by_role'])) {
                            if (empty($restrictedTo)) {
                                $restrictedTo = $_SESSION['user_id'];
                            } else {
                                $restrictedTo .= ','.$_SESSION['user_id'];
                            }
                        }

                        // Can user modify it?
                        if ($record['anyone_can_modify'] === "1"
                            || $_SESSION['user_id'] === $record['log_user']
                            || ($_SESSION['user_read_only'] === "1" && $folderIsPf === "0")
                            || (isset($_SESSION['settings']['manager_edit']) && $_SESSION['settings']['manager_edit'] === "1") // force draggable if user is manager
                        ) {
                            $canMove = 1;
                        }
                        // CASE where item is restricted to a role to which the user is not associated
                        if (
                            isset($user_is_included_in_role)
                            && $user_is_included_in_role == 0
                            && isset($item_is_restricted_to_role)
                            && $item_is_restricted_to_role == 1
                            && !in_array($_SESSION['user_id'], $restricted_users_array)
                            && !in_array($_POST['id'], $_SESSION['personal_folders'])
                        ) {
                            $perso = '<i class="fa fa-tag mi-red fa-sm"></i>&nbsp';
                            $findPfGroup = 0;
                            $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', \'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\')';
                            $displayItem = $need_sk = $canMove = 0;
                        }
                        // Case where item is in own personal folder
                        elseif (
                            in_array($_POST['id'], array_merge($_SESSION['personal_visible_groups'], $_SESSION['personal_folders']))
                            && $record['perso'] == 1
                        ) {
                            $perso = '<i class="fa fa-user-secret mi-grey-1 fa-sm"></i>&nbsp';
                            $findPfGroup = 1;
                            $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'1\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'1\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                            $displayItem = $need_sk = $canMove = 1;
                        }
                        // CAse where item is restricted to a group of users included user
                        elseif (
                            !empty($record['restricted_to'])
                            && in_array($_SESSION['user_id'], $restricted_users_array)
                            || (isset($_SESSION['list_folders_editable_by_role'])
                                    && in_array($_POST['id'], $_SESSION['list_folders_editable_by_role']))
                            && in_array($_SESSION['user_id'], $restricted_users_array)
                        ) {
                            $perso = '<i class="fa fa-tag mi-yellow fa-sm"></i>&nbsp';
                            $findPfGroup = 0;
                            $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                            $displayItem = 1;
                            $canMove = 1;
                        }
                        // CAse where item is restricted to a group of users not including user
                        elseif (
                            $record['perso'] === "1"
                            ||
                            (
                                !empty($record['restricted_to'])
                                && !in_array($_SESSION['user_id'], $restricted_users_array)
                            )
                            ||
                            (
                                isset($user_is_included_in_role)
                                && isset($item_is_restricted_to_role)
                                && $user_is_included_in_role == 0
                                && $item_is_restricted_to_role == 1
                            )
                        ) {
                            if (
                                isset($user_is_included_in_role)
                                && isset($item_is_restricted_to_role)
                                && $user_is_included_in_role == 0
                                && $item_is_restricted_to_role == 1
                            ) {
                                $perso = '<i class="fa fa-tag mi-red fa-sm"></i>&nbsp';
                                $findPfGroup = 0;
                                $action = 'AfficherDetailsItem(\''.$record['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\',\'\', \'\')';
                                $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\')';
                                $displayItem = $need_sk = $canMove = 0;
                            } else {
                                $perso = '<i class="fa fa-tag mi-yellow fa-sm"></i>&nbsp';
                                $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\')';
                                $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                                // reinit in case of not personal group
                                if ($init_personal_folder == false) {
                                    $findPfGroup = "";
                                    $init_personal_folder = true;
                                }

                                if (!empty($record['restricted_to']) && in_array($_SESSION['user_id'], $restricted_users_array)) {
                                    $displayItem = 1;
                                }
                            }
                        } else {
                            $perso = '<i class="fa fa-tag mi-green fa-sm"></i>&nbsp';
                            $action = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$record['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                            $displayItem = 1;
                            // reinit in case of not personal group
                            if ($init_personal_folder == false) {
                                $findPfGroup = "";
                                $init_personal_folder = true;
                            }
                        }
                        // Prepare full line
                        $html .= '<li name="'.strip_tags(htmlentities(cleanString($record['label']))).'" ondblclick="'.$action_dbl.'" class="';
                        if ($canMove == 1 && $accessLevel === 0) {
                            $html .= 'item_draggable';
                        } else {
                            $html .= 'item';
                        }

                        $html .= '" id="'.$record['id'].'" style="margin-left:-30px;">';

                        if ($canMove == 1 && $accessLevel === 0) {
                            $html .= '<span style="cursor:hand;" class="grippy"><i class="fa fa-sm fa-arrows mi-grey-1"></i>&nbsp;</span>';
                        } else {
                            $html .= '<span style="margin-left:11px;"></span>';
                        }

                        // manage text to show
                        $label = stripslashes(handleBackslash($record['label']));
                        if (!empty($record['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] === "1") {
                            $desc = explode("<br>", $record['description']);
                            $desc = strip_tags(stripslashes(cleanString($desc[0])));
                        } else {
                            $desc = "";
                        }
                        //$html .= $expirationFlag.''.$perso.'&nbsp;<a id="fileclass'.$record['id'].'" class="file" onclick="'.$action.'">'.$label.'&nbsp;<font size="1px">['.$desc.']</font></a></p>';

                            // manage text to show
                        $label = stripslashes(handleBackslash($record['label']));
                        if (!empty($record['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] === "1") {
                            $desc = explode("<br>", $record['description']);
                            $desc = strip_tags(stripslashes(cleanString($desc[0])));
                        } else {
                            $desc = "";
                        }
                        if (strlen($label) >= 95 || $desc === "") {
                            $html .= $expirationFlag.''.$perso.'&nbsp;<a id="fileclass'.$record['id'].'" class="file" onclick="'.$action.'">'.substr($label, 0, 100);
                        } else if (strlen($label) < 95 && strlen($label) > 65) {
                            $item_text = substr($label, 0, 65);
                            $html .= $expirationFlag.''.$perso.'&nbsp;<a id="fileclass'.$record['id'].'" class="file" onclick="'.$action.'">'.$item_text.'&nbsp;<font size="1px">['.substr($desc, 0, 95 - strlen($label)).']</font>';
                        } else if (strlen($label) <= 65) {
                            $item_text = substr($label, 0, 65);
                            $html .= $expirationFlag.''.$perso.'&nbsp;<a id="fileclass'.$record['id'].'" class="file" onclick="'.$action.'">'.$item_text.'&nbsp;<font size="1px">['.substr($desc, 0, 95 - strlen($label)).']</font>';
                        }

                        $html .= '</a>';

                        // increment array for icons shortcuts (don't do if option is not enabled)
                        if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                            if ($need_sk == true && isset($_SESSION['user_settings']['session_psk'])) {
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
                            if (!isUTF8($pw)) {
                                $pw = "";
                                $html .= '&nbsp;<i class="fa fa-warning fa-sm mi-red tip" title="'.$LANG['pw_encryption_error'].'"></i>'.$pw;
                            } else if (empty($pw)) {
                                $html .= '&nbsp;<i class="fa fa-exclamation-circle fa-sm mi-yellow tip" title="'.$LANG['password_is_empty'].'"></i>'.$pw;
                            }
                        } else {
                            $pw = "";
                        }

                        $html .= '<span style="float:right;margin:2px 10px 0px 0px;">';

                        // mini icon for collab
                        if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
                            if ($record['anyone_can_modify'] == 1) {
                                $html .= '<i class="fa fa-pencil fa-sm mi-grey-1 tip" title="'.$LANG['item_menu_collab_enable'].'"></i>&nbsp;&nbsp;';
                            }
                        }

                        // display quick icon shortcuts ?
                        if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                            if ($displayItem == true) {
                                if (!empty($record['login'])) {
                                    $html .= '<i class="fa fa-sm fa-user mi-black mini_login" data-clipboard-text="'.str_replace('"', "&quot;", $record['login']).'" title="'.$LANG['item_menu_copy_login'].'"></i>&nbsp;';
                                }
                                if (!empty($pw)) {
                                    $html .= '<i class="fa fa-sm fa-lock mi-black mini_pw" data-clipboard-text="'.str_replace('"', "&quot;", $pw).'" title="'.$LANG['item_menu_copy_pw'].'"></i>&nbsp;';
                                }
                            }
                        }
                        // Prepare make Favorite small icon
                        $html .= '<span id="quick_icon_fav_'.$record['id'].'" title="Manage Favorite" class="cursor tip">';
                        if (in_array($record['id'], $_SESSION['favourites'])) {
                            $html .= '<i class="fa fa-sm fa-star mi-yellow" onclick="ActionOnQuickIcon('.$record['id'].',0)" class="tip"></i>';
                        } else {
                            $html .= '<i class="fa fa-sm fa-star-o mi-black" onclick="ActionOnQuickIcon('.$record['id'].',1)" class="tip"></i>';
                        }

                        $html .= '</span></li>';
                        // Build array with items
                        array_push($itemsIDList, array($record['id'], $pw, $record['login'], $displayItem));

                        $i ++;
                    }
                    $idManaged = $record['id'];
                }

                $rights = recupDroitCreationSansComplexite($_POST['id']);
            }

            // Identify of it is a personal folder
            if (in_array($_POST['id'], $_SESSION['personal_visible_groups'])) {
                $findPfGroup = 1;
            } else {
                $findPfGroup = "";
            }
            // count
            DB::query(
                "SELECT *
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                WHERE %l
                ORDER BY i.label ASC, l.date DESC",
                $where
            );
            $counter = DB::count();
            // DELETE - 2.1.19 - AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
            // Check list to be continued status
            if (($items_to_display_once + $start) < $counter && $items_to_display_once != "max") {
                $listToBeContinued = "yes";
            } else {
                $listToBeContinued = "end";
            }
            // Get folder complexity
            $folderComplexity = DB::queryFirstRow(
                "SELECT valeur FROM ".prefix_table("misc")." WHERE type = %s AND intitule = %i",
                "complex",
                $_POST['id']
            );

            //  Fixing items not being displayed
            $html = iconv('UTF-8', 'UTF-8//IGNORE', mb_convert_encoding($html, "UTF-8", "UTF-8"));

            // Has this folder some categories to be displayed?
            $displayCategories = "";
            if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
                $catRow = DB::query(
                    "SELECT id_category FROM ".prefix_table("categories_folders")." WHERE id_folder = %i", $_POST['id']
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


            // Prepare returned values
            $returnValues = array(
                "recherche_group_pf" => $findPfGroup,
                "arborescence" => $arboHtml,
                "array_items" => $itemsIDList,
                "items_html" => $html,
                "error" => $showError,
                "saltkey_is_required" => $folderIsPf,
                "show_clipboard_small_icons" => isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1 ? 1 : 0,
                "next_start" => $_POST['nb_items_to_display_once'] + $start,
                "list_to_be_continued" => $listToBeContinued,
                "items_count" => $counter,
                'folder_complexity' => $folderComplexity['valeur'],
                // "items" => $returnedData
                'displayCategories' => $displayCategories,
                'access_level' => $accessLevel,
                'IsPersonalFolder' => $folderIsPf
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
            // get some info about ITEM
            $dataItem = DB::queryfirstrow(
                "SELECT perso, anyone_can_modify
                FROM ".prefix_table("items")."
                WHERE id=%i",
                $_POST['item_id']
            );
            // is user allowed to access this folder - readonly
            if (isset($_POST['groupe']) && !empty($_POST['groupe'])) {
                if (in_array($_POST['groupe'], $_SESSION['read_only_folders']) || !in_array($_POST['groupe'], $_SESSION['groupes_visibles'])) {
                    // check if this item can be modified by anyone
                    if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
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

            if (isset($_POST['item_id']) && !empty($_POST['item_id'])) {
                // Lock Item (if already locked), go back and warn
                $dataTmp = DB::queryFirstRow("SELECT timestamp, user_id FROM ".prefix_table("items_edition")." WHERE item_id = %i", $_POST['item_id']);//echo ">".$dataTmp[0];

                // If token is taken for this Item and delay is passed then delete it.
                if (isset($_SESSION['settings']['delay_item_edition']) &&
                    $_SESSION['settings']['delay_item_edition'] > 0 && !empty($dataTmp['timestamp']) &&
                    round(abs(time()-$dataTmp['timestamp']) / 60, 2) > $_SESSION['settings']['delay_item_edition']
                ) {
                    DB::delete(prefix_table("items_edition"), "item_id = %i", $_POST['item_id']);
                    //reload the previous data
                    $dataTmp = DB::queryFirstRow(
                        "SELECT timestamp, user_id FROM ".prefix_table("items_edition")." WHERE item_id = %i",
                        $_POST['item_id']
                    );
                }

                // If edition by same user (and token not freed before for any reason, then update timestamp)
                if (!empty($dataTmp['timestamp']) && $dataTmp['user_id'] == $_SESSION['user_id']) {
                    DB::update(
                        prefix_table("items_edition"),
                        array(
                            "timestamp" => time()
                        ),
                        "user_id = %i AND item_id = %i",
                        $_SESSION['user_id'],
                        $_POST['item_id']
                    );
                    // If no token for this Item, then initialize one
                } elseif (empty($dataTmp[0])) {
                    DB::insert(
                        prefix_table("items_edition"),
                        array(
                                'timestamp' => time(),
                                'item_id' => $_POST['item_id'],
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
                $_POST['groupe']
            );

            // check if user can perform this action
            if (isset($_POST['context']) && !empty($_POST['context'])) {
                if ($_POST['context'] == "create_folder" || $_POST['context'] == "edit_folder" || $_POST['context'] == "delete_folder") {
                    if (
                        $_SESSION['is_admin'] == 1
                        || ($_SESSION['user_manager'] == 1)
                        || (
                           isset($_SESSION['settings']['enable_user_can_create_folders'])
                           && $_SESSION['settings']['enable_user_can_create_folders'] == 1
                        )
                        || (
                            $data_this_folder['personal_folder'] === "1" && $data_this_folder['title'] === $_SESSION['user_id']
                        )   // take into consideration if this is a personal folder
                    ) {
                        // allow
                    } else {
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
                $_POST['groupe']
            );

            if (isset($data['valeur']) && (!empty($data['valeur']) || $data['valeur'] == 0)) {
                $complexity = $_SESSION['settings']['pwComplexity'][$data['valeur']][1];
                $folder_is_personal = $data['personal_folder'];
            } else {
                $complexity = $LANG['not_defined'];

                // if not defined, then previous query failed and personal_folder is null
                // do new query to know if current folder is pf
                $data_pf = DB::queryFirstRow(
                    "SELECT personal_folder
                    FROM ".prefix_table("nested_tree")."
                    WHERE id = %s",
                    $_POST['groupe']
                );
                $folder_is_personal = $data_pf['personal_folder'];
            }
            // Prepare Item actual visibility (what Users/Roles can see it)
            $visibilite = "";
            if (!empty($dataPf[0])) {
                $visibilite = $_SESSION['login'];
            } else {
                $rows = DB::query(
                    "SELECT t.title
                    FROM ".prefix_table("roles_values")." as v
                    INNER JOIN ".prefix_table("roles_title")." as t ON (v.role_id = t.id)
                    WHERE v.folder_id = %i
                    GROUP BY title",
                    $_POST['groupe']
                );
                foreach ($rows as $record) {
                    if (empty($visibilite)) {
                        $visibilite = $record['title'];
                    } else {
                        $visibilite .= " - ".$record['title'];
                    }
                }
            }

            recupDroitCreationSansComplexite($_POST['groupe']);

            $returnValues = array(
                "val" => $data['valeur'],
                "visibility" => $visibilite,
                "complexity" => $complexity,
                "personal" => $folder_is_personal
            );
            echo prepareExchangedData($returnValues, "encode");
            break;

        /*
          * CASE
          * WANT TO CLIPBOARD PW/LOGIN OF ITEM
        */
        case "get_clipboard_item":
            $dataItem = DB::queryfirstrow(
                "SELECT pw,login,perso FROM ".prefix_table("items")." WHERE id=%i", $_POST['id']
            );

            if ($_POST['field'] == "pw") {
                if ($dataItem['perso'] == 1) {
                    $data = cryption(
                        $dataItem['pw'],
                        $_SESSION['user_settings']['session_psk'],
                        "decrypt"
                    );
                } else {
                    $data = cryption(
                        $dataItem['pw'],
                        "",
                        "decrypt"
                    );
                }
            } else {
                $data = $dataItem['login'];
            }
            // Encrypt data to return
            echo prepareExchangedData($data, "encode");
            break;

        /*
        * CASE
        * DELETE attached file from an item
        */
        case "delete_attached_file":
            // Get some info before deleting
            $data = DB::queryFirstRow("SELECT name,id_item,file FROM ".prefix_table("files")." WHERE id = %i", $_POST['file_id']);
            if (!empty($data['id_item'])) {
                // Delete from FILES table
                DB::delete(prefix_table("files"), "id = %i", $_POST['file_id']);
                // Update the log
                logItems($data['id_item'], $dataItem['label'], $_SESSION['user_id'], 'at_modification', $_SESSION['login'],'at_del_file : '.$data['name']);
                // Delete file from server
                @unlink($_SESSION['settings']['path_to_upload_folder']."/".$data['file']);
            }
            break;

        /*
        * CASE
        * REBUILD the description editor
        */
        case "rebuild_description_textarea":
            $returnValues = array();
            if (isset($_SESSION['settings']['richtext']) && $_SESSION['settings']['richtext'] == 1) {
                if ($_POST['id'] == "desc") {
                    $returnValues['desc'] = '$("#desc").ckeditor({toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]], height: 100,language: "'.$k['langs'][$_SESSION['user_language']].'"});';
                } elseif ($_POST['id'] == "edit_desc") {
                    $returnValues['desc'] = 'CKEDITOR.replace("edit_desc",{toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]], height: 100,language: "'.$k['langs'][$_SESSION['user_language']].'"});';
                }
            }
            // Multselect
            $returnValues['multi_select'] = '$("#edit_restricted_to_list").multiselect({selectedList: 7, minWidth: 430, height: 145, checkAllText: "'.$LANG['check_all_text'].'", uncheckAllText: "'.$LANG['uncheck_all_text'].'",noneSelectedText: "'.$LANG['none_selected_text'].'"});';
            // Display popup
            if ($_POST['id'] == "edit_desc") {
                $returnValues['dialog'] = '$("#div_formulaire_edition_item").dialog("open");';
            } else {
                $returnValues['dialog'] = '$("#div_formulaire_saisi").dialog("open");';
            }
            echo $returnValues;
            break;

        /*
        * CASE
        * Clear HTML tags
        */
        case "clear_html_tags":
            // Get information for this item
            $dataItem = DB::queryfirstrow(
                "SELECT description FROM ".prefix_table("items")." WHERE id=%i", $_POST['id_item']
            );
            // Clean up the string
            echo json_encode(array("description" => strip_tags($dataItem['description'])), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
            break;

        /*
        * FUNCTION
        * Launch an action when clicking on a quick icon
        * $action = 0 => Make not favorite
        * $action = 1 => Make favorite
        */
        case "action_on_quick_icon":
            if ($_POST['action'] == 1) {
                // Add new favourite
                array_push($_SESSION['favourites'], $_POST['id']);
                DB::update(
                    prefix_table("users"),
                    array(
                        'favourites' => implode(';', $_SESSION['favourites'])
                       ),
                    'id = %i',
                    $_SESSION['user_id']
                );
                // Update SESSION with this new favourite
                $data = DB::queryfirstrow("SELECT label,id_tree FROM ".prefix_table("items")." WHERE id = ".mysqli_real_escape_string($link, filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)));
                $_SESSION['favourites_tab'][$_POST['id']] = array(
                    'label' => $data['label'],
                    'url' => 'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$_POST['id']
                   );
            } elseif ($_POST['action'] == 0) {
                // delete from session
                foreach ($_SESSION['favourites'] as $key => $value) {
                    if ($_SESSION['favourites'][$key] == $_POST['id']) {
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
                        if ($key == $_POST['id']) {
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
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true || !isset($_SESSION['settings']['pwd_maximum_length'])) {
                // error
                exit();
            }
            // get data about item
            $dataSource = DB::queryfirstrow(
                "SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("nested_tree")." as f ON (i.id_tree=f.id)
                WHERE i.id=%i",
                $_POST['item_id']
            );
            // get data about new folder
            $dataDestination = DB::queryfirstrow(
                "SELECT personal_folder, title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                $_POST['folder_id']
            );

            // previous is non personal folder and new too
            if ($dataSource['personal_folder'] == 0 && $dataDestination['personal_folder'] == 0) {
                // just update is needed. Item key is the same
                DB::update(
                    prefix_table("items"),
                    array(
                        'id_tree' => $_POST['folder_id']
                       ),
                    "id=%i",
                    $_POST['item_id']
                );
            } elseif ($dataSource['personal_folder'] == 0 && $dataDestination['personal_folder'] == 1) {
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
                        'id_tree' => $_POST['folder_id'],
                        'pw' => $encrypt['string'],
                        'pw_iv' => "",
                        'perso' => 1
                    ),
                    "id=%i",
                    $_POST['item_id']
                );
            }
            // If previous is personal folder and new is personal folder too => no key exist on item
            elseif ($dataSource['personal_folder'] == 1 && $dataDestination['personal_folder'] == 1) {
                // just update is needed. Item key is the same
                DB::update(
                    prefix_table("items"),
                    array(
                        'id_tree' => $_POST['folder_id']
                    ),
                    "id=%i",
                    $_POST['item_id']
                );
            }
            // If previous is personal folder and new is not personal folder => no key exist on item => add new
            elseif ($dataSource['personal_folder'] == 1 && $dataDestination['personal_folder'] == 0) {
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
                        'id_tree' => $_POST['folder_id'],
                        'pw' => $encrypt['string'],
                        'pw_iv' => "",
                        'perso' => 0
                    ),
                    "id=%i",
                    $_POST['item_id']
                );
            }
            // Log item moved
            logItems($_POST['item_id'], $dataSource['label'], $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_moved : '.$dataSource['title'].' -> '.$dataDestination['title']);

            echo '[{"from_folder":"'.$dataSource['id_tree'].'" , "to_folder":"'.$_POST['folder_id'].'"}]';
            break;

        /*
        * CASE
        * MASSIVE Move an ITEM
        */
        case "mass_move_items":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true || !isset($_SESSION['settings']['pwd_maximum_length'])) {
                // error
                exit();
            }

            // loop on items to move
            foreach (explode(";", $_POST['item_ids']) as $item_id) {
                if (!empty($item_id)) {
                    // get data about item
                    $dataSource = DB::queryfirstrow(
                        "SELECT i.pw, f.personal_folder,i.id_tree, f.title,i.label
                        FROM ".prefix_table("items")." as i
                        INNER JOIN ".prefix_table("nested_tree")." as f ON (i.id_tree=f.id)
                        WHERE i.id=%i",
                        $item_id
                    );
                    // get data about new folder
                    $dataDestination = DB::queryfirstrow(
                        "SELECT personal_folder, title FROM ".prefix_table("nested_tree")." WHERE id = %i",
                        $_POST['folder_id']
                    );

                    // previous is non personal folder and new too
                    if ($dataSource['personal_folder'] == 0 && $dataDestination['personal_folder'] == 0) {
                        // just update is needed. Item key is the same
                        DB::update(
                            prefix_table("items"),
                            array(
                                'id_tree' => $_POST['folder_id']
                               ),
                            "id=%i",
                            $item_id
                        );
                    } elseif ($dataSource['personal_folder'] == 0 && $dataDestination['personal_folder'] == 1) {
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
                                'id_tree' => $_POST['folder_id'],
                                'pw' => $encrypt['string'],
                                'pw_iv' => "",
                                'perso' => 1
                            ),
                            "id=%i",
                            $item_id
                        );
                    }
                    // If previous is personal folder and new is personal folder too => no key exist on item
                    elseif ($dataSource['personal_folder'] == 1 && $dataDestination['personal_folder'] == 1) {
                        // just update is needed. Item key is the same
                        DB::update(
                            prefix_table("items"),
                            array(
                                'id_tree' => $_POST['folder_id']
                            ),
                            "id=%i",
                            $item_id
                        );
                    }
                    // If previous is personal folder and new is not personal folder => no key exist on item => add new
                    elseif ($dataSource['personal_folder'] == 1 && $dataDestination['personal_folder'] == 0) {
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
                                'id_tree' => $_POST['folder_id'],
                                'pw' => $encrypt['string'],
                                'pw_iv' => "",
                                'perso' => 0
                            ),
                            "id=%i",
                            $item_id
                        );
                    }
                    // Log item moved
                    logItems($item_id, $dataSource['label'], $_SESSION['user_id'], 'at_modification', $_SESSION['login'], 'at_moved : '.$dataSource['title'].' -> '.$dataDestination['title']);
                }
            }

            // reload cache table
            require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
            updateCacheTable("reload", "");

            echo '[{"error":"" , "status":"ok"}]';
            break;

        /*
         * CASE
         * MASSIVE Delete an item
        */
        case "mass_delete_items":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key']) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($LANG['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }

            // loop on items to move
            foreach (explode(";", $_POST['item_ids']) as $item_id) {
                if (!empty($item_id)) {
                    // get info
                    $dataSource = DB::queryfirstrow(
                        "SELECT label, id_tree
                        FROM ".prefix_table("items")."
                        WHERE id=%i",
                        $item_id
                    );

                    // perform a check in case of Read-Only user creating an item in his PF
                    if ($_SESSION['user_read_only'] === true) {
                        echo prepareExchangedData(array("error" => "ERR_FOLDER_NOT_ALLOWED"), "encode");
                        break;
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
                    logItems($item_id, $dataSource['label'], $_SESSION['user_id'], 'at_delete', $_SESSION['login']);
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            } else {
                if (!empty($_POST['content'])) {
                    $content = explode(',', $_POST['content']);
                }
                // get links url
                if (empty($_SESSION['settings']['email_server_url'])) {
                    $_SESSION['settings']['email_server_url'] = $_SESSION['settings']['cpassman_url'];
                }
                if ($_POST['cat'] == "request_access_to_author") {
                    $dataAuthor = DB::queryfirstrow("SELECT email,login FROM ".prefix_table("users")." WHERE id= ".$content[1]);
                    $dataItem = DB::queryfirstrow("SELECT label FROM ".prefix_table("items")." WHERE id= ".$content[0]);
                    $ret = @sendEmail(
                        $LANG['email_request_access_subject'],
                        str_replace(array('#tp_item_author#', '#tp_user#', '#tp_item#'), array(" ".addslashes($dataAuthor['login']), addslashes($_SESSION['login']), addslashes($dataItem['label'])), $LANG['email_request_access_mail']),
                        $dataAuthor['email']
                    );
                } elseif ($_POST['cat'] == "share_this_item") {
                    $dataItem = DB::queryfirstrow(
                        "SELECT label,id_tree
                        FROM ".prefix_table("items")."
                        WHERE id= %i",
                        (mysqli_real_escape_string($link, filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT)))
                    );
                    // send email
                    $ret = @sendEmail(
                        $LANG['email_share_item_subject'],
                        str_replace(
                            array('#tp_link#', '#tp_user#', '#tp_item#'),
                            array($_SESSION['settings']['email_server_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.mysqli_real_escape_string($link, filter_var($_POST['id'], FILTER_SANITIZE_STRING)), addslashes($_SESSION['login']), addslashes($dataItem['label'])),
                            $LANG['email_share_item_mail']
                        ),
                        $_POST['receipt']
                    );
                }
                echo '[{'.$ret.'}]';
            }
            break;

        /*
           * CASE
           * manage notification of an Item
        */
        case "notify_a_user":
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            } else {
                if ($_POST['notify_type'] === "on_show") {
                    // Check if values already exist
                    $data = DB::queryfirstrow("SELECT notification FROM ".prefix_table("items")." WHERE id = %i", $_POST['item_id']);
                    $notifiedUsers = explode(';', $data['notification']);
                    // User is not in actual notification list
                    if ($_POST['status'] === "true" && !in_array($_POST['user_id'], $notifiedUsers)) {
                        // User is not in actual notification list and wants to be notified
                        DB::update(
                            prefix_table("items"),
                            array(
                                'notification' => empty($data['notification']) ? $_POST['user_id'].";" : $data['notification'].$_POST['user_id']
                               ),
                            "id=%i",
                            $_POST['item_id']
                        );
                        echo '[{"error" : "", "new_status":"true"}]';
                        break;
                    } elseif ($_POST['status'] == false && in_array($_POST['user_id'], $notifiedUsers)) {
                        // TODO : delete user from array and store in DB
                        // User is in actual notification list and doesn't want to be notified
                        DB::update(
                            prefix_table("items"),
                            array(
                                'notification' => empty($data['notification']) ? $_POST['user_id'] : $data['notification'].";".$_POST['user_id']
                               ),
                            "id=%i",
                            $_POST['item_id']
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
            if ($_POST['key'] != $_SESSION['key']) {
                $data = array("error" => "key_is_wrong");
                echo prepareExchangedData($data, "encode");
                break;
            } else {
                // decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData($_POST['data'], "decode");
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

                if (
                    (
                        (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles'])) && ($dataItem['perso'] == 0 || ($dataItem['perso'] == 1 && $dataItem['id_user'] == $_SESSION['user_id'])) && $restrictionActive == false
                    )
                    ||
                    (
                        isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1 && $dataItem['anyone_can_modify'] == 1 && (in_array($dataItem['id_tree'], $_SESSION['groupes_visibles']) || $_SESSION['is_admin'] == 1) && $restrictionActive == false
                    )
                    ||
                    (@in_array($_POST['id'], $_SESSION['list_folders_limited'][$_POST['folder_id']]))
                ) {
                    $error = "";
                    // Query
                    logItems($dataReceived['item_id'], $dataItem['label'], $_SESSION['user_id'], 'at_manual', $_SESSION['login'], htmlspecialchars_decode($dataReceived['label']));
                    // Prepare new line
                    $data = DB::queryfirstrow(
                        "SELECT * FROM ".prefix_table("log_items")." WHERE id_item = %i ORDER BY date DESC",
                        $dataReceived['item_id']
                    );
                    //$reason = explode(':', $data['raison']);
                    $historic = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $data['date'])." - ".$_SESSION['login']." - ".$LANG[$data['action']]." - ".$data['raison'];
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // Do
            DB::delete(prefix_table("items_edition"), "item_id = %i", $_POST['id']);
            break;

        /*
        * CASE
        * Check if Item has been changed since loaded
        */
        case "is_item_changed":
            $data = DB::queryFirstRow(
                "SELECT date FROM ".prefix_table("log_items")." WHERE action = %s AND id_item = %i ORDER BY date DESC",
                "at_modification",
                $_POST['item_id']
            );
            // Check if it's in a personal folder. If yes, then force complexity overhead.
            if ($data['date'] > $_POST['timestamp']) {
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // delete all existing old otv codes
            $rows = DB::query("SELECT id FROM ".prefix_table("otv")." WHERE timestamp < ".(time() - $_SESSION['settings']['otv_expiration_period'] * 86400));
            foreach ($rows as $record) {
                DB::delete(prefix_table('otv'), "id=%i", $record['id']);
            }

            // generate session
            $otv_code = GenerateCryptKey(32, false, true, true, true, false);

            DB::insert(
                prefix_table("otv"),
                array(
                    'id' => null,
                    'item_id' => intval($_POST['id']),
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

            if (!isset($_SESSION['settings']['otv_expiration_period'])) {
                $_SESSION['settings']['otv_expiration_period'] = 7;
            }
            $url = $_SESSION['settings']['cpassman_url']."/index.php?otv=true&".http_build_query($otv_session);
            $exp_date = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time() + (intval($_SESSION['settings']['otv_expiration_period'])*86400));

            echo json_encode(
                array(
                    "error" => "",
                    "url" => str_replace(
                        array("#URL#", "#DAY#"),
                        array('<span id=\'otv_link\'>'.$url.'</span>&nbsp;<span class=\'fa-stack tip" title=\''.addslashes($LANG['copy']).'\' style=\'cursor:pointer;\' id=\'button_copy_otv_link\'><span class=\'fa fa-square fa-stack-2x\'></span><span class=\'fa fa-clipboard fa-stack-1x fa-inverse\'></span></span>', $exp_date),
                        $LANG['one_time_view_item_url_box']
                    )
                )
            );
            break;

        /*
        * CASE
        * Free Item for Edition
        */
        case "image_preview_preparation":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get file info
            $file_info = DB::queryfirstrow("SELECT file, status FROM ".prefix_table("files")." WHERE id=%i", substr($_POST['uri'], 1));

            // prepare image info
            $image_code = $file_info['file'];
            $extension = substr($_POST['title'], strrpos($_POST['title'], '.')+1);
            $file_to_display = $_SESSION['settings']['url_to_upload_folder'].'/'.$image_code;
            $file_suffix = "";

            // Prepare encryption options
            $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
            $iv = substr(hash("md5", "iv".$ascii_key), 0, 8);
            $key = substr(hash("md5", "ssapmeat1".$ascii_key, true), 0, 24);
            $opts = array('iv'=>$iv, 'key'=>$key);


            // should we encrypt/decrypt the file
            encrypt_or_decrypt_file($file_info['file'], $file_info['status'], $opts);
/*
            if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] === "1" && isset($file_info['status']) && $file_info['status'] === "clear") {
                // file needs to be encrypted
                if (file_exists($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code)) {
                    // make a copy of file
                    if (!copy(
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy"
                    )) {
                        $error = "Copy not possible";
                        exit;
                    } else {
                        // do a bck
                        copy(
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".bck"
                        );
                    }

                    // Open the file
                    unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code);
                    $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy", "rb");
                    $out = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code, 'wb');

                    // ecnrypt
                    stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);

                    // read file and create new one
                    while (($line = fgets($fp)) !== false) {
                        fputs($out, $line);
                    }
                    fclose($fp);
                    fclose($out);

                    // update table
                    DB::update(
                        prefix_table('files'),
                        array(
                            'status' => 'encrypted'
                           ),
                        "id=%i",
                        substr($_POST['uri'], 1)
                    );
                }
            } elseif (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] === "0" && isset($file_info['status']) && $file_info['status'] === "encrypted") {
                // file needs to be encrypted
                if (file_exists($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code)) {
                    // make a copy of file
                    if (!copy(
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy"
                    )) {
                        $error = "Copy not possible";
                        exit;
                    } else {
                        // do a bck
                        copy(
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                            $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".bck"
                        );
                    }

                    // Open the file
                    unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code);
                    $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy", "rb");
                    $out = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code, 'wb');

                    // ecnrypt
                    stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts);

                    // read file and create new one
                    while (($line = fgets($fp)) !== false) {
                        fputs($out, $line);
                    }
                    fclose($fp);
                    fclose($out);

                    // update table
                    DB::update(
                        prefix_table('files'),
                        array(
                            'status' => 'clear'
                           ),
                        "id=%i",
                        substr($_POST['uri'], 1)
                    );
                }
            }
*/
            // should we decrypt the attachment?
            if (isset($file_info['status']) && $file_info['status'] === "encrypted") {

                @unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code."_delete.".$extension);

                // Open the file
                $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code, 'rb');
                $fp_new = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code."_delete.".$extension, 'wb');

                // Add the Mcrypt stream filter
                stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts);
                // copy stream
                stream_copy_to_stream($fp, $fp_new);
                // close files
                fclose($fp);
                fclose($fp_new);
                // prepare variable
                //$_POST['uri'] = $_POST['uri']."_delete.";
                $file_to_display = $file_to_display."_delete.".$extension;
                $file_suffix = "_delete.".$extension;
            }

            // Encrypt data to return
            echo prepareExchangedData(
                array(
                    "error" => "",
                    "new_file" => $file_to_display,
                    "file_suffix" => $file_suffix,
                    "file_path" => $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code."_delete.".$extension
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // get file info
            $result = DB::queryfirstrow("SELECT file FROM ".prefix_table("files")." WHERE id=%i", substr($_POST['uri'], 1));

            @unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$result['file'].$_POST['file_suffix']);

            break;

        /*
        * CASE
        * Get list of users that have access to the folder
        */
        case "get_refined_list_of_users":
            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
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
                $_POST['iFolderId']
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            $error = "";
            $duplicate = 0;

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");
            // Prepare variables
            $label = htmlspecialchars_decode($dataReceived['label']);
            $idFolder = $dataReceived['idFolder'];

            // don't check if Personal Folder
            $data = DB::queryFirstRow("SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i", $idFolder);
            if ($data['title'] == $_SESSION['user_id']) {
                // send data
                echo '[{"duplicate" : "'.$duplicate.'" , error" : ""}]';
            } else {
                if ($_POST['option'] == "same_folder") {
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
                    if (!empty($row['id'])) {
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
                    if (!empty($arrayPf)) {
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }

            // Build list of visible folders
            $selectVisibleFoldersOptions = $selectVisibleNonPersonalFoldersOptions = $selectVisibleActiveFoldersOptions = "";
            if (isset($_SESSION['settings']['can_create_root_folder']) && $_SESSION['settings']['can_create_root_folder'] == 1) {
                $selectVisibleFoldersOptions = '<option value="0">'.$LANG['root'].'</option>';
            }

            if ($_SESSION['user_admin'] == 1 && (isset($k['admin_full_right'])
                && $k['admin_full_right'] == true) || !isset($k['admin_full_right'])) {
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
            $tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();
            $folders = $tree->getDescendants();

            foreach ($folders as $folder) {
                // Be sure that user can only see folders he/she is allowed to
                if (
                    !in_array($folder->id, $_SESSION['forbiden_pfs'])
                    || in_array($folder->id, $_SESSION['groupes_visibles'])
                    || in_array($folder->id, $listFoldersLimitedKeys)
                    || in_array($folder->id, $listRestrictedFoldersForItemsKeys)
                ) {
                    $displayThisNode = false;
                    $hide_node = false;
                    $nbChildrenItems = 0;
                    // Check if any allowed folder is part of the descendants of this node
                    $nodeDescendants = $tree->getDescendants($folder->id, true, false, true);
                    foreach ($nodeDescendants as $node) {
                        // manage tree counters
                        if (isset($_SESSION['settings']['tree_counters']) && $_SESSION['settings']['tree_counters'] == 1) {
                            DB::query(
                                "SELECT * FROM ".prefix_table("items")."
                                WHERE inactif=%i AND id_tree = %i",
                                0,
                                $node
                            );
                            $nbChildrenItems += DB::count();
                        }
                        if (
                            in_array(
                                $node,
                                array_merge($_SESSION['groupes_visibles'], $_SESSION['list_restricted_folders_for_items'])
                            )
                            || @in_array($node, $listFoldersLimitedKeys)
                            || @in_array($node, $listRestrictedFoldersForItemsKeys)
                        ) {
                            $displayThisNode = true;
                            //break;
                        }
                    }

                    if ($displayThisNode == true) {
                        $ident = "";
                        for ($x = 1; $x < $folder->nlevel; $x++) {
                            $ident .= "&nbsp;&nbsp;";
                        }

                        // resize title if necessary
                        $fldTitle = str_replace("&", "&amp;", $folder->title);

                        // rename personal folder with user login
                        if ($folder->title == $_SESSION['user_id'] && $folder->nlevel == 1 ) {
                            $fldTitle = $_SESSION['login'];
                        }

                        // build select for all visible folders
                        if (in_array($folder->id, $_SESSION['groupes_visibles']) && !in_array($folder->id, $_SESSION['read_only_folders'])) {
                            if ($_SESSION['user_read_only'] == 0 || ($_SESSION['user_read_only'] == 1 && in_array($folder->id, $_SESSION['personal_visible_groups']))) {
                                if (($folder->title == $_SESSION['user_id'] && $folder->nlevel == 1)) { //
                                    $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" >'.$ident.$fldTitle.'</option>';
                                } else {
                                    $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.$fldTitle.'</option>';
                                }
                            } else {
                                $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                            }
                        } else {
                            $selectVisibleFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                        }
                        // build select for non personal visible folders
                        if (isset($_SESSION['all_non_personal_folders']) && in_array($folder->id, $_SESSION['all_non_personal_folders'])) {
                            $selectVisibleNonPersonalFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.$fldTitle.'</option>';
                        } else {
                            $selectVisibleNonPersonalFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                        }
                        // build select for active folders (where user can do something)
                        if (isset($_SESSION['list_restricted_folders_for_items']) && !in_array($folder->id, $_SESSION['read_only_folders'])) {
                            $selectVisibleActiveFoldersOptions .= '<option value="'.$folder->id.'">'.$ident.$fldTitle.'</option>';
                        } else {
                            $selectVisibleActiveFoldersOptions .= '<option value="'.$folder->id.'" disabled="disabled">'.$ident.$fldTitle.'</option>';
                        }
                    }
                }
            }

            $data = array(
                'error' => "",
                'selectVisibleFoldersOptions' => ($selectVisibleFoldersOptions),
                'selectVisibleNonPersonalFoldersOptions' => ($selectVisibleNonPersonalFoldersOptions),
                'selectVisibleActiveFoldersOptions' => ($selectVisibleActiveFoldersOptions),
                'selectFullVisibleFoldersOptions' => str_replace('disabled="disabled"', "", $selectVisibleFoldersOptions)
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

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
                if ($record['action'] == "at_modification" && $reason[0] == "at_pw ") {
                    // check if item is PF
                    if ($dataItem['perso'] != 1) {
                        $reason[1] = cryption(
                            $reason[1],
                            "",
                            "decrypt"
                        );
                    } else {
                        $reason[1] = cryption(
                            $reason[1],
                            $_SESSION['user_settings']['session_psk'],
                            "decrypt"
                        );
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

                if (!empty($reason[1]) || $record['action'] == "at_copy" || $record['action'] == "at_creation" || $record['action'] == "at_manual" || $record['action'] == "at_modification" || $record['action'] == "at_delete" || $record['action'] == "at_restored") {

                    // prepare avatar
                    if (isset($record['avatar_thumb']) && !empty($record['avatar_thumb'])) {
                        if (file_exists($_SESSION['settings']['cpassman_url'].'/includes/avatars/'.$record['avatar_thumb'])) {
                            $avatar = $_SESSION['settings']['cpassman_url'].'/includes/avatars/'.$record['avatar_thumb'];
                        } else {
                            $avatar = $_SESSION['settings']['cpassman_url'].'/includes/images/photo.jpg';
                        }
                    } else {
                        $avatar = $_SESSION['settings']['cpassman_url'].'/includes/images/photo.jpg';
                    }

                    $history .= '<tr style="">'.
                        '<td rowspan="2" style="width:40px;"><img src="'.$avatar.'" style="border-radius:20px; height:35px;"></td>'.
                        '<td colspan="2" style="font-size:11px;"><i>'.$LANG['by'].' '.$record['login'].' '.$LANG['at'].' '.date($_SESSION['settings']['date_format'].' '.$_SESSION['settings']['time_format'], $record['date']).'</i></td></tr>'.
                        '<tr style="border-bottom:3px solid #C9C9C9;"><td style="width:100px;"><b>'.$LANG[$record['action']].'</b></td>'.
                        '<td style="">' . (!empty($record['raison']) && $record['action'] !== "at_creation" ? (count($reason) > 1 ? $LANG[trim($reason[0])].' : '.handleBackslash($reason[1]) : ($record['action'] == "at_manual" ? $reason[0] : $LANG[trim($reason[0])])) : '') . '</td>'.
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
            if ($_POST['key'] != $_SESSION['key']) {
                echo '[ { "error" : "key_not_conform" } ]';
                break;
            }
            // decrypt and retrieve data in JSON format
            $data_received = prepareExchangedData($_POST['data'], "decode");

            // prepare variables
            $label = htmlspecialchars_decode($data_received['label']);
            $pwd = htmlspecialchars_decode($data_received['pwd']);
            $login = htmlspecialchars_decode($data_received['login']);
            $email = htmlspecialchars_decode($data_received['email']);
            $url = htmlspecialchars_decode($data_received['url']);
            $folder = htmlspecialchars_decode($data_received['folder']);
            //$description = htmlspecialchars_decode($data_received['description']);
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
                    $record['email']
                );
            }

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
                //echo $record['tag']."|".$record['tag']."\n";
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
    global $db;
    $data = DB::queryFirstRow(
        "SELECT bloquer_creation, bloquer_modification, personal_folder FROM ".prefix_table("nested_tree")." WHERE id = %i",
        $groupe
    );
    // Check if it's in a personal folder. If yes, then force complexity overhead.
    if ($data['personal_folder'] == 1) {
        return array("bloquer_modification_complexite" => 1, "bloquer_creation_complexite" => 1);
    } else {
        return array("bloquer_modification_complexite" => $data['bloquer_modification'], "bloquer_creation_complexite" => $data['bloquer_creation']);
    }
}

/*
* FUNCTION
* permits to identify what icon to display depending on file extension
*/
function fileFormatImage($ext)
{
    global $k;
    if (in_array($ext, $k['office_file_ext'])) {
        $image = "file-word-o";
    } elseif ($ext == "pdf") {
        $image = "file-pdf-o";
    } elseif (in_array($ext, $k['image_file_ext'])) {
        $image = "file-image-o";
    } elseif ($ext == "txt") {
        $image = "file-text-o";
    } else {
        $image = "file-o";
    }

    return $image;
}

/*
* FUNCTION
* permits to remplace some specific characters in password
*/
function passwordReplacement($pw)
{
    $pwPatterns = array('/ETCOMMERCIAL/', '/SIGNEPLUS/');
    $pwRemplacements = array('&', '+');

    return preg_replace($pwPatterns, $pwRemplacements, $pw);
}
