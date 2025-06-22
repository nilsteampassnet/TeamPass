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
 * @copyright 2009-2025 Teampass.net
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
        .select2({
            language: '<?php echo $session->get('user-language_code'); ?>'
        });

    // Provide list to filder target select
    $('#import-csv-target-folder, #import-keepass-target-folder').html(store.get('teampassUser').folders)

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
                        });

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
                        '<i class="fa-solid fa-exclamation-circle fa-lg mr-2"></i>Message: ' + data.message,
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
                    if (debugJavascript === true) {
                        console.log('START IMPORT');
                    }
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


    // STEP 1 - Permits to upload passwords from CSV file
    const batchSizeFolders = 100;
    let batchSizeItems = 10; // Is defined as 10 but will change to 100 in case of tasks handler used for keys encryption
    let folderIdMap = {};
    let failedItems = [];
    let processTimeEstimated = 0;
    let processTimeStarted = 0;

    function ImportCSV() {
        // Show spinner
        toastr.remove();
        toastr.info('<?php echo $lang->get('reading_file'); ?><i class="fa-solid fa-ellipsis fa-2x fa-fade ml-2"></i>');

        if (debugJavascript === true) {
            console.log("file: "+store.get('teampassApplication').uploadedFileId+" -- Folder id: "+$('#import-csv-target-folder').val());
        }

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
                if (debugJavascript === true) {
                    console.log(data);
                }

                // CLear
                store.update(
                    'teampassApplication',
                    function(teampassApplication) {
                        teampassApplication.uploadedFileId = '';
                    }
                );

                if (data.error == true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fa-solid fa-ban fa-lg mr-2"></i>' + data.message,
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );

                    $('#import-feedback').removeClass('hidden');
                    $('#import-feedback div').html('');
                } else {
                    // Show number of items
                    $('#csv-file-info').html('<i class="fa-solid fa-list mr-2"></i><?php echo $lang->get('to_be_imported'); ?>:' + 
                        '<i class="fa-solid fa-key ml-4 mr-1"></i><span id="csv-items-number">' + data.items_number + '</span>' +
                        (data.userCanManageFolders === 1 ?
                        '<i class="fa-solid fa-folder ml-4 mr-1"></i><span id="csv-folders-number">' + data.folders_number + '</span>'
                        : '') +
                        '<input type="hidden" id="csv-operation-id" value="' + data.operation_id + '">' +
                        '<input type="hidden" id="userCanManageFolders" value="' + data.userCanManageFolders + '">'
                    );

                    // Show Import definition
                    if (data.userCanManageFolders === 1 && data.folders_number > 0) {
                        $('.csv-folder').removeClass('hidden');
                    } else {
                        $('.csv-folder').addClass('hidden');
                    }
                    $('.csv-setup').removeClass('hidden');

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


    // Function to update progress bar dynamically
    function updateCsvProgressBar(current, total) {
        let progress = Math.round((current / total) * 100);
        $('#import-csv-progress-bar').css('width', progress+'%').text(progress+'%');
    }


    // STEP 2 - Start performing the import of the preloaded CSV file
    function launchCSVItemsImport() {
        // IS the folder selected?
        if (parseInt($("#import-csv-target-folder").val()) === 0) {
            toastr.remove();
            toastr.error(
                '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo $lang->get('please_select_a_folder'); ?>',
                '', {
                    timeOut: 10000,
                    closeButton: true,
                    progressBar: true
                }
            );
            return false;
        }

        // Is the operation id available?
        if ($('#csv-operation-id').val() === '') {
            toastr.remove();
            toastr.error(
                '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
                '', {
                    timeOut: 10000,
                    closeButton: true,
                    progressBar: true
                }
            );
            return false;
        }

        // Show spinner
        toastr.remove();
        toastr.info('<i class="fa-solid fa-cog fa-spin fa-1x mr-2"></i><?php echo $lang->get('please_wait_folders_in_construction'); ?>');

        // Stop session timer
        ProcessInProgress = true;
        
        // Configuration
        let processedFolders = 0;
        let totalFolders = 0;
        let csvOperationId = $('#csv-operation-id').val();
        let folderId = parseInt($("#import-csv-target-folder").val());

        
        // Initialize progress bar
        $("#csv-setup-progress, #import-progress").removeClass("hidden");
        $("#import-progress-bar").css("width", "0%").attr("aria-valuenow", "0");

        // If user can manage folders, process folders first
        if ($('#userCanManageFolders').val() === '1') {
            // Set total folder count and start recursive processing
            totalFolders = parseInt($('#csv-folders-number').text()) || 0;
            if (totalFolders > 0 && processedFolders <= totalFolders) {
                processCsvFoldersBatch(csvOperationId, folderId, processedFolders, totalFolders, batchSizeFolders,);
            } else {
                toastr.info(
                    '<i class="fa-solid fa-info-circle fa-lg mr-2"></i><?php echo addslashes($lang->get('import_no_folders_to_process')); ?>',
                    '', {
                        timeOut: 5000,
                        closeButton: true
                    }
                );
                processCSVItems(csvOperationId, folderId);
            }
        } else {
            // No folders to process, start importing items directly
            processCSVItems(csvOperationId, folderId);
        }
    }

    // Function to process folders in batches recursively
    function processCsvFoldersBatch(csvOperationId, folderId, processedFolders, totalFolders, batchSizeFolders) {
        let dataFolders = {
            'csvOperationId': csvOperationId,
            'folderId': folderId,
            'offset': processedFolders, // Start from where we left off
            'limit': batchSizeFolders, // Process only batchSize folders
            'folderIdMap': folderIdMap, // Persist folder ID mapping
            'folderPasswordComplexity': $('#import-csv-complexity').val(),
            'folderAccessRight': $('#import-csv-access-right').val(),
        };

        if (debugJavascript === true) {
            console.log(`Processing folders batch: ${processedFolders} / ${totalFolders}`, dataFolders);
        }

        // Time estimation
        processTimeStarted = Date.now();

        $.post(
            "sources/import.queries.php",
            {
                type: "import_csv_folders",
                data: prepareExchangedData(JSON.stringify(dataFolders), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(response) {
                response = prepareExchangedData(response, "decode", "<?php echo $session->get('key'); ?>");

                if (debugJavascript === true) {
                    console.log("Folder creation response", response);
                }

                if (response.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_folder_creation')); ?>',
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );
                    return;
                }

                // Estimate time remaining based upon the number of folders processed and the time taken
                let processTimeElapsed = Date.now() - processTimeStarted;
                let estimatedTimeRemaining = Math.round((processTimeElapsed / (processedFolders + batchSizeFolders)) * (totalFolders - processedFolders));
                let estimatedTimeRemainingFormatted = new Date(estimatedTimeRemaining * 1000).toISOString().substr(11, 8);
                $('#import-csv-progress-text').html(
                    '<i class="fa-solid fa-folder mr-2 fa-beat-fade"></i>' + processedFolders + '/' + totalFolders +
                    ' <span class="">(<i class="fa-solid fa-stopwatch ml-1 mr-1"></i>' + estimatedTimeRemainingFormatted + ')</span>'
                );

                // Persist the updated folderIdMap
                folderIdMap = response.folderIdMap;

                // Update processed count
                processedFolders += response.processedCount || batchSizeFolders;
                updateCsvProgressBar(processedFolders, totalFolders);

                // If there are more folders to process, continue recursively
                if (processedFolders < totalFolders) {
                    processCsvFoldersBatch(csvOperationId, folderId, processedFolders, totalFolders, batchSizeFolders);
                } else {
                    // All folders processed successfully
                    toastr.remove();
                    toastr.success(
                        '<i class="fa-solid fa-check-circle fa-lg mr-2"></i><?php echo addslashes($lang->get('import_folders_success')); ?>',
                        '', {
                            timeOut: 2000,
                            closeButton: true,
                            progressBar: true
                        }
                    );

                    // Wait for 1 second before starting the next phase
                    setTimeout(() => {
                        processCSVItems(csvOperationId, folderId);
                    }, 1500);
                }
            }
        );
    }

    // STEP 3 - Start importing items from CSV
    function processCSVItems(csvOperationId, folderId)
    {
        // Show spinner
        toastr.remove();
        toastr.info('<i class="fa-solid fa-cog fa-spin fa-1x mr-2"></i><?php echo $lang->get('please_wait_items_in_construction'); ?>');

        // Configuration
        let processedItems = 0;
        let totalItems = 0;
        let insertedItems = 0;

        if ($('#import-csv-keys-strategy').val() === 'tasksHandler' && batchSizeItems === 10) {
            // If the keys strategy is set to tasksHandler, increase batch size to 100
            batchSizeItems = 100;
        }

        // Set total folder count and start recursive processing
        totalItems = parseInt($('#csv-items-number').text()) || 0;
        if (totalItems > 0) {
            // Initialize progress bar            
            updateCsvProgressBar(processedItems, totalItems);

            $('#import-csv-progress-text').html(
                '<i class="fa-solid fa-key mr-2 fa-beat-fade"></i><?php echo addslashes($lang->get('please_wait')); ?></span>'
            );

            // Start processing items in batches
            processCsvItemsBatch(csvOperationId, folderId, processedItems, totalItems, batchSizeItems, insertedItems);
        } else {
            toastr.info(
                '<i class="fa-solid fa-info-circle fa-lg mr-2"></i><?php echo addslashes($lang->get('import_no_items_to_process')); ?>',
                '', {
                    timeOut: 5000,
                    closeButton: true
                }
            );

            finishingCSVImport();
        }

    }

    function processCsvItemsBatch(csvOperationId, folderId, processedItems, totalItems, batchSizeItems, insertedItems)
    {
        let dataItems = {
            'csvOperationId': csvOperationId,
            'folderId': folderId,
            'offset': processedItems, // Start from where we left off
            'limit': batchSizeItems, // Process only batchSize items
            'editAll': $('#import-csv-edit-all-checkbox').prop('checked') ? 1 : 0,
            'editRole': $('#import-csv-edit-role-checkbox').prop('checked') ? 1 : 0,
            'keysGenerationWithTasksHandler': $('#import-csv-keys-strategy').val(),
            'insertedItems': insertedItems,
            'foldersNumber': parseInt($('#csv-folders-number').text()) || 0,
        };

        if (debugJavascript === true) {
            console.log(`Processing items batch: ${processedItems} / ${totalItems}`, dataItems);
        }

        // Time estimation
        processTimeStarted = Date.now();

        $.post(
            "sources/import.queries.php",
            {
                type: "import_csv_items",
                data: prepareExchangedData(JSON.stringify(dataItems), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(response) {
                response = prepareExchangedData(response, "decode", "<?php echo $session->get('key'); ?>");

                if (debugJavascript === true) {
                    console.log("Item creation response", response);
                }

                if (response.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_item_creation')); ?>',
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );
                    return;
                }

                // Estimate time remaining based upon the number of items processed and the time taken
                let processTimeElapsed = Date.now() - processTimeStarted;
                let estimatedTimeRemaining = Math.round((processTimeElapsed / (processedItems + batchSizeFolders)) * (totalItems - processedItems));
                let estimatedTimeRemainingFormatted = new Date(estimatedTimeRemaining * 1000).toISOString().substr(11, 8);
                $('#import-csv-progress-text').html(
                    '<i class="fa-solid fa-folder mr-2 fa-beat-fade"></i>' + processedItems + '/' + totalItems +
                    ' <span class="">(<i class="fa-solid fa-stopwatch ml-1 mr-1"></i>' + estimatedTimeRemainingFormatted + ')</span>'
                );

                // Update processed count
                processedItems += batchSizeItems;
                updateCsvProgressBar(processedItems, totalItems);

                // Update inserted items count
                insertedItems = response.insertedItems || 0;

                // Update failedItems array
                failedItems = failedItems.concat(response.failedItems || []);

                // If there are more folders to process, continue recursively
                if (debugJavascript === true) {
                    console.log('Processed items', processedItems, totalItems)
                }
                if (processedItems < totalItems) {
                    processCsvItemsBatch(csvOperationId, folderId, processedItems, totalItems, batchSizeItems, insertedItems);
                } else {
                    // All items processed successfully
                    finishingCSVImport(csvOperationId, totalItems, insertedItems);
                }
            }
        );
    }

    // STEP 4 - Finish the CSV import process
    function finishingCSVImport(csvOperationId = 0, totalItems = 0, insertedItems = 0) {
        // Delete items in temporary table
        let data = {
            'csvOperationId': csvOperationId,
        };
        $.post(
            "sources/import.queries.php",
            {
                type: "import_csv_items_finalization",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(response) {
                response = prepareExchangedData(response, "decode", "<?php echo $session->get('key'); ?>");

                if (debugJavascript === true) {
                    console.log("Finalization response", response);
                }

                if (response.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_item_creation')); ?>',
                        '', {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );
                    return;
                }
                // Show status
                toastr.remove();
                toastr.success(
                    '<i class="fa-solid fa-check-circle fa-lg mr-2"></i><?php echo addslashes($lang->get('csv_import_success')); ?>',
                    '', {
                        timeOut: 2000,
                        closeButton: true,
                        progressBar: true
                    }
                );

                // Clear form
                $('.csv-setup, #csv-setup-progress').addClass('hidden');
                $('#csv-file-info').html('');
                $('#import-csv-attach-pickfile-csv-text').val('');
                $('.import-csv-cb').iCheck('uncheck');
                $('#import-csv-access-right, #import-csv-target-folder, #import-csv-keys-strategy, #import-csv-complexity').val('');

                $('#import-feedback').removeClass('hidden');
                $('#import-feedback-progress-text').html(
                    '<i class="fa-solid fa-check-circle fa-lg mr-2"></i><?php echo addslashes($lang->get('csv_import_success')); ?>' +
                    '<br>' +
                    '<i class="fa-solid fa-key mr-2"></i><?php echo addslashes($lang->get('number_of_items_imported')); ?>: ' + insertedItems +
                    '<br>' +
                    '<i class="fa-solid fa-exclamation-triangle mr-2"></i><?php echo addslashes($lang->get('number_of_items_failed')); ?>: ' + failedItems.length +
                    '<br>' +
                    (failedItems.length > 0
                        ? failedItems.map((item) =>
                            `<i class="fa-solid fa-exclamation-triangle mr-2"></i>ID ${item.increment_id} : ${item.error}`
                        ).join('<br>')
                        : ''
                    )
                );

                // Show message
                
                // restart time expiration counter
                ProcessInProgress = false;
            }
        );
    }

    // **************  K E E P A S S  *************** //


    // Plupload for KEEPASS
    var uploader_keepass = new plupload.Uploader({
        runtimes: "gears,html5,flash,silverlight,browserplus",
        browse_button: "import-keepass-attach-pickfile-keepass",
        container: "import-keepass-upload-zone",
        max_file_size: "20mb",
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
                toastr.info('<i class="fa-solid fa-cog fa-spin fa-2x"></i>');

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
                        '<i class="fa-solid fa-exclamation-circle fa-lg mr-2"></i>Message: ' + data.message,
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
                        '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
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
                        .html('<i class="fa-solid fa-cog fa-spin ml-4 mr-2"></i><?php echo $lang->get('folder'); ?> <?php echo $lang->get('at_creation'); ?>');
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
                                    '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
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
                                            .html('<i class="fa-solid fa-cog fa-spin ml-4 mr-2"></i><?php echo $lang->get('operation_progress');?> ('+((counter*100)/itemsNumber).toFixed(2)+'%) - <i id="item-title"></i>');

                                        // XSS Filtering :
                                        $('#import-feedback-progress-text').text(itemsList[0].Title);

                                        data = {
                                            'edit-all': $('#import-keepass-edit-all-checkbox').prop('checked') === true ? 1 : 0,
                                            'edit-role': $('#import-keepass-edit-role-checkbox').prop('checked') === true ? 1 : 0,
                                            'items': itemsToAdd.slice(0, 500),
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
                                                        '<i class="fa-solid fa-ban fa-lg mr-2"></i><?php echo addslashes($lang->get('import_error_no_read_possible')); ?>',
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
                                                    itemsToAdd.splice(0, 500);

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
