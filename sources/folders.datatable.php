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
 * @file      folders.datatable.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
session_name('teampass_session');
session_start();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('folders') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load language file
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$superGlobal->get('user_language', 'SESSION', 'user').'.php';

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Ensure Complexity levels are translated
if (defined('TP_PW_COMPLEXITY') === false) {
    define(
        'TP_PW_COMPLEXITY',
        [
            TP_PW_STRENGTH_1 => [TP_PW_STRENGTH_1, langHdl('complex_level1'), 'fas fa-thermometer-empty text-danger'],
            TP_PW_STRENGTH_2 => [TP_PW_STRENGTH_2, langHdl('complex_level2'), 'fas fa-thermometer-quarter text-warning'],
            TP_PW_STRENGTH_3 => [TP_PW_STRENGTH_3, langHdl('complex_level3'), 'fas fa-thermometer-half text-warning'],
            TP_PW_STRENGTH_4 => [TP_PW_STRENGTH_4, langHdl('complex_level4'), 'fas fa-thermometer-three-quarters text-success'],
            TP_PW_STRENGTH_5 => [TP_PW_STRENGTH_5, langHdl('complex_level5'), 'fas fa-thermometer-full text-success'],
        ]
    );
}

/*
//init SQL variables
$filterLetter = '';

// Filter
if (isset($_GET['letter']) === true
    && $_GET['letter'] !== ''
    && $_GET['letter'] !== 'None'
) {
    $filterLetter = filter_var($_GET['letter'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
*/

