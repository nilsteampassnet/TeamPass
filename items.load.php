<?php
/**
 * @file          items.load.php
 * @author        Nils Laumaillé
 * @version       2.1.19
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

?>

<script type="text/javascript">
    var query_in_progress = 0;
    ZeroClipboard.setMoviePath("<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/js/zeroclipboard/ZeroClipboard.swf");

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

    function showItemsInTree(type)
    {
        if ($("#img_funnel").attr('src') == "includes/images/funnel_plus.png")
            $("#img_funnel").attr('src','includes/images/funnel_minus.png');
        else
            $("#img_funnel").attr('src','includes/images/funnel_plus.png');
    }

    //FUNCTION mask/unmask passwords characters
    function ShowPassword(pw)
    {
        if ($('#id_pw').html() == '<img src="includes/images/masked_pw.png">' || $('#id_pw').html() == '<IMG src="includes/images/masked_pw.png">') {
            $('#id_pw').text($('#hid_pw').val());
        } else {
            $('#id_pw').html('<img src="includes/images/masked_pw.png" />');
        }
    }

    //Showh the password in new form
    function ShowPasswords_Form()
    {
		if ($('#visible_pw').is(":visible")) {
			$('#visible_pw').hide();
		} else {
			$('#visible_pw').show();
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
		if ($('#visible_editpw').is(":visible")) {
			$('#visible_editpw').hide();
		} else {
			$('#visible_editpw').show();
		}
    }

	$("#edit_pw1").keyup(function() {
	    $("#visible_editpw").text( this.value );
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
        if (modal == "false") {
            $("#"+id).dialog("option", "modal", false);
        } else {
            $("#"+id).dialog("option", "modal", true);
        }
        $("#"+id).dialog("open");
    }

//###########
//## FUNCTION : Launch the listing of all items of one category
//###########
function ListerItems(groupe_id, restricted, start)
{
    $("#request_lastItem").val("");
    if (groupe_id != undefined) {
        if (query_in_progress != 0 && query_in_progress != groupe_id) request.abort();    //kill previous query if needed
        query_in_progress = groupe_id;
        //LoadingPage();
        $("#items_list_loader").show();
        if (start == 0) {
            //clean form
            $('#id_label, #id_pw, #id_email, #id_url, #id_desc, #id_login, #id_info, #id_restricted_to, #id_files, #id_tags, #id_kbs').html("");
            $("#items_list").html("<ul class='liste_items 'id='full_items_list'></ul>");
        }
        $("#items_list").css("display", "");
        $("#selected_items").val("");
        $("#hid_cat").val(groupe_id);
        if ($(".tr_fields") != undefined) $(".tr_fields, .newItemCat, .editItemCat").hide();

        //Disable menu buttons
        $('#menu_button_edit_item,#menu_button_del_item,#menu_button_add_fav,#menu_button_del_fav,#menu_button_show_pw,#menu_button_copy_pw,#menu_button_copy_login,#menu_button_copy_link,#menu_button_copy_item,#menu_button_notify,#menu_button_history,#menu_button_share').attr('disabled', 'disabled');

        // clear existing clips
        //ZeroClipboard.destroy();

        //ajax query
        request = $.post("sources/items.queries.php",
            {
                type     : "lister_items_groupe",
                id         : groupe_id,
                restricted : restricted,
                start    : start,
                key        : "<?php echo $_SESSION['key'];?>",
                nb_items_to_display_once : $("#nb_items_to_display_once").val()
            },
            function(data) {
                //get data
                data = prepareExchangedData(data, "decode");

                // display path of folders
				$("#items_path_var").html(data.arborescence);

                // store the categories to be displayed
                $("#display_categories").val(data.displayCategories);

                if (data.error == "is_pf_but_no_saltkey") {
                    //warn user about his saltkey
                    $("#item_details_no_personal_saltkey").show();
                    $("#item_details_ok, #item_details_nok").hide();

                    $('#menu_button_add_item').attr('disabled', 'disabled');
                    $("#items_list_loader, #div_loading").hide();
                } else if (data.error == "not_authorized") {
                    //warn user
                    $("#hid_cat").val("");
                    $("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_history, #menu_button_share").attr('disabled', 'disabled');
                    $("#item_details_nok").show();
                    $("#item_details_ok, #item_details_no_personal_saltkey").hide();
                    $("#items_list_loader").hide();
                } else if ($("#user_is_read_only").val() == 1 && data.recherche_group_pf == 0) {
                    //readonly user
                    $("#recherche_group_pf").val(data.saltkey_is_required);
                    $("#item_details_no_personal_saltkey, #item_details_nok").hide();
                    $("#item_details_ok, #items_list").show();

                    $("#more_items").remove();

                    if (data.list_to_be_continued == "yes") {
                        $("#full_items_list").append(data.items_html);
                        //set next start for query
                        $("#query_next_start").val(data.next_start);
                    } else {
                        $("#full_items_list").append(data.items_html);
                        $("#query_next_start").val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($(".tr_fields") != undefined && data.displayCategories != "") {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $(".itemCatName_"+liste[i]+", #newItemCatName_"+liste[i]+", #editItemCatName_"+liste[i]).show();
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($(".tr_fields") != undefined) $(".tr_fields").hide();
                        }
                    }
                    //disable buttons
                    $("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item").attr('disabled', 'disabled');

                    proceed_list_update();
                } else {
                    $("#recherche_group_pf").val(data.saltkey_is_required);
                    //Display items
                    $("#item_details_no_personal_saltkey, #item_details_nok").hide();
                    $("#item_details_ok, #items_list").show();
                    $("#items_path_var").html(data.arborescence);
                    $('#complexite_groupe').val(data.folder_complexity);
                    $('#bloquer_creation_complexite').val(data.bloquer_creation_complexite);
                    $('#bloquer_modification_complexite').val(data.bloquer_modification_complexite);

                    if (data.list_to_be_continued == "yes") {
                        $("#full_items_list").append(data.items_html);
                        //set next start for query
                        $("#query_next_start").val(data.next_start);
                    } else {
                        $("#full_items_list").append(data.items_html);
                        $("#query_next_start").val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($(".tr_fields") != undefined && data.displayCategories != "") {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $(".itemCatName_"+liste[i]+", #newItemCatName_"+liste[i]+", #editItemCatName_"+liste[i]).show();
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($(".tr_fields") != undefined) $(".tr_fields").hide();
                        }
                    }

                    //If restriction for role
                    if (restricted == 1) {
                        $("#menu_button_add_item").attr('disabled', 'disabled');
                    } else {
                        $("#menu_button_add_item").prop('disabled', false);
                    }
                    $("#menu_button_copy_item").attr('disabled', 'disabled');

                    $("#menu_button_copy_item, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item").prop("disabled", false);

					// if PF folder, then diable menu create folder
					if ($('#recherche_group_pf').val() == "1") {
						$("#menu_button_add_group").prop("disabled", true);
					} else {
						$("#menu_button_add_group").prop("disabled", false);
					}

                    //If no data then empty
                    if (data.array_items != null) {
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
                                return $("<div class='ui-widget-header'>"+"<?php echo $txt['drag_drop_helper'];?>"+"</div>");
                            }
                        });
                        $(".folder").droppable({
                            hoverClass: "ui-state-active",
                            drop: function(event, ui) {
                                ui.draggable.hide();
                                //move item
                                $.post(
                                    "sources/items.queries.php",
                                      {
                                          type     : "move_item",
                                          item_id : ui.draggable.attr("id"),
                                          folder_id : $(this).attr("id").substring(4),
                                        key        : "<?php echo $_SESSION['key'];?>"
                                      },
                                    function(data) {
                                        //increment / decrement number of items in folders
                                        $("#itcount_"+data[0].from_folder).text(Math.floor($("#itcount_"+data[0].from_folder).text())-1);
                                        $("#itcount_"+data[0].to_folder).text(Math.floor($("#itcount_"+data[0].to_folder).text())+1);
                                    },
                                    "json"
                               );
                            }
                        });
                    }

                    proceed_list_update();
                }

                //Delete data
                delete data;
            }
       );
    }
}

function pwGenerate(elem)
{
    if (elem != "") elem = elem+"_";
    $("#pw1").show();

    //show ajax image
    $("#"+elem+"pw_wait").show();

    $.post(
        "sources/main.queries.php",
        {
            type    : "generate_a_password",
            size      : $("#"+elem+'pw_size').val(),
            num      : $("#"+elem+'pw_numerics').prop("checked"),
            maj      : $("#"+elem+'pw_maj').prop("checked"),
            symb      : $("#"+elem+'pw_symbols').prop("checked"),
            secure  : $("#"+elem+'pw_secure').prop("checked"),
            elem      : elem,
            force      : "false"
        },
        function(data) {
			data = prepareExchangedData(data, "decode");
           	if (data.error == "true") {
           		$("#div_dialog_message_text").html(data.error_msg);
           		$("#div_dialog_message").dialog("open");
           	} else {
           		$("#"+elem+"pw1").val(data.key).focus();
				$("#visible_pw").text(data.key);
           	}
            $("#"+elem+"pw_wait").hide();
        }
   );
}

function pwCopy(elem)
{
    if (elem != "") elem = elem+"_";
    $("#"+elem+'pw2').val($("#"+elem+'pw1').val());
}

function catSelected(val)
{
    $("#hid_cat").val(val);
}

/**
* Get Item complexity
*/
function RecupComplexite(val, edit)
{
	var funcReturned = null;
    $.ajaxSetup({async: false});
    $.post(
        "sources/items.queries.php",
        {
            type    : "recup_complex",
            groupe  : val,
            item_id : $("#selected_items").val()
        },
        function(data) {
        	data = prepareExchangedData(data, "decode");
            //data = $.parseJSON(data);
            if (data.error == undefined || data.error == 0) {
                $("#complexite_groupe").val(data.val);
                if (edit == 1) {
                    $("#edit_complex_attendue").html("<b>"+data.complexity+"</b>");
                    $("#edit_afficher_visibilite").html("<img src='includes/images/users.png'>&nbsp;<b>"+data.visibility+"</b>");
                } else {
                    $("#complex_attendue").html("<b>"+data.complexity+"</b>");
                    $("#afficher_visibilite").html("<img src='includes/images/users.png'>&nbsp;<b>"+data.visibility+"</b>");
                }
            } else if (data.error == "no_edition_possible") {
            	$("#div_dialog_message_text").html(data.error_msg);
                $("#div_dialog_message").dialog("open");
                funcReturned = 0;
            } else {
            	$("#div_formulaire_edition_item").dialog("close");
                $("#div_dialog_message_text").html(data.error_msg);
                $("#div_dialog_message").dialog("open");
            }
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
    $("#div_formulaire_saisi_info").show().html("<?php echo addslashes($txt['please_wait']);?>");
    LoadingPage();
    $("#error_detected").val('');   //Refresh error foolowup
    var erreur = "";
    var  reg=new RegExp("[.|;|:|!|=|+|-|*|/|#|\"|'|&|]");

    //Complete url format
    var url = $("#url").val();
    if (url.substring(0,7) != "http://" && url!="" && url.substring(0,8) != "https://" && url.substring(0,6) != "ftp://" && url.substring(0,6) != "ssh://") {
        url = "http://"+url;
    }

    if ($("#label").val() == "") erreur = "<?php echo $txt['error_label'];?>";
    else if ($("#pw1").val() == "") erreur = "<?php echo $txt['error_pw'];?>";
    else if ($("#categorie").val() == "na") erreur = "<?php echo $txt['error_group'];?>";
    else if ($("#pw1").val() != $("#pw2").val()) erreur = "<?php echo $txt['error_confirm'];?>";
    else if ($("#enable_delete_after_consultation").is(':checked') && (($("#times_before_deletion").val() < 1 && $("#deletion_after_date").val() == "") || ($("#times_before_deletion").val() == "" && $("#deletion_after_date").val() == ""))) erreur = "<?php echo $txt['error_times_before_deletion'];?>";
    else if ($("#item_tags").val() != "" && reg.test($("#item_tags").val())) erreur = "<?php echo $txt['error_tags'];?>";
    else{
        //Check pw complexity level
        if (
            ($("#bloquer_creation_complexite").val() == 0 && parseInt($("#mypassword_complex").val()) >= parseInt($("#complexite_groupe").val()))
            ||
            ($("#bloquer_creation_complexite").val() == 1)
            ||
            ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)
      ) {
            var annonce = 0;
            if ($('#annonce').is(':checked')) annonce = 1;

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
            if (CKEDITOR.instances["desc"]) {
                var description = sanitizeString(CKEDITOR.instances["desc"].getData());
            } else {
                var description = sanitizeString($("#desc").val()).replace(/\n/g, '<br />');
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
                if (fields == "") fields = id[1]+'~~'+$(this).val();
                else fields += '_|_'+id[1]+'~~'+$(this).val();
            });

            //prepare data
            var data = '{"pw":"'+sanitizeString($('#pw1').val())+'", "label":"'+sanitizeString($('#label').val())+'", '+
            '"login":"'+sanitizeString($('#item_login').val())+'", "is_pf":"'+is_pf+'", '+
            '"description":"'+(description)+'", "email":"'+$('#email').val()+'", "url":"'+url+'", "categorie":"'+$('#hid_cat').val()+'", '+
            '"restricted_to":"'+restriction+'", "restricted_to_roles":"'+restriction_role+'", "salt_key_set":"'+$('#personal_sk_set').val()+
            '", "annonce":"'+annonce+'", "diffusion":"'+diffusion+'", "id":"'+$('#id_item').val()+'", '+
            '"anyone_can_modify":"'+$('#anyone_can_modify:checked').val()+'", "tags":"'+sanitizeString($('#item_tags').val())+
            '", "random_id_from_files":"'+$('#random_id').val()+'", "to_be_deleted":"'+to_be_deleted+'", "fields":"'+sanitizeString(fields)+'"}';

            //Send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "new_item",
                    data     : prepareExchangedData(data, "encode"),
                    key        : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    //decrypt data

                    try {
                        data = prepareExchangedData(data, "decode");
                    } catch (e) {
                        // error
                        $("#div_loading").hide();
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);
                        $("#div_dialog_message").dialog("open");

                        return;
                    }

                    //Check errors
                    if (data.error == "item_exists") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('<?php echo addslashes($txt['error_item_exists']);?>');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "something_wrong") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('ERROR!!');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.error == "pw_too_long") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('<?php echo addslashes($txt['error_pw_too_long']);?>');
                        $("#new_show_error").show();
                        LoadingPage();
                    } else if (data.new_id != "") {
                        $("#new_show_error").hide();

                        //add new line directly in list of items
                        $("#full_items_list").append(data.new_entry);

                        //Increment counter
                        $("#itcount_"+$("#hid_cat").val()).text(Math.floor($("#itcount_"+$("#hid_cat").val()).text())+1);

                        AfficherDetailsItem(data.new_id);

                        //empty form
                        $("#label, #item_login, #email, #url, #pw1, #visible_pw, #pw2, #item_tags, #deletion_after_date, #times_before_deletion, #mypassword_complex").val("");
                        CKEDITOR.instances["desc"].setData("");
                        //$("#restricted_to_list").multiselect('uncheckall');TODO
                        $("#item_tabs").tabs({selected: 0});
                        $('ul#full_items_list>li').tsort("",{order:"asc",attr:"name"});
                        $(".fields, .item_field, #categorie, #random_id").val("");
                        $(".fields_div, #item_file_queue").html("");

                        $("#div_formulaire_saisi").dialog('close');
                    }
                    $("#div_formulaire_saisi_info").hide().html("");
                    $("#div_loading").hide();
                }
           );
        } else {
            $('#new_show_error').html("<?php echo addslashes($txt['error_complex_not_enought']);?>").show();
            $("#div_formulaire_saisi_info").hide().html("");
        }
    }
    if (erreur != "") {
        $('#new_show_error').html(erreur).show();
        $("#div_formulaire_saisi_info").hide().html("");
    }
}

