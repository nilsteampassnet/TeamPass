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
 * @file      import.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('import') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    var debugJavascript = false;

    // Checkbox
    $('input[type="checkbox"].flat-blue, input[type="radio"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
        radioClass: 'iradio_flat-blue'
    });

    // Select2
    $('.select2')
        .html(store.get('teampassUser').folders)
        .select2({
            language: '<?php echo $session->get('user-language_code'); ?>'
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
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.xap',
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
                        type_category: 'action_system',
                        size: 25,
                        capital: true,
                        numeric: true,
                        ambiguous: true,
                        reason: "import_items_from_csv",
                        duration: 10,
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.uploadedFileId = data[0].token;
                            }
                        );

                        up.setOption('multipart_params', {
                            PHPSESSID: '<?php echo $session->get('key'); ?>',
                            type_upload: "import_items_from_csv",
                            user_token: data[0].token
                            /*itemId: store.get('teampassItem').id,
                            type_upload: 'item_attachments',
                            isNewItem: store.get('teampassItem').isNewItem,
                            isPersonal: store.get('teampassItem').folderIsPersonal,
                            edit_item: false,
                            user_upload_token: store.get('teampassApplication').attachmentToken,
                            randomId: store.get('teampassApplication').uploadedFileId,
                            files_number: $('#form-item-hidden-pickFilesNumber').val(),
                            file_size: file.size*/
                        });

                        /*up.settings.multipart_params.PHPSESSID = "<?php echo session_id(); ?>";
                        up.settings.multipart_params.type_upload = "import_items_from_csv";
                        up.settings.multipart_params.user_token = data[0].token;*/

                        up.start();
                    },
                    "json"
                );
            },
            BeforeUpload: function(up, file) {
                // Show spinner
                toastr.remove();
                toastr.info('<i class="fa-solid fa-ellipsis fa-2x fa-fade ml-2"></i>');
            },
            FileUploaded: function(upldr, file, object) {
                var data = prepareExchangedData(object.response, "decode", "<?php echo $session->get('key'); ?>");
                if (debugJavascript === true) {
                    console.log(data)
                }

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
                        '<?php echo $lang->get('done'); ?>',
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
        toastr.info('<?php echo $lang->get('reading_file'); ?><i class="fa-solid fa-ellipsis fa-2x fa-fade ml-2"></i>');

        console.log("file: "+store.get('teampassApplication').uploadedFileId+" -- Folder id: "+$('#import-csv-target-folder').val())

        // Perform query
        $.post(
            "sources/import.queries.php", {
                type: "import_file_format_csv",
                file: store.get('teampassApplication').uploadedFileId,
                folder_id: $('#import-csv-target-folder').val(),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
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
                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
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
                        '<?php echo $lang->get('done'); ?>',
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
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i><?php echo $lang->get('please_wait'); ?>');

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
                '<i class="fas fa-ban fa-lg mr-2"></i><?php echo $lang->get('no_data_selected'); ?>',
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
            'edit-all': $('#import-csv-edit-all-checkbox').prop('checked') === true ? 1 : 0,
            'edit-role': $('#import-csv-edit-role-checkbox').prop('checked') === true ? 1 : 0,
            'folder-id' : parseInt($("#import-csv-target-folder").val()),
        }
        console.log(data);
        // Lauchn ajax query that will insert items into DB
        $.post(
            "sources/import.queries.php", {
                type: "import_items",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                console.log(data)

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
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
                        '<?php echo $lang->get('number_of_items_imported'); ?> : ' + counter_treated_items,
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


    // Plupload for KEEPASS
    var uploader_keepass = new plupload.Uploader({
        runtimes: "gears,html5,flash,silverlight,browserplus",
        browse_button: "import-keepass-attach-pickfile-keepass",
        container: "import-keepass-upload-zone",
        max_file_size: "10mb",
        chunk_size: "0",
        unique_names: true,
        dragdrop: true,
        multiple_queues: false,
        multi_selection: false,
        max_file_count: 1,
        url: "<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php",
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.xap',
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
                        type_category: 'action_system',
                        size: 25,
                        capital: true,
                        numeric: true,
                        ambiguous: true,
                        reason: "import_items_from_keepass",
                        duration: 10,
                        key: '<?php echo $session->get('key'); ?>'
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

                up.settings.multipart_params.PHPSESSID = "<?php echo session_id(); ?>";
                up.settings.multipart_params.type_upload = "import_items_from_keepass";
                up.settings.multipart_params.user_token = store.get('teampassApplication').uploadedFileId;			
            },
            FileUploaded: function(upldr, file, object) {
                var data = prepareExchangedData(object.response, "decode", "<?php echo $session->get('key'); ?>");
                if (debugJavascript === true) {
                    console.log(data);
                }

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
                        '<?php echo $lang->get('done'); ?>',
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
            },
            Error: function(up, data) {
                toastr.warning(
                    data.message + ' (' + up.settings.max_file_size + ')',
                    '<?php echo $lang->get('caution'); ?>',
                    {
                        timeOut: 4000,
                        progressBar: true
                    }
                );
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
        $('#import-feedback-progress-text')
            .html('<?php echo $lang->get('reading_file'); ?>');
            $('#import-feedback').removeClass('hidden');
        
        // block time counter
        ProcessInProgress = true;

        data = {
            'file': store.get('teampassApplication').uploadedFileId,
            'folder-id': parseInt($('#import-keepass-target-folder').val()),
        }
        if (debugJavascript === true) {
            console.log(data);
        }
        // Lauchn ajax query that will insert items into DB
        $.post(
            "sources/import.queries.php", {
                type: "import_file_format_keepass",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                if (debugJavascript === true) {
                    console.log(data);
                }

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );

                    $('#import-feedback, #import-feedback-progress').addClass('hidden');
                    //$('#import-feedback div').html('');
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
                    // Show spinner
                    $('#import-feedback-progress-text')
                        .html('<i class="fas fa-cog fa-spin ml-4 mr-2"></i><?php echo $lang->get('folder'); ?> <?php echo $lang->get('at_creation'); ?>');
                    if (debugJavascript === true) {
                        console.info("Now creating folders")
                    }
                    $.post(
                        "sources/import.queries.php", {
                            type: "keepass_create_folders",
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                            key: '<?php echo $session->get('key'); ?>'
                        },
                        function(data) {
                            data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                            if (debugJavascript === true) {
                                console.log(data);
                            }

                            if (data.error === true) {
                                toastr.remove();
                                toastr.error(
                                    '<i class="fas fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
                                    '', {
                                        timeOut: 10000,
                                        closeButton: true,
                                        progressBar: true
                                    }
                                );

                                $('#import-feedback, #import-feedback-progress').addClass('hidden');
                                $('#import-feedback-result').html('');
                            } else {
                                // STEP 2 - create Items
                                data = {
                                    'edit-all': $('#import-keepass-edit-all-checkbox').prop('checked') === true ? 1 : 0,
                                    'edit-role': $('#import-keepass-edit-role-checkbox').prop('checked') === true ? 1 : 0,
                                    'folders': data.folders,
                                    'items': itemsToAdd,
                                }
                                itemsNumber = itemsToAdd.length;
                                counter = 1;
                                console.info("Now creating items")
                                if (debugJavascript === true) {
                                    console.log(data);
                                }

                                // Recursive loop on each item to add
                                callRecurive(itemsToAdd, data.folders, counter, itemsNumber); 

                                // recursive action
                                function callRecurive(itemsList, foldersList, counter, itemsNumber) {
                                    var dfd = $.Deferred();

                                    // Isolate first item
                                    if (itemsList.length > 0) {
                                        $('#import-feedback-progress-text')
                                            .html('<i class="fas fa-cog fa-spin ml-4 mr-2"></i><?php echo $lang->get('operation_progress');?> ('+((counter*100)/itemsNumber).toFixed(2)+'%) - <i id="item-title"></i>');

                                        // XSS Filtering :
                                        $('#import-feedback-progress-text').text(itemsList[0].Title);

                                        data = {
                                            'edit-all': $('#import-keepass-edit-all-checkbox').prop('checked') === true ? 1 : 0,
                                            'edit-role': $('#import-keepass-edit-role-checkbox').prop('checked') === true ? 1 : 0,
                                            'items': itemsToAdd[0],
                                            'folders': foldersList,
                                        }
                                        if (debugJavascript === true) {
                                            console.log(data);
                                        }

                                        // Do query
                                        $.post(
                                            "sources/import.queries.php", {
                                                type: "keepass_create_items",
                                                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                                key: '<?php echo $session->get('key'); ?>'
                                            },
                                            function(data) {
                                                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                                                if (debugJavascript === true) {
                                                    console.log(data);
                                                }

                                                if (data.error === true) {
                                                    // ERROR
                                                    toastr.remove();
                                                    toastr.error(
                                                        '<i class="fas fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
                                                        '', {
                                                            timeOut: 10000,
                                                            closeButton: true,
                                                            progressBar: true
                                                        }
                                                    );

                                                    $('#import-feedback, #import-feedback-progress').addClass('hidden');
                                                    $('#import-feedback-result').html('');
                                                } else {
                                                    // Done for this item
                                                    // Add results
                                                    $('#import-feedback-result').append(data.info+"<br>");

                                                    // Remove item from list
                                                    itemsToAdd.shift();

                                                    // Do recursive call until step = finished
                                                    counter++
                                                    callRecurive(
                                                        itemsList,
                                                        foldersList,
                                                        counter,
                                                        itemsNumber
                                                    ).done(function(response) {
                                                        dfd.resolve(response);
                                                    });
                                                }
                                            }
                                        );
                                    } else {
                                        // THis is the end.
                                        // Table of items to import is empty

                                        // Show results
                                        $('#import-feedback-progress').addClass('hidden');
                                        $('#import-feedback div').removeClass('hidden');
                                        $('#import-feedback-result').append(data.info)
                                        $('#import-feedback-progress-text').html('');
                                        
                                        // Show
                                        toastr.remove();
                                        toastr.success(
                                            '<?php echo $lang->get('done'); ?>',
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

                                        // restart time expiration counter
                                        ProcessInProgress = false;
                                    }
                                    
                                    return dfd.promise();
                                }
                            }
                        }
                    );
                }
            }
        );
    }
</script>
