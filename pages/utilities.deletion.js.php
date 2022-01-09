<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      utilities.deletion.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[


    // Prepare tooltips
    $('.infotip').tooltip();

    // Prepare iCheck
    $('#toggle-all').iCheck({
        checkboxClass: 'icheckbox_flat-blue'
    });


    /**
     * ON START
     */
    $(function() {
        // Show spinner
        toastr.remove();
        toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        // Do clean
        $('#recycled-folders, #recycled-items').html('<div class="text-warning"><i class="fas fa-info mr-2"></i><?php echo langHdl('refreshing'); ?></div>');
        $('#temp-message').remove();

        // Launch action
        $.post(
            'sources/utilities.queries.php', {
                type: 'recycled_bin_elements',
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                console.log(data);
                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // FOLDERS - Build table
                    if (data.folders.length === 0) {
                        $('#recycled-folders, #recycled-items').html(
                            '<div class="alert alert-info" id="temp-message">' +
                            '<?php echo langHdl('empty_list'); ?>' +
                            '</div>'
                        )

                    } else {
                        var foldersHtml = '',
                            itemsHtml = '';

                        // Folders list
                        $.each(data.folders, function(index, value) {
                            foldersHtml += '<tr class="icheck-toggle">' +
                                '<td width="35px"><input type="checkbox" data-id="' + value.id + '" class="folder-select"></td>' +
                                '<td class="font-weight-bold">' + value.label + '</td>' +
                                '</tr>';
                        });
                        $('#recycled-folders').html(foldersHtml);
                    }
                    

                    // Items - Build table
                    if (data.items.length === 0) {
                        $('#recycled-items').html(
                            '<div class="alert alert-info" id="temp-message">' +
                            '<?php echo langHdl('empty_list'); ?>' +
                            '</div>'
                        )

                    } else {
                        // Items list
                        $.each(data.items, function(index, value) {
                            itemsHtml += '<tr class="icheck-toggle">' +
                                '<td width="35px"><input type="checkbox" data-id="' + value.id + '" class="item-select"></td>' +
                                '<td class="font-weight-bold">' + value.label + '</td>' +
                                '<td class="font-weight-light"><i class="far fa-calendar-alt mr-1"></i>' + value.date + '</td>' +
                                '<td class=""><i class="far fa-user mr-1"></i>' + value.name + ' [' + value.login + ']</td>' +
                                '<td class="font-italic"><i class="far fa-folder mr-1"></i>' + value.folder_label + '</td>' +
                                (value.folder_deleted === true ?
                                    '<td class=""><?php echo langHdl('belong_of_deleted_folder'); ?></td>' :
                                    '') +
                                '</tr>';
                        });
                        $('#recycled-items').html(itemsHtml);

                        // Prepare iCheck
                        $('#recycled-bin input[type="checkbox"]').iCheck({
                            checkboxClass: 'icheckbox_flat-blue'
                        });

                        // Global checkboxes toggle
                        $('#toggle-all').on('ifChanged', function(event) {
                            if ($(this).is(':checked') === true) {
                                $('.item-select, .folder-select').iCheck('check');
                            } else {
                                $('.item-select, .folder-select').iCheck('uncheck');
                            }
                        });
                    }

                    // OK
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            }
        );
    });


    // Toggle checkbox on row click
    $(document).on('click', '.icheck-toggle', function() {
        $(":checkbox:eq(0)", this).iCheck('toggle');

        // Inc / Dec counter
        updateCounter($(":checkbox:eq(0)", this));
    });


    $(document).on('click', '#highlight', function() {
        showSelected();
    });

    // On click, show all delected elements
    $(document).on('click', '#highlight-cancel', function() {
        $('.icheck-toggle').each(function(index, obj) {
            $(obj).removeClass('hidden');
        });
    });


    // Buttons action
    $('button').click(function() {
        if ($(this).data('action') === 'restore' && $('#recycled-bin input[type=checkbox]:checked').length > 0) {
            // Show text to user
            $('#recycled-bin-confirm-restore div').find('.card-body')
                .html('<div class="callout callout-info"><h5>' +
                    '<?php echo langHdl('number_of_selected_objects'); ?>: <span id="objects_counter" class="text-bold">' +
                    $('input:checkbox:checked').length + '</span></h5>' +
                    '<?php echo langHdl('highlight_selected'); ?>:<i class="far fa-check-circle fa-lg ml-2 pointer text-success" id="highlight"></i>' +
                    '<i class="far fa-times-circle fa-lg ml-2 pointer text-danger" id="highlight-cancel"></i></div>' +
                    '<div class="alert alert-info"><i class="fas fa-warning mr-2"></i><?php echo langHdl('confirm_selection_restore'); ?></div>');

            // Hide other confirm box
            $('#recycled-bin-confirm-restore').addClass('hidden');

            // SHow confirm
            $('#recycled-bin-confirm-restore').removeClass('hidden');

            // Perform filter
            showSelected();
        } else if ($(this).data('action') === 'delete' && $('#recycled-bin input[type=checkbox]:checked').length > 0) {
            // Show text to user
            $('#recycled-bin-confirm-delete div').find('.card-body')
                .html('<div class="callout callout-warning"><h5>' +
                    '<?php echo langHdl('number_of_selected_objects'); ?>: <span id="objects_counter" class="text-bold">' +
                    $('input:checkbox:checked').length + '</span></h5>' +
                    '<?php echo langHdl('highlight_selected'); ?>:<i class="far fa-check-circle fa-lg ml-2 pointer text-success" id="highlight"></i>' +
                    '<i class="far fa-times-circle fa-lg ml-2 pointer text-danger" id="highlight-cancel"></i></div>' +
                    '<div class="alert alert-warning"><i class="fas fa-warning mr-2"></i><?php echo langHdl('confirm_selection_delete'); ?></div>');

            // Hide other confirm box
            $('#recycled-bin-confirm-restore').addClass('hidden');

            // SHow confirm        
            $('#recycled-bin-confirm-delete').removeClass('hidden');

            // Perform filter
            showSelected();
        } else if ($(this).data('action') === 'cancel-restore') {
            // Hide the confirm box
            $('#recycled-bin-confirm-restore').addClass('hidden');
        } else if ($(this).data('action') === 'cancel-delete') {
            // Hide the confirm box
            $('#recycled-bin-confirm-delete').addClass('hidden');
        } else if ($(this).data('action') === 'submit-restore') {
            // Now perform RESTORE
            restoreOrDelete(
                'restore_selected_objects',
                'recycled-bin-confirm-restore'
            );
        } else if ($(this).data('action') === 'submit-delete') {
            // Now perform DELETE
            restoreOrDelete(
                'delete_selected_objects',
                'recycled-bin-confirm-delete'
            );
        }
    });


    function updateCounter(checkbox) {
        if ($('#objects_counter').length > 0) {
            if (checkbox.is(':checked') === true) {
                $('#objects_counter').text(parseInt($('#objects_counter').text()) + 1);
            } else {
                $('#objects_counter').text(parseInt($('#objects_counter').text()) - 1);
            }
        }
    }


    function showSelected() {
        $('.icheck-toggle').each(function(index, obj) {
            if ($(":checkbox:eq(0)", obj)[0].checked === true) {
                $(obj).removeClass('hidden');
            } else {
                $(obj).addClass('hidden');
            }
        });
    }


    function restoreOrDelete(action, id) {
        // Show spinner
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Prepare selected data
        var folders = [],
            items = [];
        $('.icheck-toggle').each(function(index, obj) {
            var my = $(":checkbox:eq(0)", obj);
            if (my[0].checked === true) {
                if (my.hasClass('folder-select') === true) {
                    folders.push(my.data('id'));
                } else if (my.hasClass('item-select') === true) {
                    items.push(my.data('id'));
                }
            }
        });
        var data = {
            'folders': folders,
            'items': items,
        }

        // Launch action
        $.post(
            'sources/utilities.queries.php', {
                type: action,
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Remove deleted elements
                    // And Remove filter
                    $('.icheck-toggle').each(function(index, obj) {
                        var my = $(":checkbox:eq(0)", obj);
                        if (my[0].checked === true) {
                            $(obj).remove();
                        } else {
                            $(obj).removeClass('hidden');
                        }
                    });

                    $('#' + id).addClass('hidden');

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            }
        );
    }
    //]]>
</script>