function EditerItem()
{
    $("#div_formulaire_edition_item_info").show().html("<?php echo addslashes($txt['please_wait']);?>");
    var erreur = "";
    var  reg=new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

    //Complete url format
    var url = $("#edit_url").val();
    if (url.substring(0,7) != "http://" && url!="" && url.substring(0,8) != "https://" && url.substring(0,6) != "ftp://" && url.substring(0,6) != "ssh://") {
        url = "http://"+url;
    }

    if ($('#edit_label').val() == "") erreur = "<?php echo $txt['error_label'];?>";
    else if ($("#edit_pw1").val() == "") erreur = "<?php echo $txt['error_pw'];?>";
    else if ($("#edit_pw1").val() != $("#edit_pw2").val()) erreur = "<?php echo $txt['error_confirm'];?>";
    else if ($("#edit_tags").val() != "" && reg.test($("#edit_tags").val())) erreur = "<?php echo $txt['error_tags'];?>";
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
      ) {
            LoadingPage();  //afficher image de chargement
            var annonce = 0;
            if ($('#edit_annonce').attr('checked')) annonce = 1;


            //Manage restriction
            var restriction = restriction_role = "";
            $("#edit_restricted_to_list option:selected").each(function () {
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
            var myselect = document.getElementById('edit_annonce_liste_destinataires');
            var diffusion = "";
            for (var loop=0; loop < myselect.options.length; loop++) {
                if (myselect.options[loop].selected == true) diffusion = diffusion + myselect.options[loop].value + ";";
            }
            if (diffusion == ";") {
                diffusion = "";
            }

            //Manage description
            if (CKEDITOR.instances["edit_desc"]) {
                var description = sanitizeString(CKEDITOR.instances["edit_desc"].getData());
            } else {
                var description = sanitizeString($("#edit_desc").val());
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
            if ($("#edit_enable_delete_after_consultation").is(':checked') && ($("#edit_times_before_deletion").val() >= 1 || $("#edit_deletion_after_date").val() != "")) {
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
                if (fields == "") fields = id[2]+'~~'+$(this).val();
                else fields += '_|_'+id[2]+'~~'+$(this).val();
            });

              //prepare data
            var data = '{"pw":"'+sanitizeString($('#edit_pw1').val())+'", "label":"'+sanitizeString($('#edit_label').val())+'", '+
            '"login":"'+sanitizeString($('#edit_item_login').val())+'", "is_pf":"'+is_pf+'", '+
            '"description":"'+description+'", "email":"'+$('#edit_email').val()+'", "url":"'+url+'", "categorie":"'+$("#edit_categorie option:selected").val()+'", '+
            '"restricted_to":"'+restriction+'", "restricted_to_roles":"'+restriction_role+'", "salt_key_set":"'+$('#personal_sk_set').val()+'", "is_pf":"'+$('#recherche_group_pf').val()+'", '+
            '"annonce":"'+annonce+'", "diffusion":"'+diffusion+'", "id":"'+$('#id_item').val()+'", '+
            '"anyone_can_modify":"'+$('#edit_anyone_can_modify:checked').val()+'", "tags":"'+sanitizeString($('#edit_tags').val())+'" ,'+
            '"to_be_deleted":"'+to_be_deleted+'" ,"fields":"'+sanitizeString(fields)+'"}';

            //send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "update_item",
                    data      : prepareExchangedData(data, "encode"),
                    key        : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data, "decode");
                    } catch (e) {
                        // error
                        $("#div_loading").hide();
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text")
                            .html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+
                            data);
                        $("#div_dialog_message").dialog("open");
                        return;
                    }

                    //check if format error
                    if (data.error == "format") {
                        $("#div_loading").hide();
                        $("#edit_show_error").html(data.error+' ERROR (JSON is broken)!!!!!');
                        $("#edit_show_error").show();
                    } else if (data.error == "pw_too_long") {
                        $("#div_loading").hide();
                        $("#edit_show_error").html('<?php echo addslashes($txt['error_pw_too_long']);?>');
                        $("#edit_show_error").show();
                        LoadingPage();
                    } else if (data.error != "") {
                        $("#div_loading").hide();
                        $("#edit_show_error").html('<?php echo addslashes($txt['error_not_allowed_to']);?>');
                        $("#edit_show_error").show();
                        LoadingPage();
                    } else {
                        //refresh item in list
                        $("#fileclass"+data.id).text($('#edit_label').val());

                        //Refresh form
                        $("#id_label").text($('#edit_label').val());
                        //$("#id_pw").text($('#edit_pw1').val());
                        $("#id_email").html($('#edit_email').val());
                        $("#id_url").html($('#edit_url').val());
                        $("#id_desc").html(description);
                        $("#id_login").html($('#edit_item_login').val());
                        $("#id_restricted_to").html(data.list_of_restricted);
                        $("#id_tags").html($('#edit_tags').val());
                        $("#id_files").html(unsanitizeString(data.files));
                        $("#item_edit_list_files").html(data.files_edit);
                        $("#id_info").html(unsanitizeString(data.history));
                        $('#id_pw').html('<img src="includes/images/masked_pw.png" />');

                        //Refresh hidden data
                        $("#hid_label").val($('#edit_label').val());
                        $("#hid_pw").val($('#edit_pw1').val());
                        $("#hid_email").val($('#edit_email').val());
                        $("#hid_url").val($('#edit_url').val());
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
                            $('.edit_item_field').each(function(i){
                                id = $(this).attr('id').split('_');
                                // copy data from form to Item Div
                                $('#id_field_'+id[2]).html($(this).val());
                                $('#hid_field_'+id[2]).val($(this).val());
                                // clear form
                                $(this).val("");
                            });
                        }

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

                        //Close dialogbox
                        $("#div_formulaire_edition_item").dialog('close');
                        //hide loader
                        $("#div_loading").hide();
                    }
                }
           );


        } else {
            $('#edit_show_error').html("<?php echo addslashes($txt['error_complex_not_enought']);?>").show();
            $("#div_formulaire_edition_item_info").hide().html("");
        }
    }

    if (erreur != "") {
        $('#edit_show_error').html(erreur).show();
        $("#div_formulaire_edition_item_info").hide().html("");
    }
}

