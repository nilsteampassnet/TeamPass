<?php
/**
 * @file          users.load.php
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

    $.extend($.expr[":"], {
        "containsIN": function(elem, i, match, array) {
            return (elem.textContent || elem.innerText || "").toLowerCase().indexOf((match[3] || "").toLowerCase()) >= 0;
        }
    });

    // prepare Alphabet
    var _alphabetSearch = '';
    $.fn.dataTable.ext.search.push( function ( settings, searchData ) {
        if ( ! _alphabetSearch ) {
            return true;
        }
        if ( searchData[0].charAt(0) === _alphabetSearch ) {
            return true;
        }
        return false;
    } );

$(function() {
    $("#tabs").tabs();

    //Build multiselect box
    $("#user_edit_functions_list, #user_edit_managedby, #user_edit_auth, #user_edit_forbid, #new_user_groups, #new_user_auth_folders, #new_user_forbid_folders").multiselect({
        selectedList: 7,
        minWidth: 550,
        height: 145,
        checkAllText: "<?php echo $LANG['check_all_text'];?>",
        uncheckAllText: "<?php echo $LANG['uncheck_all_text'];?>"
    });
    $("#new_is_admin_by").multiselect({
        selectedList: 7,
        multiple:false,
        minWidth: 550,
        height: 145,
        checkAllText: "<?php echo $LANG['check_all_text'];?>",
        uncheckAllText: "<?php echo $LANG['uncheck_all_text'];?>"
    });
    $("#share_rights_from, #share_rights_to").multiselect({
        selectedList: 7,
        multiple:false,
        minWidth: 350,
        height: 145,
        checkAllText: "<?php echo $LANG['check_all_text'];?>",
        uncheckAllText: "<?php echo $LANG['uncheck_all_text'];?>"
    });

    //Launch the datatables pluggin
    var tableUsers = $("#t_users").dataTable({
        "order": [[ 1, "asc" ]],
        "ordering": false,
        "pagingType": "full_numbers",
        "processing": true,
        "serverSide": true,
        "ajax": {
            url: "sources/datatable/datatable.users.php",
            data: function(d) {
                d.letter = _alphabetSearch
            }
        },
        "language": {
            "url": "includes/language/datatables.<?php echo $_SESSION['user_language'];?>.txt"
        },
        "columns": [
            {"width": "13%", className: "dt-body-left"},
            {"width": "10%"},
            {"width": "15%"},
            {"width": "15%"},
            {"width": "20%"},
            {"width": "20%"},
            null,
            null,
            null,
            null,
            null,
            null
        ]
    })
    .on('xhr.dt', function ( e, settings, json, xhr ) {
        $(".tip").tooltipster({multiple: true});
    } );

    // manage the Alphabet
    var alphabet = $('<div class="alphabet"/>').append( 'Search: ' );
    $('<span class="clear active"/>')
        .data( 'letter', '' )
        .html( 'None' )
        .appendTo( alphabet );
    for ( var i=0 ; i<26 ; i++ ) {
        var letter = String.fromCharCode( 65 + i );

        $('<span/>')
            .data( 'letter', letter )
            .html( letter )
            .appendTo( alphabet );
    }
    alphabet.insertBefore( "#t_users_alphabet" );
    alphabet.on( 'click', 'span', function () {
        alphabet.find( '.active' ).removeClass( 'active' );
        $(this).addClass( 'active' );

        _alphabetSearch = $(this).data('letter');

        tableUsers.api().ajax.reload();
    } );

    // manage the click on toggle icons
    $(document).on({
        click: function (event) {
            $("#div_loading").show();
            var tmp = $(this).attr('tp').split('-');    //[0]>ID ; [1]>action  ; [2]>NewValue

            // send change to be stored
            $.post(
                "sources/users.queries.php",
                {
                    type    : tmp[1],
                    value   : tmp[2],
                    id      : tmp[0],
                    key        : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
                    $("#div_loading").hide();

                    // manage not allowed
                    if (data.error == "not_allowed") {
                       $("#div_dialog_message_text").html(data.error_text);
                       $("#div_dialog_message").dialog("open");
                       return false;
                    }

                    // refresh table content
                    tableUsers.api().ajax.reload();
                }
            );
        }
    },
    ".fa-toggle-off, .fa-toggle-on"
    );

    // check if login is available
    $("#new_login").change(function() {
        login_exists($(this).val());
    });


    $("#change_user_pw_newpw").simplePassMeter({
        "requirements": {},
          "container": "#pw_strength",
          "defaultText" : "<?php echo $LANG['index_pw_level_txt'];?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $LANG['complex_level0'];?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $LANG['complex_level1'];?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $LANG['complex_level2'];?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $LANG['complex_level3'];?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $LANG['complex_level4'];?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $LANG['complex_level5'];?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $LANG['complex_level6'];?>"
            }
        ]
    });
    $("#change_user_pw_newpw").bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            //$("#pw_strength").val(score);
        }
    });

    $("#change_user_functions").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 400,
        title: "<?php echo $LANG['change_user_functions_title'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                Change_user_rights(document.getElementById("selected_user").value,"functions");
                $(this).dialog("close");
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#change_user_autgroups").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 400,
        title: "<?php echo $LANG['change_user_autgroups_title'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                Change_user_rights(document.getElementById("selected_user").value,"autgroups");
                $(this).dialog("close");
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#change_user_forgroups").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 400,
        title: "<?php echo $LANG['change_user_forgroups_title'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                Change_user_rights(document.getElementById("selected_user").value,"forgroups");
                $(this).dialog("close");
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });;

    $("#change_user_adminby").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 200,
        title: "<?php echo $LANG['is_administrated_by_role'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                $.post(
                    "sources/users.queries.php",
                    {
                        type    :"change_user_adminby",
                        userId : $("#selected_user").val(),
                        isAdministratedByRole : $("#user_admin_by").val(),
                        key    : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        if ($("#user_admin_by").val() == "0") {
                            $("#list_adminby_"+$("#selected_user").val()).
                            html("<?php echo $LANG['admin_small'];?>");
                        } else {
                            $("#list_adminby_"+$("#selected_user").val()).
                            html($("#user_admin_by option:selected").text().match(/"([^"]+)"/)[1]);
                        }
                        $("#change_user_adminby").dialog("close");
                    }
               )
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#add_new_user").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 590,
        height: 620,
        title: "<?php echo $LANG['new_user_title'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                if ($("#new_login").val() == "" || $("#new_pwd").val()=="" || $("#new_email").val()=="") {
                    $("#add_new_user_error").show(1).html("<?php echo $LANG['error_must_enter_all_fields'];?>").delay(1000).fadeOut(1000);
                } else {
                    $("#add_new_user_info").show().html("<span class=\'fa fa-cog fa-spin fa-lg\'></span>&nbsp;<?php echo $LANG['please_wait'];?>");

                    // get lists
                    var forbidFld = "", authFld = "", groups = "";
                    $("#new_user_groups option:selected").each(function () {
                        groups += $(this).val() + ";";
                    });
                    $("#new_user_auth_folders option:selected").each(function () {
                        authFld += $(this).val() + ";";
                    });
                    $("#new_user_forbid_folders option:selected").each(function () {
                        forbidFld += $(this).val() + ";";
                    });

                    //prepare data
                    var data = '{"login":"'+sanitizeString($('#new_login').val())+'", '+
                        '"name":"'+sanitizeString($('#new_name').val())+'", '+
                        '"lastname":"'+sanitizeString($('#new_lastname').val())+'", '+
                        '"pw":"'+sanitizeString($('#new_pwd').val())+'", '+
                        '"email":"'+$("#new_email").val()+'", '+
                        '"admin":"'+$("#new_admin").prop("checked")+'", '+
                        '"manager":"'+$("#new_manager").prop("checked")+'", '+
                        '"read_only":"'+$("#new_read_only").prop("checked")+'", '+
                        '"personal_folder":"'+$("#new_personal_folder").prop("checked")+'", '+
                        '"new_folder_role_domain":"'+$("#new_folder_role_domain").prop("checked")+'", '+
                        '"domain":"'+$('#new_domain').val()+'", '+
                        '"isAdministratedByRole":"'+$("#new_is_admin_by").val()+'", '+
                        '"groups":"' + groups + '", '+
                        '"allowed_flds":"' + authFld + '", '+
                        '"forbidden_flds":"' + forbidFld + '"}';

                    $.post(
                        "sources/users.queries.php",
                        {
                            type    :"add_new_user",
                            data     : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                            key    : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            $("#add_new_user_info").hide().html("");
                            if (data[0].error == "no") {
                                // clear form fields
                                $("#new_name, #new_lastname, #new_login, #new_pwd, #new_is_admin_by, #new_email, #new_domain").val("");
                                $("#new_admin, #new_manager, #new_read_only, #new_personal_folder").prop("checked", false);

                                // refresh table content
                                tableUsers.api().ajax.reload();

                                $("#add_new_user").dialog("close");
                            } else {
                                $("#add_new_user_error").html(data[0].error).show(1).delay(1000).fadeOut(1000);
                            }
                        },
                        "json"
                   )
                }
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#delete_user").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 150,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
            "<?php echo $LANG['ok'];?>": function() {
                $.post(
                    "sources/users.queries.php",
                    {
                        type    : "delete_user",
                        id        : $("#delete_user_id").val(),
                        action    : $("#delete_user_action").val(),
                        key        : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        window.location.href = "index.php?page=manage_users";
                    },
                    "json"
               );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#change_user_pw").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 380,
        height: 300,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
            "<?php echo $LANG['pw_generate'];?>": function() {
                $("#generated_user_pw").html("");
                $("#change_user_pw_wait").show();
                $.post(
                        "sources/main.queries.php",
                        {
                            type       : "generate_a_password",
                            length     : 12,
                            secure     : true,
                            symbols    : true,
                            capitalize : true,
                            numerals   : true
                        },
                        function(data) {
                            prepareExchangedData
                            if (data.error == "true") {
                                $("#div_dialog_message_text").html(data.error_msg);
                                $("#div_dialog_message").dialog("open");
                            } else {
                                $("#change_user_pw_newpw_confirm, #change_user_pw_newpw").val(data.key);
                                $("#generated_user_pw").text(data.key);
                                $("#generated_user_pw, #generated_user_pw_title").show();
                                $("#change_user_pw_newpw").focus();
                            }
                            $("#change_user_pw_wait").hide();
                        }
                   );
            },
            "<?php echo $LANG['save_button'];?>": function() {
                if ($("#change_user_pw_newpw_confirm").val() === "" || $("#change_user_pw_newpw").val() === "") {
                // check if empty
                    $("#change_user_pw_error").html("<?php echo $LANG['error_must_enter_all_fields'];?>").show(1).delay(1000).fadeOut(1000);
                } else if ($("#change_user_pw_newpw").val() === $("#change_user_pw_newpw_confirm").val()) {
                // check if egual
                    var data = "{\"new_pw\":\""+sanitizeString($("#change_user_pw_newpw").val())+"\" , \"user_id\":\""+$("#change_user_pw_id").val()+"\" , \"key\":\"<?php echo $_SESSION['key'];?>\"}";
                    $.post(
                        "sources/main.queries.php",
                        {
                            type    : "change_pw",
                            change_pw_origine    : "admin_change",
                            data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>")
                        },
                        function(data) {
                            if (data[0].error == "none") {
                                $("#change_user_pw_error").html("").hide();
                                $("#change_user_pw_newpw_confirm, #change_user_pw_newpw").val("");
                                $("#change_user_pw").dialog("close");
                            } else if (data[0].error == "key_not_conform") {
                                $("#change_user_pw_error").html("PROTECTION KEY NOT CONFORM!! Try to relog.");
                            } else {
                                $("#change_user_pw_error").html("Something occurs ... no data to work with!");
                            }
                        },
                        "json"
                   );
                } else {
                    $("#change_user_pw_error").html("<?php echo $LANG['error_password_confirmation'];?>").show(1).delay(1000).fadeOut(1000);
                }
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#change_user_pw_newpw_confirm, #change_user_pw_newpw").val("");
                $(this).dialog("close");
            }
        },
        beforeClose: function( event, ui ) {
            $("#change_user_pw_newpw, #change_user_pw_newpw_confirm, #generated_user_pw").val("");
            $("#show_generated_pw").hide();
        }
    });

    $("#user_logs_dialog").dialog({
        bgiframe: false,
        modal: false,
        autoOpen: false,
        width: 850,
        height: 500,
        title: "<?php echo $LANG["logs"];?>",
        buttons: {
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#span_user_activity_option").hide();
                $("#activity").val(0);
                $("#tbody_logs").empty();
                $("#log_pages").empty();
                $(this).dialog("close");
            }
        },
        open: function() {
            $.post(
                "sources/users.queries.php",
                {
                    type    : "user_log_items",
                    page    : $("#log_page").val(),
                    nb_items_by_page:    $("#nb_items_by_page").val(),
                    id        : $("#selected_user").val(),
                    scope    : $("#activity").val()
                },
                function(data) {
                    if (data[0].error == "no") {
                        $("#tbody_logs").empty().append(data[0].table_logs);
                        $("#log_pages").empty().append(data[0].pages);
                    }
                },
                "json"
           );
        }
    });

    $("#manager_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 200,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#user_edit_login_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 280,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                $("#user_edit_login_dialog_message").html("<?php echo $LANG['please_wait'];?>");
                $.post(
                    "sources/users.queries.php",
                    {
                        type    : "user_edit_login",
                        id      : $("#selected_user").val(),
                        login   : $("#edit_login").val(),
                        name    : $("#edit_name").val(),
                        lastname: $("#edit_lastname").val(),
                        key     : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        $("#name_"+$("#selected_user").html()).html($("#edit_name").val());
                        $("#lastname_"+$("#selected_user").val()).html($("#edit_lastname").val());
                        $("#login_"+$("#selected_user").val()).html($("#edit_login").val());
                        $("#user_edit_login_dialog").dialog("close");
                    }
                );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $("#edit_name").val($("#name_"+$("#selected_user").val()).html());
            $("#edit_lastname").val($("#lastname_"+$("#selected_user").val()).html());
            $("#edit_login").val($("#login_"+$("#selected_user").val()).html());
        },
        close: function() {
            $("#user_edit_login_dialog_message").html("");
        }
    });

    var watermark = 'Search a user';

    //init, set watermark text and class
    $('#search').val(watermark).addClass('watermark');

    //if blur and no value inside, set watermark text and class again.
    $('#search').blur(function(){
        if ($(this).val().length == 0){
            $(this).val(watermark).addClass('watermark');
        }
    });

    //if focus and text is watermrk, set it to empty and remove the watermark class
    $('#search').focus(function(){
        if ($(this).val() == watermark){
            $(this).val('').removeClass('watermark');
        }
    });


    $('input[name="search"]').keyup(function(){
        var searchterm = $(this).val();
        if(searchterm.length > 1) {
            var match = $('tr.data-row:containsIN("' + searchterm + '")');
            var nomatch = $('tr.data-row:not(:containsIN("' + searchterm + '"))');
            match.addClass('selected');
            nomatch.css("display", "none");
        } else {
            $('tr.data-row').css("display", "");
            $('tr.data-row').removeClass('selected');
        }
    });

    $("#manager_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 200,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#user_management_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 600,
        height: 550,
        title: "<?php echo $LANG['dialog_admin_user_edit_title'];?>",
        open:  function() {
            $("#user_edit_functions_list, #user_edit_managedby, #user_edit_auth, #user_edit_forbid").empty();
            $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['save_button'];?>')").button("disable");
            $.post(
                "sources/users.queries.php",
                {
                    type : "get_user_info",
                    id   : $('#user_edit_id').val(),
                    key  : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    if (data.error == "no") {
                        $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['save_button'];?>')").button("enable");

                        $("#user_edit_login").val(data.log);
                        $("#user_edit_name").val(data.name);
                        $("#user_edit_lastname").val(data.lastname);
                        $("#user_edit_email").val(data.email);
                        $("#user_edit_info").html(data.info);

                        $("#user_edit_functions_list").append(data.function);
                        $("#user_edit_functions_list").multiselect('refresh');

                        $("#user_edit_managedby").append(data.managedby);
                        $("#user_edit_managedby").multiselect({
                            multiple: false,
                            header: "<?php echo $LANG['select'];?>",
                            noneSelectedText: "<?php echo $LANG['select'];?>",
                            selectedList: 1
                        }, 'refresh');

                        $("#user_edit_auth").append(data.foldersAllow);
                        $("#user_edit_auth").multiselect('refresh');

                        $("#user_edit_forbid").append(data.foldersForbid);
                        $("#user_edit_forbid").multiselect('refresh');

                        $("#user_edit_wait").hide();
                        $("#user_edit_div").show();
                    } else {
                        $("#user_edit_error").html("<?php echo $LANG['error_unknown'];?>")
                        $("#user_edit_wait").hide();
                        $("#user_edit_div").show();
                    }
                },
                "json"
            );

            $("#user_edit_error, #user_edit_warning_bottom").hide().html("");
        },
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                var functions = managedby = allowFld = forbidFld = action_on_user = "";
                // manage the multiselect boxes
                $("#user_edit_functions_list option:selected").each(function () {
                    functions += $(this).val() + ";";
                });
                $("#user_edit_managedby option:selected").each(function () {
                    managedby = $(this).val();
                });
                $("#user_edit_auth option:selected").each(function () {
                    allowFld += $(this).val() + ";";
                });
                $("#user_edit_forbid option:selected").each(function () {
                    forbidFld += $(this).val() + ";";
                });

                // manage the account status
                $(".chk:checked").each(function() {
                    if ($(this).val() == "lock") action_on_user = "lock";
                    else if ($(this).val() == "delete") action_on_user = "delete";
                    else if ($(this).val() == "unlock") action_on_user = "unlock";
                });


                //prepare data
                var data = '{"login":"'+sanitizeString($('#user_edit_login').val())+'", '+
                    '"name":"'+sanitizeString($('#user_edit_name').val())+'", '+
                    '"lastname":"'+sanitizeString($('#user_edit_lastname').val())+'", '+
                    '"email":"'+sanitizeString($('#user_edit_email').val())+'", '+
                    '"action_on_user":"'+sanitizeString(action_on_user)+'", '+
                    '"functions":"'+functions+'", '+
                    '"managedby":"'+managedby+'", '+
                    '"allowFld":"'+allowFld+'", '+
                    '"forbidFld":"'+forbidFld+'"}';

                $("#user_edit_wait").show();
                $.post(
                    "sources/users.queries.php",
                    {
                        type    : "store_user_changes",
                        id      : $('#user_edit_id').val(),
                        data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                        key     : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        if (data[0].error == "no") {

                        }
                        // refresh table content
                        tableUsers.api().ajax.reload();
                        $("#user_management_dialog").dialog("close");
                    },
                    "json"
                );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $("#user_edit_error, #user_edit_warning_bottom").hide().html("");
                $(this).dialog("close");
            }
        }
    });


    $("#share_rights_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 600,
        height: 400,
        title: "<?php echo $LANG['share_user_rights'];?>",
        open:  function() {
            $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['save_button'];?>')").button("disable");
            $.post(
                "sources/users.queries.php",
                {
                    type : "get_list_of_users_for_sharing",
                    key  : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
                    if (data.error === "") {
                        $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['save_button'];?>')").button("enable");

                        $("#share_rights_from").append(data.users_list_from);
                        $("#share_rights_from").multiselect({
                            multiple: false,
                            header: "<?php echo $LANG['select'];?>",
                            noneSelectedText: "<?php echo $LANG['select'];?>",
                            selectedList: 1,
                            maxWidth: "300px;"
                        }, 'refresh');

                        $("#share_rights_to").append(data.users_list_to);
                        $("#share_rights_to").multiselect({
                            multiple: true,
                            header: "<?php echo $LANG['select'];?>",
                            noneSelectedText: "<?php echo $LANG['select'];?>",
                            selectedList: 7,
                            maxWidth: "300px;"
                        }, 'refresh');

                        get_user_rights();
                    } else {
                        $("#share_rights_dialog_error").html("<?php echo $LANG['error_unknown'];?>");
                    }
                }
            );
        },
        close:  function() {
            $("#share_rights_from, #share_rights_to").empty();
            $("#share_rights_details_1, share_rights_details_2, share_rights_details_3, share_rights_details_4").html("");
            $("#share_rights_details_ids_1, #share_rights_details_ids_2, #share_rights_details_ids_3, #share_rights_details_ids_4").val("");
        },
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                $("#share_rights_dialog_msg").html("<?php echo '<i class=\"fa fa-cog fa-spin fa-2x\"></i>&nbsp;'.$LANG['please_wait'];?>").show();

                // destination users
                var destination_ids = '';
                $("#share_rights_to option:selected").each(function () {
                    if ($(this).val() !== $("#share_rights_from").val()) {
                        if (destination_ids === "") {
                            destination_ids = $(this).attr('id').split('-')[1];
                        } else {
                            destination_ids += ";" + $(this).attr('id').split('-')[1];
                        }
                    }
                });

                if (destination_ids === "") {
                    $("#share_rights_dialog_msg").html("").hide();
                    return false;
                }

                $.post(
                    "sources/users.queries.php",
                    {
                        type            : "update_users_rights_sharing",
                        source_id       : $("#share_rights_from option:selected").attr('id').split('-')[1],
                        destination_ids : destination_ids,
                        user_functions  : $("#share_rights_details_ids_1").val(),
                        user_managedby  : $("#share_rights_details_ids_2").val(),
                        user_fldallowed : $("#share_rights_details_ids_3").val(),
                        user_fldforbid  : $("#share_rights_details_ids_4").val(),
                        user_otherrights: $("#share_rights_details_other").val(),
                        key             : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        $("#share_rights_dialog_msg").hide();
                        $("#share_rights_from").empty();
                        $("#share_rights_to option:selected").prop("selected", false);

                        // refresh table content
                        tableUsers.api().ajax.reload();

                        // unselect destination users
                        $("#share_rights_to").multiselect("uncheckAll");

                        $("#share_rights_dialog_msg").html("<?php echo '<i class=\"fa fa-check-circle fa-2x mi-green\"></i>&nbsp;'.$LANG['alert_message_done'];?>").show(0).delay(2000).hide(0);
                    }
                );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });


    $("#user_folders_rights_dialog").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 380,
        height: 600,
        title: "<?php echo $LANG['user_s_rights_on_folders'];?>",
        buttons: {
            "<?php echo $LANG['close'];?>": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $("#user_folders_rights_dialog_wait").show();
            $("#user_folders_rights_dialog_txt").html("");
            $.post(
                "sources/users.queries.php",
                {
                    type    : "user_folders_rights",
                    id      : $('#user_folders_rights_dialog_id').val(),
                    key     : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
                    $("#user_folders_rights_dialog_txt").html(data.html);
                    $("#user_folders_rights_dialog_wait").hide();
                }
            );
        }
    });
});

/*
* Adds some warnings when decision is to delete an account
*/
function confirmDeletion()
{
    if ($("#account_delete").prop("checked") == true) {
        if ($("#confirm_deletion").val() == "") {
            $("#account_delete").prop("checked", false);
            $("#confirm_deletion").val("1");
            $("#user_edit_error").show().html("<?php echo $LANG['user_info_delete'];?>");
        } else {
            $("#user_edit_error").hide().html("");
            $("#user_edit_warning_bottom").show().html("<?php echo $LANG['user_info_delete_warning'];?>");
        }
    } else {
        $("#confirm_deletion").val("");
        $("#user_edit_error, #user_edit_warning_bottom").hide().html("");
        $("#user_edit_deletion_warning").remove();
    }
}

