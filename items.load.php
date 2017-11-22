<?php
/**
 * @file          items.load.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
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

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

$var['hidden_asterisk'] = '<i class="fa fa-eye fa-border fa-sm tip" title="'.$LANG['show_password'].'"></i>&nbsp;&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>';

// load csrfprotector
$csrfp_config = include $SETTINGS['cpassman_dir'].'/includes/libraries/csrfp/libs/csrfp.config.php';
?>

<script type="text/javascript">
//<![CDATA[
    // Some global declaration
    var query_in_progress = 0;
    var clipboard;

    $(document).on('focusin', function(e) {e.stopImmediatePropagation();});

//  Part of Safari 6 OS X fix
    //  clean up HTML for sending via JSON to PHP code
    function clean_up_html_safari(input)
    {
        //  applies to Safari 6 on OS X only, so check for that
        user_agent = navigator.userAgent;
        if (/Mac OS X.+6\.\d\.\d\sSafari/.test(user_agent))
        {
            // remove strange divs
            input = input.replace(/<\/*div.+>\n/g, '');
            /**/
            //  remove other strange tags
            allowed_tags = '<strong><em><strike><ol><li><ul><a><br>';
            input = strip_tags(input, allowed_tags);

            //  replace special characters
            input = input.replace(/(\r\n|\n|\r)/gm, '<br>')
                                                .replace(/\t/g, '')
                                                .replace(/\f/g, '')
                                                .replace(/\v/g, '')
                                                .replace(/\r/g, '');
        }
        return input;
    }/* */

    function AddNewNode()
    {
        //Select first child node in tree
        $('#2').click();
        //Add new node to selected node
        simpleTreeCollection.get(0).addNode(1,'A New Node')
    }

    function EditNode()
    {
        //Select first child node in tree
        $('#2').click();
        //Add new node to selected node
        simpleTreeCollection.get(0).addNode(1,'A New Node')
    }

    function DeleteNode()
    {
        //Select first child node in tree
        $('#2').click();
        //Add new node to selected node
        simpleTreeCollection.get(0).delNode()
    }

    //FUNCTION mask/unmask passwords characters
    function ShowPassword(pw)
    {
        if ($("#selected_items").val() == "") return;

        if ($('#id_pw').html().indexOf("fa-asterisk") != -1) {
            itemLog("item_password_shown");
            $('#id_pw').text($('#hid_pw').val());
        } else {
            $('#id_pw').html('<?php echo $var["hidden_asterisk"]; ?>');
        }
    }

    $("#tabs-02").on(
        "change",
        "#pw1",
        function() {
            $('#visible_pw').val($('#pw1').val());
        }
    );

    function ShowPasswords_EditForm()
    {
        if ($('#edit_visible_pw').is(":visible")) {
            $('#edit_visible_pw').addClass("hidden");
        } else {
            $('#edit_visible_pw').removeClass("hidden");
        }
    }

    $("#edit_pw1").keyup(function() {
        $("#edit_visible_pw").text( this.value );
    });

    $("#pw1").keyup(function() {
        $("#visible_pw").text( this.value );
    });



    /**
     * Open a dialogbox
     * @access public
     * @return void
     **/
    function OpenDialog(id, modal)
    {
        if ($("#selected_items").val() == "") return;

        if (modal == "false") {
            $("#"+id).dialog("option", "modal", false);
        } else {
            $("#"+id).dialog("option", "modal", true);
        }
        $("#"+id).dialog("open");
    }

/*
*
*/
function LoadTreeNode(node_id)
{

}

//###########
//## FUNCTION : Launch the listing of all items of one category
//###########
var requestRunning = false;
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
            $("#items_list").html("<ul class='liste_items 'id='full_items_list'></ul>");
        }
        $("#items_list").css("display", "");

        $("#hid_cat").val(groupe_id);
        if ($(".tr_fields") != undefined) $(".tr_fields, .newItemCat, .editItemCat").addClass("hidden");

        //Disable menu buttons
        $("#button_quick_login_copy, #button_quick_pw_copy").addClass("hidden");

        $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;<?php echo addslashes($LANG["opening_folder"]); ?>');

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
                    if ((data.arborescence !== undefined && data.arborescence !== "") || $("#tmp_arbo").length > 0) {
                        var path_maxlength = 420;
                        var arbo = '';

                        // check arbo and rebuild it
                        if ($("#tmp_arbo").length > 0) {
                            arbo = $("#tmp_arbo").html();
                        } else {
                            arbo = rebuildPath(data.arborescence);
                        }

                        if ($("#path_fontsize").val() != "") $("#items_path_var").css('font-size', $("#path_fontsize").val());
                        if (data.IsPersonalFolder === 0) {
                            $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;' + arbo);
                        } else {
                            $("#items_path_var").html('<i class="fa fa-folder-open-o"></i>&nbsp;<?php echo addslashes($LANG['personal_folder']); ?>&nbsp;:&nbsp;' + arbo);
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
                    } else {
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
                    $("#items_list").html('<div style="text-align:center;margin-top:30px;"><b><i class="fa fa-info-circle"></i>&nbsp;<?php echo addslashes($LANG['no_item_to_display']); ?></b></div>');
                }

                if (data.error == "is_pf_but_no_saltkey") {
                    //warn user about his saltkey
                    $("#item_details_no_personal_saltkey").show();
                    $("#item_details_ok, #item_details_nok").addClass("hidden");

                    $('#menu_button_add_item').prop('disabled', 'true');
                    $("#items_list_loader, #div_loading").addClass("hidden");
                } else if (data.error == "not_authorized" || data.access_level === "") {
                    //warn user
                    $("#hid_cat").val("");
                    //$("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_history, #menu_button_share, #menu_button_otv").prop('disabled', 'true');
                    $("#item_details_nok").removeClass("hidden");
                    $("#item_details_ok, #item_details_no_personal_saltkey").addClass("hidden");
                    $("#items_list_loader").addClass("hidden");
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
                                $(".itemCatName_"+liste[i]+", #newItemCatName_"+liste[i]+", #editItemCatName_"+liste[i]).show();
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
                                $(".itemCatName_"+liste[i]+", #newItemCatName_"+liste[i]+", #editItemCatName_"+liste[i]).show();
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

/**
 * Builds the HTML list of Items
 * @param  {[type]} data [description]
 * @return {[type]}      [description]
 */
function showItemsList(data)
{
    $.each((data), function(i, value) {
        var new_line = pwd_error = icon_all_can_modify = icon_login = icon_pwd = icon_favorite = item_flag = '';

        // Prepare item icon
        if (value.canMove === 1 && value.accessLevel === 0) {
            item_class = "item_draggable";
            item_span = '<span style="cursor:hand;" class="grippy"><span class="fa fa-sm fa-arrows mi-grey-1"></span>&nbsp;</span>';
        } else {
            item_class = "item";
            item_span = '<span style="margin-left:11px;"></span>';
        }

        // Prepare error message
        if (value.pw_status === "encryption_error") {
            pwd_error = '<span class="fa fa-warning fa-sm mi-red tip" title="<?php echo addslashes($LANG['pw_encryption_error']);?>"></span>&nbsp;';
        }

        // Prepare anyone can modify icon
        if (value.anyone_can_modify === "1") {
            icon_all_can_modify = '<span class="fa fa-pencil fa-sm mi-grey-1 tip" title="<?php echo addslashes($LANG['item_menu_collab_enable']);?>"></span>&nbsp;&nbsp;';
        }

        // Prepare mini icons
        if (value.copy_to_clipboard_small_icons === "1") {
            // Login icon
            if (value.login !== "") {
                icon_login = '<span class="fa fa-sm fa-user mi-black mini_login" data-clipboard-text="'+value.login+'" title="<?php echo addslashes($LANG['item_menu_copy_login']);?>"></span>&nbsp;';
            }
            // Pwd icon
            if (value.pw !== "") {
                icon_pwd = '<span class="fa fa-sm fa-lock mi-black mini_pw" data-clipboard-text="'+value.pw+'" title="<?php echo addslashes($LANG['item_menu_copy_pw']);?>" data-clipboard-id="'+value.item_id+'"></span>&nbsp;';
            }

            // Now check if pwd is empty. If it is then warn user
            if (value.pw === "") {
                pwd_error = '&nbsp;<span class="fa fa-exclamation-circle fa-sm mi-yellow tip" title="<?php echo addslashes($LANG['password_is_empty']);?>"></span>&nbsp;';
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
        $("#full_items_list").append(
            '<li name="' + value.label + '" ondblclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \''+value.open_edit+'\', \''+value.reload+'\', \''+value.tree_id+'\')" class="'+ item_class + ' trunc_line" id="'+value.item_id+'" style="">' + item_span +
            item_flag +
            '<i class="fa ' + value.perso + ' fa-sm"></i>&nbsp' +
            '&nbsp;<a id="fileclass'+value.item_id+'" class="file " onclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \'\', \''+value.reload+'\', \''+value.tree_id+'\')"><div class="truncate">'+value.label+'&nbsp;<font size="1px">' +
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

        if (new_path === "") {
            new_path = '<a class="path_element" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</a>';
        } else {
            new_path += '&nbsp;<span class="fa fa-caret-right"></span>&nbsp;<a class="path_element" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</a>'
        }
    });

    return new_path;
}

function pwGenerate(elem)
{
    if (elem != "") elem = elem+"_";
    $("#"+elem+"pw1").show().focus();

    //show ajax image
    $("#"+elem+"pw_wait").removeClass("hidden");

    $.post(
        "sources/main.queries.php",
        {
            type    : "generate_a_password",
            size      : $("#"+elem + 'pw_size').val(),
            numerals      : $("#"+elem + 'pw_numerics').prop("checked"),
            capitalize      : $("#"+elem + 'pw_maj').prop("checked"),
            symbols      : $("#"+elem + 'pw_symbols').prop("checked"),
            secure  : $("#"+elem + 'pw_secure').prop("checked"),
            elem      : elem,
            force      : "false"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
               if (data.error == "true") {
                   $("#div_dialog_message_text").html(data.error_msg);
                   $("#div_dialog_message").dialog("open");
               } else {
                $("#"+elem+"visible_pw").text(data.key);
                   $("#"+elem+"pw1, #"+elem+"pw2").val(data.key);
                $("#"+elem+"pw1").focus();
               }
            //$("#"+elem+"pw1").show().blur();
            $("#"+elem+"pw_wait").addClass("hidden");
        }
   );
}

function pwCopy(elem)
{
    if (elem != "") elem = elem+"_";
    $("#"+elem + 'pw2').val($("#"+elem + 'pw1').val());
}

function catSelected(val)
{
    $("#hid_cat").val(val);
}

/**
* Get Item complexity
*/
function RecupComplexite(val, edit, context)
{
    context = context || "";    // make context optional

    var funcReturned = null;
    $.ajaxSetup({async: false});
    $.post(
        "sources/items.queries.php",
        {
            type    : "get_complixity_level",
            groupe  : val,
            context : context,
            item_id : $("#selected_items").val()
        },
        function(data) {
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                $("#div_loading").addClass("hidden");
                $("#request_ongoing").val("");
                $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);
                $("#div_dialog_message").dialog("open");

                return;
            }

            funcReturned = 1;
            if (data.error == undefined
                || data.error === ""
            ) {
                $("#complexite_groupe").val(data.val);
                $("#selected_folder_is_personal").val(data.personal);
                if (edit == 1) {
                    $("#edit_complex_attendue").html("<b>"+data.complexity+"</b>");
                    $("#edit_afficher_visibilite").html("<span class='fa fa-users'></span>&nbsp;<b>"+data.visibility+"</b>");
                } else {
                    $("#complex_attendue").html("<b>"+data.complexity+"</b>");
                    $("#afficher_visibilite").html("<span class='fa fa-users'></span>&nbsp;<b>"+data.visibility+"</b>");
                }
            } else if (data.error === "no_edition_possible") {
                $("#div_dialog_message_text").html(data.error_msg);
                $("#div_dialog_message").dialog("open");
                funcReturned = 0;
            } else if (data.error === "user_is_readonly") {
                displayMessage(data.message);
                funcReturned = 0;
            } else if (data.error === "no_folder_creation_possible"
                || data.error === "no_folder_edition_possible"
                || data.error === "delete_folder") {
                displayMessage('<i class="fa fa-warning"></i>&nbsp;' + data.error_msg
            );
                $("#div_loading").addClass("hidden");
                funcReturned = 0;
            } else {
                $("#div_formulaire_edition_item").dialog("close");
                $("#div_dialog_message_text").html(data.error_msg);
                $("#div_dialog_message").dialog("open");
            }
            $("#div_loading").addClass("hidden");
        }
    );
    $.ajaxSetup({async: true});
    return funcReturned;
}

/**
* Check if Item has been changed since loaded
*/
function CheckIfItemChanged()
{
    var funcReturned = null;
    $.ajaxSetup({async: false});
    $.post(
        "sources/items.queries.php",
        {
            type        : "is_item_changed",
            timestamp   : $("#timestamp_item_displayed").val(),
            item_id     : $("#selected_items").val()
        },
        function(data) {
            data = $.parseJSON(data);
            if (data.modified == 1) {
                funcReturned = 1;
            } else {
                funcReturned = 0;
            }
        }
   );
    $.ajaxSetup({async: true});
    return funcReturned;
}

function AjouterItem()
{
    $("#div_formulaire_saisi_info").show().html("<?php echo "<i class='fa fa-cog fa-spin fa-lg'></i>&nbsp;".addslashes($LANG['please_wait'])."..."; ?>");
    LoadingPage();
    $("#error_detected").val('');   //Refresh error foolowup
    var erreur = "";
    var  reg=new RegExp("[.|;|:|!|=|+|-|*|/|#|\"|'|&|]");

    //Complete url format
    var url = $("#url").val();
    if (url.substring(0,7) != "http://" && url!="" && url.substring(0,8) != "https://" && url.substring(0,6) != "ftp://" && url.substring(0,6) != "ssh://") {
        url = "http://"+url;
    }

    // do checks
    if ($("#label").val() == "") erreur = "<?php echo addslashes($LANG['error_label']); ?>";
    else if ($("#pw1").val() === "" && $("#create_item_without_password").val() !== "1") erreur = "<?php echo addslashes($LANG['error_pw']); ?>";
    else if ($("#categorie").val() == "na") erreur = "<?php echo addslashes($LANG['error_group']); ?>";
    else if ($("#pw1").val() != $("#pw2").val()) erreur = "<?php echo addslashes($LANG['error_confirm']); ?>";
    else if ($("#enable_delete_after_consultation").is(':checked') && (($("#times_before_deletion").val() < 1 && $("#deletion_after_date").val() == "") || ($("#times_before_deletion").val() == "" && $("#deletion_after_date").val() == ""))) erreur = "<?php echo addslashes($LANG['error_times_before_deletion']); ?>";
    else if ($("#item_tags").val() != "" && reg.test($("#item_tags").val())) erreur = "<?php echo addslashes($LANG['error_tags']); ?>";
    else if (($('#recherche_group_pf').val() === "1" || $('#selected_folder_is_personal').val() === "1") && $('#personal_sk_set').val() === "0") {
        erreur = "<?php echo addslashes($LANG['alert_message_personal_sk_missing']); ?>";
    } else{
        //Check pw complexity level
        if (
            ($("#bloquer_creation_complexite").val() == 0 && parseInt($("#mypassword_complex").val()) >= parseInt($("#complexite_groupe").val()))
            ||
            ($("#bloquer_creation_complexite").val() == 1)
            ||
            ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)
            ||
            $("#create_item_without_password").val() === "1"
      ) {
            //Manage restrictions
            var restriction = restriction_role = "";
            $("#restricted_to_list option:selected").each(function () {
                //check if it's a role
                if ($(this).val().indexOf('role_') != -1) {
                    restriction_role += $(this).val().substring(5) + ";";
                } else {
                    restriction += $(this).val() + ";";
                }
            });
            if (restriction != "" && restriction.indexOf($('#form_user_id').val()) == "-1")
                restriction = $('#form_user_id').val()+";"+restriction
            if (restriction == ";") restriction = "";

            //Manage diffusion list
            var diffusion = "";
            $("#annonce_liste_destinataires option:selected").each(function () {
                diffusion += $(this).val() + ";";
            });
            if (diffusion == ";") diffusion = "";

            //Manage description
            if (CKEDITOR.instances && CKEDITOR.instances["edit_desc"]) {
                CKEDITOR.instances["edit_desc"].destroy();
            }
            if (CKEDITOR.instances && CKEDITOR.instances["desc"]) {
                var description = sanitizeString(CKEDITOR.instances["desc"].getData()).replace(/\n/g, '<br />').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
            } else {
                var description = sanitizeString($("#desc").val()).replace(/\n/g, '<br />').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
            }

            // Sanitize description with Safari
            description = clean_up_html_safari(description);

            //Is PF
            if ($('#selected_folder_is_personal').val() == 1 && $('#personal_sk_set').val() == 1) {
                var is_pf = 1;
            } else {
                var is_pf = 0;
            }

            //To be deleted
            if ($("#enable_delete_after_consultation").is(':checked') && ($("#times_before_deletion").val() >= 1 || $("#deletion_after_date").val() != "")) {
                if ($("#times_before_deletion").val() >= 1) {
                    var to_be_deleted = $("#times_before_deletion").val();
                } else if ($("#deletion_after_date").val() != "") {
                    var to_be_deleted = $("#deletion_after_date").val();
                }
            } else {
                var to_be_deleted = "";
            }

            // get item field values
            var fields = "";
            $('.item_field').each(function(i){
                id = $(this).attr('id').split('_');
                if (fields == "") fields = id[1] + '~~' + $(this).val() + '~~' + id[2];
                else fields += '_|_' + id[1] + '~~' + $(this).val() + '~~' + id[2];
            });

            // check if a folder is selected
            var selected_folder;
            if ($('#categorie').val() === "" || $('#categorie').val() === null) {
                selected_folder = $('#hid_cat').val();
            } else {
                selected_folder = $('#categorie').val();
            }

            //prepare data
            var data = {"pw": sanitizeString($('#pw1').val()) , "label": sanitizeString($('#label').val()) ,
                "login": sanitizeString($('#item_login').val()) , "is_pf": is_pf.toString() ,
                "description": (description) , "email": $('#email').val() , "url": url , "categorie": selected_folder ,
                "restricted_to": restriction , "restricted_to_roles": restriction_role ,
                "salt_key_set": $('#personal_sk_set').val() , "diffusion": diffusion , "id": $('#id_item').val() ,
                "anyone_can_modify": $('#anyone_can_modify:checked').val() , "tags": sanitizeString($('#item_tags').val()) ,
                "random_id_from_files": $('#random_id').val() , "to_be_deleted": to_be_deleted ,
                "fields": sanitizeString(fields) , "complexity_level": parseInt($("#mypassword_complex").val())};

            //Send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "new_item",
                    data     : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key        : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        $("#div_loading").addClass("hidden");
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);
                        $("#div_dialog_message").dialog("open");

                        return false;
                    }

                    //Check errors
                    if (data.error === "item_exists") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('<?php echo addslashes($LANG['error_item_exists']); ?>');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "ERR_KEY_NOT_CORRECT") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('Key verification for Query is not correct!');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "ERR_FOLDER_NOT_ALLOWED") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('User not allowed to access this folder!');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "ERR_PWD_TOO_LONG") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('<?php echo addslashes($LANG['error_pw_too_long']); ?>');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "ERR_ENCRYPTION_NOT_CORRECT") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('Item password could not be correctly encrypted!');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "ERR_PWD_EMPTY") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('Item password is empty!');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "ERR_ENCRYPTION") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html(data.msg);
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.new_id != "") {
                        $("#new_show_error").addClass("hidden");

                        //add new line directly in list of items
                        $("#full_items_list").append(data.new_entry);

                        //Increment counter
                        $("#itcount_"+$("#hid_cat").val()).text(Math.floor($("#itcount_"+$("#hid_cat").val()).text())+1);

                        // prepare the display of the new item
                        AfficherDetailsItem(data.new_id);

                        // refresh list of items
                        ListerItems($('#categorie').val(), "", 0)

                        refreshTree($('#categorie').val());

                        //empty form
                        $("#label, #item_login, #email, #url, #pw1, #visible_pw, #pw2, #item_tags, #deletion_after_date, #times_before_deletion, #mypassword_complex").val("");
                        CKEDITOR.instances["desc"].setData("");

                        $("#item_tabs").tabs({selected: 0});
                        $('ul#full_items_list>li').tsort("",{order:"asc",attr:"name"});
                        $(".fields, .item_field, #categorie, #random_id").val("");
                        $(".fields_div, #item_file_queue, #display_title, #visible_pw").html("");

                        $("#div_formulaire_saisi").dialog('close');
                        $("#div_formulaire_saisi ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                    }
                    $("#div_formulaire_saisi_info").addClass("hidden").html("");
                    $("#div_loading").addClass("hidden");
                }
           );
        } else {
            $('#new_show_error').html("<?php echo addslashes($LANG['error_complex_not_enought']); ?>").show();
            $("#div_formulaire_saisi ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
            $("#div_formulaire_saisi_info").addClass("hidden").html("");
        }
    }
    if (erreur != "") {
        $('#new_show_error').html(erreur).show();
        $("#div_formulaire_saisi_info").addClass("hidden").html("");
    }
}