function AddNewFolder()
{
    if ($("#new_rep_titre").val() == "") {
        $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_label']);?>").show();
    } else if ($("#new_rep_groupe").val() == "0") {
        $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_noparent']);?>").show();
    } else if ($("#new_rep_complexite").val() == "") {
        $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_complex']);?>").show();
    } else{
    	$("#new_rep_show_error").hide();
        if ($("#new_rep_role").val() == undefined) {
            role_id = "<?php echo $_SESSION['fonction_id'];?>";
        } else {
            role_id = $("#new_rep_role").val();
        }

        //prepare data
        var data = '{"title":"'+sanitizeString($('#new_rep_titre').val())+'", "complexity":"'+sanitizeString($('#new_rep_complexite').val())+'", '+
        '"parent_id":"'+$("#new_rep_groupe option:selected").val()+'", "renewal_period":"0"}';

        //send query
        $.post(
            "sources/folders.queries.php",
            {
                type    : "add_folder",
                data      : prepareExchangedData(data, "encode"),
                key        : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                //Check errors
                if (data[0].error == "error_group_exist") {
                    $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_exist']);?>").show();
                } else if (data[0].error == "error_html_codes") {
                    $("#addgroup_show_error").html("<?php echo addslashes($txt['error_html_codes']);?>").show();
                } else {
                    window.location.href = "index.php?page=items";
                }
            },
            "json"
       	);
    }
}


function SupprimerFolder()
{
    if ($("#delete_rep_groupe").val() == "0") {
        alert("<?php echo $txt['error_group'];?>");
    } else if (confirm("<?php echo $txt['confirm_delete_group'];?>")) {
        $.post(
            "sources/folders.queries.php",
            {
                type    : "delete_folder",
                id      : $("#delete_rep_groupe").val(),
                key        : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                window.location.href = "index.php?page=items";
            }
       );
    }
}

