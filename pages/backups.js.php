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
 * @file      backups.js.php
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

    $(document).on('click', '.key-generate', function() {
        $.post(
            "sources/main.queries.php", {
                type: "generate_password",
                size: "<?php echo $SETTINGS['pwd_maximum_length']; ?>",
                lowercase: "true",
                numerals: "true",
                capitalize: "true",
                symbols: "false",
                secure: "true"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");

                if (data.key !== "") {
                    $('#onthefly-backup-key').val(data.key);
                }
            }
        );
    });

    $(document).on('click', '.start', function() {
        var action = $(this).data('action');

        if (action === 'onthefly-backup') {
            // PERFORM ONE BACKUP
            if ($('#onthefly-backup-key').val() !== '') {
                // Show cog
                toastr.remove();
                toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'encryptionKey': $('#onthefly-backup-key').val(),
                };

                //send query
                $.post(
                    "sources/backups.queries.php", {
                        type: "onthefly_backup",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                                '<?php echo langHdl('error'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            // Store KEY in DB
                            var newData = {
                                "field": 'bck_script_passkey',
                                "value": $('#onthefly-backup-key').val(),
                            }

                            $.post(
                                "sources/admin.queries.php", {
                                    type: "save_option_change",
                                    data: prepareExchangedData(JSON.stringify(newData), "encode", "<?php echo $_SESSION['key']; ?>"),
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

                                    if (data.error === false) {
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
                            // SHOW LINK
                            $('#onthefly-backup-progress')
                                .removeClass('hidden')
                                .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                    '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                    '<h5><i class="icon fa fa-check mr-2"></i><?php echo langHdl('done'); ?></h5>' +
                                    '<i class="fas fa-file-download mr-2"></i><a href="' + data.download + '"><?php echo langHdl('pdf_download'); ?></a>' +
                                    '</div>');

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
        } else if (action === 'onthefly-restore') {
            // PERFORM A RESTORE
            if ($('#onthefly-restore-key').val() !== '') {
                // Show cog
                toastr.remove();
                toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                // Prepare data
                var data = {
                    'encryptionKey': $('#onthefly-restore-key').val(),
                    'backupFile': $('#onthefly-restore-file').data('operation-id')
                };
                console.log(data);
                //send query
                $.post(
                    "sources/backups.queries.php", {
                        type: "onthefly_restore",
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                        key: "<?php echo $_SESSION['key']; ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                        console.log(data);

                        if (data.error === true) {
                            // ERROR
                            toastr.remove();
                            toastr.error(
                                '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                                '<?php echo langHdl('error'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        } else {
                            // SHOW LINK
                            $('#onthefly-restore-progress')
                                .removeClass('hidden')
                                .html('<div class="alert alert-success alert-dismissible ml-2">' +
                                    '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                                    '<h5><i class="icon fa fa-check mr-2"></i><?php echo langHdl('done'); ?></h5>' +
                                    '<?php echo langHdl('restore_done_now_logout'); ?>' +
                                    '</div>');

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
        }
    });



    // PREPARE UPLOADER with plupload
    var restoreOperationId = '',
        uploader_restoreDB = new plupload.Uploader({
            runtimes: "gears,html5,flash,silverlight,browserplus",
            browse_button: "onthefly-restore-file-select",
            container: "onthefly-restore-file",
            max_file_size: "10mb",
            chunk_size: "1mb",
            unique_names: true,
            dragdrop: false,
            multiple_queues: false,
            multi_selection: false,
            max_file_count: 1,
            url: "sources/upload.files.php",
            flash_swf_url: "includes/libraries/Plupload/plupload.flash.swf",
            silverlight_xap_url: "includes/libraries/Plupload/plupload.silverlight.xap",
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
                            size: 50,
                            capital: true,
                            numeric: true,
                            lowercase: true,
                            secure: false,
                            reason: "restore_db",
                            duration: 10
                        },
                        function(data) {
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
                    toastr.info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

                    up.settings.multipart_params = {
                        "PHPSESSID": "<?php echo $_SESSION['user_id']; ?>",
                        "File": file.name,
                        "type_upload": "restore_db",
                        "user_token": store.get('teampassUser').uploadToken
                    };
                },
                UploadComplete: function(up, files) {
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.uploadFileObject = restoreOperationId;
                        }
                    );
                    $('#onthefly-restore-file').text(files[0].name);

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                },
                Error: function(up, args) {
                    console.log(args);
                }
            }
        });
    // Uploader options
    uploader_restoreDB.bind('FileUploaded', function(upldr, file, object) {
        console.log(object)
        var myData = prepareExchangedData(object.response, "decode", "<?php echo $_SESSION['key']; ?>");
        console.log(myData);
        $('#onthefly-restore-file').data('operation-id', myData.operation_id);
    });

    uploader_restoreDB.bind("Error", function(up, err) {
        console.log(err)
        var myData = prepareExchangedData(err, "decode", "<?php echo $_SESSION['key']; ?>");
        $("#onthefly-restore-progress")
            .removeClass('hidden')
            .html('<div class="alert alert-danger alert-dismissible ml-2">' +
                '<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>' +
                '<h5><i class="icon fas fa-ban mr-2"></i><?php echo langHdl('done'); ?></h5>' +
                '' + err.message +
                '</div>');
        uploader_restoreDB.refresh(); // Reposition Flash/Silverlight
    });

    uploader_restoreDB.init();

    //]]>
</script>
