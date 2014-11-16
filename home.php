<?php
/**
 * @file          home.php
 * @author        Nils Laumaillé
 * @version       2.1.22
 * @copyright     (c) 2009-2014 Nils Laumaillé
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
            FROM ".$pre."log_items l
            INNER JOIN ".$pre."items i
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
            DB::query("SELECT * FROM ".$pre."log_items WHERE id_item = %i AND action = %s", $record['id'], 'at_delete');
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

    //Import from CSV div
    echo '
                <div style="">
                    <div id="div_import_from_csv" style="display:none;padding:4px;">';
                        // Show buttons for selected what kind of import
                        echo '
                        <div id="radio_import_type">
                            <input type="radio" id="radio1" name="radio" class="import_radio" checked="checked" onclick="toggle_import_type(\'csv\')" /><label for="radio1">CSV</label>
                            <input type="radio" id="radio2" name="radio" class="import_radio" onclick="toggle_import_type(\'keepass\')" /><label for="radio2">Keepass XML</label>
                        </div>';

                        //error div
                        echo '
                        <div style="margin:2px;display:none;" id="import_from_file_info" class="ui-state-error ui-corner-all"></div>';

                        // CSV import type
                        echo '
                        <div id="import_type_csv">
                            <div style="margin-bottom:5px;margin-top:5px;padding:5px;" class="ui-widget ui-state-active ui-corner-all">'.$LANG['import_csv_dialog_info'].'</div>
                            <!-- show input file -->
                            <div style="text-align:center;margin-top:10px;">
                                <div id="upload_container_csv">
                                    <div id="filelist_csv"></div><br />
                                    <a id="pickfiles_csv" href="#">'.$LANG['csv_import_button_text'].'</a>
                                </div>
                                <div id="csv_import_options" style="display:none;">
                                    <input type="checkbox" id="import_csv_anyone_can_modify" /><label for="import_csv_anyone_can_modify">'.$LANG['import_csv_anyone_can_modify_txt'].'</label><br />
                                    <input type="checkbox" id="import_csv_anyone_can_modify_in_role" /><label for="import_csv_anyone_can_modify_in_role">'.$LANG['import_csv_anyone_can_modify_in_role_txt'].'</label>
                                </div>
                            </div>
                        </div>';

                        // KEEPASS import type
                        echo '
                        <div id="import_type_keepass" style="display:none;">
                            <div style="margin-bottom:5px;margin-top:5px;padding:5px;" class="ui-widget ui-state-active ui-corner-all">'.$LANG['import_keepass_dialog_info'].'</div>
                             <!-- Prepare a list of all folders that the user can choose -->
                            <div style="margin-top:10px;" id="keypass_import_options">
                                <label><b>'.$LANG['import_keepass_to_folder'].'</b></label>&nbsp;
                                <select id="import_keepass_items_to">
                                    <option value="0">'.$LANG['root'].'</option>';
    $prevLevel = "";
    foreach ($fullTree as $t) {
        if (in_array($t->id, $_SESSION['groupes_visibles'])) {
            if (is_numeric($t->title)) {
                $user = DB::queryfirstrow("SELECT login FROM ".$pre."users WHERE id = %i", $t->title);
                $t->title = $user['login'];
                $t->id = $t->id."-perso";
            }
            $ident="&nbsp;&nbsp;";
            for ($x=1; $x<$t->nlevel; $x++) {
                $ident .= "&nbsp;&nbsp;";
            }
            if ($prevLevel < $t->nlevel) {
                echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
            } elseif ($prevLevel == $t->nlevel) {
                echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
            } else {
                echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
            }
            $prevLevel = $t->nlevel;
        }
    }
    echo '
                                </select><br />
                                <input type="checkbox" id="import_kps_anyone_can_modify" /><label for="import_kps_anyone_can_modify">'.$LANG['import_csv_anyone_can_modify_txt'].'</label><br />
                                <input type="checkbox" id="import_kps_anyone_can_modify_in_role" /><label for="import_kps_anyone_can_modify_in_role">'.$LANG['import_csv_anyone_can_modify_in_role_txt'].'</label>
                            </div>';

                        echo '
                            <div id="upload_container_kp" style="text-align:center;margin-top:10px;">
                                <div id="filelist_kp"></div><br />
                                <a id="pickfiles_kp" href="#">'.$LANG['keepass_import_button_text'].'</a>
                            </div>
                        </div>';

                        // Import results
                        echo '
                        <div id="import_status_ajax_loader" style="margin-top:5px;display:none;text-align:center;"><img src="includes/images/ajax-loader.gif" /></div>
                        <div id="import_status" style="margin-top:10px;"></div>
                    </div>
                </div>';

                //Data Export (PDF/CSV)
                if (isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1 && $_SESSION['temporary']['user_can_printout'] == true) {
                echo '
                <div>
                    <div id="div_print_out" style="display:none;padding:4px;">
                        <div style="height:20px;text-align:center;margin:2px;" id="print_out_error" class=""></div>

                        <label for="selected_folders" class="form_label">'.$LANG['select_folders'].' :&nbsp;<i class="fa fa-cog fa-spin" id="loading_folders_wait" style="display:none;"></i></label>
                        <select id="selected_folders" multiple size="7" class="text ui-widget-content ui-corner-all" style="padding:10px;"></select>

                        <div class="div_radio" stle="text-align:center;">
                            <input type="radio" id="export_format_radio1" name="export_format" value="pdf" /><label for="export_format_radio1">'.$LANG['pdf'].'</label>
                            <input type="radio" id="export_format_radio2" name="export_format" value="csv" /><label for="export_format_radio2">'.$LANG['csv'].'</label>
                        </div>

                        <br /><br />
                        <label for="pdf_password" class="">'.$LANG['admin_action_db_restore_key'].' :</label>
                        <input type="password" id="pdf_password" name="pdf_password" />

                        <div class="ui-state-highlight ui-corner-all" style="margin:10px;padding:10px;">
                            <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;">&nbsp;</span>'.$LANG['print_out_warning'].'
                        </div>

                        <div id="download_link" style="text-align:center; width:100%; margin-top:15px;"></div>
                        <div style="text-align:center;margin-top:8px; display:none;" id="div_print_out_wait"><img src="includes/images/ajax-loader.gif" /></div>
                    </div>
                </div>';
                }

	// Off line mode
	if (isset($_SESSION['settings']['settings_offline_mode']) && $_SESSION['settings']['settings_offline_mode'] == 1) {
		echo '
                <div>
                    <div id="div_offline_mode" style="display:none;padding:4px;">
                        <div style="height:20px;text-align:center;margin:2px;" id="offline_mode_error" class=""></div>
						<div style="margin:10px 0 10px 0;">
                        <label for="offline_mode_selected_folders" class="form_label">'.$LANG['select_folders'].' :</label>
                        <select id="offline_mode_selected_folders" multiple size="7" class="text ui-widget-content ui-corner-all" style="padding:10px;"></select>
						</div>
						<div style="margin:10px 0 10px 0;">
	                        <label for="pdf_password" class="">'.$LANG['admin_action_db_restore_key'].' :</label>
	                        <input type="password" id="offline_password" name="offline_password" />
	                        <div id="offline_pw_strength" style="margin:10px 0 0 50px;"></div>
                            <input type="hidden" id="offline_pw_strength_value" />
		                    <input type="hidden" id="min_offline_pw_strength_value" value="'.$_SESSION['settings']['offline_key_level'].'" />
						</div>
						<div style="margin:10px 0 10px 0;">
                        <div class="ui-state-highlight ui-corner-all" style="margin:10px;padding:10px;">
						<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;">&nbsp;d</span>'.$LANG['offline_mode_warning'].'
                        </div>
						</div>
                        <div id="offline_download_link" style="text-align:center; width:100%; margin-top:15px;">&nbsp;</div>
                        <div style="text-align:center;margin-top:8px; display:none;" id="div_offline_mode_wait"><img src="includes/images/ajax-loader.gif" /></div>
						<input type="hidden" id="offmode_number" />
						<input type="hidden" id="offmode_list" />
                    </div>
                </div>';
	}
}
echo '
            </div>';

require_once 'home.load.php';