function AfficherDetailsItem(id, salt_key_required, expired_item, restricted, display, open_edit, reload)
{
    // If a request is already launched, then kill new.
    if ($("#request_ongoing").val() != "") {
        request.abort();
        return;
    }

    // Store status query running
    $("#request_ongoing").val("1");

    // If opening new item, reinit hidden fields
    if ($("#request_lastItem").val() != id) {
        $("#request_lastItem").val("");
        $("#item_editable").val("");
    }

    // Don't show details
    if (display == "no_display") {
        $("#item_details_nok").show();
        $("#item_details_ok").hide();
        $("#item_details_expired").hide();
        $("#item_details_expired_full").hide();
        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, menu_button_copy_url, #menu_button_copy_link").attr("disabled","disabled");
        $("#request_ongoing").val("");
        return false;
    }
    $("#div_loading").show();//LoadingPage();
    if ($("#is_admin").val() == "1") {
        $('#menu_button_edit_item,#menu_button_del_item,#menu_button_copy_item').attr('disabled', 'disabled');
    }

    if ($("#edit_restricted_to") != undefined) {
        $("#edit_restricted_to").val("");
    }

    // Check if personal SK is needed and set
    if (($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 0) && salt_key_required == 1) {
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($txt['alert_message_personal_sk_missing']);?><\/div>");
        $("#div_loading").hide();//LoadingPage();
        $("#div_dialog_message").dialog("open");
    } else if ($('#recherche_group_pf').val() == 0 || ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)) {
        // Double click
        if (open_edit == 1 && $("#item_editable").val() == 1 && reload != 1) {
            $("#request_ongoing").val("");
            open_edit_item_div(
                        <?php if (isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) {
    echo 1;
} else {
    echo 0;
}?>);
        } else if ($("#request_lastItem").val() == id && reload != 1) {
            $("#request_ongoing").val("");
            LoadingPage();
            return;
        } else {
            $("#timestamp_item_displayed").val("");
            //Send query
            $.post(
                "sources/items.queries.php",
                {
                    type                : 'show_details_item',
                    id                  : id,
                    folder_id           : $('#hid_cat').val(),
                    salt_key_required   : $('#recherche_group_pf').val(),
                    salt_key_set        : $('#personal_sk_set').val(),
                    expired_item        : expired_item,
                    restricted          : restricted,
					page				: "items",
                    key                 : "<?php echo $_SESSION['key'];?>"
                },
                function(data) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data, "decode");
                    } catch (e) {
                        // error
                        $("#div_loading").hide();
                        $("#request_ongoing").val("");
                        $("#div_dialog_message_text").html("An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />"+data);
                        $("#div_dialog_message").show();
                        return;
                    }

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
                        $("#edit_item_salt_key").hide();
                    }

                    //Show detail item
                    if (data.show_detail_option == "0") {
                        $("#item_details_ok").show();
                        $("#item_details_expired, #item_details_expired_full").hide();
                    }if (data.show_detail_option == "1") {
                        $("#item_details_ok, #item_details_expired").show();
                        $("#item_details_expired_full").hide();
                    } else if (data.show_detail_option == "2") {
                        $("#item_details_ok, #item_details_expired, #item_details_expired_full").hide();
                    }
                    $("#item_details_nok").hide();
                    $("#fileclass"+data.id).addClass("fileselected");
                    $("item_editable").val(0);

                    if (data.show_details == "1" && data.show_detail_option != "2") {
                        //unprotect data
                        data.login = unsanitizeString(data.login);

                        //Display details
                        $("#id_label").html(data.label).html();
                        $("#hid_label").val(data.label);
                        $("#id_pw").html('<img src="includes/images/masked_pw.png" />');
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
                        $("#id_restricted_to").html(data.id_restricted_to+data.id_restricted_to_roles);
                        $("#hid_restricted_to").val(data.id_restricted_to);
                        $("#hid_restricted_to_roles").val(data.id_restricted_to_roles);
                        $("#id_tags").html(data.tags).html();
                        $("#hid_tags").val($("#id_tags").html());
                        $("#hid_anyone_can_modify").val(data.anyone_can_modify);
                        $("#id_categorie").val(data.folder);
                        $("#id_item").val(data.id);
                        $("#id_kbs").html(data.links_to_kbs);
                        $(".tip").tooltip();

                        // show Field values
                        $(".fields").val("");
                        $(".fields_div").html("");
                        var liste = data.fields.split('_|_');
                        for (var i=0; i<liste.length; i++) {
                            var field = liste[i].split('~~');
                            $('#id_field_'+field[0]).html(field[1]);
                            $('#hid_field_'+field[0]).val(field[1]);
                        }

                        //Anyone can modify button
                        if (data.anyone_can_modify == "1") {
                            $("#edit_anyone_can_modify").attr('checked', true);
                            $("#new_history_entry_form").show();
                        } else {
                            $("#edit_anyone_can_modify").attr('checked', false);
                            $("#new_history_entry_form").hide();
                        }

                        //Show to be deleted in case activated
                        if (data.to_be_deleted == "not_enabled") {
                            $("#edit_to_be_deleted").hide();
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
            		        $("#menu_button_share").attr('disabled', 'disabled');
            		    } else {
            		    	$("#menu_button_share").prop("disabled", false);
            		    }

                        //Manage to deleted information
                        if (data.to_be_deleted != 0 && data.to_be_deleted != null && data.to_be_deleted != "not_enabled") {
                            $('#item_extra_info').html("<i><img src=\'&nbsp;<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/information-white.png\'><?php echo addslashes($txt['automatic_deletion_activated']);?></i>");
                        } else {
                            $('#item_extra_info').html("");
                        }

                        if (data.notification_status == 0 && data.id_user == <?php echo $_SESSION['user_id'];?>) {
                            $('#menu_button_notify')
                                .prop("disabled", false)
                                .attr('title','<?php echo $txt['enable_notify'];?>')
                                .attr('onclick','notify_click(\'true\')');
                            $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock-plus.png');
                        } else if (data.notification_status == 1 && data.id_user == <?php echo $_SESSION['user_id'];?>) {
                            $('#menu_button_notify')
                                .prop("disabled", false)
                                .attr('title','<?php echo $txt['disable_notify'];?>')
                                .attr('onclick','notify_click(\'false\')');
                            $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock-minus.png');
                            $('#item_extra_info').html("<i><img src=\'<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock.png\'>&nbsp;<?php echo addslashes($txt['notify_activated']);?></i>");
                        } else {
                            $('#menu_button_notify').attr('disabled', 'disabled');
                            $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock.png');
                        }

                        //Prepare clipboard copies
                        if (data.pw != "") {
                            var clip = new ZeroClipboard.Client();
                            clip.setText(unsanitizeString(data.pw));
                            clip.addEventListener('complete', function(client, text) {
                                    $("#message_box").html("<?php echo addslashes($txt['pw_copied_clipboard']);?>").show().fadeOut(1000);
                            });
                            clip.glue('menu_button_copy_pw');
                        }
                        if (data.login != "") {
                            var clip = new ZeroClipboard.Client();
                            clip.setText(data.login);
                            clip.addEventListener('complete', function(client, text) {
                                    $("#message_box").html("<?php echo addslashes($txt['login_copied_clipboard']);?>").show().fadeOut(1000);
                            });
                            clip.glue('menu_button_copy_login');
                        }
                        // #XXX
                        if (data.url != "") {
                            var clip = new ZeroClipboard.Client();
                            clip.setText(data.url);
                            clip.addEventListener('complete', function(client, text) {
                                    $("#message_box").html("<?php echo addslashes($txt['url_copied_clipboard']);?>").show().fadeOut(1000);
                            });
                            clip.glue('menu_button_copy_url');
                        }
                        //prepare link to clipboard
                        var clip = new ZeroClipboard.Client();
                        clip.setText("<?php echo $_SESSION['settings']['cpassman_url'];?>/index.php?page=items&group="+data.folder+"&id="+data.id);
                        clip.addEventListener('complete', function(client, text) {
                                $("#message_box").html("<?php echo addslashes($txt['url_copied']);?>").show().fadeOut(1000);
                        });
                        clip.glue('menu_button_copy_link');

                        // function calling image lightbox when clicking on link
                        $("a.image_dialog").click(function(event) {
                            event.preventDefault();
                            PreviewImage($(this).attr("href"),$(this).attr("title"));
                        });

                        //set if user can edit
                        if (data.restricted == "1" || data.user_can_modify == "1") {
                            $("#item_editable").val(1);
                        }

                        //Manage double click
                        if (open_edit == true && (data.restricted == "1" || data.user_can_modify == "1")) {
                            open_edit_item_div(
                            <?php if (isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) {
    echo 1;
} else {
    echo 0;
}?>);
                        }

						// continue loading data
						showDetailsStep2(id, param);

                    } else if (data.show_details == "1" && data.show_detail_option == "2") {
                        $("#item_details_nok").hide();
                        $("#item_details_ok").hide();
                        $("#item_details_expired_full").show();
                        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");
						$("#div_loading").hide();
                    } else {
                        //Dont show details
                        $("#item_details_nok").show();
                        $("#item_details_nok_restriction_list").html('<div style="margin:10px 0 0 20px;"><b><?php echo $txt['author'];?>: </b>'+data.author+'<br><b><?php echo $txt['restricted_to'];?>: </b>'+data.restricted_to+'<br><br><u><a href="#" onclick="SendMail(\'request_access_to_author\',\''+data.id+','+data.id_user+'\',\'<?php echo $_SESSION['key'];?>\',\'<?php echo addslashes($txt['forgot_my_pw_email_sent']);?>\')"><?php echo addslashes($txt['request_access_ot_item']);?></a></u></div>');
                        $("#item_details_ok").hide();
                        $("#item_details_expired").hide();
                        $("#item_details_expired_full").hide();
                        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");
						$("#div_loading").hide();
                    }
                    $("#request_ongoing").val("");
                }
           );
       }
    }
    //Store Item id shown
    $("#request_lastItem").val(id);
}


