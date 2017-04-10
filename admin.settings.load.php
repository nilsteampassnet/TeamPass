<?php
/**
 * @file          admin.settings.load.php
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
?>

<script type="text/javascript">
//<![CDATA[
/*
* Add a new field to a category
*/
function fieldAdd(id) {
    $("#post_id").val(id);
    $("#add_new_field").dialog("open");
}
/*
* Edit category's folders
*/
function catInFolders(id) {
    $("#post_id").val(id);
    $("#catInFolder_title").html($("#item_"+id).html());    // display title
    // pre-select folders
    $("#cat_folders_selection > option").prop("selected", false);
    var folder = $("#catFoldersList_"+id).val().split(";");
    for (var i=0; i<folder.length; i++) {
        $("#cat_folders_selection option[value="+folder[i]+"]").attr('selected', 'selected');
    };
    // open
    $("#category_in_folder").dialog("open");
}

/*
* Add a new category
*/
function categoryAdd() {
    if ($("#new_category_label").val() == "") {
        return false;
    }
    $("#div_loading").show();
    //send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "addNewCategory",
            title   : sanitizeString($("#new_category_label").val())
        },
        function(data) {
            // build new row
            $("#tbl_categories").append(
                '<tr id="t_cat_'+data[0].id+'"><td colspan="2">'+
                '<input type="text" id="catOrd_'+data[0].id+'" size="1" class="category_order" value="1" />&nbsp;&nbsp;'+
                '<span class="fa-stack tip" title="<?php echo $LANG['field_add_in_category'];?>" onclick="fieldAdd('+
                data[0].id+')" style="cursor:pointer;">'+
                '<i class="fa fa-square fa-stack-2x"></i><i class="fa fa-plus fa-stack-1x fa-inverse"></i>'+
                '</span>&nbsp;'+
                '<input type="radio" name="sel_item" id="item_'+data[0].id+'_cat" />'+
                '<label for="item_'+data[0].id+'_cat" id="item_'+data[0].id+'">'+
                $("#new_category_label").val()+'</label>'+
                '</td><td>'+
                '<span class="fa-stack tip" title="<?php echo $LANG['category_in_folders'];?>" onclick="catInFolders('+data[0].id+')" style="cursor:pointer;">'+
                '<i class="fa fa-square fa-stack-2x"></i><i class="fa fa-edit fa-stack-1x fa-inverse"></i>'+
                '</span>&nbsp;'+
                '<?php echo $LANG['category_in_folders_title'];?>:'+
                '<span style="font-family:italic; margin-left:10px;" id="catFolders_'+data[0].id+'"></span>'+
                '<input type="hidden" id="catFoldersList_'+data[0].id+'" value="'+data[0].id+'" /></td><td></td>');
            // Add new cat
            $("#moveItemTo").append('<option value="'+data[0].id+'">'+$("#new_category_label").val()+'</option>');
            // clean
            $("#new_category_label, #new_item_title").val("");
            //loadFieldsList();
            $("#div_loading,#no_category").hide();
        },
        "json"
   );
}

/*
* rename an Element
*/
function renameItem() {
    var data = $("input[name=sel_item]:checked").attr("id").split('_');
    $("#post_id").val(data[1]);
    $("#post_type").val("renameItem");
    $("#category_confirm_text").html("<?php echo $LANG['confirm_rename'];?>");
    $("#category_confirm").dialog("open");
}

/*
* Delete an Element
*/
function deleteItem() {
    var data = $("input[name=sel_item]:checked").attr("id").split('_');
    $("#post_id").val(data[1]);
    $("#post_type").val("deleteCategory");
    $("#category_confirm_text").html("<?php echo $LANG['confirm_deletion'];?>");
    $("#category_confirm").dialog("open");
}

/*
* Move an Element
*/
function moveItem() {
    var data = $("input[name=sel_item]:checked").attr("id").split('_');
    $("#post_id").val(data[1]);
    $("#post_type").val("moveItem");
    $("#category_confirm_text").html("<?php echo $LANG['confirm_moveto'];?>");
    $("#category_confirm").dialog("open");
}