function EditerItem()
{
    $("#div_formulaire_edition_item_info")
        .html("<?php echo "<i class='fa fa-cog fa-spin fa-lg'></i>&nbsp;".addslashes($LANG['please_wait'])."..."; ?>")
        .removeClass("hidden");
    $("#item_detail_zone_loader").addClass("hidden");
    var erreur = "";
    var  reg=new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

    //Complete url format
    var url = $("#edit_url").val();
    if (url.substring(0,7) != "http://" && url!="" && url.substring(0,8) != "https://" && url.substring(0,6) != "ftp://" && url.substring(0,6) != "ssh://") {
        url = "http://"+url;
    }

    // do checks
    if ($('#edit_label').val() == "") erreur = "<?php echo addslashes($LANG['error_label']); ?>";
    else if ($("#edit_pw1").val() === "" && $("#create_item_without_password").val() !== "1") erreur = "<?php echo addslashes($LANG['error_pw']); ?>";
    else if ($("#edit_pw1").val() != $("#edit_pw2").val()) erreur = "<?php echo addslashes($LANG['error_confirm']); ?>";
    else if ($("#edit_tags").val() != "" && reg.test($("#edit_tags").val())) erreur = "<?php echo addslashes($LANG['error_tags']); ?>";
    else if ($("#edit_categorie option:selected").val() == "" || typeof  $("#edit_categorie option:selected").val() === "undefined")  erreur = "<?php echo addslashes($LANG['error_no_selected_folder']); ?>";
    else{
        //Check pw complexity level
        if ((
                $("#bloquer_modification_complexite").val() == 0 &&
                parseInt($("#edit_mypassword_complex").val()) >= parseInt($("#complexite_groupe").val())
           )
            ||
            ($("#bloquer_modification_complexite").val() == 1)
            ||
            ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)
            ||
            $("#create_item_without_password").val() === "1"
      ) {
            LoadingPage();  //afficher image de chargement
            var annonce = 0;
            if ($('#edit_annonce').attr('checked')) annonce = 1;
            $("#item_detail_zone_loader").show();


            //Manage restriction
            var restriction = restriction_role = "";
            $("#edit_restricted_to_list option:selected").each(function () {
                if ($(this).val().indexOf('role_') != -1) {
                    restriction_role += $(this).val() + ";";
                } else {
                    restriction += $(this).val() + ";";
                }
            });
            if (restriction != "" && restriction.indexOf($('#form_user_id').val()) == "-1")
                restriction = $('#form_user_id').val()+";"+restriction
            if (restriction == ";") restriction = "";


            //Manage diffusion list
            var myselect = document.getElementById('edit_annonce_liste_destinataires');
            var diffusion = "";
            for (var loop=0; loop < myselect.options.length; loop++) {
                if (myselect.options[loop].selected === true) diffusion = diffusion + myselect.options[loop].value + ";";
            }
            if (diffusion == ";") {
                diffusion = "";
            }

            //Manage description
            if (CKEDITOR.instances["edit_desc"]) {
                var description = sanitizeString(CKEDITOR.instances["edit_desc"].getData()).replace(/\n/g, '<br />').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
            } else {
                var description = sanitizeString($("#edit_desc").val()).replace(/\n/g, '<br />').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
            }

            // Sanitize description with Safari
            description = clean_up_html_safari(description);

            //Is PF
            if ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1) {
                var is_pf = 1;
            } else {
                var is_pf = 0;
            }

          //To be deleted
            if ($("#edit_enable_delete_after_consultation").is(':checked')
                && ($("#edit_times_before_deletion").val() >= 1 || $("#edit_deletion_after_date").val() != "")
            ) {
                if ($("#edit_times_before_deletion").val() >= 1) {
                    var to_be_deleted = $("#edit_times_before_deletion").val();
                    //var to_be_deleted_after_date = "";
                } else if ($("#edit_deletion_after_date").val() != "") {
                    //var to_be_deleted = "0";
                    var to_be_deleted = $("#edit_deletion_after_date").val();
                }
            } else {
                var to_be_deleted = "";
                //var to_be_deleted_after_date = "";
            }

            // get item field values
            var fields = "";
            $('.edit_item_field').each(function(i){
                id = $(this).attr('id').split('_');
                var fieldValue = $(this).val(),
                    fieldType = $(this).attr('type');
                if (fieldType == 'checkbox') fieldValue = $(this).prop('checked');
                if (fields == "") fields = id[2] + '~~' + fieldValue;
                else fields += '_|_' + id[2] + '~~' + fieldValue;
            });

              //prepare data
            var data = {
                "pw": sanitizeString($('#edit_pw1').val()),
                "label": sanitizeString($('#edit_label').val()),
                "login": sanitizeString($('#edit_item_login').val()),
                "is_pf": is_pf ,
                "description": description,
                "email": $('#edit_email').val(),
                "url": url ,
                "categorie": $("#edit_categorie option:selected").val(),
                "restricted_to": restriction,
                "restricted_to_roles": restriction_role,
                "salt_key_set": $('#personal_sk_set').val(),
                "is_pf": $('#recherche_group_pf').val(),
                "annonce": annonce ,
                "diffusion": diffusion ,
                "id": $('#id_item').val(),
                "anyone_can_modify": $('#edit_anyone_can_modify:checked').val(),
                "tags": sanitizeString($('#edit_tags').val()),
                "to_be_deleted": to_be_deleted,
                "fields": sanitizeString(fields),
                "complexity_level": parseInt($("#edit_mypassword_complex").val())
            };

            //send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "update_item",
                    data      : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key        : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        $("#div_loading").addClass("hidden");
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text")
                            .html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+
                            data);
                        $("#div_dialog_message").dialog("open");
                        return;
                    }

                    //check if format error
                    if (data.error === "ERR_JSON_FORMAT") {
                        $("#div_loading").addClass("hidden");
                        $("#edit_show_error")
                            .html(data.error + ' ERROR (JSON is broken)!!!!!')
                            .show();
                    } else if (data.error === "ERR_KEY_NOT_CORRECT") {
                        $("#div_loading").addClass("hidden");
                        $("#edit_show_error")
                            .html('Key verification for Query is not correct!')
                            .show();
                        LoadingPage();
                    }else if (data.error === "ERR_ENCRYPTION_NOT_CORRECT") {
                        $("#div_loading").addClass("hidden");
                        $("#edit_show_error")
                            .html('Item password could not be correctly encrypted!')
                            .show();
                        LoadingPage();
                    } else if (data.error === "ERR_PWD_TOO_LONG") {
                        $("#div_loading").addClass("hidden");
                        $("#edit_show_error")
                            .html('<?php echo addslashes($LANG['error_pw_too_long']); ?>')
                            .show();
                        LoadingPage();
                    } else if (data.error === "ERR_NOT_ALLOWED_TO_EDIT") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error")
                            .html('User not allowed to edit this Item!')
                            .show();
                        LoadingPage();
                    } else if (data.error !== "") {
                        $("#div_loading").addClass("hidden");
                        $("#edit_show_error")
                            .html('<?php echo addslashes($LANG['error_not_allowed_to']); ?>')
                            .show();
                        LoadingPage();
                    } else {
                        //refresh item in list
                        $("#fileclass"+data.id).html('<div class="truncate">' + $('#edit_label').val() + '</div>');

                        //Refresh form
                        $("#id_label").text($('#edit_label').val());
                        //$("#id_pw").text($('#edit_pw1').val());
                        $("#id_email").html($('#edit_email').val());
                        $("#id_url").html($('#edit_url').val().escapeHTML());
                        $("#id_desc").html(description);
                        $("#id_login").html($('#edit_item_login').val());
                        $("#id_restricted_to").html(data.list_of_restricted);
                        $("#id_tags").html(data.tags);
                        $("#id_files").html(unsanitizeString(data.files));
                        $("#item_edit_list_files").html(data.files_edit);
                        $("#id_info").html(unsanitizeString(data.history));
                        $('#id_pw').html('<?php echo $var['hidden_asterisk']; ?>');

                        //Refresh hidden data
                        $("#hid_label").val($('#edit_label').val());
                        $("#hid_pw").val($('#edit_pw1').val());
                        $("#hid_email").val($('#edit_email').val());
                        $("#hid_url").val($('#edit_url').val().escapeHTML());
                        $("#hid_desc").val(description);
                        $("#hid_login").val($('#edit_item_login').val());
                        $("#hid_restricted_to").val(restriction);
                        $("#hid_restricted_to_roles").val(restriction_role);
                        $("#hid_tags").val($('#edit_tags').val());
                        $("#hid_files").val(data.files);
                        /*$("#id_categorie").html(data.id_tree);
                        $("#id_item").html(data.id);*/

                        // refresh fields
                        if ($('.edit_item_field').val() != undefined) {
                            $('.tr_fields').addClass("hidden");
                            $('.edit_item_field').each(function(i){
                                var fieldValue = $(this).val(),
	            			    fieldType = $(this).attr("type");

                                if (fieldType == 'checkbox'){
                                    fieldValue = $(this).prop('checked').toString();
                                }
                                id = $(this).attr('id').split('_');

                                if (fieldValue !== "") {
                                    // copy data from form to Item Div
                                    $('#id_field_' + id[2] + '_' + id[3]).html(fieldValue);
                                    $('#cf_tr_' + id[2] + ', .editItemCatName_' + id[3] + ', #tr_catfield_' + id[3]).show()
                                }
                                $('#hid_field_' + id[2] + '_' + id[3]).val(fieldValue);

                                // clear form
                                $(this).val("");
                            });
                        }
                        $("#edit_display_title, #edit_visible_pw").html("");

                        //calling image lightbox when clicking on link
                        $("a.image_dialog").click(function(event) {
                            event.preventDefault();
                            PreviewImage($(this).attr("href"),$(this).attr("title"));
                        });

                        //Change title in "last items list"
                        $("#last_items_"+data.id).text($('#edit_label').val());

                        //Clear upload queue
                        $('#item_edit_file_queue').html('');
                        //Select 1st tab
                        $("#item_edit_tabs").tabs({ selected: 0 });

                        //if reload page is needed
                        if (data.reload_page == "1") {
                            //reload list
                            ListerItems($('#hid_cat').val(), "", 0)
                            //increment / decrement number of items in folders
                            $("#itcount_"+$('#hid_cat').val()).text(Math.floor($("#itcount_"+$('#hid_cat').val()).text())-1);
                            $("#itcount_"+$('#edit_categorie').val()).text(Math.floor($("#itcount_"+$('#edit_categorie').val()).text())+1);
                        }

                        // tags
                        $(".round-grey").addClass("ui-state-highlight ui-corner-all");

                        //Prepare clipboard copies
                        if ($('#edit_pw1').val() !== "") {
                            new Clipboard("#menu_button_copy_pw, #button_quick_pw_copy", {
                                text: function() {
                                    return unsanitizeString($('#edit_pw1').val());
                                }
                            });

                            $("#button_quick_pw_copy").removeClass("hidden");
                        } else {
                            $("#button_quick_pw_copy").addClass("hidden");
                        }
                        if ($('#edit_item_login').val() != "") {
                            var clipboard_elogin = new Clipboard("#menu_button_copy_login, #button_quick_login_copy", {
                                text: function() {
                                    return unsanitizeString($('#edit_item_login').val());
                                }
                            });
                            $("#button_quick_login_copy").removeClass("hidden");
                        } else {
                            $("#button_quick_login_copy").addClass("hidden");
                        }


                        $("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                        //Close dialogbox
                        $("#div_formulaire_edition_item").dialog('close');
                        $("#div_formulaire_edition_item ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                        //hide loader
                        $("#div_loading").addClass("hidden");
                    }
                }
           );

           // statistic
           /*$.post(
                "sources/main.queries.php",
                {
                    type                : 'item_stat',
                    id                  : $('#id_item').val(),
                    stat_action                : "item"
                },
                function(data) {

                }
            );*/

        } else {
            $('#edit_show_error')
                .html("<?php echo addslashes($LANG['error_complex_not_enought']); ?>")
                .show();
            $("#div_formulaire_edition_item ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
            $("#div_formulaire_edition_item_info")
                .addClass("hidden")
                .html("");
        }
    }

    if (erreur != "") {
        $('#edit_show_error').html(erreur).show();
        $("#div_formulaire_edition_item_info")
            .addClass("hidden")
            .html("");
        $("#div_formulaire_edition_item ~ .ui-dialog-buttonpane")
            .find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')")
            .prop("disabled", false);
    }
}

