<?php
/**
 *
 * @file          folders.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link	      http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include 'error.php';
    exit();
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

/* load help*/
require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'_admin_help.php';

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

/* Get full tree structure */
$tst = $tree->getDescendants();

/* Build list of all folders */
if ($_SESSION['is_admin'] == 1 || $_SESSION['settings']['can_create_root_folder'] == 1) {
    $folders_list = "\'0\':\'".$txt['root']."\'";
} else {
    $folders_list = "";
}
$ident = "";
foreach ($tst as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
        if ($t->nlevel == 1) {
            $ident = ">";
        }
        if ($t->nlevel == 2) {
            $ident = "->";
        }
        if ($t->nlevel == 3) {
            $ident = "-->";
        }
        if ($t->nlevel == 4) {
            $ident = "--->";
        }
        if ($t->nlevel == 5) {
            $ident = "---->";
        }
        $folders_list .= ','."\'".$t->id.'\':\''.$ident." ".addslashes(addslashes($t->title))."\'";
    }
}

/* Display header */
echo '
<div class="title ui-widget-content ui-corner-all">' .
$txt['admin_groups'].'&nbsp;&nbsp;&nbsp;<img src="includes/images/folder--plus.png" id="open_add_group_div" title="'.$txt['item_menu_add_rep'].'" style="cursor:pointer;" />
    <span style="float:right;margin-right:5px;"><img src="includes/images/question-white.png" style="cursor:pointer" title="'.$txt['show_help'].'" onclick="OpenDialog(\'help_on_folders\')" /></span>
</div>';
// Hidden things
echo '
<input type="hidden" id="folder_id_to_edit" value="" />';

echo '
<form name="form_groupes" method="post" action="">
    <div style="width:700px;margin:auto; line-height:20px;">
    <table cellspacing="0" style="margin-top:10px;">
        <thead><tr>
            <th>ID</th>
            <th>'.$txt['group'].'</th>
            <th>'.$txt['complexity'].'</th>
            <th>'.$txt['group_parent'].'</th>
            <th>'.$txt['level'].'</th>
            <th title="'.$txt['group_pw_duration_tip'].'">'.$txt['group_pw_duration'].'</th>
            <th title="'.$txt['del_group'].'"><img src="includes/images/folder--minus.png" /></th>
            <th title="'.$txt['auth_creation_without_complexity'].'"><img src="includes/images/auction-hammer.png" /></th>
            <th title="'.$txt['auth_modification_without_complexity'].'"><img src="includes/images/alarm-clock.png" /></th>
        </tr></thead>
        <tbody>';
$x = 0;
$arr_ids = array();
foreach ($tst as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
        // r?cup $t->parent_id
        //$data = $db->fetchRow("SELECT title FROM ".$pre."nested_tree WHERE id = ".$t->parent_id);
        $data = $db->queryGetRow(
            "nested_tree",
            array(
                "title"
            ),
            array(
                "id" => intval($t->parent_id)
            )
        );
        if ($t->nlevel == 1) {
            $data[0] = $txt['root'];
        }
        // r?cup les droits associ?s ? ce groupe
        $tab_droits = array();
        $rows = $db->fetchAllArray("SELECT fonction_id  FROM ".$pre."rights WHERE authorized=1 AND tree_id = ".$t->id);
        foreach ($rows as $reccord) {
            array_push($tab_droits, $reccord['fonction_id']);
        }
        // g?rer l'identation en fonction du niveau
        $ident = "";
        for ($l = 1; $l < $t->nlevel; $l++) {
            $ident .= "&nbsp;&nbsp;";
        }
        // Get some elements from DB concerning this node
        /*$node_data = $db->fetchRow(
            "SELECT m.valeur as valeur, n.renewal_period as renewal_period
            FROM ".$pre."misc as m,
            ".$pre."nested_tree as n
            WHERE m.type='complex'
            AND m.intitule = n.id
            AND m.intitule = ".$t->id
        );*/
        $node_data = $db->queryGetRow(
            array(
                "misc" => "m",
                "nested_tree" => "n"
            ),
            array(
                "m.valeur" => "valeur",
                "n.renewal_period" => "renewal_period"
            ),
            array(
                "m.type" => "complex",
                "m.intitule" => intval(n.id),
                "m.intitule" => intval($t->id)
            )
        );

        echo '
                <tr class="ligne0" id="row_'.$t->id.'">
                    <td align="center" onclick="open_edit_folder_dialog('.$t->id.')">'.$t->id.'</td>
                    <td width="50%" onclick="open_edit_folder_dialog('.$t->id.')">
                        '.$ident.'<span id="title_'.$t->id.'">'.$t->title.'</span>
                    </td>
                    <td align="center" onclick="open_edit_folder_dialog('.$t->id.')">
                        <span id="complexite_'.$t->id.'">'.@$pwComplexity[$node_data[0]][1].'</span>
                    </td>
                    <td align="center" onclick="open_edit_folder_dialog('.$t->id.')">
                        <span id="parent_'.$t->id.'">'.$data[0].'</span>
                    </td>
                    <td align="center" onclick="open_edit_folder_dialog('.$t->id.')">
                        '.$t->nlevel.'
                    </td>
                    <td align="center" onclick="open_edit_folder_dialog('.$t->id.')">
                        <span id="renewal_'.$t->id.'">'.$node_data[1].'</span>
                    </td>
                    <td align="center">
                        <img src="includes/images/folder--minus.png" onclick="supprimer_groupe(\''.$t->id.'\')" style="cursor:pointer;" />
                    </td>';

        //$data3 = $db->fetchRow("SELECT bloquer_creation,bloquer_modification FROM ".$pre."nested_tree WHERE id = ".$t->id);
        $data3 = $db->queryGetRow(
            array(
                "bloquer_creation",
                "bloquer_modification"
            ),
            "nested_tree",
            array(
                "id" => intval($t->id)
            )
        );
        echo '
                    <td align="center">
                        <input type="checkbox" id="cb_droit_'.$t->id.'" onchange="Changer_Droit_Complexite(\''.$t->id.'\',\'creation\')"', isset($data3[0]) && $data3[0] == 1 ? 'checked' : '', ' />
                    </td>
                    <td align="center">
                        <input type="checkbox" id="cb_droit_modif_'.$t->id.'" onchange="Changer_Droit_Complexite(\''.$t->id.'\',\'modification\')"', isset($data3[1]) && $data3[1] == 1 ? 'checked' : '', ' />
                    </td>
                    <td>
                        <input type="hidden"  id="parent_id_'.$t->id.'" value="'.$t->parent_id.'" />
                        <input type="hidden"  id="renewal_id_'.$t->id.'" value="'.$node_data[0].'" />
                    </td>
                </tr>';
        array_push($arr_ids, $t->id);
        $x++;
    }
}
echo '
        </tbody>
    </table>
    <div style="font-size:11px;font-style:italic;margin-top:5px;">
        <img src="includes/images/information-white.png" alt="" />&nbsp;'.$txt['info_click_to_edit'].'
    </div>
    </div>
