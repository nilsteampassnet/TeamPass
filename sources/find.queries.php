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
 * @file      find.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => null !== $request->request->get('type') ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

// if no folders are visible then return no results
if (null === $session->get('user-accessible_folders')
    || empty($session->get('user-accessible_folders')) === true
) {
    echo '{"sEcho": ' . $request->query->filter('sEcho', FILTER_SANITIZE_NUMBER_INT) . ' ,"iTotalRecords": "0", "iTotalDisplayRecords": "0", "aaData": [] }';
    exit;
}

//Columns name
$aColumns = ['c.id', 'c.label', 'c.login', 'c.description', 'c.tags', 'c.id_tree', 'c.folder', 'c.login', 'c.url', 'ci.data'];//
$aSortTypes = ['ASC', 'DESC'];
//init SQL variables
$sOrder = $sLimit = $sWhere = '';
$sWhere = 'c.id_tree IN %ls_idtree';
//limit search to the visible folders

if (null === $request->query->get('limited')
    || (null !== $request->query->get('limited') && $request->query->get('limited') === 'false')
) {
    $folders = $session->get('user-accessible_folders');
} else {
    // Build tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $folders = $tree->getDescendants($request->query->filter('limited', null, FILTER_SANITIZE_NUMBER_INT), true);
    $folders = array_keys($folders);
}

