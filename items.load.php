<?php
/**
 * @file 		items.load.php
 * @author		Nils Laumaillé
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	OpenSource BSD 3-clause (OSI)
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
    var query_in_progress = 0;
    ZeroClipboard.setMoviePath( "<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/libraries/zeroclipboard/ZeroClipboard.swf" );

    public function AddNewNode()
    {
        //Select first child node in tree
        $('#2').click();
        //Add new node to selected node
        simpleTreeCollection.get(0).addNode(1,'A New Node')
    }

    public function EditNode()
    {
        //Select first child node in tree
        $('#2').click();
        //Add new node to selected node
        simpleTreeCollection.get(0).addNode(1,'A New Node')
    }

    public function DeleteNode()
    {
        //Select first child node in tree
        $('#2').click();
        //Add new node to selected node
        simpleTreeCollection.get(0).delNode()
    }

    public function showItemsInTree(type)
    {
        if ( $("#img_funnel").attr('src') == "includes/images/funnel_plus.png" )
            $("#img_funnel").attr('src','includes/images/funnel_minus.png');
        else
            $("#img_funnel").attr('src','includes/images/funnel_plus.png');
    }

    //FUNCTION mask/unmask passwords characters
    public function ShowPassword(pw)
    {
        if ( $('#id_pw').html() == '<img src="includes/images/masked_pw.png">' || $('#id_pw').html() == '<IMG src="includes/images/masked_pw.png">' ) {
            $('#id_pw').text($('#hid_pw').val());
        } else {
            $('#id_pw').html('<img src="includes/images/masked_pw.png" />');
        }
    }

    //Showh the password in new form
    public function ShowPasswords_Form()
    {
        if ($('#pw1').is(":visible")) {
            $('#pw1').hide();
            $('#pw1_txt').show();
        } else {
            $('#pw1').show();
            $('#pw1_txt').hide();
        }
    }
    $('#pw1').change(function(){
        $('#pw1_txt').val($('#pw1').val());
    });
    $('#pw1_txt').change(function(){
        $('#pw1').val($('#pw1_txt').val());
    });

    public function ShowPasswords_EditForm()
    {
        if ($('#edit_pw1').is(":visible")) {
            $('#edit_pw1').hide();
            $('#edit_pw1_txt').show();
        } else {
            $('#edit_pw1').show();
            $('#edit_pw1_txt').hide();
        }
    }
    $('#edit_pw1').change(function(){
        $('#edit_pw1_txt').val($('#edit_pw1').val());
    });
    $('#edit_pw1_txt').change(function(){
        $('#edit_pw1').val($('#edit_pw1_txt').val());
    });

    /**
     * Open a dialogbox
     * @access public
     * @return void
     **/
    public function OpenDialog(id, modal)
    {
        if (modal == "false") {
            $("#"+id).dialog( "option", "modal", false );
        } else {
            $("#"+id).dialog( "option", "modal", true );
        }
        $("#"+id).dialog("open");
    }