function pwGenerate(elem)
{
    $.post(
        "sources/main.queries.php",
        {
            type    : "generate_a_password",
            size    : Math.floor((8-5)*Math.random()) + 6,
            num        : true,
            maj        : true,
            symb    : false,
            fixed_elem    : 1,
            elem    : elem,
            force    : false
        },
        function(data) {
            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
            if (data.error == "true") {
                $("#div_dialog_message_text").html(data.error_msg);
                $("#div_dialog_message").dialog("open");
            } else {
                $("#"+elem).val(data.key).focus();
            }
        }
   );
}

function action_on_user(id, action)
{
    if (action == "lock") {
        $("#user_action_html").html("<?php echo $LANG['confirm_lock_account'];?>");
    } else {
        $("#user_action_html").html("<?php echo $LANG['confirm_del_account'];?>");
    }
    $("#delete_user_action").val(action);
    $("#delete_user_login").val($("#login_"+id).text());
    $("#delete_user_id").val(id);
    $("#delete_user_show_login").html($("#login_"+id).text());
    $("#delete_user").dialog("open");
}

function mdp_user(id)
{
    $("#generated_user_pw_title, #generated_user_pw").hide();
    $("#change_user_pw_id").val(id);
    $("#change_user_pw_show_login").html($("#user_login_"+id).text());
    $("#change_user_pw").dialog("open");
}

