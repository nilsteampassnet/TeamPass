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
 * @file      import.js.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // Checkbox
    $('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
        radioClass: 'iradio_flat-blue'
    });

    // Select2
    $('.select2')
        .html(store.get('teampassUser').folders)
        .select2({
            language: '<?php echo $_SESSION['user_language_code']; ?>'
        });


    // Plupload for CSV
    var csv_filename = '';
    var uploader_csv = new plupload.Uploader({
        runtimes: "gears,html5,flash,silverlight,browserplus",
        browse_button: "import-csv-attach-pickfile-csv",
        container: "import-csv-upload-zone",
        max_file_size: "10mb",
        chunk_size: "1mb",
        unique_names: true,
        dragdrop: true,
        multiple_queues: false,
        multi_selection: false,
        max_file_count: 1,
        url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php",
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.xap',
        filters: [{
            title: "CSV files",
            extensions: "csv"
        }],
        init: {
            FilesAdded: function(up, files) {
                // generate and save token
                $.post(
                    "sources/main.queries.php", {
                        type: "save_token",
                        size: 25,
                        capital: true,
                        numeric: true,
                        ambiguous: true,
                        reason: "import_items_from_csv",
                        duration: 10,
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.uploadedFileId = data[0].token;
                            }
                        );

                        up.start();
                    },
                    "json"
                );
            },
            BeforeUpload: function(up, file) {
                // Show spinner
                toastr.remove();
                toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i>');

                up.settings.multipart_params = {
                    "PHPSESSID": "<?php echo session_id(); ?>",
                    "type_upload": "import_items_from_csv",
                    "user_token": store.get('teampassApplication').uploadedFileId
                };
            },
            FileUploaded: function(upldr, file, object) {
                var data = prepareExchangedData(object.response, "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data)

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fas fa-exclamation-circle fa-lg mr-2"></i>Message: ' + data.message,
                        '', {
                            timeOut: 10000,
                            progressBar: true
                        }
                    );
                } else {
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        data.message, {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );

                    $('#import-csv-attach-pickfile-csv-text')
                        .text(file.name + ' (' + plupload.formatSize(file.size) + ')');

                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.uploadedFileId = data.operation_id;
                        }
                    );

                    // Now perform import
                    console.log('START IMPORT')
                    ImportCSV();
                }
                upldr.splice(); // clear the file queue
            }
        }
    });

    // Uploader options
    uploader_csv.bind("Error", function(up, err) {
        $('#import-csv-upload-pickfile-list-csv')
            .removeClass('hidden')
            .html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
                ", Message: " + err.message +
                (err.file ? ", File: " + err.file.name : "") +
                "</div>"
            );
        up.splice(); // Clear the file queue
        up.refresh(); // Reposition Flash/Silverlight
    });

    uploader_csv.init();

    /**
     * CANCEL button
     */
    $(document).on('click', '#form-item-import-cancel', function() {
        // What importation is on-going
        var importTask = $('#import-type').find('.active').text().toLowerCase();

        if (importTask === 'csv') {
            // Clear form
            $('.csv-setup').addClass('hidden');
            $('#csv-items-number, #csv-items-list').html('');
            $('#import-csv-attach-pickfile-csv-text').val('');
            $('.import-csv-cb').iCheck('uncheck');

            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.uploadType = '';
                    teampassApplication.uploadedFileId = '';
                }
            );
        } else if (importTask === 'keepass') {
            // Clear form
            $('.keepass-setup').addClass('hidden');
            $('#keepass-items-number, #keepass-items-list').html('');
            $('#import-keepass-attach-pickfile-keepass-text').text('');
            $('.import-keepass-cb').iCheck('uncheck');

            store.update(
                'teampassApplication',
                function(teampassApplication) {
                    teampassApplication.uploadType = '';
                    teampassApplication.uploadedFileId = '';
                }
            );
        }

        $('#import-feedback').addClass('hidden');
        $('#import-feedback div').html('');
    });

    /**
     * 
     */
    $(document).on('click', '#form-item-import-perform', function() {
        // What importation is on-going
        var importTask = $('#import-type').find('.active').text().toLowerCase();

        store.update(
            'teampassApplication',
            function(teampassApplication) {
                teampassApplication.uploadType = importTask;
            }
        );

        if (importTask === 'csv') {
            // Are expected data available?
            if ($('#import-csv-target-folder').val() === 0 && parseInt($('#csv-items-number').text()) > 0) {
                return false;
            }

            launchCSVItemsImport();
        } else if (importTask === 'keepass') {
            // Are expected data available?
            if ($('#import-keepass-target-folder').val() === 0 && parseInt($('#keepass-items-number').text()) > 0) {
                return false;
            }

            launchKeepassItemsImport();
        }
    });


    //Permits to upload passwords from CSV file
    function ImportCSV() {
        // Show spinner
        toastr.remove();
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i><?php echo langHdl('reading_file'); ?>');
       

        // Perform query
        $.post(
            "sources/import.queries.php", {
                type: "import_file_format_csv",
                file: store.get('teampassApplication').uploadedFileId,
                folder_id: $('#import-csv-target-folder').val(),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data)

                // CLear
                store.update(
                    'teampassApplication',
                    function(teampassApplication) {
                        teampassApplication.uploadedFileId = '';
                    }
                );

                if (data.error == "bad_structure") {
                 toastr.remove();
                   toastr.error(
                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('import_error_no_read_possible'); ?>',
                      '', {
                          timeOut: 10000,
                            closeButton: true,
                         progressBar: true
                      }
                  );

                    $('#import-feedback').removeClass('hidden');
                    $('#import-feedback div').html('');
                } else {
                    // Show Import definition
                    $('.csv-setup').removeClass('hidden');

                    // Show items to import
                    var htmlItems = '';
                    $.each(data.output, function(i, value) {
                        // Prepare options lists
                        htmlItems += '<div class="form-group">' +
                            '<input type="checkbox" class="flat-blue csv-item" id="importcsv-' + i + '" ' +
                            'data-label="' + value.label + '" data-login="' + value.login + '" ' +
                            'data-pwd="' + value.pwd + '" data-url="' + value.url + '" ' +
                            'data-comment="' + value.comment + '" data-row="' + i + '">' +
                            '<label for="importcsv-' + i + '" class="ml-2">' + value.label + '</label>' +
                            '</div>';
                    });
                    $('#csv-items-list').html(htmlItems);

                    // Prepare iCheck format for checkboxes
                    $('input[type="checkbox"].flat-blue').iCheck({
                        checkboxClass: 'icheckbox_flat-blue'
                    });
                    $('input[type="checkbox"].flat-blue').iCheck('check');

                    // Increment counter
                    $(document).on('ifChecked', 'input[type="checkbox"].flat-blue', function() {
                        $('#csv-items-number').html(parseInt($('#csv-items-number').text()) + 1);
                    });

                    // Decrease counter
                    $(document).on('ifUnchecked', 'input[type="checkbox"].flat-blue', function() {
                        $('#csv-items-number').html(parseInt($('#csv-items-number').text()) - 1);
                    });

                    // Show number of items
                    $('#csv-items-number').html(data.number);

                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        data.message, {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    }

    //get list of items checked by user
    function launchCSVItemsImport() {
        // Show spinner
        toastr.remove();
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i><?php echo langHdl('please_wait'); ?>');

        // Init
        var items = '',
            arrItems = [];

        // Get data checked
        $(".flat-blue").each(function() {
            if ($(this).is(':checked') === true && $(this).hasClass('csv-item') === true) {
                if ($(this).attr('id') !== undefined) {
                    var elem = $(this).attr("id").split("-");
                    // Exclude previously imported items
                    if ($("#importcsv-" + elem[1]).parent().closest('.icheckbox_flat-blue').hasClass('disabled') !== true) {
                        arrItems.push({
                            label: $(this).data('label'),
                            login: $(this).data('login'),
                            pwd: $(this).data('pwd'),
                            url: $(this).data('url'),
                            comment: $(this).data('comment'),
                            row: $(this).data('row'),
                        })
                    }
                }
            }
        });

        if (arrItems.length === 0) {
            toastr.remove();
           toastr.error(
                '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('no_data_selected'); ?>',
               '', {
                  timeOut: 10000,
                    closeButton: true,
                 progressBar: true
              }
          );
            return false;
        }

        data = {
            'items': arrItems,
            'edit-all': $('#import-csv-edit-all-checkbox').prop('checked'),
            'edit-role': $('#import-csv-edit-role-checkbox').prop('checked'),
        }
        console.log(data);
        // Lauchn ajax query that will insert items into DB
        $.post(
            "sources/import.queries.php", {
                type: "import_items",
                folder: $("#import-csv-target-folder").val(),
                data: prepareExchangedData(JSON.stringify(arrItems), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data)

                if (data.error === true) {
                 toastr.remove();
                   toastr.error(
                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('import_error_no_read_possible'); ?>',
                      '', {
                          timeOut: 10000,
                            closeButton: true,
                         progressBar: true
                      }
                  );

                    $('#import-feedback').removeClass('hidden');
                    $('#import-feedback div').html('');
                } else {
                    var counter_treated_items = 0;
                    // after inserted, disable the checkbox in order to prevent against new insert
                    $.each(data.items, function(i, value) {
                        $("#importcsv-" + value).parent().closest('.icheckbox_flat-blue').addClass('disabled');
                        $('#csv-items-number').html(parseInt($('#csv-items-number').text()) - 1);
                        counter_treated_items++;
                    });

                    // Show
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('number_of_items_imported'); ?> : ' + counter_treated_items,
                        data.message, {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    }

    // **************  K E E P A S S  *************** //


    // Plupload for CSV
    var uploader_keepass = new plupload.Uploader({
        runtimes: "html5,flash,silverlight,html4",
        browse_button: "import-keepass-attach-pickfile-keepass",
        container: "import-keepass-upload-zone",
        max_file_size: "10mb",
        chunk_size: "1mb",
        unique_names: true,
        dragdrop: true,
        multiple_queues: false,
        multi_selection: false,
        max_file_count: 1,
        url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php",
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.xap',
        filters: [{
            title: "KEEPASS files",
            extensions: "xml"
        }],
        init: {
            FilesAdded: function(up, files) {
                // generate and save token
                $.post(
                    "sources/main.queries.php", {
                        type: "save_token",
                        size: 25,
                        capital: true,
                        numeric: true,
                        ambiguous: true,
                        reason: "import_items_from_keepass",
                        duration: 10,
                    key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.uploadedFileId = data[0].token;
                            }
                        );

                        up.start();
                    },
                    "json"
                );
            },
            BeforeUpload: function(up, file) {
                // Show spinner
                toastr.remove();
                toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i>');

                up.settings.multipart_params = {
                    "PHPSESSID": "<?php echo session_id(); ?>",
                    "type_upload": "import_items_from_keepass",
                    "user_token": store.get('teampassApplication').uploadedFileId
                };
            },
            FileUploaded: function(upldr, file, object) {
                var data = prepareExchangedData(object.response, "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data)

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fas fa-exclamation-circle fa-lg mr-2"></i>Message: ' + data.message,
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );
                } else {
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        data.message, {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );

                    $('#import-keepass-attach-pickfile-keepass-text')
                        .text(file.name + ' (' + plupload.formatSize(file.size) + ')');

                    store.update(
                        'teampassApplication',
                        function(teampassApplication) {
                            teampassApplication.uploadedFileId = data.operation_id;
                        }
                    );

                    // Now show form
                    $('.keepass-setup').removeClass('hidden');
                }
                upldr.splice(); // clear the file queue
            }
        }
    });

    // Uploader options
    uploader_keepass.bind("Error", function(up, err) {
        $('#import-csv-keepass-pickfile-list-keepass')
            .removeClass('hidden')
            .html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
                ", Message: " + err.message +
                (err.file ? ", File: " + err.file.name : "") +
                "</div>"
            );
        up.splice(); // Clear the file queue
        up.refresh(); // Reposition Flash/Silverlight
    });

    uploader_keepass.init();


    function launchKeepassItemsImport() {
        // Show spinner
        toastr.remove();
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i><?php echo langHdl('reading_file'); ?>');

        data = {
            'file': store.get('teampassApplication').uploadedFileId,
            'folder-id': parseInt($('#import-keepass-target-folder').val()),
        }
        console.log(data);
        // Lauchn ajax query that will insert items into DB
        $.post(
            "sources/import.queries.php", {
                type: "import_file_format_keepass",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data);

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('import_error_no_read_possible'); ?>',
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );

                    $('#import-feedback').addClass('hidden');
                    $('#import-feedback div').html('');
                } else {
                    foldersToAdd = data.data.folders;
                    itemsToAdd = data.data.items;
                    // STEP 1 - create Folders
                    data = {
                        'edit-all': $('#import-keepass-edit-all-checkbox').prop('checked') === true ? 1 : 0,
                        'edit-role': $('#import-keepass-edit-role-checkbox').prop('checked') === true ? 1 : 0,
                        'folder-id': parseInt($('#import-keepass-target-folder').val()),
                        'folders': foldersToAdd,
                    }
                    console.info("Now creating folders")
                    console.log(data);
                    $.post(
                        "sources/import.queries.php", {
                            type: "keepass_create_folders",
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key: '<?php echo $_SESSION['key']; ?>'
                        },
                        function(data) {
                            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                            console.log(data)

                            if (data.error === true) {
                                toastr.remove();
                                toastr.error(
                                    '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('import_error_no_read_possible'); ?>',
                                    '', {
                                        timeOut: 10000,
                                        closeButton: true,
                                        progressBar: true
                                    }
                                );

                                $('#import-feedback').addClass('hidden');
                                $('#import-feedback div').html('');
                            } else {
                                // STEP 2 - create Items
                                data = {
                                    'edit-all': $('#import-keepass-edit-all-checkbox').prop('checked') === true ? 1 : 0,
                                    'edit-role': $('#import-keepass-edit-role-checkbox').prop('checked') === true ? 1 : 0,
                                    'folders': data.folders,
                                    'items': itemsToAdd,
                                }
                                console.info("Now creating items")
                                console.log(data);
                                $.post(
                                    "sources/import.queries.php", {
                                        type: "keepass_create_items",
                                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                                        key: '<?php echo $_SESSION['key']; ?>'
                                    },
                                    function(data) {
                                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                                        console.info("Done")
                                        console.log(data)

                                        if (data.error === true) {
                                            toastr.remove();
                                            toastr.error(
                                                '<i class="fas fa-ban fa-lg mr-2"></i><?php echo langHdl('import_error_no_read_possible'); ?>',
                                                '', {
                                                    timeOut: 10000,
                                                    closeButton: true,
                                                    progressBar: true
                                                }
                                            );

                                            $('#import-feedback').addClass('hidden');
                                            $('#import-feedback div').html('');
                                        } else {
                                            // Finished
                                            // Show results
                                            $('#import-feedback div').html(data.info)
                                            $('#import-feedback').removeClass('hidden');
                                            
                                            // Show
                                            toastr.remove();
                                            toastr.success(
                                                '<?php echo langHdl('done'); ?>',
                                                data.message, {
                                                    timeOut: 2000,
                                                    progressBar: true
                                                }
                                            );

                                            // Clear form
                                            $('.keepass-setup').addClass('hidden');
                                            $('#keepass-items-number, #keepass-items-list').html('');
                                            $('#import-keepass-attach-pickfile-keepass-text').text('');
                                            $('.import-keepass-cb').iCheck('uncheck');

                                            store.update(
                                                'teampassApplication',
                                                function(teampassApplication) {
                                                    teampassApplication.uploadType = '';
                                                    teampassApplication.uploadedFileId = '';
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
    }
</script>