/*
* Loading Item details step 2
*/
function showDetailsStep2(id, param)
{
	$("#div_loading").show();
	$.post(
		"sources/items.queries.php",
		{
		type     : "showDetailsStep2",
		id         : id
		},
		function(data) {
			data = prepareExchangedData(data, "decode");

			$("#item_history_log").html(htmlspecialchars_decode(data.history));
			$("#edit_past_pwds").attr('title', data.history_of_pwds);

            $("#id_files").html(data.files_id);
            $("#hid_files").val(data.files_id);
            $("#item_edit_list_files").html(data.files_edit).html();

			$("#div_last_items").html(htmlspecialchars_decode(data.div_last_items));

            //Set favourites icon
            if (data.favourite == "1") {
                $("#menu_button_add_fav").attr("disabled","disabled");
                $("#menu_button_del_fav").prop("disabled", false);
            } else {
                $("#menu_button_add_fav").prop("disabled", false);
                $("#menu_button_del_fav").attr("disabled","disabled");
            }

			$(param).prop("disabled", false);
			$("#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history").prop("disabled", false);
			$("#div_loading").hide();
	     }
	 );
};

/*
   * FUNCTION
   * Launch an action when clicking on a quick icon
   * $action = 0 => Make not favorite
   * $action = 1 => Make favorite
*/
function ActionOnQuickIcon(id, action)
{
    //change quick icon
    if (action == 1) {
        $("#quick_icon_fav_"+id).html("<img src='includes/images/mini_star_enable.png' onclick='ActionOnQuickIcon("+id+",0)' //>");
    } else if (action == 0) {
        $("#quick_icon_fav_"+id).html("<img src='includes/images/mini_star_disable.png' onclick='ActionOnQuickIcon("+id+",1)' //>");
    }

    //Send query
    $.post("sources/items.queries.php",
        {
            type    : 'action_on_quick_icon',
            id      : id,
            action  : action
        }
   );
}

//###########
//## FUNCTION : prepare new folder dialogbox
//###########
function open_add_group_div()
{
    //Select the actual folder in the dialogbox
    $('#new_rep_groupe').val($('#hid_cat').val());
    $('#div_ajout_rep').dialog('open');
}

//###########
//## FUNCTION : prepare editing folder dialogbox
//###########
function open_edit_group_div()
{
	// disable folder selection if PF
	if ($('#recherche_group_pf').val() == "1") {
		$("#edit_folder_folder").prop("disabled", true);
	} else {
		$("#edit_folder_folder").prop("disabled", false);
	}

    //Select the actual forlder in the dialogbox
    $('#edit_folder_folder').val($('#hid_cat').val());
    $('#edit_folder_title').val($.trim($('#edit_folder_folder :selected').text()));
    $('#edit_folder_complexity').val($('#complexite_groupe').val());
    $('#div_editer_rep').dialog('open');
}

//###########
//## FUNCTION : prepare delete folder dialogbox
//###########
function open_del_group_div()
{
	// if PF folder, then not allowed to delete
	if ($('#recherche_group_pf').val() == "1") {
		$("#delete_rep_groupe").prop("disabled", true);
	} else {
		$("#delete_rep_groupe").prop("disabled", false);
	}

    $('#delete_rep_groupe').val($('#hid_cat').val());
    $('#div_supprimer_rep').dialog('open');
}

//###########
//## FUNCTION : prepare new item dialogbox
//###########
function open_add_item_div()
{
    LoadingPage();

    //Check if personal SK is needed and set
    if ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 0) {
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($txt['alert_message_personal_sk_missing']);?><\/div>");
        LoadingPage();
        $("#div_dialog_message").dialog("open");
    } else if ($("#hid_cat").val() == "") {
        LoadingPage();
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($txt['error_no_selected_folder']);?><\/div>").dialog("open");
    } else if ($('#recherche_group_pf').val() == 0 || ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)) {
        //Select the actual forlder in the dialogbox
        $('#categorie').val($('#hid_cat').val());

        //Get the associated complexity level
        RecupComplexite($('#hid_cat').val(), 0);

        //Show WYGIWYS editor if enabled
        //if ($('#richtext_on').val() == "1") {
            CKEDITOR.replace(
                "desc",
                {
                    toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]],
                    height: 100,
                    language: "<?php echo $_SESSION['user_language_code'];?>"
                }
           );
        //}
        if ($("#recherche_group_pf").val() == 1) {
            $("#div_editRestricted").hide();
        } else {
        	$("#div_editRestricted").show();
        }

        //open dialog
        $("#div_formulaire_saisi_info").hide().html("");
        $("#div_formulaire_saisi").dialog("open");
    }
}

//###########
//## FUNCTION : prepare editing item dialogbox
//###########
function open_edit_item_div(restricted_to_roles)
{
	$("#div_loading").show();

    // If no Item selected, no edition possible
	if ($("#selected_items").val() == "") {
		$("#div_loading").hide();
	    return;
	}

    // Get complexity level for this folder
    // and stop edition if Item edited by another user
    if (RecupComplexite($('#hid_cat').val(), 1) == 0) {
        if (CKEDITOR.instances["edit_desc"]) {
            CKEDITOR.instances["edit_desc"].destroy();
        }
        $("#div_loading").hide();
        return;
    }

    // Check if Item has changed since loaded
    if (CheckIfItemChanged() == 1) {
        var tmp = $("#"+$("#selected_items").val()).attr("ondblclick");
        tmp = tmp.substring(20,tmp.indexOf(")"));
        tmp = tmp.replace(/'/g, "").split(',');
        AfficherDetailsItem(tmp[0], tmp[1], tmp[2], tmp[3], tmp[4], 1, 1);
        $("#div_loading").hide();
        return;
    }

    // Show WYGIWYG editor
    CKEDITOR.replace(
        "edit_desc",
        {
            toolbar :[["Bold", "Italic", "Strike", "-", "NumberedList", "BulletedList", "-", "Link","Unlink","-","RemoveFormat"]],
            height: 100,
            language: "<?php echo $_SESSION['user_language_code'];?>"
        }
   );
    CKEDITOR.instances["edit_desc"].setData($('#hid_desc').val());

    $('#edit_display_title').html($('#hid_label').val());
    $('#edit_label').val($('#hid_label').val());
    $('#edit_desc').html($('#hid_desc').val());
    $('#edit_pw1, #edit_pw2').val($('#hid_pw').val());
	$("#visible_editpw").text($('#hid_pw').val());
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
            $('#edit_field_'+id[2]).val(htmlspecialchars_decode($('#hid_field_'+id[2]).val()));
        });
    }

    //Get list of people in restriction list
    if ($("#recherche_group_pf").val() == 1) {
        $("#div_editRestricted").hide();
    } else {
    	$("#div_editRestricted").show();
        if ($('#edit_restricted_to').val() != undefined) {
            $('#edit_restricted_to_list').empty();
            if (restricted_to_roles == 1) {
                //add optgroup
                var optgroup = $('<optgroup>');
                optgroup.attr('label', "<?php echo $txt['users'];?>");
                $("#edit_restricted_to_list option:last").wrapAll(optgroup);
            }
            var liste = $('#input_liste_utilisateurs').val().split(';');
            for (var i=0; i<liste.length; i++) {
                var elem = liste[i].split('#');
                if (elem[0] != "") {
                    $("#edit_restricted_to_list").append("<option value='"+elem[0]+"'>"+elem[1]+"</option>");
                    var index = $('#edit_restricted_to').val().lastIndexOf(elem[1]+";");
                    if (index != -1) {
                        $("#edit_restricted_to_list option[value="+elem[0]+"]").attr('selected', true);
                    }
                }
            }
        }

        //Add list of roles if option is set
        if (restricted_to_roles == 1 && $('#edit_restricted_to').val() != undefined) {
            var j = i;
            //add optgroup
            var optgroup = $('<optgroup>');
            optgroup.attr('label', "<?php echo $txt['roles'];?>");

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

        //Prepare multiselect widget
        $("#edit_restricted_to_list").multiselect({
            selectedList: 7,
            minWidth: 430,
            height: 145,
            checkAllText: "<?php echo $txt['check_all_text'];?>",
            uncheckAllText: "<?php echo $txt['uncheck_all_text'];?>",
            noneSelectedText: "<?php echo $txt['none_selected_text'];?>"
        });
        $("#edit_restricted_to_list").multiselect('refresh');
    }

	// disable folder selection if PF
	if ($('#recherche_group_pf').val() == "1") {
		$("#edit_categorie").prop("disabled", true);
	} else {
		$("#edit_categorie").prop("disabled", false);
	}

    //open dialog
    $("#div_formulaire_edition_item_info").hide().html("");
    $("#div_formulaire_edition_item").dialog("open");
}