function ChangeUserParm(id, parameter, new_value)
{
    $.post("sources/users.queries.php",
        {
            type    : parameter,
            value   : new_value,
            id      : id,
            key        : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            $("#div_dialog_message_text").html("<div style=\"font-size:16px; text-align:center;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><?php echo $LANG['alert_message_done'];?></div>");$("#div_dialog_message").dialog("open");

        }
   );
}

function Open_Div_Change(id,type)
{
    $("#div_loading").show();
    $.post("sources/users.queries.php",
        {
            type    : "open_div_"+type,
            id      : id,
            key        : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            data = $.parseJSON(data);
            $("#div_loading").hide();
            if (type == "functions") {
                $("#change_user_functions_list").html(data.text);
                $("#selected_user").val(id);
                $("#change_user_functions").dialog("open");
            } else if (type == "autgroups") {
                $("#change_user_autgroups_list").html(data.text);
                $("#selected_user").val(id);
                $("#change_user_autgroups").dialog("open");
            } else if (type == "forgroups") {
                $("#change_user_forgroups_list").html(data.text);
                $("#selected_user").val(id);
                $("#change_user_forgroups").dialog("open");
            }
        }
   );
}

function ChangeUSerAdminBy(id)
{
    $("#selected_user").val(id);
    $("#change_user_adminby").dialog("open");
}

