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
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], curPage())) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

$var['hidden_asterisk'] = '<i class="fa fa-eye fa-border fa-sm infotip" title="'.langHdl('show_password').'"></i>&nbsp;&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>&nbsp;<i class="fa fa-asterisk"></i>';


?>


<script type="text/javascript">


var requestRunning = false,
    clipboard,
    query_in_progress = 0,
    screenHeight = $(window).height(),
    quick_icon_query_status = true,
    start = 0,
    first_group = 1,
    userScrollPosition = 0;

// Launch items loading
if ($('#jstree_group_selected').val() == '') {
    first_group = 1;
} else {
    first_group = $('#jstree_group_selected').val();
}

if ($('#hid_cat').val() != '') {
    first_group = $('#hid_cat').val();
}

//load items
if (parseInt($('#query_next_start').val()) > 0) start = parseInt($('#query_next_start').val());
else start = 0;

// load list of items
if (first_group !== '') {
    ListerItems(first_group, '' , start);
}

// Build tree
$('#jstree').jstree({
    'core' : {
        'animation' : 0,
        'check_callback' : true,
        'data' : {
            'url' : './sources/tree.php',
            'dataType' : 'json',
            'async' : true,
            'data' : function (node) {
                return { 'id' : node.id.split('_')[1] };
            }
        },
        'strings' : {
            'Loading ...' : '<?php echo langHdl('loading'); ?>...'
        },
        'error' : {

        }
    },
    'plugins' : [
        'state', 'search'
    ]
})
//search in tree
.bind('search.jstree', function (e, data) {
    if (data.nodes.length == 1) {
        //open the folder
        ListerItems($('#jstree li>a.jstree-search').attr('id').split('_')[1], '', 0);
    }
});

// Find folders in jstree
$('#jstree_search').keypress(function(e) {
    if (e.keyCode == 13) {
        $('#jstree').jstree('search',$('#jstree_search').val());
    }
});

var tbval = $('#jstree_search').val();
$('#jstree_search').focus(function() { $(this).val('');});
$('#jstree_search').blur(function() { $(this).val(tbval);});

// load list of visible folders for current user
$(this).delay(500).queue(function() {
    refreshVisibleFolders();
    $(this).dequeue();
});


// Ensure correct height of folders tree
$('#jstree').height(screenHeight - 200);


// Manage folders action
$('.tp-action').click(function() {
    if ($(this).data('folder-action') === 'refresh') {
        refreshTree();        
    } else if ($(this).data('folder-action') === 'expand') {
        $('#jstree').jstree('open_all');
    } else if ($(this).data('folder-action') === 'collapse') {
        $('#jstree').jstree('close_all');
    }

});

// Quit item details card back to items list
$('.but-back-to-list').click(function() {
    $('.items-list-card, #folders-tree-card').removeClass('hidden');
    $('.item-details-card').addClass('hidden');
    // Restore scroll position
    $(window).scrollTop(userScrollPosition);
})



//Evaluate number of items to display - depends on screen height
if (parseInt($('#nb_items_to_display_once').val()) === true
    || $('#nb_items_to_display_once').val() === 'max'
) {
    //do nothing ... good value
} else {
    //adapt to the screen height
    $('#nb_items_to_display_once')
        .val(Math.max(Math.round(($(window).height()-450)/23),2));
}


 
/**
 * Click on item
 */
$(document).on('click', '.teampass-item', function() {
    // Store scroll position
    userScrollPosition = $(window).scrollTop();
    
    $('#items-list-card, #folders-tree-card').addClass('hidden');
    
    // Prepare card
    $('#card-item-label').html($(this).html());
    ShowItemDetails($(this).closest('tr'));

    $('.item-details-card').removeClass('hidden');

    // Scroll to top
    $(window).scrollTop(0);
});


/**
 * Make the item favourite by clicking on icon
 */
$(document).on('click', '.item-favourite', function() {
    if (quick_icon_query_status === true) {
        quick_icon_query_status = false;
        
        //change quick icon
        if ($(this).data('item-favourited') === 0) {
            $('#quick_icon_fav_' + $(this).data('item-id'))
                .html('<i class="fa fa-sm fa-star text-warning"></i>')
                .data('data-favourited', 1);
        } else {
            $('#quick_icon_fav_' + $(this).data('item-id'))
                .html('<i class="fa fa-sm fa-star-o"></i>')
                .data('data-favourited', 0);
        }


        //Send query
        alertify
            .message('<?php echo langHdl('success'); ?>', 0);

        $.post('sources/items.queries.php',
            {
                type    : 'action_on_quick_icon',
                id      : $(this).data('item-id'),
                action  : $(this).data('data-favourited')
            },
            function(data) {
                alertify
                    .success('<?php echo langHdl('success'); ?>', 1)
                    .dismissOthers();
                quick_icon_query_status = true;
            }
       );
    }
});

/**
 * Launch the items search
 */
$('#find_items').bind('keypress', function(e) {
    var code = e.keyCode || e.which;
    if(code == 13) {
        searchItems($(this).val());
    }
});

$('#find_items_button').click(function() {
    if ($('#find_items').val() !== '') {
        searchItems($('#find_items').val());
    }
});

/**
 * Start items search
 */
