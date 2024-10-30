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
 * @file      users.datatable.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;
use voku\helper\AntiXSS;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$antiXss = new AntiXSS();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
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

// --------------------------------- //

// Configure AntiXSS to keep double-quotes
$antiXss->removeEvilAttributes(['style', 'onclick', 'onmouseover', 'onmouseout', 'onmousedown', 'onmouseup', 'onmousemove', 'onkeydown', 'onkeyup', 'onkeypress', 'onchange', 'onblur', 'onfocus', 'onabort', 'onerror', 'onscroll']);
$antiXss->removeEvilHtmlTags(['script', 'iframe', 'embed', 'object', 'applet', 'link', 'style']);

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Build FUNCTIONS list
$params = $request->query->all();
$rolesList = [];
$titles = DB::query('SELECT id,title FROM '.prefixTable('roles_title').' ORDER BY title ASC');
foreach ($titles as $title) {
    $rolesList[$title['id']] = ['id' => $title['id'], 'title' => $title['title']];
}

$html = '';
//Columns name
$aColumns = ['id', 'login', 'name', 'lastname', 'admin', 'read_only', 'gestionnaire', 'isAdministratedByRole', 'can_manage_all_users', 'can_create_root_folder', 'personal_folder', 'email', 'ga', 'fonction_id', 'mfa_enabled'];
$aSortTypes = ['asc', 'desc'];
//init SQL variables
$sWhere = $sOrder = $sLimit = '';
/* BUILD QUERY */
// Paging
$sLimit = '';
if (isset($params['length']) && (int) $params['length'] !== -1) {
    $start = filter_var($params['start'], FILTER_SANITIZE_NUMBER_INT);
    $length = filter_var($params['length'], FILTER_SANITIZE_NUMBER_INT);
    $sLimit = " LIMIT $start, $length";
}

