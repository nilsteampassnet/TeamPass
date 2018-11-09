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
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

// if no folders are visible then return no results
if (isset($_SESSION['groupes_visibles']) === false
    || empty($_SESSION['groupes_visibles']) === true
) {
    echo '{"sEcho": '.intval($_GET['sEcho']).' ,"iTotalRecords": "0", "iTotalDisplayRecords": "0", "aaData": [] }';
    exit;
}

//Connect to DB
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

//Columns name
$aColumns = array('id', 'label', 'login', 'description', 'tags', 'id_tree', 'folder', 'login', 'url');
$aSortTypes = array('ASC', 'DESC');

//init SQL variables
$sOrder = $sLimit = $sWhere = '';
$sWhere = 'id_tree IN %ls_idtree'; //limit search to the visible folders

if (isset($_GET['limited']) === false
    || (isset($_GET['limited']) === true && $_GET['limited'] === 'false')
) {
    $folders = $_SESSION['groupes_visibles'];
} else {
    // Build tree
    $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    $folders = $tree->getDescendants(filter_var($_GET['limited'], FILTER_SANITIZE_NUMBER_INT), true);
    $folders = array_keys($folders);
}

//Get current user "personal folder" ID
$row = DB::query(
    'SELECT id FROM '.prefixTable('nested_tree').' WHERE title = %i',
    intval($_SESSION['user_id'])
);

//get list of personal folders
$arrayPf = $crit = array();
$listPf = '';
if (empty($row['id']) === false) {
    $rows = DB::query(
        'SELECT id FROM '.prefixTable('nested_tree').'
        WHERE personal_folder=1 AND NOT parent_id = %i AND NOT title = %i',
        '1',
        filter_var($row['id'], FILTER_SANITIZE_NUMBER_INT),
        filter_var($_SESSION['user_id'], FILTER_SANITIZE_NUMBER_INT)
    );
    foreach ($rows as $record) {
        if (!in_array($record['id'], $arrayPf)) {
            //build an array of personal folders ids
            array_push($arrayPf, $record['id']);
            //build also a string with those ids
            if (empty($listPf)) {
                $listPf = $record['id'];
            } else {
                $listPf .= ', '.$record['id'];
            }
        }
    }
}

/* BUILD QUERY */
//Paging
$sLimit = '';
if (isset($_GET['start']) === true && $_GET['length'] != '-1') {
    $sLimit = 'LIMIT '.filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT).', '.filter_var($_GET['length'], FILTER_SANITIZE_NUMBER_INT).'';
}

//Ordering
$sOrder = '';
if (isset($_GET['order']) === true) {
    if (in_array(strtoupper($_GET['order'][0]['dir']), $aSortTypes) === false) {
        // possible attack - stop
        echo '[{}]';
        exit;
    } else {
        $sOrder = 'ORDER BY  ';
        if ($_GET['order'][0]['column'] >= 0) {
            $sOrder .= ''.$aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)].' '
            .mysqli_escape_string($link, $_GET['order'][0]['dir']).', ';
        }

        $sOrder = substr_replace($sOrder, '', -2);
        if ($sOrder == 'ORDER BY') {
            $sOrder = '';
        }
    }
}

// Define criteria
$search_criteria = '';
if (isset($_GET['search']) === true) {
    if (empty($_GET['search']['value']) === false) {
        $search_criteria = filter_var($_GET['search']['value'], FILTER_SANITIZE_STRING);
    } elseif (empty($_GET['search']) === false) {
        $search_criteria = filter_var($_GET['search'], FILTER_SANITIZE_STRING);
    }
}

/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */
if (empty($search_criteria) === false) {
    $sWhere .= ' AND (';
    for ($i = 0; $i < count($aColumns); ++$i) {
        $sWhere .= $aColumns[$i].' LIKE %ss_'.$i.' OR ';
    }
    $sWhere = substr_replace($sWhere, '', -3).') ';

    $crit = array(
        'idtree' => $folders,
        '0' => $search_criteria,
        '1' => $search_criteria,
        '2' => $search_criteria,
        '3' => $search_criteria,
        '4' => $search_criteria,
        '5' => $search_criteria,
        '6' => $search_criteria,
        '7' => $search_criteria,
        '8' => $search_criteria,
        'pf' => $arrayPf,
    );
}

// Define default search criteria
if (count($crit) === 0) {
    $crit = array(
        'idtree' => $folders,
        '0' => $search_criteria,
        '1' => $search_criteria,
        '2' => $search_criteria,
        '3' => $search_criteria,
        '4' => $search_criteria,
        '5' => $search_criteria,
        '6' => $search_criteria,
        '7' => $search_criteria,
        '8' => $search_criteria,
        'pf' => $arrayPf,
    );
}

