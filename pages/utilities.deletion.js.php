<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      utilities.deletion.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.deletion') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[
    var debugJavascript = false;


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
        toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        // Do clean
        $('#recycled-folders, #recycled-items').html('<div class="text-warning"><i class="fas fa-info mr-2"></i><?php echo $lang->get('refreshing'); ?></div>');
        $('#temp-message').remove();

        // Launch action
        $.post(
            'sources/utilities.queries.php', {
                type: 'recycled_bin_elements',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                console.log(data);
                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // FOLDERS - Build table
                    if (data.folders.length === 0) {
                        $('#recycled-folders, #recycled-items').html(
                            '<div class="alert alert-info" id="temp-message">' +
                            '<?php echo $lang->get('empty_list'); ?>' +
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
                            '<?php echo $lang->get('empty_list'); ?>' +
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
                                    '<td class=""><?php echo $lang->get('belong_of_deleted_folder'); ?></td>' :
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
                        '<?php echo $lang->get('done'); ?>',
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
                    '<?php echo $lang->get('number_of_selected_objects'); ?>: <span id="objects_counter" class="text-bold">' +
                    $('input:checkbox:checked').length + '</span></h5>' +
                    '<?php echo $lang->get('highlight_selected'); ?>:<i class="far fa-check-circle fa-lg ml-2 pointer text-success" id="highlight"></i>' +
                    '<i class="far fa-times-circle fa-lg ml-2 pointer text-danger" id="highlight-cancel"></i></div>' +
                    '<div class="alert alert-info"><i class="fas fa-warning mr-2"></i><?php echo $lang->get('confirm_selection_restore'); ?></div>');

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
                    '<?php echo $lang->get('number_of_selected_objects'); ?>: <span id="objects_counter" class="text-bold">' +
                    $('input:checkbox:checked').length + '</span></h5>' +
                    '<?php echo $lang->get('highlight_selected'); ?>:<i class="far fa-check-circle fa-lg ml-2 pointer text-success" id="highlight"></i>' +
                    '<i class="far fa-times-circle fa-lg ml-2 pointer text-danger" id="highlight-cancel"></i></div>' +
                    '<div class="alert alert-warning"><i class="fas fa-warning mr-2"></i><?php echo $lang->get('confirm_selection_delete'); ?></div>');

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
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

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
        if (debugJavascript === true) {
            console.log("Selection action: " + action);
            console.log(data);
        }
        // Launch action
        $.post(
            'sources/utilities.queries.php', {
                type: action,
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);
                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('error'); ?>', {
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
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 5000
                        }
                    );
                }
            }
        );
    }
    //]]>
</script>