function searchItems(criteria)
{
    if (criteria !== '') {
        // stop items loading (if on-going)
        $('#items_listing_should_stop').val('1');

        // wait
        alertify
            .message('<?php echo langHdl('searching'); ?>', 0);

        // clean
        $('#id_label, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to ,#id_tags, #id_kbs, .fields_div, .fields, #item_extra_info').html('');
        $('#button_quick_login_copy, #button_quick_pw_copy').addClass('hidden');
        $('#teampass_items_list').html('');
        $('#selected_items').val('');

        // send query
        $.get(
            'sources/find.queries.php',
            {
                type        : 'search_for_items',
                sSearch     : criteria,
                key         : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                var pwd_error = '',
                    icon_login,
                    icon_pwd,
                    icon_favorite;

                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                
                $('#items_folder_path').html('<button class="btn btn-info btn-sm"><i class="fa fa-filter"></i>&nbsp;' + data.message + '</button>');
                
                // Build HTML list
                $.each(data.html_json, function(i, value) {

                    // Prepare error message
                    if (value.pw_status === 'encryption_error') {
                        pwd_error = '<i class="fa fa-warning fa-sm text-danger infotip" title="<?php echo langHdl('pw_encryption_error'); ?>"></i>&nbsp;';
                    }

                    // Prepare mini icons
                    if (value.copy_to_clipboard_small_icons === '1' && value.display === '') {
                        // Login icon
                        if (value.login !== '') {
                            icon_login = '<i class="fa fa-sm fa-user mini_login pointer infotip" data-clipboard-text="'+sanitizeString(value.login)+'" title="<?php echo langHdl('item_menu_copy_login'); ?>" id="minilogin_'+value.item_id+'"></i>&nbsp;';
                        }
                        // Pwd icon
                        if (value.pw !== '') {
                            icon_pwd = '<i class="fa fa-sm fa-lock mini_pw pointer infotip" data-clipboard-text="'+sanitizeString(value.pw)+'" title="<?php echo langHdl('item_menu_copy_pw'); ?>" data-clipboard-id="'+value.item_id+'" id="minipwd_'+value.item_id+'"></i>&nbsp;';
                        }

                        // Now check if pwd is empty. If it is then warn user
                        if (value.pw === '') {
                            pwd_error = '&nbsp;<i class="fa fa-exclamation-circle fa-sm text-warning infotip" title="<?php echo langHdl('password_is_empty'); ?>"></i>&nbsp;';
                        }
                    }

                    // Prepare Favorite icon
                    if (value.display === '' && value.enable_favourites === '1') {
                        if (value.is_favorite === 1) {
                            icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="1">' +
                                '<i class="fa fa-sm fa-star text-warning"></i></span>';
                        } else {
                            icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="0">' +
                                '<i class="fa fa-sm fa-star-o"></i></span>';
                        }
                    }

                    // Prepare Description
                    if (value.desc !== '') {
                        value.desc = '[' + value.desc + ']';
                    }

                    // Prepare flag
                    if (value.expiration_flag !== '') {
                        item_flag = '<i class="fa fa-flag ' + value.expiration_flag + ' fa-sm"></i>&nbsp;';
                    }
                    
                    $('#teampass_items_list').append(
                        '<tr data-edition="' + value.open_edit + '">' +
                        '<td><i class="fa fa-key"></i></td>' +
                        '<td class="teampass-item pointer" data-item-id="' + value.item_id + '">' + value.label + '</td>' +
                        '<td style="max-width: 5%;">' + value.folder + '</td>' +
                        '<td class="text-truncate small" style="max-width: 30%;">' + value.desc + '</td>' +
                        '<td style="min-width: 50px;" class="text-right">' + pwd_error  + icon_login + icon_pwd + icon_favorite + '</td>' +
                        '</tr>'
                    );

                })

                alertify
                    .success('<?php echo langHdl('success');?>', 1)
                    .dismissOthers();

                adjustElemsSize();
            }
        );
    }
}


/**
 * Undocumented function
 *
 * @return void
 */