function Change_user_rights(id,type)
{
    var list = "";
    if (type == "functions") var form = document.forms.tmp_functions;
    if (type == "autgroups") var form = document.forms.tmp_autgroups;
    if (type == "forgroups") var form = document.forms.tmp_forgroups;

    $("#div_loading").show();

    for (i=0 ; i<= form.length-1 ; i++) {
        if (form[i].type == "checkbox" && form[i].checked) {
            function_id = form[i].id.split("-")
            if (list == "") list = function_id[1];
            else list = list + ";" + function_id[1];
        }
    }

    $.post("sources/users.queries.php",
        {
            type    : "change_user_"+type,
            id      : id,
            list    : list,
            key     : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            if (type == "functions") {
                $("#list_function_user_"+id).html(data[0].text);
            } else if (type == "autgroups") {
                $("#list_autgroups_user_"+id).html(data[0].text);
            } else if (type == "forgroups") {
                $("#list_forgroups_user_"+id).html(data[0].text);
            }
            $("#div_loading").hide();
        },
        "json"
   );
}

function unlock_user(id)
{
    $.post("sources/users.queries.php",
        {
            type    : "unlock_account",
            id      : id,
            key        : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            document.form_utilisateurs.submit();
        }
   );
};

function check_domain(email)
{
    $("#ajax_loader_new_mail").show();

    //extract domain from email
    var atsign = email.substring(0,email.lastIndexOf("@")+1);
    var domain = email.substring(atsign.length,email.length+1);

    //check if domain exists
    $.post("sources/users.queries.php",
        {
            type        : "check_domain",
            domain      : domain
        },
        function(data) {
            data = $.parseJSON(data);
            $("#new_folder_role_domain").attr("disabled", "disabled");
            if (data.folder == "not_exists" && data.role == "not_exists" && domain !="") {
                $("#new_folder_role_domain").attr("disabled", "");
                $("#auto_create_folder_role_span").html(domain);
                $("#new_domain").val(domain);
                $("#auto_create_folder_role").css('visibility', 'visible');
            } else {
                $("#auto_create_folder_role").css('visibility', 'hidden');
            }
            $("#ajax_loader_new_mail").hide();
        }
   );
}