// Do NOT show the items in PERSONAL FOLDERS
if (empty($listPf) === false) {
    if (empty($sWhere) === false) {
        $sWhere .= ' AND ';
    }
    $sWhere = 'WHERE '.$sWhere.'id_tree NOT IN %ls_pf ';
} else {
    $sWhere = 'WHERE '.$sWhere;
}
db::debugmode(false);
DB::query(
    'SELECT id FROM '.prefixTable('cache')."
    $sWhere
    $sOrder",
    $crit
);
$iTotal = DB::count();

$rows = DB::query(
    'SELECT id, label, description, tags, id_tree, perso, restricted_to, login, folder, author, renewal_period, url, timestamp
    FROM '.prefixTable('cache')."
    $sWhere
    $sOrder
    $sLimit",
    $crit
);
db::debugmode(false);
$iFilteredTotal = DB::count();

/*
 * Output
 */
if (isset($_GET['type']) === false) {
    $sOutput = '{';
    if (isset($_GET['draw']) === true) {
        $sOutput .= '"draw": '.intval($_GET['draw']).', ';
    }
    $sOutput .= '"data": [';
    $sOutputConst = '';

    foreach ($rows as $record) {
        $getItemInList = true;
        $sOutputItem = '[';

        // massive move/delete enabled?
        $checkbox = '';
        if (isset($SETTINGS['enable_massive_move_delete']) && $SETTINGS['enable_massive_move_delete'] === '1') {
            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];

            foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                $access = DB::queryFirstRow(
                    'SELECT type FROM '.prefixTable('roles_values').' WHERE role_id = %i AND folder_id = %i',
                    $role,
                    $record['id_tree']
                );
                if ($access['type'] == 'R') {
                    array_push($arrTmp, 1);
                } elseif ($access['type'] == 'W') {
                    array_push($arrTmp, 0);
                } else {
                    array_push($arrTmp, 2);
                }
            }
            $accessLevel = min($arrTmp);
            if ($accessLevel === 0) {
                $checkbox = '<input type=\"checkbox\" value=\"0\" class=\"mass_op_cb\" data-id=\"'.$record['id'].'\">';
            }
        }

        // Expiration
        if ($SETTINGS['activate_expiration'] === '1') {
            if ($record['renewal_period'] > 0
                && ($record['timestamp'] + ($record['renewal_period'] * TP_ONE_MONTH_SECONDS)) < time()
            ) {
                $expired = 1;
            } else {
                $expired = 0;
            }
        } else {
            $expired = 0;
        }

        // Manage the restricted_to variable
        if (null !== filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING)) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING);
        } else {
            $restrictedTo = '';
        }

        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role'])) {
            if (empty($restrictedTo)) {
                $restrictedTo = $_SESSION['user_id'];
            } else {
                $restrictedTo .= ','.$_SESSION['user_id'];
            }
        }

        //col1
        $sOutputItem .= '"<i class=\"fa fa-external-link-alt infotip mr-2\" title=\"'.langHdl('open_url_link').'\" onClick=\"window.location.href=&#039;index.php?page=items&amp;group='.$record['id_tree'].'&amp;id='.$record['id'].'&#039;\" style=\"cursor:pointer;\"></i>'.
            '<i class=\"fa fa-eye infotip mr-2 item-detail\" title=\"'.langHdl('see_item_title').'\" data-id=\"'.$record['id'].'\" data-perso=\"'.$record['perso'].'\" data-tree-id=\"'.$record['id_tree'].'\" data-expired=\"'.$expired.'\" data-restricted-to=\"'.$restrictedTo.'\" style=\"cursor:pointer;\"></i>'.$checkbox.'", ';

        //col2
        $sOutputItem .= '"<span id=\"item_label-'.$record['id'].'\">'.(stripslashes(utf8_encode($record['label']))).'</span>", ';

        //col3
        $sOutputItem .= '"'.str_replace('&amp;', '&', htmlspecialchars(stripslashes(utf8_encode($record['login'])), ENT_QUOTES)).'", ';

        //col4
        //get restriction from ROles
        $restrictedToRole = false;
        $rTmp = DB::queryFirstColumn(
            'SELECT role_id FROM '.prefixTable('restriction_to_roles').' WHERE item_id = %i',
            $record['id']
        );
        // We considere here that if user has at least one group similar to the object ones
        // then user can see item
        if (count(array_intersect($rTmp, $_SESSION['user_roles'])) === 0 && count($rTmp) > 0) {
            $restrictedToRole = true;
        }

        if ((
            $record['perso'] == 1 && $record['author'] != $_SESSION['user_id'])
            ||
            (
                empty($record['restricted_to']) === false
                && in_array($_SESSION['user_id'], explode(';', $record['restricted_to'])) === false
            )
            ||
            (
                $restrictedToRole === true
            )
        ) {
            $getItemInList = false;
        } else {
            $txt = str_replace(array('\n', '<br />', '\\'), array(' ', ' ', '', ' '), strip_tags($record['description']));
            if (strlen($txt) > 50) {
                $sOutputItem .= '"'.substr(stripslashes(preg_replace('~/<[\/]{0,1}[^>]*>\//|[ \t]/~', '', $txt)), 0, 50).'", ';
            } else {
                $sOutputItem .= '"'.stripslashes(preg_replace('~/<[^>]*>|[ \t]/~', '', $txt)).'", ';
            }
        }

        //col5 - TAGS
        $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['tags']), ENT_QUOTES).'", ';

        // col6 - URL
        if ($record['url'] != '0') {
            $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['url']), ENT_QUOTES).'", ';
        } else {
            $sOutputItem .= '"", ';
        }

        //col7 - Prepare the Treegrid
        $sOutputItem .= '"'.htmlspecialchars(stripslashes($record['folder']), ENT_QUOTES).'"';

        //Finish the line
        $sOutputItem .= '], ';

        if ($getItemInList === true) {
            $sOutputConst .= $sOutputItem;
        } else {
            --$iFilteredTotal;
            --$iTotal;
        }
    }
    if (!empty($sOutputConst)) {
        $sOutput .= substr_replace($sOutputConst, '', -2);
    }
    $sOutput .= '], ';

    $sOutput .= '"recordsTotal": '.$iTotal.', ';
    $sOutput .= '"recordsFiltered": '.$iTotal.' }';

    echo $sOutput;
} elseif (isset($_GET['type']) && ($_GET['type'] == 'search_for_items' || $_GET['type'] == 'search_for_items_with_tags')) {
    include_once 'main.functions.php';
    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
    $sOutput = '';
    $init_personal_folder = false;
    $arr_data = [];

    foreach ($rows as $record) {
        $displayItem = false;

        $arr_data[$record['id']]['item_id'] = $record['id'];
        $arr_data[$record['id']]['tree_id'] = $record['id_tree'];
        $arr_data[$record['id']]['label'] = $record['label'];
        $arr_data[$record['id']]['desc'] = strip_tags(explode('<br>', $record['description'])[0]);
        $arr_data[$record['id']]['folder'] = $record['folder'];
        $arr_data[$record['id']]['login'] = strtr($record['login'], '"', '&quot;');
        $arr_data[$record['id']]['copy_to_clipboard_small_icons'] = isset($SETTINGS['copy_to_clipboard_small_icons'])
            ? intval($SETTINGS['copy_to_clipboard_small_icons']) : 0;
        $arr_data[$record['id']]['enable_favourites'] = isset($SETTINGS['enable_favourites'])
            ? intval($SETTINGS['enable_favourites']) : 0;
        $arr_data[$record['id']]['anyone_can_modify'] = isset($SETTINGS['anyone_can_modify'])
            ? intval($SETTINGS['anyone_can_modify']) : 0;
        $arr_data[$record['id']]['is_result_of_search'] = 1;

        if ($SETTINGS['activate_expiration'] === '1') {
            if ($record['renewal_period'] > 0
                && ($record['timestamp'] + ($record['renewal_period'] * TP_ONE_MONTH_SECONDS)) < time()
            ) {
                $arr_data[$record['id']]['expired'] = 1;
                $arr_data[$record['id']]['expirationFlag'] = 'red';
            } else {
                $arr_data[$record['id']]['expired'] = 0;
                $arr_data[$record['id']]['expirationFlag'] = 'green';
            }
        } else {
            $arr_data[$record['id']]['expired'] = 0;
            $arr_data[$record['id']]['expirationFlag'] = '';
        }
        // list of restricted users
        $restricted_users_array = explode(';', $record['restricted_to']);
        $itemPw = $itemLogin = '';
        $displayItem = $need_sk = $canMove = $item_is_restricted_to_role = 0;
        // TODO: Element is restricted to a group. Check if element can be seen by user
        // => récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
        $user_is_included_in_role = 0;
        $roles = DB::query(
            'SELECT role_id FROM '.prefixTable('restriction_to_roles').' WHERE item_id=%i',
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
        if (null !== filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING)) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING);
        } else {
            $restrictedTo = '';
        }

        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role'])) {
            if (empty($restrictedTo)) {
                $restrictedTo = $_SESSION['user_id'];
            } else {
                $restrictedTo .= ','.$_SESSION['user_id'];
            }
        }

        $arr_data[$record['id']]['restricted'] = $restrictedTo;

        // CASE where item is restricted to a role to which the user is not associated
        if (isset($user_is_included_in_role) === true
            && $user_is_included_in_role == 0
            && isset($item_is_restricted_to_role) === true
            && $item_is_restricted_to_role == 1
            && in_array($_SESSION['user_id'], $restricted_users_array) === false
        ) {
            // $perso = '<i class="fa fa-tag mi-red"></i>&nbsp;';
            $arr_data[$record['id']]['perso'] = 'fa-tag mi-red';
            $arr_data[$record['id']]['sk'] = 0;
            $arr_data[$record['id']]['display'] = 'no_display';
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = '';

        // Case where item is in own personal folder
        } elseif (in_array($record['id_tree'], $_SESSION['personal_visible_groups'])
            && $record['perso'] == 1
        ) {
            $arr_data[$record['id']]['perso'] = 'fa-warning mi-red';
            $arr_data[$record['id']]['sk'] = 1;
            $arr_data[$record['id']]['display'] = '';
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = '';

        // CAse where item is restricted to a group of users included user
        } elseif (empty($record['restricted_to']) === false
            && in_array($_SESSION['user_id'], $restricted_users_array) === true
            || (isset($_SESSION['list_folders_editable_by_role']) === true
            && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role']) === true)
            && in_array($_SESSION['user_id'], $restricted_users_array) === true
        ) {
            $arr_data[$record['id']]['perso'] = 'fa-tag mi-yellow';
            $arr_data[$record['id']]['sk'] = 0;
            $arr_data[$record['id']]['display'] = '';
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = '';

        // CAse where item is restricted to a group of users not including user
        } elseif ($record['perso'] == 1
            ||
            (
                empty($record['restricted_to']) === false
                && in_array($_SESSION['user_id'], $restricted_users_array) === false
            )
            ||
            (
                isset($user_is_included_in_role) === true
                && isset($item_is_restricted_to_role) === true
                && $user_is_included_in_role == 0
                && $item_is_restricted_to_role == 1
            )
        ) {
            if (isset($user_is_included_in_role)
                && isset($item_is_restricted_to_role)
                && $user_is_included_in_role == 0
                && $item_is_restricted_to_role == 1
            ) {
                $arr_data[$record['id']]['perso'] = 'fa-tag mi-red';
                $arr_data[$record['id']]['sk'] = 0;
                $arr_data[$record['id']]['display'] = 'no_display';
                $arr_data[$record['id']]['open_edit'] = 1;
                $arr_data[$record['id']]['reload'] = '';
                $displayItem = true;
            } else {
                $arr_data[$record['id']]['perso'] = 'fa-tag mi-yellow';
                $arr_data[$record['id']]['sk'] = 0;
                $arr_data[$record['id']]['display'] = '';
                $arr_data[$record['id']]['open_edit'] = 1;
                $arr_data[$record['id']]['reload'] = '';
                $displayItem = true;
            }
        } else {
            $arr_data[$record['id']]['perso'] = 'fa-tag mi-green';
            $arr_data[$record['id']]['sk'] = 0;
            $arr_data[$record['id']]['display'] = '';
            $arr_data[$record['id']]['open_edit'] = 1;
            $arr_data[$record['id']]['reload'] = '';
            $displayItem = true;
        }

        // prepare pwd copy if enabled
        $arr_data[$record['id']]['pw_status'] = '';
        if (isset($SETTINGS['copy_to_clipboard_small_icons']) === true && $SETTINGS['copy_to_clipboard_small_icons'] === '1') {
            $data_item = DB::queryFirstRow(
                'SELECT pw
                from '.prefixTable('items').' WHERE id=%i',
                $record['id']
            );

            if ($record['perso'] === '1' && isset($_SESSION['user_settings']['session_psk']) === true) {
                $pw = cryption(
                    $data_item['pw'],
                    $_SESSION['user_settings']['session_psk'],
                    'decrypt',
                    $SETTINGS
                );
            } else {
                $pw = cryption(
                    $data_item['pw'],
                    '',
                    'decrypt',
                    $SETTINGS
                );
            }

            // test charset => may cause a json error if is not utf8
            $pw = $pw['string'];
            if (isUTF8($pw) === false) {
                $pw = '';
                $arr_data[$record['id']]['pw_status'] = 'encryption_error';
            }
        } else {
            $pw = '';
        }
        $arr_data[$record['id']]['pw'] = strtr($pw, '"', '&quot;');
        if (in_array($record['id'], $_SESSION['favourites'])) {
            $arr_data[$record['id']]['is_favorite'] = 1;
        } else {
            $arr_data[$record['id']]['is_favorite'] = 0;
        }

        $arr_data[$record['id']]['display_item'] = $displayItem === true ? 1 : 0;
    }

    $returnValues = array(
        'html_json' => $arr_data,
        'message' => str_replace('%X%', $iFilteredTotal, langHdl('find_message')),
    );

    echo prepareExchangedData($returnValues, 'encode');
}