function AddNewFolder()
{
    if ($("#new_rep_titre").val() == "") {
        $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_group_label']); ?>").removeClass("hidden");
    } else if ($("#new_rep_groupe").val() === "") {
        $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_group_noparent']); ?>").removeClass("hidden");
    } else if ($("#new_rep_complexite").val() == "") {
        $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_group_complex']); ?>").removeClass("hidden");
    } else if (/^\d+$/.test($("#new_rep_titre").val())) {
        // check if folder title contains only numbers
        $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_only_numbers_in_folder_name']); ?>").removeClass("hidden");
    } else if ($("#new_rep_groupe option:selected").length === 0) {
        $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_fields_2']); ?>").removeClass("hidden");
    } else if ($("#user_ongoing_action").val() == "") {
        $("#add_folder_loader").removeClass("hidden");
        $("#user_ongoing_action").val("true");
        $("#new_rep_show_error").addClass("hidden");
        if ($("#new_rep_role").val() == undefined) {
            role_id = "<?php echo $_SESSION['fonction_id']; ?>";
        } else {
            role_id = $("#new_rep_role").val();
        }

        //prepare data
        var data = {"title": sanitizeString($('#new_rep_titre').val()),
            "complexity": sanitizeString($('#new_rep_complexite').val()), "is_pf": $('#pf_selected').val(),
            "parent_id": $("#new_rep_groupe option:selected").val(), "renewal_period":"0"};

        //send query
        $.post(
            "sources/folders.queries.php",
            {
                type   : "add_folder",
                data   : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key    : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                $("#user_ongoing_action").val("");
                //Check errors
                if (data[0].error == "error_group_exist") {
                    $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_group_exist']); ?>").removeClass("hidden");
                } else if (data[0].error == "error_html_codes") {
                    $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_html_codes']); ?>").removeClass("hidden");
                } else if (data[0].error == "error_title_only_with_numbers") {
                    $("#new_rep_show_error").html("<?php echo addslashes($LANG['error_only_numbers_in_folder_name']); ?>").removeClass("hidden");
                } else if (data[0].error != "") {
                    $("#new_rep_show_error").html(data[0].error).removeClass("hidden");
                } else {
                    $("#new_rep_titre").val("");
                    refreshTree(data[0].newid);
                    $("#div_ajout_rep").dialog("close");
                }
                $("#add_folder_loader").addClass("hidden");
            },
            "json"
           );
    }
}


function SupprimerFolder()
{
    if ($("#delete_rep_groupe_validate").is(':checked') === false) {
        $("#del_rep_show_error").html("<?php echo '<span class=\"fa fa-warning fa-lg\"></span>&nbsp;<\span>'.addslashes($LANG['please_confirm']); ?>").show(1).delay(2000).fadeOut(1000);
    } else if ($("#delete_rep_groupe").val() === "0") {
        $("#del_rep_show_error").html("<?php echo '<span class=\"fa fa-warning fa-lg\"></span>&nbsp;<\span>'.addslashes($LANG['error_group']); ?>").show(1).delay(2000).fadeOut(1000);
    } else if ($("#delete_rep_groupe option:selected").text() === "<?php echo $_SESSION['login']; ?>") {
        $("#del_rep_show_error").html("<?php echo '<span class=\"fa fa-warning fa-lg\"></span>&nbsp;<\span>'.addslashes($LANG['error_not_allowed_to']); ?>").show(1).delay(2000).fadeOut(1000);
    } else {
        $("#del_folder_loader").show();
        $.post(
            "sources/folders.queries.php",
            {
                type    : "delete_folder",
                id      : $("#delete_rep_groupe").val(),
                key        : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                $("#del_folder_loader").addClass("hidden");

                //decrypt data
                try {
                    data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    $("#div_loading").addClass("hidden");
                    $("#div_dialog_message_text")
                        .html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+
                        data);
                    $("#div_dialog_message").dialog("open");
                    return;
                }

                if (data.error !== "") {
                    if (data.error === "ERR_SUB_FOLDERS_EXIST") {
                        $("#del_rep_show_error").html("<?php echo '<span class=\"fa fa-warning fa-lg\"></span>&nbsp;<\span>'.addslashes($LANG['error_cannot_delete_subfolders_exist']); ?>").show(1).delay(3000).fadeOut(1000);

                    } else if (data.error === "ERR_FOLDER_NOT_ALLOWED") {
                        $("#del_rep_show_error").html("<?php echo '<span class=\"fa fa-warning fa-lg\"></span>&nbsp;<\span>'.addslashes($LANG['error_not_allowed_to']); ?>").show(1).delay(3000).fadeOut(1000);
                    }
                } else {
                    refreshTree(data.parent_id);
                    ListerItems(data.parent_id,'', 0);
                    $("#div_supprimer_rep").dialog("close");
                }
            }
       );
    }
}

function AfficherDetailsItem(id, salt_key_required, expired_item, restricted, display, open_edit, reload, id_tree)
{
    // If a request is already launched, then kill new.
    if ($("#request_ongoing").val() !== "") {
        request.abort();
        return;
    }
    id_tree = id_tree || "";
    salt_key_required = salt_key_required || 0;
    id_tree = id_tree || "";
    id_tree = id_tree || "";

    // Store status query running
    $("#request_ongoing").val("1");

    // If opening new item, reinit hidden fields
    if ($("#request_lastItem").val() != id) {
        $("#request_lastItem").val("");
        $("#item_editable").val("");
    }

    // Don't show details
    if (display === "no_display") {
        $("#item_details_nok").removeClass("hidden");
        $("#item_details_ok").addClass("hidden");
        $("#item_details_expired").addClass("hidden");
        $("#item_details_expired_full").addClass("hidden");
        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_url, #menu_button_copy_link").attr("disabled","disabled");
        $("#request_ongoing").val("");
        return false;
    }
    $("#div_loading").removeClass("hidden");
    if ($("#is_admin").val() == "1") {
        $('#menu_button_edit_item,#menu_button_del_item,#menu_button_copy_item').attr('disabled', 'disabled');
    }

    if ($("#edit_restricted_to") != undefined) {
        $("#edit_restricted_to").val("");
    }

    // Check if personal SK is needed and set
    if (($('#recherche_group_pf').val() === "1" && $('#personal_sk_set').val() === "0") && salt_key_required === "1") {
        $("#set_personal_saltkey_warning").html("<div style='font-size:16px;'><span class='fa fa-warning fa-lg'></span>&nbsp;</span><?php echo addslashes($LANG['alert_message_personal_sk_missing']); ?></div>").show(1).delay(2500).fadeOut(1000);
        $('#div_set_personal_saltkey').dialog('open');

        //$("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='fa fa-warning fa-lg mi-red'></span>&nbsp;<\/span><?php echo addslashes($LANG['alert_message_personal_sk_missing']); ?><\/div>");
        $("#div_loading").addClass("hidden");
        //$("#div_dialog_message").dialog("open");
        $("#request_ongoing").val("");
        return false;
    } else if ($('#recherche_group_pf').val() === "0" || ($('#recherche_group_pf').val() === "1" && $('#personal_sk_set').val() === "1")) {
        // Double click
        if (open_edit === "1" && $("#item_editable").val() === "1" && reload !== "1") {
            $("#request_ongoing").val("");
            open_edit_item_div(
                <?php if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === "1") {
    echo 1;
} else {
    echo 0;
}?>
            );
        } else if ($("#request_lastItem").val() == id && reload != 1) {
            $("#request_ongoing").val("");
            LoadingPage();
            return;
        } else {
            $("#timestamp_item_displayed").val("");
            var data = {
                "id" : id,
                "folder_id" : $('#hid_cat').val(),
                "salt_key_required" : $('#recherche_group_pf').val(),
                "salt_key_set" : $('#personal_sk_set').val(),
                "expired_item" : expired_item === undefined ? "" : expired_item,
                "restricted" : restricted === undefined ? "" : restricted,
                "page" : "items"
            };

            //Send query
            $.post(
                "sources/items.queries.php",
                {
                    type : 'show_details_item',
                    data : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key  : "<?php echo $_SESSION['key']; ?>"
                },
                function(data_raw) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data_raw , "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        $("#div_loading").addClass("hidden");
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />"+data_raw);
                        $("#div_dialog_message").show();
                        return;
                    }

                    if (data.error != "") {
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />"+data.error);
                        $("#div_dialog_message").show();
                    }

                    // reset password shown info
                    $("#pw_shown").val("0");

                    // show some info on top
                    if (data.auto_update_pwd_frequency != "0") var auto_update_pwd = "<i class='fa fa-shield tip' title='<?php echo addslashes($LANG['server_auto_update_password_enabled_tip']); ?>'></i>&nbsp;<b>"+data.auto_update_pwd_frequency+"</b>&nbsp;|&nbsp;";
                    else var auto_update_pwd = "";
                    $("#item_viewed_x_times").html(auto_update_pwd+"&nbsp;<i class='fa fa-sticky-note-o tip' title='Number of times item was displayed'></i>&nbsp;<b>"+data.viewed_no+"</b>");

                    // Show timestamp
                    $("#timestamp_item_displayed").val(data.timestamp);

                    //Change the class of this selected item
                    if ($("#selected_items").val() != "") {
                        $("#fileclass"+$("#selected_items").val()).removeClass("fileselected");
                    }
                    $("#selected_items").val(data.id);

                    //Show saltkey
                    if (data.edit_item_salt_key == "1") {
                        $("#edit_item_salt_key").show();
                    } else {
                        $("#edit_item_salt_key").addClass("hidden");
                    }

                    // clean some not used fields
                    //$("#item_history_log, #edit_past_pwds, #hid_files, #item_edit_list_files").html("");

                    //Show detail item
                    if (data.show_detail_option == "0") {
                        $("#item_details_ok").removeClass("hidden");
                        $("#item_details_expired, #item_details_expired_full").addClass("hidden");
                    }if (data.show_detail_option == "1") {
                        $("#item_details_ok, #item_details_expired").removeClass("hidden");
                        $("#item_details_expired_full").addClass("hidden");
                    } else if (data.show_detail_option == "2") {
                        $("#item_details_ok, #item_details_expired, #item_details_expired_full").addClass("hidden");
                    }
                    $("#item_details_nok").addClass("hidden");
                    $("#fileclass"+data.id).addClass("fileselected");
                    $("item_editable").val(0);

                    if (data.show_details == "1" && data.show_detail_option != "2") {
                        //unprotect data
                        data.login = unsanitizeString(data.login);

                        $("#id_files").html("");

                        //Display details
                        $("#id_label").html(data.label);
                        $("#hid_label").val(unsanitizeString(data.label));
                        if (data.pw === "") {
                            $("#id_pw").html("");
                        } else {
                            $("#id_pw").html('<?php echo $var['hidden_asterisk']; ?>');
                        }
                        $("#hid_pw").val(unsanitizeString(data.pw));
                        if (data.url != "") {
                            $("#id_url").html(data.url+data.link);
                            $("#hid_url").val(data.url);
                        } else {
                            $("#id_url").html("");
                            $("#hid_url").val("");
                        }
                        $("#id_desc").html(data.description);
                        $("#hid_desc").val(data.description);
                        $("#id_login").html(data.login);
                        $("#hid_login").val(data.login);
                        $("#id_email").html(data.email);
                        $("#hid_email").val(data.email);
                        //prepare nice list of users / groups
                        var tmp_arr = data.id_restricted_to.split(";");
                        var html_users = "";
                        for (var i=0; i<tmp_arr.length; i++) {
                            if (tmp_arr[i] !== "") html_users += "<span class='round-grey'><i style='margin-right:2px;' class='fa fa-user fa-sm'></i>"+tmp_arr[i]+"</span>";
                        }
                        var tmp_arr = data.id_restricted_to_roles.split(";");
                        var html_groups = "";
                        for (var i=0; i<tmp_arr.length; i++) {
                            if (tmp_arr[i] !== "") html_groups += "<span class='round-grey'><i style='margin-right:2px;' class='fa fa-group fa-sm'></i>"+tmp_arr[i]+"</span>";
                        }
                        $("#id_restricted_to").html(
                            html_users+
                            html_groups
                        );
                        $("#hid_restricted_to").val(data.id_restricted_to);
                        $("#hid_restricted_to_roles").val(data.id_restricted_to_roles);
                        $("#id_tags").html(data.tags);
                        // extract real tags list
                        var item_tag = "";
                        $("span.item_tag").each(function(){
                            if (item_tag == "") item_tag = $(this).text();
                            else item_tag += " "+$(this).text();
                        });
                        $("#hid_tags").val(item_tag);
                        $("#hid_anyone_can_modify").val(data.anyone_can_modify);
                        $("#id_categorie").val(data.folder);
                        $("#id_item").val(data.id);
                        $("#id_kbs").html(data.links_to_kbs);
                        $(".tip").tooltipster({
                            maxWidth: 400,
                            contentAsHTML: true,
                            multiple: true
                        });

                        // ---
                        // Show Field values
                        $(".fields").val("");
                        $(".fields_div").html("");
                        // If no CF then hide
                        if (data.fields === "") {
                            $(".tr_fields").addClass("hidden");
                        } else {
                            $(".tr_cf, .tr_fields").removeClass("hidden");
                            var liste = data.fields.split('_|_');
                            for (var i=0; i<liste.length; i++) {
                                var field = liste[i].split('~~'),
				                fieldValue = field[1];

                                if (field[3] == 'checkbox'){
                                    fieldValue = ((fieldValue == 'on') || (fieldValue == 'true') || fieldValue == true);
                                }

                                $("#cf_tr_" + field[0] + ", #tr_catfield_" + field[2]).show();
                                $('#hid_field_' + field[0] + '_' + field[2]).val(fieldValue);
                                if (field[3] === "masked") {
                                    $('#id_field_' + field[0] + '_' + field[2])
                                        .html('<?php echo $var['hidden_asterisk']; ?>');
                                } else {
                                    $('#id_field_' + field[0] + '_' + field[2])
                                        .html(fieldValue);
                                }
                            }
                        }

                        //Anyone can modify button
                        if (data.anyone_can_modify == "1") {
                            $("#edit_anyone_can_modify").attr('checked', true);
                            $("#new_history_entry_form").show();
                        } else {
                            $("#edit_anyone_can_modify").attr('checked', false);
                            $("#new_history_entry_form").addClass("hidden");
                        }

                        //Show to be deleted in case activated
                        if (data.to_be_deleted == "not_enabled") {
                            $("#edit_to_be_deleted").addClass("hidden");
                        } else {
                            $("#edit_to_be_deleted").show();
                            if (data.to_be_deleted != "") {
                                $("#edit_enable_delete_after_consultation").attr("checked",true);
                                if (data.to_be_deleted_type == 2) {
                                    $("#edit_times_before_deletion").val("");
                                    $("#edit_deletion_after_date").val(data.to_be_deleted);
                                } else {
                                    $("#edit_times_before_deletion").val(data.to_be_deleted);
                                    $("#edit_deletion_after_date").val("");
                                }
                            } else {
                                $("#edit_enable_delete_after_consultation").attr("checked",false);
                                $("#edit_times_before_deletion, #edit_deletion_after_date").val("");
                            }
                        }

                        //manage buttons
                        if ($("#user_is_read_only").val() == 1) {
                            $('#menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                        } else if (data.user_can_modify == 0) {
                            $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                        } else if (data.restricted == "1" || data.user_can_modify == "1") {
                            //$("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item").prop("disabled", false);
                            var param = "#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item";
                            $("#new_history_entry_form").show();
                        } else {
                            //$("#menu_button_add_item, #menu_button_copy_item").prop("disabled", false);
                            var param = "#menu_button_del_item, #menu_button_copy_item";
                            $("#new_history_entry_form").show();
                        }
                        //$("#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history").prop("disabled", false);

                        // disable share button for personal folder
                        if ($("#recherche_group_pf").val() == 1) {
                            $("#menu_button_share, #menu_button_otv").attr('disabled', 'disabled');
                        } else {
                            $("#menu_button_share, #menu_button_otv").prop("disabled", false);
                        }

                        //Manage to deleted information
                        if (data.to_be_deleted != 0 && data.to_be_deleted != null && data.to_be_deleted != "not_enabled") {
                            $('#item_extra_info')
                                .html("<b><i class='fa fa-bell-o mi-red'></i></b>&nbsp;")
                                .attr("title", "<?php echo addslashes($LANG['automatic_deletion_activated']); ?>");
                            $('#item_extra_info').tooltipster({multiple: true});
                        } else {
                            $('#item_extra_info').html("");
                        }

                        if (data.notification_status == 0 && data.id_user == <?php echo $_SESSION['user_id']; ?>) {
                            $('#menu_button_notify')
                                .prop("disabled", false)
                                .attr('title','<?php echo addslashes($LANG['enable_notify']); ?>')
                                .attr('onclick','notify_click(\'true\')');
                            $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                        } else if (data.notification_status == 1 && data.id_user == <?php echo $_SESSION['user_id']; ?>) {
                            $('#menu_button_notify')
                                .prop("disabled", false)
                                .attr('title','<?php echo addslashes($LANG['disable_notify']); ?>')
                                .attr('onclick','notify_click(\'false\')');
                            $('#div_notify').attr('class', '<i class="fa fa-bell-slash mi-red"></i>&nbsp;');
                            $('#item_extra_info').html("<i><i class=\'fa fa-bell mi-green\'></i>&nbsp;<?php echo addslashes($LANG['notify_activated']); ?></i>");
                        } else {
                            $('#menu_button_notify').attr('disabled', 'disabled');
                            $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                        }

                        //Prepare clipboard copies
                        if (data.pw != "") {
                            var clipboard_pw = new Clipboard("#menu_button_copy_pw, #button_quick_pw_copy", {
                                text: function() {
                                    return (unsanitizeString(data.pw));
                                }
                            });
                            clipboard_pw.on('success', function(e) {
                                $("#message_box").html("<?php echo addslashes($LANG['pw_copied_clipboard']); ?>").show().fadeOut(1000);
                                itemLog("item_password_copied");

                                e.clearSelection();
                            });

                            $("#button_quick_pw_copy").removeClass("hidden");
                        } else {
                            $("#button_quick_pw_copy").addClass("hidden");
                        }
                        if (data.login != "") {
                            var clipboard_login = new Clipboard("#menu_button_copy_login, #button_quick_login_copy", {
                                text: function() {
                                    return (data.login);
                                }
                            });
                            clipboard_login.on('success', function(e) {
                                $("#message_box").html("<?php echo addslashes($LANG['login_copied_clipboard']); ?>").show().fadeOut(1000);

                                e.clearSelection();
                            });
                            $("#button_quick_login_copy").removeClass("hidden");
                        } else {
                            $("#button_quick_login_copy").addClass("hidden");
                        }
                        // #525
                        if (data.url != "") {
                            var clipboard_url = new Clipboard("#menu_button_copy_url", {
                                text: function() {
                                    return unsanitizeString(data.url);
                                }
                            });
                            clipboard_url.on('success', function(e) {
                                $("#message_box").html("<?php echo addslashes($LANG['url_copied_clipboard']); ?>").show().fadeOut(1000);

                                e.clearSelection();
                            });
                        }

                        //prepare link to clipboard
                        var clipboard_link = new Clipboard("#menu_button_copy_link", {
                            text: function() {
                                return "<?php echo $SETTINGS['cpassman_url']; ?>"+"/index.php?page=items&group="+data.folder+"&id="+data.id;
                            }
                        });
                        clipboard_link.on('success', function(e) {
                            $("#message_box").html("<?php echo addslashes($LANG['url_copied']); ?>").show().fadeOut(1000);

                            e.clearSelection();
                        });


                        //set if user can edit
                        if (data.restricted == "1" || data.user_can_modify == "1") {
                            $("#item_editable").val(1);
                        }

                        //Manage double click
                        if (open_edit === true && (data.restricted == "1" || data.user_can_modify == "1")) {
                            open_edit_item_div(
                            <?php if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] == 1) {
    echo 1;
} else {
    echo 0;
}?>);
                        }

                        // tags
                        $(".round-grey").addClass("ui-state-highlight ui-corner-all");

                        // continue loading data
                        showDetailsStep2(id, param);

                    } else if (data.show_details === "1" && data.show_detail_option === "2") {
                        $("#item_details_nok").addClass("hidden");
                        $("#item_details_ok").addClass("hidden");
                        $("#item_details_expired_full").show();
                        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");
                        $("#div_loading").addClass("hidden");
                    } else {
                        //Dont show details
                        $("#item_details_nok").removeClass("hidden");
                        $("#item_details_nok_restriction_list").html('<div style="margin:10px 0 0 20px;"><b><?php echo addslashes($LANG['author']); ?>: </b>' + data.author + '<br /><b><?php echo addslashes($LANG['restricted_to']); ?>: </b>' + data.restricted_to + '<br /><br /><u><a href="#" onclick="SendMail(\'request_access_to_author\',\'' + data.id + ',' + data.id_user + '\',\'<?php echo $_SESSION['key']; ?>\',\'<?php echo addslashes($LANG['forgot_my_pw_email_sent']); ?>\')"><?php echo addslashes($LANG['request_access_ot_item']); ?></a></u></div>');
                        $("#item_details_ok").addClass("hidden");
                        $("#item_details_expired").addClass("hidden");
                        $("#item_details_expired_full").addClass("hidden");
                        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");
                        $("#div_loading").addClass("hidden");
                    }
                    $("#request_ongoing").val("");
                }
           );

            if (id_tree != "" && id_tree != $("#hid_cat").val()) {
                refreshTree(id_tree, "0");
            }

           // statistic
           /*$.post(
                "sources/main.queries.php",
                {
                    type                : 'item_stat',
                    id                  : id,
                    scope                : "item"
                },
                function(data) {

                }
            );*/
       }
    //Store Item id shown
    $("#request_lastItem").val(id);
    }
}


