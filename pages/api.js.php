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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'api', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
//<![CDATA[

$(document).on('click', '#button-new-api-key', function() {
    
    alertify.prompt(
        '<?php echo langHdl('adding_new_api_key'); ?>',
        '<?php echo langHdl('label'); ?>',
        '',
        function(evt, value) {
            if (value === '') {
                return false;
            }

            // Prepare data
            var data = {
                'label'     : value,
                'action'    : 'add',
            }

            // Launch action
            $.post(
                'sources/admin.queries.php',
                {
                    type    : 'admin_action_api_save_key',
                    data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key     : '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                    if (data.error === true) {
                        // ERROR
                        alertify
                            .error(
                                '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                                0
                            )
                            .dismissOthers();
                    } else {
                        $('#table-api-keys')
                            .append(
                                '<tr data-id("' + data.keyId + '")>' +                              
                                '<td width="50px"><i class="fas fa-trash infotip pointer" title="<?php echo langHdl('del_button'); ?>"></i></td>' +
                                '<td><span class="edit-api-key">' + value + '</span></td>' +
                                '<td>' + data.key + '</td>' +
                                '</tr>'
                            );
                    }
                }
            );
        },
        function() {
            alertify.message('<?php echo langHdl('cancelled'); ?>', 2)
        }
    );
});

$(document).on('click', '.delete-api-key', function() {
    var row = $(this).closest('tr'),
        keyId = row.data('id');

    if (keyId !== '') {
        // Confirm
        alertify
            .confirm(
                '<?php echo langHdl('warning'); ?>',
                '<?php echo langHdl('please_confirm_deletion'); ?>',
                function() {
                    // Prepare data
                    var data = {
                        'id'     : keyId,
                        'action'    : 'delete',
                    }

                    // Launch action
                    $.post(
                        'sources/admin.queries.php',
                        {
                            type    : 'admin_action_api_save_key',
                            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key     : '<?php echo $_SESSION['key']; ?>'
                        },
                        function(data) {
                            //decrypt data
                            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                            if (data.error === true) {
                                // ERROR
                                alertify
                                    .error(
                                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                                        0
                                    )
                                    .dismissOthers();
                            } else {
                                $(row).remove();
                            }
                        }
                    );
                },
                function() {

                }
            );
    }
});

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
        'id'     : keyId,
        'label'  : label,
        'action' : 'update',
    }

    // Launch action
    $.post(
        'sources/admin.queries.php',
        {
            type    : 'admin_action_api_save_key',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error === true) {
                $(cell).html('<span class="edit-api-key pointer">' + oldLabel + '</span>');
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
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
    
    alertify.prompt(
        '<?php echo langHdl('adding_new_api_ip'); ?>',
        '<?php echo langHdl('label'); ?>',
        '',
        function(evt, value) {
            if (value === '') {
                return false;
            }

            $('#table-api-ip').removeClass('hidden');

            // Prepare data
            var data = {
                'label'     : value,
                'action'    : 'add',
            }

            // Launch action
            $.post(
                'sources/admin.queries.php',
                {
                    type    : 'admin_action_api_save_ip',
                    data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key     : '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                    if (data.error === true) {
                        // ERROR
                        alertify
                            .error(
                                '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                                0
                            )
                            .dismissOthers();
                    } else {
                        $('#table-api-keys')
                            .append(
                                '<tr data-id("' + data.keyId + '")>' +                              
                                '<td width="50px"><i class="fas fa-trash infotip pointer" title="<?php echo langHdl('del_button'); ?>"></i></td>' +
                                '<td><span class="edit-api-key">' + value + '</span></td>' +
                                '<td>' + data.key + '</td>' +
                                '</tr>'
                            );
                    }
                }
            );
        },
        function() {
            alertify.message('<?php echo langHdl('cancelled'); ?>', 2)
        }
    );
});
    
//]]>
</script>