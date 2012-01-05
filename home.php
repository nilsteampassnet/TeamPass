<?php
/**
 * @file 		home.php
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

//Call nestedtree library and load full tree
require_once ("sources/NestedTree.class.php");
$tree = new NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$tree->rebuild();
$full_tree = $tree->getDescendants();

echo '
            <div style="line-height: 24px;margin-top:10px;min-height:220px;">
            <span class="ui-icon ui-icon-person" style="float: left; margin-right: .3em;">&nbsp;</span>
            '.$txt['index_welcome'].' <b>'.$_SESSION['login'].'</b><br />';
            //Check if password is valid
            if ( empty($_SESSION['last_pw_change']) || $_SESSION['validite_pw'] == false ){
                echo '
                <div style="margin:auto;padding:4px;width:300px;"  class="ui-state-focus ui-corner-all">
                	<h3>'.$txt['index_change_pw'].'</h3>
                    <div style="height:20px;text-align:center;margin:2px;display:none;" id="change_pwd_error" class=""></div>
                    <div id="pw_strength" style="margin:0 0 10px 30px;"></div>
                    <table>
                        <tr>
                        	<td>'.$txt['index_new_pw'].' :</td><td><input type="password" size="10" name="new_pw" id="new_pw"/></td>
                        </tr>
                        <tr><td>'.$txt['index_change_pw_confirmation'].' :</td><td><input type="password" size="10" name="new_pw2" id="new_pw2" /></td></tr>
                        <tr><td colspan="2"><input type="button" onClick="ChangeMyPass()" value="'.$txt['index_change_pw_button'].'" /></td></tr>
                    </table>
                </div>';
            }elseif ( !empty($_SESSION['derniere_connexion']) ){
                //Last items created block
                if ( isset($_SESSION['settings']['show_last_items']) && $_SESSION['settings']['show_last_items'] == 1 && !empty($_SESSION['groupes_visibles_list']) ){
                    echo '
                    <div style="position:relative;float:right;margin-top:-25px;padding:4px;width:250px;" class="ui-state-highlight ui-corner-all">
                        <span class="ui-icon ui-icon-comment" style="float: left; margin-right: .3em;">&nbsp;</span>
                        <span style="font-weight:bold;margin-bottom:10px;">'.$txt['block_last_created'].'</span><br />';
                        $sql = "SELECT
                        i.label AS label, i.id AS id, i.id_tree AS id_tree
                        FROM ".$pre."log_items l
                        INNER JOIN ".$pre."items i
                        WHERE l.action = 'at_creation'
                            AND l.id_item = i.id
                            AND i.id_tree IN (".$_SESSION['groupes_visibles_list'].")
                            AND i.perso = 0
                        ORDER BY l.date DESC
                        LIMIT 0,10
                        ";
                	$cpt=1;
                    $rows = $db->fetch_all_array($sql);
                	foreach($rows as $record){
                		$data = $db->fetch_row("SELECT COUNT(*) FROM ".$pre."log_items WHERE id_item = '".$record['id']."' AND action = 'at_delete'");
                		if ( $data[0] == 0 ){
                			echo '<span class="ui-icon ui-icon-tag" style="float: left; margin-right: .3em;">&nbsp;</span>
                            <a href="#" onClick="javascript:$(\'#menu_action\').val(\'action\');window.location.href =\'index.php?page=items&amp;group='.$record['id_tree'].'&amp;id='.$record['id'].'\';" style="cursor:pointer;">'.stripslashes($record['label']).'</a><br />';
                			if($cpt==5) break;
                			$cpt++;
                		}
                	}
                        echo '
                    </div>';
                }

                //some informations
                echo '
                   <span class="ui-icon ui-icon-calendar" style="float: left; margin-right: .3em;">&nbsp;</span>
                   '.$txt['index_last_seen'].' ', isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'],$_SESSION['derniere_connexion']) : date("d/m/Y",$_SESSION['derniere_connexion']), ' '.$txt['at'].' ', isset($_SESSION['settings']['time_format']) ? date($_SESSION['settings']['time_format'],$_SESSION['derniere_connexion']) : date("H:i:s",$_SESSION['derniere_connexion']), '
                   <br />
                    <span class="ui-icon ui-icon-key" style="float: left; margin-right: .3em;">&nbsp;</span>
                '.$txt['index_last_pw_change'].' ', isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'],$_SESSION['last_pw_change']) : date("d/m/Y",$_SESSION['last_pw_change']), '. ', $nb_jours_avant_expiration_du_mdp == "infinite" ? '' : $txt['index_pw_expiration'].' '.$nb_jours_avant_expiration_du_mdp.' '.$txt['days'].'.';


                //Personnal menu
                echo '
                <div style="margin-top:15px;" id="personal_menu_actions">
                    <span class="ui-icon ui-icon-script" style="float: left; margin-right: .3em;">&nbsp;</span><b>'.$txt['home_personal_menu'].'</b>
                    <div style="margin-left:30px;">
                        <button title="'.$txt['index_change_pw'].'" onclick="OpenDialogBox(\'div_changer_mdp\')">
                            <img src="includes/images/lock--pencil.png" alt="Change pw" />
                        </button>
                        &nbsp;
                        <button title="'.$txt['import_csv_menu_title'].'" onclick="$(\'#div_import_from_csv\').dialog(\'open\')">
                            <img src="includes/images/database-import.png" alt="Import" />
                        </button>',
                		(isset($_SESSION['settings']['allow_print']) && $_SESSION['settings']['allow_print'] == 1) ? '
                        &nbsp;
                        <button title="'.$txt['print_out_menu_title'].'" onclick="print_out_items()">
                            <img src="includes/images/printer.png" alt="Print" />
                        </button>' : '' ,'
                    </div>
                </div>';

            	//Personnal SALTKEY
            	if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) {
            		echo '
                <div style="margin-top:15px;" id="personal_saltkey">
                    <span class="ui-icon ui-icon-locked" style="float: left; margin-right: .3em;">&nbsp;</span><b>'.$txt['home_personal_saltkey'].'</b>
                    <div style="margin-left:30px;">
           				<input type="password" name="input_personal_saltkey" id="input_personal_saltkey" style="width:200px;padding:5px;" class="text ui-widget-content ui-corner-all" value="', isset($_SESSION['my_sk']) ? '**************************' : '', '" title="'.$txt['home_personal_saltkey_info'].'" />
                        <button id="personal_sk" onclick="StorePersonalSK()">
                            '.$txt['home_personal_saltkey_button'].'
                        </button>
                        &nbsp;
                        <button id="change_personal_sk" onclick="$(\'#div_change_personal_saltkey\').dialog(\'open\')">
                            '.$txt['personal_saltkey_change_button'].'
                        </button>
                    </div>
                </div>';

            		//change the saltkey dialogbox
            		echo '
           	    <div id="div_change_personal_saltkey" style="display:none;padding:4px;">
           	        <label for="new_personal_saltkey" class="form_label_180">'.$txt['new_saltkey'].' :</label>
           	        <input type="text" size="30" name="new_personal_saltkey" id="new_personal_saltkey" />
           	        <div style="margin-top:20px;" class="ui-state-highlight">
           	        	'.$txt['new_saltkey_warning'].'
           	        </div>
           	    </div>';
            	}

                //change the password
                echo '
                <div>
                    <div id="div_changer_mdp" style="display:none;padding:4px;">
                        <div style="height:20px;text-align:center;margin:2px;" id="change_pwd_error" class=""></div>

                        <label for="new_pw" class="form_label">'.$txt['index_new_pw'].' :</label>
                        <input type="password" size="15" name="new_pw" id="new_pw" />
                        <label for="new_pw2" class="form_label">'.$txt['index_change_pw_confirmation'].' :</label>
                        <input type="password" size="15" name="new_pw2" id="new_pw2" />

                        <div id="pw_strength" style="margin:10px 0 0 50px;"></div>
						<input type="hidden" id="pw_strength_value" />
                    </div>
                </div>';

                //Import from CSV div
                echo '
                <div style="">
                    <div id="div_import_from_csv" style="display:none;padding:4px;">';
                        // Show buttons for selected what kind of import
                        echo '
                        <div id="radio_import_type">
                            <input type="radio" id="radio1" name="radio" class="import_radio" checked="checked" onclick="javascript:$(\'#import_type_csv\').show();$(\'#import_type_keepass\').hide();" /><label for="radio1">CSV</label>
                            <input type="radio" id="radio2" name="radio" class="import_radio" onclick="javascript:$(\'#import_type_csv\').hide();$(\'#import_type_keepass\').show();" /><label for="radio2">Keepass XML</label>
                        </div>';

                        //error div
                        echo '
                        <div style="margin:2px;display:none;" id="import_from_file_info" class="ui-state-error ui-corner-all"></div>';

                        // CSV import type
                        echo '
                        <div id="import_type_csv">
                            <div style="margin-bottom:5px;margin-top:5px;padding:5px;" class="ui-widget ui-state-active ui-corner-all">'.$txt['import_csv_dialog_info'].'</div>
                            <!-- show input file + uploadify call -->
                            <div style="text-align:center;margin-top:10px;">
                            	<input id="fileInput_csv" name="fileInput_csv" type="file" /><br />
								<input type="checkbox" id="import_csv_anyone_can_modify" /><label for="import_csv_anyone_can_modify">'.$txt['import_csv_anyone_can_modify_txt'].'</label><br />
								<input type="checkbox" id="import_csv_anyone_can_modify_in_role" /><label for="import_csv_anyone_can_modify_in_role">'.$txt['import_csv_anyone_can_modify_in_role_txt'].'</label>
                            </div>
                        </div>';

                        // KEEPASS import type
                        echo '
                        <div id="import_type_keepass" style="display:none;">
                            <div style="margin-bottom:5px;margin-top:5px;padding:5px;" class="ui-widget ui-state-active ui-corner-all">'.$txt['import_keepass_dialog_info'].'</div>
                             <!-- Prepare a list of all folders that the user can choose -->
                            <div style="margin-top:10px;">
                            	<label><b>'.$txt['import_keepass_to_folder'].'</b></label>&nbsp;
                                <select id="import_keepass_items_to">
                                    <option value="0">'.$txt['root'].'</option>';
                            $prev_level = "";
                            foreach($full_tree as $t){
                                if ( in_array($t->id,$_SESSION['groupes_visibles']) ){
                                    $ident="&nbsp;&nbsp;";
                                    for($x=1;$x<$t->nlevel;$x++) $ident .= "&nbsp;&nbsp;";
                                    if ( $prev_level < $t->nlevel ){
                                        echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
                                    }else if ( $prev_level == $t->nlevel ){
                                       echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
                                    }else{
                                        echo '<option value="'.$t->id.'">'.$ident.$t->title.'</option>';
                                    }
                                    $prev_level = $t->nlevel;
                                }
                            }
                        echo '
	                            </select><br />
								<input type="checkbox" id="import_kps_anyone_can_modify" /><label for="import_kps_anyone_can_modify">'.$txt['import_csv_anyone_can_modify_txt'].'</label><br />
								<input type="checkbox" id="import_kps_anyone_can_modify_in_role" /><label for="import_kps_anyone_can_modify_in_role">'.$txt['import_csv_anyone_can_modify_in_role_txt'].'</label>
	                        </div>';

            			echo '
                            <!-- show input file + uploadify call -->
                            <div style="text-align:center;margin-top:10px;"><input id="fileInput_keepass" name="fileInput_keepass" type="file" /></div>
                        </div>';

                        // Import results
                        echo '
                        <div id="import_status_ajax_loader" style="margin-top:5px;display:none;text-align:center;"><img src="includes/images/ajax-loader.gif" /></div>
                        <div id="import_status" style="margin-top:10px;"></div>
                    </div>
                </div>';

            	//Print out the items
            	echo '
            	<div>
            	    <div id="div_print_out" style="display:none;padding:4px;">
            	        <div style="height:20px;text-align:center;margin:2px;" id="print_out_error" class=""></div>

            	        <label for="selected_folders" class="form_label">'.$txt['select_folders'].' :</label>
            	        <select id="selected_folders" multiple size="7" class="text ui-widget-content ui-corner-all" style="padding:10px;"></select>

            	        <div class="ui-state-highlight ui-corner-all" style="margin:10px;padding:10px;">
            	        	<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;">&nbsp;</span>'.$txt['print_out_warning'].'
						</div>
            	    </div>
            	</div>';
            }else{

            }
            echo '
            </div>';

?>