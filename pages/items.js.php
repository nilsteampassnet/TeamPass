<?php
/**
 * Teampass - a collaborative passwords manager
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   Items.php
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @version   GIT: <git_id>
 * @link      http://www.teampass.net
 */

if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}


?>


<script type="text/javascript">


var requestRunning = false,
    clipboard,
    query_in_progress = 0;

// Build tree
$('#jstree').jstree({
    "core" : {
        "animation" : 0,
        "check_callback" : true,
        'data' : {
            'url' : "./sources/tree.php",
            "dataType" : "json",
            "async" : true,
            'data' : function (node) {
                return { 'id' : node.id.split('_')[1] };
            }
        },
        "strings" : {
            "Loading ..." : "<?php echo langHdl('loading'); ?>..."
        },
        "error" : {

        }
    },
    "plugins" : [
        "state", "search"
    ]
})
//search in tree
.bind("search.jstree", function (e, data) {
    if (data.nodes.length == 1) {
        //open the folder
        ListerItems($("#jstree li>a.jstree-search").attr('id').split('_')[1], '', 0);
    }
});

//Expend/Collapse jstree
$("#jstree_close").click(function() {
    $("#jstree_search").keypress(function(e) {
        if (e.keyCode == 13) {
            $("#jstree").jstree("search",$("#jstree_search").val());
        }
    });
});

// load list of visible folders for current user
$(this).delay(500).queue(function() {
    refreshVisibleFolders();
    $(this).dequeue();
});


// Ensure correct height of folders tree
$("#jstree").height($(window).height() - 250);


// Manage folders action
$('.btn-tool').click(function() {
    if ($(this).data('folder-action') === 'refresh') {
        refreshTree();        
    } else if ($(this).data('folder-action') === 'expand') {
        $("#jstree").jstree("open_all");
    } else if ($(this).data('folder-action') === 'collapse') {
        $("#jstree").jstree("close_all");
    }

});

//Evaluate number of items to display - depends on screen height
if (parseInt($("#nb_items_to_display_once").val())
    || $("#nb_items_to_display_once").val() == "max"
) {
    //do nothing ... good value
} else {
    //adapt to the screen height
    $("#nb_items_to_display_once")
        .val(Math.max(Math.round(($(window).height()-450)/23),2));
}

/**
 * Undocumented function
 *
 * @return void
 */
function refreshVisibleFolders()
{
    $.post(
        "sources/items.queries.php",
        {
            type    : "refresh_visible_folders",
            key        : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            //check if format error
            if (data.error === "") {
                // Build html lists
                var html_visible = '',
                    html_full_visible = '',
                    html_active_visible = '',
                    indentation = '',
                    disabled = '';

                // Shall we show the root folder
                if (data.html_json.can_create_root_folder === 1) {
                    html_visible = '<option value="0"><?php echo langHdl('root'); ?></option>';
                    html_full_visible = '<option value="0"><?php echo langHdl('root'); ?></option>';
                    html_active_visible = '<option value="0"><?php echo langHdl('root'); ?></option>';
                }

                //
                $.each(data.html_json.folders, function(i, value) {
                    // Build identation
                    indentation = '';
                    for (x = 1; x <= value.level; x += 1) {
                        indentation += "&nbsp;&nbsp;";
                    }

                    // Is it disabled?
                    if (value.disabled === 1) {
                        disabled = ' disabled="disabled"';
                    } else {
                        disabled = '';
                    }

                    if (value.is_visible_active === 1) {
                        disabled_active_visible = ' disabled="disabled"';
                    } else {
                        disabled_active_visible = '';
                    }

                    // Prepare options lists
                    html_visible += '<option value="'+value.id+'"'+disabled+'>'+indentation+value.title+'</option>';
                    html_full_visible += '<option value="'+value.id+'">'+indentation+value.title+'</option>';
                    html_active_visible += '<option value="'+value.id+'"'+disabled_active_visible+'>'+indentation+value.title+'</option>';
                });

                // append new list
                $("#categorie, #edit_categorie, #new_rep_groupe, #edit_folder_folder, #delete_rep_groupe").find('option').remove().end().append(html_visible);
                $("#move_folder_id").find('option').remove().end().append(html_full_visible);
                $("#copy_in_folder").find('option').remove().end().append(html_active_visible);

                // remove ROOT option if exists
                $('#edit_folder_folder option[value="0"]').remove();
                $('#delete_rep_groupe option[value="0"]').remove();
            }
        }
   );
}


