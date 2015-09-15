<?php
/**
 * @file          users.load.php
 * @author        Nils Laumaillé
 * @version       2.1.23
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

?>

<script type="text/javascript">


	$.extend($.expr[":"], {
		"containsIN": function(elem, i, match, array) {
			return (elem.textContent || elem.innerText || "").toLowerCase().indexOf((match[3] || "").toLowerCase()) >= 0;
		}
	});

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
    $(".button").button();
    //inline editing
    $(".editable_textarea").editable("sources/users.queries.php", {
          indicator : "<img src=\'includes/images/loading.gif\' />",
          type   : "text",
          select : true,
          submit : "<br /><img src=\'includes/images/disk_black.png\' />",
          cancel : "<img src=\'includes/images/cross.png\' />",
          name : "newValue"
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
        }
    });
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
        //tableUsers.draw();
    } );

    $(".fa-toggle-off").click(function(event) {
        var tmp = $(this).prev().attr('tp');
        console.log("TTT"+tmp);
    })



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
        width: 320,
        height: 540,
        title: "<?php echo $LANG['new_user_title'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                if ($("#new_login").val() == "" || $("#new_pwd").val()=="" || $("#new_email").val()=="") {
                    $("#add_new_user_error").show().html("<?php echo $LANG['error_must_enter_all_fields'];?>");
                } else {
                    $("#add_new_user_info").show().html("<?php echo $LANG['please_wait'];?>");

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
                        '"isAdministratedByRole":"'+$("#new_is_admin_by").val()+'"}';

                    $.post(
                        "sources/users.queries.php",
                        {
                            type    :"add_new_user",
                            data     : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
                            key    : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            if (data[0].error == "no") {
                                window.location.href = "index.php?page=manage_users";
                            } else {
                                $("#add_new_user_error").html(data[0].error).show();
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
        width: 430,
        height: 230,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
        	"<?php echo $LANG['pw_generate'];?>": function() {
            	$("#change_user_pw_wait").show();
            	$.post(
                        "sources/main.queries.php",
                        {
                            type       : "generate_a_password",
                            length     : 8,
                            secure     : true,
                            symbols    : false,
                            capitalize : true,
                            numerals   : true
                        },
                        function(data) {
                        	data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key'];?>");
                        	if (data.error == "true") {
                        		$("#div_dialog_message_text").html(data.error_msg);
                        		$("#div_dialog_message").dialog("open");
                        	} else {
                                $("#change_user_pw_newpw_confirm, #change_user_pw_newpw").val(data.key);
                                $("#generated_user_pw").text(data.key);
                                $("#show_generated_pw").show();
                        		$("#change_user_pw_newpw").focus();
                        	}
                    		$("#change_user_pw_wait").hide();
                        }
                   );
        	},
            "<?php echo $LANG['save_button'];?>": function() {
                if ($("#change_user_pw_newpw").val() == $("#change_user_pw_newpw_confirm").val()) {
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
                    $("#change_user_pw_error").html("<?php echo $LANG['error_password_confirmation'];?>");
                    $("#change_user_pw_error").show();
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

    $("#change_user_email").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 200,
        title: "<?php echo $LANG['admin_action'];?>",
        buttons: {
            "<?php echo $LANG['save_button'];?>": function() {
                $.post(
                    "sources/users.queries.php",
                    {
                        type      : "modif_mail_user",
                        id        : $("#change_user_email_id").val(),
                        newemail  : $("#change_user_email_newemail").val(),
                        key       : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        $("#useremail_"+$("#change_user_email_id").val()).attr("title", $("#change_user_email_newemail").val());
                        $("#change_user_email").dialog("close");
                    },
                    "json"
               );
            },
            "<?php echo $LANG['cancel_button'];?>": function() {
                $(this).dialog("close");
            }
        }
    });

    $("#help_on_users").dialog({
        bgiframe: false,
        modal: false,
        autoOpen: false,
        width: 850,
        height: 500,
        title: "<?php echo $LANG["admin_help"];?>",
        buttons: {
            "<?php echo $LANG["close"];?>": function() {
                $(this).dialog("close");
            }
        },
        open: function() {
            $("#accordion").accordion({ autoHeight: false, navigation: true, collapsible: true, active: false });
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
});

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
    $("#change_user_pw_id").val(id);
    $("#change_user_pw_show_login").html($("#login_"+id).text());
    $("#change_user_pw").dialog("open");
}

function mail_user(id,email)
{
    $("#change_user_email_id").val(id);
    $("#change_user_email_show_login").html($("#login_"+id).text());
    $("#change_user_email_newemail").val(email);
    $("#change_user_email").dialog("open");
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
            if (data.folder == "not_exists" && data.role == "not_exists") {
                $("#new_folder_role_domain").attr("disabled", "");
                $("#auto_create_folder_role_span").html(domain);
                $("#new_domain").val(domain);
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
	$.post(
	"sources/main.queries.php",
	{
		type    : "ga_generate_qr",
		id      : id,
		send_email : "1"
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
	},
	"json"
	);
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

function loginCreation()
{
	$("#new_login").val($("#new_name").val().toLowerCase().replace(/ /g,"")+"."+$("#new_lastname").val().toLowerCase().replace(/ /g,""));
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

</script>