function displayLogs(page, scope)
{
    $.post(
        "sources/users.queries.php",
        {
            type    : "user_log_items",
            page    :page,
            nb_items_by_page:    $("#nb_items_by_page").val(),
            filter    :$("#activity_filter").val(),
            id        : $("#selected_user").val(),
            scope    : scope
        },
        function(data) {
            if (data[0].error == "no") {
                $("#tbody_logs").empty().append(data[0].table_logs);
                $("#log_pages").empty().append(data[0].pages);
            }
        },
        "json"
   );
}

function user_action_log_items(id)
{
    $("#selected_user").val(id);
    $("#user_logs_dialog").dialog("open");
}

function user_action_ga_code(id)
{
    $("#div_loading").show();
    $.post(
    "sources/main.queries.php",
    {
        type        : "ga_generate_qr",
        id          : id,
        send_email  : "1"
    },
    function(data) {
        if (data[0].error == "0") {
            $("#manager_dialog_error").html("<div><?php echo $LANG['share_sent_ok'];?></div>");
        } else {
            if (data[0].error == "no_email") {
                $("#manager_dialog_error").html("<?php echo $LANG['error_no_email'];?>");
            } else if (data[0].error == "no_user") {
                $("#manager_dialog_error").html("<?php echo $LANG['error_no_user'];?>");
            }
        }
        $("#manager_dialog").dialog('open');
        $("#div_loading").hide();
    },
    "json"
    );
}