//###########
//## FUNCTION : prepare new item dialogbox
//###########
function open_del_item_div()
{
    $('#div_del_item').dialog('open');
}

//###########
//## FUNCTION : prepare copy item dialogbox
//###########
function open_copy_item_to_folder_div()
{
    $('#copy_in_folder').val($("#hid_cat").val());
    $('#div_copy_item_to_folder').dialog('open');
}

$("#div_copy_item_to_folder").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 200,
        title: "<?php echo $txt['item_menu_copy_elem'];?>",
        buttons: {
            "<?php echo $txt['ok'];?>": function() {
                //Send query
                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "copy_item",
                        item_id : $('#id_item').val(),
                        folder_id : $('#copy_in_folder').val(),
                        key        : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        //check if format error
                        if (data[0].error == "no_item") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
                        } else if (data[0].error == "not_allowed") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
                        }
                        //if OK
                        if (data[0].status == "ok") {
                            window.location.href = "index.php?page=items&group="+$('#copy_in_folder').val()+"&id="+data[1].new_id;
                        }
                    },
                    "json"
               );
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $("#copy_item_to_folder_show_error").html("").hide();
                $(this).dialog('close');
            }
        }
    });

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
function delete_attached_file(file_id)
{
    $.post(
        "sources/items.queries.php",
        {
            type    : "delete_attached_file",
            file_id  : file_id
        },
        function(data) {
            $("#span_edit_file_"+file_id).css("textDecoration", "line-through");
        }
   );
}

//###########
//## FUNCTION : Permits to preview an attached image
//###########
PreviewImage = function(uri,title) {
    $("#dialog_files").html('<img id="image_files" src="" />');
    //Get the HTML Elements
    imageDialog = $("#dialog_files");
    imageTag = $('#image_files');

    //Split the URI so we can get the file name
    uriParts = uri.split("/");

    //Set the image src
    imageTag.attr('src', uri);

    //When the image has loaded, display the dialog
    imageTag.load(function() {
        imageDialog.dialog({
            modal: true,
            resizable: false,
            draggable: false,
            width: 'auto',
            title: title
        });
    });
}

/**
 *
 * @access public
 * @return void
 **/
function get_clipboard_item(field,id)
{
	clip = new ZeroClipboard.Client();
    if (field == "pw") {
    	clip.setText($("#item_pw_in_list_"+id).val());
        clip.addEventListener('complete', function(client, text) {
            $("#message_box").html("<?php echo addslashes($txt['pw_copied_clipboard']);?>").show().fadeOut(1000);
            clip.destroy();
        });
        clip.glue('iconpw_'+id);
    } else {
    	clip.setText($("#item_login_in_list_"+id).val());
        clip.addEventListener('complete', function(client, text) {
            $("#message_box").html("<?php echo addslashes($txt['login_copied_clipboard']);?>").show().fadeOut(1000);
            clip.destroy();
        });
        clip.glue('iconlogin_'+id);
    }

    var div = $('#items_list');
    var box = ZeroClipboard.getDOMObjectPosition(clip.domElement);
    clip.div.style.top = '' + (box.top - $(div).scrollTop()) + 'px';
};

function notify_click(status)
{
    $.post("sources/items.queries.php",
            {
                type     : "notify_a_user",
                user_id : <?php echo $_SESSION['user_id'];?>,
                status    : status,
                notify_type : 'on_show',
                notify_role : '',
                item_id : $('#id_item').val(),
                key        : "<?php echo $_SESSION['key'];?>"
            },
            function(data) {
                if (data[0].error == "something_wrong") {
                    $("#new_show_error").html('ERROR!!');
                    $("#new_show_error").show();
                } else {
                    $("#new_show_error").hide();
                    if (data[0].new_status == "true") {
                        $('#menu_button_notify')
                            .attr('title','<?php echo $txt['disable_notify'];?>')
                            .attr('onclick','notify_click(\'false\')');
                        $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock-minus.png');
                        $('#item_extra_info').html("<?php echo addslashes($txt['notify_activated']);?>");
                    } else if (data[0].new_status == "false") {
                        $('#menu_button_notify')
                            .attr('title','<?php echo $txt['enable_notify'];?>')
                            .attr('onclick','notify_click(\'true\')');
                        $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock-plus.png');
                        $('#item_extra_info').html("");
                    }
                }
            },
            "json"
          );
}

