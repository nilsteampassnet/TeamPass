<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      find.queries.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (! isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || ! isset($_SESSION['key']) || empty($_SESSION['key'])) {
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
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';
// if no folders are visible then return no results
if (isset($_SESSION['groupes_visibles']) === false
    || empty($_SESSION['groupes_visibles']) === true
) {
    echo '{"sEcho": ' . intval($_GET['sEcho']) . ' ,"iTotalRecords": "0", "iTotalDisplayRecords": "0", "aaData": [] }';
    exit;
}

//Connect to DB
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
//Columns name
$aColumns = ['id', 'label', 'login', 'description', 'tags', 'id_tree', 'folder', 'login', 'url'];
$aSortTypes = ['ASC', 'DESC'];
//init SQL variables
$sOrder = $sLimit = $sWhere = '';
$sWhere = 'id_tree IN %ls_idtree';
//limit search to the visible folders

if (isset($_GET['limited']) === false
    || (isset($_GET['limited']) === true && $_GET['limited'] === 'false')
) {
    $folders = $_SESSION['groupes_visibles'];
} else {
    // Build tree
    $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'] . '/includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $folders = $tree->getDescendants(filter_var($_GET['limited'], FILTER_SANITIZE_NUMBER_INT), true);
    $folders = array_keys($folders);
}

//Get current user "personal folder" ID
$row = DB::query(
    'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE title = %i',
    intval($_SESSION['user_id'])
);
//get list of personal folders
$arrayPf = [];
$crit = [];
$listPf = '';
if (empty($row['id']) === false) {
    $rows = DB::query(
        'SELECT id FROM ' . prefixTable('nested_tree') . '
        WHERE personal_folder = 1 AND NOT parent_id = %i AND NOT title = %i',
        filter_var($row['id'], FILTER_SANITIZE_NUMBER_INT),
        filter_var($_SESSION['user_id'], FILTER_SANITIZE_NUMBER_INT)
    );
    foreach ($rows as $record) {
        if (! in_array($record['id'], $arrayPf)) {
            //build an array of personal folders ids
            array_push($arrayPf, $record['id']);
            //build also a string with those ids
            if (empty($listPf)) {
                $listPf = $record['id'];
            } else {
                $listPf .= ', ' . $record['id'];
            }
        }
    }
}

/* BUILD QUERY */
//Paging
$sLimit = '';
if (isset($_GET['start']) === true && $_GET['length'] !== '-1') {
    $sLimit = 'LIMIT ' . filter_var($_GET['start'], FILTER_SANITIZE_NUMBER_INT) . ', ' . filter_var($_GET['length'], FILTER_SANITIZE_NUMBER_INT) . '';
}

//Ordering
$sOrder = '';
if (isset($_GET['order']) === true) {
    if (in_array(strtoupper($_GET['order'][0]['dir']), $aSortTypes) === false) {
        // possible attack - stop
        echo '[{}]';
        exit;
    }
    $sOrder = 'ORDER BY  ';
    if ($_GET['order'][0]['column'] >= 0) {
        $sOrder .= '' . $aColumns[filter_var($_GET['order'][0]['column'], FILTER_SANITIZE_NUMBER_INT)] . ' '
                . filter_var($_GET['order'][0]['dir'], FILTER_SANITIZE_STRING) . ', ';
    }

    $sOrder = substr_replace($sOrder, '', -2);
    if ($sOrder === 'ORDER BY') {
        $sOrder = '';
    }
} else {
    $sOrder = 'ORDER BY ' . $aColumns[1] . ' ASC';
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
        $sWhere .= $aColumns[$i] . ' LIKE %ss_' . $i . ' OR ';
    }
    $sWhere = substr_replace((string) $sWhere, '', -3) . ') ';
    $crit = [
        'idtree' => array_unique($folders),
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
    ];
}

// Define default search criteria
if (count($crit) === 0) {
    $crit = [
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
    ];
}

// Do NOT show the items in PERSONAL FOLDERS
if (empty($listPf) === false) {
    if (empty($sWhere) === false) {
        $sWhere .= ' AND ';
    }
    $sWhere = 'WHERE ' . $sWhere . 'id_tree NOT IN %ls_pf ';
} else {
    $sWhere = 'WHERE ' . $sWhere;
}