function user_edit_login(id)
{
    $("#selected_user").val(id);
    $("#user_edit_login_dialog").dialog("open");
}


/**
 *
 * @access public
 * @return void
 **/
function migrate_pf(user_id)
{
    $("#migrate_pf_admin_id").val(user_id);
    $('#migrate_pf_dialog').dialog('open');
}

/**
*
*/
function user_edit(user_id)
{
    $("#user_edit_wait").show();
    $("#user_edit_div").hide();
    $("#user_edit_id").val(user_id);
    $('#user_management_dialog').dialog('open');
}


/**
*
*/
function get_user_rights()
{
    if ($("#share_rights_from option:selected").length === 0) return false;

    var user_id = $("#share_rights_from option:selected").attr('id').split('-')[1]
    $.post(
        "sources/users.queries.php",
        {
            type : "get_user_info",
            id   : user_id,
            key  : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            if (data.error == "no") {
                $("#share_rights_details").show();

                // functions
                var tmp = "", tmp2 = "";
                var my_json = $.parseJSON(data.share_function);
                $.each(my_json, function(k, v) {
                    if (v['id'] !== "") {
                        if (tmp === "") {
                            tmp = v['title'];
                            tmp2 = v['id'];
                        } else {
                            tmp += " ; " + v['title'];
                            tmp2 += ";"+v['id'];
                        }
                    }
                });
                $("#share_rights_details_1").html(tmp);
                $("#share_rights_details_ids_1").val(tmp2);

                // managed by
                tmp = "";
                tmp2 = "";
                my_json = $.parseJSON(data.share_managedby);
                $.each(my_json, function(k, v) {
                    if (v['id'] !== "") {
                        if (tmp === "") {
                            tmp = v['title'];
                            tmp2 = v['id'];
                        } else {
                            tmp += " ; " + v['title'];
                            tmp2 += ";"+v['id'];
                        }
                    }
                });
                $("#share_rights_details_2").html(tmp);
                $("#share_rights_details_ids_2").val(tmp2);

                // forbidden
                tmp = "";
                tmp2 = "";
                my_json = $.parseJSON(data.share_forbidden);
                $.each(my_json, function(k, v) {
                    if (v['id'] !== "") {
                        if (tmp === "") {
                            tmp = v['title'];
                            tmp2 = v['id'];
                        } else {
                            tmp += " ; " + v['title'];
                            tmp2 += ";"+v['id'];
                        }
                    }
                });
                $("#share_rights_details_3").html(tmp === "" ? "<?php echo $LANG['none'];?>" : tmp);
                $("#share_rights_details_ids_3").val(tmp2);

                // allowed
                tmp = "";
                tmp2 = "";
                my_json = $.parseJSON(data.share_allowed);
                $.each(my_json, function(k, v) {
                    if (v['id'] !== "") {
                        if (tmp === "") {
                            tmp = v['title'];
                            tmp2 = v['id'];
                        } else {
                            tmp += " ; " + v['title'];
                            tmp2 += ";"+v['id'];
                        }
                    }
                });
                $("#share_rights_details_4").html(tmp === "" ? "<?php echo $LANG['none'];?>" : tmp);
                $("#share_rights_details_ids_4").val(tmp2);

                $("#share_rights_details_other").val(data.gestionnaire + ";" + data.read_only + ";" + data.can_create_root_folder + ";" + data.personal_folder + ";" + data.can_manage_all_users + ";" + data.admin);
            }
        },
        "json"
    );
}

