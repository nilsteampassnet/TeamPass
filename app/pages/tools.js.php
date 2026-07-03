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
 * @file      tools.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request AS SymfonyRequest;
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
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('tools') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[
    /**
     * TOP MENU BUTTONS ACTIONS
     */
    $(document).on('click', '.tp-action', function() {
        console.log($(this).data('action'))
        if ($(this).data('action') === 'fix_pf_items_but') {
            // check if possible
            if ($('#fix_pf_items_user_id').data('psk') !== 1) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#fix_pf_items_user_id').data('pf') === '') {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // continue
            $('#fix_pf_items_results').html("");
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var data = {
                'userId': $('#fix_pf_items_user_id').val(),
            }

            $.post(
                "sources/tools.queries.php", {
                    type: "perform_fix_pf_items-step1",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(dataStep1) {
                    dataStep1 = prepareExchangedData(dataStep1, 'decode', '<?php echo $session->get('key'); ?>');
                    console.log(dataStep1);

                    if (dataStep1.error === true) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            dataStep1.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                //timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#fix_pf_items_results').html(dataStep1.message + dataStep1.personalFolders);

                        // Launch step 2
                        var data = {
                            'userId': $('#fix_pf_items_user_id').val(),
                            'personalFolders': JSON.parse(dataStep1.personalFolders).join(","),
                        }

                        $.post(
                            "sources/tools.queries.php", {
                                type: "perform_fix_pf_items-step2",
                                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                key: "<?php echo $session->get('key'); ?>"
                            },
                            function(dataStep2) {
                                dataStep2 = prepareExchangedData(dataStep2, 'decode', '<?php echo $session->get('key'); ?>');
                                console.log(dataStep2);

                                if (dataStep2.error === true) {
                                    // Show error
                                    toastr.remove();
                                    toastr.error(
                                        dataStep2.message,
                                        '<?php echo $lang->get('caution'); ?>', {
                                            //timeOut: 5000,
                                            progressBar: true
                                        }
                                    );
                                } else {
                                    $('#fix_pf_items_results').append(dataStep2.message + dataStep1.personalFolders);

                                    // Launch step 3
                                    var data = {
                                        'userId': $('#fix_pf_items_user_id').val(),
                                        'personalFolders': JSON.parse(dataStep1.personalFolders).join(","),
                                    }

                                    $.post(
                                        "sources/tools.queries.php", {
                                            type: "perform_fix_pf_items-step3",
                                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                            key: "<?php echo $session->get('key'); ?>"
                                        },
                                        function(dataStep3) {
                                            dataStep3 = prepareExchangedData(dataStep3, 'decode', '<?php echo $session->get('key'); ?>');
                                            console.log(dataStep3);

                                            if (dataStep3.error === true) {
                                                // Show error
                                                toastr.remove();
                                                toastr.error(
                                                    dataStep3.message,
                                                    '<?php echo $lang->get('caution'); ?>', {
                                                        //timeOut: 5000,
                                                        progressBar: true
                                                    }
                                                );
                                            } else {
                                                $('#fix_pf_items_results').append(dataStep3.message + dataStep1.personalFolders);

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
                                }
                            }
                        );
                    }
                }
            );
            // ---
            // END
            // ---
        }

        else if ($(this).data('action') === 'fix_items_master_keys_but') {
            // check if possible
            if ($('#fix_items_master_keys_user_id').val() === 0) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>1',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#fix_items_master_keys_pwd').val() === '') {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>1',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // continue
            $(this).prop('disabled', true);            
            
            // Ask confirmation to the user through a checkbox and button
            $('#fix_items_master_keys_results').html(
                '<div class="alert alert-warning" role="alert">'+
                    '<i class="fas fa-exclamation-triangle"></i> '+
                    'This operation will encrypt all items master keys using the ones from the selected user. '+
                    'This operation is irreversible. '+
                    '<br>Please confirm by checking the box below and clicking on the button.'+
                    '<div class="form-check mt-2">'+
                        '<input class="form-check-input" type="checkbox" value="" id="restore_items_master_keys_confirm">'+
                        '<label class="form-check-label" for="restore_items_master_keys_confirm">'+
                            'I confirm the operation and I have a backup of table <code>teampass_sharekeys_items</code>.'+
                        '</label>'+
                    '</div>'+
                    '<button type="button" class="btn btn-danger mt-2 btn-sm tp-action" id="fix_items_master_keys_confirm_but" data-action="fix_items_master_keys_confirm_but">'+
                        'Confirm'+
                    '</button>'+
                    '<button type="button" class="btn btn-secundary mt-2 ml-2 btn-sm tp-action" id="fix_items_master_keys_cancel_but" data-action="fix_items_master_keys_cancel_but">'+
                        'Cancel'+
                    '</button>'+
                '</div>'
            );
        }

        // Fix items shared keys -> CANCEL
        else if ($(this).data('action') === 'fix_items_master_keys_cancel_but') {
            //
            $('#fix_items_master_keys_results').html("");
            $('#fix_items_master_keys_but').prop('disabled', false);
        }

        // Fix items shared keys -> GO
        else if ($(this).data('action') === 'fix_items_master_keys_confirm_but') {
            // check if possible
            if ($('#fix_items_master_keys_user_id').val() === 0) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>1',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#fix_items_master_keys_pwd').val() === '') {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>1',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            $('#fix_items_master_keys_results').html("");
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var data = {}

            $.post(
                "sources/tools.queries.php", {
                    type: "perform_fix_items_master_keys-step1",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(dataStep1) {
                    dataStep1 = prepareExchangedData(dataStep1, 'decode', '<?php echo $session->get('key'); ?>');
                    console.log(dataStep1);

                    $('#fix_items_master_keys_results').html(dataStep1.message);

                    if (dataStep1.error === true) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            dataStep1.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        $('#fix_items_master_keys_but').prop('disabled', false);
                    } else {
                        $('#fix_items_master_keys_results').html('Step 1:<br>'+dataStep1.message+'<br>Public key is available');

                        // Launch step 2
                        // CHecking                        
                        var data = {
                            'userId': $('#fix_items_master_keys_user_id').val(),
                            'userPassword': $('#fix_items_master_keys_pwd').val(),
                        }
                        console.log(data);
                        $.post(
                            "sources/tools.queries.php", {
                                type: "perform_fix_items_master_keys-step2",
                                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                key: "<?php echo $session->get('key'); ?>"
                            },
                            function(dataStep2) {
                                dataStep2 = prepareExchangedData(dataStep2, 'decode', '<?php echo $session->get('key'); ?>');
                                console.log('-- STEP2 RESULTS --');
                                console.log(dataStep2);

                                $('#fix_items_master_keys_results').append('<br><br>Step 2:<br>'+dataStep2.message);

                                if (dataStep2.error === true) {
                                    // Show error
                                    toastr.remove();
                                    toastr.error(
                                        dataStep2.message,
                                        '<?php echo $lang->get('caution'); ?>', {
                                            progressBar: true
                                        }
                                    );
                                    $('#fix_items_master_keys_but').prop('disabled', false);
                                } else {
                                    //$('#fix_items_master_keys_results').append(dataStep2.message);
                                    // Recursievely decrypt all items
                                    function fetchData(startIndex, limit, operationCode, dataStep1, dataStep2) {
                                        var data = {
                                            'userId': $('#fix_items_master_keys_user_id').val(),
                                            'tp_user_publicKey': dataStep1.tp_user_publicKey,
                                            'selected_user_privateKey': dataStep2.selected_user_privateKey,
                                            'nbItems': dataStep2.nb_items_to_proceed,
                                            'startIndex': startIndex,
                                            'limit': limit,
                                            'operationCode': operationCode,
                                        }
                                        console.log(data);
                                        $.post(
                                            "sources/tools.queries.php", {
                                                type: "perform_fix_items_master_keys-step3",
                                                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                                key: "<?php echo $session->get('key'); ?>"
                                            },
                                            function(dataStep3) {
                                                dataStep3 = prepareExchangedData(dataStep3, 'decode', '<?php echo $session->get('key'); ?>');
                                                console.log(dataStep3);

                                                if (dataStep2.error === true) {
                                                    $('#fix_items_master_keys_results').append(dataStep3.message);
                                                    // Show error
                                                    toastr.remove();
                                                    toastr.error(
                                                        dataStep3.message,
                                                        '<?php echo $lang->get('caution'); ?>', {
                                                            //timeOut: 5000,
                                                            progressBar: true
                                                        }
                                                    );
                                                    $('#fix_items_master_keys_but').prop('disabled', false);
                                                } else { 
                                                    updateProgressBar(dataStep3.nextIndex, dataStep2.nb_items_to_proceed); // Update progress bar
                                                    if (dataStep3.status === 'continue') {
                                                        fetchData(
                                                            dataStep3.nextIndex,
                                                            limit,
                                                            dataStep3.operationCode,
                                                            dataStep1,
                                                            dataStep2
                                                        );  // Rappelle la fonction avec le nouvel index
                                                    } else {
                                                        $('#fix_items_master_keys-progress').remove();
                                                        //$('#fix_items_master_keys-progressbar').remove();
                                                        $('#fix_items_master_keys_results').append('Items master key have been encrypted.');
                                                        $('#fix_items_master_keys_but').prop('disabled', false);

                                                        toastr.remove();
                                                        toastr.success(
                                                            '',
                                                            'Done', {
                                                                timeOut: 5000,
                                                                progressBar: true
                                                            }
                                                        );
                                                    }
                                                }
                                            }
                                        );
                                    }

                                    function updateProgressBar(offset, totalSize) {
                                        // Show progress to user
                                        var percentage = Math.round((offset / totalSize) * 100);
                                        //$('#fix_items_master_keys-progress-text').text(percentage);
                                        //$('#fix_items_master_keys-progress-text2').text('('+offset+' / '+totalSize+')');
                                        $('#fix_items_master_keys-progressbar-value').css('width', percentage+'%').text(percentage+'%');
                                    }

                                    $('#fix_items_master_keys_results').append(                                        
                                        '<br><br>Step 3:<br>'+
                                        '<div class="alert alert-info ml-2 mt-1 mr-2" id="fix_items_master_keys-progress">'+
                                            '<i class="mr-2 fa-solid fa-rocket fa-beat"></i>Encryption process performed at'+ // <b><span id="fix_items_master_keys-progress-text">0</span>%</b>'+
                                            //'<span class="ml-3" id="fix_items_master_keys-progress-text2">(0 / '+dataStep2.nb_items_to_proceed+')</span>'+
                                            '<div class="progress mt-3" id="fix_items_master_keys-progressbar">'+
                                                '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"  style="width: 0%" id="fix_items_master_keys-progressbar-value">0%</div>'+                                            
                                            '</div>'+
                                        '</div>'
                                    );
                                    // Exemple d'appel initial
                                    fetchData(0, 50, '', dataStep1, dataStep2);
                                }
                            }
                        );
                    }
                }
            );
        } 

        
        /**
         * Restore backup
         */
        else if ($(this).data('action') === 'restore_items_master_keys_but') {
            // check if possible
            if ($('#restore_items_master_keys_id').val() === 0) {
                toastr.remove();
                toastr.error(
                    'You need to select a backup file',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            $(this).prop('disabled', true);
            $('#restore_items_master_keys_id').prop('disabled', true);
            
            // Ask confirmation to the user through a checkbox and button
            $('#restore_items_master_keys_results').html(
                '<div class="alert alert-warning" role="alert">'+
                    '<i class="fas fa-exclamation-triangle"></i> '+
                    'This operation will restore all items master keys from the backup file. '+
                    'This operation is irreversible. '+
                    '<br>Please confirm by checking the box below and clicking on the button.'+
                    '<div class="form-check mt-2">'+
                        '<input class="form-check-input" type="checkbox" value="" id="restore_items_master_keys_confirm">'+
                        '<label class="form-check-label" for="restore_items_master_keys_confirm">'+
                            'I confirm the operation and I understand that it is irreversible'+
                        '</label>'+
                    '</div>'+
                    '<button type="button" class="btn btn-danger mt-2 btn-sm tp-action" id="restore_items_master_keys_confirm_but" data-action="restore_items_master_keys_confirm_but">'+
                        'Confirm'+
                    '</button>'+
                    '<button type="button" class="btn btn-secundary mt-2 ml-2 btn-sm tp-action" id="restore_items_master_keys_cancel_but" data-action="restore_items_master_keys_cancel_but">'+
                        'Cancel'+
                    '</button>'+
                '</div>'
            );
        }
        
        /**
         * Restore backup -> CANCEL
         */
        else if ($(this).data('action') === 'restore_items_master_keys_cancel_but') {
            //
            $('#restore_items_master_keys_results').html("");
            $('#restore_items_master_keys_id').prop('disabled', false);
            $('#restore_items_master_keys_but').prop('disabled', false);
        }
        
        /**
         * Restore backup -> GO
         */
        else if ($(this).data('action') === 'restore_items_master_keys_confirm_but') {
            // check if possible
            if ($('#restore_items_master_keys_id').val() === 0) {
                toastr.remove();
                toastr.error(
                    'You need to select a backup file',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            $('#restore_items_master_keys_results').html("");
            $('#restore_items_master_keys_id').prop('disabled', true);

            // continue
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var data = {
                'operationCode': $('#restore_items_master_keys_id').val(),
            }

            $.post(
                "sources/tools.queries.php", {
                    type: "restore_items_master_keys_from_backup",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(dataStep1) {
                    dataStep1 = prepareExchangedData(dataStep1, 'decode', '<?php echo $session->get('key'); ?>');
                    console.log(dataStep1);

                    $('#restore_items_master_keys_results').html(dataStep1.message);
                    $('#restore_items_master_keys_but').prop('disabled', false);

                    if (dataStep1.error === true) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            dataStep1.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                //timeOut: 5000,
                                progressBar: true
                            }
                        );
                        $(this).prop('disabled', false);
                    } else {
                        $('#restore_items_master_keys_id')
                            .find(":selected").remove()
                            .val(0);

                        // Show user                        
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

                
        /**
         * Delete backup
         */
        else if ($(this).data('action') === 'delete_restore_backup_but') {
            // check if possible
            if ($('#restore_items_master_keys_id').val() === 0) {
                toastr.remove();
                toastr.error(
                    'You need to select a backup file',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Confirm action
            if (confirm('Are you sure you want to delete this backup file?')) {
                // continue
                $(this).prop('disabled', true);
                $('#restore_items_master_keys_results').html("");
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                var data = {
                    'operationCode': $('#restore_items_master_keys_id').val(),
                }

                $.post(
                    "sources/tools.queries.php", {
                        type: "perform_delete_restore_backup",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(dataStep) {
                        dataStep = prepareExchangedData(dataStep, 'decode', '<?php echo $session->get('key'); ?>');

                        $('#delete_restore_backup_but').prop('disabled', false);

                        if (dataStep.error === true) {
                            // Show error
                            toastr.remove();
                            toastr.error(
                                dataStep.message,
                                '<?php echo $lang->get('caution'); ?>', {
                                    //timeOut: 5000,
                                    progressBar: true
                                }
                            );
                            $(this).prop('disabled', false);
                        } else {
                            $('#restore_items_master_keys_id')
                                .find(":selected").remove()
                                .val(0);

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                dataStep.message,
                                'Done', {
                                    timeOut: 10000
                                }
                            );
                        }
                    }
                );
            }
        }

        /**
         * Restore missing sharekeys -> ANALYZE (dry-run)
         */
        else if ($(this).data('action') === 'restore_missing_sharekeys_analyze_but') {
            $(this).prop('disabled', true);
            $('#restore_missing_sharekeys_repair_but').prop('disabled', true);
            $('#restore_missing_sharekeys_results').html('');
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            $.post(
                'sources/tools.queries.php', {
                    type: 'restore_missing_sharekeys-analyze',
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    $('#restore_missing_sharekeys_analyze_but').prop('disabled', false);
                    toastr.remove();

                    if (data.error === true) {
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                progressBar: true
                            }
                        );
                        return;
                    }

                    var totalMissing = 0,
                        totalSeedable = 0,
                        totalTpMissing = 0,
                        totalUnrecoverable = 0;
                    var html = '<table class="table table-sm table-bordered"><thead><tr>' +
                        '<th></th>' +
                        '<th><?php echo $lang->get('restore_missing_sharekeys_objects'); ?></th>' +
                        '<th><?php echo $lang->get('restore_missing_sharekeys_missing_pairs'); ?></th>' +
                        '<th><?php echo $lang->get('restore_missing_sharekeys_tp_missing'); ?></th>' +
                        '<th><?php echo $lang->get('restore_missing_sharekeys_unrecoverable'); ?></th>' +
                        '</tr></thead><tbody>';
                    $.each(data.analysis, function(scope, row) {
                        totalMissing += row.missing_pairs;
                        totalSeedable += row.admin_seedable;
                        totalTpMissing += row.tp_missing;
                        totalUnrecoverable += row.unrecoverable;
                        html += '<tr><td>' + scope + '</td>' +
                            '<td>' + row.objects + '</td>' +
                            '<td>' + row.missing_pairs + '</td>' +
                            '<td>' + row.tp_missing + '</td>' +
                            '<td>' + row.unrecoverable + '</td></tr>';
                    });
                    html += '</tbody></table>';

                    if (totalMissing === 0 && totalSeedable === 0) {
                        html += '<div class="alert alert-success"><i class="fas fa-check mr-2"></i><?php echo $lang->get('restore_missing_sharekeys_no_missing'); ?></div>';
                    } else {
                        html += '<div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i><?php echo $lang->get('restore_missing_sharekeys_found'); ?></div>';
                        $('#restore_missing_sharekeys_repair_but').prop('disabled', false);
                    }
                    if (totalUnrecoverable > 0) {
                        html += '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $lang->get('restore_missing_sharekeys_unrecoverable_tip'); ?></div>';
                    }
                    if (totalTpMissing > 0) {
                        html += '<button type="button" class="btn btn-secondary btn-sm tp-action" id="restore_missing_sharekeys_details_but" data-action="restore_missing_sharekeys_details_but">' +
                            '<i class="fas fa-list mr-2"></i><?php echo $lang->get('restore_missing_sharekeys_details'); ?></button>' +
                            '<div id="restore_missing_sharekeys_details"></div>';
                    }
                    $('#restore_missing_sharekeys_results').html(html);
                }
            );
        }

        /**
         * Restore missing sharekeys -> DETAILS
         * Lists the objects without TP_USER reference key and the users
         * still holding a valid sharekey for each of them.
         */
        else if ($(this).data('action') === 'restore_missing_sharekeys_details_but') {
            $(this).prop('disabled', true);
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            $.post(
                'sources/tools.queries.php', {
                    type: 'restore_missing_sharekeys-details',
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(ret) {
                    ret = prepareExchangedData(ret, 'decode', '<?php echo $session->get('key'); ?>');
                    $('#restore_missing_sharekeys_details_but').prop('disabled', false);
                    toastr.remove();

                    if (ret.error === true) {
                        toastr.error(
                            ret.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                progressBar: true
                            }
                        );
                        return;
                    }

                    var truncated = false;
                    var html = '<table class="table table-sm table-bordered mt-2"><thead><tr>' +
                        '<th><?php echo $lang->get('type'); ?></th>' +
                        '<th>ID</th>' +
                        '<th><?php echo $lang->get('label'); ?></th>' +
                        '<th><?php echo $lang->get('restore_missing_sharekeys_key_holders'); ?></th>' +
                        '</tr></thead><tbody>';
                    $.each(ret.details, function(scope, block) {
                        if (block.total > block.objects.length) {
                            truncated = true;
                        }
                        $.each(block.objects, function(index, obj) {
                            var label = restoreSharekeysEscape(obj.label) +
                                (obj.extra !== '' ? ' <span class="text-muted">(' + restoreSharekeysEscape(obj.extra) + ')</span>' : '');
                            var holders = obj.key_holders_count > 0 ?
                                restoreSharekeysEscape(obj.key_holders) + ' <span class="badge badge-secondary ml-1">' + obj.key_holders_count + '</span>' :
                                '<span class="badge badge-danger"><?php echo $lang->get('restore_missing_sharekeys_no_holder'); ?></span>';
                            html += '<tr><td>' + scope + '</td>' +
                                '<td>' + obj.id + '</td>' +
                                '<td>' + label + '</td>' +
                                '<td>' + holders + '</td></tr>';
                        });
                    });
                    html += '</tbody></table>';
                    if (truncated === true) {
                        html += '<div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i><?php echo $lang->get('restore_missing_sharekeys_details_limit'); ?></div>';
                    }
                    $('#restore_missing_sharekeys_details').html(html);
                }
            );
        }

        /**
         * Restore missing sharekeys -> REPAIR
         * 1) seed missing TP_USER reference keys from the admin session (batched)
         * 2) launch the background task distributing keys to all users
         */
        else if ($(this).data('action') === 'restore_missing_sharekeys_repair_but') {
            $(this).prop('disabled', true);
            $('#restore_missing_sharekeys_analyze_but').prop('disabled', true);
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            restoreSharekeysSeedScope(['items', 'fields', 'files'], 0);
        }
    });

    /**
     * Escape a value for safe insertion in HTML.
     */
    function restoreSharekeysEscape(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    /**
     * Seed the missing TP_USER reference keys, one scope at a time, then
     * launch the background repair task.
     */
    function restoreSharekeysSeedScope(scopes, lastId) {
        if (scopes.length === 0) {
            restoreSharekeysLaunchTask();
            return;
        }

        var data = {
            'scope': scopes[0],
            'lastId': lastId
        };

        $.post(
            'sources/tools.queries.php', {
                type: 'restore_missing_sharekeys-seed',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(ret) {
                ret = prepareExchangedData(ret, 'decode', '<?php echo $session->get('key'); ?>');

                if (ret.error === true) {
                    toastr.remove();
                    toastr.error(
                        ret.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            progressBar: true
                        }
                    );
                    $('#restore_missing_sharekeys_analyze_but').prop('disabled', false);
                    return;
                }

                if (ret.finished === true) {
                    restoreSharekeysSeedScope(scopes.slice(1), 0);
                } else {
                    restoreSharekeysSeedScope(scopes, ret.lastId);
                }
            }
        );
    }

    /**
     * Launch the background task that recreates the missing sharekeys.
     */
    function restoreSharekeysLaunchTask() {
        $.post(
            'sources/tools.queries.php', {
                type: 'restore_missing_sharekeys-launch',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(ret) {
                ret = prepareExchangedData(ret, 'decode', '<?php echo $session->get('key'); ?>');
                toastr.remove();
                $('#restore_missing_sharekeys_analyze_but').prop('disabled', false);

                if (ret.error === true) {
                    toastr.error(
                        ret.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            progressBar: true
                        }
                    );
                    return;
                }

                $('#restore_missing_sharekeys_results').append(
                    '<div class="alert alert-success"><i class="fas fa-check mr-2"></i>' + ret.message + '</div>'
                );
                toastr.success(
                    '<?php echo $lang->get('done'); ?>',
                    '', {
                        timeOut: 5000
                    }
                );
            }
        );
    }

    /**
     * On page loaded
     */
    $(function() {
        // Click on log in button with Azure Entra
        $('#but_perform_setup').click(function() {
            if (debugJavascript === true) {
                console.log('User starts setup with Azure');
            }
            document.location.href="sources/oauth.php";
            return false;
        });
    });

    //]]>
</script>