/*
* Save the position of the Categories
*/
function storePosition() {
    $("#div_loading").show();
    // prepare listing to save
    var data = "";
    var id;
    var val;
    $('input[class$="category_order"]').each(function(index) {
        id = $(this).attr("id").split("_");
        if ($(this).val() == "") {
            val = "1";
        } else {
            val = $(this).val();
        }
        if (data == "") {
            data = id[1]+":"+val;
        } else {
            data += ";"+id[1]+":"+val;
        }
    });

    //send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "saveOrder",
            data   : data
        },
        function(data) {
            $("#div_loading").hide();
        },
        "json"
   );
}

/*
* Reload table
*/
function loadFieldsList() {
    $("#div_loading").show();
    //send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "loadFieldsList",
            title   : prepareExchangedData(sanitizeString($("#new_category_label").val()), "encode", "<?php echo $_SESSION['key'];?>")
        },
        function(data) {
            var newList = '<table id="tbl_categories" style="">';
            // parse json table and disaply
            var json = $.parseJSON(data);
            $(json).each(function(i,val){
                if (val[0] === "1") {
                    newList += '<tr id="t_cat_'+val[1]+'"><td colspan="2">'+
                    '<input type="text" id="catOrd_'+val[1]+'" size="1" class="category_order" value="'+val[3]+'" />&nbsp;'+
                    '<span class="fa-stack tip" title="<?php echo $LANG['field_add_in_category'];?>" onclick="fieldAdd('+
                    val[1]+')" style="cursor:pointer;">'+
                    '<i class="fa fa-square fa-stack-2x"></i><i class="fa fa-plus fa-stack-1x fa-inverse"></i>'+
                    '</span>&nbsp;'+
                    '<input type="radio" name="sel_item" id="item_'+val[1]+'_cat" />'+
                    '<label for="item_'+val[1]+'_cat" id="item_'+val[1]+'">'+val[2]+'</label>'+
                    '</td><td>'+
                    '<span class="fa-stack tip" title="<?php echo $LANG['category_in_folders'];?>" onclick="catInFolders('+val[1]+')" style="cursor:pointer;">'+
                    '<i class="fa fa-square fa-stack-2x"></i><i class="fa fa-edit fa-stack-1x fa-inverse"></i>'+
                    '</span>&nbsp;'+
                    '<?php echo $LANG['category_in_folders_title'];?>:'+
                    '<span style="font-family:italic; margin-left:10px;" id="catFolders_'+val[1]+'">'+val[4]+'</span>'+
                    '<input type="hidden" id="catFoldersList_'+val[1]+'" value="'+val[5]+'" /></td></tr>';
                } else {
                    newList += '<tr id="t_field_'+val[1]+'"><td width="20px"></td>'+
                    '<td><input type="text" id="catOrd_'+val[1]+'" size="1" class="category_order" value="'+val[3]+'" />&nbsp;'+
                    '<input type="radio" name="sel_item" id="item_'+val[1]+'_cat" />'+
                    '<label for="item_'+val[1]+'_cat" id="item_'+val[1]+'">'+val[2]+'</label>';

                    if (val[4] !== "") {
                        newList += '<span id="encryt_data_'+val[1]+'" style="margin-left:4px; cursor:pointer;">';
                        if (val[4] === "1") {
                            newList += '<i class="fa fa-key tip" title="<?php echo $LANG['encrypted_data'];?>" onclick="changeEncrypMode('+val[1]+', 1)"></i>';
                        } else if (val[4] === "0") {
                            newList += '<span class="fa-stack" title="<?php echo $LANG['not_encrypted_data'];?>" onclick="changeEncrypMode('+val[1]+', 0)"><i class="fa fa-key fa-stack-1x"></i><i class="fa fa-ban fa-stack-1x fa-lg" style="color:red;"></i></span>';
                        }
                        newList += '</span>'
                    }

                    newList += '</td><td></td></tr>';
                }
            });

            // display
            newList += '</table>';
            $("#new_item_title").val("");
            $("#categories_list").html(newList);
            $("#div_loading").hide();
        }
   );
}

