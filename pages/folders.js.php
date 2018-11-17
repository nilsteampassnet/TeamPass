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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
//<![CDATA[

buildTable();

function buildTable()
{
    // Clear
    $('#table-folders > tbody').html('');

    // Show spinner
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    // Build matrix
    $.post(
        'sources/folders.queries.php',
        {
            type    : 'build_matrix',
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            console.log(data);
            if (data.error !== false) {
                // Show error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                    .dismissOthers();
            } else {
                // Build html
                var newHtml = '',
                    ident = '',
                    path = '';
                $(data.matrix).each(function(i, value) {
                    // Access
                    access = '';
                    if (value.access === 'W') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo langHdl('edit_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo langHdl('delete_allowed'); ?>"></i>';
                    } else if (value.access === 'ND') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-success infotip" title="<?php echo langHdl('edit_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo langHdl('delete_not_allowed'); ?>"></i>';
                    } else if (value.access === 'NE') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo langHdl('edit_not_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-success infotip" title="<?php echo langHdl('delete_allowed'); ?>"></i>';
                    } else if (value.access === 'NDNE') {
                        access = '<i class="fas fa-indent mr-2 text-success infotip" title="<?php echo langHdl('add_allowed'); ?>"></i>' +
                            '<i class="fas fa-pen mr-2 text-danger infotip" title="<?php echo langHdl('edit_not_allowed'); ?>"></i>' +
                            '<i class="fas fa-eraser mr-2 text-danger infotip" title="<?php echo langHdl('delete_anot_llowed'); ?>"></i>';
                    } else if (value.access === 'R') {
                        access = '<i class="fas fa-book-reader mr-2 text-warning infotip" title="<?php echo langHdl('read_only'); ?>"></i>';
                    } else {
                        access = '<i class="fas fa-ban mr-2 text-danger infotip" title="<?php echo langHdl('no_access'); ?>"></i>';
                    }

                    // Build path
                    path = '';
                    $(value.path).each(function(j, valuePath) {
                        if (path === '') {
                            path = valuePath;
                        } else {
                            path += ' / ' + valuePath;
                        }
                    });

                    // Finalize
                    newHtml += '<tr>' +
                        '<td width="35px"><input type="checkbox" id="cb-' + value.id + '" data-id="' + value.id + '" class="folder-select"></td>' +
                        '<td class="pointer modify" data-id="' + value.id + '" data-access="' + value.access + '">' + value.title + '</td>' +
                        '<td class="font-italic pointer modify" data-id="' + value.id + '" data-access="' + value.access + '">' + path + '</td>' +
                        '<td class="pointer modify" data-id="' + value.id + '" data-access="' + value.access + '">' + access + '</td>' +
                        '</tr>'
                });

                // Show result
                $('#role-details').html(
                    '<table id="table-role-details" class="table table-hover table-striped" style="width:100%"><tbody>' +
                    newHtml +
                    '</tbody></table>'
                );

                //iCheck for checkbox and radio inputs
                $('#role-details input[type="checkbox"]').iCheck({
                    checkboxClass: 'icheckbox_flat-blue'
                });

                $('.infotip').tooltip();

                // Inform user
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        }
    );
}


//************************************************************** */

/*
$('.infotip').tooltip();

$('.select2').select2({
    language: '<?php echo $_SESSION['user_language_code']; ?>'
});

// Prepare iCheck format for checkboxes
$('input[type="checkbox"].flat-red').iCheck({
    checkboxClass: 'icheckbox_flat-red',
});

var calcDataTableHeight = function() {
  return $(window).height() * 55 / 100;
};

//Launch the datatables pluggin
var oTable = $('#table-folders').dataTable({
    'paging': false,
    //'searching': true,
    //'order': [[1, 'asc']],
    'info':             false,
    'processing':       false,
    'serverSide':       true,
    'responsive':       true,
    'select':           false,
    'stateSave':        true,
    'autoWidth':        true,
    'scrollY':          calcDataTableHeight(),
    'deferRender':      true,
    'scrollCollapse' :  true,
    'ajax': {
        url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/folders.datatable.php',
    },
    'language': {
        'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
    },
    'columns': [
        {'width': '90px'},
        {className: 'dt-body-left'},
        {className: 'dt-body-left'},
        {'width': '70px', className: 'dt-body-center'},
        {'width': '70px', className: 'dt-body-center'},
        {'width': '70px', className: 'dt-body-center'},
        {'width': '90px', className: 'dt-body-center'}
    ],
    'preDrawCallback': function() {
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
    },
    'drawCallback': function() {
        // Tooltips
        $('.infotip').tooltip();

        // Hide checkbox if search filtering
        var searchCriteria = $('body').find('[aria-controls="table-folders"]');
        if (searchCriteria.val() !== '' ) {
            $(document).find('.cb_selected_folder').addClass('hidden');
        } else {
            $(document).find('.cb_selected_folder').removeClass('hidden');
        }

        //iCheck for checkbox and radio inputs
        $('#table-folders input[type="checkbox"]').iCheck({
            checkboxClass: 'icheckbox_flat-blue'
        });

        alertify
            .message('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();
    },
    'createdRow': function( row, data, dataIndex ) {
        var newClasses = $(data[6]).filter('#row-class-' + dataIndex).val();
        $(row).addClass(newClasses);
    }
});


// Manage collapse/expend
$(document).on('click', '.icon-collapse', function() {
    if ($(this).hasClass('fa-folder-minus') === true) {
        $(this)
            .removeClass('fa-folder-minus')
            .addClass('fa-folder-plus text-primary');

        $('.p' + $(this).data('id')).addClass('hidden');
    } else {
        $(this)
            .removeClass('fa-folder-plus  text-primary')
            .addClass('fa-folder-minus');
            $('.p' + $(this).data('id')).removeClass('hidden');
    }
});

var currentText = '',
    item = '',
    initialColumnWidth = '',
    actionOnGoing = false;

// Edit folder label
$(document).on('click', '.edit-text', function() {
    var field = '';
    currentText = $(this).text();
    item = $(this); 
    
    if ($(this).hasClass('field-renewal')) {
        initialColumnWidth = $('#table-folders thead th')[4].style.width;
        $('#table-folders thead th')[4].style.width = '100px';
        field = 'renewal_period';
    } else if ($(this).hasClass('field-title')) {
        field = 'title';
        initialColumnWidth = $('#table-folders thead th')[1].style.width;
    } else {
        return false;
    }

    $(this)
        .addClass('hidden')
        .after('<input type="text" class="form-control form-item-control remove-me save-me" value="' + currentText + '">');

    $('.save-me')
        .focus()
        .focusout(function() {
            saveChange(item, currentText, $(this), field);
        });
});

// Edit folder label
$(document).on('click', '.edit-select', function() {
    var field = '',
        change = '';
    currentText = $(this).text();
    item = $(this);
    
    // Hide existing
    $(this).addClass('hidden');
    if ($(this).hasClass('field-complex')) {
        initialColumnWidth = $('#table-folders thead th')[3].style.width;
        $('#table-folders thead th')[3].style.width = '200px';
        field = 'complexity';
    } else {
        return false;
    }

    // Show select
    $("#select-complexity")
        .insertAfter('#' + $(this).attr('id'))
        .after('<i class="fa fa-close text-danger pointer temp-button mr-3" id="select-complexity-close"></i>');
    $('#select-complexity option[value="' + $(this).data('value') + '"]').prop('selected', true);
    console.log($(this).data('value'))
    


    $('.save-me')
        .change(function() {
            if (actionOnGoing === false) {
                actionOnGoing = true;
                saveChange(item, currentText, $(this), field)
            }
        });

    $('#select-complexity-close').click(function() {
        $("#select-complexity").detach().appendTo('#hidden-select-complexity');
        $('#table-folders thead th')[3].style.width = initialColumnWidth;
        $('.edit-select').removeClass('hidden');
        $('.tmp-loader, .temp-button').remove();
    });
});

$(document).keyup(function(e) {
    if (e.keyCode === 27) {
        $('.remove-me, .tmp-loader').remove();
        $('.edit-text').removeClass('hidden');
    }
    if (e.keyCode === 13) {
        var $focused = $(':focus');
        //console.log($focused)
        console.log(currentText)
    }
});




// 
function saveChange(item, currentText, change, field)
{
    if (change.val() !== currentText) {
        change
            .after('<i class="fa fa-refresh fa-spin fa-fw tmp-loader"></i>');

        // prepare data
        var data = {
            'folder_id' : item.data('id'),
            'field'     : field,
            'value'     : change.val()
        };
        console.log(data);
        // Save
        $.post(
            'sources/folders.queries.php',
            {
                type :  'save_folder_change',
                data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key  :  '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {console.log(data);
                if (field === 'renewal_period' || field === 'title') {
                    change.remove();
                    $('.tmp-loader').remove();
                    item
                        .text(change.val())
                        .removeClass('hidden');
                    $('#table-folders thead th')[4].style.width = initialColumnWidth;
                } else if (field === 'complexity') {
                    $("#select-complexity").detach().appendTo('#hidden-select-complexity');
                    $('#table-folders thead th')[3].style.width = initialColumnWidth;
                    $('.tmp-loader, .temp-button').remove();
                    
                    // Show change
                    item
                        .html('<i class="' + data.return.html + '"></i>')
                        .attr('data-original-title', data.return.tip)
                        .attr('data-value', data.return.value)
                        .removeClass('hidden');
                    
                    $('.infotip').tooltip();
                }
                actionOnGoing = false;
            },
            'json'
        );
    } else {
        change.remove();
        $('.tmp-loader').remove();
        item
            .text(change.val())
            .removeClass('hidden');
    }
}


// NEW FORM
var deletionList = [];
$('.tp-action').click(function() {
    if ($(this).data('action') === 'new') {
        $('.form').addClass('hidden');
        $('#folders-new')
            .removeClass('hidden');
    } else if ($(this).data('action') === 'delete') {
        // Build list of folders to delete
        var list = '<ul>';
        $(".cb_selected_folder:checked").each(function() {
            list += '<li>' +
                $($('#table-folders tbody tr')[$(this).data('row')]).find('.field-title').text() +
                '</li>';
                deletionList.push($(this).data('id'));
        });
        $('#delete-list').html(list);

        // If selection then enable button 
        $('#delete-submit').addClass('disabled');
        if (deletionList.length > 0) {
            $('.form').addClass('hidden');
            $('#folders-delete')
                .removeClass('hidden');
        } else {
            // Inform user
            alertify.set('notifier','position', 'top-center');
            alertify
                .warning(
                    '<i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('no_selection_done'); ?>',
                    5
                )
                .dismissOthers();
            alertify.set('notifier','position', 'bottom-right');
        }

        
    } else if ($(this).data('action') === 'refresh') {
        $('.form').addClass('hidden');
        $('#folders-list')
            .removeClass('hidden');
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
        oTable.api().ajax.reload();
    }
});

$('.btn').click(function() {
    if ($(this).data('action') === 'new-submit') {
        // SHow loader
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // prepare data
        var data = {
            'label' : $('#new-label').val(),
            'parent' : $('#new-parent').val(),
            'complexity' : $('#new-minimal-complexity').val(),
            'access-right' : $('#new-access-right').val(),
            'duration' : $('#new-duration').val(),
            'create-without' : $('#new-create-without').val(),
            'edit-without' : $('#new-edit-without').val(),
        };
        
        // Save
        $.post(
            'sources/folders.queries.php',
            {
                type :  'add_folder',
                data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key  :  '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.info(data);

                if (data.newId === '') {
                    alertify
                        .error(
                            '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                            0
                        )
                        .dismissOthers();
                } else {
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }

                // Clear
                $('.clear-me').val('');
                $('#new-duration').val('0');
                $('.select2').val('').change();

                // Reload
                oTable.api().ajax.reload();

                // Show
                $('.form').addClass('hidden');
                $('#folders-list').removeClass('hidden');
            }
        );
    } else if ($(this).data('action') === 'delete-submit' && $(this).hasClass('disabled') === false) {
        // prepare data
        var data = {
            'folders-list' : deletionList,
        };
        
        // If no selection then 
        if (deletionList.length === 0) {
            return;
        }

        if ($('#delete-confirm').is(':checked') === false) {
            alertify.set('notifier','position', 'top-center');
            alertify
                .warning(
                    '<i class="fa fa-warning fa-lg mr-2"></i><?php echo langHdl('tick_confirmation_box'); ?>',
                    5
                )
                .dismissOthers();
            alertify.set('notifier','position', 'bottom-right');
            return;
        }

        // SHow loader
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Save
        $.post(
            'sources/folders.queries.php',
            {
                type :  'delete_multiple_folders',
                data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key  :  '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    alertify
                        .error(
                            '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                            0
                        )
                        .dismissOthers();
                } else {
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }

                // Clear
                $('.clear-me').val('');
                $('#new-duration').val('0');
                $('.select2').val('').change();

                // Reload
                oTable.api().ajax.reload();

                // Show
                $('.form').addClass('hidden');
                $('#folders-list').removeClass('hidden');
            }
        );
    } else if ($(this).data('action') === 'cancel') {
        deletionList = [];
        $('.clear-me').val('');
        $('#new-duration').val('0');
        $('.select2').val('').change();
        $('.form').addClass('hidden');
        $('#folders-list').removeClass('hidden');
    }
});


var operationOngoin = false;
$(document).on('ifChecked', '.cb_selected_folder', function() {
    if (operationOngoin === false) {
        operationOngoin = true;
        
        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Show selection of folders
        var selected_cb = $(this),
            id = $(this).data('id');

        // Show selected
        $(this).closest('tr').css("background-color", "#c2e6fc");

        // Now get subfolders
        $.post(
            'sources/folders.queries.php',
            {
                type    : 'select_sub_folders',
                id      : id,
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                // check/uncheck checkbox
                if (data.subfolders !== '') {
                    $.each(JSON.parse(data.subfolders), function(i, value) {
                        $('#checkbox-' + value).iCheck('check');
                        $('#checkbox-' + value).closest('tr').css("background-color", "#c2e6fc");
                    });
                }
                operationOngoin = false;

                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        );
    }
});


$(document).on('ifUnchecked', '.cb_selected_folder', function() {
    if (operationOngoin === false) {
        operationOngoin = true;
        
        // Show spinner
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Show selection of folders
        var selected_cb = $(this),
            id = $(this).data('id');

        $(this).closest('tr').css("background-color", "");

        // Now get subfolders
        $.post(
            'sources/folders.queries.php',
            {
                type    : 'select_sub_folders',
                id      : id,
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                // check/uncheck checkbox
                if (data.subfolders !== '') {
                    $.each(JSON.parse(data.subfolders), function(i, value) {
                        $('#checkbox-' + value).iCheck('uncheck');
                        $('#checkbox-' + value).closest('tr').css("background-color", "");
                    });
                }
                operationOngoin = false;

                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        );
    }
});

// Toogle icon
$(document).on('click', '.toggle', function() {
    // send change to be stored
    $.post(
        "sources/folders.queries.php",
        {
            type    : $(this).data('type'),
            value   : $(this).data('set'),
            id      : $(this).data('id'),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            // refresh table content
            oTable.api().ajax.reload();
        }
    );
});


// On checkbox confirm, enable button
$('#delete-confirm')
    .on('ifChecked',function() {
        $('#delete-submit').removeClass('disabled');
    })
    .on('ifUnchecked',function() {
        $('#delete-submit').addClass('disabled');
    });

*/



//]]>
</script>