//###########
//## FUNCTION : Launch the listing of all items of one category
//###########
function ListerItems(groupe_id, restricted, start)
{
    if (groupe_id != undefined) {
        if(query_in_progress != 0 && query_in_progress != groupe_id) request.abort();	//kill previous query if needed
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

        //Disable menu buttons
        $('#menu_button_edit_item,#menu_button_del_item,#menu_button_add_fav,#menu_button_del_fav,#menu_button_show_pw,#menu_button_copy_pw,#menu_button_copy_login,#menu_button_copy_link,#menu_button_copy_item,#menu_button_notify,#menu_button_history,#menu_button_share').attr('disabled', 'disabled');

        //ajax query
        request = $.post("sources/items.queries.php",
            {
                type 	: "lister_items_groupe",
                id 		: groupe_id,
                restricted : restricted,
                start	: start,
                nb_items_to_display_once : $("#nb_items_to_display_once").val()
            },
            function(data){
                //decrypt data
                data = $.parseJSON(aes_decrypt(data));
                $("#items_path").html(data.arborescence);

                if (data.error == "is_pf_but_no_saltkey") {
                    //warn user about his saltkey
                    $("#item_details_no_personal_saltkey").show();
                    $("#item_details_ok, #item_details_nok").hide();

                    $('#menu_button_add_item').attr('disabled', 'disabled');
                    $("#items_list_loader, #div_loading").hide();
                } elseif (data.error == "not_authorized") {
                    //warn user
                    $("#hid_cat").val("");
                    $("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_history, #menu_button_share").attr('disabled', 'disabled');
                    $("#item_details_nok").show();
                    $("#item_details_ok, #item_details_no_personal_saltkey").hide();
                    $("#items_list_loader").hide();
                } elseif ($("#user_is_read_only").val() == 1 && data.recherche_group_pf == 0) {
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
                    }
                    //disable buttons
                    $("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item").attr('disabled', 'disabled');

                    proceed_list_update();
                } else {
                    $("#recherche_group_pf").val(data.saltkey_is_required);
                    //Display items
                    $("#item_details_no_personal_saltkey, #item_details_nok").hide();
                    $("#item_details_ok, #items_list").show();
                    $("#items_path").html(data.arborescence);
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
                    }

                    //If restriction for role
                    if (restricted == 1) {
                        $("#menu_button_add_item").attr('disabled', 'disabled');
                    } else {
                        $("#menu_button_add_item").removeAttr('disabled');
                    }
                    $("#menu_button_copy_item").attr('disabled', 'disabled');

                    $("#menu_button_copy_item, #menu_button_add_group, #menu_button_edit_group, #menu_button_del_group, #menu_button_add_item, #menu_button_edit_item, #menu_button_del_item").removeAttr("disabled");

                    //If no data then empty
                    if (data.array_items != null) {
                        $(".item_draggable").draggable({
                            handle: '.grippy',
                            cursor: "move",
                            opacity: 0.4,
                            appendTo: 'body',
                            stop: function(event, ui){
                                $( this ).removeClass( "ui-state-highlight" );
                            },
                            start: function(event, ui){
                                $( this ).addClass( "ui-state-highlight" );
                            },
                            helper: function( event ) {
                                return $( "<div class='ui-widget-header'>"+"<?php echo $txt['drag_drop_helper'];?>"+"</div>" );
                            }
                        });
                        $(".folder").droppable({
                            hoverClass: "ui-state-active",
                            drop: function( event, ui ) {
                                ui.draggable.hide();
                                //move item
                                $.post(
                                    "sources/items.queries.php",
                                      {
                                          type 	: "move_item",
                                          item_id : ui.draggable.attr("id"),
                                          folder_id : $( this ).attr("id").substring(4),
                                        key		: "<?php echo $_SESSION['key'];?>"
                                      },
                                    function(data){
                                        //increment / decrement number of items in folders
                                        $("#itcount_"+data[0].from_folder).text(Math.floor($("#itcount_"+data[0].from_folder).text())-1);
                                        $("#itcount_"+data[0].to_folder).text(Math.floor($("#itcount_"+data[0].to_folder).text())+1);
                                    },
                                    "json"
                                );
                            }
                        });
                    }

                    /*for (var i=0; i < data.items.length; i++) {
                        var new_line = '<li name="'+data.items[i]['li_label']+'" class="'+data.items[i]['can_move']+'" id="'+data.items[i]['id']+'" style="margin-left:-30px;">';

                        if(data.items[i]['grippy'] == 1) new_line = new_line + '<img src="includes/images/grippy.png" style="margin-right:5px;cursor:hand;" alt="" class="grippy"  />';
                        else new_line = new_line + '<span style="margin-left:11px;"></span>';

                        new_line = new_line +
                            '<img src="includes/images/flag-'+data.items[i]['flag']+'.png"><img src="includes/images/tag-small-'+data.items[i]['perso']+'.png">'+
                            '&nbsp;<a id="fileclass'+data.items[i]['id']+'" class="file" onclick="AfficherDetailsItem(\''+data.items[i]['id']+'\', \''+data.items[i]['detail_item']+'\', \''+data.items[i]['expired_item']+'\', \''+data.items[i]['restricted_to']+'\', \''+data.items[i]['display']+'\')">'+
                            +data.items[i]['label']+data.items[i]['description']+'</a>'+
                            '</li>';alert(new_line);
                        $("#full_items_list").append(new_line);
                    }*/

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
    if ( elem != "" ) elem = elem+"_";

    //show ajax image
    $("#"+elem+"pw_wait").show();

    $.post(
        "sources/items.queries.php",
        {
            type    : "pw_generate",
            size  	: $("#"+elem+'pw_size').text(),
            num  	: $("#"+elem+'pw_numerics').prop("checked"),
            maj  	: $("#"+elem+'pw_maj').prop("checked"),
            symb  	: $("#"+elem+'pw_symbols').prop("checked"),
            secure  : $("#"+elem+'pw_secure').prop("checked"),
            elem  	: elem,
            force  	: "false"
        },
        function(data){
            data = $.parseJSON(data);
            $("#"+elem+"pw1, #"+elem+"pw1_txt").val(data.key).focus();
            $("#"+elem+"pw_wait").hide();
        }
    );
}

function pwCopy(elem)
{
    if ( elem != "" ) elem = elem+"_";
    $("#"+elem+'pw2').val($("#"+elem+'pw1').val());
}

function catSelected(val)
{
    $("#hid_cat").val(val);
}

function RecupComplexite(val,edit)
{
    $.post(
        "sources/items.queries.php",
        {
            type    : "recup_complex",
            groupe  : val
        },
        function(data){
            data = $.parseJSON(data);
            $("#complexite_groupe").val(data.val);
            if (edit == 1) {
                $("#edit_complex_attendue").html("<b>"+data.complexity+"</b>");
                $("#edit_afficher_visibilite").html("<img src='includes/images/users.png'>&nbsp;<b>"+data.visibility+"</b>");
            } else {
                $("#complex_attendue").html("<b>"+data.complexity+"</b>");
                $("#afficher_visibilite").html("<img src='includes/images/users.png'>&nbsp;<b>"+data.visibility+"</b>");
            }
        }
    );
}

function AjouterItem()
{
    LoadingPage();
    $("#error_detected").val('');   //Refresh error foolowup
    var erreur = "";
    var  reg=new RegExp("[.|;|:|!|=|+|-|*|/|#|\"|'|&|]");

    //Complete url format
    var url = $("#url").val();
    if (url.substring(0,7) != "http://" && url!="" && url.substring(0,8) != "https://" && url.substring(0,6) != "ftp://" && url.substring(0,6) != "ssh://") {
        url = "http://"+url;
    }

    if ( $("#label").val() == "" ) erreur = "<?php echo $txt['error_label'];?>";
    else if ( $("#pw1").val() == "" ) erreur = "<?php echo $txt['error_pw'];?>";
    else if ( $("#categorie").val() == "na" ) erreur = "<?php echo $txt['error_group'];?>";
    else if ( $("#pw1").val() != $("#pw2").val() ) erreur = "<?php echo $txt['error_confirm'];?>";
    else if ( $("#enable_delete_after_consultation").is(':checked') && (($("#times_before_deletion").val() < 1 && $("#deletion_after_date").val() == "") || ($("#times_before_deletion").val() == "" && $("#deletion_after_date").val() == "")) ) erreur = "<?php echo $txt['error_times_before_deletion'];?>";
    else if ( $("#item_tags").val() != "" && reg.test($("#item_tags").val()) ) erreur = "<?php echo $txt['error_tags'];?>";
    else{
        //Check pw complexity level
        if (
            ( $("#bloquer_creation_complexite").val() == 0 && parseInt($("#mypassword_complex").val()) >= parseInt($("#complexite_groupe").val()) )
            ||
            ( $("#bloquer_creation_complexite").val() == 1 )
            ||
            ( $('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1 )
        ){
            var annonce = 0;
            if ( $('#annonce').is(':checked') ) annonce = 1;

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
            if ( restriction != "" && restriction.indexOf($('#form_user_id').val()) == "-1" )
                restriction = $('#form_user_id').val()+";"+restriction
            if ( restriction == ";" ) restriction = "";

            //Manage diffusion list
            var diffusion = "";
            $("#annonce_liste_destinataires option:selected").each(function () {
                diffusion += $(this).val() + ";";
            });
            if ( diffusion == ";" ) diffusion = "";

            //Manage description
            if (CKEDITOR.instances["desc"]) {
                var description = sanitizeString(CKEDITOR.instances["desc"].getData());
            } else {
                var description = sanitizeString($("#desc").val()).replace(/\n/g, '<br />');
            }

            //Is PF
            if ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1) {
                var is_pf = 1;
            } else {
                var is_pf = 0;
            }

            //To be deleted
            if ( $("#enable_delete_after_consultation").is(':checked') && ($("#times_before_deletion").val() >= 1 || $("#deletion_after_date").val() != "") ) {
                if ($("#times_before_deletion").val() >= 1) {
                    var to_be_deleted = $("#times_before_deletion").val();
                } elseif ($("#deletion_after_date").val() != "") {
                    var to_be_deleted = $("#deletion_after_date").val();
                }
            } else {
                var to_be_deleted = "";
            }

            //prepare data
            var data = '{"pw":"'+sanitizeString($('#pw1').val())+'", "label":"'+sanitizeString($('#label').val())+'", '+
            '"login":"'+sanitizeString($('#item_login').val())+'", "is_pf":"'+is_pf+'", '+
            '"description":"'+(description)+'", "email":"'+$('#email').val()+'", "url":"'+url+'", "categorie":"'+$('#categorie').val()+'", '+
            '"restricted_to":"'+restriction+'", "restricted_to_roles":"'+restriction_role+'", "salt_key_set":"'+$('#personal_sk_set').val()+'", "is_pf":"'+$('#recherche_group_pf').val()+
            '", "annonce":"'+annonce+'", "diffusion":"'+diffusion+'", "id":"'+$('#id_item').val()+'", '+
            '"anyone_can_modify":"'+$('#anyone_can_modify:checked').val()+'", "tags":"'+sanitizeString($('#item_tags').val())+
            '", "random_id_from_files":"'+$('#random_id').val()+'", "to_be_deleted":"'+to_be_deleted+'"}';

            //Send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "new_item",
                    data 	: aes_encrypt(data),
                    key		: "<?php echo $_SESSION['key'];?>"
                },
                function(data){
                    //decrypt data
                    data = $.parseJSON(aes_decrypt(data));

                    //Check errors
                    if (data.error == "item_exists") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('<?php echo addslashes($txt['error_item_exists']);?>');
                        $("#new_show_error").show();
                        LoadingPage();
                    } elseif (data.error == "something_wrong") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('ERROR!!');
                        $("#new_show_error").show();
                        LoadingPage();
                    } elseif (data.error == "pw_too_long") {
                        $("#div_formulaire_saisi").dialog("open");
                        $("#new_show_error").html('<?php echo addslashes($txt['error_pw_too_long']);?>');
                        $("#new_show_error").show();
                        LoadingPage();
                    } elseif (data.new_id != "") {
                        $("#new_show_error").hide();
                        $("#random_id").val("");

                        //add new line directly in list of items
                        $("#full_items_list").append(data.new_entry);

                        //Increment counter
                        $("#itcount_"+$("#hid_cat").val()).text(Math.floor($("#itcount_"+$("#hid_cat").val()).text())+1);

                        AfficherDetailsItem(data.new_id);

                        //emty form
                        $("#label, #item_login, #email, #url, #pw1, #pw1_txt, #pw2, #item_tags, #deletion_after_date, #times_before_deletion").val("");
                        CKEDITOR.instances["desc"].setData("");
                        $("#item_file_queue").html("");
                        $("#categorie").val("");
                        //$("#restricted_to_list").multiselect('uncheckall');TODO
                        $("#item_tabs").tabs({selected: 0});
                        $('ul#full_items_list>li').tsort("",{order:"asc",attr:"name"});

                        $("#div_formulaire_saisi").dialog('close');
                    }
                    $("#div_loading").hide();
                }
            );
        } else {
            $('#new_show_error').html("<?php echo addslashes($txt['error_complex_not_enought']);?>").show();
        }
    }
    if (erreur != "") {
        $('#new_show_error').html(erreur).show();
    }
}

