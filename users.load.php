<?php
/**
 * @file 		users.load.php
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


?>

<script type="text/javascript">
function aes_encrypt(text) {
		    return Aes.Ctr.encrypt(text, "<?php echo $_SESSION['key'];?>", 256);
		}

$(function() {
	$(".button").button();
	//inline editing
	$(".editable_textarea").editable("sources/users.queries.php", {
	      indicator : "<img src=\'includes/images/loading.gif\' />",
	      type   : "textarea",
	      select : true,
	      submit : " <img src=\'includes/images/disk_black.png\' />",
	      cancel : " <img src=\'includes/images/cross.png\' />",
	      name : "newlogin"
	});

	$("#change_user_functions").dialog({
	    bgiframe: true,
	    modal: true,
	    autoOpen: false,
	    width: 400,
	    height: 400,
	    title: "<?php echo $txt['change_user_functions_title'];?>",
	    buttons: {
	        "<?php echo $txt['save_button'];?>": function() {
	            Change_user_rights(document.getElementById("selected_user").value,"functions");
	            $(this).dialog("close");
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
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
	    title: "<?php echo $txt['change_user_autgroups_title'];?>",
	    buttons: {
	        "<?php echo $txt['save_button'];?>": function() {
	            Change_user_rights(document.getElementById("selected_user").value,"autgroups");
	            $(this).dialog("close");
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
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
	    title: "<?php echo $txt['change_user_forgroups_title'];?>",
	    buttons: {
	        "<?php echo $txt['save_button'];?>": function() {
	            Change_user_rights(document.getElementById("selected_user").value,"forgroups");
	            $(this).dialog("close");
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
	            $(this).dialog("close");
	        }
	    }
	});

	$("#add_new_user").dialog({
	    bgiframe: true,
	    modal: true,
	    autoOpen: false,
	    width: 320,
	    height: 380,
	    title: "<?php echo $txt['new_user_title'];?>",
	    buttons: {
	        "<?php echo $txt['save_button'];?>": function() {
				if ($("#new_login").val() == "" || $("#new_pwd").val()=="" || $("#new_email").val()==""){
					$("#add_new_user_error").show().html("<?php echo $txt['error_must_enter_all_fields'];?>");
				}else{
					$.post(
						"sources/users.queries.php",
						{
							type    :"add_new_user",
							login	:sanitizeString($("#new_login").val()),
							pw		:encodeURIComponent($("#new_pwd").val()),
							email	:$("#new_email").val(),
							admin	:$("#new_admin").prop("checked"),
							manager	:$("#new_manager").prop("checked"),
							read_only	:$("#new_read_only").prop("checked"),
							personal_folder	:$("#new_personal_folder").prop("checked"),
							new_folder_role_domain	:$("#new_folder_role_domain").prop("checked"),
							domain	:$("#new_domain").val(),
							key	: "<?php echo $_SESSION['key'];?>"
						},
						function(data){
							if(data[0].error == "no"){
								window.location.href = "index.php?page=manage_users";
							}else{
								$("#add_new_user_error").html(data[0].error).show();
							}
						},
						"json"
					)
				}
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
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
	    title: "<?php echo $txt['admin_action'];?>",
	    buttons: {
	        "<?php echo $txt['ok'];?>": function() {
	        	$.post(
					"sources/users.queries.php",
					{
						type    : "delete_user",
						id		: $("#delete_user_id").val(),
						action	: $("#delete_user_action").val(),
						key		: "<?php echo $_SESSION['key'];?>"
					},
					function(data){
						window.location.href = "index.php?page=manage_users";
					},
					"json"
				);
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
	            $(this).dialog("close");
	        }
	    }
	});

	$("#change_user_pw").dialog({
	    bgiframe: true,
	    modal: true,
	    autoOpen: false,
	    width: 400,
	    height: 200,
	    title: "<?php echo $txt['admin_action'];?>",
	    buttons: {
	        "<?php echo $txt['save_button'];?>": function() {
	            if ( $("#change_user_pw_newpw").val() == $("#change_user_pw_newpw_confirm").val() ){
								var data = "{\"new_pw\":\""+sanitizeString($("#change_user_pw_newpw").val())+"\" , \"user_id\":\""+$("#change_user_pw_id").val()+"\" , \"key\":\"<?php echo $_SESSION['key'];?>\"}";
	                $.post(
						"sources/main.queries.php",
						{
							type	: "change_pw",
							change_pw_origine    : "admin_change",
							data	: aes_encrypt(data)
						},
						function(data){
							if (data[0].error == "none") {
								$("#change_user_pw_error").html("").hide();
								$("#change_user_pw_newpw_confirm, #change_user_pw_newpw").val("");
								$("#change_user_pw").dialog("close");
							}else if (data[0].error == "key_not_conform") {
								$("#change_user_pw_error").html("PROTECTION KEY NOT CONFORM!! Try to relog.");
							}else{
								$("#change_user_pw_error").html("Something occurs ... no data to work with!");
							}
						},
						"json"
					);
	            }else{
	                $("#change_user_pw_error").html("<?php echo $txt['error_password_confirmation'];?>");
	                $("#change_user_pw_error").show();
	            }
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
	            $(this).dialog("close");
	        }
	    }
	});

	$("#change_user_email").dialog({
	    bgiframe: true,
	    modal: true,
	    autoOpen: false,
	    width: 400,
	    height: 200,
	    title: "<?php echo $txt['admin_action'];?>",
	    buttons: {
	        "<?php echo $txt['save_button'];?>": function() {
	        	$.post(
					"sources/users.queries.php",
					{
						type    : "modif_mail_user",
						id		:$("#change_user_email_id").val(),
						newemail:$("#change_user_email_newemail").val(),
						key	: "<?php echo $_SESSION['key'];?>"
					},
					function(data){
						$("#change_user_email").dialog("close");
					},
					"json"
				);
	        },
	        "<?php echo $txt['cancel_button'];?>": function() {
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
	    title: "<?php echo $txt["admin_help"];?>",
	    buttons: {
	        "<?php echo $txt["close"];?>": function() {
	            $(this).dialog("close");
	        }
	    },
	    open: function(){
	        $("#accordion").accordion({ autoHeight: false, navigation: true, collapsible: true, active: false });
	    }
	});

	$("#user_logs_dialog").dialog({
	    bgiframe: false,
	    modal: false,
	    autoOpen: false,
	    width: 850,
	    height: 500,
	    title: "<?php echo $txt["logs"];?>",
	    buttons: {
	        "<?php echo $txt['cancel_button'];?>": function() {
	            $(this).dialog("close");
	        }
	    },
	    open: function(){
	    	$.post(
				"sources/users.queries.php",
				{
					type    : "user_log_items",
					page    : $("#log_page").val(),
					nb_items_by_page:	$("#nb_items_by_page").val(),
					id		: $("#selected_user").val()
				},
				function(data){
					if (data[0].error == "no") {
						$("#tbody_logs").empty().append(data[0].table_logs);
						$("#log_pages").empty().append(data[0].pages);
					}
				},
				"json"
			);
		}
	});
});

function pwGenerate(elem){
	$.post(
		"sources/items.queries.php",
		{
			type    : "pw_generate",
			size    : Math.floor((8-5)*Math.random()) + 6,
			num		: true,
			maj		: true,
			symb	: false,
			fixed_elem	: 1,
			elem	: elem,
			force	: false
		},
		function(data){
			data = $.parseJSON(data);
			$("#"+elem).val(data.key).focus();
		}
	);
}

function action_on_user(id, action){
	if(action == "lock"){
		$("#user_action_html").html("<?php echo $txt['confirm_lock_account'];?>");
	}else{
		$("#user_action_html").html("<?php echo $txt['confirm_del_account'];?>");
	}
	$("#delete_user_action").val(action);
	$("#delete_user_login").val($("#login_"+id).text());
	$("#delete_user_id").val(id);
	$("#delete_user_show_login").html($("#login_"+id).text());
	$("#delete_user").dialog("open");
}

function mdp_user(id){
	$("#change_user_pw_id").val(id);
	$("#change_user_pw_show_login").html($("#login_"+id).text());
	$("#change_user_pw").dialog("open");
}

function mail_user(id,email){
	$("#change_user_email_id").val(id);
	$("#change_user_email_show_login").html($("#login_"+id).text());
	$("#change_user_email_newemail").val(email);
	$("#change_user_email").dialog("open");
}

function ChangeUserParm(id, parameter) {
	if (parameter == "can_create_root_folder") {
	    var val = $("#"+parameter+"_"+id+":checked").val();
	    if (val == "on" ) val = 1;
	    else val = 0;
	}else if (parameter == "personal_folder") {
	    var val = $("#"+parameter+"_"+id+":checked").val();
	    if (val == "on" ) val = 1;
	    else val = 0;
	}else if (parameter == "gestionnaire") {
	    var val = $("#"+parameter+"_"+id+":checked").val();
	    if (val == "on" ) val = 1;
	    else val = 0;
	}else if (parameter == "admin") {
	    var val = $("#"+parameter+"_"+id+":checked").val();
	    if (val == "on" ) val = 1;
	    else val = 0;
	}else if (parameter == "read_only") {
		    var val = $("#"+parameter+"_"+id+":checked").val();
		    if (val == "on" ) val = 1;
		    else val = 0;
		}
	$.post("sources/users.queries.php",
	    {
	        type    : parameter,
	        value   : val,
	        id      : id,
	        key		: "<?php echo $_SESSION['key'];?>"
	    },
	    function(data){
	        $("#div_dialog_message_text").html("<div style=\"font-size:16px; text-align:center;\"><span class=\"ui-icon ui-icon-info\" style=\"float: left; margin-right: .3em;\"></span><?php echo $txt['alert_message_done'];?></div>");$("#div_dialog_message").dialog("open");
	    }
	);
}

function Open_Div_Change(id,type){
	$.post("sources/users.queries.php",
	    {
	        type    : "open_div_"+type,
	        id      : id,
	        key		: "<?php echo $_SESSION['key'];?>"
	    },
	    function(data){
	    	data = $.parseJSON(data);
	    	if ( type == "functions" ){
	        	$("#change_user_functions_list").html(data.text);
	        	$("#selected_user").val(id);
	        	$("#change_user_functions").dialog("open");
	        }else if ( type == "autgroups" ){
	        	$("#change_user_autgroups_list").html(data.text);
	        	$("#selected_user").val(id);
	        	$("#change_user_autgroups").dialog("open");
	        }else if ( type == "forgroups" ){
	        	$("#change_user_forgroups_list").html(data.text);
	        	$("#selected_user").val(id);
	        	$("#change_user_forgroups").dialog("open");
	        }
	    }
	);
}

function Change_user_rights(id,type){
	var list = "";
	if ( type == "functions" ) var form = document.forms.tmp_functions;
	if ( type == "autgroups" ) var form = document.forms.tmp_autgroups;
	if ( type == "forgroups" ) var form = document.forms.tmp_forgroups;

	for (i=0 ; i<= form.length-1 ; i++){
	    if (form[i].type == "checkbox" && form[i].checked){
	        function_id = form[i].id.split("-")
	        if ( list == "" ) list = function_id[1];
	        else list = list + ";" + function_id[1];
	    }
	}

	$.post("sources/users.queries.php",
	    {
	        type    : "change_user_"+type,
	        id      : id,
	        list	: list,
	        key		: "<?php echo $_SESSION['key'];?>"
	    },
	    function(data){
	    	if ( type == "functions" ){
	        	$("#list_function_user_"+id).html(data[0].text);
	        }else if ( type == "autgroups" ){
	        	$("#list_autgroups_user_"+id).html(data[0].text);
	        }else if ( type == "forgroups" ){
	        	$("#list_forgroups_user_"+id).html(data[0].text);
	        }
	    },
	    "json"
	);
}

function unlock_user(id){
	$.post("sources/users.queries.php",
	    {
	        type    : "unlock_account",
	        id      : id,
	        key		: "<?php echo $_SESSION['key'];?>"
	    },
	    function(data){
	        document.form_utilisateurs.submit();
	    }
	);
};

function check_domain(email){
	$("#ajax_loader_new_mail").show();

	//extract domain from email
	var atsign = email.substring(0,email.lastIndexOf("@")+1);
	var domain = email.substring(atsign.length,email.length+1);

	//check if domain exists
	$.post("sources/users.queries.php",
	    {
	        type    	: "check_domain",
	        domain      : domain
	    },
	    function(data){
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

function displayLogs(page){
	$.post(
		"sources/users.queries.php",
		{
			type    : "user_log_items",
			page    :page,
			nb_items_by_page:	$("#nb_items_by_page").val(),
			filter	:$("#activity").val(),
			id		: $("#selected_user").val()
		},
		function(data){
			if (data[0].error == "no") {
				$("#tbody_logs").empty().append(data[0].table_logs);
				$("#log_pages").empty().append(data[0].pages);
			}
		},
		"json"
	);
}

function user_action_log_items(id){
	$("#selected_user").val(id);
	$("#user_logs_dialog").dialog("open");
}

/**
 *
 * @access public
 * @return void
 **/
function migrate_pf(user_id){
	$("#migrate_pf_admin_id").val(user_id);
	$('#migrate_pf_dialog').dialog('open');
}
</script>