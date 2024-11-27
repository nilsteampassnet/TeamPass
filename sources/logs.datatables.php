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
 * @file      logs.datatables.php
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
    $checkUserAccess->userAccessPage('utilities.logs') === false ||
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

// Get the data
$params = $request->query->all();

// Init
$searchValue = $sWhere = $sOrder = '';
$aSortTypes = ['ASC', 'DESC'];
$sLimitStart = $request->query->has('start') 
    ? $request->query->filter('start', 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]) 
    : 0;
$sLimitLength = $request->query->has('length') 
    ? $request->query->filter('length', 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]) 
    : 10;

// Check search parameters
if (isset($params['search']['value'])) {
    // Case 1: search[value]
    $searchValue = (string) $params['search']['value'];
} elseif (isset($params['sSearch'])) {
    // Case 2: sSearch
    $searchValue = (string) $params['sSearch'];
}

// Ordering
$order = strtoupper($params['order'][0]['dir'] ?? null);
$orderDirection = in_array($order, $aSortTypes, true) ? $order : 'DESC';
    
// Start building the query and output depending on the action
if (isset($params['action']) && $params['action'] === 'connections') {
    //Columns name
    $aColumns = ['l.date', 'l.label', 'l.qui', 'u.login', 'u.name', 'u.lastname'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }
    
    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('l.type = %s', 'user_connection');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) 
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT l.date as date, l.label as label, l.qui as who, 
    u.login as login, u.name AS name, u.lastname AS lastname
    FROM '.prefixTable('log_system').' as l
    INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();
    
    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.str_replace([chr(10), chr(13)], [' ', ' '], htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES)).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

    /* ERRORS LOG */
} elseif (isset($params['action']) && $params['action'] === 'access') {
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('l.action = %s', 'at_shown');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_items').' as l
        INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT l.date as date, u.login as login, i.label as label
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.str_replace([chr(10), chr(13)], [' ', ' '], htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES)).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

    /* COPY LOG */
} elseif (isset($params['action']) && $params['action'] === 'copy') {
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }
    
    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('l.action = %s', 'at_copy');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_items').' as l
        INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT l.date as date, u.login as login, u.name AS name, u.lastname AS lastname, i.label as label
    FROM '.prefixTable('log_items').' as l
    INNER JOIN '.prefixTable('items').' as i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (l.id_user=u.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.trim(htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES)).'", ';
        //col3
        $sOutput .= '"'.trim(htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES)).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }

    /*
    * ADMIN LOG
     */
} elseif (isset($params['action']) && $params['action'] === 'admin') {
    //Columns name
    $aColumns = ['l.date', 'u.login', 'l.label', 'l.field_1'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('l.type IN %ls', ['admin_action', 'user_mngt']);

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_system').' as l
        INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id)
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT l.date as date, u.login as login, u.name AS name, u.lastname AS lastname, l.label as label, l.field_1 as field_1
    FROM '.prefixTable('log_system').' as l
    INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);    
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';
    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = '[';
        //col1
        $sOutput_item .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput_item .= '"'.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).'", ';
        //col3
        if ($record['label'] === 'at_user_added') {
            $cell = $lang->get('user_creation');
        } elseif ($record['label'] === 'at_user_deleted' || $record['label'] === 'user_deleted') {
            $cell = $lang->get('user_deletion');
        } elseif ($record['label'] === 'at_user_updated') {
            $cell = $lang->get('user_updated');
        } elseif (strpos($record['label'], 'at_user_email_changed') !== false) {
            $change = explode(':', $record['label']);
            $cell = $lang->get('log_user_email_changed').' '.$change[1];
        } elseif ($record['label'] === 'at_user_new_keys') {
            $cell = $lang->get('new_keys_generated');
        } elseif ($record['label'] === 'at_user_keys_download') {
            $cell = $lang->get('user_keys_downloaded');
        } elseif ($record['label'] === 'at_2fa_google_code_send_by_email') {
            $cell = $lang->get('mfa_code_send_by_email');
        } else {
            $cell = htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES);
        }
        $sOutput_item .= '"'.$cell.'" ';
        //col4
        if (empty($record['field_1']) === false) {
            // get user name
            $info = DB::queryfirstrow(
                'SELECT u.login as login, u.name AS name, u.lastname AS lastname
                    FROM '.prefixTable('users').' as u
                    WHERE u.id = %i',
                    $record['field_1']
            );
            $sOutput_item .= ', "'.(empty($info['name']) === false ? htmlspecialchars(stripslashes((string) $info['name'].' '.$info['lastname']), ENT_QUOTES) : 'Removed user ('.$record['field_1'].')').'" ';
        } else {
            $sOutput_item .= ', "" ';
        }
        //Finish the line
        $sOutput_item .= '], ';
        if ($get_item_in_list === true) {
            $sOutput .= $sOutput_item;
        }
    }
    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, '', -2);
    }
    $sOutput .= '] }';