//Get current user "personal folder" ID
$row = DB::query(
    'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE title = %i',
    intval($session->get('user-id'))
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
        filter_var($session->get('user-id'), FILTER_SANITIZE_NUMBER_INT)
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
if (null !== $request->query->get('start') && $request->query->get('length') !== '-1') {
    $sLimit = 'LIMIT ' . $request->query->filter('start', null, FILTER_SANITIZE_NUMBER_INT) . ', ' . $request->query->filter('length', null, FILTER_SANITIZE_NUMBER_INT) . '';
}

//Ordering
$sOrder = '';
$orderParam = $request->query->all()['order'] ?? null;
if (isset($orderParam) && is_array($orderParam)) {
    if (in_array(strtoupper($orderParam[0]['dir']), $aSortTypes) === false) {
        // possible attack - stop
        echo '[{}]';
        exit;
    }
    $sOrder = 'ORDER BY  ';
    if ($orderParam[0]['column'] >= 0) {
        $sOrder .= '' . $aColumns[filter_var($orderParam[0]['column'], FILTER_SANITIZE_NUMBER_INT)] . ' '
                . filter_var($orderParam[0]['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . ', ';
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
$searchParam = $request->query->all()['search'] ?? null;
if (isset($searchParam)) {
    if (empty($searchParam['value']) === false) {
        $search_criteria = filter_var($searchParam['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    } elseif (empty($searchParam) === false) {
        $search_criteria = $searchParam;
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
        '9' => $search_criteria,
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
        '9' => $search_criteria,
        'pf' => $arrayPf,
    ];
}

// Do NOT show the items in PERSONAL FOLDERS
if (empty($listPf) === false) {
    if (empty($sWhere) === false) {
        $sWhere .= ' AND ';
    }
    $sWhere = 'WHERE ' . $sWhere . 'c.id_tree NOT IN %ls_pf ';
} else {
    $sWhere = 'WHERE ' . $sWhere;
}

// Do queries
DB::query(
    "SELECT c.id
    FROM " . prefixTable('cache') . " AS c
    LEFT JOIN " . prefixTable('categories_items') . " AS ci ON (ci.item_id = c.id)
    {$sWhere}
    {$sOrder}",
    $crit
);
$iTotal = DB::count();
$rows = DB::query(
    "SELECT c.*, ci.data, i.item_key
    FROM " . prefixTable('cache') . " AS c
    LEFT JOIN " . prefixTable('categories_items') . " AS ci ON (ci.item_id = c.id)
    INNER JOIN " . prefixTable('items') . " AS i ON (i.id = c.id)
    {$sWhere}
    {$sOrder}
    {$sLimit}",
    $crit
);

/*
// Search in fields
$rows_fields = DB::query(
    'SELECT item_id, data
    FROM ' . prefixTable('categories_items') . "
    WHERE encryption_type = 'not_set' AND data LIKE %ss_search
    ${sOrder}
    ${sLimit}",
    $search_criteria
);*/

/*
 * Output
 */

if (null === $request->query->get('type')) {
    $sOutput = '{';
    if (null !== $request->query->get('draw')) {
        $sOutput .= '"draw": ' . $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    }
    $sOutput .= '"data": [';
    $sOutputConst = '';
    foreach ($rows as $record) {
        $getItemInList = true;
        $sOutputItem = '';
        $right = 0;
        $checkbox = '';
        // massive move/delete enabled?
        if (isset($SETTINGS['enable_massive_move_delete']) && (int) $SETTINGS['enable_massive_move_delete'] === 1) {
            // check role access on this folder (get the most restrictive) (2.1.23)
            $accessLevel = 2;
            $arrTmp = [];
            foreach (explode(';', $session->get('user-roles')) as $role) {
                //db::debugmode(true);
                $access = DB::queryFirstRow(
                    'SELECT type FROM ' . prefixTable('roles_values') . ' WHERE role_id = %i AND folder_id = %i',
                    $role,
                    $record['id_tree']
                );
                if (DB::count() === 0 || array_key_exists('type', $access) === false) {
                    array_push($arrTmp, 4);
                } elseif ($access['type'] === 'R') {
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
        if (filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $restrictedTo = '';
        }

        if (null !== $session->get('system-list_folders_editable_by_role') && in_array($record['id_tree'], $session->get('system-list_folders_editable_by_role'))) {
            if (empty($restrictedTo)) {
                $restrictedTo = $session->get('user-id');
            } else {
                $restrictedTo .= ',' . $session->get('user-id');
            }
        }
        
        //col1
        $sOutputItem .= '"<i class=\"fa fa-external-link-alt infotip mr-2\" title=\"' . $lang->get('open_url_link') . '\" onClick=\"window.location.href=&#039;index.php?page=items&amp;group=' . $record['id_tree'] . '&amp;id=' . $record['id'] . '&#039;\" style=\"cursor:pointer;\"></i>' .
        '<i class=\"fa fa-eye infotip mr-2 item-detail\" title=\"' . stripslashes($lang->get('see_item_title')) . '\" data-id=\"' . $record['id'] . '\" data-perso=\"' . $record['perso'] . '\" data-tree-id=\"' . $record['id_tree'] . '\" data-expired=\"' . $expired . '\" data-restricted-to=\"' . $restrictedTo . '\" data-rights=\"' . $right . '\" style=\"cursor:pointer;\"></i>' . $checkbox . '",' ;
        //col2
        $sOutputItem .= '"'.base64_encode('<span id=\"item_label-' . $record['id'] . '\">' . (str_replace("\\", "&#92;", (string) $record['label'])) . '</span>').'", ';   // replace backslash #3015
        //col3
        $sOutputItem .= '"' . base64_encode(str_replace('&amp;', '&', htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES))) . '", ';
        //col4
        //get restriction from ROles
        $restrictedToRole = false;
        $rTmp = DB::queryFirstColumn(
            'SELECT role_id FROM ' . prefixTable('restriction_to_roles') . ' WHERE item_id = %i',
            $record['id']
        );
        // We considere here that if user has at least one group similar to the object ones
        // then user can see item
        if (count(array_intersect($rTmp, $session->get('user-roles_array'))) === 0 && count($rTmp) > 0) {
            $restrictedToRole = true;
        }

        if (($record['perso'] === 1 && $record['author'] !== $session->get('user-id'))
            || (empty($record['restricted_to']) === false
            && in_array($session->get('user-id'), explode(';', $record['restricted_to'])) === false)
            || ($restrictedToRole === true)
        ) {
            $getItemInList = false;
        } else {
            $txt = str_replace(['\n', '<br />', '\\'], [' ', ' ', '', ' '], strip_tags($record['description']));
            if (strlen($txt) > 50) {
                $sOutputItem .= '"' . base64_encode(substr(stripslashes(preg_replace('~/<[\/]{0,1}[^>]*>\//|[ \t]/~', '', $txt)), 0, 50)) . '", ';
            } else {
                $sOutputItem .= '"' . base64_encode(stripslashes(preg_replace('~/<[^>]*>|[ \t]/~', '', $txt))) . '", ';
            }
        }

        //col5 - TAGS
        $sOutputItem .= '"' . base64_encode(htmlspecialchars(stripslashes((string) $record['tags']), ENT_QUOTES)) . '", ';
        // col6 - URL
        if ($record['url'] !== '0') {
            $sOutputItem .= '"'.htmlspecialchars(filter_var($record['url'], FILTER_SANITIZE_URL)).'", ';
        } else {
            $sOutputItem .= '"", ';
        }

        //col7 - Prepare the Treegrid
        $sOutputItem .= '"' . base64_encode(stripslashes((string) $record['folder'])) . '"';
        //Finish the line
        //$sOutputItem .= '], ';
        if ($getItemInList === true) {
            $sOutputConst .= '['.($sOutputItem).'], ';
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
    // file deepcode ignore XSS: data is secured
    echo ($sOutput);
} elseif (null !== $request->query->get('type') && ($request->query->get('type') === 'search_for_items' || $request->query->get('type') === 'search_for_items_with_tags')) {
    include_once 'main.functions.php';
    include_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $session->get('user-language') . '.php';

    $arr_data = [];
    foreach ($rows as $record) {
        $displayItem = false;
        $arr_data[$record['id']]['item_id'] = (int) $record['id'];
        $arr_data[$record['id']]['tree_id'] = (int) $record['id_tree'];
        $arr_data[$record['id']]['label'] = (string) $record['label'];
        $arr_data[$record['id']]['desc'] = (string) strip_tags(explode('<br>', $record['description'])[0]);
        $arr_data[$record['id']]['folder'] = (string)$record['folder'];
        $arr_data[$record['id']]['login'] = (string) strtr($record['login'], '"', '&quot;');
        $arr_data[$record['id']]['item_key'] = (string) $record['item_key'];
        $arr_data[$record['id']]['link'] = (string) $record['url'] !== '0' && empty($record['url']) === false ? filter_var($record['url'], FILTER_SANITIZE_URL) : '';
        // Is favorite?
        if (in_array($record['id'], $session->get('user-favorites')) === true) {
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
                if (in_array($val['role_id'], $session->get('user-roles_array'))) {
                    $user_is_included_in_role = 1;
                    break;
                }
            }
        }
        // Manage the restricted_to variable
        if (filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_FULL_SPECIAL_CHARS) !== null) {
            $restrictedTo = filter_input(INPUT_POST, 'restricted', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            $restrictedTo = '';
        }

        if (null !== $session->get('system-list_folders_editable_by_role') && in_array($record['id_tree'], $session->get('system-list_folders_editable_by_role'))) {
            if (empty($restrictedTo)) {
                $restrictedTo = $session->get('user-id');
            } else {
                $restrictedTo .= ',' . $session->get('user-id');
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
        } elseif ((($session->has('user-manager') && (int) $session->get('user-manager') && null !== $session->get('user-manager') && (int) $session->get('user-manager') === 1)
            || ($session->has('user-can_manage_all_users') && (int) $session->get('user-can_manage_all_users') && null !== $session->get('user-can_manage_all_users') && (int) $session->get('user-can_manage_all_users') === 1))
            && (isset($SETTINGS['manager_edit']) === true && (int) $SETTINGS['manager_edit'] === 1)
            && $record['perso'] !== 1
        ) {
            // Case 2 - Is user manager and option "manager_edit" set to true?
            // Allow all rights
            $right = 70;
        // ----- END CASE 3 -----
        } elseif (empty($record['restricted_to']) === false
            && in_array($session->get('user-id'), explode(';', $record['restricted_to'])) === true
            && $record['perso'] !== 1
            && (int) $session->get('user-read_only') === 0
        ) {
            // Case 4 - Is this item limited to Users? Is current user in this list?
            // Allow all rights
            $right = 70;
        // ----- END CASE 4 -----
        } elseif ($user_is_included_in_role === true
            && $record['perso'] !== 1
            && (int) $session->get('user-read_only') === 0
        ) {
            // Case 5 - Is this item limited to group of users? Is current user in one of those groups?
            // Allow all rights
            $right = 60;
        // ----- END CASE 5 -----
        } elseif ($record['perso'] !== 1
            && (int) $session->get('user-read_only') === 1
        ) {
            // Case 6 - Is user readonly?
            // Allow limited rights
            $right = 10;
        // ----- END CASE 6 -----
        } elseif ($record['perso'] !== 1
            && (int) $session->get('user-read_only') === 1
        ) {
            // Case 7 - Is user readonly?
            // Allow limited rights
            $right = 10;
        // ----- END CASE 7 -----
        } elseif ($record['perso'] !== 1
            && (int) $session->get('user-read_only') === 1
        ) {
            // Case 8 - Is user allowed to access?
            // Allow rights
            $right = 10;
        // ----- END CASE 8 -----
        } elseif (((empty($record['restricted_to']) === false
            && in_array($session->get('user-id'), explode(';', $record['restricted_to'])) === false)
            || ($user_is_included_in_role === false && $item_is_restricted_to_role === true))
            && $record['perso'] !== 1
            && (int) $session->get('user-read_only') === 0
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
            foreach (explode(';', $session->get('user-roles')) as $role) {
                $access = DB::queryFirstRow(
                    'SELECT type FROM ' . prefixTable('roles_values') . ' WHERE role_id = %i AND folder_id = %i',
                    $role,
                    $record['id_tree']
                );
                if (DB::count() === 0 || array_key_exists('type', $access) === false) {
                    array_push($arrTmp, 4);
                } elseif ($access['type'] === 'R') {
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
                $session->get('user-id')
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
                        $session->get('user-private_key')
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
        if (in_array($record['id'], $session->get('user-favorites'))) {
            $arr_data[$record['id']]['is_favorite'] = 1;
        } else {
            $arr_data[$record['id']]['is_favorite'] = 0;
        }

        $arr_data[$record['id']]['display_item'] = $displayItem === true ? 1 : 0;
    }

    $returnValues = [
        'html_json' => filter_var_array($arr_data, FILTER_SANITIZE_FULL_SPECIAL_CHARS),
        'message' => (string) $iTotal.' '.$lang->get('find_message'),
        'total' => (int) $iTotal,
        'start' => (int) (null !== $request->query->get('start') && (int) $request->query->get('length') !== -1) ? $request->query->filter('start', FILTER_SANITIZE_NUMBER_INT) + $request->query->filter('length', FILTER_SANITIZE_NUMBER_INT) : -1,
    ];
    echo prepareExchangedData(
        $returnValues,
        'encode'
    );
}
