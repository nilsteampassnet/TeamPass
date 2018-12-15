<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], '2fa', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
//<![CDATA[

loadFieldsList();

$(document).on('click', '.edit', function() {
    var action = $(this).data('action'),
        row = $(this).closest('tr');

    if (action ==='category') {
        var categoryId = row.data('category');

        console.log(categoryId)
    } else if (action ==='field') {
        var categoryId = row.data('category'),
            fieldId = row.data('field');
            
        console.log(categoryId + " -- "+fieldId)
    }
});


$(document).on('click', '.move', function() {
    var direction = $(this).data('direction'),
        row = $(this).closest('tr'),
        categoryId = row.data('category'),
        fieldId = row.data('field'),
        currentOrder = $(row).data('order');

    if (direction ==='up') {
        if ()
        console.log(categoryId)
    } else if (direction ==='field') {
            
    }
        console.log(categoryId + " -- "+fieldId+" -- "+currentOrder)
});


/**
 * Loading table
 *
 * @return void
 */
function loadFieldsList() {
    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();
    
    //send query
    $.post(
        "sources/fields.queries.php",
        {
            type    : "loadFieldsList",
            //option  : prepareExchangedData(JSON.stringify(option), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            }
            catch (e) {
                // error
                showAlertify(
                    '<?php echo langHdl('server_answer_error').'<br />'.langHdl('server_returned_data').':<br />'; ?>' + data.error,
                    0,
                    'top-right',
                    'error'
                );
                return false;
            }
            console.log(data);

            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>' + data.message,
                        5
                    )
                    .dismissOthers();
            } else {
                if (data.array.length > 0) {
                    // Init
                    var html = '',
                        categoryId = '';

                    // Parse array and build table
                    $(data.array).each(function(i, val) {
                        console.log(i+' -- '+val)
                        // Is CATEGORY or FIELD?
                        if (val.category === true) {
                            //--- This is a Category
                            categoryId = val.id;

                            // Loop on associated folders
                            var foldersList = '';
                            $(val.folders).each(function(j, folder) {
                                foldersList += '<span class="folder mr-2" data-folder="' + folder.id + '">' +
                                    '<i class="far fa-folder mr-1"></i>' + folder.title + '</span>';
                            });

                            // Prepare html
                            html += '<tr class="table-primary" data-category="' + categoryId + '" data-order="' + val.order + '">' +
                                '<td class="text-left" width="80px"><i class="fas fa-caret-up move pointer mr-2" data-direction="up"></i>' +
                                '<i class="fas fa-caret-down move pointer mr-2" data-direction="down"></i>' +
                                '<i class="far fa-edit pointer edit" data-action="category"></i></td>' +
                                '<td class="text-left" colspan="2"><strong>' + val.title + '</strong></td>' +
                                '<td class="no-ellipsis" width="50%"><small>' + foldersList + '</small></td>' +
                                '</tr>';
                        } else {
                            //--- This is a Field
                            // Init
                            var encrypted = '',
                                masked = '',
                                mandatory = '',
                                type = '<i class="fas fa-paragraph ml-2 infotip" title="<?php echo langHdl('text'); ?>"></i>';

                            if (val.encrypted === 1) {
                                encrypted = '<i class="fas fa-shield-alt ml-2 infotip" title="<?php echo langHdl('encrypted_data'); ?>"></i>';
                            }

                            if (val.masked === 1) {
                                masked = '<i class="fas fa-mask ml-2" infotip" title="<?php echo langHdl('data_is_masked'); ?>></i>';
                            }

                            if (val.mandatory === 1) {
                                mandatory = '<i class="fas fa-fire text-danger ml-2 infotip" title="<?php echo langHdl('is_mandatory'); ?>"></i>';
                            }

                            if (val.type === 'textarea') {
                                type = '<i class="fas fa-align-justify ml-2 infotip" title="<?php echo langHdl('textarea'); ?>"></i>';
                            }

                            // Prepare html
                            html += '<tr class="field" data-category="' + categoryId + '" data-field="' + val.id + '" data-order="' + val.order + '">' +
                                '<td class="text-left"><i class="fas fa-caret-up move pointer mr-2" data-direction="up"></i>' +
                                '<i class="fas fa-caret-down move pointer mr-2" data-direction="down"></i>' +
                                '<i class="far fa-edit pointer edit" data-action="field"></i></td>' +
                                '<td class="text-left"><i class="fas fa-angle-right mr-2"></i>' + val.title + '</td>' +
                                '<td class="text-center">' + mandatory + encrypted + masked +  type + '</td>' +
                                '<td class="">' + val.groups + '</td>' +
                                '</tr>';
                        }
                    });

                    // Display
                    $('#table-fields > tbody').html(html);

                    $('.no-ellipsis')
                        .removeAttr('text-overflow')
                        .removeAttr('overflow');

                    $('#table-fields').removeClass('hidden');

                    // Show tooltips
                    $('.infotip').tooltip();
                } else {
                    // No fields is defined
                    $("#fields-message")
                        .html("<?php echo langHdl('no_category_defined'); ?>")
                        .removeClass("hidden");
                }

                // Inform user
                showAlertify(
                    '<?php echo langHdl('done'); ?>',
                    2,
                    'top-bottom',
                    'success'
                );
            }

            
            var newList = '<table id="tbl_categories" cellspacing="0" cellpadding="0" border="0px" width="100%">';
            // parse json table and disaply
            var json = $.parseJSON(data);

            if ($(json).length > 0) {
                var current_category = '';
                $(json).each(function(i,val){
                    if (val[0] === "1") {
                        current_category = val[1];
                        newList += '<tr id="t_cat_'+val[1]+'" style="background-color:#e1e1e1; margin-bottom:2px;" width="40%">'+
                        '<td colspan="2" style="font-weight:bold; padding:2px;">'+
                        '<input type="text" id="catOrd_'+val[1]+'" size="1" class="category_order" value="'+val[3]+'" />&nbsp;'+
                        '<input type="radio" name="sel_item" id="item_'+val[1]+'_cat" class="hidden" />'+
                        '<label for="item_'+val[1]+'_cat" id="item_'+val[1]+'" class="pointer">'+val[2]+'</label>'+
                        '</td><td style="padding:2px;" width="8%">'+
                        '<span class="fa-stack tip" title="<?php echo $LANG['field_add_in_category']; ?>" onclick="fieldAdd('+
                        val[1]+')" style="cursor:pointer;">'+
                        '<span class="fa fa-square fa-stack-2x"></span><span class="fa fa-plus fa-stack-1x fa-inverse"></span>'+
                        '</span>&nbsp;'+
                        '<span class="fa-stack tip" title="<?php echo $LANG['category_in_folders']; ?>" onclick="catInFolders('+val[1]+')" style="cursor:pointer;">'+
                        '<span class="fa fa-square fa-stack-2x"></span><span class="fa fa-folder-o fa-stack-1x fa-inverse"></span>'+
                        '</span>'+
                        '</td><td style="padding:2px;" width="52%"><?php echo $LANG['category_in_folders_title']; ?>:'+
                        '<span style="font-family:italic; margin-left:10px;" id="catFolders_'+val[1]+'">'+
                        (val[4] === '' ? '<?php echo $LANG['none']; ?>' : val[4])+'</span>'+
                        '<input type="hidden" id="catFoldersList_'+val[1]+'" value="'+val[5]+'" /></td></tr>';
                    } else {
                        newList += '<tr id="t_field_'+val[1]+'" class="drag">'+
                        '<td width="20px"><input type="hidden" class="field_info" value="' + current_category + ','+val[4]+','+val[6]+','+val[7]+','+val[10]+'" /></td>'+
                        '<td colspan="1" style="border-bottom:1px solid #a0a0a0; padding:3px 0 1px 0;">'+
                        '<input type="text" id="catOrd_'+val[1]+'" size="1" class="category_order" value="'+val[3]+'" />&nbsp;'+
                        '<input type="radio" name="sel_item" id="item_'+val[1]+'_cat" class="hidden" />'+
                        '<label for="item_'+val[1]+'_cat" id="item_'+val[1]+'" width="100%" class="pointer">'+val[2]+'</label>'+
                        '</td><td colspan="1" style="border-bottom:1px solid #a0a0a0;">';

                        if (val[4] !== "") {
                            newList += '<span id="encryt_data_'+val[1]+'" style="margin-left:4px; cursor:pointer;">';
                            if (val[4] === "1") {
                                newList += '<i class="fa fa-key tip" title="<?php echo $LANG['encrypted_data']; ?>"></i>';
                            } else if (val[4] === "0") {
                                newList += '<span class="fa-stack tip" title="<?php echo $LANG['not_encrypted_data']; ?>">'+
                                    '<span class="fa fa-key fa-stack-1x"></span><span class="fa fa-ban fa-stack-1x fa-lg" style="color:red;"></span></span>';
                            }
                            newList += '</span>'
                        }

                        if (val[6] !== "") {
                            newList += '<span style="margin-left:4px;">';
                            if (val[6] === "text") {
                                newList += '<span class="fa fa-paragraph tip" title="<?php echo $LANG['text']; ?>"></span>';
                            } else if (val[6] === "textarea") {
                                newList += '<span class="fa fa-align-justify tip" title="<?php echo $LANG['textarea']; ?>"></span>';
                            }

                            if (val[7] === "1") {
                                newList += '&nbsp;<span class="fa fa-eye-slash tip" title="<?php echo $LANG['data_is_masked']; ?>"></ispan>';
                            }

                            if (val[10] === "1") {
                                newList += '&nbsp;<span class="fa fa-fire tip mi-red" title="<?php echo $LANG['is_mandatory']; ?>"></ispan>';
                            }
                            newList += '</span>'
                        }

                        // Manage display Roles visibility
                        newList += '<td colspan="1" style="border-bottom:1px solid #a0a0a0;">' +
                            '<?php echo $LANG['visible_by']; ?>: <span style="font-family:italic;">' + val[8] +
                            '</span><input type="hidden" id="roleVisibilityList_'+val[1]+'" value="' + val[9] + '" /></td></tr>';
                    }
                });

                // display
                newList += '</table>';
                $("#new_item_title").val("");
                $("#categories_list").html(newList);
            } else {
                $("#no_category")
                    .html("<?php echo addslashes($LANG['no_category_defined']); ?>")
                    .removeClass("hidden");
            }
            $('.tip').tooltipster({multiple: true});
            $("#div_loading").hide();
        }
   );
}
//]]>
</script>