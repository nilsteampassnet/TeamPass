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
  * @file      search.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('search') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
$var = [];
$var['hidden_asterisk'] = '<i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk mr-2"></i><i class="fas fa-asterisk"></i>';

?>


<script type="text/javascript">
    var pwdClipboard,
        loginClipboard,
        urlClipboard;

    //Launch the datatables pluggin
    var oTable = $("#search-results-items").DataTable({
        "paging": true,
        "lengthMenu": [
            [10, 25, 50, -1],
            [10, 25, 50, "All"]
        ],
        "pagingType": "full_numbers",
        "searching": true,
        "info": true,
        "order": [
            [1, "asc"]
        ],
        "processing": true,
        "serverSide": true,
        "responsive": true,
        "select": false,
        "stateSave": true,
        "autoWidth": true,
        "ajax": {
            url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/find.queries.php",
            type: 'GET',
            "dataSrc": function ( json ) {
                for ( var i=0, ien=json.data.length ; i<ien ; i++ ) {
                    json.data[i][1]=atob(json.data[i][1]).utf8Decode();
                    json.data[i][2]=atob(json.data[i][2]).utf8Decode();
                    json.data[i][3]=atob(json.data[i][3]).utf8Decode();
                    json.data[i][4]=atob(json.data[i][4]).utf8Decode();
                    json.data[i][6]=atob(json.data[i][6]).utf8Decode();
                }
                return (json.data);
            }
        },
        "language": {
            "url": "<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt"
        },
        "columns": [{
                "width": "70px",
                class: "details-control",
                defaultContent: ""
            },
            {
                "width": "15%"
            },
            {
                "width": "10%"
            },
            {
                "width": "25%"
            },
            {
                "width": "10%"
            },
            {
                "width": "15%"
            },
            {
                "width": "15%"
            }
        ],
        "drawCallback": function() {
            // Tooltips
            $('.infotip').tooltip();

            //iCheck for checkbox and radio inputs
            $('#search-results-items input[type="checkbox"]').iCheck({
                checkboxClass: 'icheckbox_flat-blue'
            });
        }
    });

    var detailRows = [];

    $("#search-results-items tbody").on('click', '.item-detail', function() {
        var tr = $(this).closest('tr');
        var row = oTable.row(tr);
        var idx = $.inArray(tr.attr('id'), detailRows);
        var itemGlobal = row.data();
        var item = $(this);

        if (row.child.isShown()) {
            row.child.hide();

            // Change eye icon
            $(this)
                .removeClass('fa-eye-slash text-warning')
                .addClass('fa-eye');

            // Remove from the 'open' array
            detailRows.splice(idx, 1);
        } else {
            // Remove existing one
            if ($('.new-row').length > 0) {
                $('.new-row').remove();

                // Change eye icon
                var eyeIcon = $('.item-detail').closest('i.fa-eye-slash');
                eyeIcon
                    .removeClass('fa-eye-slash text-warning')
                    .addClass('fa-eye');
            }

            // Change eye icon
            $(this)
                .removeClass('fa-eye')
                .addClass('fa-eye-slash text-warning');

            // Add loader
            $(this)
                .after('<i class="fas fa-refresh fa-spin fa-fw" id="search-spinner"></i>');

            // Get content of item
            row.child(showItemInfo(itemGlobal, tr, item), 'new-row').show();

            // Add to the 'open' array
            if (idx === -1) {
                detailRows.push(tr.attr('id'));
            }
        }
    });

    function showItemInfo(d, tr, item) {
        // prepare data
        var data = {
            'id': $(item).data('id'),
            'folder_id': $(item).data('tree-id'),
            'salt_key_required': $(item).data('perso'),
            'salt_key_set': store.get('teampassUser').pskSetForSession,
            'expired_item': $(item).data('expired'),
            'restricted': $(item).data('restricted-to'),
            'page': 'find',
            'rights': $(item).data('rights'),
        };

        // Launch query
        $.post(
            'sources/items.queries.php', {
                type: 'show_details_item',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                console.info(data);
                var return_html = '';
                if (data.show_detail_option !== 0 || data.show_details === 0) {
                    //item expired
                    return_html = '<?php echo $lang->get('not_allowed_to_see_pw_is_expired'); ?>';
                } else if (data.show_details === '0') {
                    //Admin cannot see Item
                    return_html = '<?php echo $lang->get('not_allowed_to_see_pw'); ?>';
                } else {
                    return_html = '<td colspan="7">' +
                        '<div class="card card-info">' +
                        '<div class="card-header">' +
                        '<h5 id="item-label">' + data.label + '</h5>' +
                        '</div>' +
                        '<div class="card-body">' +
                        (data.description === '' ? '' : '<div class="form-group">' + data.description + '</div>') +
                        '<div class="form-group">' +
                        '<?php echo $lang->get('pw'); ?>' +
                        '<button type="button" class="btn btn-secondary ml-2" id="btn-copy-pwd" data-id="' + data.id + '" data-label="' + data.label + '"><i class="fas fa-copy"></i></button>' +
                        '<button type="button" class="btn btn-secondary btn-show-pwd ml-2" data-id="' + data.id + '"><i class="fas fa-eye pwd-show-spinner"></i></button>' +
                        '<span id="pwd-show_' + data.id + '" class="unhide_masked_data ml-2" style="height: 20px;"><?php echo $var['hidden_asterisk']; ?></span>' +
                        '<input type="hidden" id="pwd-is-shown_' + data.id + '" value="0">' +
                        '</div>' +
                        (data.login === '' ? '' :
                            '<div class="form-group">' +
                            '<label class="form-group-label"><?php echo $lang->get('index_login'); ?>' +
                            '<button type="button" class="btn btn-secondary ml-2" id="btn-copy-login" data-id="' + data.id + '"><i class="fas fa-copy"></i></button>' +
                            '</label>' +
                            '<span class="ml-2" id="login-item_' + data.id + '">' + data.login + '</span>' +
                            '</div>') +
                        (data.url === '' ? '' :
                            '<div class="form-group">' +
                            '<label class="form-group-label"><?php echo $lang->get('url'); ?>' +
                            '<button type="button" class="btn btn-secondary ml-2" id="btn-copy-url" data-id="' + data.id + '"><i class="fas fa-copy"></i></button>' +
                            '</label>' +
                            '<span class="ml-2" id="url-item_' + data.id + '">' + data.url + '</span>' +
                            '</div>') +
                        '</div>' +
                        '<div class="card-footer">' +
                        '<button type="button" class="btn btn-info float-right" id="cancel"><?php echo $lang->get('cancel'); ?></button>' +
                        '</div>' +
                        '</div>' +
                        '</td>';
                }
                $(tr).next('tr').html(return_html);
                $('.unhide_masked_data').addClass('pointer');

                // On click on CANCEL
                $('#cancel').on('click', function() {
                    // Change eye icon
                    var eyeIcon = $('.item-detail').closest('i.fa-eye-slash');
                    eyeIcon
                        .removeClass('fa-eye-slash text-warning')
                        .addClass('fa-eye');

                    // Remove card
                    $('.new-row').remove();
                });

                // Prepare clipboard using async function
                document.getElementById('btn-copy-pwd').addEventListener('click', async function() {
                    try {
                        // Retrieve the password
                        const password = await getItemPassword('at_password_copied', 'item_id', $('#btn-copy-pwd').data('id'));

                        if (!password) {
                            toastr.error('<?php echo $lang->get("error_fetching_password"); ?>', '', {
                                timeOut: 3000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            });
                            return;
                        }

                        // Copy to clipboard
                        await navigator.clipboard.writeText(password);

                        // Notification for the user
                        const clipboardDuration = store.get('teampassSettings').clipboard_life_duration || 0;
                        if (clipboardDuration === 0) {
                            toastr.info('<?php echo $lang->get("copy_to_clipboard"); ?>', '', {
                                timeOut: 2000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            });
                        } else {
                            toastr.warning('<?php echo $lang->get("clipboard_will_be_cleared"); ?>', '', {
                                timeOut: clipboardDuration * 1000,
                                progressBar: true
                            });

                            // Clear the clipboard content after a delay
                            const cleaner = new ClipboardCleaner(clipboardDuration);
                            cleaner.scheduleClearing(
                                () => {
                                    const clipboardStatus = JSON.parse(localStorage.getItem('clipboardStatus'));
                                    if (clipboardStatus.status === 'unsafe') {
                                        return;                                        
                                    }
                                    toastr.success('<?php echo $lang->get("clipboard_cleared"); ?>', '', {
                                        timeOut: 2000,
                                        positionClass: 'toast-bottom-right'
                                    });
                                },
                                (error) => {
                                    console.error('Error clearing clipboard:', error);
                                }
                            );
                        }
                    } catch (error) {
                        toastr.error('<?php echo $lang->get("clipboard_error"); ?>', '', {
                            timeOut: 3000,
                            positionClass: 'toast-bottom-right',
                            progressBar: true
                        });
                    }
                });
                
                // Click handler to copy the login
                document.getElementById('btn-copy-login').addEventListener('click', async function() {
                    try {
                        // Retrieve the ID of the element containing the login
                        const loginId = this.dataset.id;
                        const loginText = document.getElementById('login-item_' + loginId).textContent;

                        // Copy the text to the clipboard
                        await navigator.clipboard.writeText(loginText);

                        // Display a success notification
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get("copy_to_clipboard"); ?>',
                            '', {
                                timeOut: 2000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            }
                        );
                    } catch (error) {
                        toastr.error(
                            '<?php echo $lang->get("clipboard_error"); ?>',
                            '', {
                                timeOut: 3000,
                                positionClass: 'toast-bottom-right',
                                progressBar: true
                            }
                        );
                    }
                });

                // Check if the btn-copy-url button exists
                const btnCopyUrl = document.getElementById('btn-copy-url');

                if (btnCopyUrl) {
                    // Attach a click handler only if the button exists
                    btnCopyUrl.addEventListener('click', async function() {
                        try {
                            // Retrieve the ID of the element containing the URL
                            const urlId = this.dataset.id;
                            const urlText = document.getElementById('url-item_' + urlId).textContent;

                            // Copy the URL to the clipboard
                            await navigator.clipboard.writeText(urlText);

                            // Display a success notification
                            toastr.remove();
                            toastr.info(
                                '<?php echo $lang->get("copy_to_clipboard"); ?>',
                                '', {
                                    timeOut: 2000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                }
                            );
                        } catch (error) {
                            toastr.error(
                                '<?php echo $lang->get("clipboard_error"); ?>',
                                '', {
                                    timeOut: 3000,
                                    positionClass: 'toast-bottom-right',
                                    progressBar: true
                                }
                            );
                        }
                    });
                }

                $('#search-spinner').remove();
                $('.infotip').tooltip();
            }
        );
        return data;
    }

    // show password during longpress
    let mouseStillDown = false;
    $('#search-results-items')
        .on('mousedown', '.unhide_masked_data', function(event) {
            mouseStillDown = true;

            showPwdContinuous($(this).attr('id'));
        })
        .on('mouseup', '.unhide_masked_data', function(event) {
            mouseStillDown = false;
            showPwdContinuous($(this).attr('id'));
        })
        .on('mouseleave', '.unhide_masked_data', function(event) {
            mouseStillDown = false;
            showPwdContinuous($(this).attr('id'));
        });

    const showPwdContinuous = function showPwdContinuous(elem_id) {
        const itemId = elem_id.split('_')[1];
        if (mouseStillDown === true 
            && !$('#pwd-show_' + itemId).hasClass('pwd-shown')) {

            const item_pwd = getItemPassword(
                'at_password_shown',
                'item_id',
                itemId
            );

            $('#pwd-show_' + itemId).text(item_pwd);
            $('#pwd-show_' + itemId).addClass('pwd-shown');

            // Auto hide password
            setTimeout('showPwdContinuous("pwd-show_' + itemId + '")', 50);
        } else if(mouseStillDown !== true) {
            $('#pwd-show_' + itemId)
                .html('<?php echo $var['hidden_asterisk']; ?>')
                .removeClass('pwd-shown');
        }
    };

    // Manage the password show button
    // including autohide after a couple of seconds
    $(document).on('click', '.btn-show-pwd', function() {
        if ($(this).hasClass('pwd-shown') === false) {
            const itemId = $(this).data('id');
            $(this).addClass('pwd-shown');
            
            const item_pwd = getItemPassword(
                'at_password_shown',
                'item_id',
                itemId
            );

            $('#pwd-show_' + itemId).text(item_pwd);

            // Change class and show spinner
            $('.pwd-show-spinner')
                .removeClass('far fa-eye')
                .addClass('fas fa-circle-notch fa-spin text-warning');

            // Autohide
            setTimeout(() => {
                $(this).removeClass('pwd-shown');
                $('#pwd-show_' + itemId).html('<?php echo $var['hidden_asterisk']; ?>');
                $('.pwd-show-spinner')
                    .removeClass('fas fa-circle-notch fa-spin text-warning')
                    .addClass('far fa-eye');
            }, <?php echo isset($SETTINGS['password_overview_delay']) === true ? $SETTINGS['password_overview_delay'] * 1000 : 4000; ?>);
        } else {
            $('#pwd-show_' + itemId).html('<?php echo $var['hidden_asterisk']; ?>');
        }
    });


    var selectedItems = '',
        selectedAction = '',
        listOfFolders = '';
    $("#search-results-items tbody").on('ifToggled', '.mass_op_cb', function() {
        // Check if at least one CB is checked
        if ($("#search-results-items input[type=checkbox]:checked").length > 0) {
            // Show selection menu
            if ($('#search-select').hasClass('menuset') === false) {
                $('#search-select')
                    .addClass('menuset')
                    .html(
                        '<?php echo $lang->get('actions'); ?>' +
                        '<i class="fas fa-share ml-2 pointer infotip mass-operation" title="<?php echo $lang->get('move_items'); ?>" data-action="move"></i>' +
                        '<i class="fas fa-trash ml-2 pointer infotip mass-operation" title="<?php echo $lang->get('delete_items'); ?>" data-action="delete"></i>'
                    );

                // Prepare tooltips
                $('.infotip').tooltip();
            }

            // Add selected to list


            // Now move or trash
            $('.mass-operation').click(function() {
                $('#dialog-mass-operation').removeClass('hidden');

                // Define
                var item_id,
                    sel_items_txt = '<ul>',
                    testToShow = '';

                // Init
                selectedAction = $(this).data('action');
                selectedItems = '';

                // Selected items
                $('.mass_op_cb:checkbox:checked').each(function() {
                    item_id = $(this).data('id');
                    selectedItems += item_id + ';';
                    sel_items_txt += '<li>' + $('#item_label-' + item_id).text() + '</li>';
                });
                sel_items_txt += '</ul>';

                if (selectedAction === 'move') {
                    // destination folder
                    var folders = '';
                    $.each(store.get('teampassApplication').foldersList, function(index, item) {
                        if (item.disabled === 0) {
                            folders += '<option value="' + item.id + '">' + item.title +
                                '   [' +
                                (item.path === '' ? '<?php echo $lang->get('root'); ?>' : item.path) +
                                ']</option>';
                        }
                    });

                    htmlFolders = '<div><?php echo $lang->get('import_keepass_to_folder'); ?>:&nbsp;&nbsp;' +
                        '<select class="form-control form-item-control select2" style="width:100%;" id="mass_move_destination_folder_id">' + folders + '</select>' +
                        '</div>';

                    //display to user
                    $('#dialog-mass-operation-html').html(
                        '<?php echo $lang->get('you_decided_to_move_items'); ?>: ' +
                        '<div><ul>' + sel_items_txt + '</ul></div>' + htmlFolders +
                        '<div class="mt-3 alert alert-info"><i class="fas fa-warning fa-lg mr-2"></i><?php echo $lang->get('confirm_item_move'); ?></div>'
                    );

                } else if (selectedAction === 'delete') {
                    $('#dialog-mass-operation-html').html(
                        '<?php echo $lang->get('you_decided_to_delete_items'); ?>: ' +
                        '<div><ul>' + sel_items_txt + '</ul></div>' +
                        '<div class="mt-3 alert alert-danger"><i class="fas fa-warning fa-lg mr-2"></i><?php echo $lang->get('confirm_deletion'); ?></div>'
                    );
                }
            });

        } else {
            $('#dialog-mass-operation').addClass('hidden');

            $('#search-select')
                .removeClass('menuset')
                .html('&nbsp;');

            $('#dialog-mass-operation-html').html('');
        }
    });


    // Perform action expected by user
    $('#dialog-mass-operation-button').click(function() {
        if (selectedItems === "") {
            toastr.remove();
            toastr.warning(
                '<?php echo $lang->get('none_selected_text'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show to user
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if (selectedAction === 'delete') {
            // Delete selected items
            // prepare data
            var data = {
                'item_ids': selectedItems,
            };

            // Launch query
            $.post(
                'sources/items.queries.php', {
                    type: 'mass_delete_items',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    console.info(data);

                    //check if format error
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    } else {
                        //reload search
                        oTable.ajax.reload();

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Finalize template
                        $('#dialog-mass-operation').addClass('hidden');
                        $('#search-select')
                            .removeClass('menuset')
                            .html('&nbsp;');
                        $('#dialog-mass-operation-html').html('');
                    }
                }
            );
        } else if (selectedAction === 'move') {
            // prepare data
            var data = {
                'item_ids': selectedItems,
                'folder_id': $('#mass_move_destination_folder_id').val(),
            };

            // Launch query
            $.post(
                'sources/items.queries.php', {
                    type: 'mass_move_items',
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    console.info(data);

                    //check if format error
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    } else {
                        //reload search
                        oTable.ajax.reload();

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Finalize template
                        $('#dialog-mass-operation').addClass('hidden');
                        $('#search-select')
                            .removeClass('menuset')
                            .html('&nbsp;');
                        $('#dialog-mass-operation-html').html('');
                    }
                }
            );
        }
    });




    function unCryptData1(data) {
        if (data.substr(0, 7) === 'crypted') {
            return prepareExchangedData(
                data.substr(7),
                'decode',
                '<?php echo $session->get('key'); ?>'
            )
        }
        return false;
    }
    /**
     */
    function itemLog(logCase, itemId, itemLabel) {
        itemId = itemId || $('#id_item').val();

        var data = {
            "id": itemId,
            "label": DOMPurify.sanitize(itemLabel),
            "user_id": "<?php echo $session->get('user-id'); ?>",
            "action": logCase,
            "login": "<?php echo $session->get('user-login'); ?>"
        };

        $.post(
            "sources/items.logs.php", {
                type: "log_action_on_item",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            }
        );
    }
</script>
