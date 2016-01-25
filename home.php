<?php
/**
 * @file          home.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Call nestedtree library and load full tree
$tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

$tree->rebuild();
$fullTree = $tree->getDescendants();

echo '
            <div style="line-height: 24px;margin-top:10px;min-height:220px;">
            <span class="ui-icon ui-icon-person" style="float: left; margin-right: .3em;">&nbsp;</span>
            '.$LANG['index_welcome'].' <b>', isset($_SESSION['name']) && !empty($_SESSION['name']) ? $_SESSION['name'].' '.$_SESSION['lastname'] : $_SESSION['login'], '</b><br />';
            //Check if password is valid
if (empty($_SESSION['last_pw_change']) || $_SESSION['validite_pw'] == false) {
                echo '
                <div style="margin:auto;padding:4px;width:300px;"  class="ui-state-focus ui-corner-all">
                    <h3>'.$LANG['index_change_pw'].'</h3>
                    <div style="height:20px;text-align:center;margin:2px;display:none;" id="change_pwd_error" class=""></div>
                    <div style="text-align:center;margin:5px;padding:3px;" id="change_pwd_complexPw" class="ui-widget ui-state-active ui-corner-all">'.
                        $LANG['complex_asked'].' : '.$_SESSION['settings']['pwComplexity'][$_SESSION['user_pw_complexity']][1].
                    '</div>
                    <div id="pw_strength" style="margin:0 0 10px 30px;"></div>
                    <table>
                        <tr>
                            <td>'.$LANG['index_new_pw'].' :</td><td><input type="password" size="15" name="new_pw" id="new_pw"/></td>
                        </tr>
                        <tr><td>'.$LANG['index_change_pw_confirmation'].' :</td><td><input type="password" size="15" name="new_pw2" id="new_pw2" onkeypress="if (event.keyCode == 13) ChangeMyPass();" /></td></tr>
                    </table>
                    <input type="hidden" id="pw_strength_value" />
                    <input type="button" onClick="ChangeMyPass()" onkeypress="if (event.keyCode == 13) ChangeMyPass();" class="ui-state-default ui-corner-all" style="padding:4px;width:150px;margin:10px 0 0 80px;" value="'.$LANG['index_change_pw_button'].'" />
                </div>
                <script type="text/javascript">
                    $("#new_pw").focus();
                </script>';
} elseif (!empty($_SESSION['derniere_connexion'])) {
    //Last items created block
    if (
        isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] == 1
        && $_SESSION['user_admin'] != 1 && !empty($_SESSION['groupes_visibles_list'])
    ) {
                    echo '
                    <div style="position:relative;float:right;margin-top:-25px;padding:4px;width:250px;" class="ui-state-highlight ui-corner-all">
                        <span class="ui-icon ui-icon-comment" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <span style="font-weight:bold;margin-bottom:10px;">'.$LANG['block_last_created'].'</span><br />';
        $cpt=1;
        $rows = DB::query("SELECT i.label as label, i.id as id, i.id_tree as id_tree
            FROM ".prefix_table("log_items")." l
            INNER JOIN ".prefix_table("items")." i
            WHERE l.action = %s_action
            AND l.id_item = i.id
            AND i.id_tree IN %li_id_tree
            AND i.perso = %i_perso
            ORDER BY l.date DESC
            LIMIT 0,10",
            array(
                'action' => 'at_creation',
                'id_tree' => $_SESSION['groupes_visibles'],
                'perso' => 0
            )
        );
        foreach ($rows as $record) {
            DB::query("SELECT * FROM ".prefix_table("log_items")." WHERE id_item = %i AND action = %s", $record['id'], 'at_delete');
            $counter = DB::count();
            if ($counter == 0) {
                echo '<span class="ui-icon ui-icon-tag" style="float: left; margin-right: .3em;">&nbsp;</span>
                <a href="#" onClick="javascript:$(\'#menu_action\').val(\'action\');window.location.href =\'index.php?page=items&amp;group='.$record['id_tree'].'&amp;id='.$record['id'].'\';" style="cursor:pointer;">'.stripslashes($record['label']).'</a><br />';
                if ($cpt==5) {
                    break;
                }
                $cpt++;
            }
        }
        echo '
                    </div>';
    }
    //ADMIN INFORMATION
    /*if ($_SESSION['user_admin'] == 1) {
        echo '
                    <div style="position:relative;float:right;margin-top:-25px;padding:4px;width:250px;" class="ui-state-highlight ui-corner-all">
                        <span class="ui-icon ui-icon-comment" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <span style="font-weight:bold;margin-bottom:10px;">'.$LANG['block_admin_info'].'</span><br />'.
                        $LANG['admin_new1'].'
                    </div>';
    }*/

    //some informations
    echo '
                   <span class="ui-icon ui-icon-calendar" style="float: left; margin-right: .3em;">&nbsp;</span>
                   '.$LANG['index_last_seen'].' ', isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'], $_SESSION['derniere_connexion']) : date("d/m/Y", $_SESSION['derniere_connexion']), ' '.$LANG['at'].' ', isset($_SESSION['settings']['time_format']) ? date($_SESSION['settings']['time_format'], $_SESSION['derniere_connexion']) : date("H:i:s", $_SESSION['derniere_connexion']), '
                   <br />
                    <span class="ui-icon ui-icon-key" style="float: left; margin-right: .3em;">&nbsp;</span>
                   '.$LANG['index_last_pw_change'].' ', isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'], $_SESSION['last_pw_change']) : date("d/m/Y", $_SESSION['last_pw_change']), '. ', $_SESSION['numDaysBeforePwExpiration'] == "infinite" ? '' : $LANG['index_pw_expiration'].' '.$_SESSION['numDaysBeforePwExpiration'].' '.$LANG['days'].'.';
    echo '
                   <br /><span class="ui-icon ui-icon-signal-diag" style="float: left; margin-right: .3em;">&nbsp;</span>
                   <div id="upload_info">
                        <div id="plupload_runtime" class="ui-state-error ui-corner-all" style="width:400px;">Upload feature: No runtime found.</div>
                        <input type="hidden" id="upload_enabled" value="" />
                    </div>';

    //Personnal menu
    echo '
                <div style="margin-top:15px;" id="personal_menu_actions">
                    <span class="ui-icon ui-icon-script" style="float: left; margin-right: .3em;">&nbsp;</span><b>'.$LANG['home_personal_menu'].'</b>
                    <div style="margin-left:30px;">',
                        $_SESSION['user_admin'] == 1 ? '' :
                        (isset($_SESSION['settings']['allow_import']) && $_SESSION['settings']['allow_import'] == 1 && $_SESSION['user_admin'] != 1) ? '
                        <button title="'.$LANG['import_csv_menu_title'].'" onclick="$(\'#csv_import_options, #kp_import_options\').hide();$(\'#div_import_from_csv\').dialog(\'open\');">
                            <img src="includes/images/database-import.png" alt="Import" />
                        </button>' : '' ,
                        (isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 && $_SESSION['user_admin'] != 1 && $_SESSION['temporary']['user_can_printout'] == true) ? '
                        &nbsp;
                        <button title="'.$LANG['print_out_menu_title'].'" onclick="print_out_items()">
                            <img src="includes/images/printer.png" alt="Print" />
                        </button>' : '' ,
						(isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1 && $_SESSION['user_admin'] != 1) ? '
						&nbsp;
						<button title="'.$LANG['offline_menu_title'].'" onclick="offlineModeLaunch()">
						<img src="includes/images/block-share.png" alt="Print" />
						</button>' : '' , '
                    </div>
                </div>';



}
echo '
            </div>';

require_once 'home.load.php';