DB::query(
    'SELECT id FROM ' . prefixTable('cache') . "
    ${sWhere}
    ${sOrder}",
    $crit
);
$iTotal = DB::count();
$rows = DB::query(
    'SELECT id, label, description, tags, id_tree, perso, restricted_to, login, folder, author, renewal_period, url, timestamp
    FROM ' . prefixTable('cache') . "
    ${sWhere}
    ${sOrder}
    ${sLimit}",
    $crit
);

/*
 * Output
 */
if (isset($_GET['type']) === false) {
    $sOutput = '{';
    if (isset($_GET['draw']) === true) {
        $sOutput .= '"draw": ' . intval($_GET['draw']) . ', ';
    }
    $sOutput .= '"data": [';
    $sOutputConst = '';
    foreach ($rows as $record) {
        $getItemInList = true;
        $sOutputItem = '[';
        $right = 0;
        $checkbox = '';
        // massive move/delete enabled?
        if (isset($SETTINGS['enable_massive_move_delete']) && (int) $SETTINGS['enable_massive_move_delete'] === 1) {
            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];
            foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                //db::debugmode(true);
                $access = DB::queryFirstRow(
                    'SELECT type FROM ' . prefixTable('roles_values') . ' WHERE role_id = %i AND folder_id = %i',
                    $role,
                    $record['id_tree']
                );
                if ($access !== null) {
                    if ($access['type'] === 'R') {
                        array_push($arrTmp, 1);
                    } elseif ($access['type'] === 'W') {
                        array_push($arrTmp, 0);
                    } elseif ($access['type'] === 'ND') {
                        array_push($arrTmp, 2);
                    } elseif ($access['type'] === 'NE') {
                        array_push($arrTmp, 1);
                    } elseif ($access['type'] === 'NDNE') {
                        array_push($arrTmp, 1);
                    }
                }
            }
            $accessLevel = count($arrTmp) > 0 ? min($arrTmp) : $accessLevel;
            if ($accessLevel === 0) {
                $checkbox = '<input type=\"checkbox\" value=\"0\" class=\"mass_op_cb\" data-id=\"' . $record['id'] . '\">';
            }

            if ((int) $accessLevel === 0) {
                $right = 0;
            } elseif ((10 <= (int) $accessLevel) && ((int) $accessLevel < 20)) {
                $right = 20;
            } elseif ((20 <= (int) $accessLevel) && ((int) $accessLevel < 30)) {
                $right = 60;
            } elseif ((int) $accessLevel === 30) {
                $right = 70;
            } else {
                $right = 10;
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
        if (filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING) !== null) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING);
        } else {
            $restrictedTo = '';
        }

        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role'])) {
            if (empty($restrictedTo)) {
                $restrictedTo = $_SESSION['user_id'];
            } else {
                $restrictedTo .= ',' . $_SESSION['user_id'];
            }
        }

        //col1
        $sOutputItem .= '"<i class=\"fa fa-external-link-alt infotip mr-2\" title=\"' . langHdl('open_url_link') . '\" onClick=\"window.location.href=&#039;index.php?page=items&amp;group=' . $record['id_tree'] . '&amp;id=' . $record['id'] . '&#039;\" style=\"cursor:pointer;\"></i>' .
            '<i class=\"fa fa-eye infotip mr-2 item-detail\" title=\"' . langHdl('see_item_title') . '\" data-id=\"' . $record['id'] . '\" data-perso=\"' . $record['perso'] . '\" data-tree-id=\"' . $record['id_tree'] . '\" data-expired=\"' . $expired . '\" data-restricted-to=\"' . $restrictedTo . '\" data-rights=\"' . $right . '\" style=\"cursor:pointer;\"></i>' . $checkbox . '", ';
        //col2
        $sOutputItem .= '"<span id=\"item_label-' . $record['id'] . '\">' . str_replace("\\", "&#92;", (string) $record['label']) . '</span>", ';   // replace backslash #3015
        //col3
        $sOutputItem .= '"' . str_replace('&amp;', '&', htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES)) . '", ';
        //col4
        //get restriction from ROles
        $restrictedToRole = false;
        $rTmp = DB::queryFirstColumn(
            'SELECT role_id FROM ' . prefixTable('restriction_to_roles') . ' WHERE item_id = %i',
            $record['id']
        );
        // We considere here that if user has at least one group similar to the object ones
        // then user can see item
        if (count(array_intersect($rTmp, $_SESSION['user_roles'])) === 0 && count($rTmp) > 0) {
            $restrictedToRole = true;
        }

        if (($record['perso'] === 1 && $record['author'] !== $_SESSION['user_id'])
            || (empty($record['restricted_to']) === false
            && in_array($_SESSION['user_id'], explode(';', $record['restricted_to'])) === false)
            || ($restrictedToRole === true)
        ) {
            $getItemInList = false;
        } else {
            $txt = str_replace(['\n', '<br />', '\\'], [' ', ' ', '', ' '], strip_tags($record['description']));
            if (strlen($txt) > 50) {
                $sOutputItem .= '"' . substr(stripslashes(preg_replace('~/<[\/]{0,1}[^>]*>\//|[ \t]/~', '', $txt)), 0, 50) . '", ';
            } else {
                $sOutputItem .= '"' . stripslashes(preg_replace('~/<[^>]*>|[ \t]/~', '', $txt)) . '", ';
            }
        }

        //col5 - TAGS
        $sOutputItem .= '"' . htmlspecialchars(stripslashes((string) $record['tags']), ENT_QUOTES) . '", ';
        // col6 - URL
        if ($record['url'] !== '0') {
            $sOutputItem .= '"'.filter_var($record['url'], FILTER_SANITIZE_URL).'", ';
        } else {
            $sOutputItem .= '"", ';
        }

        //col7 - Prepare the Treegrid
        $sOutputItem .= '"' . htmlspecialchars(stripslashes((string) $record['folder']), ENT_QUOTES) . '"';
        //Finish the line
        $sOutputItem .= '], ';
        if ($getItemInList === true) {
            $sOutputConst .= $sOutputItem;
        } else {
            --$iTotal;
        }
    }
    if (! empty($sOutputConst)) {
        $sOutput .= substr_replace($sOutputConst, '', -2);
    }
    $sOutput .= '], ';
    $sOutput .= '"recordsTotal": ' . $iTotal . ', ';
    $sOutput .= '"recordsFiltered": ' . $iTotal . ' }';
    echo $sOutput;
} elseif (isset($_GET['type']) && ($_GET['type'] === 'search_for_items' || $_GET['type'] === 'search_for_items_with_tags')) {
    include_once 'main.functions.php';
    include_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';
    
    $arr_data = [];
    foreach ($rows as $record) {
        $displayItem = false;
        $arr_data[$record['id']]['item_id'] = (int) $record['id'];
        $arr_data[$record['id']]['tree_id'] = (int) $record['id_tree'];
        $arr_data[$record['id']]['label'] = $record['label'];
        $arr_data[$record['id']]['desc'] = strip_tags(explode('<br>', $record['description'])[0]);
        $arr_data[$record['id']]['folder'] = $record['folder'];
        $arr_data[$record['id']]['login'] = strtr($record['login'], '"', '&quot;');
        // Is favorite?
        if (in_array($record['id'], $_SESSION['favourites']) === true) {
            $arr_data[$record['id']]['enable_favourites'] = 1;
        } else {
            $arr_data[$record['id']]['enable_favourites'] = 0;
        }

        // Anyone can modify?
        $tmp = DB::queryfirstrow(
            'SELECT anyone_can_modify FROM ' . prefixTable('items') . ' WHERE id = %i',
            $record['id']
        );
        if (count($tmp) > 0) {
            $arr_data[$record['id']]['anyone_can_modify'] = (int) $tmp['anyone_can_modify'];
        } else {
            $arr_data[$record['id']]['anyone_can_modify'] = 0;
        }

        $arr_data[$record['id']]['is_result_of_search'] = 1;
        if ((int) $SETTINGS['activate_expiration'] === 1) {
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
        $displayItem = $need_sk = $canMove = $item_is_restricted_to_role = 0;
        // TODO: Element is restricted to a group. Check if element can be seen by user
        // => récupérer un tableau contenant les roles associés à cet ID (a partir table restriction_to_roles)
        $user_is_included_in_role = 0;
        $roles = DB::query(
            'SELECT role_id FROM ' . prefixTable('restriction_to_roles') . ' WHERE item_id=%i',
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
        if (filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING) !== null) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_STRING);
        } else {
            $restrictedTo = '';
        }

        if (isset($_SESSION['list_folders_editable_by_role']) && in_array($record['id_tree'], $_SESSION['list_folders_editable_by_role'])) {
            if (empty($restrictedTo)) {
                $restrictedTo = $_SESSION['user_id'];
            } else {
                $restrictedTo .= ',' . $_SESSION['user_id'];
            }
        }

        $arr_data[$record['id']]['restricted'] = $restrictedTo;

        $right = 0;
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
            && DB::count() > 0
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

            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];
            foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                $access = DB::queryFirstRow(
                    'SELECT type FROM ' . prefixTable('roles_values') . ' WHERE role_id = %i AND folder_id = %i',
                    $role,
                    $record['id_tree']
                );
                if ($access['type'] === 'R') {
                    array_push($arrTmp, 1);
                } elseif ($access['type'] === 'W') {
                    array_push($arrTmp, 0);
                } elseif ($access['type'] === 'ND') {
                    array_push($arrTmp, 2);
                } elseif ($access['type'] === 'NE') {
                    array_push($arrTmp, 1);
                } elseif ($access['type'] === 'NDNE') {
                    array_push($arrTmp, 1);
                }
            }
            if (count($arrTmp) > 0) {
                $accessLevel = min($arrTmp);
            }

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
        $arr_data[$record['id']]['rights'] = $right;
        $arr_data[$record['id']]['perso'] = 'fa-tag mi-red';
        $arr_data[$record['id']]['sk'] = $itemIsPersonal === true ? 1 : 0;
        $arr_data[$record['id']]['display'] = $right > 0 ? 1 : 0;
        $arr_data[$record['id']]['open_edit'] = in_array($right, [40, 50, 60, 70]) === true ? 1 : 0;
        $arr_data[$record['id']]['canMove'] = in_array($right, [30, 60, 70]) === true ? 1 : 0;
        //*************** */

        // prepare pwd copy if enabled
        $arr_data[$record['id']]['pw_status'] = '';
        if (isset($SETTINGS['copy_to_clipboard_small_icons']) === true
            && (int) $SETTINGS['copy_to_clipboard_small_icons'] === 1
        ) {
            $data_item = DB::queryFirstRow(
                'SELECT i.pw AS pw, s.share_key AS share_key
                FROM ' . prefixTable('items') . ' AS i
                INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
                WHERE i.id = %i AND s.user_id = %i',
                $record['id'],
                $_SESSION['user_id']
            );
            // Uncrypt PW
            if (DB::count() === 0 || empty($data_item['pw']) === true) {
                // No share key found
                $pw = '';
            } else {
                $pw = doDataDecryption(
                    $data_item['pw'],
                    decryptUserObjectKey(
                        $data_item['share_key'],
                        $_SESSION['user']['private_key']
                    )
                );
            }

            // test charset => may cause a json error if is not utf8
            if (empty($pw) === true) {
                $arr_data[$record['id']]['pw_status'] = 'pw_is_empty';
            } elseif (isUTF8($pw) === false) {
                $arr_data[$record['id']]['pw_status'] = 'encryption_error';
            }
        }
        //$arr_data[$record['id']]['pw'] = strtr($pw, '"', '&quot;');
        if (in_array($record['id'], $_SESSION['favourites'])) {
            $arr_data[$record['id']]['is_favorite'] = 1;
        } else {
            $arr_data[$record['id']]['is_favorite'] = 0;
        }

        $arr_data[$record['id']]['display_item'] = $displayItem === true ? 1 : 0;
    }

    $returnValues = [
        'html_json' => filter_var_array($arr_data, FILTER_SANITIZE_STRING),
        'message' => filter_var(
            str_replace('%X%', $iTotal, langHdl('find_message')),
            FILTER_SANITIZE_STRING
        ),
        'total' => (int) $iTotal,
        'start' => (int) (isset($_GET['start']) === true && (int) $_GET['length'] !== -1) ? (int) $_GET['start'] + (int) $_GET['length'] : -1,
    ];
    echo prepareExchangedData(
    $SETTINGS['cpassman_dir'],$returnValues, 'encode');
}