//###########
//## EXECUTE WHEN PAGE IS LOADED
//###########
$(function() {
    $('#toppathwrap').hide();
    if ($(".tr_fields") != undefined) $(".tr_fields").hide();
    //Expend/Collapse jstree
    $("#jstree_close").click(function() {
        $("#jstree").jstree("close_all", -1);
    });
    $("#jstree_open").click(function() {
        $("#jstree").jstree("open_all", -1);
    });
    $("#jstree_search").keypress(function(e) {
        if (e.keyCode == 13) {
            $("#jstree").jstree("search",$("#jstree_search").val());
        }
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
    $(".items_tree, #items_content, #item_details_ok").addClass("ui-corner-all");

    //automatic height
    var window_height = $(window).height();
    $("#div_items, #content").height(window_height-170);
    $("#items_center").height(window_height-390);
    $("#items_list").height(window_height-440);
    $(".items_tree").height(window_height-160);
    $("#jstree").height(window_height-185);

    //warning if screen height too short
    if (parseInt(window_height-440) <= 50) {
        $("#div_dialog_message_text").html("<?php echo addslashes($txt['warning_screen_height']);?>");
        $("#div_dialog_message").dialog('open');
    }

    //Evaluate number of items to display - depends on screen height
    if (parseInt($("#nb_items_to_display_once").val()) || $("#nb_items_to_display_once").val() == "max") {
        //do nothing ... good value
    } else {
        //adapt to the screen height
        $("#nb_items_to_display_once").val(Math.round((window_height-450)/23));
    }

    // Build buttons
    $("#custom_pw, #edit_custom_pw").buttonset();
    $(".cpm_button, #anyone_can_modify, #annonce, #edit_anyone_can_modify, #edit_annonce, .button").button();

    //Build multiselect box
    $("#restricted_to_list").multiselect({
        selectedList: 7,
        minWidth: 430,
        height: 145,
        checkAllText: "<?php echo $txt['check_all_text'];?>",
        uncheckAllText: "<?php echo $txt['uncheck_all_text'];?>",
        noneSelectedText: "<?php echo $txt['none_selected_text'];?>"
    });

    //Build tree - "cookies",
    $("#jstree").jstree({
    	"themes" : {
    		"theme" : "default",
    		"url" : "includes/js/jstree/themes/default"
    	},
        "plugins" : ["themes", "html_data", "ui", "search", "cookies"]
    })
    //search in tree
    .bind("search.jstree", function (e, data) {
        if (data.rslt.nodes.length == 1) {
            //open the folder
            ListerItems($("#jstree li>a.jstree-search").attr('id').split('_')[1], '', 0);
        }
    });

    $("#add_folder").click(function() {
        var posit = $('#item_selected').val();
        //alert($("ul").text());
    });

    $("#for_searchtext").hide();
    $("#copy_pw_done").hide();
    $("#copy_login_done").hide();

    //PREPARE DIALOGBOXES
    //=> ADD A NEW GROUP
    $("#div_ajout_rep").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 200,
        title: "<?php echo $txt['item_menu_add_rep'];?>",
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                AddNewFolder();
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $("#new_rep_show_error").html("").hide();
                $(this).dialog('close');
            }
        }
    });
    //<=
    //=> EDIT A GROUP
    $("#div_editer_rep").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 400,
        height: 250,
        title: "<?php echo $txt['item_menu_edi_rep'];?>",
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                //Do some checks
                $("#edit_rep_show_error").hide();
                if ($("#edit_folder_title").val() == "") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($txt['error_group_label']);?>");
                    $("#edit_rep_show_error").show();
                } else if ($("#edit_folder_folder").val() == "0") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($txt['error_group']);?>");
                    $("#edit_rep_show_error").show();
                } else if ($("#edit_folder_complexity").val() == "") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($txt['error_group_complex']);?>");
                    $("#edit_rep_show_error").show();
                } else {
                    //prepare data
                    var data = '{"title":"'+$('#edit_folder_title').val().replace(/"/g,'&quot;') + '", '+
                    '"complexity":"'+$('#edit_folder_complexity').val()+'", '+
                    '"folder":"'+$('#edit_folder_folder').val()+'"}';

                    //Send query
                    $.post(
                        "sources/items.queries.php",
                        {
                            type    : "update_rep",
                            data      : prepareExchangedData(data, "encode"),
                            key        : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            //check if format error
                            if (data[0].error == "") {
                                $("#folder_name_"+$('#edit_folder_folder').val()).text($('#edit_folder_title').val());
                                $("#path_elem_"+$('#edit_folder_folder').val()).text($('#edit_folder_title').val());
                                $("#fld_"+$('#edit_folder_folder').val()).html($('#edit_folder_title').val());
                                $("#edit_folder_title").val($('#edit_folder_title').val());
                                $("#jstree").jstree("refresh");
                                $("#div_editer_rep").dialog("close");
                            } else {
                                $("#edit_rep_show_error").html(data[0].error).show();
                            }
                        },
                        "json"
                   );
                }
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $("#edit_rep_show_error").html("").hide();
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
        width: 300,
        height: 200,
        title: "<?php echo $txt['item_menu_del_rep'];?>",
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                SupprimerFolder();
                $(this).dialog('close');
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $(this).dialog('close');
            }
        }
    });
    //<=
    //=> ADD A NEW ITEM
    $("#div_formulaire_saisi").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 505,
        height: 665,
        title: "<?php echo $txt['item_menu_add_elem'];?>",
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                $("#div_loading").show();
                AjouterItem();
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                //Clear upload queue
                $('#item_file_queue').html('');
                //Select 1st tab
                $("#item_tabs").tabs({ selected: 0 });
                $(this).dialog('close');
            }
        },
        open: function(event,ui) {
            $("#label").focus();

		    // show tab fields ? Not if PersonalFolder
		    if ($("#recherche_group_pf").val() == 1) {
		        if ($("#form_tab_fields") != undefined)
                    $("#item_tabs").tabs( "option", "hidden",3  );
		    } else {
		    	if ($("#form_tab_fields") != undefined && $("#display_categories").val() != 1)
                    $("#item_tabs").tabs( "option", "show",3  );
		    }
        },
        close: function(event,ui) {
            if (CKEDITOR.instances["desc"]) {
                CKEDITOR.instances["desc"].destroy();
            }
            $("#item_upload_list").html("");
            $(".item_field").val("");  // clean values in Fields
            $("#pw1").focus();
            $("#div_loading").hide();
        }
    });
    //<=
    //=> EDITER UN ELEMENT
    $("#div_formulaire_edition_item").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 505,
        height: 650,
        title: "<?php echo $txt['item_menu_edi_elem'];?>",
        buttons: {
            "<?php echo $txt['save_button'];?>": function() {
                EditerItem();
				$("#div_formulaire_edition_item_info").hide().html("");
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                //Clear upload queue
                $('#item_edit_file_queue').html('');
                //Select 1st tab
                $("#item_edit_tabs").tabs({ selected: 0 });
				$("#div_loading").hide();
                //Close dialog box
                $(this).dialog('close');
            }
        },
        close: function(event,ui) {
            if (CKEDITOR.instances["edit_desc"]) {
                CKEDITOR.instances["edit_desc"].destroy();
            }
            $("#div_loading, #edit_show_error").hide();
            $("#item_edit_upload_list").html("");
            $(".edit_item_field").val("");  // clean values in Fields
            //Unlock the Item
            $.post(
                "sources/items.queries.php",
                {
                    type    : "free_item_for_edition",
                    id      : $("#id_item").val(),
                    key        : "<?php echo $_SESSION['key'];?>"
                }
           );
        },
		open: function(event,ui) {
			//refresh pw complexity
			$("#item_edit_tabs").tabs( "option", "active",1  );
			$("#edit_pw1").first().focus();
			$("#item_edit_tabs").tabs( "option", "active",0  );

		    // show tab fields ? Not if PersonalFolder
		    if ($("#recherche_group_pf").val() == 1) {
		        if ($("#edit_item_more") != undefined) $("#edit_item_more").hide();
		    } else {
		    	if ($("#edit_item_more") != undefined && $("#display_categories").val() != 1)
                    $("#edit_item_more").show();
		    }
		}
    });
    //<=
    //=> SUPPRIMER UN ELEMENT
    $("#div_del_item").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 300,
        height: 150,
        title: "<?php echo $txt['item_menu_del_elem'];?>",
        buttons: {
            "<?php echo $txt['del_button'];?>": function() {
                $.post(
                    "sources/items.queries.php",
                    {
                        type    : "del_item",
                        id      : $("#id_item").val(),
                        key        : "<?php echo $_SESSION['key'];?>"
                    },
                    function(data) {
                        window.location.href = "index.php?page=items&group="+$("#hid_cat").val();
                    }
               );
                $(this).dialog('close');
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                $(this).dialog('close');
            }
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
        title: "<?php echo $txt['admin_main'];?>",
        buttons: {
            "<?php echo $txt['close'];?>": function() {
                $(this).dialog('close');
            }
        }
    });
    //<=
    //=> SHOW HISTORY DIALOG
    $("#div_item_history").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 400,
        title: "<?php echo $txt['history'];?>",
        buttons: {
            "<?php echo $txt['close'];?>": function() {
                $(this).dialog('close');
            }
        }
    });
    //<=
    //=> SHOW SHARE DIALOG
    $("#div_item_share").dialog({
        bgiframe: true,
        modal: true,
        autoOpen: false,
        width: 500,
        height: 200,
        title: "<?php echo $txt['share'];?>",
        buttons: {
            "<?php echo $txt['send'];?>": function() {
                $("#div_item_share_error").hide();
                if (IsValidEmail($("#item_share_email").val())) {    //check if email format is ok
                    $("#div_item_share_status").show();
                    $.post(
                        "sources/items.queries.php",
                        {
                            type    : "send_email",
                            id      : $("#id_item").val(),
                            receipt    : $("#item_share_email").val(),
                            cat      : "share_this_item",
                            key        : "<?php echo $_SESSION['key'];?>"
                        },
                        function(data) {
                            $("#div_item_share_status").html("").hide();
                            if (data[0].error == "") {
                                $("#div_item_share_error").html("<?php echo addslashes($txt['share_sent_ok']);?>").show();
                            } else {
                                $("#div_item_share_error").html(data[0].message).show();
                            }
                        },
                        "json"
                   );
                } else {
                    $("#div_item_share_error").html("<?php echo addslashes($txt['bad_email_format']);?>").show();
                }
            },
            "<?php echo $txt['close'];?>": function() {
                $(this).dialog('close');
            }
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
        title: "<?php echo $txt['share'];?>",
        buttons: {
            "<?php echo $txt['ok'];?>": function() {

            }
        }
    });
    //<=

    // => ATTACHMENTS INIT
    var uploader_attachments = new plupload.Uploader({
		runtimes : "gears,html5,flash,silverlight,browserplus",
		browse_button : "item_attach_pickfiles",
		container : "item_upload",
		max_file_size : "<?php
if (strrpos($_SESSION['settings']['upload_maxfilesize'], "mb") === false) {
    echo $_SESSION['settings']['upload_maxfilesize']."mb";
} else {
	echo $_SESSION['settings']['upload_maxfilesize'];
}
?>",
        chunk_size : "1mb",
        dragdrop : true,
		url : "sources/upload/upload.attachments.php",
		flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
		silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
		filters : [
			{title : "Image files", extensions : "<?php echo $_SESSION['settings']['upload_imagesext'];?>"},
			{title : "Package files", extensions : "<?php echo $_SESSION['settings']['upload_pkgext'];?>"},
			{title : "Documents files", extensions : "<?php echo $_SESSION['settings']['upload_docext'];?>"},
			{title : "Other files", extensions : "<?php echo $_SESSION['settings']['upload_otherext'];?>"},
		],<?php
if ($_SESSION['settings']['upload_imageresize_options'] == 1) {
        ?>
		resize : {
			width : <?php echo $_SESSION['settings']['upload_imageresize_width'];?>,
			height : <?php echo $_SESSION['settings']['upload_imageresize_height'];?>,
			quality : <?php echo $_SESSION['settings']['upload_imageresize_quality'];?>
		},
        <?php
}
?>
		init: {
            BeforeUpload: function (up, file) {
                $("#item_upload_wait").show();
                if ($("#random_id").val() == "") {
                	var post_id = CreateRandomString(9,"num_no_0");
                	$("#random_id").val(post_id);
                }
                up.settings.multipart_params = {
                    "PHPSESSID":"<?php echo $_SESSION['user_id'];?>",
                    "itemId":$("#random_id").val(),
                    "type_upload":"item_attachments",
                    "edit_item":false
                };
            },
            UploadComplete: function(up, files) {
                $("#item_upload_wait").hide();
            }
		}
	});

    // Uploader options
	uploader_attachments.bind("UploadProgress", function(up, file) {
		$("#" + file.id + " b").html(file.percent + "%");
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
		uploader_attachments.start();
		e.preventDefault();
	});
	uploader_attachments.init();
	uploader_attachments.bind('FilesAdded', function(up, files) {
		$.each(files, function(i, file) {
			$('#item_upload_list').append(
				'<div id="' + file.id + '">[<a href=\'#\' onclick=\'$(\"#' + file.id + '\").remove();\'>-</a>] ' +
				file.name + ' (' + plupload.formatSize(file.size) + ') <b></b>' +
			'</div>');
		});
		up.refresh(); // Reposition Flash/Silverlight
	});

    // Prepare uplupload object for attachments upload
	var edit_uploader_attachments = new plupload.Uploader({
		runtimes : "gears,html5,flash,silverlight,browserplus",
		browse_button : "item_edit_attach_pickfiles",
		container : "item_edit_upload",
		max_file_size : "<?php
if (strrpos($_SESSION['settings']['upload_maxfilesize'], "mb") === false) {
    echo $_SESSION['settings']['upload_maxfilesize']."mb";
} else {
	echo $_SESSION['settings']['upload_maxfilesize'];
}
?>",
        chunk_size : "1mb",
        dragdrop : true,
		url : "sources/upload/upload.attachments.php",
		flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
		silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
		filters : [
			{title : "Image files", extensions : "<?php echo $_SESSION['settings']['upload_imagesext'];?>"},
			{title : "Package files", extensions : "<?php echo $_SESSION['settings']['upload_pkgext'];?>"},
			{title : "Documents files", extensions : "<?php echo $_SESSION['settings']['upload_docext'];?>"},
			{title : "Other files", extensions : "<?php echo $_SESSION['settings']['upload_otherext'];?>"},
		],<?php
if ($_SESSION['settings']['upload_imageresize_options'] == 1) {
        ?>
		resize : {
			width : <?php echo $_SESSION['settings']['upload_imageresize_width'];?>,
			height : <?php echo $_SESSION['settings']['upload_imageresize_height'];?>,
			quality : <?php echo $_SESSION['settings']['upload_imageresize_quality'];?>
		},<?php
}
?>
		init: {
            BeforeUpload: function (up, file) {
                $("#item_edit_upload_wait").show();
                up.settings.multipart_params = {
                    "PHPSESSID":"<?php echo $_SESSION['user_id'];?>",
                    "itemId":$('#selected_items').val(),
                    "type_upload":"item_attachments",
                    "edit_item":true
                };
            },
            UploadComplete: function(up, files) {
                $("#item_edit_upload_wait").hide();
            }
		}
	});

    // Uploader options
	edit_uploader_attachments.bind("UploadProgress", function(up, file) {
		$("#" + file.id + " b").html(file.percent + "%");
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
	});

	// Load edit uploaded click
	$("#item_edit_attach_uploadfiles").click(function(e) {
		edit_uploader_attachments.start();
		e.preventDefault();
	});
	edit_uploader_attachments.init();
	edit_uploader_attachments.bind('FilesAdded', function(up, files) {
		$.each(files, function(i, file) {
			$('#item_edit_upload_list').append(
				'<div id="' + file.id + '">[<a href=\'#\' onclick=\'$(\"#' + file.id + '\").remove();\'>-</a>] ' +
				file.name + ' (' + plupload.formatSize(file.size) + ') <b></b>' +
			'</div>');
		});
		up.refresh(); // Reposition Flash/Silverlight
	});

	//if(sessionStorage.isConnected){
        //Launch items loading
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
        ListerItems(first_group,'', start);
        //Load item if needed and display items list
        if ($("#open_id").val() != "") {
            AfficherDetailsItem($("#open_id").val());
        }
	//}
    //Password meter for item creation
    $("#pw1").simplePassMeter({
        "requirements": {},
        "container": "#pw_strength",
        "defaultText" : "<?php echo $txt['index_pw_level_txt'];?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $txt['complex_level0'];?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level1'];?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level2'];?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level3'];?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level4'];?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level5'];?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level6'];?>"
            }
        ]
    });
    /*$('#pw1').bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#mypassword_complex").val(score);
        }
    });*/
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
          "defaultText" : "<?php echo $txt['index_pw_level_txt'];?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo $txt['complex_level0'];?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level1'];?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo $txt['complex_level2'];?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level3'];?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo $txt['complex_level4'];?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level5'];?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo $txt['complex_level6'];?>"
            }
        ]
    });
    $('#edit_pw1').bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#edit_mypassword_complex").val(score);
        }
    });

    //Text search watermark
    var tbval = $('#jstree_search').val();
    $('#jstree_search').focus(function() { $(this).val('');});
    $('#jstree_search').blur(function() { $(this).val(tbval);});

    //add date selector
    $(".datepicker").datepicker({
        dateFormat:"<?php echo str_replace(array("Y","M"), array("yy","mm"), $_SESSION['settings']['date_format']);?>",
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
        });
});


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
function proceed_list_update()
{
    if ($("#query_next_start").val() != "end") {
        //Check if nb of items do display > to 0
        if ($("#nb_items_to_display_once").val() > 0) {
            ListerItems($("#hid_cat").val(),'', parseInt($("#query_next_start").val()));
        }
    } else {
        $('ul#full_items_list>li').tsort("",{order:"asc",attr:"name"});
        $("#items_list_loader").hide();
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
        $("#full_items_list").find("a:not(:contains(" + id + "))").parent().hide();
        $("#full_items_list").find("a:contains(" + id + ")").parent().show();
    }
}

