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
 * @file      api.js.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'api', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $('[data-mask]').inputmask();

    /**
     * Adding a new KEY
     */
    $(document).on('click', '#button-new-api-key', function() {
        // IS form field?
        if ($('#new_api_key_label') === '') {
            return false;
        }

        // Prepare data
        var data = {
            'label': $('#new_api_key_label').val(),
            'action': 'add',
        }

        $('#table-api-keys').removeClass('hidden');
        $('#api-no-keys').addClass('hidden');

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_key',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    $('#table-api-keys')
                        .append(
                            '<tr data-id("' + data.keyId + '")>' +
                            '<td width="50px"><i class="fas fa-trash infotip pointer" title="<?php echo langHdl('del_button'); ?>"></i></td>' +
                            '<td><span class="edit-api-key">' + $('#new_api_key_label').val() + '</span></td>' +
                            '<td>' + data.key + '</td>' +
                            '</tr>'
                        );

                    $('#new_api_key_label').val('');
                }
            }
        );
    });

    /**
     * DELETING AN EXISTING KEY
     */
    $(document).on('click', '.delete-api-key', function() {
        var row = $(this).closest('tr'),
            keyId = row.data('id');

        if (keyId !== '') {
            // Confirm
            // Prepare modal
            showModalDialogBox(
                '#warningModal',
                '<i class="fas fa-minus-square fa-lg warning mr-2"></i><?php echo langHdl('please_confirm'); ?>',
                '<?php echo langHdl('please_confirm_deletion'); ?>',
                '<?php echo langHdl('confirm'); ?>',
                '<?php echo langHdl('cancel'); ?>'
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });
            $(document).on('click', '#warningModalButtonAction', function() {
                // SHow user
                toastr.remove();
                toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'id': keyId,
                    'action': 'delete',
                }

                // Launch action
                $.post(
                    'sources/admin.queries.php', {
                        type: 'admin_action_api_save_key',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.warning(
                                '<?php echo langHdl('none_selected_text'); ?>',
                                '', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            $(row).remove();
                            toastr.remove();
                        }
                    }
                );
            });
        }
    });

    /**
     * EDITING THE LABEL OF A KEY
     */
    var oldLabel;
    $(document).on('click', '.edit-api-key', function() {
        var cell = $(this).closest('td');
        oldLabel = $(this).html();

        $(this).remove();

        $(cell).html(
            '<input type="text" class="form-control new-api-key">' +
            '<button class="btn btn-default" id="new-api-key-save"><i class="fa fa-save"></i></button>' +
            '<button class="btn btn-default ml-2" id="new-api-key-cancel"><i class="fa fa-times"></i></button>'
        );
        $('.new-api-key').val(oldLabel);
    });

    $(document).on('click', '#new-api-key-save', function() {
        var keyId = $(this).closest('tr').data('id'),
            label = $(this).prev('input').val(),
            cell = $(this).closest('td');

        // Prepare data
        var data = {
            'id': keyId,
            'label': label,
            'action': 'update',
        }

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_key',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    $(cell).html('<span class="edit-api-key pointer">' + oldLabel + '</span>');
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    $(cell).html('<span class="edit-api-key pointer">' + label + '</span>');
                }
            }
        );

    });

    $(document).on('click', '#new-api-key-cancel', function() {

        $(this).closest('td').html('<span class="edit-api-key pointer">' + oldLabel + '</span>');

    });

    //----- WHITELIST IPS


    $(document).on('click', '#button-new-api-ip', function() {
        if ($('#new_api_ip_value').val() === '' || $('#new_api_ip_label').val() === '') {
            toastr.remove();
            toastr.warning(
                '<?php echo langHdl('fields_with_mandatory_information_are_missing'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Prepare data
        var data = {
            'label': $('#new_api_ip_label').val(),
            'ip': $('#new_api_ip_value').val(),
            'action': 'add',
        }

        $('#table-api-ips').removeClass('hidden');
        $('#api-no-ips').addClass('hidden');

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_ip',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    $('#table-api-ips')
                        .append(
                            '<tr data-id="' + data.ipId + '">' +
                            '<td width="50px"><i class="fas fa-trash infotip pointer" title="<?php echo langHdl('del_button'); ?>"></i></td>' +
                            '<td><span class="edit-api-ip pointer" data-field="label">' + $('#new_api_ip_label').val() + '</span></td>' +
                            '<td><span class="edit-api-ip pointer" data-field="value">' + $('#new_api_ip_value').val() + '</span></td>' +
                            '</tr>'
                        );

                    $('#new_api_ip_label, #new_api_ip_value').val('');
                }
            }
        );
    });

    /**
     * DELETING AN EXISTING IP
     */
    $(document).on('click', '.delete-api-ip', function() {
        var row = $(this).closest('tr'),
            ipId = row.data('id');

        if (ipId !== '') {
            // Confirm
            // Prepare modal
            showModalDialogBox(
                '#warningModal',
                '<i class="fas fa-minus-square fa-lg warning mr-2"></i><?php echo langHdl('please_confirm'); ?>',
                '<?php echo langHdl('please_confirm_deletion'); ?>',
                '<?php echo langHdl('confirm'); ?>',
                '<?php echo langHdl('cancel'); ?>'
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });
            $(document).on('click', '#warningModalButtonAction', function() {
                // SHow user
                toastr.remove();
                toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'id': ipId,
                    'action': 'delete',
                }

                // Launch action
                $.post(
                    'sources/admin.queries.php', {
                        type: 'admin_action_api_save_ip',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.warning(
                                '<?php echo langHdl('none_selected_text'); ?>',
                                '', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            $(row).remove();
                            toastr.remove();
                        }
                    }
                );
            });
        }
    });



    /**
     * EDITING THE LABEL OF AN IP
     */
    $(document).on('click', '.edit-api-ip', function() {
        var cell = $(this).closest('td'),
            field = $(this).data('field');
        oldLabel = $(this).html();

        $(this).remove();

        $(cell).html(
            '<input type="text" class="form-control new-api-ip" data-field="' + field + '">' +
            '<button class="btn btn-default" id="new-api-ip-save"><i class="fa fa-save"></i></button>' +
            '<button class="btn btn-default ml-2" id="new-api-ip-cancel"><i class="fa fa-times"></i></button>'
        );
        $('.new-api-ip').val(oldLabel);
    });

    $(document).on('click', '#new-api-ip-save', function() {
        var ipId = $(this).closest('tr').data('id'),
            label = $(this).prev('input').val(),
            field = $(this).prev('input').data('field'),
            cell = $(this).closest('td');

        // Prepare data
        var data = {
            'id': ipId,
            'value': label,
            'field': field,
            'action': 'update',
        }

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_ip',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    $(cell).html('<span class="edit-api-ip pointer">' + oldLabel + '</span>');
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    $(cell).html('<span class="edit-api-ip pointer">' + label + '</span>');
                }
            }
        );

    });

    $(document).on('click', '#new-api-ip-cancel', function() {

        $(this).closest('td').html('<span class="edit-api-ip pointer">' + oldLabel + '</span>');

    });

    //]]>
</script>