// Init search criteria
$searchCriteria = '';
// Filter
if (isset($_GET['search']) === true
    && $_GET['search']['value'] !== ''
) {
    $searchCriteria = filter_var($_GET['search']['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

//Build tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
$treeDesc = $tree->getDescendants();
/*
   * Output
*/

if (count($treeDesc) > 0) {
    $sOutput = '[';
} else {
    $sOutput = '';
}

$x = 0;
$arr_ids = [];
foreach ($treeDesc as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) === true
        && in_array($t->id, $_SESSION['personal_visible_groups']) === false
        && $t->personal_folder === 0
    ) {
        // get $t->parent_id
        $data = DB::queryFirstRow('SELECT title FROM '.prefixTable('nested_tree').' WHERE id = %i', $t->parent_id);
        if ($t->nlevel === 1) {
            $data['title'] = langHdl('root');
        }

        // get rights on this folder
        $tab_droits = [];
        $rows = DB::query('SELECT fonction_id  FROM '.prefixTable('rights').' WHERE authorized=%i AND tree_id = %i', 1, $t->id);
        foreach ($rows as $record) {
            array_push($tab_droits, $record['fonction_id']);
        }

        $arbo = $tree->getPath($t->id, false);
        $path = '';
        $parentClass = [];
        foreach ($arbo as $elem) {
            if (empty($path) === true) {
                $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
            } else {
                $path .= '<i class=\"fa fa-angle-right m-1\"></i>'.htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
            }
            array_push($parentClass, 'p'.$elem->id);
        }

        // Get some elements from DB concerning this node
        $node_data = DB::queryFirstRow(
            'SELECT m.valeur AS valeur, n.renewal_period AS renewal_period,
            n.bloquer_creation AS bloquer_creation, n.bloquer_modification AS bloquer_modification
            FROM '.prefixTable('misc').' AS m,
            '.prefixTable('nested_tree').' AS n
            WHERE m.type=%s AND m.intitule = n.id AND m.intitule = %i',
            'complex',
            $t->id
        );
        // If filter on letter
        if (empty($searchCriteria) === true
            || strpos(strtolower($t->title), strtolower($searchCriteria)) !== false
        ) {
            // start the line
            $sOutput .= '[';
            
            //col1
            if (($t->parent_id === 0
                && ($_SESSION['is_admin'] === 1 || $_SESSION['can_create_root_folder'] === 1))
                || $t->parent_id !== 0
            ) {
                $sOutput .= '"<input type=\"checkbox\" class=\"cb_selected_folder\" data-id=\"'.$t->id.'\" id=\"checkbox-'.$t->id.'\" data-row=\"'.$x.'\" />';
                if ($tree->numDescendants($t->id) > 0) {
                    $sOutput .= '<i class=\"fa fa-folder-minus fa-sm infotip ml-2 pointer icon-collapse\" data-id=\"'.$t->id.'\" title=\"'.langHdl('edit').'\"></i>';
                }

                $sOutput .= '"';
            } else {
                $sOutput .= '""';
            }
            $sOutput .= ',';
            //col2
            $sOutput .= '"<span id=\"folder-'.$t->id.'\" data-id=\"'.$t->id.'\" class=\"infotip edit-text field-title pointer\" data-html=\"true\" title=\"'.langHdl('id').': '.$t->id.'<br>'.langHdl('level').': '.$t->nlevel.'<br>'.langHdl('nb_items').': '.DB::count().'\">'.addslashes(str_replace("'", '&lsquo;', $t->title)).'</span>"';
            $sOutput .= ',';
            //col3 - PARENT
            $sOutput .= '"<small class=\"text-muted ml-1\">'.$path.'</small>"';
            $sOutput .= ',';
            //col4
            if (isset(TP_PW_COMPLEXITY[$node_data['valeur']][1]) === true) {
                $sOutput .= '"<span data-id=\"'.$t->id.'\" id=\"c'.$t->id.'\" class=\"infotip edit-select field-complex pointer\" title=\"'.TP_PW_COMPLEXITY[$node_data['valeur']][1].'\"  data-value=\"'.TP_PW_COMPLEXITY[$node_data['valeur']][0].'\"><i class=\"'.addslashes(TP_PW_COMPLEXITY[$node_data['valeur']][2]).'\"></i></span>"';
            } else {
                $sOutput .= '""';
            }
            $sOutput .= ',';
            //col6
            $sOutput .= '"<span  data-id=\"'.$t->id.'\" class=\"edit-text field-renewal pointer\">'.$node_data['renewal_period'].'</span>"';
            $sOutput .= ',';
            //col7
            $data7 = DB::queryFirstRow(
                'SELECT bloquer_creation,bloquer_modification
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                intval($t->id)
            );
            if (isset($data7['bloquer_creation']) && $data7['bloquer_creation'] === 1) {
                $sOutput .= '"<i class=\"fa fa-toggle-on text-info pointer toggle\" data-id=\"'.$t->id.'\" data-set=\"0\" data-type=\"create_without_strength_check\"></i>"';
            } else {
                $sOutput .= '"<i class=\"fa fa-toggle-off pointer toggle\"  data-id=\"'.$t->id.'\" data-set=\"1\" data-type=\"create_without_strength_check\"></i>"';
            }
            $sOutput .= ',';
            //col8
            if (isset($data7['bloquer_modification']) && $data7['bloquer_modification'] === 1) {
                $sOutput .= '"<i class=\"fa fa-toggle-on text-info pointer toggle\" data-id=\"'.$t->id.'\" data-set=\"0\" data-type=\"edit_without_strength_check\"></i>';
            } else {
                $sOutput .= '"<i class=\"fa fa-toggle-off pointer toggle\" data-id=\"'.$t->id.'\" data-set=\"1\" data-type=\"edit_without_strength_check\"></i>';
            }
            $sOutput .= '<input type=\"hidden\" id=\"parent_id_'.$t->id.'\" value=\"'.$t->parent_id.'\" /><input type=\"hidden\"  id=\"renewal_id_'.$t->id.'\" value=\"'.$node_data['valeur'].'\" /><input type=\"hidden\"  id=\"block_creation_'.$t->id.'\" value=\"'.$node_data['bloquer_creation'].'\" /><input type=\"hidden\"  id=\"block_modif_'.$t->id.'\" value=\"'.$node_data['bloquer_modification'].'\" />'.
            '<input type=\"hidden\" id=\"row-class-'.$x.'\" value=\"'.implode(' ', $parentClass).'\">"';
            //Finish the line
            $sOutput .= '],';
            array_push($arr_ids, $t->id);
            ++$x;
        }
    }
}

if (count($treeDesc) > 0) {
    if (strrchr($sOutput, '[') !== '[') {
        $sOutput = substr_replace($sOutput, '', -1);
    }
    $sOutput .= ']}';
} else {
    $sOutput .= '[] }';
}

// finalize output
echo '{"recordsTotal": '.$x.', "recordsFiltered": '.$x.', "data": '.$sOutput;