function EditerItem()
{
    var erreur = "";
    var  reg=new RegExp("[.|,|;|:|!|=|+|-|*|/|#|\"|'|&]");

    //Complete url format
    var url = $("#edit_url").val();
    if (url.substring(0,7) != "http://" && url!="" && url.substring(0,8) != "https://" && url.substring(0,6) != "ftp://" && url.substring(0,6) != "ssh://") {
        url = "http://"+url;
    }

    if ($('#edit_label').val() == "" ) erreur = "<?php echo $txt['error_label'];?>";
    else if ( $("#edit_pw1").val() == "" ) erreur = "<?php echo $txt['error_pw'];?>";
    else if ( $("#edit_pw1").val() != $("#edit_pw2").val() ) erreur = "<?php echo $txt['error_confirm'];?>";
    else if ( $("#edit_tags").val() != "" && reg.test($("#edit_tags").val()) ) erreur = "<?php echo $txt['error_tags'];?>";
    else{
        //Check pw complexity level
        if ( (
                $("#bloquer_modification_complexite").val() == 0 &&
                parseInt($("#edit_mypassword_complex").val()) >= parseInt($("#complexite_groupe").val())
            )
            ||
            ( $("#bloquer_modification_complexite").val() == 1 )
            ||
            ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)
        ){
            LoadingPage();  //afficher image de chargement
            var annonce = 0;
            if ( $('#edit_annonce').attr('checked') ) annonce = 1;


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
            if ( restriction != "" && restriction.indexOf($('#form_user_id').val()) == "-1" )
                restriction = $('#form_user_id').val()+";"+restriction
            if ( restriction == ";" ) restriction = "";


            //Manage diffusion list
            var myselect = document.getElementById('edit_annonce_liste_destinataires');
            var diffusion = "";
            for (var loop=0; loop < myselect.options.length; loop++) {
                if (myselect.options[loop].selected == true) diffusion = diffusion + myselect.options[loop].value + ";";
            }
            if ( diffusion == ";" ) diffusion = "";

            //Manage description
            if (CKEDITOR.instances["edit_desc"]) {
                var description = sanitizeString(CKEDITOR.instances["edit_desc"].getData());
            } else {
                var description = sanitizeString($("#edit_desc").val());
            }

            //Is PF
            if ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1) {
                var is_pf = 1;
            } else {
                var is_pf = 0;
            }

          //To be deleted
            if ( $("#edit_enable_delete_after_consultation").is(':checked') && ($("#edit_times_before_deletion").val() >= 1 || $("#edit_deletion_after_date").val() != "") ) {
                if ($("#edit_times_before_deletion").val() >= 1) {
                    var to_be_deleted = $("#edit_times_before_deletion").val();
                    //var to_be_deleted_after_date = "";
                } elseif ($("#edit_deletion_after_date").val() != "") {
                    //var to_be_deleted = "0";
                    var to_be_deleted = $("#edit_deletion_after_date").val();
                }
            } else {
                var to_be_deleted = "";
                //var to_be_deleted_after_date = "";
            }

              //prepare data
            var data = '{"pw":"'+sanitizeString($('#edit_pw1').val())+'", "label":"'+sanitizeString($('#edit_label').val())+'", '+
            '"login":"'+sanitizeString($('#edit_item_login').val())+'", "is_pf":"'+is_pf+'", '+
            '"description":"'+description+'", "email":"'+$('#edit_email').val()+'", "url":"'+url+'", "categorie":"'+$('#edit_categorie').val()+'", '+
            '"restricted_to":"'+restriction+'", "restricted_to_roles":"'+restriction_role+'", "salt_key_set":"'+$('#personal_sk_set').val()+'", "is_pf":"'+$('#recherche_group_pf').val()+'", '+
            '"annonce":"'+annonce+'", "diffusion":"'+diffusion+'", "id":"'+$('#id_item').val()+'", '+
            '"anyone_can_modify":"'+$('#edit_anyone_can_modify:checked').val()+'", "tags":"'+sanitizeString($('#edit_tags').val())+'" ,'+
            '"to_be_deleted":"'+to_be_deleted+'"}';

            //send query
            $.post(
                "sources/items.queries.php",
                {
                    type    : "update_item",
                    data      : aes_encrypt(data),
                    key		: "<?php echo $_SESSION['key'];?>"
                },
                function(data){
                    //decrypt data
                    data = $.parseJSON(aes_decrypt(data));

                    //check if format error
                    if (data.error == "format") {
                        $("#div_loading").hide();
                        $("#edit_show_error").html(data.error+' ERROR (JSON is broken)!!!!!');
                        $("#edit_show_error").show();
                    } elseif (data.error == "pw_too_long") {
                        $("#div_loading").hide();
                        $("#edit_show_error").html('<?php echo addslashes($txt['error_pw_too_long']);?>');
                        $("#edit_show_error").show();
                        LoadingPage();
                    } elseif (data.error != "") {
                        $("#div_loading").hide();
                        $("#edit_show_error").html('<?php echo addslashes($txt['error_not_allowed_to']);?>');
                        $("#edit_show_error").show();
                        LoadingPage();
                    }
                    //if reload page is needed
                    else if (data.reload_page == "1") {
                        window.location.href = "index.php?page=items&group="+data.id_tree+"&id="+data.id;
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

                        //calling image lightbox when clicking on link
                        $("a.image_dialog").click(function(event){
                            event.preventDefault();
                            PreviewImage($(this).attr("href"),$(this).attr("title"));
                        });

                        //Change title in "last items list"
                        $("#last_items_"+data.id).text($('#edit_label').val());

                        //Clear upload queue
                        $('#item_edit_file_queue').html('');
                        //Select 1st tab
                        $( "#item_edit_tabs" ).tabs({ selected: 0 });
                        //Close dialogbox
                        $("#div_formulaire_edition_item").dialog('close');

                        //hide loader
                        $("#div_loading").hide();
                    }
                }
            );


        } else {
            $('#edit_show_error').html("<?php echo addslashes($txt['error_complex_not_enought']);?>").show();
        }
    }

    if (erreur != "") {
        $('#edit_show_error').html(erreur).show();
    }
}