/**
* SHOW USER FOLDERS
*/
function user_folders_rights(user_id)
{
    $("#user_folders_rights_dialog_id").val(user_id);
    $('#user_folders_rights_dialog').dialog('open');
}

/**
 *
 */
 function show_user_log(action)
 {
     if (action == "user_activity") {
         $("#span_user_activity_option").show();
         displayLogs(1,'user_activity');
     } else {
         $("#span_user_activity_option").hide();
         displayLogs(1,'user_mngt');
     }
}

/**
* permits to create an automatic login based upon name and lastname
*/
function loginCreation()
{
    $("#new_login").val($("#new_name").val().toLowerCase().replace(/ /g,"")+"."+$("#new_lastname").val().toLowerCase().replace(/ /g,""));
    login_exists($("#new_login").val());
}

/**
* Launches a query to identify if login exists
*/
function login_exists(text) {
    $.post(
        "sources/users.queries.php",
        {
            type    : "is_login_available",
            login   : text,
            key     : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            if (data[0].error === "") {
                if (data[0].exists === "0") {
                    $("#new_login_status").html('<span class="fa fa-check mi-green"></span>').show();
                } else {
                    $("#new_login_status").html('<span class="fa fa-minus-circle mi-red"></span>').show();
                }
            }
        },
        "json"
    );
}

function aes_decrypt(text)
{
    return Aes.Ctr.decrypt(text, "<?php echo $_SESSION['key'];?>", 256);
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
//]]>
</script>
