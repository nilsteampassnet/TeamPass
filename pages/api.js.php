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
 * @file      api.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('api') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $('[data-mask]').inputmask();

    /**
     * TOGGLE API STATUS (ENABLED/DISABLED)
     */
    $(document).on('click', '.api-clickme-action', function() {
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // prepare data
        var data = {
            'increment_id': $(this).data('increment-id'),
            'field': $(this).data('field'),
            'value': $(this).hasClass('fa-toggle-off') === true ? 1 : 0,
        },
        selectedIcon = $(this);

        $.post(
            'sources/admin.queries.php', {
                type: 'save_user_change',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // CHange icon format
                    if (selectedIcon.hasClass('fa-toggle-off') === true) {
                        selectedIcon
                            .removeClass('fa-toggle-off text-danger')
                            .addClass('fa-toggle-on text-info')
                            .prop('data-user-auth-type', 'ldap');
                    } else {
                        selectedIcon
                            .removeClass('fa-toggle-on text-info')
                            .addClass('fa-toggle-off')
                            .prop('data-user-auth-type', 'local');
                    }

                    $('.infotip').tooltip();

                    // Inform user
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

    /**
     * Adding a new KEY
     */
    $(document).on('click', '#button-new-api-key', function() {
        // IS form field?
        if ($('#new_api_key_label') === '') {
            return false;
        }
        
        // Sanitize text fields
        purifyRes = fieldDomPurifierLoop('#new_api_key_label');
        if (purifyRes.purifyStop === true) {
            // if purify failed, stop
            return false;
        }

        // Prepare data
        var data = {
            'label': purifyRes.arrFields['label'], //$('#new_api_key_label').val(),
            'action': 'add',
        }

        $('#table-api-keys').removeClass('hidden');
        $('#api-no-keys').addClass('hidden');

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_key',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    $('#table-api-keys')
                        .append(
                            '<tr data-id("' + data.keyId + '")>' +
                            '<td width="50px"><i class="fas fa-trash infotip pointer" title="<?php echo $lang->get('del_button'); ?>"></i></td>' +
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
                '<i class="fas fa-minus-square fa-lg warning mr-2"></i><?php echo $lang->get('please_confirm'); ?>',
                '<?php echo $lang->get('please_confirm_deletion'); ?>',
                '<?php echo $lang->get('confirm'); ?>',
                '<?php echo $lang->get('cancel'); ?>'
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });
            $(document).on('click', '#warningModalButtonAction', function() {
                // SHow user
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'id': keyId,
                    'action': 'delete',
                }

                // Launch action
                $.post(
                    'sources/admin.queries.php', {
                        type: 'admin_action_api_save_key',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.warning(
                                '<?php echo $lang->get('none_selected_text'); ?>',
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
            '<button class="btn btn-default" id="new-api-key-save"><i class="fas fa-save"></i></button>' +
            '<button class="btn btn-default ml-2" id="new-api-key-cancel"><i class="fas fa-times"></i></button>'
        );
        $('.new-api-key').val(oldLabel);
    });

    $(document).on('click', '#new-api-key-save', function() {
        var keyId = $(this).closest('tr').data('id'),
            label = simplePurifier($(this).prev('input').val()),
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
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    $(cell).html('<span class="edit-api-key pointer">' + oldLabel + '</span>');
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('none_selected_text'); ?>',
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
                '<?php echo $lang->get('fields_with_mandatory_information_are_missing'); ?>',
                '', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Sanitize text fields
        purifyRes = fieldDomPurifierLoop('#new-api-ip .purify');
        if (purifyRes.purifyStop === true) {
            // if purify failed, stop
            return false;
        }

        // Prepare data
        var data = {
            'label': purifyRes.arrFields['label'],
            'ip': $('#new_api_ip_value').val(),
            'action': 'add',
        }

        $('#table-api-ips').removeClass('hidden');
        $('#api-no-ips').addClass('hidden');

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_ip',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    $('#table-api-ips')
                        .append(
                            '<tr data-id="' + data.ipId + '">' +
                            '<td width="50px"><i class="fas fa-trash infotip pointer" title="<?php echo $lang->get('del_button'); ?>"></i></td>' +
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
                '<i class="fas fa-minus-square fa-lg warning mr-2"></i><?php echo $lang->get('please_confirm'); ?>',
                '<?php echo $lang->get('please_confirm_deletion'); ?>',
                '<?php echo $lang->get('confirm'); ?>',
                '<?php echo $lang->get('cancel'); ?>'
            );

            // Actions on modal buttons
            $(document).on('click', '#warningModalButtonClose', function() {
                // Nothing
            });
            $(document).on('click', '#warningModalButtonAction', function() {
                // SHow user
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'id': ipId,
                    'action': 'delete',
                }

                // Launch action
                $.post(
                    'sources/admin.queries.php', {
                        type: 'admin_action_api_save_ip',
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.warning(
                                '<?php echo $lang->get('none_selected_text'); ?>',
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
            '<button class="btn btn-default" id="new-api-ip-save"><i class="fas fa-save"></i></button>' +
            '<button class="btn btn-default ml-2" id="new-api-ip-cancel"><i class="fas fa-times"></i></button>'
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
            'value': simplePurifier(label),
            'field': field,
            'action': 'update',
        }

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_api_save_ip',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    $(cell).html('<span class="edit-api-ip pointer">' + oldLabel + '</span>');
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('none_selected_text'); ?>',
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