function aes_encrypt(text)
{
    return Aes.Ctr.encrypt(text, "<?php echo $_SESSION['key'];?>", 256);
}

function aes_decrypt(text)
{
    return Aes.Ctr.decrypt(text, "<?php echo $_SESSION['key'];?>", 256);
}

function AjouterFolder()
{
    if ( $("#new_rep_titre").val() == "0" ) $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_label']);?>").show();
    else if ( $("#new_rep_complexite").val() == "" ) $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_complex']);?>").show();
    else{
        if ($("#new_rep_role").val() == undefined) {
            role_id = "<?php echo $_SESSION['fonction_id'];?>";
        } else {
            role_id = $("#new_rep_role").val();
        }

        //prepare data
        var data = '{"title":"'+sanitizeString($('#new_rep_titre').val())+'", "complexity":"'+sanitizeString($('#new_rep_complexite').val())+'", '+
        '"parent_id":"'+sanitizeString($('#new_rep_groupe').val())+'", "renewal_period":"0"}';

        //send query
        $.post(
            "sources/folders.queries.php",
            {
                type    : "add_folder",
                data  	: aes_encrypt(data),
                key		: "<?php echo $_SESSION['key'];?>"
            },
            function(data){
                //Check errors
                if (data[0].error == "error_group_exist") {
                    $("#new_rep_show_error").html("<?php echo addslashes($txt['error_group_exist']);?>").show();
                } elseif (data[0].error == "error_html_codes") {
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
    if ( $("#delete_rep_groupe").val() == "0" ) alert("<?php echo $txt['error_group'];?>");
    else if ( confirm("<?php echo $txt['confirm_delete_group'];?>") ) {
        $.post(
            "sources/folders.queries.php",
            {
                type    : "delete_folder",
                id      : $("#delete_rep_groupe").val(),
                key		: "<?php echo $_SESSION['key'];?>"
            },
            function(data){
                window.location.href = "index.php?page=items";
            }
        );
    }
}

function AfficherDetailsItem(id, salt_key_required, expired_item, restricted, display, open_edit)
{
    if (display == "no_display") {
        //Dont show details
        $("#item_details_nok").show();
        $("#item_details_ok").hide();
        $("#item_details_expired").hide();
        $("#item_details_expired_full").hide();
        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");

        return false;
    }
    LoadingPage();  //afficher image de chargement
    if ( $("#is_admin").val() == "1" ) {
        $('#menu_button_edit_item,#menu_button_del_item,#menu_button_copy_item').attr('disabled', 'disabled');
    }

    if($("#edit_restricted_to") != undefined) $("#edit_restricted_to").val("");

    //Check if personal SK is needed and set
    if ( ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 0) && salt_key_required == 1 ) {
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($txt['alert_message_personal_sk_missing']);?><\/div>");
        LoadingPage();
        $("#div_dialog_message").dialog("open");
    } elseif ($('#recherche_group_pf').val() == 0 || ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)) {
        $.post(
            "sources/items.queries.php",
            {
                type    			: 'show_details_item',
                id      			: id,
                folder_id 			: $('#hid_cat').val(),
                salt_key_required   : $('#recherche_group_pf').val(),
                salt_key_set        : $('#personal_sk_set').val(),
                expired_item        : expired_item,
                restricted        	: restricted,
                key		: "<?php echo $_SESSION['key'];?>"
            },
            function(data){
                //decrypt data
                data = $.parseJSON(aes_decrypt(data));

                //Change the class of this selected item
                if ( $("#selected_items").val() != "") {
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
                } elseif (data.show_detail_option == "2") {
                    $("#item_details_ok, #item_details_expired, #item_details_expired_full").hide();
                }
                $("#item_details_nok").hide();
                $("#fileclass"+data.id).addClass("fileselected");

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
                    $("#item_history_log").html(htmlspecialchars_decode(data.historique));
                    $("#id_restricted_to").html(data.id_restricted_to+data.id_restricted_to_roles);
                    $("#hid_restricted_to").val(data.id_restricted_to);
                    $("#hid_restricted_to_roles").val(data.id_restricted_to_roles);
                    $("#id_tags").html(data.tags).html();
                    $("#hid_tags").val($("#id_tags").html());
                    $("#hid_anyone_can_modify").val(data.anyone_can_modify);
                    $("#id_categorie").val(data.folder);
                    $("#id_item").val(data.id);
                    $("#id_files").html(data.files_id);
                    $("#hid_files").val(data.files_id);
                    $("#item_edit_list_files").html(data.files_edit).html();
                    $("#div_last_items").html(htmlspecialchars_decode(data.div_last_items));
                    $("#id_kbs").html(data.links_to_kbs);
                    $("#edit_past_pwds").attr('title', data.history_of_pwds);
                    $(".tip").tooltip({
                        delay: 0,
                        showURL: false
                    });

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
                    } elseif (data.user_can_modify == 0) {
                        $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                    } elseif (data.restricted == "1" || data.user_can_modify == "1") {
                        $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item").removeAttr("disabled");
                        $("#new_history_entry_form").show();
                    } else {
                        $("#menu_button_add_item, #menu_button_copy_item").removeAttr("disabled");
                        $("#new_history_entry_form").show();
                    }
                    $("#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history, #menu_button_share").removeAttr("disabled");

                    //Manage to deleted information
                    if (data.to_be_deleted != 0 && data.to_be_deleted != null && data.to_be_deleted != "not_enabled") {
                        $('#item_extra_info').html("<i><img src=\'<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/information-white.png\'> <?php echo addslashes($txt['automatic_deletion_activated']);?></i>");
                    } else {
                        $('#item_extra_info').html("");
                    }

                    if (data.notification_status == 0 && data.id_user == <?php echo $_SESSION['user_id'];?>) {
                        $('#menu_button_notify')
                            .removeAttr("disabled")
                            .attr('title','<?php echo $txt['enable_notify'];?>')
                            .attr('onclick','notify_click(\'true\')');
                        $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock-plus.png');
                    } elseif (data.notification_status == 1 && data.id_user == <?php echo $_SESSION['user_id'];?>) {
                        $('#menu_button_notify')
                            .removeAttr("disabled")
                            .attr('title','<?php echo $txt['disable_notify'];?>')
                            .attr('onclick','notify_click(\'false\')');
                        $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock-minus.png');
                        $('#item_extra_info').html("<i><img src=\'<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock.png\'> <?php echo addslashes($txt['notify_activated']);?></i>");
                    } else {
                        $('#menu_button_notify').attr('disabled', 'disabled');
                        $('#div_notify').attr('src', '<?php echo $_SESSION['settings']['cpassman_url'];?>/includes/images/alarm-clock.png');
                    }

                    //Prepare clipboard copies
                    if (data.pw != "") {
                        var clip = new ZeroClipboard.Client();
                        clip.setText(unsanitizeString(data.pw));
                        clip.addEventListener( 'complete', function(client, text) {
                                $("#message_box").html("<?php echo addslashes($txt['pw_copied_clipboard']);?>").show().fadeOut(1000);
                        } );
                        clip.glue('menu_button_copy_pw');
                    }
                    if (data.login != "") {
                        var clip = new ZeroClipboard.Client();
                        clip.setText(data.login);
                        clip.addEventListener( 'complete', function(client, text) {
                                $("#message_box").html("<?php echo addslashes($txt['login_copied_clipboard']);?>").show().fadeOut(1000);
                        } );
                        clip.glue('menu_button_copy_login');
                    }
                    //prepare link to clipboard
                    var clip = new ZeroClipboard.Client();
                    clip.setText("<?php echo $_SESSION['settings']['cpassman_url'];?>/index.php?page=items&group="+data.folder+"&id="+data.id);
                    clip.addEventListener( 'complete', function(client, text) {
                            $("#message_box").html("<?php echo addslashes($txt['url_copied']);?>").show().fadeOut(1000);
                    } );
                    clip.glue('menu_button_copy_link');

                    // function calling image lightbox when clicking on link
                    $("a.image_dialog").click(function(event){
                        event.preventDefault();
                        PreviewImage($(this).attr("href"),$(this).attr("title"));
                    });

                    //Set favourites icon
                    if (data.favourite == "1") {
                        $("#menu_button_add_fav").attr("disabled","disabled");
                        $("#menu_button_del_fav").removeAttr("disabled");
                    } else {
                        $("#menu_button_add_fav").removeAttr("disabled");
                        $("#menu_button_del_fav").attr("disabled","disabled");
                    }

                    //Manage double click
                    if (open_edit == true && (data.restricted == "1" || data.user_can_modify == "1")) {
                        open_edit_item_div(<?php if(isset($_SESSION['settings']['restricted_to_roles']) && $_SESSION['settings']['restricted_to_roles'] == 1) echo 1; else echo 0;?>);
                    }
                } elseif (data.show_details == "1" && data.show_detail_option == "2") {
                    $("#item_details_nok").hide();
                    $("#item_details_ok").hide();
                    $("#item_details_expired_full").show();
                    $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");
                } else {
                    //Dont show details
                    $("#item_details_nok").show();
                    $("#item_details_nok_restriction_list").html('<div style="margin:10px 0 0 20px;"><b><?php echo $txt['author'];?>: </b>'+data.author+'<br><b><?php echo $txt['restricted_to'];?>: </b>'+data.restricted_to+'<br><br><u><a href="#" onclick="SendMail(\'request_access_to_author\',\''+data.id+','+data.id_user+'\')"><?php echo addslashes($txt['request_access_ot_item']);?></a></u></div>');
                    $("#item_details_ok").hide();
                    $("#item_details_expired").hide();
                    $("#item_details_expired_full").hide();
                    $("#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link").attr("disabled","disabled");
                }
                $("#div_loading").hide();
            }
        );
    }
}

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
    } elseif (action == 0) {
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
    //Select the actual forlder in the dialogbox
    $('#new_rep_groupe').val($('#hid_cat').val());
    $('#div_ajout_rep').dialog('open');
}