/*
* builds the folders tree
*/
function refreshTree(node_to_select, do_refresh, refresh_visible_folders)
{
    do_refresh = do_refresh || ""
    node_to_select = node_to_select || "";
    refresh_visible_folders = refresh_visible_folders || 1;

    if (refresh_visible_folders !== 1) {
        $("#jstree").jstree("deselect_all");
        $('#jstree').jstree("select_node", "#li_"+groupe_id);
        return false;
    }

    if (do_refresh !== "0") {
        $('#jstree').jstree(true).refresh();
    }

    if (node_to_select !== "") {
        $("#hid_cat").val(node_to_select);
        $("#jstree").jstree("deselect_all");

        $('#jstree')
        .one("refresh.jstree", function(e, data) {
            data.instance.select_node("#li_"+node_to_select);
        });
    }

    if (refresh_visible_folders === 1) {
        $(this).delay(500).queue(function() {
            refreshVisibleFolders();
            $(this).dequeue();
        });
    }
}

/**
 * 
 */
function ListerItems(groupe_id, restricted, start, stop_listing_current_folder)
{
    var me = $(this);
    stop_listing_current_folder = stop_listing_current_folder || "0";

    // Delete existing clipboard
    if (clipboard) {
        clipboard.destroy();
    }

    // case where we should stop listing the items
    if ($("#items_listing_should_stop").val() === "1") {
        requestRunning = false;
        $("#items_listing_should_stop").val("0");
        return false;
    }

    if (stop_listing_current_folder === 1) {
        me.data('requestRunning', false);
        $("#new_listing_characteristics").val(groupe_id+","+restricted+","+start+",0");
    } else {
        $("#new_listing_characteristics").val("");
    }


    // prevent launch of similar query in case of doubleclick
    if (requestRunning === true) {
        return false;
    }
    requestRunning = true;

    $("#request_lastItem, #selected_items").val("");

    if (groupe_id != undefined) {
        //refreshTree(groupe_id);
        if (query_in_progress != 0 && query_in_progress != groupe_id) {
            request.abort();    //kill previous query if needed
        }
        query_in_progress = groupe_id;
        //LoadingPage();
        $("#items_list_loader").removeClass("hidden");
        if (start == 0) {
            //clean form
            $('#id_label, #id_pw, #id_email, #id_url, #id_desc, #id_login, #id_info, #id_restricted_to, #id_files, #id_tags, #id_kbs, #item_extra_info, #item_viewed_x_times').html("");
            $("#teampass_items_list").html('');
        }
        $("#items_list").css("display", "");

        $("#hid_cat").val(groupe_id);
        if ($(".tr_fields") !== undefined) {
            $(".tr_fields, .newItemCat, .editItemCat").addClass("hidden");
        }

        //Disable menu buttons
        $("#button_quick_login_copy, #button_quick_pw_copy").addClass("hidden");

        $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;<?php echo langHdl('opening_folder'); ?>');

        //ajax query
        request = $.post("sources/items.queries.php",
            {
                type        : "lister_items_groupe",
                id          : groupe_id,
                restricted  : restricted,
                start       : start,
                uniqueLoadData : $("#uniqueLoadData").val(),
                key         : "<?php echo $_SESSION['key']; ?>",
                nb_items_to_display_once : $("#nb_items_to_display_once").val()
            },
            function(data) {
                if (data == "Hacking attempt...") {
                    alert("Hacking attempt...");
                    return false;
                }
                //get data
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
console.log(data);
                // reset doubleclick prevention
                requestRunning = false;

                // manage not allowed
                if (data.error == "not_allowed") {
                   $("#div_dialog_message_text").html(data.error_text);
                   $("#div_dialog_message").dialog("open");
                   $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;Error');
                   $("#items_list_loader").addClass("hidden");
                   return false;
                }

                // to be done only in 1st list load
                if (data.list_to_be_continued === "end") {
                    $("#pf_selected").val(data.IsPersonalFolder);

                    // display path of folders
                    if ((data.arborescence !== undefined && data.arborescence !== "")) {
                        console.log(data.arborescence);
                        $("#items_path_var")
                            .html('')
                            .append(rebuildPath(data.arborescence));
                    }
                    /*if ((data.arborescence !== undefined && data.arborescence !== "") || $("#tmp_arbo").length > 0) {
                        var path_maxlength = 420;
                        var arbo = '';

                        // check arbo and rebuild it
                        if ($("#tmp_arbo").length > 0) {
                            arbo = $("#tmp_arbo").html();
                        } else {
                            arbo = rebuildPath(data.arborescence);
                        }

                        if ($("#path_fontsize").val() != "") {
                            $("#items_path_var").css('font-size', $("#path_fontsize").val());
                        }
                        if (data.IsPersonalFolder === 0) {
                            $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;' + arbo);
                        } else {
                            $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;<?php echo langHdl('personal_folder'); ?>&nbsp;:&nbsp;' + arbo);
                        }
                        var path_levels = arbo.split('&nbsp;<i class="fa fa-caret-right"></i>&nbsp;').length;
                        if ($("#items_path_var").width() > path_maxlength) {
                            $("#path_fontsize").val($("#items_path_var").css('font-size'));
                            // start reducing size of font
                            $("#items_path_var").css('font-size', parseInt($("#items_path_var").css('font-size'))-1);
                            if ($("#items_path_var").width() > path_maxlength && path_levels < 2) {
                                while ($("#items_path_var").width() > path_maxlength) {
                                    $("#items_path_var").css('font-size', parseInt($("#items_path_var").css('font-size')) - 1);
                                }
                            }

                            if ($("#items_path_var").width() > path_maxlength && path_levels >= 2) {
                                // only take first and last
                                var nb = 1;
                                var totalPathLength = occupedWidth = 0;
                                $(".path_element").each(function () {
                                    totalPathLength += $(this).width();
                                    if (nb != 1 && nb != (path_levels-1) && nb != path_levels) {
                                        $(this).html("<span class='tip' title='"+$(this).html()+"'>...</span>");
                                    } else if (nb == path_levels) {
                                        // next condition occurs if lasst folder name is too long
                                        if (totalPathLength > path_maxlength) {
                                            var lastTxt = $(this).html();
                                            while ($(this).width() > (path_maxlength - occupedWidth)) {
                                                lastTxt = lastTxt.slice(0, -1);
                                                $(this).html(lastTxt);
                                            }
                                            $(this).html(lastTxt+"...");
                                        }
                                    }
                                    occupedWidth += $(this).width()+15; // 15 pixels corresponds to the small right triangle
                                    nb++;
                                });
                            }
                        }
                    }*/ else {
                        $("#items_path_var").html('');
                    }

                    // Remove span with arbo
                    $("#tmp_arbo").remove();

                    // store the categories to be displayed
                    if (data.displayCategories !== undefined) {
                        $("#display_categories").val(data.displayCategories);
                    }

                    // store type of access on folder
                    $("#access_level").val(data.access_level);

                    // warn about a required change of personal SK
                    if ($("#personal_upgrade_needed").val() == "1" && data.recherche_group_pf === 1) {
                        $("#dialog_upgrade_personal_passwords").dialog("open");
                    }

                    $("#items_loading_progress").remove();

                    // show correct fodler in Tree
                    $("#jstree").jstree("deselect_all");
                    $('#jstree').jstree("select_node", "#li_"+groupe_id);
                } else if (data.error === "not_authorized") {
                    $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;'+rebuildPath(data.arborescence));
                    $("#items_list_loader").addClass("hidden");
                } else {
                    $("#uniqueLoadData").val(data.uniqueLoadData);
                    if ($("#items_loading_progress").length == 0) {
                        $("#items_list_loader").after('<span id="items_loading_progress">' + Math.round(data.next_start*100/data.counter_full, 0) + '%</span>');
                    } else {
                        $("#items_loading_progress").html(Math.round(data.next_start*100/data.counter_full, 0) + '%');
                    }
                    // Store arbo
                    if (data.arborescence !== undefined && data.arborescence !== "" && $("#tmp_arbo").length === 0) {
                        // Rebuild path
                        new_path = rebuildPath(data.arborescence);

                        // Store path in tempo element
                        $("body").append('<span class="hidden" id="tmp_arbo">'+new_path+'</span>');
                    }
                }


                if (data.array_items == "" && data.items_count == "0") {
                    $("#items_list").html('<div style="text-align:center;margin-top:30px;"><b><i class="fa fa-info-circle"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?></b></div>');
                }

                if (data.error == "is_pf_but_no_saltkey") {
                    //warn user about his saltkey
                    $("#item_details_no_personal_saltkey").removeClass("hidden");
                    $("#item_details_ok, #item_details_nok, #items_list_loader, #div_loading").addClass("hidden");
                    $('#menu_button_add_item').prop('disabled', 'true');
                } else if (data.error == "not_authorized" || data.access_level === "") {
                    //warn user
                    $("#hid_cat").val("");
                    //$("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_history, #menu_button_share, #menu_button_otv").prop('disabled', 'true');
                    $("#item_details_nok").removeClass("hidden");
                    $("#item_details_ok, #item_details_no_personal_saltkey, #items_list_loader").addClass("hidden");
                } else if (($("#user_is_read_only").val() == 1 && data.recherche_group_pf == 0) || data.access_level == 1) {
                    //readonly user
                    $("#recherche_group_pf").val(data.saltkey_is_required);
                    $("#item_details_no_personal_saltkey, #item_details_nok").addClass("hidden");
                    $("#item_details_ok, #items_list").removeClass("hidden");

                    $("#more_items").remove();

                    // show items
                    showItemsList(data.html_json);

                    if (data.list_to_be_continued === "yes") {
                        //set next start for query
                        $("#query_next_start").val(data.next_start);
                    } else {
                        $("#query_next_start").val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($(".tr_fields") !== undefined && data.displayCategories !== undefined && data.displayCategories !== "") {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $(".itemCatName_"+liste[i]+", #newItemCatName_"+liste[i]+", #editItemCatName_"+liste[i]).removeClass('hidden');
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($(".tr_fields") != undefined) $(".tr_fields").addClass("hidden");
                        }
                    }

                    proceed_list_update(stop_listing_current_folder);
                } else {
                    $("#recherche_group_pf").val(data.saltkey_is_required);
                    //Display items
                    $("#item_details_no_personal_saltkey, #item_details_nok").addClass("hidden");
                    $("#item_details_ok, #items_list").removeClass("hidden");

                    $('#complexite_groupe').val(data.folder_complexity);
                    $('#bloquer_creation_complexite').val(data.bloquer_creation_complexite);
                    $('#bloquer_modification_complexite').val(data.bloquer_modification_complexite);

                    // show items
                    showItemsList(data.html_json);

                    // Prepare next iteration if needed
                    if (data.list_to_be_continued === "yes") {
                        //set next start for query
                        $("#query_next_start").val(data.next_start);
                    } else {
                        $("#query_next_start").val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($(".tr_fields") != undefined && data.displayCategories !== undefined && data.displayCategories != "") {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $(".itemCatName_"+liste[i]+", #newItemCatName_"+liste[i]+", #editItemCatName_"+liste[i]).removeClass("hidden");
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($(".tr_fields") != undefined) $(".tr_fields").addClass("hidden");
                        }
                    }

                    proceed_list_update(stop_listing_current_folder);
                }
            }
        );
    }
}