/*
* Loading Item details step 2
*/
function showDetailsStep2(id, param)
{
    $("#div_loading").removeClass("hidden");
    $.post(
        "sources/items.queries.php",
        {
        type    : "showDetailsStep2",
        id      : id
        },
        function(data) {
            //decrypt data
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                $("#div_loading").addClass("hidden");
                $("#request_ongoing").val("");
                $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);
                $("#div_dialog_message").dialog("open");

                return;
            }

            if (data.error !== "") {
                $("#div_dialog_message_text").html(data.error_text);
                $("#div_dialog_message").show();
                return false;
            }

            $("#item_history_log").html(htmlspecialchars_decode(data.history));
            $("#edit_past_pwds").attr('title', htmlspecialchars_decode(data.history_of_pwds));
            $("#edit_past_pwds_div").html(htmlspecialchars_decode(data.history_of_pwds));

            $("#id_files").html(data.files_id);
            $("#hid_files").val(data.files_id);
            $("#item_edit_list_files").html(data.files_edit);

            //$("#div_last_items").html(htmlspecialchars_decode(data.div_last_items));

            // function calling image lightbox when clicking on link
            $("a.image_dialog").click(function(event) {
                event.preventDefault();
                PreviewImage($(this).attr("href"),$(this).attr("title"));
            });

            //Set favourites icon
            if (data.favourite == "1") {
                $("#menu_button_add_fav").attr("disabled","disabled");
                $("#menu_button_del_fav").prop("disabled", false);
            } else {
                $("#menu_button_add_fav").prop("disabled", false);
                $("#menu_button_del_fav").attr("disabled","disabled");
            }

            // set indicator if item has change proposal
            if (parseInt(data.has_change_proposal) > 0) {
                $("#item_extra_info").prepend('<i class="fa fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo addslashes($LANG['item_has_change_proposal']); ?>" onclick=""></i>&nbsp;');
            }

            $(param).prop("disabled", false);
            $("#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history").prop("disabled", false);
            $("#div_loading").addClass("hidden");

            $(".tip").tooltipster({multiple: true});

            // refresh
            if ($("#hid_cat").val() !== "") {
                refreshListLastSeenItems();
            }
         }
     );
};

/*
   * FUNCTION
   * Launch an action when clicking on a quick icon
   * $action = 0 => Make not favorite
   * $action = 1 => Make favorite
*/
var quick_icon_query_status = true;
function ActionOnQuickIcon(id, action)
{
    if (quick_icon_query_status === true) {
        quick_icon_query_status = false;
        //change quick icon
        if (action == 1) {
            $("#quick_icon_fav_"+id).html("<i class='fa fa-sm fa-star mi-yellow' onclick='ActionOnQuickIcon("+id+",0)'></i>");
        } else if (action == 0) {
            $("#quick_icon_fav_"+id).html("<i class='fa fa-sm fa-star-o' onclick='ActionOnQuickIcon("+id+",1)'></i>");
        }


        //Send query
        LoadingPage();
        $.post("sources/items.queries.php",
            {
                type    : 'action_on_quick_icon',
                id      : id,
                action  : action
            },
            function(data) {
                LoadingPage();
                displayMessage("<?php echo addslashes($LANG['alert_message_done']); ?>");
                quick_icon_query_status = true;
            }
       );
    }
}

//###########
//## FUNCTION : prepare new folder dialogbox
//###########
function open_add_group_div()
{
    if ($("#user_is_read_only").length && $("#user_is_read_only").val() == 1) {
        displayMessage("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }

    $("#div_loading").removeClass("hidden");

    // check if read only or forbidden
    if (RecupComplexite($('#hid_cat').val(), 0, "create_folder") == 0) {
        return false;
    }

    //Select the actual folder in the dialogbox
    $('#new_rep_groupe option[value=' + $('#hid_cat').val() + ']').prop('selected', true);
    $('#div_ajout_rep').dialog('open');
    $("#div_loading").addClass("hidden");
}

//###########
//## FUNCTION : prepare editing folder dialogbox
//###########
function open_edit_group_div()
{
    if ($("#user_is_read_only").length && $("#user_is_read_only").val() == 1) {
        displayMessage("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }

    $("#div_loading").removeClass("hidden");

    // check if read only or forbidden
    if (RecupComplexite($('#hid_cat').val(), 0, "edit_folder") == 0) {
        return false;
    }

    //Select the actual forlder in the dialogbox
    $('#edit_folder_folder option[value=' + $('#hid_cat').val() + ']').prop('selected', true);
    $('#edit_folder_title').val($.trim($('#edit_folder_folder :selected').text()));
    $('#edit_folder_complexity').val($('#complexite_groupe').val());
    $('#div_editer_rep').dialog('open');
    $("#div_loading").addClass("hidden");
}

//###########
//## FUNCTION : prepare moving folder dialogbox
//###########
function open_move_group_div()
{
    if ($.inArray($("#hid_cat").val(), $("#personal_visible_groups_list").val().split(',')) != -1 && $("#personal_sk_set").val() === "0") {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['error_personal_sk_expected']); ?>");
        return false;
    }

    if ($("#hid_cat").val() == "<?php if (isset($_SESSION['personal_folders'][0])) {
    echo $_SESSION['personal_folders'][0];
} else {
    echo "";
}
?>") {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }
    if ($("#user_is_read_only").length && $("#user_is_read_only").val() == 1) {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }
    $("#div_loading").removeClass("hidden");

    // check if read only or forbidden
    if (RecupComplexite($('#hid_cat').val(), 0) == 0) return false;

    //Select the actual folder in the dialogbox
    //$('#move_folder_id option[value=' + $('#hid_cat').val() + ']').prop('selected', true);
    $('#move_folder_title').html($.trim($('#move_folder_id :selected').text())+" [id"+$('#hid_cat').val()+"]");
    $('#move_folder_id').val(0);
    $('#div_move_folder').dialog('open');
    $("#div_loading").addClass("hidden");
}

//###########
//## FUNCTION : prepare delete folder dialogbox
//###########
function open_del_group_div()
{
    if ($("#user_is_read_only").length && $("#user_is_read_only").val() == 1) {
        displayMessage("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }
    $("#div_loading").removeClass("hidden");


    // check if read only or forbidden
    if (RecupComplexite($('#hid_cat').val(), 0, "delete_folder") == 0) {
        return false;
    } else {
        $('#div_supprimer_rep').dialog('open');
        $('#delete_rep_groupe option[value=' + $('#hid_cat').val() + ']').prop('selected', true);
        $("#div_loading").addClass("hidden");
    }
}

//###########
//## FUNCTION : prepare new item dialogbox
//###########
function open_add_item_div()
{
    LoadingPage();

    //Check if personal SK is needed and set
    if ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 0) {
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($LANG['alert_message_personal_sk_missing']); ?><\/div>");
        LoadingPage();
        $("#div_dialog_message").dialog("open");
    } else if ($("#hid_cat").val() == "") {
        LoadingPage();
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($LANG['error_no_selected_folder']); ?><\/div>").dialog("open");
    } else if ($('#recherche_group_pf').val() == 0 || ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)) {
        // is user read only and it is not a personal folder
        if ($('#recherche_group_pf').val() == 0 && $("#user_is_read_only").length && $("#user_is_read_only").val() == "1") {
            displayMessage("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
            LoadingPage();
            return false;
        }

        //Select the actual forlder in the dialogbox
        $('#categorie').val($('#hid_cat').val());

        //Get the associated complexity level
        var compReturn = RecupComplexite($('#hid_cat').val(), 0);

        // exclude because user is read only
        if (compReturn === 0) {
            $("#div_loading").addClass("hidden");
            return false;
        }

        //Show WYGIWYS editor
        CKEDITOR.replace(
            "desc",
            {
                toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]],
                height: 100,
                language: "<?php echo $_SESSION['user_language_code']; ?>"
            }
        );

        // prepare select2 for users
        $("#annonce_liste_destinataires").select2({
            language: "<?php echo $_SESSION['user_language_code']; ?>"
        });

        if ($("#recherche_group_pf").val() == 1) {
            $("#div_editRestricted").addClass("hidden");
        } else {
            $("#div_editRestricted").show();
        }

        //open dialog
        $("#div_formulaire_saisi_info").addClass("hidden").html("");
        $("#div_formulaire_saisi").dialog("open");
    }
}

//###########
//## FUNCTION : prepare editing item dialogbox
//###########
function open_edit_item_div(restricted_to_roles)
{
    // is user read only and it is not a personal folder
    if (
        ($('#recherche_group_pf').val() === "0" && $("#user_is_read_only").length && $("#user_is_read_only").val() === "1")
        || (
            ($("#access_level").val() === "1" || $("#access_level").val() === "3")
            && $('#recherche_group_pf').val() === "0"
        )
    ) {
        // Exclude the case where the user is in his PF with PSK set
        if ($('#recherche_group_pf').val() === "1" && $("#personal_sk_set").val() === "1") {
            // do nothing
        } else {
            displayMessage("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
            return false;
        }
    }

    // If no Item selected, no edition possible
    if ($("#selected_items").val() == "") {
        displayMessage("<?php echo addslashes($LANG['none_selected_text']); ?>");
        return false;
    }
    $("#div_loading").removeClass("hidden");

    // Get complexity level for this folder
    // and stop edition if Item edited by another user
    var compReturn = RecupComplexite($('#hid_cat').val(), 1);

    if (compReturn == 0) {
        if (CKEDITOR.instances["edit_desc"]) {
            CKEDITOR.instances["edit_desc"].destroy();
        }
        if (CKEDITOR.instances["desc"]) {
            CKEDITOR.instances["desc"].destroy();
        }
        $("#div_loading").addClass("hidden");
        return;
    }

    // Check if Item has changed since loaded
    if (CheckIfItemChanged() == 1) {
        var tmp = $("#"+$("#selected_items").val()).attr("ondblclick");
        tmp = tmp.substring(20,tmp.indexOf(")"));
        tmp = tmp.replace(/'/g, "").split(',');
        AfficherDetailsItem(tmp[0], tmp[1], tmp[2], tmp[3], tmp[4], 1, 1);
        $("#div_loading").addClass("hidden");
        return;
    }

    // Show WYGIWYG editor
    CKEDITOR.replace(
        "edit_desc",
        {
            toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]],
            height: 100,
            language: "<?php echo $_SESSION['user_language_code']; ?>"
        }
   );
    CKEDITOR.instances["edit_desc"].setData($('#hid_desc').val());

    $('#edit_display_title').html($('#hid_label').val());
    $('#edit_label').val($('#hid_label').val());
    $('#edit_desc').html($('#hid_desc').val());
    $('#edit_pw1, #edit_pw2').val($('#hid_pw').val());
    $("#edit_visible_pw").text($('#hid_pw').val());
    $('#edit_item_login').val($('#hid_login').val());
    $('#edit_email').val($('#hid_email').val());
    $('#edit_url').val($('#hid_url').val());
    $('#edit_categorie').val($('#id_categorie').val());
    if ($('#edit_restricted_to').val() != undefined) {
        $('#edit_restricted_to').val($('#hid_restricted_to').val());
    }
    if ($('#edit_restricted_to_roles').val() != undefined) {
        $('#edit_restricted_to_roles').val($('#hid_restricted_to_roles').val());
    }
    $('#edit_tags').val($('#hid_tags').val());
    if ($('#hid_anyone_can_modify').val() == "1") {
        $('#edit_anyone_can_modify').attr("checked","checked");
        $('#edit_anyone_can_modify').button("refresh");
    } else {
        $('#edit_anyone_can_modify').attr("checked",false);
        $('#edit_anyone_can_modify').button("refresh");
    }
    // fields display
    if ($('.fields').val() != undefined && $("#display_categories").val() != "") {

        $('.fields').each(function(i){
            id = $(this).attr('id').split('_');
	    var thisFieldName = 'edit_field_' + id[2] + '_' + id[3],
            thisField = $('#' + thisFieldName),
            fieldType = thisField.attr("type"),
            fieldRawValue = $('#hid_field_' + id[2] + '_' + id[3]).val(),
            fieldValue = htmlspecialchars_decode(fieldRawValue);

	    if (fieldType == 'checkbox') {
		    thisField.prop('checked', (fieldRawValue == 'true'));
	    } else {
		    thisField.val(fieldValue);
        }
        });
    }

    //Get list of people in restriction list
    if ($("#recherche_group_pf").val() == 1) {
        $("#div_editRestricted").addClass("hidden");
    } else {
        $("#div_editRestricted").show();
        // tick selected users / roles
        if ($('#edit_restricted_to').val() != undefined) {
            var list = $('#hid_restricted_to').val().split(';');
            for (var i=0; i<list.length; i++) {
                var elem = list[i];
                if (elem != "") {
                    $(".folder_rights_user_edit").each(function() {
                        if ($(this).attr("id") == elem) {
                            $(this).prop("checked", true);
                            exit;
                        }
                    });
                }
            }
        }

        if ($('#edit_restricted_to').val() != undefined) {
            $('#edit_restricted_to_list').empty();
            if (restricted_to_roles == 1) {
                //add optgroup
                var optgroup = $('<optgroup>');
                optgroup.attr('label', "<?php echo addslashes($LANG['users']); ?>");
                $("#edit_restricted_to_list option:last").wrapAll(optgroup);
            }
            /*var liste = $('#input_liste_utilisateurs').val().split(';');
            for (var i=0; i<liste.length; i++) {
                var elem = liste[i].split('#');
                if (elem[0] != "") {
                    $("#edit_restricted_to_list").append("<option value='"+elem[0]+"'>"+elem[1]+"</option>");
                    var index = $('#edit_restricted_to').val().lastIndexOf(elem[1]+";");
                    if (index != -1) {
                        $("#edit_restricted_to_list option[value="+elem[0]+"]").attr('selected', true);
                    }
                }
            }*/
        }

        //Add list of roles if option is set
        if (restricted_to_roles == 1 && $('#edit_restricted_to').val() != undefined) {
            var j = i;
            //add optgroup
            var optgroup = $('<optgroup>');
            optgroup.attr('label', "<?php echo addslashes($LANG['roles']); ?>");

            var liste = $('#input_list_roles').val().split(';');
            for (var i=0; i<liste.length; i++) {
                var elem = liste[i].split('#');
                if (elem[0] != "") {
                    $("#edit_restricted_to_list").append("<option value='role_"+elem[0]+"'>"+elem[1]+"</option>");
                    var index = $('#edit_restricted_to_roles').val().lastIndexOf(elem[1]+";");
                    if (index != -1) {
                        $("#edit_restricted_to_list option[value='role_"+elem[0]+"']").attr('selected', true);
                    }
                    if (i==0) $("#edit_restricted_to_list option:last").wrapAll(optgroup);
                }
                j++;
            }
        }
    }

    // prepare select2 for users
    $("#edit_annonce_liste_destinataires").select2({
        language: "<?php echo $_SESSION['user_language_code']; ?>"
    });

    // disable folder selection if PF
    if ($('#recherche_group_pf').val() == "1") {
        //$("#edit_categorie").prop("disabled", true);
    } else {
        $("#edit_categorie").prop("disabled", false);
    }

    //open dialog
    $("#div_formulaire_edition_item_info").addClass("hidden").html("");
    $("#div_formulaire_edition_item").dialog("open");
}

//###########
//## FUNCTION : prepare new item dialogbox
//###########
function open_del_item_div()
{
    // is user read only
    if (
        ($('#recherche_group_pf').val() === "0" && $("#user_is_read_only").length && $("#user_is_read_only").val() === "1")
        || (
            ($("#access_level").val() === "1" || $("#access_level").val() === "2" || $("#access_level").val() === "3")
            && $('#recherche_group_pf').val() === "0"
        )
    ) {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }

    if ($("#selected_items").val() != "") {
        $("#div_loading").removeClass("hidden");
        //Get the associated complexity level
        var compReturn = RecupComplexite($('#hid_cat').val(), 0);

        // exclude because user is read only
        if (compReturn == 0) {
            return false;
        }

        $("#div_loading").addClass("hidden");
        $('#div_del_item').dialog('open');
    } else {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['none_selected_text']); ?>");
    }
}

//###########
//## FUNCTION : prepare copy item dialogbox
//###########
function open_copy_item_to_folder_div()
{
    // is user read only
    if ($('#recherche_group_pf').val() == 0 && $("#user_is_read_only").length && $("#user_is_read_only").val() == "1") {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
        return false;
    }

    if ($("#selected_items").val() != "") {
        $('#copy_in_folder').val($("#hid_cat").val());
        $('#div_copy_item_to_folder').dialog('open');
    } else {
        displayMessage("<i class='fa fa-warning'></i>&nbsp;<?php echo addslashes($LANG['none_selected_text']); ?>");
    }
}


//###########
//## FUNCTION : Clear HTML tags from a string
//###########
function clear_html_tags()
{
    $.post(
        "sources/items.queries.php",
        {
            type    : "clear_html_tags",
            id_item  : $("#id_item").val()
        },
        function(data) {
            data = $.parseJSON(data);
            $("#edit_desc").val(data.description);
        }
   );
}

//###########
//## FUNCTION : Permits to delete an attached file
//###########
function delete_attached_file(file_id, confirm)
{
    if (parseInt(confirm) === 1) {
        // user has confirmed deletion
        $.post(
            "sources/items.queries.php",
            {
                type    : "delete_attached_file",
                file_id : file_id,
                key     : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                $("#span_edit_file_"+file_id).css("textDecoration", "line-through");
            }
       );
    } else {
        // Ask user to confirm
        $(".delete_me").remove();
        $(".file-eraser_icon").show();
        $("#delete-edit-file_"+file_id)
            .hide()
            .after(
                '<span class="delete_me">&nbsp;<span id="confirm-delete-edit-file_'+file_id+'" class="fa fa-thumbs-up tip" style="cursor:pointer;" onclick="delete_attached_file('+file_id+', 1)" title="<?php echo addslashes($LANG['confirm']);?>"></span>' +
                '&nbsp;<span id="cancel-delete-edit-file_'+file_id+'" class="fa fa-thumbs-down tip" style="cursor:pointer;" onclick="cancel_delete_attached_file('+file_id+')" title="<?php echo addslashes($LANG['cancel']);?>"></span>&nbsp;</span>'
            );
            $(".tip").tooltipster({multiple: true});
    }
}

/*
**
*/
function cancel_delete_attached_file(file_id)
{
    $(".delete_me").remove();
    $("#delete-edit-file_"+file_id).show();
}

//###########
//## FUNCTION : Permits to preview an attached image
//###########
PreviewImage = function(uri,title) {
    $("#div_loading").removeClass("hidden");
    $.post(
        "sources/items.queries.php",
        {
            type    : "image_preview_preparation",
            uri     : uri,
            title   : title,
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");

            $("#dialog_files").html('<img id="image_files" src="" />');
            //Get the HTML Elements
            imageDialog = $("#dialog_files");
            imageTag = $('#image_files');

            //Set the image src
            imageTag.attr("src", data.new_file);

            //When the image has loaded, display the dialog
            imageTag
            .error(function() {
                $("#div_loading").addClass("hidden");
                displayMessage("<?php echo "<i class='fa fa-exclamation-triangle fa-2x'></i>  ".addslashes($LANG['error_file_is_missing']); ?>");
            })
            .load(function() {
                $("#div_loading").addClass("hidden");
                imageDialog.dialog({
                    modal: true,
                    resizable: false,
                    draggable: false,
                    width: 'auto',
                    title: title,
                    open: function( event, ui ) {
                        // nothing to do
                    },
                    close: function (event, ui) {
                        // delete file
                        $.post(
                            "sources/main.queries.php",
                            {
                                type    : "file_deletion",
                                filename: data.file_path,
                                key     : "<?php echo $_SESSION['key']; ?>"
                            }
                        );
                    }
                });
            });
        }
    );
};

function notify_click(status)
{
    $.post("sources/items.queries.php",
    {
        type     : "notify_a_user",
        user_id : <?php echo $_SESSION['user_id']; ?>,
        status    : status,
        notify_type : 'on_show',
        notify_role : '',
        item_id : $('#id_item').val(),
        key        : "<?php echo $_SESSION['key']; ?>"
    },
    function(data) {
        if (data[0].error == "something_wrong") {
            $("#new_show_error").html('ERROR!!');
            $("#new_show_error").show();
        } else {
            $("#new_show_error").addClass("hidden");
            if (data[0].new_status == "true") {
                $('#menu_button_notify')
                    .attr('title','<?php echo addslashes($LANG['disable_notify']); ?>')
                    .attr('onclick','notify_click(\'false\')');
                $('#div_notify').attr('class', '<i class="fa fa-bell-slash mi-green"></i>&nbsp;');
                $('#item_extra_info').html("<?php echo addslashes($LANG['notify_activated']); ?>");
            } else if (data[0].new_status == "false") {
                $('#menu_button_notify')
                    .attr('title','<?php echo addslashes($LANG['enable_notify']); ?>')
                    .attr('onclick','notify_click(\'true\')');
                $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                $('#item_extra_info').html("");
            }
        }
    },
    "json"
    );
}

/*
** Checks if current item title is a duplicate in current folder
*/
function checkTitleDuplicate(itemTitle, checkInCurrentFolder, checkInAllFolders, textFieldId)
{
    $("#new_show_error").html("").addClass("hidden");
    $("#div_formulaire_saisi ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").button("enable");
    if (itemTitle != "") {
        if (checkInCurrentFolder == "1" || checkInAllFolders == "1") {
            //prepare data
            var data = {"label": itemTitle.replace(/"/g,'&quot;') , "idFolder": $('#hid_cat').val()};

            if (checkInCurrentFolder == "1") {
                var typeOfCheck = "same_folder";
            } else {
                var typeOfCheck = "all_folders";
            }

            // disable Save button
            $("#div_formulaire_saisi ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").button("disable");

            // send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "check_for_title_duplicate",
                    option  : typeOfCheck,
                    data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key     : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    if (data[0].duplicate != "1") {
                        $("#div_formulaire_saisi ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").button("enable");
                        // display title
                        $("#"+textFieldId).html(itemTitle.escapeHTML());
                    } else {
                        $("#label").focus();
                        $("#new_show_error").html("<?php echo addslashes($LANG['duplicate_title_in_same_folder']); ?>").show();
                    }
                }
            );
        } else {
            // display title
            $("#"+textFieldId).html(itemTitle.escapeHTML());
        }
    }
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
        .one("refresh.jstree", function (e, data) {
            data.instance.select_node("#li_"+node_to_select);
        });
        //.jstree("select_node", "#li_"+node_to_select);

    }

    if (refresh_visible_folders === 1) {
        $(this).delay(500).queue(function() {
            refreshVisibleFolders();
            $(this).dequeue();
        });
    }
}