//###########
//## FUNCTION : prepare editing folder dialogbox
//###########
function open_edit_group_div()
{
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
    if ( $('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 0 ) {
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($txt['alert_message_personal_sk_missing']);?><\/div>");
        LoadingPage();
        $("#div_dialog_message").dialog("open");
    } elseif ($("#hid_cat").val() == "") {
        LoadingPage();
        $("#div_dialog_message_text").html("<div style='font-size:16px;'><span class='ui-icon ui-icon-alert' style='float: left; margin-right: .3em;'><\/span><?php echo addslashes($txt['error_no_selected_folder']);?><\/div>").dialog("open");
    } elseif ($('#recherche_group_pf').val() == 0 || ($('#recherche_group_pf').val() == 1 && $('#personal_sk_set').val() == 1)) {
        //Select the actual forlder in the dialogbox
        $('#categorie').val($('#hid_cat').val());

        //Get the associated complexity level
        RecupComplexite($('#hid_cat').val(),0);

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

        //open dialog
        $("#div_formulaire_saisi").dialog("open");
    }
}

//###########
//## FUNCTION : prepare editing item dialogbox
//###########
function open_edit_item_div(restricted_to_roles)
{
    LoadingPage();

    //Show WYGIWYS editor
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
    $('#edit_pw1, #edit_pw1_txt, #edit_pw2').val($('#hid_pw').val());
    $('#edit_item_login').val($('#hid_login').val());
    $('#edit_email').val($('#hid_email').val());
    $('#edit_url').val($('#hid_url').val());
    $('#edit_categorie').val($('#id_categorie').val());
    if($('#edit_restricted_to').val() != undefined) $('#edit_restricted_to').val($('#hid_restricted_to').val());
    if($('#edit_restricted_to_roles').val() != undefined) $('#edit_restricted_to_roles').val($('#hid_restricted_to_roles').val());
    $('#edit_tags').val($('#hid_tags').val());
    if ($('#id_anyone_can_modify:checked').val() == "on") {
        $('#edit_anyone_can_modify').attr("checked","checked");
        $('#edit_anyone_can_modify').button("refresh");
    } else {
        $('#edit_anyone_can_modify').attr("checked",false);
        $('#edit_anyone_can_modify').button("refresh");
    }

    //Get complexity level for this folder
    RecupComplexite($('#hid_cat').val(),1);

    //Get list of people in restriction list
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
                if(i==0) $("#edit_restricted_to_list option:last").wrapAll(optgroup);
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

    //refresh pw complecity
    $("#edit_pw1").focus();

    //open dialog
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
                        key		: "<?php echo $_SESSION['key'];?>"
                    },
                    function(data){
                        //check if format error
                        if (data[0].error == "no_item") {
                            $("#copy_item_to_folder_show_error").html(data[1].error_text).show();
                        } elseif (data[0].error == "not_allowed") {
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
        function(data){
            data = $.parseJSON(data);
            $("#edit_desc").val(data.description);
        }
    );
}

//###########
//## FUNCTION : Permits to start uploading files in EDIT ITEM mode
//###########
function upload_attached_files_edit_mode()
{
    // Pass dynamic ITEM id
    var post_id = $('#selected_items').val();
    var user_id = $('#form_user_id').val();

    $('#item_edit_files_upload').uploadifySettings('scriptData', {'post_id':post_id, 'user_id':user_id, 'type':'modification', 'timezone':'<?php echo $_SESSION['settings']['timezone'];?>', 'session_id':'nils'});

    // Launch upload
    $("#item_edit_files_upload").uploadifyUpload();
}

//###########
//## FUNCTION : Permits to start uploading files in NEW ITEM mode
//###########
function upload_attached_files()
{
    // Pass dynamic ITEM id
    var post_id  = "";
    var user_id = $('#form_user_id').val();

    //generate fake id if needed
    if ($("#random_id").val() == "") {
        var post_id = CreateRandomString(9,"num_no_0");
        //Save fake id
        $("#random_id").val(post_id);
    } else {
        var post_id = $("#random_id").val();
    }

    $('#item_files_upload').uploadifySettings(
        'scriptData',
        {
            'post_id':post_id,
            'user_id':user_id,
            'type':'creation',
            'timezone':'<?php echo $_SESSION['settings']['timezone'];?>'
        }
    );

    // Launch upload
    $("#item_files_upload").uploadifyUpload();
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
        function(data){
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
    imageTag.load(function(){
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
        $.post("sources/items.queries.php",
            {
                type 	: "get_clipboard_item",
                field	: field,
                id 		: id
            },
            function(data){
                data = aes_decrypt(data);
                clip = new ZeroClipboard.Client();
                clip.setText( data );
                if (field == "pw") {
                    clip.addEventListener( 'complete', function(client, text) {
                        $("#message_box").html("<?php echo addslashes($txt['pw_copied_clipboard']);?>").show().fadeOut(1000);
                        clip.destroy();
                    } );
                    clip.glue( 'iconpw_'+id );
                } else {
                    clip.addEventListener( 'complete', function(client, text) {
                        $("#message_box").html("<?php echo addslashes($txt['login_copied_clipboard']);?>").show().fadeOut(1000);
                        clip.destroy();
                    } );
                    clip.glue( 'iconlogin_'+id );
                }

                var div = $('#items_list');
                var box = ZeroClipboard.getDOMObjectPosition(clip.domElement);
                clip.div.style.top = '' + (box.top - $(div).scrollTop()) + 'px';
            }
           );
   };

function notify_click(status)
{
    $.post("sources/items.queries.php",
            {
                type 	: "notify_a_user",
                user_id : <?php echo $_SESSION['user_id'];?>,
                status	: status,
                notify_type : 'on_show',
                notify_role : '',
                item_id : $('#id_item').val(),
                key		: "<?php echo $_SESSION['key'];?>"
            },
            function(data){
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
                    } elseif (data[0].new_status == "false") {
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
$(function() {$('#toppathwrap').hide();
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
    $("#div_items, #content").height(window_height-150);
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

    //autocomplete for TAGS
    $("#item_tags, #edit_tags").focus().autocomplete('sources/items.queries.php?type=autocomplete_tags', {
        width: 300,
        multiple: true,
        matchContains: false,
        multipleSeparator: " "
    });

    //Build tree - "cookies",
    $("#jstree").jstree({
        "plugins" : ["themes", "html_data", "ui", "search", "cookies"]
    })
    //search in tree
    .bind("search.jstree", function (e, data) {
        if (data.rslt.nodes.length == 1) {
            //open the folder
            ListerItems($("#jstree li>a.jstree-search").attr('id').split('_')[1], '', 0);
        }
    });

    /*$("#jstree").dynatree({
        persist: true,
        selectMode: 1,
          onExpand: function(node, event) {
            return false;
          },
        onFocus: function(node, event) {
            if (node.data.title.indexOf('<span',0) != -1) {
                var fld_id = node.data.key.split('_');
                ListerItems(fld_id[1], '', 0);
            }
          }
    });
    */

    $("#add_folder").click(function() {
        var posit = $('#item_selected').val();
        alert($("ul").text());
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
                AjouterFolder();
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
                } elseif ($("#edit_folder_folder").val() == "0") {
                    $("#edit_rep_show_error").html("<?php echo addslashes($txt['error_group']);?>");
                    $("#edit_rep_show_error").show();
                } elseif ($("#edit_folder_complexity").val() == "") {
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
                            data      : aes_encrypt(data),
                            key		: "<?php echo $_SESSION['key'];?>"
                        },
                        function(data){
                            //check if format error
                            if (data[0].error == "") {
                                $("#folder_name_"+$('#edit_folder_folder').val()).text($('#edit_folder_title').val());
                                $("#path_elem_"+$('#edit_folder_folder').val()).text($('#edit_folder_title').val());
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
                $( "#item_tabs" ).tabs({ selected: 0 });
                $(this).dialog('close');
            }
        },
        close: function(event,ui) {
            if (CKEDITOR.instances["desc"]) {
                CKEDITOR.instances["desc"].destroy();
            }
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
            },
            "<?php echo $txt['cancel_button'];?>": function() {
                //Clear upload queue
                $('#item_edit_file_queue').html('');
                //Select 1st tab
                $( "#item_edit_tabs" ).tabs({ selected: 0 });
                //Close dialog box
                $(this).dialog('close');
            }
        },
        close: function(event,ui) {
            if (CKEDITOR.instances["edit_desc"]) {
                CKEDITOR.instances["edit_desc"].destroy();
            }
            $("#div_loading, #edit_show_error").hide();
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
                        id  	: $("#id_item").val(),
                        key		: "<?php echo $_SESSION['key'];?>"
                    },
                    function(data){
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
                if (IsValidEmail($("#item_share_email").val())) {	//check if email format is ok
                    $("#div_item_share_status").show();
                    $.post(
                        "sources/items.queries.php",
                        {
                            type    : "send_email",
                            id  	: $("#id_item").val(),
                            receipt	: $("#item_share_email").val(),
                            cat  	: "share_this_item",
                            key		: "<?php echo $_SESSION['key'];?>"
                        },
                        function(data){
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


    //CALL TO UPLOADIFY FOR FILES UPLOAD in EDIT ITEM
    $("#item_edit_files_upload").uploadify({
        "uploader"  : "includes/libraries/uploadify/uploadify.swf",
        "script"    : "includes/libraries/uploadify/uploadify.php",
        "cancelImg" : "includes/libraries/uploadify/cancel.png",
        "scriptData": {"timezone":"<?php echo $_SESSION['settings']['timezone'];?>", "PHPSESSID":"<?php echo session_id();?>"},
        "auto"      : false,
        "multi"     : true,
        "folder"    : "<?php echo $_SESSION['settings']['path_to_upload_folder'];?>",
        "sizeLimit" : 16777216,
        "queueID"   : "item_edit_file_queue",
        "onComplete": function(event, queueID, fileObj, reponse, data){if (reponse=="ok") {$("#edit_show_error").html(reponse).hide();$("#item_edit_list_files").append(fileObj.name+"<br />");} else {$("#edit_show_error").html(reponse).show();}},
        "buttonText": "<?php echo $txt['upload_button_text'];?>"
    });

    //CALL TO UPLOADIFY FOR FILES UPLOAD in NEW ITEM
    $("#item_files_upload").uploadify({
        "uploader"  : "includes/libraries/uploadify/uploadify.swf",
        "script"    : "includes/libraries/uploadify/uploadify.php?PHPSESSID='<?php $_SESSION['user_id'];?>'",
        "cancelImg" : "includes/libraries/uploadify/cancel.png",
        "auto"      : false,
        "multi"     : true,
        "folder"    : "<?php echo $_SESSION['settings']['path_to_upload_folder'];?>",
        "sizeLimit" : 16777216,
        "queueID"   : "item_file_queue",
        "onComplete": function(event, queueID, fileObj, reponse, data){$("#item_files_upload").append(fileObj.name+"<br />");},
        "buttonText": "<?php echo $txt['upload_button_text'];?>"
    });


    //Launch items loading
    var first_group = <?php if(!empty($first_group)) echo $first_group;else echo "1";?>;
    if ($("#hid_cat").val() != "") {
        first_group = $("#hid_cat").val();
    }

    //load items
    if(parseInt($("#query_next_start").val()) > 0) start = parseInt($("#query_next_start").val());
    else start = 0;
    ListerItems(first_group,'', start);
    //Load item if needed and display items list
    if ($("#open_id").val() != "") {
        AfficherDetailsItem($("#open_id").val());
    }

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
    $('#pw1').bind({
        "score.simplePassMeter" : function(jQEvent, score) {
            $("#mypassword_complex").val(score);
        }
    });

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
    $( ".datepicker" ).datepicker({
        dateFormat:"<?php echo str_replace(array("Y","M"), array("yy","mm"), $_SESSION['settings']['date_format']);?>",
        changeMonth: true,
        changeYear: true
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
                    noquotes = true;            } elseif (OPTS[quote_style[i]]) {
                    optTemp = optTemp | OPTS[quote_style[i]];
                }
            }        quote_style = optTemp;
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


/*
* Forces load Items when user reaches end of scrollbar
*/
/*$('#items_list').scroll(function() {
    if ($("#query_next_start").val() != "end" && $('#items_list').scrollTop() + $('#items_list').outerHeight() == $('#items_list')[0].scrollHeight) {
        ListerItems($("#hid_cat").val(),'', parseInt($("#query_next_start").val())+1);
    }
});*/

function proceed_list_update()
{
    if ($("#query_next_start").val() != "end") {
        ListerItems($("#hid_cat").val(),'', parseInt($("#query_next_start").val()));
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
        //$("#full_items_list").find("a:not(:contains(" + id + "))").parent().hide();
        //$("#full_items_list").find("a:contains(" + id + ")").parent().show();

        /*$("#full_items_list").filter(function (index) {
                  return index == 1 || $(this).attr("name") == "999";alert('ici');
                }).show();*/
        $("#full_items_list").find("a:not(:contains(" + id + "))").parent().hide();
        $("#full_items_list").find("a:contains(" + id + ")").parent().show();
    }
}

function manage_history_entry(action, entry_id)
{
    if (action == "add_entry" && $("#add_history_entry_label").val() != "") {
        //prepare
        var data = '{"label":"'+sanitizeString($('#add_history_entry_label').val())+'", "item_id":"'+$('#id_item').val()+'"}';

        //Send query
        $.post(
            "sources/items.queries.php",
            {
                type    : "history_entry_add",
                data	: aes_encrypt(data),
                key		: "<?php echo $_SESSION['key'];?>"
            },
            function(data){
                //check if format error
                if (data[0].error == "") {
                    $("#item_history_log_error").html("").hide();
                    $("#add_history_entry_label").val("");
                    $("#item_history_log").append(data[0].new_line);
                } else {
                    $("#item_history_log_error").html(data[0].error).show();
                }
            },
            "json"
        );
    }
}
</script>