function refreshVisibleFolders()
{
    $.post(
        'sources/items.queries.php',
        {
            type    : 'refresh_visible_folders',
            key        : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            //check if format error
            if (data.error === '') {
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
                        indentation += '&nbsp;&nbsp;';
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
                $('#categorie, #edit_categorie, #new_rep_groupe, #edit_folder_folder, #delete_rep_groupe').find('option').remove().end().append(html_visible);
                $('#move_folder_id').find('option').remove().end().append(html_full_visible);
                $('#copy_in_folder').find('option').remove().end().append(html_active_visible);

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
    do_refresh = do_refresh || ''
    node_to_select = node_to_select || '';
    refresh_visible_folders = refresh_visible_folders || 1;

    if (refresh_visible_folders !== 1) {
        $('#jstree').jstree('deselect_all');
        $('#jstree').jstree('select_node', '#li_'+groupe_id);
        return false;
    }

    if (do_refresh !== '0') {
        $('#jstree').jstree(true).refresh();
    }

    if (node_to_select !== '') {
        $('#hid_cat').val(node_to_select);
        $('#jstree').jstree('deselect_all');

        $('#jstree')
        .one('refresh.jstree', function(e, data) {
            data.instance.select_node('#li_'+node_to_select);
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
    stop_listing_current_folder = stop_listing_current_folder || '0';

    // Delete existing clipboard
    if (clipboard) {
        clipboard.destroy();
    }

    // case where we should stop listing the items
    if ($('#items_listing_should_stop').val() === '1') {
        requestRunning = false;
        $('#items_listing_should_stop').val('0');
        return false;
    }

    if (stop_listing_current_folder === 1) {
        me.data('requestRunning', false);
        $('#new_listing_characteristics').val(groupe_id+','+restricted+','+start+',0');
    } else {
        $('#new_listing_characteristics').val('');
    }


    // prevent launch of similar query in case of doubleclick
    if (requestRunning === true) {
        return false;
    }
    requestRunning = true;

    $('#request_lastItem, #selected_items').val('');

    // Hide any info
    $('#info_teampass_items_list').addClass('hidden');

    if (groupe_id != undefined) {
        //refreshTree(groupe_id);
        if (query_in_progress != 0 && query_in_progress != groupe_id) {
            request.abort();    //kill previous query if needed
        }
        query_in_progress = groupe_id;
        //LoadingPage();
        $('#items_list_loader').removeClass('hidden');
        if (start == 0) {
            //clean form
            $('#id_label, #id_pw, #id_email, #id_url, #id_desc, #id_login, #id_info, #id_restricted_to, #id_files, #id_tags, #id_kbs, #item_extra_info, #item_viewed_x_times').html('');
            $('#teampass_items_list, #items_folder_path').html('');
        }

        $('#hid_cat').val(groupe_id);
        if ($('.tr_fields') !== undefined) {
            $('.tr_fields, .newItemCat, .editItemCat').addClass('hidden');
        }

        //Disable menu buttons
        $('#button_quick_login_copy, #button_quick_pw_copy').addClass('hidden');

        // Inform user
        alertify
            .message('<?php echo langHdl('opening_folder');?>&nbsp;<i class="fa fa-cog fa-spin"></i>', 0)
            .dismissOthers();

        //ajax query
        request = $.post('sources/items.queries.php',
            {
                type                     : 'lister_items_groupe',
                id                       : groupe_id,
                restricted               : restricted,
                start                    : start,
                uniqueLoadData           : $('#uniqueLoadData').val(),
                key                      : '<?php echo $_SESSION['key']; ?>',
                nb_items_to_display_once : $('#nb_items_to_display_once').val()
            },
            function(retData) {
                if (retData == 'Hacking attempt...') {
                    alert('Hacking attempt...');
                    return false;
                }
                //get data
                data = prepareExchangedData(retData, 'decode', '<?php echo $_SESSION['key']; ?>');
                
                // reset doubleclick prevention
                requestRunning = false;

                // manage not allowed
                if (data.error == 'not_allowed') {
                    alertify
                        .error('<i class="fa fa-warning fa-lg"></i>&nbsp;' + data.error_text, 0)
                        .dismissOthers();
                   return false;
                }

                // to be done only in 1st list load
                if (data.list_to_be_continued === 'end') {
                    $('#pf_selected').val(data.IsPersonalFolder);

                    // display path of folders
                    if ((data.arborescence !== undefined && data.arborescence !== '')) {
                        console.log(data.arborescence);
                        $('#items_folder_path')
                            .html('')
                            .append(rebuildPath(data.arborescence));
                    } else {
                        $('#items_folder_path').html('');
                    }

                    // Remove span with arbo
                    $('#tmp_arbo').remove();

                    // store the categories to be displayed
                    if (data.displayCategories !== undefined) {
                        $('#display_categories').val(data.displayCategories);
                    }

                    // store type of access on folder
                    $('#access_level').val(data.access_level);

                    // warn about a required change of personal SK
                    if ($('#personal_upgrade_needed').val() == '1' && data.recherche_group_pf === 1) {
                        $('#dialog_upgrade_personal_passwords').dialog('open');
                    }

                    // show correct fodler in Tree
                    $('#jstree').jstree('deselect_all');
                    $('#jstree').jstree('select_node', '#li_'+groupe_id);
                } else if (data.error === 'not_authorized') {
                    $('#items_folder_path').html('<i class="fa fa-folder-open-o"></i>&nbsp;'+rebuildPath(data.arborescence));
                } else {
                    $('#uniqueLoadData').val(data.uniqueLoadData);
                    if ($('#items_loading_progress').length == 0) {
                        $('#items_list_loader').after('<span id="items_loading_progress">' + Math.round(data.next_start*100/data.counter_full, 0) + '%</span>');
                    } else {
                        $('#items_loading_progress').html(Math.round(data.next_start*100/data.counter_full, 0) + '%');
                    }
                    // Store arbo
                    if (data.arborescence !== undefined && data.arborescence !== '' && $('#tmp_arbo').length === 0) {
                        // Rebuild path
                        new_path = rebuildPath(data.arborescence);

                        // Store path in tempo element
                        $('body').append('<span class="hidden" id="tmp_arbo">'+new_path+'</span>');
                    }
                }
                
                if (data.array_items.length === 0) {
                    // Show warning to user
                    $('#info_teampass_items_list')
                        .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                            '<i class="fa fa-info-circle"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?></b>' +
                            '</div>')
                        .removeClass('hidden');
                }

                if (data.error === 'is_pf_but_no_saltkey') {
                    //warn user about his saltkey
                    $('#item_details_no_personal_saltkey').removeClass('hidden');
                    $('#menu_button_add_item').prop('disabled', 'true');
                } else if (data.error === 'not_authorized' || data.access_level === '') {
                    //warn user
                    $('#hid_cat').val('');                    
                    $('#item_details_no_personal_saltkey').addClass('hidden');

                    // Show warning to user
                    $('#info_teampass_items_list')
                        .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                            '<i class="fa fa-warning"></i>&nbsp;<?php echo langHdl('not_allowed_to_see_pw'); ?></b>' +
                            '</div>')
                        .removeClass('hidden');

                } else if (($('#user_is_read_only').val() == 1 && data.recherche_group_pf == 0) || data.access_level == 1) {
                    //readonly user
                    $('#recherche_group_pf').val(data.saltkey_is_required);
                    $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                    $('#item_details_ok, #items_list').removeClass('hidden');

                    $('#more_items').remove();

                    // show items
                    showItemsList(data.html_json);

                    if (data.list_to_be_continued === 'yes') {
                        //set next start for query
                        $('#query_next_start').val(data.next_start);
                    } else {
                        $('#query_next_start').val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($('.tr_fields') !== undefined && data.displayCategories !== undefined && data.displayCategories !== '') {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $('.itemCatName_'+liste[i]+', #newItemCatName_'+liste[i]+', #editItemCatName_'+liste[i]).removeClass('hidden');
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($('.tr_fields') != undefined) $('.tr_fields').addClass('hidden');
                        }
                    }

                    proceed_list_update(stop_listing_current_folder);
                } else {
                    $('#recherche_group_pf').val(data.saltkey_is_required);
                    //Display items
                    $('#item_details_no_personal_saltkey, #item_details_nok').addClass('hidden');
                    $('#item_details_ok, #items_list').removeClass('hidden');

                    $('#complexite_groupe').val(data.folder_complexity);
                    $('#bloquer_creation_complexite').val(data.bloquer_creation_complexite);
                    $('#bloquer_modification_complexite').val(data.bloquer_modification_complexite);

                    // show items
                    showItemsList(data.html_json);

                    // Prepare next iteration if needed
                    if (data.list_to_be_continued === 'yes') {
                        //set next start for query
                        $('#query_next_start').val(data.next_start);
                    } else {
                        $('#query_next_start').val(data.list_to_be_continued);

                        // display Categories if needed
                        if ($('.tr_fields') != undefined && data.displayCategories !== undefined && data.displayCategories != '') {
                            var liste = data.displayCategories.split(';');
                            for (var i=0; i<liste.length; i++) {
                                $('.itemCatName_'+liste[i]+', #newItemCatName_'+liste[i]+', #editItemCatName_'+liste[i]).removeClass('hidden');
                            }
                        }
                        if (data.saltkey_is_required == 1) {
                            if ($('.tr_fields') != undefined) $('.tr_fields').addClass('hidden');
                        }
                    }

                    proceed_list_update(stop_listing_current_folder);
                }
            }
        );
    }
}

function showItemsList(data)
{console.log(data);
    $.each((data), function(i, value) {
        var new_line = '',
            pwd_error = '',
            icon_all_can_modify = '',
            icon_login = '',
            icon_pwd = '',
            icon_favorite = '',
            item_flag = '',
            item_grippy = '';

        // Prepare item icon
        if (value.canMove === 1 && value.accessLevel === 0) {
            item_grippy = '<i class="fa fa-sm fa-ellipsis-v item_draggable grippy"></i>&nbsp;</span>';
        }

        // Prepare error message
        if (value.pw_status === 'encryption_error') {
            pwd_error = '<i class="fa fa-warning fa-sm text-danger infotip" title="<?php echo langHdl('pw_encryption_error'); ?>"></i>&nbsp;';
        }

        // Prepare anyone can modify icon
        if (value.anyone_can_modify === '1') {
            icon_all_can_modify = '<i class="fa fa-pencil fa-sm infotip item-modify" title="<?php echo langHdl('item_menu_collab_enable'); ?>"></i>&nbsp;&nbsp;';
        }

        // Prepare mini icons
        if (value.copy_to_clipboard_small_icons === '1' && value.display_item === 1) {
            // Login icon
            if (value.login !== '') {
                icon_login = '<i class="fa fa-sm fa-user mini_login pointer infotip" data-clipboard-text="'+sanitizeString(value.login)+'" title="<?php echo langHdl('item_menu_copy_login'); ?>" id="minilogin_'+value.item_id+'"></i>&nbsp;';
            }
            // Pwd icon
            if (value.pw !== '') {
                icon_pwd = '<i class="fa fa-sm fa-lock mini_pw pointer infotip" data-clipboard-text="'+sanitizeString(value.pw)+'" title="<?php echo langHdl('item_menu_copy_pw'); ?>" data-clipboard-id="'+value.item_id+'" id="minipwd_'+value.item_id+'"></i>&nbsp;';
            }

            // Now check if pwd is empty. If it is then warn user
            if (value.pw === '') {
                pwd_error = '&nbsp;<i class="fa fa-exclamation-circle fa-sm text-warning infotip" title="<?php echo langHdl('password_is_empty'); ?>"></i>&nbsp;';
            }
        }

        // Prepare Favorite icon
        if (value.display_item === 1 && value.enable_favourites === '1') {
            if (value.in_favorite === 1) {
                icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="1">' +
                    '<i class="fa fa-sm fa-star text-warning"></i></span>';
            } else {
                icon_favorite = '<span title="Manage Favorite" class="pointer infotip item-favourite" data-item-id="' + value.item_id + '" data-item-favourited="0">' +
                    '<i class="fa fa-sm fa-star-o"></i></span>';
            }
        }

        // Prepare Description
        if (value.desc !== '') {
            value.desc = '[' + value.desc + ']';
        }

        // Prepare flag
        if (value.expiration_flag !== '') {
            item_flag = '<i class="fa fa-flag ' + value.expiration_flag + ' fa-sm"></i>&nbsp;';
        }
        
        $('#teampass_items_list').append(
            '<tr data-edition="' + value.open_edit + '" data-item-id="'+value.item_id+'", data-item-sk="'+value.sk+'", data-item-expired="'+value.expired+'", data-item-restricted="'+value.restricted+'", data-item-display="'+value.display+'", data-item-open-edit="'+value.open_edit+'", data-item-reload="'+value.reload+'", data-item-tree-id="'+value.tree_id+'">' +
            '<td style="max-width: 5%;">' + item_grippy + '</td>' +
            '<td style="max-width: 5%;"><i class="fa ' + value.perso + ' fa-sm"></i</td>' +
            '<td class="teampass-item pointer" data-item-id="' + value.item_id + '">' + value.label + '</td>' +
            '<td class="text-truncate small" style="max-width: 40%;">' + value.desc + '</td>' +
            '<td style="max-width: 10%;" class="text-right">' + pwd_error + icon_all_can_modify + icon_login + icon_pwd + icon_favorite + '</td>' +
            '</tr>'
        );
    });
    adjustElemsSize();    
}


function adjustElemsSize()
{
    // Adjust height of folders tree
    if ($('#teampass_items_list').height() > (screenHeight - 215)) {
        $('#jstree').height($('#teampass_items_list').height() + 75);
    } else {
        $('#jstree').height($(window).height() - 215);
    }
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
            new_path_elem = ' class="pointer" onclick="ListerItems('+value['id']+', \'\', 0)"';
        }

         new_path += '<li class="breadcrumb-item" id="path_elem_'+value['id']+'"'+new_path_elem+'>'+value['title']+'</li>';
    });

    return new_path;
}

/**

 */
function proceed_list_update(stop_proceeding)
{
    stop_proceeding = stop_proceeding || '';

    if (stop_proceeding === '1'
        || ($('#new_listing_characteristics').val() !== ''
        && $('#query_next_start').val() !== 'end')
    ) {
        var tmp = $('#new_listing_characteristics').val().split(',');
        $('#new_listing_characteristics').val('');
        ListerItems(tmp[0], tmp[1], tmp[2], tmp[3]);
        return false;
    }
    
    if ($('#query_next_start').val() !== 'end') {
        //Check if nb of items do display > to 0
        if ($('#nb_items_to_display_once').val() > 0) {
            ListerItems($('#hid_cat').val(),'', parseInt($('#query_next_start').val()));
        }
    } else {
        //$('ul#full_items_list>li').tsort('',{order:'asc',attr:'name'});

        // Show tooltips
        $('.infotip').tooltip();

        alertify
            .success('<?php echo langHdl('success'); ?>', 1)
            .dismissOthers();

        // prepare clipboard items
        clipboard = new Clipboard('.mini_login');
        clipboard.on('success', function(e) {
            alertify .success('<?php echo langHdl('login_copied_clipboard'); ?>', 1)
            e.clearSelection();
        });

        clipboard = new Clipboard('.mini_pw');
        clipboard.on('success', function(e) {
            alertify .success('<?php echo langHdl('pw_copied_clipboard'); ?>', 1)
            /*itemLog(
                'at_password_copied',
                e.trigger.dataset.clipboardId,
                $('#item_label_'+e.trigger.dataset.clipboardId).text()
            );*/
            e.clearSelection();
        });

        $('.mini_login, .mini_pw').css('cursor', 'pointer');

        // Prepare items dragable on folders
        /*$('.item_draggable').draggable({
            handle: '.grippy',
            cursor: 'move',
            opacity: 0.4,
            appendTo: 'body',
            stop: function(event, ui) {
                $(this).removeClass('ui-state-highlight');
            },
            start: function(event, ui) {
                $(this).addClass('ui-state-highlight');
            },
            helper: function(event) {
                return $('<div class="ui-widget-header" id="drop_helper">'+'<?php echo langHdl('drag_drop_helper'); ?>'+'</div>');
            }
        });
        $('.folder').droppable({
            hoverClass: 'ui-state-error',
            tolerance: 'pointer',
            drop: function(event, ui) {
                ui.draggable.addClass('hidden');
                LoadingPage();
                //move item
                $.post(
                    'sources/items.queries.php',
                    {
                        type      : 'move_item',
                        item_id   : ui.draggable.attr('id'),
                        folder_id : $(this).attr('id').substring(4),
                        key       : '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        LoadingPage();
                        // check if errors
                        if (data[0].error !== '') {
                            if (data[0].error === 'ERR_PSK_REQUIRED') {
                                displayMessage('<?php echo langHdl('psk_required'); ?>');
                            } else {
                                displayMessage('<?php echo langHdl('error_not_allowed_to'); ?>');
                            }
                            ui.draggable.removeClass('hidden');
                            return false;
                        }
                        //increment / decrement number of items in folders
                        $('#itcount_'+data[0].from_folder).text(Math.floor($('#itcount_'+data[0].from_folder).text())-1);
                        $('#itcount_'+data[0].to_folder).text(Math.floor($('#itcount_'+data[0].to_folder).text())+1);
                        $('#id_label, #item_viewed_x_times, #id_desc, #id_pw, #id_login, #id_email, #id_url, #id_files, #id_restricted_to, #id_tags, #id_kbs').html('');
                        displayMessage('<?php echo langHdl('alert_message_done'); ?>');
                    },
                    'json'
               );
            }
        });*/

        var restricted_to_roles = <?php
        if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] == 1) {
            echo 1;
        } else {
            echo 0;
        }
        ?>;

        // refine users list to the related roles
        $.post(
            'sources/items.queries.php',
            {
                type        : 'get_refined_list_of_users',
                iFolderId   : $('#hid_cat').val(),
                key         : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                // *** restricted_to_list ***
                /*$('#restricted_to_list').empty();
                // add list of users
                if ($('#restricted_to').val() != undefined) {
                    $('#restricted_to_list').append(data.selOptionsUsers);
                    if (restricted_to_roles == 1) {
                        //add optgroup
                        var optgroup = $('<optgroup>');
                        optgroup.attr('label', '<?php echo langHdl('users'); ?>');
                        $('.folder_rights_user').wrapAll(optgroup);
                    }
                }
                //Add list of roles if option is set
                if (restricted_to_roles == 1 && $('#restricted_to').val() != undefined) {
                    //add optgroup
                    var optgroup = $('<optgroup>');
                    optgroup.attr('label', '<?php echo langHdl('roles'); ?>');
                    $('#restricted_to_list').append(data.selOptionsRoles);
                    $('.folder_rights_role').wrapAll(optgroup);
                }
                //Prepare multiselect widget
                $('#restricted_to_list').select2({
                    language: '<?php echo $_SESSION['user_language_code']; ?>'
                });

                // *** edit_restricted_to_list ***
                $('#edit_restricted_to_list').empty();
                if ($('#edit_restricted_to').val() != undefined) {
                    $('#edit_restricted_to_list').append(data.selEOptionsUsers);
                    if (restricted_to_roles == 1) {
                        //add optgroup
                        var optgroup = $('<optgroup>');
                        optgroup.attr('label', '<?php echo langHdl('users'); ?>');
                        $('.folder_rights_user_edit').wrapAll(optgroup);
                    }
                }
                //Add list of roles if option is set
                if (restricted_to_roles == 1 && $('#edit_restricted_to').val() != undefined) {
                    //add optgroup
                    var optgroup = $('<optgroup>');
                    optgroup.attr('label', '<?php echo langHdl('roles'); ?>');
                    $('#edit_restricted_to_list').append(data.selEOptionsRoles);
                    $('.folder_rights_role_edit').wrapAll(optgroup);
                }
                //Prepare multiselect widget
                $('#edit_restricted_to_list').select2({
                    language: '<?php echo $_SESSION['user_language_code']; ?>'
                });*/
            }
       );
    }
}