function showItemsList(data)
{
    $.each((data), function(i, value) {
        var new_line = pwd_error = icon_all_can_modify = icon_login = icon_pwd = icon_favorite = item_flag = '';

        // Prepare item icon
        if (value.canMove === 1 && value.accessLevel === 0) {
            item_class = "item_draggable";
            item_span = '<span style="cursor:hand;" class="grippy"><span class="fa fa-sm fa-ellipsis-v"></span>&nbsp;</span>';
        } else {
            item_class = "item";
            item_span = '';
        }

        // Prepare error message
        if (value.pw_status === "encryption_error") {
            pwd_error = '<span class="fa fa-warning fa-sm mi-red tip" title="<?php echo langHdl('pw_encryption_error'); ?>"></span>&nbsp;';
        }

        // Prepare anyone can modify icon
        if (value.anyone_can_modify === "1") {
            icon_all_can_modify = '<span class="fa fa-pencil fa-sm mi-grey-1 pointer tip" title="<?php echo langHdl('item_menu_collab_enable'); ?>" onclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \''+value.open_edit+'\', \''+value.reload+'\', \''+value.tree_id+'\')"></span>&nbsp;&nbsp;';
        }

        // Prepare mini icons
        if (value.copy_to_clipboard_small_icons === "1" && value.display_item === 1) {
            // Login icon
            if (value.login !== "") {
                icon_login = '<span class="fa fa-sm fa-user mi-black mini_login" data-clipboard-text="'+sanitizeString(value.login)+'" title="<?php echo langHdl('item_menu_copy_login'); ?>" id="minilogin_'+value.item_id+'"></span>&nbsp;';
            }
            // Pwd icon
            if (value.pw !== "") {
                icon_pwd = '<span class="fa fa-sm fa-lock mi-black mini_pw" data-clipboard-text="'+sanitizeString(value.pw)+'" title="<?php echo langHdl('item_menu_copy_pw'); ?>" data-clipboard-id="'+value.item_id+'" id="minipwd_'+value.item_id+'"></span>&nbsp;';
            }

            // Now check if pwd is empty. If it is then warn user
            if (value.pw === "") {
                pwd_error = '&nbsp;<span class="fa fa-exclamation-circle fa-sm mi-yellow tip" title="<?php echo langHdl('password_is_empty'); ?>"></span>&nbsp;';
            }
        }

        // Prepare Favorite icon
        if (value.display_item === 1 && value.enable_favourites === "1") {
            if (value.in_favorite === 1) {
                icon_favorite = '<span id="quick_icon_fav_'+value.item_id+'" title="Manage Favorite" class="cursor tip">' +
                    '<span class="fa fa-sm fa-star mi-yellow" onclick="ActionOnQuickIcon('+value.item_id+',0)" class="tip"></span>' +
                    '</span>';
            } else {
                icon_favorite = '<span id="quick_icon_fav_'+value.item_id+'" title="Manage Favorite" class="cursor tip">' +
                    '<span class="fa fa-sm fa-star-o mi-black" onclick="ActionOnQuickIcon('+value.item_id+',1)" class="tip"></span>' +
                    '</span>';
            }
        }

        // Prepare Description
        if (value.desc !== "") {
            value.desc = '[' + value.desc + ']';
        }

        // Prepare flag
        if (value.expiration_flag !== "") {
            item_flag = '<i class="fa fa-flag ' + value.expiration_flag + ' fa-sm"></i>&nbsp;';
        }

        // Appenditem row
        /*$("#full_items_list").append(
            '<li name="' + value.label + '" class="'+ item_class + ' trunc_line" id="'+value.item_id+'" data-edition="'+value.open_edit+'">' + item_span +
            item_flag +
            '<i class="fa ' + value.perso + ' fa-sm"></i>&nbsp' +
            '&nbsp;<a id="fileclass'+value.item_id+'" class="file " onclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \'\', \''+value.reload+'\', \''+value.tree_id+'\')"  ondblclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \''+value.open_edit+'\', \''+value.reload+'\', \''+value.tree_id+'\')"><div class="truncate">'+
            '<span id="item_label_' + value.item_id + '">' + value.label + '</span>&nbsp;<font size="1px">' +
            value.desc +
            '</div></font></a>' +
            '<span style="float:right;margin-top:2px;">' +
            pwd_error +
            icon_all_can_modify +
            icon_login +
            icon_pwd +
            icon_favorite +
            '</span>' +
            '</li>'
        );*/
        $("#teampass_items_list").append(
            '<tr name="' + value.label + '" data-edition="' + value.open_edit + '">' +
            '<td>' + item_span + '</td>' +
            '<td><i class="fa ' + value.perso + ' fa-sm"></i</td>' +
            '<td><span id="item_label_' + value.item_id + '">' + value.label + '</span></td>' +
            '<td class="text-truncate" style="width: 8rem;">' + value.desc + '</td>' +
            '<td>' + item_span + '</td>' +
            '<td>' + pwd_error + icon_all_can_modify + icon_login + icon_pwd + icon_favorite + '</td>' +
            '</tr>'
        );
    });
}