// Ordering
$sOrder = '';
$order = $params['order'][0] ?? null;
if ($order && in_array($order['dir'], $aSortTypes)) {
    $sOrder = 'ORDER BY  ';
    if (isset($order['column']) && preg_match('#^(asc|desc)$#i', $order['dir'])) {
        $columnIndex = filter_var($order['column'], FILTER_SANITIZE_NUMBER_INT);
        $dir = filter_var($order['dir'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $sOrder .= $aColumns[$columnIndex] . ' ' . $dir . ', ';
    }

    $sOrder = substr_replace($sOrder, '', -2);
    if ($sOrder === 'ORDER BY') {
        $sOrder = '';
    }
}

/*
   * Filtering
   * NOTE this does not match the built-in DataTables filtering which does it
   * word by word on any field. It's possible to do here, but concerned about efficiency
   * on very large tables, and MySQL's regex functionality is very limited
*/

// exclude any deleted user
$sWhere = ' WHERE deleted_at IS NULL AND id NOT IN (9999991,9999997,9999998,9999999)';

$letter = $request->query->filter('letter', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$searchValue = isset($params['search']) && isset($params['search']['value']) ? filter_var($params['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

if ($letter !== '' && $letter !== 'None') {
    $sWhere .= ' AND (';
    $sWhere .= $aColumns[1] . " LIKE '" . $letter . "%' OR ";
    $sWhere .= $aColumns[2] . " LIKE '" . $letter . "%' OR ";
    $sWhere .= $aColumns[3] . " LIKE '" . $letter . "%' ";
    $sWhere .= ')';
} elseif ($searchValue !== '') {
    $sWhere .= ' AND (';
    $sWhere .= $aColumns[1] . " LIKE '" . $searchValue . "%' OR ";
    $sWhere .= $aColumns[2] . " LIKE '" . $searchValue . "%' OR ";
    $sWhere .= $aColumns[3] . " LIKE '" . $searchValue . "%' ";
    $sWhere .= ')';
}




// enlarge the query in case of Manager
if ((int) $session->get('user-admin') === 0
    && (int) $session->get('user-can_manage_all_users') === 0
) {
    $sWhere .= ' AND ';
    $arrUserRoles = array_filter($session->get('user-roles_array'));
    if (count($arrUserRoles) > 0) {
        $sWhere .= 'isAdministratedByRole IN ('.implode(',', $arrUserRoles).')';
    }
}

db::debugmode(false);
$rows = DB::query(
    'SELECT *
    FROM '.prefixTable('users').
    $sWhere
);
$iTotal = DB::count();
$rows = DB::query(
    'SELECT *,
        CASE
            WHEN pw LIKE "$2y$10$%" THEN 1
            ELSE 0
        END AS pw_passwordlib
    FROM '.prefixTable('users').
    $sWhere.
    $sLimit
);

// Output
$sOutput = '{';
$sOutput .= '"sEcho": '.(int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT).', ';
$sOutput .= '"iTotalRecords": '.$iTotal.', ';
$sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
$sOutput .= '"aaData": ';
if (DB::count() > 0) {
    $sOutput .= '[';
} else {
    $sOutput .= '';
}

foreach ($rows as $record) {
    //Show user only if can be administrated by the adapted Roles manager
    if ((int) $session->get('user-admin') === 1
        || in_array($record['isAdministratedByRole'], $session->get('user-roles_array'))
        || ((int) $session->get('user-can_manage_all_users') === 1 && (int) $record['admin'] === 0 && (int) $record['id'] !== (int) $session->get('user-id'))
    ) {
        $showUserFolders = true;
    } else {
        $showUserFolders = false;
    }

    // Display Grid
    if ($showUserFolders === true) {
        /*
        // Build list of available users
        if ((int) $record['admin'] !== 1 && (int) $record['disabled'] !== 1) {
            $listAvailableUsers .= '<option value="'.$record['id'].'">'.$record['login'].'</option>';
        }
        */

        // Get list of allowed functions
        $listAlloFcts = '';
        if ((int) $record['admin'] !== 1) {
            if (count($rolesList) > 0) {
                foreach ($rolesList as $fonction) {
                    if (is_null($record['fonction_id']) === false && in_array($fonction['id'], explode(';', $record['fonction_id']))) {
                        $listAlloFcts .= '<i class="fa-solid fa-angle-right mr-1"></i>'.addslashes(filter_var($fonction['title'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)).'<br />';
                    } else if (isset($SETTINGS['enable_ad_users_with_ad_groups']) === true && (int) $SETTINGS['enable_ad_users_with_ad_groups'] === 1 && is_null($record['roles_from_ad_groups']) === false && in_array($fonction['id'], explode(';', $record['roles_from_ad_groups']))) {
                        $listAlloFcts .= '<i class="fa-solid fa-angle-right mr-1"></i><i>'.addslashes(filter_var($fonction['title'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)).'</i><i class="fa-solid fa-rectangle-ad ml-1 infotip" title="'.$lang->get('ad_group').'"></i><br />';
                    }
                }
            }
            if (empty($listAlloFcts)) {
                $listAlloFcts = '<i class="fas fa-exclamation-triangle text-danger infotip" title="'.$lang->get('user_alarm_no_function').'"></i>';
            }
        }

        $userDate = DB::queryfirstrow(
            'SELECT date FROM '.prefixTable('log_system ').' WHERE type = %s AND field_1 = %i',
            'user_mngt',
            $record['id']
        );

        // Get some infos about user
        $userDisplayInfos = 
            (isset($userDate['date']) ? '<i class=\"fas fa-calendar-day infotip text-info ml-2\" title=\"'.$lang->get('creation_date').': '.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $userDate['date']).'\"></i>' : '')
            .
            ((int) $record['last_connexion'] > 0 ? '<i class=\"far fa-clock infotip text-info ml-2\" title=\"'.$lang->get('index_last_seen').": ".
            date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['last_connexion']).'\"></i>' : '')
            .
            ((int) $record['user_ip'] > 0 ? '<i class=\"fas fa-street-view infotip text-info ml-1\" title=\"'.$lang->get('ip').": ".($record['user_ip']).'\"></i>' : '')
            .
            ($record['auth_type'] === 'ldap' ? '<i class=\"far fa-address-book infotip text-warning ml-1\" title=\"'.$lang->get('managed_through_ad').'\"></i>' : '')
            .
            ((in_array($record['id'], [OTV_USER_ID, TP_USER_ID, SSH_USER_ID, API_USER_ID]) === false && (int) $record['admin'] !== 1 && ((int) $SETTINGS['duo'] === 1 || (int) $SETTINGS['google_authentication'] === 1)) ?
                ((int) $record['mfa_enabled'] === 1 ? '' : '<i class=\"fa-solid fa-fingerprint infotip ml-1\" style=\"color:Tomato\" title=\"'.$lang->get('mfa_disabled_for_user').'\"></i>') :
                ''
                );
        if ($request->query->filter('display_warnings', '', FILTER_VALIDATE_BOOLEAN) === true) {
            $userDisplayInfos .= '<br>'.
                ((in_array($record['id'], [OTV_USER_ID, TP_USER_ID, SSH_USER_ID, API_USER_ID]) === false && (int) $record['admin'] !== 1 && is_null($record['keys_recovery_time']) === true) ? 
                    '<i class=\"fa-solid fa-download infotip ml-1\" style=\"color:Tomato\" title=\"'.$lang->get('recovery_keys_not_downloaded').'\"></i>' :
                    ''
                ).
                ((in_array($record['id'], [OTV_USER_ID, TP_USER_ID, SSH_USER_ID, API_USER_ID]) === false && (int) $record['pw_passwordlib'] === 1) ? '<i class=\"fa-solid fa-person-walking-luggage infotip ml-1\" style=\"color:Tomato\" title=\"Old password encryption. Shall login to initialize.\"></i>' : '');
        }

        $sOutput .= '["<span data-id=\"'.$record['id'].'\" data-fullname=\"'.
            (empty($record['name']) === false ? htmlentities($record['name'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED) : '').' '.
            (empty($record['lastname']) === false ? htmlentities($record['lastname'], ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED) : '').
            '\" data-auth-type=\"'.$record['auth_type'].'\" data-special=\"'.$record['special'].'\" data-mfa-enabled=\"'.$record['mfa_enabled'].'\" data-otp-provided=\"'.(isset($record['otp_provided']) === true ? $record['otp_provided'] : '').'\"></span>", ';
        //col2
        $sOutput .= '"'.
            ((int) $record['disabled'] === 1 ? '<i class=\"fas fa-user-slash infotip text-danger mr-2\" title=\"'.$lang->get('account_is_locked').'\" id=\"user-disable-'.$record['id'].'\"></i>'
            : '').
            '<span data-id=\"'.$record['id'].'\" data-field=\"login\" data-html=\"true\" id=\"user-login-'.$record['id'].'\">'.addslashes(str_replace("'", '&lsquo;', $record['login'])).'</span>'.
            $userDisplayInfos.
            (is_null($record['ongoing_process_id']) === false ? '<i class=\"fas fa-hourglass-half fa-beat-fade infotip text-warning ml-3\" title=\"'.$lang->get('task_in_progress_user_not_active').'\"></i>' : '').
            '" , ';
        //col3
        $sOutput .= '"<span data-id=\"'.$record['id'].'\" data-field=\"name\" data-html=\"true\">'.addslashes($record['name'] === NULL ? '' : $record['name']).'</span>", ';
        //col4
        $sOutput .= '"<span data-id=\"'.$record['id'].'\" data-field=\"lastname\" data-html=\"true\">'.addslashes($record['lastname'] === NULL ? '' : $record['lastname']).'</span>", ';
        //col5 - MANAGED BY
        $txt = '<span id=\"managedby-'.$record['id'].'\" data-id=\"'.$record['id'].'\" data-field=\"isAdministratedByRole\" data-html=\"true\">';
        $rows2 = DB::query(
            'SELECT title
            FROM '.prefixTable('roles_title')."
            WHERE id = '".$record['isAdministratedByRole']."'
            ORDER BY title ASC"
        );
        if (DB::count() > 0) {
            foreach ($rows2 as $record2) {
                $txt .= $lang->get('managers_of').' '.addslashes(str_replace("'", '&lsquo;', $record2['title'])).'<br />';
            }
        } else {
            $txt .= $lang->get('god');
        }
        $sOutput .= '"'.$txt.'</span>", ';
        //col6
        $sOutput .= '"<span data-id=\"'.$record['id'].'\" data-field=\"fonction_id\" data-html=\"true\">'.addslashes($listAlloFcts).'</span>", ';
        // Get the user maximum privilege
        if ((int) $record['admin'] === 1) {
            $sOutput .= '"<i class=\"fa-solid fa-user-cog infotip\" title=\"'.$lang->get('god').'\"></i>", ';
        } elseif ((int) $record['can_manage_all_users'] === 1) {
            $sOutput .= '"<i class=\"fa-solid fa-user-graduate infotip\" title=\"'.$lang->get('human_resources').'\"></i>", ';
        } elseif ((int) $record['gestionnaire'] === 1) {
            $sOutput .= '"<i class=\"fa-solid fa-user-tie infotip\" title=\"'.$lang->get('gestionnaire').'\"></i>", ';
        } elseif ((int) $record['read_only'] === 1) {
            $sOutput .= '"<i class=\"fa-solid fa-book-reader infotip\" title=\"'.$lang->get('read_only_account').'\"></i>", ';
        } else {
            $sOutput .= '"<i class=\"fa-solid fa-user infotip\" title=\"'.$lang->get('user').'\"></i>", ';
        }
        //col12
        if ((int) $record['can_create_root_folder'] === 1) {
            $sOutput .= '"<i class=\"fa-solid fa-toggle-on text-info\"></i>", ';
        } else {
            $sOutput .= '"<i class=\"fa-solid fa-toggle-off\"></i>", ';
        }

        //col13
        if ((int) $record['personal_folder'] === 1) {
            $sOutput .= '"<i class=\"fa-solid fa-toggle-on text-info\"></i>"';
        } else {
            $sOutput .= '"<i class=\"fa-solid fa-toggle-off\"></i>"';
        }

        //Finish the line
        $sOutput .= '],';
    }
}

if (count($rows) > 0) {
    if (strrchr($sOutput, '[') !== '[') {
        $sOutput = substr_replace($sOutput, '', -1);
    }
    $sOutput .= ']';
} else {
    $sOutput .= '[]';
}

echo ($sOutput).'}';