function ShowItemDetails(itemDefinition)
{
    // If a request is already launched, then kill new.
    if ($('#request_ongoing').val() !== '') {
        request.abort();
        return;
    }

    var itemId = parseInt($(itemDefinition).data('item-id')) || '';
    var itemTreeId = parseInt($(itemDefinition).data('item-tree-id')) || '';
    var itemSk = parseInt($(itemDefinition).data('item-sk')) || 0;
    var itemExpired = parseInt($(itemDefinition).data('item-expired')) || '';
    var itemRestricted = parseInt($(itemDefinition).data('item-restricted-id')) || '';
    var itemDisplay = parseInt($(itemDefinition).data('item-display')) || 0;
    var itemOpenEdit = parseInt($(itemDefinition).data('item-open-edit')) || 0;
    var itemReload = parseInt($(itemDefinition).data('item-reload')) || 0;

    // Store status query running
    $('#request_ongoing').val(1);

    // If opening new item, reinit hidden fields
    if (parseInt($('#request_lastItem').val()) !== itemId) {
        $('#request_lastItem').val('');
        $('#item_editable').val('');
    }

    // Don't show details
    if (itemDisplay === 'no_display') {
        // Inform user
        $('#info_teampass_items_list')
            .html('<div class="alert alert-primary text-center col col-lg-10" role="alert">' +
                '<i class="fa fa-info-circle"></i>&nbsp;<?php echo langHdl('no_item_to_display'); ?></b>' +
                '</div>')
            .removeClass('hidden');
        $('#table_teampass_items_list').addClass('hidden');

        $('#item_details_nok').removeClass('hidden');
        $('#item_details_ok').addClass('hidden');
        $('#item_details_expired').addClass('hidden');
        $('#item_details_expired_full').addClass('hidden');
        $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_url, #menu_button_copy_link').attr('disabled','disabled');
        $('#request_ongoing').val('');
        return false;
    }
    $('#div_loading').removeClass('hidden');
    if ($('#is_admin').val() == '1') {
        $('#menu_button_edit_item,#menu_button_del_item,#menu_button_copy_item').attr('disabled', 'disabled');
    }

    if ($('#edit_restricted_to') != undefined) {
        $('#edit_restricted_to').val('');
    }

    // Check if personal SK is needed and set
    if (($('#recherche_group_pf').val() === '1' && $('#personal_sk_set').val() === '0') && itemSk === 1) {
        $('#set_personal_saltkey_warning').html('<div style="font-size:16px;"><span class="fa fa-warning fa-lg"></span>&nbsp;</span><?php echo langHdl('alert_message_personal_sk_missing'); ?></div>').show(1).delay(2500).fadeOut(1000);
        $('#div_set_personal_saltkey').dialog('open');

        $('#div_loading').addClass('hidden');

        $('#request_ongoing').val('');
        return false;
    } else if ($('#recherche_group_pf').val() === '0' || ($('#recherche_group_pf').val() === '1' && $('#personal_sk_set').val() === '1')) {
        // Double click
        if (itemOpenEdit === 1 && $('#item_editable').val() === '1') {
            $('#request_ongoing').val('');
            open_edit_item_div(
                <?php if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === '1') {
    echo 1;
} else {
    echo 0;
}?>
            );
        } else if (parseInt($('#request_lastItem').val()) === itemId && itemReload !== 1) {
            $('#request_ongoing').val('');
            LoadingPage();
            return;
        } else {
            $('#timestamp_item_displayed').val('');
            var data = {
                'id' : itemId,
                'folder_id' : $('#hid_cat').val(),
                'salt_key_required' : $('#recherche_group_pf').val(),
                'salt_key_set' : $('#personal_sk_set').val(),
                'expired_item' : itemExpired === undefined ? '' : itemExpired,
                'restricted' : itemRestricted === undefined ? '' : itemRestricted,
                'page' : 'items'
            };

            //Send query
            $.post(
                'sources/items.queries.php',
                {
                    type : 'show_details_item',
                    data : prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key  : '<?php echo $_SESSION['key']; ?>'
                },
                function(data_raw) {
                    //decrypt data
                    try {
                        data = prepareExchangedData(data_raw , 'decode', '<?php echo $_SESSION['key']; ?>');
                    } catch (e) {
                        // error
                        $('#div_loading').addClass('hidden');
                        $('#request_ongoing').val('');
                        $('#div_dialog_message_text').html('An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />'+data_raw);
                        $('#div_dialog_message').show();
                        return;
                    }
                    
                    if (data.error != '') {
                        $('#div_dialog_message_text').html('An error appears. Answer from Server cannot be parsed!<br /><br />Returned data:<br />'+data.error);
                        $('#div_dialog_message').show();
                    }

                    // reset password shown info
                    $('#pw_shown').val('0');

                    // show some info on top
                    if (data.auto_update_pwd_frequency != '0') var auto_update_pwd = '<i class="fa fa-shield tip" title="<?php echo langHdl('server_auto_update_password_enabled_tip'); ?>"></i>&nbsp;<b>'+data.auto_update_pwd_frequency+'</b>&nbsp;|&nbsp;';
                    else var auto_update_pwd = '';
                    $('#item_viewed_x_times').html(auto_update_pwd+'&nbsp;<i class="fa fa-sticky-note-o tip" title="Number of times item was displayed"></i>&nbsp;<b>'+data.viewed_no+'</b>');

                    // Show timestamp
                    $('#timestamp_item_displayed').val(data.timestamp);

                    //Change the class of this selected item
                    if ($('#selected_items').val() !== '') {
                        $('#fileclass'+$('#selected_items').val()).removeClass('fileselected');
                    }
                    $('#selected_items').val(data.id);

                    //Show saltkey
                    if (data.edit_item_salt_key == '1') {
                        $('#edit_item_salt_key').show();
                    } else {
                        $('#edit_item_salt_key').addClass('hidden');
                    }

                    // clean some not used fields
                    //$('#item_history_log, #edit_past_pwds, #hid_files, #item_edit_list_files').html('');

                    //Show detail item
                    if (data.show_detail_option == '0') {
                        $('#item_details_ok').removeClass('hidden');
                        $('#item_details_expired, #item_details_expired_full').addClass('hidden');
                    }if (data.show_detail_option == '1') {
                        $('#item_details_ok, #item_details_expired').removeClass('hidden');
                        $('#item_details_expired_full').addClass('hidden');
                    } else if (data.show_detail_option == '2') {
                        $('#item_details_ok, #item_details_expired, #item_details_expired_full').addClass('hidden');
                    }
                    $('#item_details_nok').addClass('hidden');
                    $('#fileclass'+data.id).addClass('fileselected');
                    $('item_editable').val(0);

                    if (data.show_details == '1' && data.show_detail_option != '2') {
                        //unprotect data
                        data.login = unsanitizeString(data.login);

                        $('#id_files').html('');

                        //Display details
                        $('#id_label').html(data.label);
                        $('#hid_label').val(unsanitizeString(data.label));
                        if (data.pw === '') {
                            $('#id_pw').html('');
                        } else {
                            $('#id_pw').html('<?php echo $var['hidden_asterisk']; ?>');
                        }
                        
                        $('#hid_pw').text(unsanitizeString(data.pw));
                        if (data.url != '') {
                            $('#id_url').html(data.url+data.link);
                            $('#hid_url').val(data.url);
                        } else {
                            $('#id_url').html('');
                            $('#hid_url').val('');
                        }
                        $('#id_desc').html(data.description);
                        $('#hid_desc').val(data.description);
                        $('#id_login').html(data.login);
                        $('#hid_login').val(data.login);
                        $('#id_email').html(data.email);
                        $('#hid_email').val(data.email);
                        //prepare nice list of users / groups
                        var tmp_arr = data.id_restricted_to.split(';');
                        var html_users = '';
                        for (var i=0; i<tmp_arr.length; i++) {
                            if (tmp_arr[i] !== '') html_users += '<span class="round-grey"><i style="margin-right:2px;" class="fa fa-user fa-sm"></i>'+tmp_arr[i]+'</span>';
                        }
                        var tmp_arr = data.id_restricted_to_roles.split(';');
                        var html_groups = '';
                        for (var i=0; i<tmp_arr.length; i++) {
                            if (tmp_arr[i] !== '') html_groups += '<span class="round-grey"><i style="margin-right:2px;" class="fa fa-group fa-sm"></i>'+tmp_arr[i]+'</span>';
                        }
                        $('#id_restricted_to').html(
                            html_users+
                            html_groups
                        );
                        $('#hid_restricted_to').val(data.id_restricted_to);
                        $('#hid_restricted_to_roles').val(data.id_restricted_to_roles);
                        $('#id_tags').html(data.tags);
                        // extract real tags list
                        var item_tag = '';
                        $('span.item_tag').each(function(){
                            if (item_tag == '') item_tag = $(this).text();
                            else item_tag += ' '+$(this).text();
                        });
                        $('#hid_tags').val(item_tag);
                        $('#hid_anyone_can_modify').val(data.anyone_can_modify);
                        $('#id_categorie').val(data.folder);
                        $('#id_item').val(data.id);
                        $('#id_kbs').html(data.links_to_kbs);
                        $('.tip').tooltipster({
                            maxWidth: 400,
                            contentAsHTML: true,
                            multiple: true
                        });

                        // ---
                        // Show Field values
                        $('.fields').val('');
                        $('.fields_div, .fields').html('');
                        // If no CF then hide
                        if (data.fields === '') {
                            $('.tr_fields').addClass('hidden');
                        } else {
                            $('.tr_cf, .tr_fields').addClass('hidden');
                            var liste = data.fields.split('_|_');
                            for (var i=0; i<liste.length; i++) {
                                var field = liste[i].split('~~');
                                if (data.template_id !== '') {
                                    if (data.template_id === field[2]) {
                                        $('#cf_tr_' + field[0] + ', #tr_catfield_' + field[2]).removeClass('hidden');
                                    }
                                } else {
                                    $('#cf_tr_' + field[0] + ', #tr_catfield_' + field[2]).removeClass('hidden');
                                }
                                
                                $('#hid_field_' + field[0] + '_' + field[2]).text(field[1].replace(/<br ?\/?>/g,''));
                                if (field[4] === '1') {
                                    $('#id_field_' + field[0] + '_' + field[2])
                                        .html('<?php echo $var['hidden_asterisk']; ?>');
                                } else {
                                    $('#id_field_' + field[0] + '_' + field[2])
                                        .text(field[1]);
                                }
                            }
                        }

                        // 
                        if (data.template_id !== '') {
                            $('.default_item_field, .tr_fields_header').addClass('hidden');
                        } else {
                            $('.default_item_field, .tr_fields_header').removeClass('hidden');
                        }

                        // Template id
                        $('#template_selected_id').val(data.template_id);

                        //Anyone can modify button
                        if (data.anyone_can_modify == '1') {
                            $('#edit_anyone_can_modify').attr('checked', true);
                            $('#new_history_entry_form').show();
                        } else {
                            $('#edit_anyone_can_modify').attr('checked', false);
                            $('#new_history_entry_form').addClass('hidden');
                        }

                        //Show to be deleted in case activated
                        if (data.to_be_deleted == 'not_enabled') {
                            $('#edit_to_be_deleted').addClass('hidden');
                        } else {
                            $('#edit_to_be_deleted').show();
                            if (data.to_be_deleted != '') {
                                $('#edit_enable_delete_after_consultation').attr('checked',true);
                                if (data.to_be_deleted_type == 2) {
                                    $('#edit_times_before_deletion').val('');
                                    $('#edit_deletion_after_date').val(data.to_be_deleted);
                                } else {
                                    $('#edit_times_before_deletion').val(data.to_be_deleted);
                                    $('#edit_deletion_after_date').val('');
                                }
                            } else {
                                $('#edit_enable_delete_after_consultation').attr('checked',false);
                                $('#edit_times_before_deletion, #edit_deletion_after_date').val('');
                            }
                        }

                        //manage buttons
                        if ($('#user_is_read_only').val() == 1) {
                            $('#menu_button_add_item, #menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                        } else if (data.user_can_modify == 0) {
                            $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').attr('disabled', 'disabled');
                        } else if (data.restricted == '1' || data.user_can_modify == '1') {
                            //$('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item').prop('disabled', false);
                            var param = '#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item';
                            $('#new_history_entry_form').show();
                        } else {
                            //$('#menu_button_add_item, #menu_button_copy_item').prop('disabled', false);
                            var param = '#menu_button_del_item, #menu_button_copy_item';
                            $('#new_history_entry_form').show();
                        }
                        //$('#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history').prop('disabled', false);

                        // disable share button for personal folder
                        if ($('#recherche_group_pf').val() == 1) {
                            $('#menu_button_share, #menu_button_otv').attr('disabled', 'disabled');
                        } else {
                            $('#menu_button_share, #menu_button_otv').prop('disabled', false);
                        }

                        //Manage to deleted information
                        if (data.to_be_deleted != 0 && data.to_be_deleted != null && data.to_be_deleted != 'not_enabled') {
                            $('#item_extra_info')
                                .html('<b><i class="fa fa-bell-o mi-red"></i></b>&nbsp;')
                                .attr('title', '<?php echo langHdl('automatic_deletion_activated'); ?>');
                            $('#item_extra_info').tooltipster({multiple: true});
                        } else {
                            $('#item_extra_info').html('');
                        }

                        if (data.notification_status == 0 && data.id_user == <?php echo $_SESSION['user_id']; ?>) {
                            $('#menu_button_notify')
                                .prop('disabled', false)
                                .attr('title','<?php echo langHdl('enable_notify'); ?>')
                                .attr('onclick','notify_click(\'true\')');
                            $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                        } else if (data.notification_status == 1 && data.id_user == <?php echo $_SESSION['user_id']; ?>) {
                            $('#menu_button_notify')
                                .prop('disabled', false)
                                .attr('title','<?php echo langHdl('disable_notify'); ?>')
                                .attr('onclick','notify_click(\'false\')');
                            $('#div_notify').attr('class', '<i class="fa fa-bell-slash mi-red"></i>&nbsp;');
                            $('#item_extra_info').html('<i><i class=\'fa fa-bell mi-green\"></i>&nbsp;<?php echo langHdl('notify_activated'); ?></i>');
                        } else {
                            $('#menu_button_notify').attr('disabled', 'disabled');
                            $('#div_notify').attr('class', '<i class="fa fa-bell mi-green"></i>&nbsp;');
                        }

                        //Prepare clipboard copies
                        if (data.pw != '') {
                            var clipboard_pw = new Clipboard('#menu_button_copy_pw, #button_quick_pw_copy', {
                                text: function() {
                                    return (unsanitizeString(data.pw));
                                }
                            });
                            clipboard_pw.on('success', function(e) {
                                $('#message_box').html('<?php echo langHdl('pw_copied_clipboard'); ?>').show().fadeOut(1000);
                                itemLog(
                                    'at_password_copied',
                                    e.trigger.dataset.clipboardId,
                                    $('#item_label_'+e.trigger.dataset.clipboardId).text()
                                );

                                e.clearSelection();
                            });

                            $('#button_quick_pw_copy').removeClass('hidden');
                        } else {
                            $('#button_quick_pw_copy').addClass('hidden');
                        }
                        if (data.login != '') {
                            var clipboard_login = new Clipboard('#menu_button_copy_login, #button_quick_login_copy', {
                                text: function() {
                                    return (data.login);
                                }
                            });
                            clipboard_login.on('success', function(e) {
                                $('#message_box').html('<?php echo langHdl('login_copied_clipboard'); ?>').show().fadeOut(1000);

                                e.clearSelection();
                            });
                            $('#button_quick_login_copy').removeClass('hidden');
                        } else {
                            $('#button_quick_login_copy').addClass('hidden');
                        }
                        // #525
                        if (data.url != '') {
                            var clipboard_url = new Clipboard('#menu_button_copy_url', {
                                text: function() {
                                    return unsanitizeString(data.url);
                                }
                            });
                            clipboard_url.on('success', function(e) {
                                $('#message_box').html('<?php echo langHdl('url_copied_clipboard'); ?>').show().fadeOut(1000);

                                e.clearSelection();
                            });
                        }

                        //prepare link to clipboard
                        var clipboard_link = new Clipboard('#menu_button_copy_link', {
                            text: function() {
                                return '<?php echo $SETTINGS['cpassman_url']; ?>'+'/index.php?page=items&group='+data.folder+'&id='+data.id;
                            }
                        });
                        clipboard_link.on('success', function(e) {
                            $('#message_box').html('<?php echo langHdl('url_copied'); ?>').show().fadeOut(1000);

                            e.clearSelection();
                        });


                        //set if user can edit
                        if (data.restricted == '1' || data.user_can_modify == '1') {
                            $('#item_editable').val(1);
                        }
                        
                        //Manage double click
                        if (itemOpenEdit === '1' && (data.restricted === 1 || data.user_can_modify === 1)) {
                            open_edit_item_div(
                            <?php if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] == 1) {
    echo 1;
} else {
    echo 0;
}?>);
                        }

                        // tags
                        $('.round-grey').addClass('ui-state-highlight ui-corner-all');

                        // continue loading data
                        showDetailsStep2(id, param);

                    } else if (data.show_details === '1' && data.show_detail_option === '2') {
                        $('#item_details_nok').addClass('hidden');
                        $('#item_details_ok').addClass('hidden');
                        $('#item_details_expired_full').show();
                        $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled','disabled');
                        $('#div_loading').addClass('hidden');
                    } else {
                        //Dont show details
                        $('#item_details_nok').removeClass('hidden');
                        $('#item_details_nok_restriction_list').html('<div style="margin:10px 0 0 20px;"><b><?php echo langHdl('author'); ?>: </b>' + data.author + '<br /><b><?php echo langHdl('restricted_to'); ?>: </b>' + data.restricted_to + '<br /><br /><u><a href="#" onclick="openReasonToAccess()"><?php echo langHdl('request_access_ot_item'); ?></a></u></div>');

                        $('#reason_to_access').remove();
                        $('#item_details_nok')
                          .append('<input type="hidden" id="reason_to_access" value="'+data.id + ',' + data.id_user+'">');

                        // Protect
                        $('#item_details_ok').addClass('hidden');
                        $('#item_details_expired').addClass('hidden');
                        $('#item_details_expired_full').addClass('hidden');
                        $('#menu_button_edit_item, #menu_button_del_item, #menu_button_copy_item, #menu_button_add_fav, #menu_button_del_fav, #menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link').attr('disabled','disabled');
                        $('#div_loading').addClass('hidden');
                    }
                    $('#request_ongoing').val('');

                    // Double click
                    if (itemOpenEdit === 1 && $('#item_editable').val() === '1') {
                        $('#request_ongoing').val('');
                        open_edit_item_div(
                            <?php if (isset($SETTINGS['restricted_to_roles']) && $SETTINGS['restricted_to_roles'] === '1') {
    echo 1;
} else {
    echo 0;
}?>
                        );
                     }
                }
            );

            if (itemTreeId !== '' && itemTreeId !== $('#hid_cat').val()) {
                refreshTree(itemTreeId, '0');
            }
       }

        //Store Item id shown
        $('#request_lastItem').val(itemId);
    }
}