/*
* refreshes the various lists of folders used in dialogboxes
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
                    html_visible = '<option value="0"><?php echo addslashes($LANG['root']);?></option>';
                    html_full_visible = '<option value="0"><?php echo addslashes($LANG['root']);?></option>';
                    html_active_visible = '<option value="0"><?php echo addslashes($LANG['root']);?></option>';
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


//###########
//## EXECUTE WHEN PAGE IS LOADED
//###########
$(function() {

    var clear_tp_clipboard = new Clipboard("#but_empty_clipboard", {
        text: function() {
            return "cleared";
        }
    });
    clear_tp_clipboard.on('success', function(e) {
        $("#message_box").html("super").show().fadeOut(1000);

        e.clearSelection();
    });

    $.ajaxSetup({
        error: function(jqXHR, exception) {
            if (jqXHR.status === 0) {
                console.log('Not connect.\nVerify Network.');
            } else if (jqXHR.status == 404) {
                alert('Requested page not found. [404]');
            } else if (jqXHR.status == 500) {
                alert('Internal Server Error [500].');
            } else if (exception === 'parsererror') {
                alert('Requested JSON parse failed.');
            } else if (exception === 'timeout') {
                alert('Time out error.');
            } else if (exception === 'abort') {
                alert('Ajax request aborted.');
            } else {
                alert('Uncaught Error.n' + jqXHR.responseText);
            }
        }
    });

    // manage item div resize
    $( "#item_details_scroll" ).resizable({handles: {'s': '#handle'}});
    $("#handle").dblclick(function() {
        var inner = $("#item_details_scroll").find('table');
        var current_height = $("#item_details_scroll").height();
        $("#item_details_scroll").animate({top:'+='+(current_height-inner.height())}, 0);
        $("#item_details_scroll").height(inner.outerHeight(true));
    });

    $('#toppathwrap').addClass("hidden");
    if ($(".tr_fields") != undefined) $(".tr_fields").addClass("hidden");
    //Expend/Collapse jstree
    $("#jstree_close").click(function() {
        $("#jstree").jstree("close_all");
    });
    $("#jstree_open").click(function() {
        $("#jstree").jstree("open_all");
    });
    $("#jstree_search").keypress(function(e) {
        if (e.keyCode == 13) {
            $("#jstree").jstree("search",$("#jstree_search").val());
        }
    });

    $(".quick_menu").menu({
        icons: { submenu: "no-icon" }
    });
    $(".quick_menu_left").menu({
        position: {
            my : "right top",
            at : "left top"
        }
    });

    $('.menu_200, .menu_150').on('blur', function () {
        $(this).addClass("hidden");
    });

    $("#pw_size, #edit_pw_size").spinner({
        min:   3,
        step:  1,
        numberFormat: "n"
    });

    //Disable menu buttons
    $('#menu_button_edit_item,#menu_button_del_item,#menu_button_add_fav,#menu_button_del_fav').attr('disabled', 'disabled');

    //DIsable more buttons if read only user
    if ($("#user_is_read_only").val() == 1) {
        $('#menu_button_add_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group').attr('disabled', 'disabled');
    }

    // Autoresize Textareas
    $(".items_tree, #items_content").addClass("ui-corner-all");

    //automatic height
    var window_height = $(window).height();
    $("#div_items, #content").height(window_height-130);
    $("#items_center").height(window_height-360);
    $("#items_list").height(window_height-410);
    $(".items_tree").height(window_height-140);
    $("#items_content").height(window_height-130);
    $("#jstree").height(window_height-185);

    //warning if screen height too short
    if (parseInt(window_height-440) <= 30) {
        $("#div_dialog_message_text").html("<?php echo addslashes($LANG['warning_screen_height']); ?>");
        $("#div_dialog_message").dialog('open');
    }

    //Evaluate number of items to display - depends on screen height
    if (parseInt($("#nb_items_to_display_once").val()) || $("#nb_items_to_display_once").val() == "max") {
        //do nothing ... good value
    } else {
        //adapt to the screen height
        $("#nb_items_to_display_once").val(Math.max(Math.round((window_height-450)/23),2));
    }

    // Build buttons
    $("#custom_pw, #edit_custom_pw").buttonset();
    $(".cpm_button, #anyone_can_modify, #annonce, #edit_anyone_can_modify, #edit_annonce, .button").button();

    // Launch items loading
    if ($("#jstree_group_selected").val() == "") {
        var first_group = 1;
    } else {
        var first_group = $("#jstree_group_selected").val();
    }

    if ($("#hid_cat").val() != "") {
        first_group = $("#hid_cat").val();
    }

    //load items
    if (parseInt($("#query_next_start").val()) > 0) start = parseInt($("#query_next_start").val());
    else start = 0;

    // load list of items
    if (first_group !== "") {
        ListerItems(first_group,'', start);
    }

    //Load item if needed and display items list
    if ($("#open_id").val() !== "") {
        AfficherDetailsItem($("#open_id").val());
        //refreshTree($("#hid_cat").val(), "0");
        $("#open_item_by_get").val("");
    }

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
                "Loading ..." : "<?php echo addslashes($LANG['loading']); ?>..."
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

    // load list of visible folders for current user
    $(this).delay(500).queue(function() {
        refreshVisibleFolders();
        $(this).dequeue();
    });

    $("#add_folder").click(function() {
        var posit = $('#item_selected').val();
        //alert($("ul").text());
    });

    $("#for_searchtext").addClass("hidden");
    $("#copy_pw_done").addClass("hidden");
    $("#copy_login_done").addClass("hidden");

    //PREPARE DIALOGBOXES
    //=> ADD A NEW GROUP
    $("#div_ajout_rep").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 280,
        title: "<?php echo addslashes($LANG['item_menu_add_rep']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['save_button']); ?>": function() {
                AddNewFolder();
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $("#new_rep_show_error").html("").addClass("hidden");
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $("#new_rep_show_error").addClass("hidden");
            $("#new_rep_show_error").html("");
            $(".ui-tooltip").siblings(".tooltip").remove();
        }
    });
    //<=
    //=> EDIT A GROUP
    $("#div_editer_rep").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 490,
        height: 280,
        title: "<?php echo addslashes($LANG['item_menu_edi_rep']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['save_button']); ?>": function() {
                //Do some checks
                $("#edit_rep_show_error").addClass("hidden");
                if ($("#edit_folder_title").val() == "") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($LANG['error_group_label']); ?>");
                    $("#edit_rep_show_error").show();
                } else if ($("#edit_folder_folder").val() == "0") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($LANG['error_group']); ?>");
                    $("#edit_rep_show_error").show();
                } else if ($("#edit_folder_complexity").val() == "") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($LANG['error_group_complex']); ?>");
                    $("#edit_rep_show_error").show();
                } else if (/^\d+$/.test($("#edit_folder_title").val())) {
                    $("#edit_rep_show_error").html("<?php echo addslashes($LANG['error_only_numbers_in_folder_name']); ?>");
                    $("#edit_rep_show_error").show();
                } else {
                    $("#edit_folder_loader").show();
                    $("#div_editer_rep ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", true);

                    //prepare data
                    var data = {"title": $('#edit_folder_title').val().replace(/"/g,'&quot;'),
                        "complexity": $('#edit_folder_complexity').val(),
                        "folder": $('#edit_folder_folder').val()};

                    //Send query
                    $.post(
                        "sources/items.queries.php",
                        {
                            type    : "update_folder",
                            data      : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key        : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            //check if format error
                            if (data[0].error == "") {
                                refreshTree($('#edit_folder_folder').val());
                                $("#folder_name_"+$('#edit_folder_folder').val()).text($('#edit_folder_title').val());
                                $("#path_elem_"+$('#edit_folder_folder').val()).text($('#edit_folder_title').val());
                                $("#fld_"+$('#edit_folder_folder').val()).html($('#edit_folder_title').val());
                                $("#edit_folder_title").val($('#edit_folder_title').val());
                                $("#div_editer_rep").dialog("close");
                            } else {
                                if (data[0].error === "ERR_TITLE_ONLY_WITH_NUMBERS") {
                                    $("#edit_rep_show_error").html('<?php echo addslashes($LANG['error_only_numbers_in_folder_name']); ?>').show();
                                } else {
                                    $("#edit_rep_show_error").html(data[0].error).show();
                                }

                            }
                            $("#edit_folder_loader").addClass("hidden");
                            $("#div_editer_rep ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                        },
                        "json"
                   );
                }
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $("#edit_folder_loader").addClass("hidden");
                $("#edit_rep_show_error").html("").addClass("hidden");
                $("#div_editer_rep ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
        }
    });
    //<=

    // =>
    $("#div_copy_item_to_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 250,
        title: "<?php echo addslashes($LANG['item_menu_copy_elem']); ?>",
        open: function( event, ui ) {
            $("#copy_in_folder").select2({
                language: "<?php echo $_SESSION['user_language_code']; ?>"
            });
            $(":button:contains('<?php echo addslashes($LANG['ok']); ?>')").prop("disabled", false);
            $("#copy_item_info")
                .addClass("ui-state-highlight ui-corner-all")
                .addClass("hidden");
            $(".ui-tooltip").siblings(".tooltip").remove();
            $("#div_copy_item_to_folder_item").html("<center>"+$("#id_label").html()+"</center>");
        },
        buttons: {
            "<?php echo addslashes($LANG['ok']); ?>": function() {
                $("#copy_item_info")
                    .addClass("ui-state-highlight ui-corner-all")
                    .removeClass("hidden")
                    .html("<span><?php echo addslashes($LANG['please_wait'])." <i class=\'fa fa-cog fa-spin'></i>"; ?></span>");
                $(":button:contains('<?php echo addslashes($LANG['ok']); ?>')").prop("disabled", true);
                //Send query
                $.post(
                    "sources/items.queries.php",
                    {
                        type        : "copy_item",
                        item_id     : $('#id_item').val(),
                        source_id   : $('#hid_cat').val(),
                        dest_id     : $('#copy_in_folder').val(),
                        key         : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //check if format error
                        if (data[0].error !== "") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show(1).delay(2000).fadeOut(1000);
                        }
                        //if OK
                        if (data[0].status == "ok") {
                            //window.location.href = "index.php?page=items&group="+$('#copy_in_folder').val()+"&id="+data[1].new_id;
                            ListerItems($('#copy_in_folder').val(),'', 0);
                            AfficherDetailsItem(data[1].new_id);
                            refreshTree($('#copy_in_folder').val());
                            $("#copy_in_folder").val("");
                            $("#div_copy_item_to_folder").dialog('close');
                        }
                        $("#copy_item_info").html('').addClass("hidden");
                    },
                    "json"
               );
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $("#copy_item_to_folder_show_error").html("").addClass("hidden");
                $("#div_copy_item_to_folder").dialog('close');
            }
        }
    });
    // <=

    //=> MOVE A GROUP
    $("#div_move_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 350,
        height: 250,
        title: "<?php echo addslashes($LANG['item_menu_mov_rep']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['save_button']); ?>": function() {
                //Do some checks
                $("#move_rep_show_error").addClass("hidden");
                if ($("#move_folder_id").val() == "0") {
                    $("#move_rep_show_error").html("<?php echo addslashes($LANG['error_group']); ?>");
                    $("#move_rep_show_error").show();
                } else if($('#hid_cat').val() === $('#move_folder_id').val()) {
                    // do not move to itself
                    $("#move_rep_show_error").html("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
                    $("#move_rep_show_error").show();
                } else {
                    $("#move_folder_loader").show();
                    $("#div_editer_rep ~ .ui-dialog-buttonpane")
                        .find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')")
                        .prop("disabled", true);

                    //prepare data
                    var data = {"source_folder_id": $('#hid_cat').val(),
                        "target_folder_id": $('#move_folder_id').val()};

                    //Send query
                    $.post(
                        "sources/items.queries.php",
                        {
                            type    : "move_folder",
                            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key     : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            //check if format error
                            if (data[0].error == "") {
                                $("#div_move_folder ~ .ui-dialog-buttonpane")
                                    .find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                                ListerItems($('#hid_cat').val(), "", 0);
                                $("#move_folder_loader").addClass("hidden");
                                refreshTree();
                                $("#div_move_folder").dialog("close");
                            } else {
                                $("#move_rep_show_error").html(data[0].error).show();
                            }
                            $("#move_folder_loader").addClass("hidden");
                        },
                        "json"
                   );
                }
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $("#edit_rep_show_error").html("").addClass("hidden");
                $("#div_editer_rep ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                $("#move_rep_show_error").html("").addClass("hidden");
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
        }
    });
    //<=


    //=> COPY OF FOLDER
    $("#div_copy_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 290,
        title: "<?php echo addslashes($LANG['copy_folder']); ?>",
        close: function () {
            $("#copy_folder_source_id, #copy_folder_destination_id").children('option').remove();
            $("#div_copy_folder_msg")
                .html('')
                .removeClass("ui-state-highlight")
                .addClass("hidden");
        },
        open: function(event,ui) {
            $("#div_copy_folder ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);

            // get list of folders
                $.post(
                    "sources/folders.queries.php",
                    {
                        type    : "get_list_of_folders",
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        $("#div_loading").addClass("hidden");

                        //display to user
                        $("#copy_folder_source_id, #copy_folder_destination_id").append(data[0].list_folders);

                        $("#copy_folder_source_id").val($("#hid_cat").val());
                    },
                    "json"
                );
        },
        buttons: {
            "<?php echo addslashes($LANG['save_button']); ?>": function() {
                //Do some checks
                if ($("#copy_folder_source_id").val() === "" || $("#copy_folder_destination_id").val() === "") {
                    $("#div_copy_folder_msg")
                        .html('<i class="fa fa-warning"></i>&nbsp;<?php echo addslashes($LANG['error_must_enter_all_fields']); ?>')
                        .addClass("ui-state-error")
                        .show().delay(2000).fadeOut(1000);
                        return false;
                }

                if ($("#copy_folder_source_id").val() === $("#copy_folder_destination_id").val()) {
                    $("#div_copy_folder_msg")
                        .html('<i class="fa fa-warning"></i>&nbsp;<?php echo addslashes($LANG['error_source_and_destination_are_equal']); ?>')
                        .addClass("ui-state-error")
                        .show().delay(2000).fadeOut(1000);
                        return false;
                }


                $("#div_copy_folder_msg")
                    .html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo addslashes($LANG['please_wait']); ?>')
                    .addClass("ui-state-highlight")
                    .show();

                //prepare data
                var data = {"source_folder_id": $('#copy_folder_source_id').val(),
                    "target_folder_id": $('#copy_folder_destination_id').val()};

                //Send query
                $.post(
                    "sources/folders.queries.php",
                    {
                        type    : "copy_folder",
                        data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //check if format error
                        if (data[0].error == "") {
                            $("#div_copy_folder ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                            refreshTree();
                            $("#div_copy_folder").dialog("close");
                        } else {
                            $("#div_copy_folder_msg").html(data[0].error).show().delay(2000).fadeOut(1000);
                        }
                    },
                    "json"
                );
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $("#div_copy_folder_msg").html("").addClass("hidden");
                $("#div_copy_folder ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
                $(this).dialog('close');
            }
        }
    });
    //<=

    //=> DELETE A GROUP
    $("#div_supprimer_rep").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 600,
        height: 230,
        title: "<?php echo addslashes($LANG['item_menu_del_rep']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['delete']); ?>": function() {
                SupprimerFolder();
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
        },
        close: function() {
            $("#delete_rep_groupe_validate").prop("checked", false);
            $("#del_rep_show_error").html("").addClass("hidden");
        }
    });
    //<=
    //=> ADD A NEW ITEM
    $("#div_formulaire_saisi").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 505,
        height: 680,
        title: "<?php echo addslashes($LANG['item_menu_add_elem']); ?>",
        open: function( event, ui ) {
            $(".ui-dialog-buttonpane button:contains('<?php echo addslashes($LANG['save_button']); ?>')").button("disabled");
        },
        buttons: {
            "<?php echo addslashes($LANG['save_button']); ?>": function() {
                $("#div_loading").removeClass("hidden");
                $(".ui-dialog-buttonpane button:contains('<?php echo addslashes($LANG['save_button']); ?>')").button("enable");
                AjouterItem();
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                //Clear upload queue
                $('#item_file_queue').html('');
                //Select 1st tab
                $("#item_tabs").tabs({ selected: 0 });
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $("#label").focus();
            $("#visible_pw").html("");
            $("#item_tabs").tabs("option", "active", 0);
            $(".ui-tooltip").siblings(".tooltip").remove();

            // show tab fields ? Not if PersonalFolder
            if ($("#recherche_group_pf").val() == 1) {
                if ($("#form_tab_fields") != undefined)
                    $("#item_tabs").tabs("option", "hidden", 3);
            } else {
                if ($("#form_tab_fields") != undefined && $("#display_categories").val() != 1)
                    $("#item_tabs").tabs("option", "show", 3);
            }

            // hide complexity if PF
            if ($("#pf_selected").val() == 1) {
                $("#expected_complexity").addClass("hidden");
            } else {
                $("#expected_complexity").show();
            }

            $("#categorie").select2({
                language: "<?php echo $_SESSION['user_language_code']; ?>"
            });
        },
        close: function(event,ui) {
            if (CKEDITOR.instances["desc"]) {
                CKEDITOR.instances["desc"].destroy();
            }
            $("#item_upload_list").html("");
            $(".item_field").val("");  // clean values in Fields
            $("#pw1").focus();
            $("#new_show_error").html("").addClass("hidden");
            $(".ui-dialog-buttonpane button:contains('<?php echo addslashes($LANG['save_button']); ?>')").button("enable");
            $("#div_loading").addClass("hidden");
        }
    });
    //<=
    //=> EDITER UN ELEMENT
    $("#div_formulaire_edition_item").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 505,
        height: 680,
        title: "<?php echo addslashes($LANG['item_menu_edi_elem']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['save_button']); ?>": function() {
                $("#div_formulaire_edition_item ~ .ui-dialog-buttonpane").find("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", true);
                EditerItem();
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                //Clear upload queue
                $('#item_edit_file_queue').html('');
                //Select 1st tab
                $("#item_edit_tabs").tabs({ selected: 0 });
                $("#div_loading").addClass("hidden");
                //Close dialog box
                $(this).dialog('close');
            }
        },
        close: function(event,ui) {
            if (CKEDITOR.instances["edit_desc"]) {
                CKEDITOR.instances["edit_desc"].destroy();
            }
            if (CKEDITOR.instances["desc"]) {
                CKEDITOR.instances["desc"].destroy();
            }
            $("#div_loading, #edit_show_error").addClass("hidden");
            $("#item_edit_upload_list").html("");
            $(".edit_item_field").val("");  // clean values in Fields
            //Unlock the Item
            $.post(
                "sources/items.queries.php",
                {
                    type    : "free_item_for_edition",
                    id      : $("#id_item").val(),
                    key        : "<?php echo $_SESSION['key']; ?>"
                }
            );
            $("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);
            $(".delete_me").remove();
            $(".file-eraser_icon").show();
            $('#edit_visible_pw').addClass("hidden");
        },
        open: function(event,ui) {
            //refresh pw complexity
            $("#item_edit_tabs").tabs( "option", "active",1  );
            $("#edit_pw1").first().focus();
            $("#item_edit_tabs").tabs( "option", "active",0  );
            $(".ui-tooltip").siblings(".tooltip").remove();

            // show tab fields ? Not if PersonalFolder
            if ($("#recherche_group_pf").val() == 1) {
                if ($("#edit_item_more") != undefined) $("#edit_item_more").addClass("hidden");
            } else {
                if ($("#edit_item_more") != undefined && $("#display_categories").val() != 1)
                    $("#edit_item_more").show();
            }
            $("button:contains('<?php echo addslashes($LANG['save_button']); ?>')").prop("disabled", false);

            // hide complexity if PF
            if ($("#pf_selected").val() == 1) {
                $("#edit_expected_complexity").addClass("hidden");
            } else {
                $("#edit_expected_complexity").show();
            }

            $("#edit_categorie").select2({
                language: "<?php echo $_SESSION['user_language_code']; ?>"
            });

            // get list of Users
            $.post(
                "sources/items.queries.php",
                {
                    type    : "build_list_of_users",
                    key     : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        $("#div_loading").addClass("hidden");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />"+data);
                        $("#div_dialog_message").show();
                        return;
                    }

                    if (data.error === "" && $('#edit_restricted_to').val() != undefined ) {
                        var list = data.list.split(';');
                        for (var i=0; i<list.length; i++) {
                            var elem = list[i].split('#');
                            if (elem[0] != "") {
                                $("#edit_restricted_to_list").append("<option value='"+elem[0]+"'>"+elem[1]+"</option>");
                                var index = $('#edit_restricted_to').val().lastIndexOf(elem[1]+";");
                                if (index != -1) {
                                    $("#edit_restricted_to_list option[value="+elem[0]+"]").attr('selected', true);
                                }
                            }
                        }
                    }
                }
           );
        }
    });
    //<=
    //=> SUPPRIMER UN ELEMENT
    $("#div_del_item").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 220,
        title: "<?php echo addslashes($LANG['item_menu_del_elem']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['del_button']); ?>": function() {
                $.post(
                    "sources/items.queries.php",
                    {
                        type        : "del_item",
                        id          : $("#id_item").val(),
                        categorie   : $('#hid_cat').val(),
                        label       : $("#hid_label").val(),
                        key         : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        $("#div_loading").removeClass("hidden");

                        // refresh list of items
                        $("#full_items_list").html("");
                        ListerItems($('#hid_cat').val(), "", 0)

                        // reload tree
                        refreshTree($('#hid_cat').val());

                        // clean fields
                        $("#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs").html("");
                        $("#button_quick_login_copy, #button_quick_pw_copy").addClass("hidden");
                        $("#selected_items").val("");

                        $("#div_loading").addClass("hidden");
                    }
               );
                $(this).dialog('close');
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
            $("#div_del_item_selection").html("<center>"+$("#id_label").html()+"</center>");
        }
    });
    //<=
    //=> SHOW LINK COPIED DIALOG
    $("#div_item_copied").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 200,
        title: "<?php echo addslashes($LANG['admin_main']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
        }
    });
    //<=
    //=> SHOW HISTORY DIALOG
    $("#div_item_history").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 650,
        height: 400,
        title: "<?php echo addslashes($LANG['history']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();

            // load content
            const data = {"id":$("#id_item").val()};
            $.post(
                "sources/items.queries.php",
                {
                    type    : "load_item_history",
                    data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key     : "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        $("#div_loading").addClass("hidden");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />"+data);
                        $("#div_dialog_message").show();
                        return;
                    }

                    if (data.error === "") {
                        $("#item_history_log").html(data.new_html);
                    }
                }
           );
        }
    });
    //<=
    //=> SHOW SHARE DIALOG
    $("#div_item_share").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 240,
        title: "<?php echo addslashes($LANG['share']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['send']); ?>": function() {
                $("#div_item_share_error")
                    .addClass("hidden")
                    .html('');
                $("#div_item_share_status").removeClass("hidden");

                // Check if email format is ok
                if (IsValidEmail($("#item_share_email").val())) {
                    $("#div_item_share_status").show();
                    $.post(
                        "sources/items.queries.php",
                        {
                            type    : "send_email",
                            id      : $("#id_item").val(),
                            receipt    : $("#item_share_email").val(),
                            cat      : "share_this_item",
                            key        : "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            $("#div_item_share_status").addClass("hidden");
                            if (data[0].error === "") {
                                $("#div_item_share_init").addClass("hidden");
                                $("#div_item_share_error").removeClass("ui-state-error").addClass("ui-state-highlight");
                                $("#div_item_share_error").html("<?php echo addslashes($LANG['share_sent_ok']); ?>").show();

                                // close dialog
                                $(this).delay(1500).queue(function() {
                                    $("#div_item_share").dialog('close');
                                    $(this).dequeue();
                                });
                            } else {
                                $("#div_item_share_error").removeClass("ui-state-highlight").addClass("ui-state-error");
                                $("#div_item_share_error").html(data[0].message).show();
                            }
                        },
                        "json"
                   );
                } else {
                    $("#div_item_share_error")
                        .html("<?php echo addslashes($LANG['bad_email_format']); ?>")
                        .removeClass("hidden");
                }
            },
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
            $("#div_item_share_error").addClass("ui-state-error hidden").html('');
            $("#div_item_share_init").removeClass("hidden");
            $("#item_share_email").val('');
        }
    });
    //<=
    //=> SHOW ITEM UPDATED DIALOG
    $("#div_item_updated").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 300,
        height: 100,
        title: "<?php echo addslashes($LANG['share']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['ok']); ?>": function() {

            }
        },
        open: function(event,ui) {
            $(".ui-tooltip").siblings(".tooltip").remove();
        }
    });
    //<=
    //=> SHOW SHARE DIALOG
    $("#div_suggest_change").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 750,
        height: 450,
        title: "<?php echo addslashes($LANG['suggest_password_change']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['ok']); ?>": function() {
                $("#div_suggest_change_wait").html('<i class="fa fa-cog fa-spin fa-2x"></i>').show().removeClass("ui-state-error");

                // do checks
                if (!IsValidEmail($("#email_change").val()) && $("#email_change").val() !== "") {
                    $("#div_suggest_change_wait").html('<i class="fa fa-warning fa-lg"></i>&nbsp;<?php echo addslashes($LANG['email_format_is_not_correct']); ?>').show(1).delay(2000).fadeOut(1000).addClass("ui-state-error");
                    return false;
                }
                if (!validateURL($("#url_change").val()) && $("#url_change").val() !== "") {
                    $("#div_suggest_change_wait").html('<i class="fa fa-warning fa-lg"></i>&nbsp;<?php echo addslashes($LANG['url_format_is_not_correct']); ?>').show(1).delay(2000).fadeOut(1000).addClass("ui-state-error");
                    return false;
                }

                // prepare changes
                var data = {"label": $("#label_change").val(), "pwd": $("#pwd_change").val(),
                    "url": $("#url_change").val(), "login": $("#login_change").val(),
                    "email": $("#email_change").val(), "folder": $("#hid_cat").val(),
                    "comment": $("#comment_change").val(), "item_id": $("#id_item").val()};

                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "suggest_item_change",
                        data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        id      : $("#id_item").val(),
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        if (data[0].error === "") {
                            $("#div_suggest_change_wait").html("<?php echo addslashes($LANG['suggestion_done']); ?>").show(1).delay(1500).fadeOut(1000);

                            // set indicator if item has change proposal
                            $("#item_extra_info").prepend('<i class="fa fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo addslashes($LANG['item_has_change_proposal']); ?>" onclick=""></i>&nbsp;');
                            $(".tip").tooltipster({multiple: true});

                            setTimeout(
                                function() {
                                    $("#div_suggest_change").dialog("close");
                                },
                                500
                            );
                        }
                    },
                    "json"
               );
            },
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $("#div_suggest_change_html")
            .html(
                '<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['label']); ?></label><input type="text" id="label_change" value="'+$("#hid_label").val()+'" class="input_text_80 ui-widget-content ui-corner-all">' +
                '<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['pw']); ?></label><input type="text" id="pwd_change" value="" class="input_text_80 ui-widget-content ui-corner-all">' +
                '&nbsp;<i class="fa fa-info-circle fa-lg tip" title="<?php echo addslashes($LANG['suggest_change_password_blank']); ?>"></i>' +
                //'<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['description']); ?></label><textarea id="description_change_change" class="input_text_80 ui-widget-content ui-corner-all">'+$("#hid_desc").val()+'</textarea>' +
                '<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['index_login']); ?></label><input type="text" id="login_change" value="'+$("#hid_login").val()+'" class="input_text_80 ui-widget-content ui-corner-all">' +
                '<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['email']); ?></label><input type="text" id="email_change" value="'+$("#hid_email").val()+'" class="input_text_80 ui-widget-content ui-corner-all">' +
                '<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['url']); ?></label><input type="text" id="url_change" value="'+$("#hid_url").val()+'" class="input_text_80 ui-widget-content ui-corner-all">' +
                '<label class="form_label_100" style="padding:4px;"><?php echo addslashes($LANG['comment']); ?></label><input type="text" id="comment_change" value="" class="input_text_80 ui-widget-content ui-corner-all">'
            )
            .show();
            $(".tip").tooltipster({multiple: true});
        }
    });
    //<=

    // => ATTACHMENTS INIT
    var uploader_attachments = new plupload.Uploader({
        runtimes : "html5,flash,silverlight,html4",
        browse_button : "item_attach_pickfiles",
        container : "item_upload",
        max_file_size : "<?php
if (strrpos($SETTINGS['upload_maxfilesize'], "mb") === false) {
    echo $SETTINGS['upload_maxfilesize']."mb";
} else {
    echo $SETTINGS['upload_maxfilesize'];
}
?>",
        chunk_size : "1mb",
        dragdrop : true,
        url : "sources/upload/upload.attachments.php",
        flash_swf_url : "includes/libraries/Plupload/Moxie.swf",
        silverlight_xap_url : "includes/libraries/Plupload/Moxie.xap",
        filters : [
            {title : "Image files", extensions : "<?php echo $SETTINGS['upload_imagesext']; ?>"},
            {title : "Package files", extensions : "<?php echo $SETTINGS['upload_pkgext']; ?>"},
            {title : "Documents files", extensions : "<?php echo $SETTINGS['upload_docext']; ?>"},
            {title : "Other files", extensions : "<?php echo $SETTINGS['upload_otherext']; ?>"}
        ],<?php
if ($SETTINGS['upload_imageresize_options'] == 1) {
?>
        resize : {
            width : <?php echo $SETTINGS['upload_imageresize_width']; ?>,
            height : <?php echo $SETTINGS['upload_imageresize_height']; ?>,
            quality : <?php echo $SETTINGS['upload_imageresize_quality']; ?>
        },
<?php
}
?>
        init: {
            BeforeUpload: function (up, file) {
                $("#item_upload_wait").removeClass("hidden");

                if ($("#random_id").val() == "") {
                    var post_id = CreateRandomString(9,"num_no_0");
                    $("#random_id").val(post_id);
                }

                up.setOption('multipart_params', {
                    PHPSESSID : "<?php echo $_SESSION['user_id']; ?>",
                    itemId : $("#random_id").val(),
                    type_upload : "item_attachments",
                    edit_item : false,
                    user_token: $("#item_user_token").val(),
                    files_number: $("#files_number").val()
                });
            },
            UploadComplete: function(up, files) {
                $("#item_upload_wait").addClass("hidden");
                $("#files_number").val(0);
            }
        }
    });

    // Uploader options
    uploader_attachments.bind("UploadProgress", function(up, file) {
        $("#" + file.id + " b").html(file.percent + "%");
        $("#remove_" + file.id).remove();
    });
    uploader_attachments.bind("Error", function(up, err) {
        $("#item_upload_list").html(
            "<div class=\'ui-state-error ui-corner-all\' style=\'padding:2px;\'>Error: " + err.code +
            ", Message: " + err.message +
            (err.file ? ", File: " + err.file.name : "") +
            "</div>"
        );
        up.refresh(); // Reposition Flash/Silverlight
    });
    uploader_attachments.bind("+", function(up, file) {
        $("#" + file.id + " b").html("100%");
    });

    // Load edit uploaded click
    $("#item_attach_uploadfiles").click(function(e) {
        // generate and save token
        $.post(
            "sources/main.queries.php",
            {
                type : "save_token",
                size : 25,
                capital: true,
                numeric: true,
                ambiguous: true,
                reason: "item_attachments",
                duration: 10
            },
            function(data) {
                $("#item_user_token").val(data[0].token);
                uploader_attachments.start();
            },
            "json"
        );
        e.preventDefault();
    });
    uploader_attachments.init();
    uploader_attachments.bind('FilesAdded', function(up, files) {
        $.each(files, function(i, file) {
            $('#item_upload_list').append(
                '<div id= file.id><span id="remove_' + file.id + '>[<a href=\'#\' onclick=\'$(\"#' + file.id + '\").remove();\'>-</a>]</span> ' +
                file.name + ' (' + plupload.formatSize(file.size) + ')' +
            '</div>');
            $("#files_number").val(parseInt($("#files_number").val())+1);
        });
        up.refresh(); // Reposition Flash/Silverlight
    });

    // Prepare uplupload object for attachments upload
    var edit_uploader_attachments = new plupload.Uploader({
        runtimes : "html5,flash,silverlight,html4",
        browse_button : "item_edit_attach_pickfiles",
        container : "item_edit_upload",
        max_file_size : "<?php
if (strrpos($SETTINGS['upload_maxfilesize'], "mb") === false) {
    echo $SETTINGS['upload_maxfilesize']."mb";
} else {
    echo $SETTINGS['upload_maxfilesize'];
}
?>",
        chunk_size : "1mb",
        dragdrop : true,
        url : "sources/upload/upload.attachments.php",
        flash_swf_url : "includes/libraries/Plupload/Moxie.swf",
        silverlight_xap_url : "includes/libraries/Plupload/Moxie.xap",
        filters : [
            {title : "Image files", extensions : "<?php echo $SETTINGS['upload_imagesext']; ?>"},
            {title : "Package files", extensions : "<?php echo $SETTINGS['upload_pkgext']; ?>"},
            {title : "Documents files", extensions : "<?php echo $SETTINGS['upload_docext']; ?>"},
            {title : "Other files", extensions : "<?php echo $SETTINGS['upload_otherext']; ?>"}
        ],<?php
if ($SETTINGS['upload_imageresize_options'] == 1) {
        ?>
        resize : {
            width : <?php echo $SETTINGS['upload_imageresize_width']; ?>,
            height : <?php echo $SETTINGS['upload_imageresize_height']; ?>,
            quality : <?php echo $SETTINGS['upload_imageresize_quality']; ?>
        },<?php
}
?>
        init: {
            BeforeUpload: function (up, file) {
                $("#item_edit_upload_wait").removeClass("hidden");

                up.setOption('multipart_params', {
                    PHPSESSID : "<?php echo $_SESSION['user_id']; ?>",
                    itemId : $('#selected_items').val(),
                    type_upload : "item_attachments",
                    edit_item : true,
                    user_token: $("#item_user_token").val(),
                    files_number: $("#edit_files_number").val()
                });
            },
            UploadComplete: function(up, files) {
                $("#item_edit_upload_wait").addClass("hidden");
                $("#edit_files_number").val(0);
            }
        }
    });

    // Uploader options
    edit_uploader_attachments.bind("UploadProgress", function(up, file) {
        $("#" + file.id + " b").html(file.percent + "%");
        $("#edit_remove_" + file.id).remove();
    });
    edit_uploader_attachments.bind("Error", function(up, err) {
        $("#item_edit_upload_list").html(
            "<div class=\'ui-state-error ui-corner-all\' style=\'padding:2px;\'>Error: " + err.code +
            ", Message: " + err.message +
            (err.file ? ", File: " + err.file.name : "") +
            "</div>"
        );
        up.refresh(); // Reposition Flash/Silverlight
    });
    edit_uploader_attachments.bind("+", function(up, file) {
        $("#" + file.id + " b").html("100%");
        $("#edit_remove_" + file.id).remove();
    });

    // Load edit uploaded click
    $("#item_edit_attach_uploadfiles").click(function(e) {
        // generate and save token
        $.post(
            "sources/main.queries.php",
            {
                type : "save_token",
                size : 25,
                capital: true,
                numeric: true,
                ambiguous: true,
                reason: "item_attachments",
                duration: 30
            },
            function(data) {
                $("#item_user_token").val(data[0].token);
                edit_uploader_attachments.start();
            },
            "json"
        );

        e.preventDefault();
    });
    edit_uploader_attachments.init();
    edit_uploader_attachments.bind('FilesAdded', function(up, files) {
        $.each(files, function(i, file) {
            $('#item_edit_upload_list').append(
                '<div id= file.id><span id="edit_remove_' + file.id + '>[<a href=\'#\' onclick=\'$(\"#' + file.id + '\").remove();\'>-</a>]</span> ' +
                file.name + ' (' + plupload.formatSize(file.size) + ')' +
            '</div>');
            $("#edit_files_number").val(parseInt($("#edit_files_number").val())+1);
        });
        up.refresh(); // Reposition Flash/Silverlight
    });

    //Password meter for item creation
    $("#pw1").simplePassMeter({
        "requirements": {},
        "container": "#pw_strength",
        "defaultText" : "<?php echo addslashes($LANG['index_pw_level_txt']); ?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo addslashes($LANG['complex_level0']); ?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo addslashes($LANG['complex_level1']); ?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo addslashes($LANG['complex_level2']); ?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo addslashes($LANG['complex_level3']); ?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo addslashes($LANG['complex_level4']); ?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo addslashes($LANG['complex_level5']); ?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo addslashes($LANG['complex_level6']); ?>"
            }
        ]
    });
    $('#pw1').bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#mypassword_complex").val(score);
        }
    }).change({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#mypassword_complex").val(score);
        }
    });

    $("#tabs-02").on(
        "score.simplePassMeter",
        "#pw1",
        function(jQEvent, score) {
            $("#mypassword_complex").val(score);
        }
    );


    //Password meter for item update
    $("#edit_pw1").simplePassMeter({
        "requirements": {},
        "container": "#edit_pw_strength",
        "defaultText" : "<?php echo addslashes($LANG['index_pw_level_txt']); ?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo addslashes($LANG['complex_level0']); ?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo addslashes($LANG['complex_level1']); ?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo addslashes($LANG['complex_level2']); ?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo addslashes($LANG['complex_level3']); ?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo addslashes($LANG['complex_level4']); ?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo addslashes($LANG['complex_level5']); ?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo addslashes($LANG['complex_level6']); ?>"
            }
        ]
    });
    $('#edit_pw1').on(
        "score.simplePassMeter", function(jQEvent, score) {
            $("#edit_mypassword_complex").val(score);
        }
    );

    //Text search watermark
    var tbval = $('#jstree_search').val();
    $('#jstree_search').focus(function() { $(this).val('');});
    $('#jstree_search').blur(function() { $(this).val(tbval);});
    $('#search_item').focus(function() { $(this).val('');});
    $('#search_item').blur(function() { $(this).val(tbval);});

    //add date selector
    $(".datepicker").datepicker({
        dateFormat:"<?php echo str_replace(array("Y", "M"), array("yy", "mm"), $SETTINGS['date_format']); ?>",
        changeMonth: true,
        changeYear: true
    });

    //autocomplete for TAGS
    $("#item_tags, #edit_tags")
        .focus()
        .bind( "keydown", function( event ) {
            if ( event.keyCode === $.ui.keyCode.TAB &&
                    $( this ).data( "autocomplete" ).menu.active ) {
                event.preventDefault();
            }
        })
        .autocomplete({
            //source: 'sources/items.queries.php?type=autocomplete_tags',
            source: function( request, response ) {
                $.getJSON( "sources/items.queries.php?type=autocomplete_tags&t=1", {
                    term: extractLast( request.term )
                }, response );
            },
            focus: function() {
                // prevent value inserted on focus
                return false;
            },
            search: function() {
                var term = extractLast( this.value );
            },
            select: function( event, ui ) {
                var terms = split( this.value );
                // remove the current input
                terms.pop();
                // add the selected item
                terms.push( ui.item.value );
                // add placeholder to get the comma-and-space at the end
                terms.push( "" );
                this.value = terms.join( " " );

                return false;
            }
        }
    );

    //DIALOG FOR OFFLINE MODE
    $("#dialog_offline_mode").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 350,
        title: "<?php echo addslashes($LANG['offline_menu_title']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['button_offline_generate']); ?>": function() {
                generateOfflineFile();
            },
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog("close");
            }
        },
        close: function() {
            $("#div_offline_mode").html("<i class=\"fa fa-cog fa-spin fa-2x\"></i>");
        }
    });

    //DIALOG FOR EXPORT FILE
    $("#dialog_export_file").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 350,
        title: "<?php echo addslashes($LANG['print_out_menu_title']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['button_export_file']); ?>": function() {
                exportItemsToFile();
            },
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog("close");
            }
        },
        close: function() {
            $("#div_export_file").html("<i class=\"fa fa-cog fa-spin fa-2x\"></i>");
        }
    });

    //DIALOG FOR IMPORT FILE
    $("#dialog_import_file").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 600,
        height: 500,
        title: "<?php echo addslashes($LANG['import_csv_menu_title']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog("close");
            }
        },
        close: function() {
            $("#div_import_file").html("<i class=\"fa fa-cog fa-spin fa-2x\"></i>");
        }
    });


    // DIALOG BOX FOR PERSONAL PASSWORDS UPGRADE
    $("#dialog_upgrade_personal_passwords").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 300,
        title: "<?php echo addslashes($LANG['upgrade_needed']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['admin_action_db_backup_start_tip']); ?>": function() {
                $("#dialog_upgrade_personal_passwords_status").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo addslashes($LANG['please_wait']); ?>&nbsp;...&nbsp;<span id="reencryption_progress">0%</span>').attr("class","").show();
                $.post(
                    "sources/utils.queries.php",
                    {
                        type    : "reencrypt_personal_pwd_start",
                        user_id : "<?php echo $_SESSION['user_id']; ?>",
                        key     : "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        if (data[0].error != "") {
                            $("#dialog_upgrade_personal_passwords_status").html(data[0].error).addClass("ui-state-error").show();
                        } else {
                            reEncryptPersonalPwds(data[0].pws_list, data[0].currentId, data[0].nb);
                        }
                    },
                    "json"
                );
            },
            "<?php echo addslashes($LANG['cancel_button']); ?>": function() {
                $(this).dialog("close");
            }
        }
    });

    //DIALOG FOR SSH
    $("#dialog_ssh").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 620,
        height: 500,
        title: "<?php echo addslashes($LANG['update_server_password']); ?>",
        buttons: {
            "<?php echo addslashes($LANG['close']); ?>": function() {
                $(this).dialog("close");
            }
        },
        close: function() {
            $("#div_ssh").html("<i class=\'fa fa-cog fa-spin fa-2x\'></i>&nbsp;<b><?php echo addslashes($LANG['please_wait']); ?></b>");
        }
    });

    //Simulate a CRON activity (only 8 secs after page loading)
    setTimeout(
        function() {
            // send email
            $.post(
                "sources/main.queries.php",
                {
                    type : "send_waiting_emails",
                    key     : "<?php echo $_SESSION['key']; ?>"
                }
            );

            // send statistics
            $.post(
                "sources/main.queries.php",
                {
                    type : "sending_statistics",
                    key     : "<?php echo $_SESSION['key']; ?>"
                }
            );
        },
        8000
    );

    NProgress.done();
});

// show password during longpress
var mouseStillDown = false;
$('#item_details_ok').on('mousedown', '.unhide_masked_data', function(event) {
    mouseStillDown = true;
     showPwdContinuous($(this).attr('id'));
}).on('mouseup', '.unhide_masked_data', function(event) {
     mouseStillDown = false;
}).on('mouseleave', '.unhide_masked_data', function(event) {
     mouseStillDown = false;
});
var showPwdContinuous = function(elem_id){
    if(mouseStillDown){
        $('#'+elem_id).html('<span style="cursor:none;">' + $('#h'+elem_id).val() + '</span>');
        setTimeout("showPwdContinuous('"+elem_id+"')", 50);
        // log password is shown
        if (elem_id === "id_pw" && $("#pw_shown").val() == "0") {
            itemLog("item_password_shown");
            $("#pw_shown").val("1");
        }
    } else {
        $('#'+elem_id).html('<?php echo $var['hidden_asterisk']; ?>');
        $('.tip').tooltipster({multiple: true});
    }
};

var showPwd = function(){
    $("#visible_pw, #edit_visible_pw").toggle();
};

/*
* permits to save
*/
function itemLog(log_case, item_id)
{
    item_id = item_id || $('#id_item').val();
    $.post(
        "sources/items.logs.php",
        {
            type        : log_case,
            id_item     : item_id,
            folder_id   : $('#hid_cat').val(),
            hid_label   : $('#hid_label').val(),
            key         : "<?php echo $_SESSION['key']; ?>"
        }
    );
}

