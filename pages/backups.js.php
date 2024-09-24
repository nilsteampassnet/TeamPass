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
 * @file      backups.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('backups') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $(document).on('click', '.key-generate', function() {
        $.post(
            "sources/main.queries.php", {
                type: "generate_password",
                type_category: 'action_user',
                size: "<?php echo $SETTINGS['pwd_maximum_length']; ?>",
                lowercase: "true",
                numerals: "true",
                capitalize: "true",
                symbols: "false",
                secure: "true",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                if (data.key !== "") {
                    $('#onthefly-backup-key').val(data.key);
                }
            }
        );
    });

    $(document).on('click', '.btn-choose-file', function() {
        $('#onthefly-restore-finished, #onthefly-backup-progress')
            .addClass('hidden')
            .html('');
    });

    $(document).on('click', '.start', function() {
        var action = $(this).data('action');

        if (action === 'onthefly-backup') {
            // PERFORM ONE BACKUP
            if ($('#onthefly-backup-key').val() !== '') {
                // Show cog
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'encryptionKey': simplePurifier($('#onthefly-backup-key').val()),
                };

                //send query
                $.post(
                    "sources/backups.queries.php", {
                        type: "onthefly_backup",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                                '<?php echo $lang->get('error'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            // Store KEY in DB
                            var newData = {
                                "field": 'bck_script_passkey',
                                "value": simplePurifier($('#onthefly-backup-key').val()),
                            }

                            $.post(
                                "sources/admin.queries.php", {
                                    type: "save_option_change",
                                    data: prepareExchangedData(JSON.stringify(newData), "encode", "<?php echo $session->get('key'); ?>"),
                                    key: "<?php echo $session->get('key'); ?>"
                                },
                                function(data) {
                                    // Handle server answer
                                    try {
                                        data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                                    } catch (e) {
                                        // error
                                        toastr.remove();
                                        toastr.error(
                                            '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                                            '<?php echo $lang->get('error'); ?>', {
                                                timeOut: 5000,
                                                progressBar: true
                                            }
                                        );
                                        return false;
                                    }

                                    if (data.error === false) {
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
                            // SHOW LINK
                            $('#onthefly-backup-progress')
                                .removeClass('hidden')
                                .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                    '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                    '<h5><i class="icon fa fa-check mr-2"></i><?php echo $lang->get('done'); ?></h5>' +
                                    '<i class="fas fa-file-download mr-2"></i><a href="' + data.download + '"><?php echo $lang->get('pdf_download'); ?></a>' +
                                    '</div>');

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
        } else if (action === 'onthefly-restore') {
            // PERFORM A RESTORE
            if ($('#onthefly-restore-key').val() !== '') {
                // Show cog
                toastr.remove();
                toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                $('#onthefly-restore-progress').removeClass('hidden');

                function restoreDatabase(offset, clearFilename, totalSize) {
                    var data = {
                        encryptionKey: simplePurifier($('#onthefly-restore-key').val()),
                        backupFile: $('#onthefly-restore-file').data('operation-id'),
                        offset: offset,
                        clearFilename: clearFilename,
                        totalSize: totalSize
                    };

                    $.post(
                        "sources/backups.queries.php", 
                        {
                            type: "onthefly_restore",
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                            key: "<?php echo $session->get('key'); ?>"
                        },
                        function(response) {
                        data = decodeQueryReturn(response, '<?php echo $session->get('key'); ?>');
                        if (!data.error) {
                            if (data.newOffset !== offset) {
                                // block time counter
                                ProcessInProgress = true;
                                
                                // Continue with new offset
                                updateProgressBar(data.newOffset, data.totalSize); // Update progress
                                restoreDatabase(data.newOffset, data.clearFilename, data.totalSize);
                            } else {
                                // SHOW LINK
                                $('#onthefly-restore-finished')
                                    .removeClass('hidden')
                                    .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                        '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                        '<h5><i class="icon fa fa-check mr-2"></i><?php echo $lang->get('done'); ?></h5>' +
                                        '<?php echo $lang->get('restore_done_now_logout'); ?>' +
                                        '</div>');

                                // Clean progress info
                                $('#onthefly-restore-progress').addClass('hidden');
                                $('#onthefly-restore-progress-text').html('0');                                    

                                // Inform user
                                toastr.remove();
                                toastr.success(
                                    '<?php echo $lang->get('done'); ?>',
                                    '', {
                                        timeOut: 1000
                                    }
                                );

                                // restart time expiration counter
                                ProcessInProgress = false; 
                            }
                        } else {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                                '<?php echo $lang->get('error'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );

                            // Clean progress info
                            $('#onthefly-restore-progress').addClass('hidden');
                            $('#onthefly-restore-progress-text').html('0');

                            // restart time expiration counter
                            ProcessInProgress = false; 
                        }
                    });
                }

                function updateProgressBar(offset, totalSize) {
                    // Show progress to user
                    var percentage = Math.round((offset / totalSize) * 100);
                    //var message = '<i class="mr-2 fa-solid fa-rocket fa-beat"></i><?php echo $lang->get('restore_in_progress');?> <b>' + percentage  + '%</b>';
                    //console.log(message)
                    $('#onthefly-restore-progress-text').text(percentage);
                }

                // Start restoration
                restoreDatabase(0, '', 0);
            }
        }
    });



    // PREPARE UPLOADER with plupload
<?php
$maxFileSize = (strrpos($SETTINGS['upload_maxfilesize'], 'mb') === false)
    ? $SETTINGS['upload_maxfilesize'] . 'mb'
    : $SETTINGS['upload_maxfilesize'];
?>

    var restoreOperationId = '',
        uploader_restoreDB = new plupload.Uploader({
            runtimes: "gears,html5,flash,silverlight,browserplus",
            browse_button: "onthefly-restore-file-select",
            container: "onthefly-restore-file",
            max_file_size: "<?php echo $maxFileSize; ?>",
            chunk_size: "5mb",
            unique_names: true,
            dragdrop: true,
            multiple_queues: false,
            multi_selection: false,
            max_file_count: 1,
            url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php",
            flash_swf_url: "<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/plupload/js/plupload.flash.swf",
            silverlight_xap_url: "<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/plupload/js/plupload.silverlight.xap",
            filters: [{
                title: "SQL files",
                extensions: "sql"
            }],
            init: {
                FilesAdded: function(up, files) {
                    // generate and save token
                    $.post(
                        "sources/main.queries.php", {
                            type: "save_token",
                            type_category: 'action_system',
                            size: 25,
                            capital: true,
                            numeric: true,
                            ambiguous: true,
                            reason: "restore_db",
                            duration: 10,
                            key: '<?php echo $session->get('key'); ?>'
                        },
                        function(data) {
                            console.log(data);
                            store.update(
                                'teampassUser',
                                function(teampassUser) {
                                    teampassUser.uploadToken = data[0].token;
                                }
                            );
                            up.start();
                        },
                        "json"
                    );
                },
                BeforeUpload: function(up, file) {
                    // Show cog
                    toastr.remove();
                    toastr.info('<?php echo $lang->get('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
                    console.log("Upload token: "+store.get('teampassUser').uploadToken);

                    up.setOption('multipart_params', {
                        PHPSESSID: '<?php echo $session->get('user-id'); ?>',
                        type_upload: 'restore_db',
                        File: file.name,
                        user_token: store.get('teampassUser').uploadToken
                    });
                },
                UploadComplete: function(up, files) {
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.uploadFileObject = restoreOperationId;
                        }
                    );
                    
                    $('#onthefly-restore-file-text').text(up.files[0].name);

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                },
                Error: function(up, args) {
                    console.log("ERROR arguments:");
                    console.log(args);
                }
            }
        });

    // Uploader options
    uploader_restoreDB.bind('FileUploaded', function(upldr, file, object) {
        var myData = prepareExchangedData(object.response, "decode", "<?php echo $session->get('key'); ?>");
        $('#onthefly-restore-file').data('operation-id', myData.operation_id);
    });

    uploader_restoreDB.bind("Error", function(up, err) {
        //var myData = prepareExchangedData(err, "decode", "<?php echo $session->get('key'); ?>");
        $("#onthefly-restore-progress")
            .removeClass('hidden')
            .html('<div class="alert alert-danger alert-dismissible ml-2">' +
                '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                '<h5><i class="icon fas fa-ban mr-2"></i><?php echo $lang->get('done'); ?></h5>' +
                '' + err.message +
                '</div>');
                up.refresh(); // Reposition Flash/Silverlight
    });

    uploader_restoreDB.init();

    //]]>
</script>