//###########
//## FUNCTION : Launch the action the admin wants
//###########
function LaunchAdminActions(action,option)
{
    $("#div_loading").show();
    $("#email_testing_results").hide();
    $("#result_admin_action_db_backup").html("");
    if (action == "admin_action_db_backup") option = $("#result_admin_action_db_backup_key").val();
    else if (action == "admin_action_backup_decrypt") option = $("#bck_script_decrypt_file").val();
    else if (action == "admin_action_change_salt_key") {
        option = aes_encrypt(sanitizeString($("#new_salt_key").val()));
    } else if (action == "admin_email_send_backlog") {
        $("#email_testing_results").show().html("'.addslashes($LANG['please_wait']).'").attr("class","ui-corner-all ui-state-focus");
    } else if (action == "admin_action_attachments_cryption") {
        option = $("input[name=attachments_cryption]:checked").val();
        if (option == "") return;
    }
    //Lauchn ajax query
    $.post(
        "sources/admin.queries.php",
        {
           type        : action,
           option    : option
        },
        function(data) {
            $("#div_loading").hide();
            if (data != null) {
                if (data[0].result == "db_backup") {
                    $("#result_admin_action_db_backup").html("<span class='fa fa-file-code-o'></span>&nbsp;<a href='"+data[0].href+"'><?php echo $LANG['pdf_download'];?></a>").show();
                } else if (data[0].result == "pf_done") {
                    $("#result_admin_action_check_pf").html("<span class='fa fa-check mi-green'></span>").show();
                } else if (data[0].result == "db_restore") {
                    $("#restore_bck_encryption_key_dialog").dialog("close");
                    $("#result_admin_action_db_restore").html("<span class='fa fa-check mi-green'></span>").show();
                    $("#result_admin_action_db_restore_get_file").hide();
                    //deconnect userd
                    sessionStorage.clear();
                    window.location.href = "logout.php"
                } else if (data[0].result == "cache_reload") {
                    $("#result_admin_action_reload_cache_table").html("<span class='fa fa-check mi-green'></span>").show();
                } else if (data[0].result == "db_optimize") {
                    $("#result_admin_action_db_optimize").html("<span class='fa fa-check mi-green'></span>").show();
                } else if (data[0].result == "purge_old_files") {
                    $("#result_admin_action_purge_old_files").html("<span class='fa fa-check mi-green'></span>&nbsp;"+data[0].nb_files_deleted+"&nbsp;<? echo $LANG['admin_action_purge_old_files_result'];?>").show();
                } else if (data[0].result == "db_clean_items") {
                    $("#result_admin_action_db_clean_items").html("<span class='fa fa-check mi-green'></span>&nbsp;"+data[0].nb_items_deleted+"&nbsp;<?php echo $LANG['admin_action_db_clean_items_result'];?>").show();
                } else if (data[0].result == "changed_salt_key") {
                    //deconnect user
                    $("#menu_action").val("deconnexion");
                    sessionStorage.clear();
                    window.location.href = "logout.php"
                } else if (data[0].result == "email_test_conf" || data[0].result == "admin_email_send_backlog") {
                    if (data[0].error != "") {
                        $("#email_testing_results").html("<?php echo addslashes($LANG['admin_email_result_nok']);?>&nbsp;"+data[0].message).show().attr("class","ui-state-error ui-corner-all");
                    } else {
                        $("#email_testing_results").html("<?php echo addslashes(str_replace("#email#", $_SESSION['user_email'], $LANG['admin_email_result_ok']));?>").show().attr("class","ui-corner-all ui-state-focus");
                    }
                } else if (data[0].result == "pw_prefix_correct") {
                    $("result_admin_action_pw_prefix_correct").html(data[0].ret).show();
                } else if (data[0].result == "attachments_cryption") {
                    if (data[0].continu == true) {
                        manageEncryptionOfAttachments(data[0].list, data[0].cpt);
                    } else if (data[0].error == "file_not_encrypted") {
                        $("#result_admin_action_attachments_cryption").html("It seems the files are not encrypted. Are you sure you want to decrypt? please do a check.").show();
                    } else if (data[0].error == "file_not_clear") {
                        $("#result_admin_action_attachments_cryption").html("It seems the files are encrypted. Are you sure you want to encrypt? please do a check.").show();
                    }
                } else if (data[0].result == "rebuild_config_file") {
                    $("#result_admin_rebuild_config_file").html("<span class='fa fa-check mi-green'></span>").show();
                }
            }
        },
        "json"
   );
}

/*
*
*/
function confirmChangingSk() {
    if (confirm("Please confirm")) {
        changeMainSaltKey('starting', '');
    }
}

/*
*
*/
function changeMainSaltKey(start, type)
{
    var nb = 10;    // can be changed - number of items treated in each loop

    //console.log("Start value: "+start);

    // start change
    if (start === "starting") {
        // inform
        $("#changeMainSaltKey_message").html("<i class=\"fa fa-cog fa-spin fa\"></i>&nbsp;<?php echo $LANG['starting'];?>");

        // launch query
        $.post(
            "sources/admin.queries.php",
            {
               type     : "admin_action_change_salt_key___start"
            },
            function(data) {
                //console.log("Step start - " + data[0].nextAction);
                if (data[0].error == "" && data[0].nextAction == "encrypt_items") {
                    $("#changeMainSaltKey_itemsCount").val(data[0].nbOfItems);
                    //console.log("Now launch encryption");
                    // start encrypting items with new saltkey
                    changeMainSaltKey(0, "items");
                } else {
                    // error mngt
                    $("#changeMainSaltKey_message").html("<i class=\"fa fa-alert fa-spin fa\"></i>&nbsp;<?php echo $LANG['error_sent_back'];?> : "+data[0].error);
                }
            },
            "json"
        );
    } else if (isFinite(start) && type === "items") {
        console.log("Step Encrypt - " +start+" ; "+nb+" ; "+$("#changeMainSaltKey_itemsCount").val());

        $("#changeMainSaltKey_message").html("<i class=\"fa fa-cog fa-spin fa\"></i>&nbsp;<?php echo $LANG['treating_items'];?>...&nbsp;"+start+" > "+(parseInt(start)+parseInt(nb))+" (<?php echo $LANG['total_number_of_items'];?> : "+$("#changeMainSaltKey_itemsCount").val()+")");

        $.post(
            "sources/admin.queries.php",
            {
               type     : "admin_action_change_salt_key___encrypt_files",
               start    : start,
               length    : nb,
               nbItems    : $("#changeMainSaltKey_itemsCount").val()
            },
            function(data) {
                console.log("Next action: "+data[0].nextAction);
                if (data[0].nextAction === "encrypting") {
                    changeMainSaltKey(data[0].nextStart, "items");
                } else if (data[0].nextAction === "finishing") {
                    $("#changeMainSaltKey_message").html("<?php echo $LANG['finalizing'];?>...");
                    changeMainSaltKey("finishing");
                } else {
                    // error mngt
                    $("#changeMainSaltKey_message").html("<i class=\"fa fa-alert fa-spin fa\"></i>&nbsp;<?php echo $LANG['error_sent_back'];?> : "+data[0].error);
                }
            },
            "json"
        );
    } else if (isFinite(start) && type == "logs") {
        $("#changeMainSaltKey_message").html("<i class=\"fa fa-cog fa-spin fa\"></i>&nbsp;<?php echo $LANG['treating_items'];?>...&nbsp;"+start+" > "+(parseInt(start)+parseInt(nb))+" (<?php echo $LANG['total_number_of_items'];?> : "+$("#changeMainSaltKey_itemsCount").val()+")");

        changeMainSaltKey(0, "files");

    } else if (isFinite(start) && type == "files") {
        $("#changeMainSaltKey_message").html("<i class=\"fa fa-cog fa-spin fa\"></i>&nbsp;<?php echo $LANG['treating_items'];?>...&nbsp;"+start+" > "+(parseInt(start)+parseInt(nb))+" (<?php echo $LANG['total_number_of_items'];?> : "+$("#changeMainSaltKey_itemsCount").val()+")");

        changeMainSaltKey(0, "categories");

    } else if (isFinite(start) && type == "categories") {
        $("#changeMainSaltKey_message").html("<i class=\"fa fa-cog fa-spin fa\"></i>&nbsp;<?php echo $LANG['treating_items'];?>...&nbsp;"+start+" > "+(parseInt(start)+parseInt(nb))+" (<?php echo $LANG['total_number_of_items'];?> : "+$("#changeMainSaltKey_itemsCount").val()+")");

        changeMainSaltKey(0, "custfields");

    } else if (isFinite(start) && type == "custfields") {
        $("#changeMainSaltKey_message").html("<i class=\"fa fa-cog fa-spin fa\"></i>&nbsp;<?php echo $LANG['treating_items'];?>...&nbsp;"+start+" > "+(parseInt(start)+parseInt(nb))+" (<?php echo $LANG['total_number_of_items'];?> : "+$("#changeMainSaltKey_itemsCount").val()+")");

        changeMainSaltKey("finishing");
    } else {
        $.post(
            "sources/admin.queries.php",
            {
               type     : "admin_action_change_salt_key___end"
            },
            function(data) {
                if (data[0].nextAction === "done") {
                    console.log("done");
                    $("#changeMainSaltKey_message").html("<i class=\"fa fa-info fa-lg\"></i>&nbsp;<?php echo $LANG['alert_message_done']." ".$LANG['number_of_items_treated'];?> : "+$("#changeMainSaltKey_itemsCount").val());
                } else {
                    // error mngt
                }
            },
            "json"
        );
    }
}

/*
* FUNCTION permitting to store into DB the settings changes
*/
function updateSetting(field)
{
    if (field == "") return false;

    // store in DB
    var data = '{"field":"'+field+'", "value":"'+$("#"+field).val()+'"}';
    //console.log(data);
    $.post(
        "sources/admin.queries.php",
        {
            type    : "save_option_change",
            data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
            key     : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            // force page reload in case of encryptClientServer
            if (field == "encryptClientServer") {
                location.reload(true);
                return false;
            }
            //decrypt data
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
            } catch (e) {
                // error
                $("#message_box").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data).show().fadeOut(4000);

                return;
            }
            console.log(data);
            if (data.error == "") {
                $("#"+field).after("<span class='fa fa-check fa-lg mi-green new_check'></span>");
                $(".new_check").fadeOut(2000);
                setTimeout('$(".new_check").remove()', 2100);
            }
        }
    );
}