/*
* Loading Item details step 2
*/
function showDetailsStep2(id, param)
{
    $('#div_loading').removeClass('hidden');
    $.post(
        'sources/items.queries.php',
        {
        type    : 'showDetailsStep2',
        id      : id
        },
        function(data) {
            //decrypt data
            try {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            } catch (e) {
                // error
                $('#div_loading').addClass('hidden');
                $('#request_ongoing').val('');
                $('#div_dialog_message_text').html('An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />'+data);
                $('#div_dialog_message').dialog('open');

                return;
            }

            if (data.error !== '') {
                $('#div_dialog_message_text').html(data.error_text);
                $('#div_dialog_message').show();
                return false;
            }

            $('#item_history_log').html(htmlspecialchars_decode(data.history));
            $('#edit_past_pwds').attr('title', htmlspecialchars_decode(data.history_of_pwds));
            $('#edit_past_pwds_div').html(htmlspecialchars_decode(data.history_of_pwds));

            $('#id_files').html(data.files_id);
            $('#hid_files').val(data.files_id);
            $('#item_edit_list_files').html(data.files_edit);

            //$('#div_last_items').html(htmlspecialchars_decode(data.div_last_items));

            // function calling image lightbox when clicking on link
            $('a.image_dialog').click(function(event) {
                event.preventDefault();
                PreviewImage($(this).attr('href'),$(this).attr('title'));
            });

            //Set favourites icon
            if (data.favourite == '1') {
                $('#menu_button_add_fav').attr('disabled','disabled');
                $('#menu_button_del_fav').prop('disabled', false);
            } else {
                $('#menu_button_add_fav').prop('disabled', false);
                $('#menu_button_del_fav').attr('disabled','disabled');
            }

            // set indicator if item has change proposal
            if (parseInt(data.has_change_proposal) > 0) {
                $('#item_extra_info').prepend('<i class="fa fa-lightbulb-o fa-sm mi-yellow tip" title="<?php echo langHdl('item_has_change_proposal'); ?>"></i>&nbsp;');
            }

            $(param).prop('disabled', false);
            $('#menu_button_show_pw, #menu_button_copy_pw, #menu_button_copy_login, #menu_button_copy_link, #menu_button_history').prop('disabled', false);
            $('#div_loading').addClass('hidden');

            $('.tip').tooltipster({multiple: true});

            // refresh
            if ($('#hid_cat').val() !== '') {
                refreshListLastSeenItems();
            }
         }
     );
};

</script>