/* ITEMS */
} elseif (isset($params['action']) && $params['action'] === 'items') {
    require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
    //Columns name
    $aColumns = ['l.date', 'i.label', 'u.login', 'l.action', 'i.perso', 'i.id', 't.title'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('OR');
    if ($searchValue !== '') {        
        foreach ($aColumns as $column) {
            $sWhere->add($column.' LIKE %ss', $searchValue);
        }
    }

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_items').' AS l
        INNER JOIN '.prefixTable('items').' AS i ON (l.id_item=i.id)
        INNER JOIN '.prefixTable('users').' AS u ON (l.id_user=u.id)
        INNER JOIN '.prefixTable('nested_tree').' AS t ON (i.id_tree=t.id)
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT l.date AS date, u.login AS login, u.name AS name, u.lastname AS lastname, i.label AS label,
    i.perso AS perso, l.action AS action, t.title AS folder, i.id AS id
    FROM '.prefixTable('log_items').' AS l
    INNER JOIN '.prefixTable('items').' AS i ON (l.id_item=i.id)
    INNER JOIN '.prefixTable('users').' AS u ON (l.id_user=u.id)
    INNER JOIN '.prefixTable('nested_tree').' AS t ON (i.id_tree=t.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": [ ';
    foreach ($rows as $record) {
        $get_item_in_list = true;
        $sOutput_item = '[';
        //col1
        $sOutput_item .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col3
        $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes((string) $record['id']), ENT_QUOTES)).'", ';
        //col3
        $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES)).'", ';
        //col2
        $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes((string) $record['folder']), ENT_QUOTES)).'", ';
        //col2
        $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES)).' '.trim(htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES)).' ['.trim(htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES)).']", ';
        //col4
        $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes($lang->get($record['action'])), ENT_QUOTES)).'", ';
        //col5
        if ($record['perso'] === 1) {
            $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes($lang->get('yes')), ENT_QUOTES)).'"';
        } else {
            $sOutput_item .= '"'.trim(htmlspecialchars(stripslashes($lang->get('no')), ENT_QUOTES)).'"';
        }

        //Finish the line
        $sOutput_item .= '], ';
        if ($get_item_in_list === true) {
            $sOutput .= $sOutput_item;
        }
    }
    if ($iFilteredTotal > 0) {
        $sOutput = substr_replace($sOutput, '', -2);
    }
    $sOutput .= '] }';