// Init
$(function() {
    $('.toggle').toggles({
        drag: true, // allow dragging the toggle between positions
        click: true, // allow clicking on the toggle
        text: {
            on: '<?php echo $LANG['yes'];?>', // text for the ON position
            off: '<?php echo $LANG['no'];?>' // and off
        },
        on: true, // is the toggle ON on init
        animate: 250, // animation time (ms)
        easing: 'swing', // animation transition easing function
        width: 50, // width used if not set in css
        height: 20, // height if not set in css
        type: 'compact' // if this is set to 'select' then the select style toggle will be used
    });
    $('.toggle').on('toggle', function(e, active) {
        if (active) {
            $("#"+e.target.id+"_input").val(1);
            if (e.target.id == "ldap_mode") {$("#div_ldap_configuration").show();}
        } else {
            $("#"+e.target.id+"_input").val(0);
            if (e.target.id == "ldap_mode") {$("#div_ldap_configuration").hide();}
        }

        // store in DB
        var data = '{"field":"'+e.target.id+'", "value":"'+$("#"+e.target.id+"_input").val()+'"}';
        console.log(data);
        $.post(
            "sources/admin.queries.php",
            {
                type    : "save_option_change",
                data     : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                key     : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                // force page reload in case of encryptClientServer
                if (e.target.id == "encryptClientServer") {
                    location.reload(true);
                    return false;
                }
                //decrypt data
                try {
                    data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
                } catch (e) {
                    // error
                    $("#message_box").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data).show().fadeOut(4000);

                    return;
                }
                console.log(data);
                if (data.error == "") {
                    $("#"+e.target.id).after("<span class='fa fa-check fa-lg mi-green new_check' style='float:left;margin:-18px 0 0 56px;'></span>");
                    $(".new_check").fadeOut(2000);
                    setTimeout('$(".new_check").remove()', 2100);
                }
            }
        );
    });

    // spinner
    $("#upload_imageresize_quality").spinner({
        min: 0,
        max: 100,
        value: 90,
        spin: function(event, ui) {
            updateSetting($("#upload_imageresize_quality").attr('id'));
        }
    });

    //BUILD BUTTONSET
    $(".div_radio").buttonset();

    // Build Tabs
    $("#tabs").tabs({
        ajaxOptions: {
            error: function(xhr, status, index, anchor) {
                $(anchor.hash).html();
            },
            beforeSend: function() {
                $("#div_loading").show();
            },
            complete: function() {
                $("#div_loading").hide();
            }
        },
        beforeLoad: function( event, ui ) {
            ui.panel.html('<div id="loader_tab"><i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['loading'];?>...</div>')
        },
        load: function( event, ui ) {
            $("#loader_tab").remove();
        }
    });

    $('#tabs').click(function(e){
        var current_index = $("#tabs").tabs("option","active");
        if (current_index == 9 || current_index == 10) {
            $("#save_button").hide();
        } else {
            $("#save_button").show();
        }
    });

    $('#tbl_categories tr').click(function (event) {
        $("#selected_row").val($(this).attr("id"));
    });

    // display text of selected item
    $(document).on("click","input[name=sel_item]",function(){
        var data = $("input[name=sel_item]:checked").attr("id").split('_');
        $("#new_item_title").val($("#item_"+data[1]).html());
    });

    // confirm dialogbox
    $("#category_confirm").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 120,
        title: "<?php echo $LANG['confirm'];?>",
        buttons: {
            "<?php echo $LANG['confirm'];?>": function() {
                $("#div_loading").show();
                var $this = $(this);
                // prepare data to send
                var data = "";
                if ($("#post_type").val() == "renameItem") {
                    data = sanitizeString($("#new_item_title").val());
                } else if ($("#post_type").val() == "moveItem") {
                    data = $("#moveItemTo").val();
                }
                // send query
                $.post(
                    "sources/categories.queries.php",
                    {
                        type    : $("#post_type").val(),
                        id      : $("#post_id").val(),
                        data    : data
                    },
                    function(data) {
                        if ($("#post_type").val() == "deleteCategory") {
                            $("#t_field_"+$("#post_id").val()).hide();
                        } else if ($("#post_type").val() == "renameItem") {
                            $("#item_"+$("#post_id").val()).html($("#new_item_title").val());
                        } else if ($("#post_type").val() == "moveItem") {
                            // reload table
                            //loadFieldsList();
                        }
                        loadFieldsList();
                        $("#new_category_label, #new_item_title").val("");
                        $("#div_loading").hide();
                        $this.dialog("close");
                    },
                    "json"
               );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#div_loading").hide();
                $(this).dialog("close");
            }
        }
    });

    $("#add_new_field").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 150,
        title: "<?php echo $LANG['category_in_folders'];?>",
        buttons: {
            "<?php echo $LANG['confirm'];?>": function() {
                if ($("#new_field_title").val() != "" && $("#post_id").val() != "") {
                    $("#div_loading").show();
                    var $this = $(this);
                    //send query
                    $.post(
                        "sources/categories.queries.php",
                        {
                            type    : "addNewField",
                            title   : sanitizeString($("#new_field_title").val()),
                            id      : $("#post_id").val()
                        },
                        function(data) {
                            $("#new_field_title").val("");
                            // reload table
                            loadFieldsList();
                            $this.dialog("close");
                        },
                        "json"
                    );
                }
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#div_loading").hide();
                $(this).dialog("close");
            }
        }
    });

    $("#cat_folders_selection").multiselect({
        selectedList: 7,
        multiple:true,
        checkAllText: "<?php echo $LANG['check_all_text'];?>",
        uncheckAllText: "<?php echo $LANG['uncheck_all_text'];?>"
    });

    $("#category_in_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 350,
        title: "<?php echo $LANG['category_in_folders'];?>",
        open: function() {
            $("#cat_folders_selection").multiselect('refresh');
        },
        buttons: {
            "<?php echo $LANG['confirm'];?>": function() {
                // get list of selected folders
                var ids = "";
                $("#cat_folders_selection :selected").each(function(i, selected) {
                    if (ids == "") ids = $(selected).val();
                    else ids = ids + ";" + $(selected).val();
                });
                if (ids != "") {
                    $("#div_loading, #catInFolder_wait").show();
                    var $this = $(this);
                    //send query
                    $.post(
                        "sources/categories.queries.php",
                        {
                            type        : "categoryInFolders",
                            foldersIds  : ids,
                            id          : $("#post_id").val()
                        },
                        function(data) {
                            $("#new_field_title").val("");
                            // display new list
                            $("#catFolders_"+$("#post_id").val()).html(data[0].list);
                            // close
                            $("#div_loading, #catInFolder_wait").hide();
                            $this.dialog("close");
                        },
                        "json"
                    );
                }
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#div_loading").hide();
                $(this).dialog("close");
            }
        }
    });

    $("#restore_bck_encryption_key_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width:250,
        height:150,
        title: "<?php echo $LANG['admin_action_db_restore_key'];?>",
        buttons: {
            "<?php echo $LANG['ok'];?>": function() {
                LaunchAdminActions("admin_action_db_restore", $("#restore_bck_fileObj").val()+"&"+$("#restore_bck_encryption_key").val());
            },
            "<?php echo $LANG['cancel_button'];?>'": function() {
                $(this).dialog("close");
            }
        }
    });

    // SQL IMPORT FOR RESTORING
    var uploader_restoreDB = new plupload.Uploader({
        runtimes : "gears,html5,flash,silverlight,browserplus",
        browse_button : "pickfiles_restoreDB",
        container : "upload_container_restoreDB",
        max_file_size : "10mb",
        chunk_size : "1mb",
        unique_names : true,
        dragdrop : true,
        multiple_queues : false,
        multi_selection : false,
        max_file_count : 1,
        url : "sources/upload/upload.files.php",
        flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
        silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
        filters : [
            {title : "SQL files", extensions : "sql"}
        ],
        init: {
            FilesAdded: function(up, files) {
                // generate and save token
                $.post(
                    "sources/main.queries.php",
                    {
                        type : "save_token",
                        size : 25,
                        capital: true,
                        numeric: true,
                        ambiguous: true,
                        reason: "restore_db",
                        duration: 10
                    },
                    function(data) {
                        $("#user_token").val(data[0].token);
                        up.start();
                    },
                    "json"
                );
            },
            BeforeUpload: function (up, file) {
                $("#import_status_ajax_loader").show();
                up.settings.multipart_params = {
                    "PHPSESSID":"'.$_SESSION['user_id'].'",
                    "File":file.name,
                    "type_upload":"restore_db",
                    "user_token": $("#user_token").val()
                };
            },
            UploadComplete: function(up, files) {
                $.each(files, function(i, file) {
                    $("#restore_bck_fileObj").val(file.name);
                    $("#restore_bck_encryption_key_dialog").dialog("open");
                });
            }
        }
    });
    // Uploader options
    uploader_restoreDB.bind("UploadProgress", function(up, file) {
        $("#" + file.id + " b").html(file.percent + "%");
    });
    uploader_restoreDB.bind("Error", function(up, err) {
        $("#filelist_restoreDB").html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
            ", Message: " + err.message +
            (err.file ? ", File: " + err.file.name : "") +
            "</div>"
        );
        up.refresh(); // Reposition Flash/Silverlight
    });
    uploader_restoreDB.bind("+", function(up, file) {
        $("#" + file.id + " b").html("100%");
    });
    // Load CSV click
    $("#uploadfiles_restoreDB").click(function(e) {
        uploader_restoreDB.start();
        e.preventDefault();
    });
    uploader_restoreDB.init();
    // -end

    //Enable/disable option
    $("#restricted_to").bind("click", function() {
        if ($("#restricted_to_input").val()== 1) {
            $("#tr_option_restricted_to_roles").show();
        } else {
            $("#tr_option_restricted_to_roles").hide();
            $("#tr_option_restricted_to_roles_input").val("0");
        }
    });
    $("#anyone_can_modify").bind("click", function() {
        if ($("#anyone_can_modify_input").val()== 1) {
            $("#tr_option_anyone_can_modify_bydefault").show();
        } else {
            $("#tr_option_anyone_can_modify_bydefault").hide();
            $("#anyone_can_modify_bydefault_input").val("0");
        }
    });

    //check NEW SALT KEY
    $("#new_salt_key").keypress(function (e) {
        var key = e.charCode || e.keyCode || 0;
        if ($("#new_salt_key").val().length != 16) {
            $("#change_salt_key_image").html('<i class="fa fa-cross mi-red"></i>');
            $("#change_salt_key_but").hide();
        } else {
            $("#change_salt_key_image").html('<i class="fa fa-check mi-green"></i>');
            $("#change_salt_key_but").show();
        }
        // allow backspace, tab, delete, arrows, letters, numbers and keypad numbers ONLY
        return (
            key != 33 && key != 34 && key != 39 && key != 92 && key != 32  && key != 96 && (key < 165)
            && $("#new_salt_key").val().length <= 32
       );
    });

    $("button").button();

});