</form>';
// DIV FOR HELP
echo '
<div id="help_on_folders" style="">
    <div>'.$txt['help_on_folders'].'</div>
</div>';

/* Form Add a folder */
echo '
<div id="div_add_group" style="display:none;">
    <div id="addgroup_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>

    <label for="ajouter_groupe_titre" class="label_cpm">'.$txt['group_title'].' :</label>
    <input type="text" id="ajouter_groupe_titre" class="input_text text ui-widget-content ui-corner-all" />

    <label for="parent_id" class="label_cpm">'.$txt['group_parent'].' :</label>
    <select id="parent_id" class="input_text text ui-widget-content ui-corner-all">';
echo '<option value="na">---'.$txt['select'].'---</option>';
if ($_SESSION['is_admin'] == 1 || $_SESSION['can_create_root_folder'] == 1) {
    echo '<option value="0">'.$txt['root'].'</option>';
}
$prev_level = 0;
foreach ($tst as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
        $ident = "";
        for ($x = 1; $x < $t->nlevel; $x++) {
            $ident .= "&nbsp;&nbsp;";
        }
        if ($prev_level < $t->nlevel) {
            echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
        } elseif ($prev_level == $t->nlevel) {
            echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
        } else {
            echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
        }
        $prev_level = $t->nlevel;
    }
}
echo '
    </select>

    <label for="new_rep_complexite" class="label_cpm">'.$txt['complex_asked'].' :</label>
    <select id="new_rep_complexite" class="input_text text ui-widget-content ui-corner-all">';
foreach ($pwComplexity as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>

    <label for="add_node_renewal_period" class="label_cpm">'.$txt['group_pw_duration'].' :</label>
    <input type="text" id="add_node_renewal_period" value="0" class="input_text text ui-widget-content ui-corner-all" />
</div>';

/* Form EDIT a folder */
echo '
<div id="div_edit_folder" style="display:none;">
    <div id="edit_folder_show_error" style="text-align:center;margin:2px;display:none;" class="ui-state-error ui-corner-all"></div>

    <label for="edit_folder_title" class="label_cpm">'.$txt['group_title'].' :</label>
    <input type="text" id="edit_folder_title" class="input_text text ui-widget-content ui-corner-all" />

    <label for="edit_parent_id" class="label_cpm">'.$txt['group_parent'].' :</label>
    <select id="edit_parent_id" class="input_text text ui-widget-content ui-corner-all">';
echo '<option value="na">---'.$txt['select'].'---</option>';
if ($_SESSION['is_admin'] == 1 || $_SESSION['can_create_root_folder'] == 1) {
    echo '<option value="0">'.$txt['root'].'</option>';
}
$prev_level = 0;
foreach ($tst as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
        $ident = "";
        for ($x = 1; $x < $t->nlevel; $x++) {
            $ident .= "&nbsp;&nbsp;";
        }
        if ($prev_level < $t->nlevel) {
            echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
        } elseif ($prev_level == $t->nlevel) {
            echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
        } else {
            echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
        }
        $prev_level = $t->nlevel;
    }
}
echo '
    </select>

    <label for="edit_folder_complexite" class="label_cpm">'.$txt['complex_asked'].' :</label>
    <select id="edit_folder_complexite" class="input_text text ui-widget-content ui-corner-all">';
foreach ($pwComplexity as $complex) {
    echo '<option value="'.$complex[0].'">'.$complex[1].'</option>';
}
echo '
    </select>

    <label for="edit_folder_renewal_period" class="label_cpm">'.$txt['group_pw_duration'].' :</label>
    <input type="text" id="edit_folder_renewal_period" value="0" class="input_text text ui-widget-content ui-corner-all" />
</div>';

require_once 'folders.load.php';