function htmlspecialchars_decode (string, quote_style)
{
    if (string != null && string != "") {
        // Convert special HTML entities back to characters
        var optTemp = 0, i = 0, noquotes= false;
        if (typeof quote_style === 'undefined') {        quote_style = 2;
        }
        string = string.toString().replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        var OPTS = {
            'ENT_NOQUOTES': 0,
            'ENT_HTML_QUOTE_SINGLE' : 1,
            'ENT_HTML_QUOTE_DOUBLE' : 2,
            'ENT_COMPAT': 2,
            'ENT_QUOTES': 3,
            'ENT_IGNORE' : 4
        };
        if (quote_style === 0) {
            noquotes = true;
        }
        if (typeof quote_style !== 'number') { // Allow for a single string or an array of string flags
            quote_style = [].concat(quote_style);
            for (i=0; i < quote_style.length; i++) {
                // Resolve string input to bitwise e.g. 'PATHINFO_EXTENSION' becomes 4
                if (OPTS[quote_style[i]] === 0) {
                    noquotes = true;
                } else if (OPTS[quote_style[i]]) {
                    optTemp = optTemp | OPTS[quote_style[i]];
                }
            }
            quote_style = optTemp;
        }
        if (quote_style & OPTS.ENT_HTML_QUOTE_SINGLE) {
            string = string.replace(/&#0*39;/g, "'"); // PHP doesn't currently escape if more than one 0, but it should
            // string = string.replace(/&apos;|&#x0*27;/g, "'"); // This would also be useful here, but not a part of PHP
        }
        if (!noquotes) {
            string = string.replace(/&quot;/g, '"');
        }

        string = string.replace(/&nbsp;/g, ' ');

        // Put this in last place to avoid escape being double-decoded    string = string.replace(/&amp;/g, '&');
    }
    return string;
}

/**
 * Permit to load dynamically the list of Items
 */
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
            $("#message_box").html("<?php echo addslashes($LANG['login_copied_clipboard']); ?>").show().fadeOut(1000);
            e.clearSelection();
        });

        clipboard = new Clipboard('.mini_pw');
        clipboard.on('success', function(e) {
            $("#message_box").html("<?php echo addslashes($LANG['pw_copied_clipboard']); ?>").show().fadeOut(1000);
            itemLog("item_password_copied", e.trigger.dataset.clipboardId);
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
                return $("<div class='ui-widget-header' id='drop_helper'>"+"<?php echo addslashes($LANG['drag_drop_helper']); ?>"+"</div>");
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
                                displayMessage("<?php echo addslashes($LANG['psk_required']); ?>");
                            } else {
                                displayMessage("<?php echo addslashes($LANG['error_not_allowed_to']); ?>");
                            }
                            ui.draggable.removeClass("hidden");
                            return false;
                        }
                        //increment / decrement number of items in folders
                        $("#itcount_"+data[0].from_folder).text(Math.floor($("#itcount_"+data[0].from_folder).text())-1);
                        $("#itcount_"+data[0].to_folder).text(Math.floor($("#itcount_"+data[0].to_folder).text())+1);
                        $("#id_label, #item_viewed_x_times, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to, #id_tags, #id_kbs").html("");
                        displayMessage("<?php echo addslashes($LANG['alert_message_done']); ?>");
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
                        optgroup.attr('label', "<?php echo addslashes($LANG['users']); ?>");
                        $(".folder_rights_user").wrapAll(optgroup);
                    }
                }
                //Add list of roles if option is set
                if (restricted_to_roles == 1 && $('#restricted_to').val() != undefined) {
                    //add optgroup
                    var optgroup = $('<optgroup>');
                    optgroup.attr('label', "<?php echo addslashes($LANG['roles']); ?>");
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
                        optgroup.attr('label', "<?php echo addslashes($LANG['users']); ?>");
                        $(".folder_rights_user_edit").wrapAll(optgroup);
                    }
                }
                //Add list of roles if option is set
                if (restricted_to_roles == 1 && $('#edit_restricted_to').val() != undefined) {
                    //add optgroup
                    var optgroup = $('<optgroup>');
                    optgroup.attr('label', "<?php echo addslashes($LANG['roles']); ?>");
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

/**
 *
 * @access public
 * @return void
 **/
function items_list_filter(id)
{
    $("#full_items_list").find("li").show();
    if (id) {
        $("#full_items_list").find("a:not(:contains(" + id + "))").parent().addClass("hidden");
        $("#full_items_list").find("a:contains(" + id + ")").parent().show();
    }
}


function manage_history_entry(type, id)
{
    var data = {"item_id": $("#id_item").val(), "label": sanitizeString($('#add_history_entry_label').val())};

    //Send query
    $.post(
        "sources/items.queries.php",
        {
            type      : "history_entry_add",
            folder_id : $('#hid_cat').val(),
            data      : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key       : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            //check if format error
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            if (data.error == "") {
                $("#item_history_log_error").html("").addClass("hidden");
                $("#add_history_entry_label").val("");
                $("#item_history_log").append(htmlspecialchars_decode(data.new_line));
            } else {
                $("#item_history_log_error").html(data.error).show();
            }
            $("#div_item_history").dialog("open");
        }
   );
}


/*
* Launch the redirection to OTV page
*/
function prepareOneTimeView()
{
    if ($("#selected_items").val() == "") return;
    $("#div_loading").removeClass("hidden");

    //Send query
    $.post(
        "sources/items.queries.php",
        {
            type    : "generate_OTV_url",
            id      : $("#id_item").val(),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            //check if format error
            if (data.error == "") {
                $("#div_dialog_message").dialog({height:300,minWidth:750});
                $("#div_dialog_message").dialog('open');
                $("#div_dialog_message_text").html(data.url+
                    '<div style="margin-top:30px;font-size:13px;text-align:center;"><span id="show_otv_copied" class="ui-state-focus ui-corner-all" style="padding:10px;display:none;"></span></div>'
                );

                // prepare clipboard
                var clipboard = new Clipboard("#button_copy_otv_link", {
                    text: function() {
                        return unsanitizeString($('#otv_link').text());
                    }
                });
                clipboard.on('success', function(e) {
                    $("#show_otv_copied").html("<?php echo addslashes($LANG['link_is_copied']); ?>").show().fadeOut(2000);

                    e.clearSelection();
                });

                $(".tip").tooltipster({multiple: true});
            } else {
                $("#item_history_log_error").html(data.error).show();
            }
            $("#div_loading").addClass("hidden");
        },
        "json"
   );
}

function globalItemsSearch()
{
    if ($("#search_item").val() != "") {
        // stop items loading (if on-going)
        $("#items_listing_should_stop").val("1");

        // wait
        $("#items_list_loader").removeClass("hidden");
        $("#items_path_var").html('<i class="fa fa-filter"></i>&nbsp;<?php echo addslashes($LANG['searching']); ?>');

        // clean
        $("#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs, .fields_div, #item_extra_info").html("");
        $("#button_quick_login_copy, #button_quick_pw_copy").addClass("hidden");
        $("#full_items_list").html("");
        $("#selected_items").val("");

        // send query
        $.get(
            "sources/find.queries.php",
            {
                type        : "search_for_items",
                sSearch     : $("#search_item").val(),
                key         : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                displayMessage(data.message);
                $("#items_path_var").html('<i class="fa fa-filter"></i>&nbsp;<?php echo addslashes($LANG['search_results']); ?>');
                $("#items_list").html("<ul class='liste_items 'id='full_items_list'></ul>");
                //$("#full_items_list").html(data.items_html);

                // Build HTML list
                $.each(data.html_json, function(i, value) {
                    // Prepare Description
                    if (value.desc !== "") {
                        value.desc = '&nbsp;<font size="1px">[' + value.desc + ']</font>';
                    }

                    if (value.copy_to_clipboard_small_icons === "1") {
                        // Prepare Login
                        if (value.login !== "") {
                            value.login = '<span class="fa fa-user fa-lg mi-black mini_login tip" data-clipboard-text="'+value.login+'" title="<?php echo addslashes($LANG['item_menu_copy_login']);?>"></span>&nbsp;';
                        }

                        // Prepare PWD
                        if (value.pw !== "") {
                            value.pw = '<span class="fa fa-lock fa-lg mi-black mini_pw tip" data-clipboard-text="'+value.pw+'" title="<?php echo addslashes($LANG['item_menu_copy_pw']);?>"></span>&nbsp;'
                        }

                        // Prepare favorite
                        if (value.enable_favourites === "1") {
                            if (value.is_favorite === 1) {
                                icon_favorite = '<span class="fa fa-star fa-lg mi-yellow tip" onclick="ActionOnQuickIcon('+value.item_id+',0)" class="tip" title="<?php echo addslashes($LANG['item_menu_del_from_fav']);?>"></span>';
                            } else {
                                icon_favorite = '<span class="fa fa-star-o fa-lg tip" onclick="ActionOnQuickIcon('+value.item_id+',1)" class="tip" title="<?php echo addslashes($LANG['item_menu_add_to_fav']);?>"></span>';
                            }
                        } else {
                            icon_favorite = '';
                        }
                    } else {
                        value.login = '';
                        value.pw = '';
                        icon_favorite = '';
                    }

                    // Append
                    $("#full_items_list").append(
                    '<li class="item trunc_line" id="'+value.item_id+'"><a id="fileclass'+value.item_id+'" class="file_search">' +
                    '<span class="fa fa-key mi-yellow tip" onclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \''+value.open_edit+'\', \''+value.reload+'\', \''+value.tree_id+'\')" title="<?php echo addslashes($LANG['click_to_edit']);?>"></span>&nbsp;' +
                        '<span class="truncate" onclick="AfficherDetailsItem(\''+value.item_id+'\',\''+value.sk+'\',\''+value.expired+'\', \''+value.restricted+'\', \''+value.display+'\', \'\', \''+value.reload+'\', \''+value.tree_id+'\')">'+value.label +
                        value.desc +
                        '&nbsp;<span style="font-size:11px;font-style:italic;"><i class="fa fa-folder-o"></i>&nbsp;'+value.folder+'</span>' +
                        '</span><span style="float:right;margin:2px 10px 0px 0px;">' +
                        value.login +
                        value.pw +
                        icon_favorite +
                        '</span></li>'
                    );
                })

                $("#items_list_loader").addClass("hidden");
            }
        );
    }
}

/*
*
*/
function searchItemsWithTags(tag)
{
    if (tag == "") return false

    // wait
    $("#items_list_loader").removeClass("hidden");
    $("#items_path_var").html('<i class="fa fa-filter"></i>&nbsp;<?php echo addslashes($LANG['searching_tag']); ?>&nbsp;<b>' + tag + '</b> ...');

    // clean
    $("#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs").html("");
    $("#button_quick_login_copy, #button_quick_pw_copy").addClass("hidden");
    $("#full_items_list").html("");
    $("#selected_items").val("");

    // send query
    $.get(
        "sources/find.queries.php",
        {
            type        : "search_for_items_with_tags",
            tagSearch   : tag,
            key         : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            displayMessage(data.message);
            $("#items_path_var").html('<i class="fa fa-filter"></i>&nbsp;<?php echo addslashes($LANG['search_results']); ?>&nbsp;<b>' + tag + '</b>');
            $("#full_items_list").html(data.items_html);
            $("#items_list_loader").addClass("hidden");
        }
    );
}

function loadOfflineDialog()
{
    $("#dialog_offline_mode").dialog({
        open: function(event, ui) {
            loadHtml(
                "<?php echo $SETTINGS['cpassman_url']; ?>/items.offline.php?key=<?php echo $_SESSION['key']; ?>",
                "div_offline_mode"
            );
        }
    }).dialog("open");
}

function loadExportDialog()
{
    $("#dialog_export_file").dialog({
        open: function(event, ui) {
            loadHtml(
                "<?php echo $SETTINGS['cpassman_url']; ?>/items.export.php?key=<?php echo $_SESSION['key']; ?>",
                "div_export_file"
            );
        }
    }).dialog("open");
}

function loadImportDialog()
{
    $("#dialog_import_file").dialog({
        open: function(event, ui) {
            loadHtml(
                "<?php echo $SETTINGS['cpassman_url']; ?>/items.import.php?key=<?php echo $_SESSION['key']; ?>&folder_id="+$("#hid_cat").val(),
                "div_import_file"
            );
        }
    }).dialog("open");
}

function reEncryptPersonalPwds(remainingIds, currentId, nb)
{
    //console.log(remainingIds+";"+currentId+";"+nb);
    $("#dialog_upgrade_personal_passwords_status").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo addslashes($LANG['please_wait']); ?>&nbsp;...&nbsp;<span id="reencryption_progress">0%</span>').attr("class","").show();

    $.ajax({
        url: "sources/utils.queries.php",
        type : 'POST',
        dataType : "json",
        data : {
            type        : "reencrypt_personal_pwd",
            currentId   : currentId,
            user_id     : "<?php echo $_SESSION['user_id']; ?>",
            key         : "<?php echo $_SESSION['key']; ?>"
        },
        complete : function(data, statut){
            var aIds = remainingIds.split(",");
            var currentID = aIds[0];
            aIds.shift();
            var nb2 = aIds.length;
            aIds = aIds.toString();
            if (nb == 0)
                $("#reencryption_progress").html("100%");
            else
                $("#reencryption_progress").html(Math.floor(((nb-nb2) / nb) * 100)+"%");

            if (nb2 != "0" || (nb2 == "" && currentID != "")) {
                reEncryptPersonalPwds(aIds, currentID, nb);
            } else {
                $("#dialog_upgrade_personal_passwords").html('<i class="fa fa-info"></i>&nbsp;<?php echo addslashes($LANG['operation_encryption_done']); ?>');

                // ensure that no upgrade popup is shown
                $("#personal_upgrade_needed").val("");
            }
        }
    });
}

 function serverAutoChangePwd()
 {
    //console.log("opening");
    $("#dialog_ssh").dialog({
        open: function(event, ui) {
            $("#div_ssh").load(
                "<?php echo $SETTINGS['cpassman_url'].'/ssh.php?key='.$_SESSION['key']; ?>&id="+$("#selected_items").val(), function(){}
            );
        }
    }).dialog("open");
}

/*
**
*/
function showPasswordsHistory() {
    if ($('#edit_past_pwds_div').text() !== "") {
        $('#edit_past_pwds_div').toggle();
    }
}

$.fn.simulateClick = function() {
    return this.each(function() {
        if('createEvent' in document) {
            var doc = this.ownerDocument,
                evt = doc.createEvent('MouseEvents');
            evt.initMouseEvent('click', true, true, doc.defaultView, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
            this.dispatchEvent(evt);
        } else {
            this.click(); // IE Boss!
        }
    });
};


// escape HTML characters
String.prototype.escapeHTML = function() {
    return this.replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
};
//]]>
</script>
