<?php
/**
 * @file          items.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    //include 'error.php';
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
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
include 'main.functions.php';
// pw complexity levels
$pwComplexity = array(0 => array(0, $txt['complex_level0']),
    25 => array(25, $txt['complex_level1']),
    50 => array(50, $txt['complex_level2']),
    60 => array(60, $txt['complex_level3']),
    70 => array(70, $txt['complex_level4']),
    80 => array(80, $txt['complex_level5']),
    90 => array(90, $txt['complex_level6'])
   );

$allowedTags = '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>';

//Class loader
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
$db = new SplClassLoader('Database\Core', '../includes/libraries');
$db->register();
$db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
$db->connect();

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// Do asked action
if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        /*
        * CASE
        * creating a new ITEM
        */
        case "new_item":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "something_wrong"), "encode");
                break;
            }
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            // Prepare variables
            $label = htmlspecialchars_decode($dataReceived['label']);
            $url = htmlspecialchars_decode($dataReceived['url']);
            $pw = htmlspecialchars_decode($dataReceived['pw']);
            $login = htmlspecialchars_decode($dataReceived['login']);
            $tags = htmlspecialchars_decode($dataReceived['tags']);

            // is author authorized to create in this folder
            if (in_array($dataReceived['categorie'], array_keys($_SESSION['list_folders_limited']))) {
                echo prepareExchangedData(array("error" => "something_wrong"), "encode");
                break;
            }

            if (!empty($pw)) {
                // Check length
                if (strlen($pw) > $_SESSION['settings']['pwd_maximum_length']) {
                    echo prepareExchangedData(array("error" => "pw_too_long"), "encode");
                    break;
                }
                // ;check if element doesn't already exist
                $itemExists = 0;
                $newID = "";
                //$data = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."items WHERE label = '".addslashes($label)."' AND inactif=0");
                $data = $db->queryCount(
                    "items",
                    array(
                        "label" => addslashes($label),
                        "inactif" => "0"
                    )
                );
                if ($data[0] != 0) {
                    $itemExists = 1;
                } else {
                    $itemExists = 0;
                }

                if (
                    (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 0 && $itemExists == 0)
                    ||
                    (isset($_SESSION['settings']['duplicate_item']) && $_SESSION['settings']['duplicate_item'] == 1)
                ) {
                    // set key if non personal item
                    if ($dataReceived['is_pf'] != 1) {
                        // generate random key
                        $randomKey = generateKey();
                        $pw = $randomKey.$pw;
                    }
                    // encrypt PW
                    if ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) {
                        $pw = encrypt($pw, mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                        $restictedTo = $_SESSION['user_id'];
                    } else {
                        $pw = encrypt($pw);
                    }
                    if (empty($pw)) {
                        echo prepareExchangedData(array("error" => "something_wrong"), "encode");
                        break;
                    }
                    // ADD item
                    $newID = $db->queryInsert(
                        'items',
                        array(
                            'label' => $label,
                            'description' => $dataReceived['description'],
                            'pw' => $pw,
                            'email' => $dataReceived['email'],
                            'url' => $url,
                            'id_tree' => $dataReceived['categorie'],
                            'login' => $login,
                            'inactif' => '0',
                            'restricted_to' => isset($dataReceived['restricted_to']) ? $dataReceived['restricted_to'] : '',
                            'perso' => ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) ? '1' : '0',
                            'anyone_can_modify' => (isset($dataReceived['anyone_can_modify']) && $dataReceived['anyone_can_modify'] == "on") ? '1' : '0'
                           )
                    );

                    // update fields
                    if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
                        foreach (explode("_|_", $dataReceived['fields']) as $field) {
                            $field_data = explode("~~", $field);
                            if (count($field_data)>1 && !empty($field_data[1])) {
                                // generate Key for fields
                                $randomKeyFields = generateKey();
                                // Store generated key for Field
                                $db->queryInsert(
                                    'keys',
                                    array(
                                        'table' => 'categories_items',
                                        'id' => $newID,
                                        'rand_key' => $randomKeyFields
                                    )
                                );

                                $db->queryInsert(
                                    'categories_items',
                                    array(
                                        'item_id' => $newID,
                                        'field_id' => $field_data[0],
                                        'data' => encrypt($randomKeyFields.$field_data[1])
                                    )
                                );
                            }
                        }
                    }


                    // If automatic deletion asked
                    if ($dataReceived['to_be_deleted'] != 0 && !empty($dataReceived['to_be_deleted'])) {
                        $date_stamp = dateToStamp($dataReceived['to_be_deleted']);
                        $db->queryInsert(
                            'automatic_del',
                            array(
                                'item_id' => $newID,
                                'del_enabled' => 1, // 0=deactivated;1=activated
                                'del_type' => $date_stamp != false ? 2 : 1, // 1=counter;2=date
                                'del_value' => $date_stamp != false ? $date_stamp : $dataReceived['to_be_deleted']
                               )
                        );
                    }
                    // Store generated key
                    if ($dataReceived['is_pf'] != 1) {
                        $db->queryInsert(
                            'keys',
                            array(
                                'table' => 'items',
                                'id' => $newID,
                                'rand_key' => $randomKey
                               )
                        );
                    }
                    // Manage retriction_to_roles
                    if (isset($dataReceived['restricted_to_roles'])) {
                        foreach (array_filter(explode(';', $dataReceived['restricted_to_roles'])) as $role) {
                            $db->queryInsert(
                                'restriction_to_roles',
                                array(
                                    'role_id' => $role,
                                    'item_id' => $newID
                                   )
                            );
                        }
                    }
                    // log
                    $db->queryInsert(
                        'log_items',
                        array(
                            'id_item' => $newID,
                            'date' => time(),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_creation'
                           )
                    );
                    // Add tags
                    $tags = explode(' ', $tags);
                    foreach ($tags as $tag) {
                        if (!empty($tag)) {
                            $db->queryInsert(
                                'tags',
                                array(
                                    'item_id' => $newID,
                                    'tag' => strtolower($tag)
                                   )
                            );
                        }
                    }
                    // Check if any files have been added
                    if (!empty($dataReceived['random_id_from_files'])) {
                        $sql = "SELECT id
                                FROM ".$pre."files
                                WHERE id_item=".$dataReceived['random_id_from_files'];
                        $rows = $db->fetchAllArray($sql);
                        foreach ($rows as $reccord) {
                            // update item_id in files table
                            $db->queryUpdate(
                                'files',
                                array(
                                    'id_item' => $newID
                                   ),
                                "id='".$reccord['id']."'"
                            );
                        }
                    }
                    // Update CACHE table
                    updateCacheTable("add_value", $newID);
                    // Announce by email?
                    if ($dataReceived['annonce'] == 1) {
                        // send email
                        foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                            // send it
                            @sendEmail(
                                $txt['email_subject'],
                                $txt['email_body_1'].mysql_real_escape_string(stripslashes(($_POST['label']))).$txt['email_body_2'].$txt['email_body_3'],
                                $emailAddress,
                                $txt['email_altbody_1']." ".mysql_real_escape_string(stripslashes(($_POST['label'])))." ".$txt['email_altbody_2']
                            );
                        }
                    }
                    // Get Expiration date
                    $expirationFlag = '';
                    if ($_SESSION['settings']['activate_expiration'] == 1) {
                        $expirationFlag = '<img src="includes/images/flag-green.png">';
                    }
                    // Prepare full line
                    $html = '<li class="item_draggable'
                    .'" id="'.$newID.'" style="margin-left:-30px;">'
                    .'<img src="includes/images/grippy.png" style="margin-right:5px;cursor:hand;" alt="" class="grippy"  />'
                    .$expirationFlag.'<img src="includes/images/tag-small-green.png">' .
                    '&nbsp;<a id="fileclass'.$newID.'" class="file" onclick="AfficherDetailsItem(\''.$newID.'\', \'0\', \'\', \'\', \'\', \'\', \'\')">' .
                    stripslashes($dataReceived['label']);
                    if (!empty($dataReceived['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1) {
                        $html .= '&nbsp;<font size=2px>['.strip_tags(stripslashes(substr(cleanString($dataReceived['description']), 0, 30))).']</font>';
                    }
                    $html .= '</a><span style="float:right;margin:2px 10px 0px 0px;">';
                    // display quick icon shortcuts ?
                    if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                        $itemLogin = '<img src="includes/images/mini_user_disable.png" id="icon_login_'.$newID.'" />';
                        $itemPw = '<img src="includes/images/mini_lock_disable.png" id="icon_pw_'.$newID.'" class="copy_clipboard" />';

                        if (!empty($dataReceived['login'])) {
                            $itemLogin = '<img src="includes/images/mini_user_enable.png" id="icon_login_'.$newID.'" class="copy_clipboard" title="'.$txt['item_menu_copy_login'].'" />';
                        }
                        if (!empty($dataReceived['pw'])) {
                            $itemPw = '<img src="includes/images/mini_lock_enable.png" id="icon_pw_'.$newID.'" class="copy_clipboard" title="'.$txt['item_menu_copy_pw'].'" />';
                        }
                        $html .= $itemLogin.'&nbsp;'.$itemPw;
                        // $html .= '<input type="hidden" id="item_pw_in_list_'.$newID.'" value="'.$dataReceived['pw'].'"><input type="hidden" id="item_login_in_list_'.$newID.'" value="'.$dataReceived['login'].'">';
                    }
                    // Prepare make Favorite small icon
                    $html .= '&nbsp;<span id="quick_icon_fav_'.$newID.'" title="Manage Favorite" class="cursor">';
                    if (in_array($newID, $_SESSION['favourites'])) {
                        $html .= '<img src="includes/images/mini_star_enable.png" onclick="ActionOnQuickIcon('.$newID.',0)" />';
                    } else {
                        $html .= '<img src="includes/images/mini_star_disable.png"" onclick="ActionOnQuickIcon('.$newID.',1)" />';
                    }
                    // mini icon for collab
                    if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
                        if ($dataReceived['anyone_can_modify'] == 1) {
                            $itemCollab = '&nbsp;<img src="includes/images/mini_collab_enable.png" title="'.$txt['item_menu_collab_enable'].'" />';
                        } else {
                            $itemCollab = '&nbsp;<img src="includes/images/mini_collab_disable.png" title="'.$txt['item_menu_collab_disable'].'" />';
                        }
                        $html .= '</span>'.$itemCollab.'</span>';
                    }

                    $html .= '</li>';
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
            } else {
                $returnValues = array("error" => "something_wrong");
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
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "something_wrong"), "encode");
                break;
            }
            // init
            $reloadPage = false;
            $returnValues = array();
            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            if (count($dataReceived) > 0) {
                // Prepare variables
                $label = htmlspecialchars_decode($dataReceived['label']);
                $url = htmlspecialchars_decode($dataReceived['url']);
                $pw = $original_pw = $sentPw = htmlspecialchars_decode($dataReceived['pw']);
                $login = htmlspecialchars_decode($dataReceived['login']);
                $tags = htmlspecialchars_decode($dataReceived['tags']);
                // Get all informations for this item
                $sql = "SELECT *
                        FROM ".$pre."items as i
                        INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                        WHERE i.id=".$dataReceived['id']."
                        AND l.action = 'at_creation'";
                $dataItem = $db->queryFirst($sql);
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
                    // Check length
                    if (strlen($pw) > $_SESSION['settings']['pwd_maximum_length']) {
                        echo prepareExchangedData(array("error" => "pw_too_long"), "encode");
                        break;
                    }
                    // Get existing values
                    $data = $db->queryFirst(
                        "SELECT i.id as id, i.label as label, i.description as description, i.pw as pw, i.url as url, i.id_tree as id_tree, i.perso as perso, i.login as login,
                        i.inactif as inactif, i.restricted_to as restricted_to, i.anyone_can_modify as anyone_can_modify, i.email as email, i.notification as notification,
                        u.login as user_login, u.email as user_email
                        FROM ".$pre."items as i
                        INNER JOIN ".$pre."log_items as l ON (i.id=l.id_item)
                        INNER JOIN ".$pre."users as u ON (u.id=l.id_user)
                        WHERE i.id=".$dataReceived['id']
                    );
                    // Manage salt key
                    if ($data['perso'] != 1) {
                        // Get original key
                        $originalKey = $db->queryFirst(
                            "SELECT `rand_key`
                            FROM `".$pre."keys`
                            WHERE `table` LIKE 'items' AND `id`=".$dataReceived['id']
                        );
                        // if no randkey then generate it
                        if (empty($originalKey['rand_key'])) {
                            $randomKey = generateKey();
                            $pw = $sentPw = $randomKey.$pw;
                            // store key prefix
                            $db->queryInsert(
                                'keys',
                                array(
                                    'table'     => 'items',
                                    'id'        => $data['id'],
                                    'rand_key'  => $randomKey
                                )
                            );
                        } else {
                            $pw = $sentPw = $originalKey['rand_key'].$pw;
                        }
                    }
                    // encrypt PW
                    if ($dataReceived['salt_key_set'] == 1 && isset($dataReceived['salt_key_set']) && $dataReceived['is_pf'] == 1 && isset($dataReceived['is_pf'])) {
                        $sentPw = $pw;
                        $pw = encrypt($pw, mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                        $restictedTo = $_SESSION['user_id'];
                    } else {
                        $pw = encrypt($pw);
                    }
                    if (empty($pw)) {
                        echo prepareExchangedData(array("error" => "something_wrong"), "encode");
                        break;
                    }
                    // ---Manage tags
                    // deleting existing tags for this item
                    $db->query("DELETE FROM ".$pre."tags WHERE item_id = '".$dataReceived['id']."'");
                    // Add new tags
                    $tags = explode(' ', $tags);
                    foreach ($tags as $tag) {
                        if (!empty($tag)) {
                            $db->queryInsert(
                                'tags',
                                array(
                                    'item_id' => $dataReceived['id'],
                                    'tag' => strtolower($tag)
                                )
                            );
                        }
                    }
                    // update item
                    $db->queryUpdate(
                        'items',
                        array(
                            'label' => $label,
                            'description' => $dataReceived['description'],
                            'pw' => $pw,
                            'email' => $dataReceived['email'],
                            'login' => $login,
                            'url' => $url,
                            'id_tree' => $dataReceived['categorie'],
                            'restricted_to' => $dataReceived['restricted_to'],
                            'anyone_can_modify' => (isset($dataReceived['anyone_can_modify']) && $dataReceived['anyone_can_modify'] == "on") ? '1' : '0'
                           ),
                        "id='".$dataReceived['id']."'"
                    );
                    // update fields
                    if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
                        foreach (explode("_|_", $dataReceived['fields']) as $field) {
                            $field_data = explode("~~", $field);
                            if (count($field_data)>1 && !empty($field_data[1])) {
                                $dataTmp = $db->fetchRow(
                                    "SELECT c.title AS title, i.data AS data
                                    FROM ".$pre."categories_items AS i
                                    INNER JOIN ".$pre."categories AS c ON (i.field_id=c.id)
                                    WHERE i.field_id = '".intval($field_data[0])."'
                                    AND i.item_id=".intval($dataReceived['id'])
                                );
                                // store Field text in DB
                                if (count($dataTmp[0]) == 0) {
                                    // generate Key for fields
                                    $randomKeyFields = generateKey();
                                    // Store generated key for Field
                                    $db->queryInsert(
                                        'keys',
                                        array(
                                            'table' => 'categories_items',
                                            'id' => $dataReceived['id'],
                                            'rand_key' => $randomKeyFields
                                        )
                                    );
                                    // store field text
                                    $db->queryInsert(
                                        'categories_items',
                                        array(
                                            'item_id' => $dataReceived['id'],
                                            'field_id' => $field_data[0],
                                            'data' => encrypt($randomKeyFields.$field_data[1])
                                        )
                                    );
                                    // update LOG
                                    $db->queryInsert(
                                        'log_items',
                                        array(
                                            'id_item' => $dataReceived['id'],
                                            'date' => time(),
                                            'id_user' => $_SESSION['user_id'],
                                            'action' => 'at_creation',
                                            'raison' => 'at_field : '.$dataTmp[0]
                                        )
                                    );
                                } else {
                                    // get key for original Field
                                    $originalKeyField = $db->queryFirst('SELECT rand_key FROM `'.$pre.'keys` WHERE `table` LIKE "categories_items" AND `id` ='.$dataReceived['id']);

                                    // compare the old and new value
                                    $oldVal = substr(decrypt($dataTmp[1]), strlen($originalKeyField['rand_key']));
                                    if ($field_data[1] != $oldVal) {
                                        // update value
                                        $db->queryUpdate(
                                            'categories_items',
                                            array(
                                                'data' => encrypt($originalKeyField['rand_key'].$field_data[1])
                                            ),
                                            'item_id = "'.$dataReceived['id'].'" AND field_id = "'.$field_data[0].'"'
                                        );

                                        // update LOG
                                        $db->queryInsert(
                                            'log_items',
                                            array(
                                                'id_item' => $dataReceived['id'],
                                                'date' => time(),
                                                'id_user' => $_SESSION['user_id'],
                                                'action' => 'at_modification',
                                                'raison' => 'at_field : '.$dataTmp[0].' => '.$oldVal
                                            )
                                        );
                                    }
                                }
                            } else {
                                if (empty($field_data[1])) {
                                    $db->query("DELETE FROM ".$pre."categories_items WHERE item_id = '".$dataReceived['id']."' AND field_id = '".$field_data[0]."'");
                                }
                            }
                        }
                    }

                    // Update automatic deletion - Only by the creator of the Item
                    if (isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1) {
                        // check if elem exists in Table. If not add it or update it.
                        //$dataTmp = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."automatic_del WHERE item_id = '".$dataReceived['id']."'");
                        $dataTmp = $db->queryCount(
                            "automatic_del",
                            array(
                                "item_id" => $dataReceived['id']
                            )
                        );
                        if ($dataTmp[0] == 0) {
                            // No automatic deletion for this item
                            if (!empty($dataReceived['to_be_deleted']) || ($dataReceived['to_be_deleted'] > 0 && is_numeric($dataReceived['to_be_deleted']))) {
                                // Automatic deletion to be added
                                $db->queryInsert(
                                    'automatic_del',
                                    array(
                                        'item_id' => $dataReceived['id'],
                                        'del_enabled' => 1,
                                        'del_type' => is_numeric($dataReceived['to_be_deleted']) ? 1 : 2,
                                        'del_value' => is_numeric($dataReceived['to_be_deleted']) ? $dataReceived['to_be_deleted'] : dateToStamp($dataReceived['to_be_deleted'])
                                       )
                                );
                                // update LOG
                                $db->queryInsert(
                                    'log_items',
                                    array(
                                        'id_item' => $dataReceived['id'],
                                        'date' => time(),
                                        'id_user' => $_SESSION['user_id'],
                                        'action' => 'at_modification',
                                        'raison' => 'at_automatic_del : '.$dataReceived['to_be_deleted']
                                       )
                                );
                            }
                        } else {
                            // Automatic deletion exists for this item
                            if (!empty($dataReceived['to_be_deleted']) || ($dataReceived['to_be_deleted'] > 0 && is_numeric($dataReceived['to_be_deleted']))) {
                                // Update automatic deletion
                                $db->queryUpdate(
                                    "automatic_del",
                                    array(
                                        'del_type' => is_numeric($dataReceived['to_be_deleted']) ? 1 : 2,
                                        'del_value' => is_numeric($dataReceived['to_be_deleted']) ? $dataReceived['to_be_deleted'] : dateToStamp($dataReceived['to_be_deleted'])
                                       ),
                                    "item_id = ".$dataReceived['id']
                                );
                            } else {
                                // delete automatic deleteion for this item
                                $db->query("DELETE FROM ".$pre."automatic_del WHERE item_id = '".$dataReceived['id']."'");
                            }
                            // update LOG
                            $db->queryInsert(
                                'log_items',
                                array(
                                    'id_item' => $dataReceived['id'],
                                    'date' => time(),
                                    'id_user' => $_SESSION['user_id'],
                                    'action' => 'at_modification',
                                    'raison' => 'at_automatic_del : '.$dataReceived['to_be_deleted']
                                   )
                            );
                        }
                    }
                    // get readable list of restriction
                    $listOfRestricted = $oldRestrictionList = "";
                    if (!empty($dataReceived['restricted_to']) && $_SESSION['settings']['restricted_to'] == 1) {
                        foreach (explode(';', $dataReceived['restricted_to']) as $userRest) {
                            if (!empty($userRest)) {
                                $dataTmp = $db->queryFirst("SELECT login FROM ".$pre."users WHERE id= ".$userRest);
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
                                    $dataTmp = $db->queryFirst("SELECT login FROM ".$pre."users WHERE id= ".$userRest);
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
                        $rows = $db->fetchAllArray(
                            "SELECT t.title
                            FROM ".$pre."roles_title as t
                            INNER JOIN ".$pre."restriction_to_roles as r ON (t.id=r.role_id)
                            WHERE r.item_id = ".$dataReceived['id']."
                            ORDER BY t.title ASC"
                        );
                        foreach ($rows as $reccord) {
                            if (empty($oldRestrictionList)) {
                                $oldRestrictionList = $reccord['title'];
                            } else {
                                $oldRestrictionList .= ";".$reccord['title'];
                            }
                        }
                        // delete previous values
                        $db->queryDelete(
                            'restriction_to_roles',
                            array(
                                'item_id' => $dataReceived['id']
                               )
                        );
                        // add roles for item
                        foreach (array_filter(explode(';', $dataReceived['restricted_to_roles'])) as $role) {
                            $db->queryInsert(
                                'restriction_to_roles',
                                array(
                                    'role_id' => $role,
                                    'item_id' => $dataReceived['id']
                                   )
                            );
                            $dataTmp = $db->queryFirst("SELECT title FROM ".$pre."roles_title WHERE id= ".$role);
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
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_label : '.$data['label'].' => '.$label
                               )
                        );
                    }
                    /*LOGIN */
                    if ($data['login'] != $login) {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_login : '.$data['login'].' => '.$login
                               )
                        );
                    }
                    /*EMAIL */
                    if ($data['email'] != $dataReceived['email']) {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_email : '.$data['email'].' => '.$dataReceived['email']
                               )
                        );
                    }
                    /*URL */
                    if ($data['url'] != $url && $url != "http://") {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_url : '.$data['url'].' => '.$url
                               )
                        );
                    }
                    /*DESCRIPTION */
                    if ($data['description'] != $dataReceived['description']) {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_description'
                               )
                        );
                    }
                    /*FOLDER */
                    if ($data['id_tree'] != $dataReceived['categorie']) {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_category : '.$data['id_tree'].' => '.$dataReceived['categorie']
                               )
                        );
                        // ask for page reloading
                        $reloadPage = true;
                    }
                    /*PASSWORD */
                    if (isset($dataReceived['salt_key']) && !empty($dataReceived['salt_key'])) {
                        $oldPw = decrypt($data['pw'], $dataReceived['salt_key']);
                    } else {
                        $oldPw = decrypt($data['pw']);
                    }
                    if ($sentPw != $oldPw) {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_pw : '.$data['pw']
                               )
                        );
                    }
                    /*RESTRICTIONS */
                    if ($data['restricted_to'] != $dataReceived['restricted_to']) {
                        $db->queryInsert(
                            'log_items',
                            array(
                                'id_item' => $dataReceived['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_modification',
                                'raison' => 'at_restriction : '.$oldRestrictionList.' => '.$listOfRestricted
                               )
                        );
                    }
                    // Reload new values
                    $dataItem = $db->queryFirst(
                        "SELECT *
                        FROM ".$pre."items as i
                        INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                        WHERE i.id=".$dataReceived['id']."
                            AND l.action = 'at_creation'"
                    );
                    // Reload History
                    $history = "";
                    $rows = $db->fetchAllArray(
                        "SELECT l.date as date, l.action as action, l.raison as raison, u.login as login
                        FROM ".$pre."log_items as l
                        LEFT JOIN ".$pre."users as u ON (l.id_user=u.id)
                        WHERE l.action <> 'at_shown' AND id_item=".$dataReceived['id']
                    );
                    foreach ($rows as $reccord) {
                        $reason = explode(':', $reccord['raison']);
                        if (empty($history)) {
                            $history = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ".$reccord['login']." - ".$txt[$reccord['action']] .
                            " - ".(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' : '.$reason[1] : $txt[trim($reason[0])]):'');
                        } else {
                            $history .= "<br />".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - " .
                            $reccord['login']." - ".$txt[$reccord['action']]." - " .
                            (!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' => '.$reason[1] : ($reccord['action'] != "at_manual" ? $txt[trim($reason[0])] : trim($reason[0]))):'');
                        }
                    }
                    // decrypt PW
                    if (empty($dataReceived['salt_key'])) {
                        $pw = decrypt($dataItem['pw']);
                    } else {
                        $pw = decrypt($dataItem['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                    }
                    $pw = cleanString($pw);
                    // generate 2d key
                    $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
                    $pwgen->register();
                    $pwgen = new Encryption\PwGen\pwgen();
                    $pwgen->setLength(20);
                    $pwgen->setSecure(true);
                    $pwgen->setSymbols(false);
                    $pwgen->setCapitalize(true);
                    $pwgen->setNumerals(true);
                    $_SESSION['key_tmp'] = $pwgen->generate();
                    // Prepare files listing
                    $files = $filesEdit = "";
                    // launch query
                    $rows = $db->fetchAllArray(
                        "SELECT *
                            FROM ".$pre."files
                            WHERE id_item=".$dataReceived['id']
                    );
                    foreach ($rows as $reccord) {
                        // get icon image depending on file format
                        $iconImage = fileFormatImage($reccord['extension']);
                        // If file is an image, then prepare lightbox. If not image, then prepare donwload
                        if (in_array($reccord['extension'], $k['image_file_ext'])) {
                            $files .= '<img src="'.$_SESSION['settings']['cpassman_url'].'/includes/images/'.$iconImage.'" /><a class="image_dialog" href="'.$_SESSION['settings']['url_to_upload_folder'].'/'.$reccord['file'].'" title="'.$reccord['name'].'">'.$reccord['name'].'</a><br />';
                        } else {
                            $files .= '<img src="'.$_SESSION['settings']['cpassman_url'].'/includes/images/'.$iconImage.'" /><a href=\'sources/downloadFile.php?name='.urlencode($reccord['name']).'&type=sub&file='.$reccord['file'].'&size='.$reccord['size'].'&type='.urlencode($reccord['type']).'&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'\' target=\'_blank\'>'.$reccord['name'].'</a><br />';
                        }
                        // Prepare list of files for edit dialogbox
                        $filesEdit .= '<span id="span_edit_file_'.$reccord['id'].'"><img src="'.$_SESSION['settings']['cpassman_url'].'/includes/images/'.$iconImage.'" /><img src="includes/images/document--minus.png" style="cursor:pointer;"  onclick="delete_attached_file(\"'.$reccord['id'].'\")" />&nbsp;'.$reccord['name']."</span><br />";
                    }
                    // Send email
                    if (!empty($dataReceived['diffusion'])) {
                        foreach (explode(';', $dataReceived['diffusion']) as $emailAddress) {
                            @sendEmail(
                                $txt['email_subject_item_updated'],
                                str_replace(array("#item_label#", "#item_category#", "#item_id#"), array($label, $dataReceived['categorie'], $dataReceived['id']), $txt['email_body_item_updated']),
                                $emailAddress,
                                str_replace("#item_label#", $label, $txt['email_bodyalt_item_updated'])
                            );
                        }
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
                        "error" => ""
                       );
                } else {
                    echo prepareExchangedData(array("error" => "something_wrong"), "encode");
                    break;
                }
            } else {
                // an error appears on JSON format
                $arrData = array("error" => "format");
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
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                $returnValues = '[{"error" : "not_allowed"}, {"error_text" : "'.addslashes($txt['error_not_allowed_to']).'"}]';
                echo $returnValues;
                break;
            }
            $returnValues = $pw = "";

            if (isset($_POST['item_id']) && !empty($_POST['item_id']) && !empty($_POST['folder_id'])) {
                // load the original record into an array
                $originalRecord = $db->queryFirst(
                    "SELECT *
                    FROM ".$pre."items
                    WHERE id=".$_POST['item_id']
                );
                // insert the new record and get the new auto_increment id
                $newID = $db->queryInsert(
                    'items',
                    array(
                        'label' => "duplicate"
                       )
                );
                // Check if item is PERSONAL
                if ($originalRecord['perso'] != 1) {
                    // generate random key
                    $randomKey = generateKey();
                    // Store generated key
                    $db->queryInsert(
                        'keys',
                        array(
                            'table' => 'items',
                            'id' => $newID,
                            'rand_key' => $randomKey
                           )
                    );
                    // get key for original pw
                    $originalKey = $db->queryFirst('SELECT rand_key FROM `'.$pre.'keys` WHERE `table` LIKE "items" AND `id` ='.$_POST['item_id']);
                    // unsalt previous pw
                    $pw = substr(decrypt($originalRecord['pw']), strlen($originalKey['rand_key']));
                }
                // generate the query to update the new record with the previous values
                $query = "UPDATE ".$pre."items SET ";
                foreach ($originalRecord as $key => $value) {
                    if ($key == "id_tree") {
                        $query .= '`id_tree` = "'.$_POST['folder_id'].'", ';
                    } elseif ($key == "pw" && !empty($pw)) {
                        $query .= '`pw` = "'.encrypt($randomKey.$pw).'", ';
                    } elseif ($key != "id" && $key != "key") {
                        $query .= '`'.$key.'` = "'.str_replace('"', '\"', $value).'", ';
                    }
                }
                $query = substr($query, 0, strlen($query) - 2); # lop off the extra trailing comma
                $query .= " WHERE id=".$newID;
                $db->query($query);
                // Add attached itms
                $rows = $db->fetchAllArray(
                    "SELECT *
                        FROM ".$pre."files
                        WHERE id_item=".$newID
                );
                foreach ($rows as $reccord) {
                    $db->queryInsert(
                        'files',
                        array(
                            'id_item' => $newID,
                            'name' => $reccord['name'],
                            'size' => $reccord['size'],
                            'extension' => $reccord['extension'],
                            'type' => $reccord['type'],
                            'file' => $reccord['file']
                           )
                    );
                }
                // Add this duplicate in logs
                $db->queryInsert(
                    'log_items',
                    array(
                        'id_item' => $newID,
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_creation'
                       )
                );
                // Add the fact that item has been copied in logs
                $db->queryInsert(
                    'log_items',
                    array(
                        'id_item' => $_POST['item_id'],
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_copy'
                       )
                );
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
            $arrData = array();
            // return ID
            $arrData['id'] = $_POST['id'];
            // Check if item is deleted
            //$dataDeleted = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item = '".$_POST['id']."' AND action = 'at_delete'");
            //$dataRestored = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item = '".$_POST['id']."' AND action = 'at_restored'");
            $dataDeleted = $db->queryCount(
                "log_items",
                array(
                    "id_item" => $_POST['id'],
                     "action" => "at_delete"
                )
            );
            $dataRestored = $db->queryCount(
                "log_items",
                array(
                    "id_item" => $_POST['id'],
                     "action" => "at_restored"
                )
            );
            if ($dataDeleted[0] != 0 && $dataDeleted[0] > $dataRestored[0]) {
                // This item is deleted => exit
                echo prepareExchangedData(array('show_detail_option' => 2), "encode");
                break;
            }
            // Get all informations for this item
            $dataItem = $db->queryFirst(
                "SELECT *
                FROM ".$pre."items as i
                INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                WHERE i.id=".$_POST['id']."
                AND l.action = 'at_creation'"
            );
            // LEFT JOIN ".$pre."categories_items as c ON (c.id_item = i.id)
            // INNER JOIN ".$pre."automatic_del as d ON (d.item_id = i.id)
            // Get all USERS infos
            $listNotif = array_filter(explode(";", $dataItem['notification']));
            $listRest = array_filter(explode(";", $dataItem['restricted_to']));
            $listeRestriction = $listNotification = $listNotificationEmails = "";
            $rows = $db->fetchAllArray(
                "SELECT id, login, email
                FROM ".$pre."users"
            );
            foreach ($rows as $reccord) {
                // Get auhtor
                if ($reccord['id'] == $dataItem['id_user']) {
                    $arrData['author'] = $reccord['login'];
                    $arrData['author_email'] = $reccord['email'];
                    $arrData['id_user'] = $dataItem['id_user'];
                    if (in_array($reccord['id'], $listNotif)) {
                        $arrData['notification_status'] = true;
                    } else {
                        $arrData['notification_status'] = false;
                    }
                }
                // Get restriction list for users
                if (in_array($reccord['id'], $listRest)) {
                    $listeRestriction .= $reccord['login'].";";
                }
                // Get notification list for users
                if (in_array($reccord['id'], $listNotif)) {
                    $listNotification .= $reccord['login'].";";
                    $listNotificationEmails .= $reccord['email'].",";
                }
            }

            /*
            //Get auhtor
            $dataTmp = $db->queryFirst("SELECT login, email FROM ".$pre."users WHERE id= ".$dataItem['id_user']);
            $arrData['author'] = $dataTmp['login'];
            $arrData['author_email'] = $dataTmp['email'];
            $arrData['id_user'] = $dataItem['id_user'];
            */
            // Get all tags for this item
            $tags = "";
            $sql = "SELECT tag
                    FROM ".$pre."tags
                    WHERE item_id=".$_POST['id'];
            $rows = $db->fetchAllArray($sql);
            foreach ($rows as $reccord) {
                $tags .= $reccord['tag']." ";
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
            $rows_tmp = $db->fetchAllArray(
                "SELECT role_id
                FROM ".$pre."restriction_to_roles
                WHERE item_id=".$_POST['id']
            );
            $myTest = 0;
            if (in_array($_SESSION['user_id'], $rows_tmp)) {
                $myTest = 1;
            }
            // Uncrypt PW
            if (isset($_POST['salt_key_required']) && $_POST['salt_key_required'] == 1 && isset($_POST['salt_key_set']) && $_POST['salt_key_set'] == 1) {
                $pw = decrypt($dataItem['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                if (empty($pw)) {
                    $pw = decryptOld($dataItem['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                }
                $arrData['edit_item_salt_key'] = 1;
            } else {
                $pw = decrypt($dataItem['pw']);
                if (empty($pw)) {
                    $pw = decryptOld($dataItem['pw']);
                }
                $arrData['edit_item_salt_key'] = 0;
            }
            // extract real pw from salt
            if ($dataItem['perso'] != 1) {
                $dataItemKey = $db->queryFirst('SELECT rand_key FROM `'.$pre.'keys` WHERE `table`="items" AND `id`='.$_POST['id']);
                $pw = substr($pw, strlen($dataItemKey['rand_key']));
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
                (@in_array($_POST['id'], $_SESSION['list_folders_limited'][$_POST['folder_id']]))
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
                    $rows = $db->fetchAllArray(
                        "SELECT t.title
                        FROM ".$pre."roles_title as t
                        INNER JOIN ".$pre."restriction_to_roles as r ON (t.id=r.role_id)
                        WHERE r.item_id = ".$_POST['id']."
                        ORDER BY t.title ASC"
                    );
                    foreach ($rows as $reccord) {
                        if (!in_array($reccord['title'], $listRestrictionRoles)) {
                            array_push($listRestrictionRoles, $reccord['title']);
                        }
                    }
                }
                // Check if any KB is linked to this item
                if (isset($_SESSION['settings']['enable_kb']) && $_SESSION['settings']['enable_kb'] == 1) {
                    $tmp = "";
                    $rows = $db->fetchAllArray(
                        "SELECT k.label, k.id
                        FROM ".$pre."kb_items as i
                        INNER JOIN ".$pre."kb as k ON (i.kb_id=k.id)
                        WHERE i.item_id = ".$_POST['id']."
                        ORDER BY k.label ASC"
                    );
                    foreach ($rows as $reccord) {
                        if (empty($tmp)) {
                            $tmp = "<a href='".$_SESSION['settings']['cpassman_url']."/index.php?page=kb&id=".$reccord['id']."'>".$reccord['label']."</a>";
                        } else {
                            $tmp .= "&nbsp;-&nbsp;<a href='".$_SESSION['settings']['cpassman_url']."/index.php?page=kb&id=".$reccord['id']."'>".$reccord['label']."</a>";
                        }
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

                $arrData['label'] = $dataItem['label'];
                $arrData['pw'] = $pw;
                $arrData['email'] = $dataItem['email'];
                $arrData['url'] = $dataItem['url'];
                if (!empty($dataItem['url'])) {
                    $arrData['link'] = "&nbsp;<a href='".$dataItem['url']."' target='_blank'><img src='includes/images/arrow_skip.png' style='border:0px;' title='".$txt['open_url_link']."'></a>";
                }

                $arrData['description'] = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags($dataItem['description'], $allowedTags));
                $arrData['login'] = str_replace('"', '&quot;', $dataItem['login']);
                $arrData['id_restricted_to'] = $listeRestriction;
                $arrData['id_restricted_to_roles'] = count($listRestrictionRoles) > 0 ? implode(";", $listRestrictionRoles).";" : "";
                $arrData['tags'] = str_replace('"', '&quot;', $tags);
                $arrData['folder'] = $dataItem['id_tree'];
                if (isset($_SESSION['settings']['anyone_can_modify_bydefault'])
                    && $_SESSION['settings']['anyone_can_modify_bydefault'] == 1) {
                    $arrData['anyone_can_modify'] = 1;
                } else {
                    $arrData['anyone_can_modify'] = $dataItem['anyone_can_modify'];
                }

                // get fields
                $fieldsTmp = $arrCatList = "";
                if (
                	isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1
                	&& isset($_POST['page']) && $_POST['page'] == "items"
                ) {
                    // get list of associated Categories
                	$arrCatList = array();
                    $rows_tmp = $db->fetchAllArray(
                        "SELECT id_category
                        FROM ".$pre."categories_folders
                        WHERE id_folder=".$_POST['folder_id']
                    );
                    foreach ($rows_tmp as $row) {
                        array_push($arrCatList, $row['id_category']);
                    }
                    $arrCatList = implode(",", $arrCatList);

                    // get fields for this Item
                    $rows_tmp = $db->fetchAllArray(
                        "SELECT i.field_id AS field_id, i.data AS data
                        FROM ".$pre."categories_items AS i
                        INNER JOIN ".$pre."categories AS c ON (i.field_id=c.id)
                        WHERE i.item_id=".$_POST['id']."
                        AND c.parent_id IN (".$arrCatList.")"
                    );
                    foreach ($rows_tmp as $row) {
                        $fieldText = decrypt($row['data']);
                        // extract real pw from salt
                        $dataItemKey = $db->queryFirst('SELECT rand_key FROM `'.$pre.'keys` WHERE `table`="categories_items" AND `id`='.$_POST['id']);
                        $fieldText = substr($fieldText, strlen($dataItemKey['rand_key']));
                        // build returned list of Fields text
                        if (empty($fieldsTmp)) {
                            $fieldsTmp = $row['field_id']."~~".str_replace('"', '&quot;', $fieldText);
                        } else {
                            $fieldsTmp .= "_|_".$row['field_id']."~~".str_replace('"', '&quot;', $fieldText);
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
                $sql = "SELECT * FROM ".$pre."automatic_del WHERE item_id=".$_POST['id'];
                $dataDelete = $db->queryFirst($sql);
                $arrData['to_be_deleted'] = $dataDelete['del_value'];
                $arrData['to_be_deleted_type'] = $dataDelete['del_type'];
                // $date = date_parse_from_format($_SESSION['settings']['date_format'], $dataDelete['del_value']);
                // echo $_SESSION['settings']['date_format']." ; ".$dataDelete['del_value'] ." ; ".mktime(0, 0, 0, $date['month'], $date['day'], $date['year'])." ; ".time()." ; ";
                if (isset($_SESSION['settings']['enable_delete_after_consultation']) && $_SESSION['settings']['enable_delete_after_consultation'] == 1) {
                    if ($dataDelete['del_enabled'] == 1 || $arrData['id_user'] != $_SESSION['user_id']) {
                        if ($dataDelete['del_type'] == 1 && $dataDelete['del_value'] > 1) {
                            // decrease counter
                            $db->queryUpdate(
                                "automatic_del",
                                array(
                                    'del_value' => $dataDelete['del_value'] - 1
                                   ),
                                "item_id = ".$_POST['id']
                            );
                            // store value
                            $arrData['to_be_deleted'] = $dataDelete['del_value'] - 1;
                        } elseif ($dataDelete['del_type'] == 1 && $dataDelete['del_value'] <= 1 || $dataDelete['del_type'] == 2 && $dataDelete['del_value'] < time()
                               )
                        {
                            $arrData['show_details'] = 0;
                            // delete item
                            $db->query("DELETE FROM ".$pre."automatic_del WHERE item_id = '".$_POST['id']."'");
                            // make inactive object
                            $db->queryUpdate(
                                "items",
                                array(
                                    'inactif' => '1',
                                   ),
                                "id = ".$_POST['id']
                            );
                            // log
                            $db->queryInsert(
                                "log_items",
                                array(
                                    'id_item' => $_POST['id'],
                                    'date' => time(),
                                    'id_user' => $_SESSION['user_id'],
                                    'action' => 'at_delete',
                                    'raison' => 'at_automatically_deleted'
                                   )
                            );
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
                    $arrData['notification_list'] = $listNotification;
                    // Send email if activated
                    if (!empty($listNotificationEmails) && !in_array($_SESSION['login'], explode(';', $listNotification))) {
                        $db->queryInsert(
                            'emails',
                            array(
                                'timestamp' => time(),
                                'subject' => $txt['email_on_open_notification_subject'],
                                'body' => str_replace(array('#tp_item_author#', '#tp_user#', '#tp_item#'), array(" ".addslashes($arrData['author']), addslashes($_SESSION['login']), addslashes($dataItem['label'])), $txt['email_on_open_notification_mail']),
                                'receivers' => $listNotificationEmails,
                                'status' => ''
                               )
                        );
                    }
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
                            $dataTmp = $db->queryFirst("SELECT login FROM ".$pre."users WHERE id= ".$userRest);
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
            // print_r($arrData);
            // Encrypt data to return
            echo prepareExchangedData($arrData, "encode");
            break;

        	/*
        	   * CASE
        	   * Display History of the selected Item
        	*/
        case "showDetailsStep2":
        	// get Item info
        	$dataItem = $db->queryFirst(
        	    "SELECT *
                FROM ".$pre."items
                WHERE id=".$_POST['id']
        	);

        	// get Item Key for decryption
        	$dataItemKey = $db->queryFirst('SELECT rand_key FROM `'.$pre.'keys` WHERE `table`="items" AND `id`='.$_POST['id']);

        	// GET Audit trail
        	$history = "";
        	$historyOfPws = "";
        	$rows = $db->fetchAllArray(
        	"SELECT l.date as date, l.action as action, l.raison as raison, u.login as login
                FROM ".$pre."log_items as l
                LEFT JOIN ".$pre."users as u ON (l.id_user=u.id)
                WHERE id_item=".$_POST['id']."
                AND action <> 'at_shown'
                ORDER BY date ASC"
        	);
        	foreach ($rows as $reccord) {
        		$reason = explode(':', $reccord['raison']);
        		if ($reccord['action'] == "at_modification" && $reason[0] == "at_pw ") {
        			// don't do if item is PF
        			if ($dataItem['perso'] != 1) {
        				$reason[1] = substr(decrypt($reason[1]), strlen($dataItemKey['rand_key']));
        			}
        			// if not UTF8 then cleanup and inform that something is wrong with encrytion/decryption
        			if (!isUTF8($reason[1])) {
        				$reason[1] = "";
        			}
        		}
        		if (!empty($reason[1]) || $reccord['action'] == "at_copy" || $reccord['action'] == "at_creation" || $reccord['action'] == "at_manual") {
        			if (empty($history)) {
        				$history = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ".$reccord['login']." - ".$txt[$reccord['action']]." - ".(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' : '.$reason[1] : ($reccord['action'] == "at_manual" ? $reason[0] : $txt[trim($reason[0])])):'');
        			} else {
        				$history .= "<br />".date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $reccord['date'])." - ".$reccord['login']." - ".$txt[$reccord['action']]." - ".(!empty($reccord['raison']) ? (count($reason) > 1 ? $txt[trim($reason[0])].' => '.$reason[1] : ($reccord['action'] == "at_manual" ? $reason[0] : $txt[trim($reason[0])])):'');
        			}
        			if (trim($reason[0]) == "at_pw") {
        				if (empty($historyOfPws)) {
        					$historyOfPws = $txt['previous_pw']." | ".$reason[1];
        				} else {
        					$historyOfPws .= $reason[1];
        				}
        			}
        		}
        	}

        	// generate 2d key
        	$pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
        	$pwgen->register();
        	$pwgen = new Encryption\PwGen\pwgen();
        	$pwgen->setLength(20);
        	$pwgen->setSecure(true);
        	$pwgen->setSymbols(false);
        	$pwgen->setCapitalize(true);
        	$pwgen->setNumerals(true);
        	$_SESSION['key_tmp'] = $pwgen->generate();
        	// Prepare files listing
        	$files = $filesEdit = "";
        	// launch query
        	$rows = $db->fetchAllArray(
        	"SELECT *
                        FROM ".$pre."files
                        WHERE id_item=".$_POST['id']
        	);
        	foreach ($rows as $reccord) {
        		// get icon image depending on file format
        		$iconImage = fileFormatImage($reccord['extension']);
        		// If file is an image, then prepare lightbox. If not image, then prepare donwload
        		if (in_array($reccord['extension'], $k['image_file_ext'])) {
        			$files .= '<img src=\'includes/images/'.$iconImage.'\' /><a class=\'image_dialog\' href=\''.$_SESSION['settings']['url_to_upload_folder'].'/'.$reccord['file'].'\' title=\''.$reccord['name'].'\'>'.$reccord['name'].'</a><br />';
        		} else {
        			$files .= '<img src=\'includes/images/'.$iconImage.'\' /><a href=\'sources/downloadFile.php?name='.urlencode($reccord['name']).'&type=sub&file='.$reccord['file'].'&size='.$reccord['size'].'&type='.urlencode($reccord['type']).'&key='.$_SESSION['key'].'&key_tmp='.$_SESSION['key_tmp'].'\'>'.$reccord['name'].'</a><br />';
        		}
        		// Prepare list of files for edit dialogbox
        		$filesEdit .= '<span id=\'span_edit_file_'.$reccord['id'].'\'><img src=\'includes/images/'.$iconImage.'\' /><img src=\'includes/images/document--minus.png\' style=\'cursor:pointer;\'  onclick=\'delete_attached_file("'.$reccord['id'].'")\' />&nbsp;'.$reccord['name']."</span><br />";
        	}
        	// display lists
        	$filesEdit = str_replace('"', '&quot;', $filesEdit);
        	$files_id = $files;
        	// Refresh last seen items
        	$text = $txt['last_items_title'].": ";
        	$_SESSION['latest_items_tab'] = "";
        	foreach ($_SESSION['latest_items'] as $item) {
        		if (!empty($item)) {
        			$data = $db->queryFirst("SELECT id,label,id_tree FROM ".$pre."items WHERE id = ".$item);
        			$_SESSION['latest_items_tab'][$item] = array(
        			    'id' => $item,
        			    'label' => addslashes($data['label']),
        			    'url' => 'index.php?page=items&group='.$data['id_tree'].'&id='.$item
        			);
        			$text .= '<span class="last_seen_item" onclick="javascript:window.location.href = \''.$_SESSION['latest_items_tab'][$item]['url'].'\'"><img src="includes/images/tag-small.png" /><span id="last_items_'.$_SESSION['latest_items_tab'][$item]['id'].'">'.stripslashes($_SESSION['latest_items_tab'][$item]['label']).'</span></span>';
        		}
        	}
        	$div_last_items = str_replace('"', '&quot;', $text);
        	// disable add bookmark if alread bookmarked
        	if (in_array($_POST['id'], $_SESSION['favourites'])) {
        		$favourite = 1;
        	} else {
        		$favourite = 0;
        	}

        	// Add the fact that item has been viewed in logs
        	if (isset($_SESSION['settings']['log_accessed']) && $_SESSION['settings']['log_accessed'] == 1) {
        		$db->queryInsert(
        		'log_items',
        		array(
        		    'id_item' => $_POST['id'],
        		    'date' => time(),
        		    'id_user' => $_SESSION['user_id'],
        		    'action' => 'at_shown'
        		   )
        		);
        	}

        	// Add this item to the latests list
        	if (isset($_SESSION['latest_items']) && isset($_SESSION['settings']['max_latest_items']) && !in_array($dataItem['id'], $_SESSION['latest_items'])) {
        		if (count($_SESSION['latest_items']) >= $_SESSION['settings']['max_latest_items']) {
        			array_pop($_SESSION['latest_items']); //delete last items
        		}
        		array_unshift($_SESSION['latest_items'], $dataItem['id']);
        		// update DB
        		$db->queryUpdate(
            		"users",
            		array(
            		    'latest_items' => implode(';', $_SESSION['latest_items'])
            		   ),
            		"id=".$_SESSION['user_id']
        		);
        	}

        	echo prepareExchangedData(
	        	array(
	        		"history" => str_replace('"', '&quot;', $history),
	        		"history_of_pwds" => str_replace('"', '&quot;', $historyOfPws),
		        	"favourite" => $favourite,
		        	"div_last_items" => $div_last_items,
		        	"files_edit" => $filesEdit,
		        	"files_id" => $files_id
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
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                // error
                break;
            }
            // delete item consists in disabling it
            $db->queryUpdate(
                "items",
                array(
                    'inactif' => '1',
                   ),
                "id = ".$_POST['id']
            );
            // log
            $db->queryInsert(
                "log_items",
                array(
                    'id_item' => $_POST['id'],
                    'date' => time(),
                    'id_user' => $_SESSION['user_id'],
                    'action' => 'at_delete'
                   )
            );
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
                // error
                break;
            }
            // decrypt and retreive data in JSON format
        	$dataReceived = prepareExchangedData($_POST['data'], "decode");
            // Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            // Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                echo '[ { "error" : "'.addslashes($txt['error_html_codes']).'" } ]';
                break;
            }
            // Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                // $data = $db->fetchRow("SELECT id, title FROM ".$pre."nested_tree WHERE title = '".addslashes($title)."'");
                $data = $db->queryGetRow(
                    "nested_tree",
                    array(
                        "title",
                        "id"
                    ),
                    array(
                        "title" => addslashes($title)
                    )
                );
                if (!empty($data[0]) && $dataReceived['folder'] != $data[0]) {
                    echo '[ { "error" : "'.addslashes($txt['error_group_exist']).'" } ]';
                    break;
                }
            }
            // update Folders table
            /*$tmp = $db->fetchRow(
                "SELECT title, parent_id, personal_folder FROM ".$pre."nested_tree WHERE id = ".$dataReceived['folder']
            );*/
            $tmp = $db->queryGetRow(
                "nested_tree",
                array(
                    "title",
                    "parent_id",
                    "personal_folder"
                ),
                array(
                    "id" => intval($dataReceived['folder'])
                )
            );
            if ( $tmp[1] != 0 || $tmp[0] != $_SESSION['user_id'] || $tmp[2] != 1 ) {
                $db->queryUpdate(
                    "nested_tree",
                    array(
                        'title' => $title
                       ),
                    'id='.$dataReceived['folder']
                );
                // update complixity value
                $db->queryUpdate(
                    "misc",
                    array(
                        'valeur' => $dataReceived['complexity']
                       ),
                    'intitule = "'.$dataReceived['folder'].'" AND type = "complex"'
                );
                // rebuild fuild tree folder
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


            $db->queryUpdate(
                "nested_tree",
                array(
                    'parent_id' => $_POST['destination']
                   ),
                'id = '.$_POST['source']
            );
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();
            break;

        /*
        * CASE
        * List items of a group
        */
        case 'lister_items_groupe':
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
                $arboHtml_tmp = '<a id="path_elem_'.$elem->id.'"';
                if (in_array($elem->id, $_SESSION['groupes_visibles'])) {
                    $arboHtml_tmp .= ' style="cursor:pointer;" onclick="ListerItems('.$elem->id.', \'\', 0)"';
                }
            	if (strlen($elem->title) > 20) {
            		$arboHtml_tmp .= '>'. substr(htmlspecialchars(stripslashes($elem->title), ENT_QUOTES), 0, 17)."...". '</a>';
            	} else {
            		$arboHtml_tmp .= '>'. htmlspecialchars(stripslashes($elem->title), ENT_QUOTES). '</a>';
            	}
                if (empty($arboHtml)) {
                    $arboHtml = $arboHtml_tmp;
                } else {
                    $arboHtml .= ' » '.$arboHtml_tmp;
                }
            }
            // adapt the length of arbo versus the width #511
            // TODO

            // Check if ID folder send is valid
            if (
                    !in_array($_POST['id'], $_SESSION['groupes_visibles'])
                    && !in_array($_POST['id'], array_keys($_SESSION['list_folders_limited']))
            ) {
                $_POST['id'] = 1;
            }
            // check if this folder is a PF. If yes check if saltket is set
            if ((!isset($_SESSION['my_sk']) || empty($_SESSION['my_sk'])) && $folderIsPf == 1) {
                $showError = "is_pf_but_no_saltkey";
            }
            // check if items exist
            if (isset($_POST['restricted']) && $_POST['restricted'] == 1) {
                $data_count[0] = count($_SESSION['list_folders_limited'][$_POST['id']]);
                $whereArg = " AND i.id IN (".implode(',', $_SESSION['list_folders_limited'][$_POST['id']]).")";
            }
            // check if this folder is visible
            elseif (!in_array(
                $_POST['id'],
                array_merge($_SESSION['groupes_visibles'], @array_keys($_SESSION['list_restricted_folders_for_items']))
            )) {
            	echo prepareExchangedData(array("error" => "not_authorized"), "encode");
                break;
            } else {
                //$data_count = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."items WHERE inactif = 0");
                $data_count = $db->queryCount(
                    "items",
                    array(
                        "inactif" => "0"
                    )
                );
                $whereArg = " AND i.id_tree=".intval($_POST['id']);
            }

            if ($data_count[0] > 0 && empty($showError)) {
                // init variables
                $init_personal_folder = false;
                $expired_item = false;
                $limited_to_items = "";

                /*if (in_array($_POST['id'],@array_keys($_SESSION['list_restricted_folders_for_items']))) {
                    foreach ($_SESSION['list_restricted_folders_for_items'][$_POST['id']] as $reccord) {
                        if (empty($limited_to_items)) $limited_to_items = $reccord;
                        else $limited_to_items .= ",".$reccord;
                    }
                }*/
                // List all ITEMS
                if ($folderIsPf == 0) {
                    $query = "SELECT DISTINCT i.id as id, i.restricted_to as restricted_to, i.perso as perso,
                        i.label as label, i.description as description, i.pw as pw, i.login as login,
                        i.anyone_can_modify as anyone_can_modify, l.date as date,
                        n.renewal_period as renewal_period,
                        l.action as log_action, l.id_user as log_user,
                        k.rand_key as rand_key
                    FROM ".$pre."items as i
                    INNER JOIN ".$pre."nested_tree as n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items as l ON (i.id = l.id_item)
                    LEFT JOIN ".$pre."keys as k ON (k.id = i.id)
                    WHERE i.inactif = 0" .
                    $whereArg."
                    AND l.action = 'at_creation'";
                    if (!empty($limited_to_items)) {
                        $query .= "
                    AND i.id IN (".$limited_to_items.")";
                    }
                    $query .= "
                    ORDER BY i.label ASC, l.date DESC";
                    if ($_POST['nb_items_to_display_once'] != 'max') {
                        $query .= "
                    LIMIT ".$start.",".$_POST['nb_items_to_display_once'];
                    }
                    $rows = $db->fetchAllArray($query);
                } else {
                    $query = "SELECT DISTINCT i.id as id, i.restricted_to as restricted_to, i.perso as perso,
                        i.label as label, i.description as description, i.pw as pw, i.login as login,
                        i.anyone_can_modify as anyone_can_modify,l.date as date,
                        n.renewal_period as renewal_period,
                        l.action as log_action, l.id_user as log_user
                    FROM ".$pre."items as i
                    INNER JOIN ".$pre."nested_tree as n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items as l ON (i.id = l.id_item)
                    WHERE i.inactif = 0" .
                    $whereArg."
                    AND (l.action = 'at_creation')
                    ORDER BY i.label ASC, l.date DESC";
                    if ($_POST['nb_items_to_display_once'] != 'max') {
                        $query .= "
                        LIMIT ".$start.",".$_POST['nb_items_to_display_once'];
                    }

                    $rows = $db->fetchAllArray($query);
                }
                // REMOVED:  OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%')
                $idManaged = '';
                $i = 0;

                foreach ($rows as $reccord) {
                    // exclude all results except the first one returned by query
                    if (empty($idManaged) || $idManaged != $reccord['id']) {
                        // $returnedData[$i] = array('id' => $reccord['id']);
                        // Get Expiration date
                        $expirationFlag = '';
                        $expired_item = 0;
                        if ($_SESSION['settings']['activate_expiration'] == 1) {
                            $expirationFlag = '<img src="includes/images/flag-green.png">';
                            if (
                                $reccord['renewal_period'] > 0 &&
                                ($reccord['date'] + ($reccord['renewal_period'] * $k['one_month_seconds'])) < time()
                            ) {
                                $expirationFlag = '<img src="includes/images/flag-red.png">';
                                $expired_item = 1;
                            }
                        }
                        // list of restricted users
                        $restricted_users_array = explode(';', $reccord['restricted_to']);
                        $itemPw = $itemLogin = "";
                        $displayItem = $need_sk = $canMove = $item_is_restricted_to_role = 0;
                        // TODO: Element is restricted to a group. Check if element can be seen by user
                        // => récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
                        $user_is_included_in_role = 0;
                        $roles = $db->fetchAllArray(
                            "SELECT role_id FROM ".$pre."restriction_to_roles WHERE item_id=".$reccord['id']
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
                        if ($reccord['anyone_can_modify'] == 1 ||
                            $_SESSION['user_id'] == $reccord['log_user'] ||
                            ($_SESSION['user_read_only'] == 1 && $folderIsPf == 0)
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
                        ) {
                            $perso = '<img src="includes/images/tag-small-red.png">';
                            $findPfGroup = 0;
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', \'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\')';
                            $displayItem = $need_sk = $canMove = 0;
                        }
                        // Case where item is in own personal folder
                        elseif (
                            in_array($_POST['id'], $_SESSION['personal_visible_groups'])
                            && $reccord['perso'] == 1
                        ) {
                            $perso = '<img src="includes/images/tag-small-alert.png">';
                            $findPfGroup = 1;
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\', \'1\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'1\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                            $displayItem = $need_sk = $canMove = 1;
                        }
                        // CAse where item is restricted to a group of users included user
                        elseif (
                            !empty($reccord['restricted_to'])
                            && in_array($_SESSION['user_id'], $restricted_users_array)
                            || (isset($_SESSION['list_folders_editable_by_role'])
                                    && in_array($_POST['id'], $_SESSION['list_folders_editable_by_role']))
                            && in_array($_SESSION['user_id'], $restricted_users_array)
                        ) {
                            $perso = '<img src="includes/images/tag-small-yellow.png">';
                            $findPfGroup = 0;
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', \'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                            $displayItem = 1;
                        }
                        // CAse where item is restricted to a group of users not including user
                        elseif (
                            $reccord['perso'] == 1
                            ||
                            (
                                !empty($reccord['restricted_to'])
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
                                $perso = '<img src="includes/images/tag-small-red.png">';
                                $findPfGroup = 0;
                                $action = 'AfficherDetailsItem(\''.$reccord['id'].'\', \'0\', \''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\',\'\', \'\')';
                                $action_dbl = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'no_display\', true, \'\')';
                                $displayItem = $need_sk = $canMove = 0;
                            } else {
                                $perso = '<img src="includes/images/tag-small-yellow.png">';
                                $action = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\')';
                                $action_dbl = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                                // reinit in case of not personal group
                                if ($init_personal_folder == false) {
                                    $findPfGroup = "";
                                    $init_personal_folder = true;
                                }

                                if (!empty($reccord['restricted_to']) && in_array($_SESSION['user_id'], $restricted_users_array)) {
                                    $displayItem = 1;
                                }
                            }
                        } else {
                            $perso = '<img src="includes/images/tag-small-green.png">';
                            $action = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\',\'\',\'\', \'\')';
                            $action_dbl = 'AfficherDetailsItem(\''.$reccord['id'].'\',\'0\',\''.$expired_item.'\', \''.$restrictedTo.'\', \'\', true, \'\')';
                            $displayItem = 1;
                            // reinit in case of not personal group
                            if ($init_personal_folder == false) {
                                $findPfGroup = "";
                                $init_personal_folder = true;
                            }
                        }
                        // Prepare full line
                        $html .= '<li name="'.strip_tags(stripslashes(cleanString($reccord['label']))).'" ondblclick="'.$action_dbl.'" class="';
                        if ($canMove == 1) {
                            $html .= 'item_draggable';
                        } else {
                            $html .= 'item';
                        }

                        $html .= '" id="'.$reccord['id'].'" style="margin-left:-30px;">';

                        if ($canMove == 1) {
                            $html .= '<img src="includes/images/grippy.png" style="margin-right:5px;cursor:hand;" alt="" class="grippy"  />';
                        } else {
                            $html .= '<span style="margin-left:11px;"></span>';
                        }
                        $html .= $expirationFlag.''.$perso.'&nbsp;<a id="fileclass'.$reccord['id'].'" class="file" onclick="'.$action.';">'.substr(stripslashes($reccord['label']), 0, 65);
                        if (!empty($reccord['description']) && isset($_SESSION['settings']['show_description']) && $_SESSION['settings']['show_description'] == 1) {
                        	$tempo = explode("<br />", $reccord['description']);
                        	if (count($tempo) == 1) {
                        		$html .= '&nbsp;<font size="2px">['.strip_tags(stripslashes(substr(cleanString($reccord['description']), 0, 30))).']</font>';
                        	} else {
                        		$html .= '&nbsp;<font size="2px">['.strip_tags(stripslashes(substr(cleanString($tempo[0]), 0, 30))).']</font>';
                        	}
                        }
                        $html .= '</a>';
                        // increment array for icons shortcuts (don't do if option is not enabled)
                        if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                            if ($need_sk == true && isset($_SESSION['my_sk'])) {
                                $pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                            } else {
                                $pw = substr(decrypt($reccord['pw']), strlen($reccord['rand_key']));
                            }
                        } else {
                            $pw = "";
                        }
                        // test charset => may cause a json error if is not utf8
                        if (!isUTF8($pw)) {
                            $pw = "";
                            $html .= '&nbsp;<img src="includes/images/exclamation_small_red.png" title="'.$txt['pw_encryption_error'].'" />';
                        }

                        $html .= '<span style="float:right;margin:2px 10px 0px 0px;">';
                        // display quick icon shortcuts ?
                        if (isset($_SESSION['settings']['copy_to_clipboard_small_icons']) && $_SESSION['settings']['copy_to_clipboard_small_icons'] == 1) {
                            $itemLogin = '<img src="includes/images/mini_user_disable.png" id="icon_login_'.$reccord['id'].'" />';
                            $itemPw = '<img src="includes/images/mini_lock_disable.png" id="icon_pw_'.$reccord['id'].'" class="copy_clipboard tip" />';
                            if ($displayItem == true) {
                                if (!empty($reccord['login'])) {
                                    $itemLogin = '<img src="includes/images/mini_user_enable.png" id="iconlogin_'.$reccord['id'].'" class="copy_clipboard tip" onclick="get_clipboard_item(\'login\','.$reccord['id'].')" title="'.$txt['item_menu_copy_login'].'" />';
                                }
                                if (!empty($pw)) {
                                    $itemPw = '<img src="includes/images/mini_lock_enable.png" id="iconpw_'.$reccord['id'].'" class="copy_clipboard tip" onclick="get_clipboard_item(\'pw\','.$reccord['id'].')" title="'.$txt['item_menu_copy_pw'].'" />';
                                }
                            }
                            $html .= $itemLogin.'&nbsp;'.$itemPw .
                            '<input type="hidden" id="item_pw_in_list_'.$reccord['id'].'" value="'.$pw.'"><input type="hidden" id="item_login_in_list_'.$reccord['id'].'" value="'.$reccord['login'].'">';
                        }
                        // Prepare make Favorite small icon
                        $html .= '&nbsp;<span id="quick_icon_fav_'.$reccord['id'].'" title="Manage Favorite" class="cursor tip">';
                        if (in_array($reccord['id'], $_SESSION['favourites'])) {
                            $html .= '<img src="includes/images/mini_star_enable.png" onclick="ActionOnQuickIcon('.$reccord['id'].',0)" class="tip" />';
                        } else {
                            $html .= '<img src="includes/images/mini_star_disable.png"" onclick="ActionOnQuickIcon('.$reccord['id'].',1)" class="tip" />';
                        }
                        // mini icon for collab
                        if (isset($_SESSION['settings']['anyone_can_modify']) && $_SESSION['settings']['anyone_can_modify'] == 1) {
                            if ($reccord['anyone_can_modify'] == 1) {
                                $itemCollab = '&nbsp;<img src="includes/images/mini_collab_enable.png" title="'.$txt['item_menu_collab_enable'].'" class="tip" />';
                            } else {
                                $itemCollab = '&nbsp;<img src="includes/images/mini_collab_disable.png" title="'.$txt['item_menu_collab_disable'].'" class="tip" />';
                            }
                            $html .= '</span>'.$itemCollab.'</span>';
                        } else {
                            $html .= '</span>';
                        }

                        $html .= '</span></li>';
                        // Build array with items
                        array_push($itemsIDList, array($reccord['id'], $pw, $reccord['login'], $displayItem));

                        $i ++;
                    }
                    $idManaged = $reccord['id'];
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
            $countItems = $db->fetchRow(
                "SELECT COUNT(*)
                FROM ".$pre."items as i
                INNER JOIN ".$pre."nested_tree as n ON (i.id_tree = n.id)
                INNER JOIN ".$pre."log_items as l ON (i.id = l.id_item)
                WHERE i.inactif = 0" .
                $whereArg."
                AND (l.action = 'at_creation')
                ORDER BY i.label ASC, l.date DESC"
            );
        	// DELETE - 2.1.19 - AND (l.action = 'at_creation' OR (l.action = 'at_modification' AND l.raison LIKE 'at_pw :%'))
            // Check list to be continued status
            if (($_POST['nb_items_to_display_once'] + $start) < $countItems[0] && $_POST['nb_items_to_display_once'] != "max") {
                $listToBeContinued = "yes";
            } else {
                $listToBeContinued = "end";
            }
            // Get folder complexity
            $folderComplexity = $db->fetchRow("SELECT valeur FROM ".$pre."misc WHERE type = 'complex' AND intitule = '".$_POST['id']."'");

            //  Fixing items not being displayed
            $html = iconv('UTF-8', 'UTF-8//IGNORE', mb_convert_encoding($html, "UTF-8", "UTF-8"));

            // Has this folder some categories to be displayed?
            $displayCategories = "";
            if (isset($_SESSION['settings']['item_extra_fields']) && $_SESSION['settings']['item_extra_fields'] == 1) {
                $catRow = $db->fetchAllArray(
                    "SELECT id_category FROM ".$pre."categories_folders WHERE id_folder = '".$_POST['id']."'"
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
                "items_count" => $countItems[0],
                'folder_complexity' => $folderComplexity[0],
                // "items" => $returnedData
                'displayCategories' => $displayCategories
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
        case "recup_complex":
            if (isset($_POST['item_id']) && !empty($_POST['item_id'])) {
                // Lock Item (if already locked), go back and warn
                // $dataTmp = $db->fetchRow("SELECT timestamp, user_id FROM ".$pre."items_edition WHERE item_id = '".$_POST['item_id']."'");//echo ">".$dataTmp[0];
                $dataTmp = $db->queryGetRow(
                    "items_edition",
                    array(
                        "timestamp",
                        "user_id"
                    ),
                    array(
                        "item_id" => intval($_POST['item_id'])
                    )
                );

                // If token is taken for this Item and delay is passed then delete it.
                if (isset($_SESSION['settings']['delay_item_edition']) &&
                    $_SESSION['settings']['delay_item_edition'] > 0 && !empty($dataTmp[0]) &&
                    round(abs(time()-$dataTmp[0]) / 60, 2) > $_SESSION['settings']['delay_item_edition']
                ) {
                    $db->query("DELETE FROM ".$pre."items_edition WHERE item_id = '".$_POST['item_id']."'");
                    //reload the previous data
                    // $dataTmp = $db->fetchRow("SELECT timestamp, user_id FROM ".$pre."items_edition WHERE item_id = '".$_POST['item_id']."'");
                    $dataTmp = $db->queryGetRow(
                        "items_edition",
                        array(
                            "timestamp",
                            "user_id"
                        ),
                        array(
                            "item_id" => intval($_POST['item_id'])
                        )
                    );
                }

                // If edition by same user (and token not freed before for any reason, then update timestamp)
                if (!empty($dataTmp[0]) && $dataTmp[1] == $_SESSION['user_id']) {
                    $db->query("UPDATE ".$pre."items_edition SET timestamp = '".time()."' WHERE user_id = '".$_SESSION['user_id']."' AND item_id = '".$_POST['item_id']."'");
                    // If no token for this Item, then initialize one
                } elseif (empty($dataTmp[0])) {
                    $db->queryInsert(
                        'items_edition',
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
                        "error_msg" => $txt['error_no_edition_possible_locked']
                    );
                    echo prepareExchangedData($returnValues, "encode");
                    break;
                }
            }

            // Get required Complexity for this Folder
            // $data = $db->fetchRow("SELECT valeur FROM ".$pre."misc WHERE type='complex' AND intitule = '".$_POST['groupe']."'");
            $data = $db->queryGetRow(
                "misc",
                array(
                    "valeur"
                ),
                array(
                    "intitule" => $_POST['groupe'],
                    "type" => "complex"
                )
            );

            if (isset($data[0]) && (!empty($data[0]) || $data[0] == 0)) {
                $complexity = $pwComplexity[$data[0]][1];
            } else {
                $complexity = $txt['not_defined'];
            }
            // Prepare Item actual visibility (what Users/Roles can see it)
            $visibilite = "";
            if (!empty($dataPf[0])) {
                $visibilite = $_SESSION['login'];
            } else {
                $rows = $db->fetchAllArray(
                    "SELECT t.title
                    FROM ".$pre."roles_values as v
                    INNER JOIN ".$pre."roles_title as t ON (v.role_id = t.id)
                    WHERE v.folder_id = '".$_POST['groupe']."'"
                );
                foreach ($rows as $reccord) {
                    if (empty($visibilite)) {
                        $visibilite = $reccord['title'];
                    } else {
                        $visibilite .= " - ".$reccord['title'];
                    }
                }
            }

            recupDroitCreationSansComplexite($_POST['groupe']);

            $returnValues = array(
                "val" => $data[0],
                "visibility" => $visibilite,
                "complexity" => $complexity
            );
            echo prepareExchangedData($returnValues, "encode");
            break;

        /*
          * CASE
          * WANT TO CLIPBOARD PW/LOGIN OF ITEM
        */
        case "get_clipboard_item":
            $sql = "SELECT pw,login,perso
                    FROM ".$pre."items
                    WHERE id=".$_POST['id'];
            $dataItem = $db->queryFirst($sql);

            if ($_POST['field'] == "pw") {
                if ($dataItem['perso'] == 1) {
                    $data = decrypt($dataItem['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                } else {
                    $pw = decrypt($dataItem['pw']);
                    $dataItemKey = $db->queryFirst('SELECT rand_key FROM `'.$pre.'keys` WHERE `table`="items" AND `id`='.$_POST['id']);
                    $data = substr($pw, strlen($dataItemKey['rand_key']));
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
            // $data = $db->fetchRow("SELECT name,id_item,file FROM ".$pre."files WHERE id = '".$_POST['file_id']."'");
            $data = $db->queryGetRow(
                "files",
                array(
                    "name",
                    "id_item",
                    "file"
                ),
                array(
                    "id" => intval($_POST['file_id'])
                )
            );
            if (!empty($data[1])) {
                // Delete from FILES table
                $db->query("DELETE FROM ".$pre."files WHERE id = '".$_POST['file_id']."'");
                // Update the log
                $db->queryInsert(
                    'log_items',
                    array(
                        'id_item' => $data[1],
                        'date' => time(),
                        'id_user' => $_SESSION['user_id'],
                        'action' => 'at_modification',
                        'raison' => 'at_del_file : '.$data[0]
                       )
                );
                // Delete file from server
                @unlink($_SESSION['settings']['path_to_upload_folder']."/".$data[2]);
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
            $returnValues['multi_select'] = '$("#edit_restricted_to_list").multiselect({selectedList: 7, minWidth: 430, height: 145, checkAllText: "'.$txt['check_all_text'].'", uncheckAllText: "'.$txt['uncheck_all_text'].'",noneSelectedText: "'.$txt['none_selected_text'].'"});';
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
            $sql = "SELECT description
                    FROM ".$pre."items
                    WHERE id=".$_POST['id_item'];
            $dataItem = $db->queryFirst($sql);
            // Clean up the string
            // echo '$("#edit_desc").val("'.stripslashes(str_replace('\n','\\\n',mysql_real_escape_string(strip_tags($dataItem['description'])))).'");';
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
                $db->queryUpdate(
                    "users",
                    array(
                        'favourites' => implode(';', $_SESSION['favourites'])
                       ),
                    'id = '.$_SESSION['user_id']
                );
                // Update SESSION with this new favourite
                $data = $db->query("SELECT label,id_tree FROM ".$pre."items WHERE id = ".$_POST['id']);
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
                $db->query("UPDATE ".$pre."users SET favourites = '".implode(';', $_SESSION['favourites'])."' WHERE id = '".$_SESSION['user_id']."'");
                // refresh session fav list
                foreach ($_SESSION['favourites_tab'] as $key => $value) {
                    if ($key == $_POST['id']) {
                        unset($_SESSION['favourites_tab'][$key]);
                        break;
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
            $dataSource = $db->queryFirst(
                "SELECT i.pw, f.personal_folder,i.id_tree, f.title
                FROM ".$pre."items as i
                INNER JOIN ".$pre."nested_tree as f ON (i.id_tree=f.id)
                WHERE i.id=".$_POST['item_id']
            );
            // get data about new folder
            $dataDestination = $db->queryFirst("SELECT personal_folder, title FROM ".$pre."nested_tree WHERE id = '".$_POST['folder_id']."'");
            // update item
            $db->queryUpdate(
                'items',
                array(
                    'id_tree' => $_POST['folder_id']
                   ),
                "id='".$_POST['item_id']."'"
            );
            // previous is non personal folder and new too
            if ($dataSource['personal_folder'] == 0 && $dataDestination['personal_folder'] == 0) {
                // just update is needed. Item key is the same
            } elseif ($dataSource['personal_folder'] == 0 && $dataDestination['personal_folder'] == 1) {
                // previous is not personal folder and new is personal folder => item key exist on item
                // => suppress it => OK !
                // get key for original pw
                $originalData = $db->queryFirst(
                    'SELECT k.rand_key, i.pw
                    FROM `'.$pre.'keys` as k
                    INNER JOIN `'.$pre.'items` as i ON (k.id=i.id)
                    WHERE k.table LIKE "items"
                    AND i.id='.$_POST['item_id']
                );
                // unsalt previous pw and encrupt with personal key
                $pw = substr(decrypt($originalData['pw']), strlen($originalData['rand_key']));
                $pw = encrypt($pw, mysql_real_escape_string(stripslashes($_SESSION['my_sk'])));
                // update pw
                $db->queryUpdate(
                    'items',
                    array(
                        'pw' => $pw,
                        'perso' => 1
                       ),
                    "id='".$_POST['item_id']."'"
                );
                // Delete key
                $db->queryDelete(
                    'keys',
                    array(
                        'id' => $_POST['item_id'],
                        'table' => 'items'
                       )
                );
            }
            // If previous is personal folder and new is personal folder too => no key exist on item
            elseif ($dataSource['personal_folder'] == 1 && $dataDestination['personal_folder'] == 1) {
                // NOTHING TO DO => just update is needed. Item key is the same
            }
            // If previous is personal folder and new is not personal folder => no key exist on item => add new
            elseif ($dataSource['personal_folder'] == 1 && $dataDestination['personal_folder'] == 0) {
                // generate random key
                $randomKey = generateKey();
                // store key
                $db->queryInsert(
                    'keys',
                    array(
                        'table' => 'items',
                        'id' => $_POST['item_id'],
                        'rand_key' => $randomKey
                       )
                );
                // update item
                $db->queryUpdate(
                    'items',
                    array(
                        'pw' => encrypt($randomKey.decrypt($dataSource['pw'], mysql_real_escape_string(stripslashes($_SESSION['my_sk'])))),
                        'perso' => 0
                       ),
                    "id='".$_POST['item_id']."'"
                );
            }
            // Log item moved
            $db->queryInsert(
                'log_items',
                array(
                    'id_item' => $_POST['item_id'],
                    'date' => time(),
                    'id_user' => $_SESSION['user_id'],
                    'action' => 'at_modification',
                    'raison' => 'at_moved : '.$dataSource['title'].' -> '.$dataDestination['title']
                   )
            );

            echo '[{"from_folder":"'.$dataSource['id_tree'].'" , "to_folder":"'.$_POST['folder_id'].'"}]';
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
                if ($_POST['cat'] == "request_access_to_author") {
                    $dataAuthor = $db->queryFirst("SELECT email,login FROM ".$pre."users WHERE id= ".$content[1]);
                    $dataItem = $db->queryFirst("SELECT label FROM ".$pre."items WHERE id= ".$content[0]);
                    $ret = @sendEmail(
                        $txt['email_request_access_subject'],
                        str_replace(array('#tp_item_author#', '#tp_user#', '#tp_item#'), array(" ".addslashes($dataAuthor['login']), addslashes($_SESSION['login']), addslashes($dataItem['label'])), $txt['email_request_access_mail']),
                        $dataAuthor['email']
                    );
                } elseif ($_POST['cat'] == "share_this_item") {
                    $dataItem = $db->queryFirst("SELECT label,id_tree FROM ".$pre."items WHERE id= ".$_POST['id']);
                    $ret = @sendEmail(
                        $txt['email_share_item_subject'],
                        str_replace(
                            array('#tp_link#', '#tp_user#', '#tp_item#'),
                            array($_SESSION['settings']['cpassman_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.$_POST['id'], addslashes($_SESSION['login']), addslashes($dataItem['label'])),
                            $txt['email_share_item_mail']
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
                if ($_POST['notify_type'] == "on_show") {
                    // Check if values already exist
                    $data = $db->queryFirst("SELECT notification FROM ".$pre."items WHERE id = ".$_POST['item_id']);
                    $notifiedUsers = explode(';', $data['notification']);
                    // User is not in actual notification list
                    if ($_POST['status'] == true && !in_array($_POST['user_id'], $notifiedUsers)) {
                        // User is not in actual notification list and wants to be notified
                        $db->queryUpdate(
                            'items',
                            array(
                                'notification' => empty($data['notification']) ? $_POST['user_id'].";" : $data['notification'].$_POST['user_id']
                               ),
                            "id='".$_POST['item_id']."'"
                        );
                        echo '[{"error" : "", "new_status":"true"}]';
                        break;
                    } elseif ($_POST['status'] == false && in_array($_POST['user_id'], $notifiedUsers)) {
                        // TODO : delete user from array and store in DB
                        // User is in actual notification list and doesn't want to be notified
                        $db->queryUpdate(
                            'items',
                            array(
                                'notification' => empty($data['notification']) ? $_POST['user_id'] : $data['notification'].";".$_POST['user_id']
                               ),
                            "id='".$_POST['item_id']."'"
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
                $sql = "SELECT *
                        FROM ".$pre."items as i
                        INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                        WHERE i.id=".$dataReceived['item_id']."
                        AND l.action = 'at_creation'";
                $dataItem = $db->queryFirst($sql);
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
                    $db->queryInsert(
                        'log_items',
                        array(
                            'id_item' => $dataReceived['item_id'],
                            'date' => time(),
                            'id_user' => $_SESSION['user_id'],
                            'action' => 'at_manual',
                            'raison' => htmlspecialchars_decode($dataReceived['label'])
                           )
                    );
                    // Prepare new line
                    $data = $db->queryFirst("SELECT * FROM ".$pre."log_items WHERE id_item = '".$dataReceived['item_id']."' ORDER BY date DESC");
                    //$reason = explode(':', $data['raison']);
                    $historic = date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], $data['date'])." - ".$_SESSION['login']." - ".$txt[$data['action']]." - ".$data['raison'];
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
            $db->query("DELETE FROM ".$pre."items_edition WHERE item_id = '".$_POST['id']."'");
            break;

        /*
        * CASE
        * Check if Item has been changed since loaded
        */
        case "is_item_changed":
            // $data = $db->fetchRow("SELECT date FROM ".$pre."log_items WHERE action = 'at_modification' AND id_item = '".$_POST['item_id']."' ORDER BY date DESC");
            $data = $db->queryGetRow(
                "log_items",
                array(
                    "date"
                ),
                array(
                    "action" => "at_modification",
                    "id_item" => intval($_POST['item_id'])
                ),
                " ORDER BY date DESC"
            );
            // Check if it's in a personal folder. If yes, then force complexity overhead.
            if ($data[0] > $_POST['timestamp']) {
                echo '{ "modified" : "1" }';
            } else {
                echo '{ "modified" : "0" }';
            }
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
            $rows = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE tag LIKE '%".$_GET['term']."%' GROUP BY tag");
            foreach ($rows as $reccord) {
                //echo $reccord['tag']."|".$reccord['tag']."\n";
                if (empty($listOfTags)) {
                    $listOfTags = '"'.$reccord['tag'].'"';
                } else {
                    $listOfTags .= ', "'.$reccord['tag'].'"';
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
    global $db, $pre;
    // $data = $db->fetchRow("SELECT bloquer_creation,bloquer_modification,personal_folder FROM ".$pre."nested_tree WHERE id = '".$groupe."'");
    $data = $db->queryGetRow(
        "nested_tree",
        array(
            "bloquer_creation",
            "bloquer_modification",
            "personal_folder"
        ),
        array(
            "id" => intval($groupe)
        )
    );
    // Check if it's in a personal folder. If yes, then force complexity overhead.
    if ($data[2] == 1) {
        return array("bloquer_modification_complexite" => 1, "bloquer_creation_complexite" => 1);
    } else {
        return array("bloquer_modification_complexite" => $data[1], "bloquer_creation_complexite" => $data[0]);
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
        $image = "document-office.png";
    } elseif ($ext == "pdf") {
        $image = "document-pdf.png";
    } elseif (in_array($ext, $k['image_file_ext'])) {
        $image = "document-image.png";
    } elseif ($ext == "txt") {
        $image = "document-txt.png";
    } else {
        $image = "document.png";
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