function displayHistory()
{
    //Send query
    $.post(
        "sources/items.queries.php",
        {
            type    : "displayHistory",
            id    : $("#id_item").val(),
            salt_key_required   : $('#recherche_group_pf').val(),
            salt_key_set        : $('#personal_sk_set').val(),
            key        : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            //check if format error
            if (data[0].error == "") {
                $("#item_history_log_error").html("").hide();
                $("#add_history_entry_label").val("");
                $("#item_history_log").html(htmlspecialchars_decode(data.historique));
            } else {
                $("#item_history_log_error").html(data[0].error).show();
            }
            $("#div_item_history").dialog("open");
        }
   );
}

function manage_history_entry(type, id)
{
	var data = '{"item_id":"'+$("#id_item").val()+'", "label":"'+sanitizeString($('#add_history_entry_label').val())+'"}';

	//Send query
    $.post(
        "sources/items.queries.php",
        {
            type      : "history_entry_add",
            folder_id           : $('#hid_cat').val(),
            data     : prepareExchangedData(data, "encode"),
            key        : "<?php echo $_SESSION['key'];?>"
        },
        function(data) {
            //check if format error
            data = prepareExchangedData(data, "decode");
            if (data.error == "") {
                $("#item_history_log_error").html("").hide();
                $("#add_history_entry_label").val("");
                $("#item_history_log").append(htmlspecialchars_decode(data.new_line));
            } else {
                $("#item_history_log_error").html(data.error).show();
            }
            $("#div_item_history").dialog("open");
        }
   );
}


function aes_encrypt(text)
{
    return Aes.Ctr.encrypt(text, "<?php echo $_SESSION['key'];?>", 256);
}


function aes_decrypt(text)
{
    return Aes.Ctr.decrypt(text, "<?php echo $_SESSION['key'];?>", 256);
}
</script>