function manageEncryptionOfAttachments(list, cpt) {

    $.post(
        "sources/admin.queries.php",
        {
            type    : "admin_action_attachments_cryption_continu",
            option  : $("input[name=attachments_cryption]:checked").val(),
            cpt     : cpt,
            list    : list
        },
        function(data) {
            if (data[0].continu == true ) {
                manageEncryptionOfAttachments(data[0].list, data[0].cpt);
            } else {
                $("#result_admin_action_attachments_cryption").html("<span class='fa fa-check mi-green'></span>&nbsp;"+data[0].cpt+" files changed");
            }
        },
        "json"
    );
}

function refreshInput()
{
    var ids = "";
    $.each($("#roles_allowed_to_print_select option:selected"), function(){
        if (ids == "") ids = $(this).val();
        else ids = ids + ";" + $(this).val();
    });
    $("#roles_allowed_to_print").val(ids);
    updateSetting('roles_allowed_to_print');
}

function changeEncrypMode(id, encrypted_data) {
    // send to server
    $("#div_loading").show();
    //send query
    $.post(
        "sources/categories.queries.php",
        {
            type    : "dataIsEncryptedInDB",
            id      : id,
            encrypt : encrypted_data === "1" ? "0" : "1"
        },
        function(data) {
            // show to user
            if (data[0].error === ""){
                if (encrypted_data === "1") {
                    $("#encryt_data_"+id).html('<span class="fa-stack" title="<?php echo $LANG['not_encrypted_data'];?>" onclick="changeEncrypMode(\''+id+'\', \'0\')"><i class="fa fa-key fa-stack-1x"></i><i class="fa fa-ban fa-stack-1x fa-lg" style="color:red;"></i></span>');
                } else {
                    $("#encryt_data_"+id).html('<i class="fa fa-key tip" title="<?php echo $LANG['encrypted_data'];?>" onclick="changeEncrypMode(\''+id+'\', \'1\')"></i>');
                }
            }
            $("#div_loading").hide();
        },
        "json"
   );


}
//]]>
</script>