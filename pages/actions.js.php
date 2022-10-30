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
 * @file      actions.js.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], '2fa', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    var requestRunning = false;

    // Prepare iCheck format for checkboxes
    $('input[type="radio"].form-radio-input').iCheck({
        radioClass: 'iradio_flat-blue',
    });

    $(document).on('click', '.button-action', function() {
        var askedAction = $(this).data('action'),
            option = {};

        if (askedAction === 'config-file') {
            action = 'admin_action_rebuild_config_file';
        } else if (askedAction === 'personal-folder') {
            action = 'admin_action_check_pf';
        } else if (askedAction === 'remove-orphans') {
            action = 'admin_action_db_clean_items';
        } else if (askedAction === 'optimize-db') {
            action = 'admin_action_db_optimize';
        } else if (askedAction === 'purge-files') {
            action = 'admin_action_purge_old_files';
        } else if (askedAction === 'reload-cache') {
            action = 'admin_action_reload_cache_table';
        } else if (askedAction === 'change-sk') {
            performSaltKeyChange();
            return false;
        } else if (askedAction === 'file-encryption') {
            showAttachmentsEncryptionOptions();
            return false;
        } else if (askedAction === 'launch-action-on-attachments') {
            confirmAttachmentsEncryption();
            return false;
        }


        if (requestRunning === true) {
            return false;
        }
        requestRunning = true;

        // Show cog
        toastr.remove();
        toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');


        // Store in DB   
        $.post(
            "sources/admin.queries.php", {
                type: action,
                option: prepareExchangedData(JSON.stringify(option), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                        '<?php echo langHdl('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }
                console.log(data)
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
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // Show time
                    $('#' + askedAction + "-result").html(
                        data.message
                    );
                }
                requestRunning = false;
            }
        );
    });



    function changeMainSaltKey(start, object) {
        if (object === 'files') {
            var nb = 5;
        } else {
            var nb = 10; // can be changed - number of items treated in each loop
        }

        // start change
        if (start === 'starting') {
            // inform
            $('#change-sk-progress').html('<i class="fas fa-cog fa-spin mr-2"></i><?php echo langHdl('starting'); ?>').show();

            // launch query
            $.post(
                'sources/admin.queries.php', {
                    type: 'admin_action_change_salt_key___start',
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    }
                    console.log(data);

                    if (data.error === false && data.nextAction === 'encrypt_items') {
                        $('#changeMainSaltKey_itemsCount').append('<input type="hidden" id="changeMainSaltKey_itemsCountTotal">');
                        $('#changeMainSaltKey_itemsCount, #changeMainSaltKey_itemsCountTotal').val(data.nbOfItems);
                        //console.log('Now launch encryption');

                        // start encrypting items with new saltkey
                        changeMainSaltKey(0, 'items,logs,files,categories');
                    } else {
                        // error mngt
                        $('#change-sk-progress').html('<i class="fas fa-ban fa-spin mr-2"></i>' +
                            '<?php echo langHdl('error_sent_back'); ?> : ' + data.error);
                    }
                }
            );

        } else if (isFinite(start) && object !== '') {
            console.log('Step Encrypt - ' + start + ' ; ' + nb + ' ; ' + $('#changeMainSaltKey_itemsCount').val());

            $('#change-sk-progress')
                .html('<i class="fas fa-cog fa-spin mr-2"></i><?php echo langHdl('treating_items'); ?>...&nbsp;' +
                    start + ' > ' + (parseInt(start) + parseInt(nb)) +
                    ' (<?php echo langHdl('total_number_of_items'); ?> : ' +
                    $('#changeMainSaltKey_itemsCount').val() + ')'
                );

            $.post(
                'sources/admin.queries.php', {
                    type: 'admin_action_change_salt_key___encrypt',
                    object: object,
                    start: start,
                    length: nb,
                    nbItems: $('#changeMainSaltKey_itemsCount').val(),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    }
                    console.log(data);

                    console.log('Next action: ' + data.nextAction);
                    if (data.nextAction !== 'encrypting' &&
                        data.nextAction !== '' &&
                        data.nextAction !== 'finishing'
                    ) {
                        if (data.nbOfItems !== '') {
                            // it is now a new table to be re-encrypted
                            $('#changeMainSaltKey_itemsCount').val(data.nbOfItems);
                            $('#changeMainSaltKey_itemsCountTotal')
                                .val(parseInt(data.nbOfItems) + parseInt($('#changeMainSaltKey_itemsCountTotal').val()));
                            data.nextStart = 0;
                            object = data.nextAction;
                        }
                        changeMainSaltKey(data.nextStart, object);
                    } else if (data.nextAction === 'finishing') {
                        $('#change-sk-progress').html('<?php echo langHdl('finalizing'); ?>...');
                        changeMainSaltKey('finishing');
                    } else {
                        // error mngt
                        $('#change-sk-progress').html('<i class="fas fa-alert fa-spin mr-2"></i>' +
                            '<?php echo langHdl('error_sent_back'); ?> : ' + data.error);
                    }
                }
            );

        } else {
            $.post(
                'sources/admin.queries.php', {
                    type: 'admin_action_change_salt_key___end',
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    }
                    console.log(data);

                    if (data.nextAction === 'done') {
                        $('#change-sk-progress')
                            .html('<i class="fas fa-info mr-2"></i>' +
                                '<?php echo langHdl('alert_message_done') . ' ' . langHdl('number_of_items_treated'); ?> : ' +
                                $('#changeMainSaltKey_itemsCountTotal').val() +
                                '<p><?php echo langHdl('check_data_after_reencryption'); ?><p>' +
                                '<div class="mt-2 pointer"><a href="#" onclick="encryption_show_revert()">' +
                                '<?php echo langHdl('revert'); ?></a></div>');
                    } else {
                        // error mngt
                    }
                    $('#changeMainSaltKey_itemsCountTotal').remove();
                }
            );
        }
    }

    function encryption_show_revert() {
        if (confirm('<?php echo langHdl('revert_the_database'); ?>')) {
            $('#change-sk-progress')
                .append('<div class=""><i class="fas fa-cog fa-spin mr-2"></i><?php echo langHdl('please_wait'); ?>...</div>')
            $.post(
                'sources/admin.queries.php', {
                    type: 'admin_action_change_salt_key___restore_backup',
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                            '<?php echo langHdl('error'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    }
                    console.log(data);

                    $('#change-sk-progress').html('').addClass('hidden');
                }
            );
        }
    }

    //-------------------------------------------------


    function showAttachmentsEncryptionOptions() {
        console.log(store.get('teampassSettings'))
        if (parseInt(store.get('teampassSettings').enable_attachment_encryption) === 1) {
            // Propose Decryption
            $('#attachments-decrypt').iCheck('check');
            $('#attachments-encrypt').iCheck('disable');
            $('#file-encryption-progress')
                .html('<span class="text-success"><i class="fas fa-thumbs-up mr-2"></i><?php echo langHdl('files_already_encrypted'); ?></span>')
                .removeClass('hidden');
        } else {
            // Propose Encryption
            $('#attachments-encrypt').iCheck('check');
            $('#attachments-decrypt').iCheck('disable');
            $('#file-encryption-progress')
                .html('<span class="text-warning"><i class="fas fa-thumbs-up mr-2"></i><?php echo langHdl('files_not_encrypted'); ?></span>')
                .removeClass('hidden');
        }
        $('#file-encryption-execution').removeClass('hidden');
    }



    /**
     * performAttachmentsEncryption
     * 
     */
    function performAttachmentsEncryption(list, counter) {
        $.post(
            "sources/admin.queries.php", {
                type: "admin_action_attachments_cryption_continu",
                option: $("input[name=encryption_type]:checked").val(),
                counter: counter,
                list: list,
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                        '<?php echo langHdl('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }
                console.log(data);

                if (data.continu === true) {
                    performAttachmentsEncryption(data.list, data.counter);
                } else {
                    // Update storage
                    if ($("input[name=encryption_type]:checked").val() === 'attachments-encrypt') {
                        store.update(
                            'teampassSettings',
                            function(teampassSettings) {
                                teampassSettings.enable_attachment_encryption = 1;
                            }
                        );

                        $('#attachments-decrypt').iCheck('enable').iCheck('check');
                        $('#attachments-encrypt').iCheck('disable');
                    } else {
                        store.update(
                            'teampassSettings',
                            function(teampassSettings) {
                                teampassSettings.enable_attachment_encryption = 0;
                            }
                        );

                        $('#attachments-encrypt').iCheck('disable').iCheck('check');
                        $('#attachments-decrypt').iCheck('disable');
                    }

                    $('#file-encryption-progress').html(data.message +
                        '<?php echo langHdl('number_of_modified_attachments'); ?>' + data.counter);

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