/**
 * Builds the HTML path
 * @param  {[type]} data [description]
 * @return {[type]}      [description]
 */
function rebuildPath(data)
{
    var new_path = new_path_elem = '';
    $.each((data), function(i, value) {
        new_path_elem = '';
        if (value['visible'] === 1) {
            new_path_elem = ' style="cursor:pointer;" onclick="ListerItems('+value['id']+', \'\', 0)"';
        }

         new_path += '<li class="breadcrumb-item" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</li>';

        /*if (new_path === "") {
            new_path = '<a class="path_element" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</a>';
        } else {
            new_path += '&nbsp;<span class="fa fa-caret-right"></span>&nbsp;<a class="path_element" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</a>'
        }*/
    });

    return new_path;
}

function proceed_list_update(stop_proceeding)
{
    stop_proceeding = stop_proceeding || "";

    if (stop_proceeding === "1" || ($("#new_listing_characteristics").val() !== "" && $("#query_next_start").val() !== "end")) {
        var tmp = $("#new_listing_characteristics").val().split(',');
        $("#new_listing_characteristics").val("");
        ListerItems(tmp[0], tmp[1], tmp[2], tmp[3]);
        return false;
    }

    if ($("#query_next_start").val() !== "end") {
        //Check if nb of items do display > to 0
        if ($("#nb_items_to_display_once").val() > 0) {
            ListerItems($("#hid_cat").val(),'', parseInt($("#query_next_start").val()));
        }
    } else {
        $('ul#full_items_list>li').tsort("",{order:"asc",attr:"name"});
        $("#items_list_loader").addClass("hidden");

        // prepare clipboard items
        clipboard = new Clipboard('.mini_login');
        clipboard.on('success', function(e) {
            $("#message_box").html("<?php echo langHdl('login_copied_clipboard'); ?>").show().fadeOut(1000);
            e.clearSelection();
        });

        clipboard = new Clipboard('.mini_pw');
        clipboard.on('success', function(e) {
            $("#message_box").html("<?php echo langHdl('pw_copied_clipboard'); ?>").show().fadeOut(1000);
            itemLog(
                "at_password_copied",
                e.trigger.dataset.clipboardId,
                $('#item_label_'+e.trigger.dataset.clipboardId).text()
            );
            e.clearSelection();
        });

        $(".tip").tooltipster({multiple: true});
        $(".mini_login, .mini_pw").css("cursor", "pointer");

        // Prepare items dragable on folders
        $(".item_draggable").draggable({
            handle: '.grippy',
            cursor: "move",
            opacity: 0.4,
            appendTo: 'body',
            stop: function(event, ui) {
                $(this).removeClass("ui-state-highlight");
            },
            start: function(event, ui) {
                $(this).addClass("ui-state-highlight");
            },
            helper: function(event) {
                return $("<div class='ui-widget-header' id='drop_helper'>"+"<?php echo langHdl('drag_drop_helper'); ?>"+"</div>");
            }
        });
        $(".folder").droppable({
            hoverClass: "ui-state-error",
            tolerance: 'pointer',
            drop: function(event, ui) {
                ui.draggable.addClass("hidden");
                LoadingPage();
                //move item
                $.post(
                    "sources/items.queries.php",
                    {
                        type      : "move_item",
                        item_id   : ui.draggable.attr("id"),
                        folder_id : $(this).attr("id").substring(4),
                        key       : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        LoadingPage();
                        // check if errors
                        if (data[0].error !== "") {
                            if (data[0].error === "ERR_PSK_REQUIRED") {
                                displayMessage("<?php echo langHdl('psk_required'); ?>");
                            } else {
                                displayMessage("<?php echo langHdl('error_not_allowed_to'); ?>");
                            }
                            ui.draggable.removeClass("hidden");
                            return false;
                        }
                        //increment / decrement number of items in folders
                        $("#itcount_"+data[0].from_folder).text(Math.floor($("#itcount_"+data[0].from_folder).text())-1);
                        $("#itcount_"+data[0].to_folder).text(Math.floor($("#itcount_"+data[0].to_folder).text())+1);
                        $("#id_label, #item_viewed_x_times, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to, #id_tags, #id_kbs").html("");
                        displayMessage("<?php echo langHdl('alert_message_done'); ?>");
                    },
                    "json"
               );
            }
        });

        var restricted_to_roles = <?php if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] == 1) {
    echo 1;
} else {
    echo 0;
}
?>;

        // refine users list to the related roles
        $.post(
            "sources/items.queries.php",
            {
                type        : "get_refined_list_of_users",
                iFolderId   : $('#hid_cat').val(),
                key         : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                // *** restricted_to_list ***
                $("#restricted_to_list").empty();
                // add list of users
                if ($('#restricted_to').val() != undefined) {
                    $("#restricted_to_list").append(data.selOptionsUsers);
                    if (restricted_to_roles == 1) {
                        //add optgroup
                        var optgroup = $('<optgroup>');
                        optgroup.attr('label', "<?php echo langHdl('users'); ?>");
                        $(".folder_rights_user").wrapAll(optgroup);
                    }
                }
                //Add list of roles if option is set
                if (restricted_to_roles == 1 && $('#restricted_to').val() != undefined) {
                    //add optgroup
                    var optgroup = $('<optgroup>');
                    optgroup.attr('label', "<?php echo langHdl('roles'); ?>");
                    $("#restricted_to_list").append(data.selOptionsRoles);
                    $(".folder_rights_role").wrapAll(optgroup);
                }
                //Prepare multiselect widget
                $("#restricted_to_list").select2({
                    language: "<?php echo $_SESSION['user_language_code']; ?>"
                });

                // *** edit_restricted_to_list ***
                $("#edit_restricted_to_list").empty();
                if ($('#edit_restricted_to').val() != undefined) {
                    $("#edit_restricted_to_list").append(data.selEOptionsUsers);
                    if (restricted_to_roles == 1) {
                        //add optgroup
                        var optgroup = $('<optgroup>');
                        optgroup.attr('label', "<?php echo langHdl('users'); ?>");
                        $(".folder_rights_user_edit").wrapAll(optgroup);
                    }
                }
                //Add list of roles if option is set
                if (restricted_to_roles == 1 && $('#edit_restricted_to').val() != undefined) {
                    //add optgroup
                    var optgroup = $('<optgroup>');
                    optgroup.attr('label', "<?php echo langHdl('roles'); ?>");
                    $("#edit_restricted_to_list").append(data.selEOptionsRoles);
                    $(".folder_rights_role_edit").wrapAll(optgroup);
                }
                //Prepare multiselect widget
                $("#edit_restricted_to_list").select2({
                    language: "<?php echo $_SESSION['user_language_code']; ?>"
                });
            }
       );
    }
}

</script>