/* FAILED AUTHENTICATION */
} elseif (isset($params['action']) && $params['action'] === 'failed_auth') {
    //Columns name
    $aColumns = ['l.date', 'l.label', 'l.qui', 'l.field_1'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('l.type = %s', 'failed_auth');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('log_system').' as l
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT l.date as auth_date, l.label as label, l.qui as who, l.field_1
    FROM '.prefixTable('log_system').' as l
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count(); 

    // Output
    if ($iTotal === '') {
        $iTotal = 0;
    }
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['auth_date']).'", ';
        //col2 - 3
        if ($record['label'] === 'password_is_not_correct' || $record['label'] === 'user_not_exists') {
            $sOutput .= '"'.$lang->get($record['label']).'", "'.$record['field_1'].'", ';
        } else {
            $sOutput .= '"'.$lang->get($record['label']).'", "", ';
        }

        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['who']), ENT_QUOTES).'"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'errors') {
    //Columns name
    $aColumns = ['l.date', 'l.label', 'l.qui', 'u.login', 'u.name', 'u.lastname'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('l.type = %s', 'error');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
            FROM '.prefixTable('log_system').' as l
            INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) 
            WHERE %l ORDER BY %l %l',
            $sWhere,
            $orderColumn,
            $orderDirection
    );
    $iTotal = DB::count();

    // Prepare the SQL query
    $sql = 'SELECT l.date as date, l.label as label, l.qui as who,
    u.login as login, u.name AS name, u.lastname AS lastname
    FROM '.prefixTable('log_system').' as l
    INNER JOIN '.prefixTable('users').' as u ON (l.qui=u.id) 
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"'.date($SETTINGS['date_format'].' '.$SETTINGS['time_format'], (int) $record['date']).'", ';
        //col2
        $sOutput .= '"'.addslashes(str_replace([chr(10), chr(13), '`', '<br />@', "'"], ['<br>', '<br>', "'", '', '&#39;'], $record['label'])).'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'items_in_edition') {
    //Columns name
    $aColumns = ['e.timestamp', 'u.login', 'i.label', 'u.name', 'u.lastname'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('OR');
    if ($searchValue !== '') {        
        foreach ($aColumns as $column) {
            $sWhere->add($column.' LIKE %ss', $searchValue);
        }
    }

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('items_edition').' AS e
        INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
        INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id)
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT e.timestamp, e.item_id, e.user_id, u.login, u.name, u.lastname, i.label
    FROM '.prefixTable('items_edition').' AS e
    INNER JOIN '.prefixTable('items').' as i ON (e.item_id=i.id)
    INNER JOIN '.prefixTable('users').' as u ON (e.user_id=u.id)
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-id=\"'.$record['item_id'].'\">", ';
        //col2
        $time_diff = intval(time() - $record['timestamp']);
        $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
        $minutesDiffRemainder = floor($time_diff % 3600 / 60);
        $sOutput .= '"'.$hoursDiff.'h '.$minutesDiffRemainder.'m'.'", ';
        //col3
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']", ';
        //col5 - TAGS
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['label']), ENT_QUOTES).' ['.$record['item_id'].']"';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'users_logged_in') {
    //Columns name
    $aColumns = ['login', 'name', 'lastname', 'timestamp', 'last_connexion'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }    

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $subclause2 = $sWhere->addClause('OR');
    $subclause2->add('timestamp = ""');
    $subclause2->add('session_end >= %i', time());

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('users').'
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT *
    FROM '.prefixTable('users').'
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = [$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-id=\"'.$record['id'].'\">", ';
        //col2
        $sOutput .= '"'.htmlspecialchars(stripslashes((string) $record['name']), ENT_QUOTES).' '.htmlspecialchars(stripslashes((string) $record['lastname']), ENT_QUOTES).' ['.htmlspecialchars(stripslashes((string) $record['login']), ENT_QUOTES).']", ';
        //col3
        if ($record['admin'] === '1') {
            $user_role = $lang->get('god');
        } elseif ($lang->get('gestionnaire') === 1) {
            $user_role = $lang->get('gestionnaire');
        } else {
            $user_role = $lang->get('user');
        }
        $sOutput .= '"'.$user_role.'", ';
        //col4
        $time_diff = time() - strtotime($record['timestamp']);
        $hoursDiff = round($time_diff / 3600, 0, PHP_ROUND_HALF_DOWN);
        $minutesDiffRemainder = floor($time_diff % 3600 / 60);
        $sOutput .= '"'.$hoursDiff.'h '.$minutesDiffRemainder.'m" ';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'tasks_in_progress') {
    //Columns name
    $aColumns = ['p.increment_id', 'p.created_at', 'p.updated_at', 'p.process_type', 'p.is_in_progress'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }    

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $subclause2 = $sWhere->addClause('OR');
    $subclause2->add('p.finished_at = ""');
    $subclause2->add('p.finished_at IS NULL');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('background_tasks').' AS p 
        LEFT JOIN '.prefixTable('users').' AS u ON %l
        WHERE %l ORDER BY %l %l',
        'u.id = json_extract(p.arguments, "$[0]")',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT p.increment_id, p.created_at, p.updated_at, p.process_type,
                p.is_in_progress, p.arguments
            FROM '.prefixTable('background_tasks').' AS p 
            LEFT JOIN '.prefixTable('users').' AS u ON %l
            WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = ['u.id = json_extract(p.arguments, "$[0]")',$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        // Get subtask progress
        $subtaskProgress = getSubtaskProgress($record['increment_id']);        

        $sOutput .= '[';
        //col1
        $sOutput .= '"<span data-done=\"'.$record['is_in_progress'].'\" data-type=\"'.$record['process_type'].'\" data-process-id=\"'.$record['increment_id'].'\"></span>", ';
        //col2
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ';
        //col3
        //$sOutput .= '"'.($record['updated_at'] === '' ? '-' : date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['updated_at'])).'", ';
        $sOutput .= '"<div class=\"progress mt-2\"><div class=\"progress-bar\" style=\"width: '.$subtaskProgress.'\">'.$subtaskProgress.'</div></div>", ';
        //col4
        $sOutput .= '"'.$record['process_type'].'", ';

        // col5
        if (in_array($record['process_type'], array('create_user_keys', 'item_copy')) === true) {
            $data_user = DB::queryfirstrow(
                'SELECT name, lastname FROM ' . prefixTable('users') . '
                WHERE id = %i',
                json_decode($record['arguments'], true)['new_user_id']
            );
            $sOutput .= '"'.$data_user['name'].' '.$data_user['lastname'].'", ';
        } elseif ($record['process_type'] === 'send_email') {
            $sOutput .= '"'.json_decode($record['arguments'], true)['receiver_name'].'", ';
        }
        // col6
        $sOutput .= '""';
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
} elseif (isset($params['action']) && $params['action'] === 'tasks_finished') {
    //Columns name
    $aColumns = ['p.created_at', 'p.finished_at', 'p.process_type', 'u.name'];

    // Ordering
    $orderColumn = $aColumns[0];
    if (isset($aColumns[$params['order'][0]['column']]) === true) {
        $orderColumn = $aColumns[$params['order'][0]['column']];
    }

    // Filtering
    $sWhere = new WhereClause('AND');
    if ($searchValue !== '') {        
        $subclause = $sWhere->addClause('OR');
        foreach ($aColumns as $column) {
            $subclause->add($column.' LIKE %ss', $searchValue);
        }
    }
    $sWhere->add('finished_at != ""');

    // Get the total number of records
    $iTotal = DB::queryFirstField(
        'SELECT COUNT(*)
        FROM '.prefixTable('background_tasks').' AS p 
        LEFT JOIN '.prefixTable('users').' AS u ON u.id = json_extract(p.arguments, "$[0]")
        WHERE %l ORDER BY %l %l',
        $sWhere,
        $orderColumn,
        $orderDirection
    );

    // Prepare the SQL query
    $sql = 'SELECT p.*
    FROM '.prefixTable('background_tasks').' AS p 
    LEFT JOIN '.prefixTable('users').' AS u ON %l
    WHERE %l ORDER BY %l %l LIMIT %i, %i';
    $params = ['u.id = json_extract(p.arguments, "$[0]")',$sWhere, $orderColumn, $orderDirection, $sLimitStart, $sLimitLength];

    // Get the records
    $rows = DB::query($sql, ...$params);
    $iFilteredTotal = DB::count();

    // Output
    $sOutput = '{';
    $sOutput .= '"sEcho": '. (int) $request->query->filter('draw', FILTER_SANITIZE_NUMBER_INT) . ', ';
    $sOutput .= '"iTotalRecords": '.$iTotal.', ';
    $sOutput .= '"iTotalDisplayRecords": '.$iTotal.', ';
    $sOutput .= '"aaData": ';
    if ($iFilteredTotal > 0) {
        $sOutput .= '[';
    }
    foreach ($rows as $record) {
        // play with dates
        $start = strtotime(date('Y-m-d H:i:s', (int) $record['created_at']));
        $end = strtotime(date('Y-m-d H:i:s', (int) $record['finished_at']));
        
        $sOutput .= '[';
        //col1
        $sOutput .= '"", ';
        //col2
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ';
        //col3
        $sOutput .= is_null($record['started_at']) === false ?
            ('"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['started_at']).'", ') :
                ('"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['created_at']).'", ');
        //col4
        $sOutput .= '"'.date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $record['finished_at']).'", ';
        // col7
        $sOutput .= '"'.gmdate('H:i:s', (int) $record['finished_at'] - (is_null($record['started_at']) === false ? (int) $record['started_at'] : (int) $record['created_at'])).'",';
        //col5
        if ($record['process_type'] === 'create_user_keys') {
            $processIcon = '<i class=\"fa-solid fa-user-gear infotip\" title=\"'.$lang->get('user_creation').'\"></i>';
        } else if ($record['process_type'] === 'send_email') {
            $processIcon = '<i class=\"fa-solid fa-envelope-circle-check infotip\" title=\"'.$lang->get('send_email_to_user').'\"></i>';
        } else if ($record['process_type'] === 'user_build_cache_tree') {
            $processIcon = '<i class=\"fa-solid fa-folder-tree infotip\" title=\"'.$lang->get('reload_user_cache_table').'\"></i>';
        } else {
            $processIcon = '<i class=\"fa-solid fa-question\"></i> ('.$record['process_type'].')';
        }
        $sOutput .= '"'.$processIcon.'", ';
        // col6
        $arguments = json_decode($record['arguments'], true);
        $newUserId = array_key_exists('new_user_id', $arguments) ? $arguments['new_user_id'] : null;
        if ($record['process_type'] === 'create_user_keys' && is_null($newUserId) === false && empty($newUserId) === false) {
            $data_user = DB::queryfirstrow(
                'SELECT name, lastname, login FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $newUserId
            );
            if (DB::count() > 0) {
                $txt = (isset($data_user['name']) === true ? $data_user['name'] : '').(isset($data_user['lastname']) === true ? ' '.$data_user['lastname'] : '');
                $sOutput .= '"'.(empty($txt) === false ? $txt : $data_user['login']).'"';
            } else {
                $sOutput .= '"<i class=\"fa-solid fa-user-slash\"></i>"';
            }
        } elseif ($record['process_type'] === 'send_email') {
            $user = json_decode($record['arguments'], true)['receiver_name'];
            $sOutput .= '"'.(is_null($user) === true || empty($user) === true ? '<i class=\"fa-solid fa-user-slash\"></i>' : $user).'"';
        } elseif ($record['process_type'] === 'user_build_cache_tree') {
            $user = json_decode($record['arguments'], true)['user_id'];
            $data_user = DB::queryfirstrow(
                'SELECT name, lastname, login FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $user
            );
            if (DB::count() > 0) {
                $txt = (isset($data_user['name']) === true ? $data_user['name'] : '').(isset($data_user['lastname']) === true ? ' '.$data_user['lastname'] : '');
                $sOutput .= '"'.(empty($txt) === false ? $txt : $data_user['login']).'"';
            } else {
                $sOutput .= '"<i class=\"fa-solid fa-user-slash\"></i>"';
            }
        } else {
            $sOutput .= '"<i class=\"fa-solid fa-user-slash\"></i>"';
        }
        //Finish the line
        $sOutput .= '],';
    }

    if (count($rows) > 0) {
        $sOutput = substr_replace($sOutput, '', -1);
        $sOutput .= '] }';
    } else {
        $sOutput .= '[] }';
    }
}

// deepcode ignore XSS: data comes from database. Before being stored it is clean with feature antiXss->xss_clean
echo (string) $sOutput;



function getSubtaskProgress($id)
{
    $subtasks = DB::query(
        'SELECT *
        FROM ' . prefixTable('background_subtasks') . '
        WHERE task_id = %i',
        $id
    );

    $i = 0;
    $nb = count($subtasks);
    $finished_nb = 0;
    foreach ($subtasks as $task) {
        if (is_null($task['finished_at']) === false) {
            $finished_nb++;
        }

        $i++;
    }

    return ($finished_nb !== 0 ? pourcentage($finished_nb, $nb, 100) : 0) .'%';
}