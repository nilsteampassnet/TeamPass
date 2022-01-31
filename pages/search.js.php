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
  * @file      search.js.php
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
            type: 'GET'
        },
        "language": {
            "url": "<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt"
        },
        "columns": [{
                "width": "10%",
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
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.info(data);
                var return_html = '';
                if (data.show_detail_option !== 0 || data.show_details === 0) {
                    //item expired
                    return_html = '<?php echo langHdl('not_allowed_to_see_pw_is_expired'); ?>';
                } else if (data.show_details === '0') {
                    //Admin cannot see Item
                    return_html = '<?php echo langHdl('not_allowed_to_see_pw'); ?>';
                } else {
                    return_html = '<td colspan="7">' +
                        '<div class="card card-info">' +
                        '<div class="card-header">' +
                        '<h5 id="item-label">' + data.label + '</h5>' +
                        '</div>' +
                        '<div class="card-body">' +
                        (data.description === '' ? '' : '<div class="form-group">' + data.description + '</div>') +
                        '<div class="form-group">' +
                        '<label class="form-group-label"><?php echo langHdl('pw'); ?>' +
                        '<button type="button" class="btn btn-gray ml-2" id="btn-copy-pwd" data-id="' + data.id + '" data-label="' + data.label + '"><i class="fas fa-copy"></i></button>' +
                        '<button type="button" class="btn btn-gray btn-show-pwd ml-2" data-id="' + data.id + '"><i class="fas fa-eye pwd-show-spinner"></i></button>' +
                        '</label>' +
                        '<span id="pwd-show_' + data.id + '" class="unhide_masked_data ml-2" style="height: 20px;"><?php echo $var['hidden_asterisk']; ?></span>' +
                        '<input id="pwd-hidden_' + data.id + '" class="pwd-clear" type="hidden" value="' + atob(data.pw) + '">' +
                        '<input type="hidden" id="pwd-is-shown_' + data.id + '" value="0">' +
                        '</div>' +
                        (data.login === '' ? '' :
                            '<div class="form-group">' +
                            '<label class="form-group-label"><?php echo langHdl('index_login'); ?>' +
                            '<button type="button" class="btn btn-gray ml-2" id="btn-copy-login" data-id="' + data.id + '"><i class="fas fa-copy"></i></button>' +
                            '</label>' +
                            '<span class="ml-2" id="login-item_' + data.id + '">' + data.login + '</span>' +
                            '</div>') +
                        (data.url === '' ? '' :
                            '<div class="form-group">' +
                            '<label class="form-group-label"><?php echo langHdl('url'); ?>' +
                            '<button type="button" class="btn btn-gray ml-2" id="btn-copy-url" data-id="' + data.id + '"><i class="fas fa-copy"></i></button>' +
                            '</label>' +
                            '<span class="ml-2" id="url-item_' + data.id + '">' + data.url + '</span>' +
                            '</div>') +
                        '</div>' +
                        '<div class="card-footer">' +
                        '<button type="button" class="btn btn-info float-right" id="cancel"><?php echo langHdl('cancel'); ?></button>' +
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

                // Manage buttons --> PASSWORD
                new ClipboardJS('#btn-copy-pwd', {
                        text: function(trigger) {
                            // Send query and get password
                            var result = '',
                                error = false;

                            // Warn user that it starts
                            toastr.remove();
                            toastr.info(
                                '<i class="fas fa-circle-notch fa-spin fa-2x"></i>'
                            );

                            $.ajax({
                                type: "POST",
                                async: false,
                                url: 'sources/items.queries.php',
                                data: 'type=show_item_password&item_id=' + $('#btn-copy-pwd').data('id') +
                                    '&key=<?php echo $_SESSION['key']; ?>',
                                dataType: "",
                                success: function(data) {
                                    //decrypt data
                                    try {
                                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                                    } catch (e) {
                                        // error
                                        toastr.remove();
                                        toastr.warning(
                                            '<?php echo langHdl('no_item_to_display'); ?>'
                                        );
                                        return false;
                                    }
                                    if (data.error === true) {
                                        error = true;
                                    } else {
                                        if (data.password_error !== '') {
                                            error = true;
                                        } else {
                                            result = atob(data.password);
                                        }
                                    }

                                    // Remove cog
                                    toastr.remove();
                                }
                            });
                            return result;
                        }
                    })
                    .on('success', function(e) {
                        itemLog(
                            'at_password_copied',
                            e.trigger.dataset.id,
                            e.trigger.dataset.label
                        );

                        // Warn user about clipboard clear
                        if (store.get('teampassSettings').clipboard_life_duration === undefined || parseInt(store.get('teampassSettings').clipboard_life_duration) === 0) {
                            toastr.remove();
                            toastr.info(
                                '<?php echo langHdl('copy_to_clipboard'); ?>',
                                '', {
                                    timeOut: 2000,
                                    positionClass: 'toast-top-right',
                                    progressBar: true
                                }
                            );
                        } else {
                            toastr.remove();
                            toastr.warning(
                                '<?php echo langHdl('clipboard_will_be_cleared'); ?>',
                                '', {
                                    timeOut: store.get('teampassSettings').clipboard_life_duration * 1000,
                                    progressBar: true
                                }
                            );

                            // Set clipboard eraser
                            clearClipboardTimeout(
                                store.get('teampassSettings').clipboard_life_duration
                            );
                        }

                        e.clearSelection();
                    });

                // Manage buttons --> LOGIN
                new ClipboardJS('#btn-copy-login', {
                        text: function(e) {
                            return $('#login-item_' + $('#btn-copy-login').data('id')).text();
                        }
                    })
                    .on('success', function(e) {
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            '', {
                                timeOut: 2000,
                                positionClass: 'toast-top-right',
                                progressBar: true
                            }
                        );
                        e.clearSelection();
                    });

                // Manage buttons --> URL
                new ClipboardJS('#btn-copy-url', {
                        text: function(e) {
                            return $('#url-item_' + $('#btn-copy-url').data('id')).text();
                        }
                    })
                    .on('success', function(e) {
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('copy_to_clipboard'); ?>',
                            '', {
                                timeOut: 2000,
                                positionClass: 'toast-top-right',
                                progressBar: true
                            }
                        );
                        e.clearSelection();
                    });

                $('#search-spinner').remove();

                $('.infotip').tooltip();
            }
        );
        return data;
    }

    // show password during longpress
    var mouseStillDown = false;
    $('#search-results-items').on('mousedown', '.unhide_masked_data', function(event) {
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

    var showPwdContinuous = function(elem_id) {
        var itemId = elem_id.split('_')[1];
        if (mouseStillDown === true) {
            // Prepare data to show
            // Is data crypted?
            var data = unCryptData($('#pwd-hidden_' + itemId).val(), '<?php echo $_SESSION['key']; ?>');

            if (data !== false) {
                $('#pwd-hidden_' + itemId).val(
                    data.password
                );
            }

            $('#pwd-show_' + itemId).html(
                '<span style="cursor:none;">' +
                $('#pwd-hidden_' + itemId).val()
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;') +
                '</span>'
            );

            setTimeout('showPwdContinuous("pwd-show_' + itemId + '")', 50);
            
            // log password is shown
            if ($("#pwd-show_" + itemId).hasClass('pwd-shown') === false) {
                itemLog(
                    'at_password_shown',
                    itemId,
                    $('#pwd-label_' + itemId).text()
                );
                $('#pwd-show_' + itemId).addClass('pwd-shown');
            }
        } else {
            $('#pwd-show_' + itemId)
                .html('<?php echo $var['hidden_asterisk']; ?>')
                .removeClass('pwd-shown');
        }
    };

    // Manage the password show button
    // including autohide after a couple of seconds
    $(document).on('click', '.btn-show-pwd', function() {
        if ($(this).hasClass('pwd-shown') === false) {
            var itemId = $(this).data('id');
            $(this).addClass('pwd-shown');
            // Prepare data to show
            // Is data crypted?
            var data = unCryptData($('#pwd-hidden_' + itemId).val(), '<?php echo $_SESSION['key']; ?>');
            if (data !== false && data !== undefined) {
                $('#pwd-hidden_' + itemId).val(
                    data.password
                );
            }

            // Change class and show spinner
            $('.pwd-show-spinner')
                .removeClass('far fa-eye')
                .addClass('fas fa-circle-notch fa-spin text-warning');


            $('#pwd-show_' + itemId)
                .html(
                    '<span style="cursor:none;">' +
                    $('#pwd-hidden_' + itemId).val()
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;') +
                    '</span>'
                );

            // log password is shown
            itemLog(
                'at_password_shown',
                itemId,
                $('#item-label').text()
            );

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
                        '<?php echo langHdl('actions'); ?>' +
                        '<i class="fas fa-share ml-2 pointer infotip mass-operation" title="<?php echo langHdl('move_items'); ?>" data-action="move"></i>' +
                        '<i class="fas fa-trash ml-2 pointer infotip mass-operation" title="<?php echo langHdl('delete_items'); ?>" data-action="delete"></i>'
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
                    console.log(store.get('teampassApplication').foldersList)
                    $.each(store.get('teampassApplication').foldersList, function(index, item) {
                        if (item.disabled === 0) {
                            folders += '<option value="' + item.id + '">' + item.title +
                                '   [' +
                                (item.path === '' ? '<?php echo langHdl('root'); ?>' : item.path) +
                                ']</option>';
                        }
                    });

                    htmlFolders = '<div><?php echo langHdl('import_keepass_to_folder'); ?>:&nbsp;&nbsp;' +
                        '<select class="form-control form-item-control select2" style="width:100%;" id="mass_move_destination_folder_id">' + folders + '</select>' +
                        '</div>';

                    //display to user
                    $('#dialog-mass-operation-html').html(
                        '<?php echo langHdl('you_decided_to_move_items'); ?>: ' +
                        '<div><ul>' + sel_items_txt + '</ul></div>' + htmlFolders +
                        '<div class="mt-3 alert alert-info"><i class="fas fa-warning fa-lg mr-2"></i><?php echo langHdl('confirm_item_move'); ?></div>'
                    );

                } else if (selectedAction === 'delete') {
                    $('#dialog-mass-operation-html').html(
                        '<?php echo langHdl('you_decided_to_delete_items'); ?>: ' +
                        '<div><ul>' + sel_items_txt + '</ul></div>' +
                        '<div class="mt-3 alert alert-danger"><i class="fas fa-warning fa-lg mr-2"></i><?php echo langHdl('confirm_deletion'); ?></div>'
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
                '<?php echo langHdl('none_selected_text'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Show to user
        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

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
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
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
                            '<?php echo langHdl('done'); ?>',
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
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
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
                            '<?php echo langHdl('done'); ?>',
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
                '<?php echo $_SESSION['key']; ?>'
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
            "label": itemLabel,
            "user_id": "<?php echo $_SESSION['user_id']; ?>",
            "action": logCase,
            "login": "<?php echo $_SESSION['login']; ?>"
        };

        $.post(
            "sources/items.logs.php", {
                type: "log_action_on_item",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            }
        );
    }
</script>
