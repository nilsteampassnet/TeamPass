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
 * @file      admin.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('admin') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

?>

<script type="text/javascript">
    var requestRunning = false;
    
    // Manage memory
    browserSession(
        'init',
        'teampassApplication', {
            lastItemSeen: '',
            selectedFolder: '',
            itemsListStop: '',
            itemsListStart: '',
            selectedFolder: '',
            itemsListFolderId: '',
            itemsListRestricted: '',
            itemsShownByQuery: '',
            foldersList: [],
            personalSaltkeyRequired: 0,
            uploadedFileId: '',
            rolesSelectOptions: '',
        }
    );


    /**
     * ADMIN
     */
    // <- PREPARE TOGGLES
    $('.toggle').toggles({
        drag: true,
        click: true,
        text: {
            on: '<?php echo $lang->get('yes'); ?>',
            off: '<?php echo $lang->get('no'); ?>'
        },
        on: true,
        animate: 250,
        easing: 'swing',
        width: 50,
        height: 20,
        type: 'compact'
    });
    $('.toggle').on('toggle', function(e, active) {
        if (active) {
            $("#" + e.target.id + "_input").val(1);
            if (e.target.id == "allow_print") {
                $("#roles_allowed_to_print_select").prop("disabled", false);
            }
            if (e.target.id == "anyone_can_modify") {
                $("#form-item-row-modify").removeClass('hidden');
            }
            if (e.target.id == "restricted_to") {
                $("#form-item-row-restricted").removeClass('hidden');
            }
        } else {
            $("#" + e.target.id + "_input").val(0);
            if (e.target.id == "allow_print") {
                $("#roles_allowed_to_print_select").prop("disabled", true);
            }
            if (e.target.id == "anyone_can_modify") {
                $("#form-item-row-modify").addClass('hidden');
            }
            if (e.target.id == "restricted_to") {
                $("#form-item-row-restricted").addClass('hidden');
            }
        }

        var data = {
            "field": e.target.id,
            "value": $("#" + e.target.id + "_input").val(),
        }
        console.log(data)
        // Store in DB   
        $.post(
            "sources/admin.queries.php", {
                type: "save_option_change",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
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
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                console.log(data)
                if (data.error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('saved'); ?>',
                        '', {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    });
    // .-> END. TOGGLES

    // <- PREPARE SELECT2
    $('.select2').select2({
        language: '<?php echo $userLang = $session->get('user-language_code'); echo isset($userLang) === null ? $userLang : 'EN'; ?>'
    });

    /**
     */
    $(document).on('change', '.form-control-sm, .setting-ldap', function() {
        var field = $(this).attr('id'),
            value = $.isArray($(this).val()) === false ? $(this).val() : JSON.stringify($(this).val().map(Number));

        if (field === '' || field === undefined || $(this).hasClass('no-save') === true) return false;
        
        // prevent launch of similar query in case of doubleclick
        if (requestRunning === true) {
            return false;
        }

        // Sanitize value
        if ($(this).hasClass('select2') === false) {
            value = fieldDomPurifierWithWarning('#' + field, false, false, false, true);
        }
        if (value === false) {
            return false;
        }
        $('#' + field).val(value);

        requestRunning = true;

        // Manage special cases
        // WHere data needs to be adapted
        if (field === 'tasks_history_delay') {
            value = parseInt(value) * 3600 * 24;
        }

        var data = {
            "field": field,
            "value": value,
        }

        // Store in DB   
        $.post(
            "sources/admin.queries.php", {
                type: "save_option_change",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
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
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                
                if (data.error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('saved'); ?>',
                        '', {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );
                }
                requestRunning = false;
            }
        );
    });

    $(document).ready(function() {
        // Perform DB integrity check
        setTimeout(
            performDBIntegrityCheck,
            2000
        );
    });

    /**
     * Perform project files integrity check
     */
    function performProjectFilesIntegrityCheck(refreshingData = false)
    {
        if (requestRunning === true) {
            return false;
        }

        requestRunning = true;

        // Remove the file from the list
        if (refreshingData === false) {
            $('#files-integrity-result').remove();
            $('#files-integrity-result-container').addClass('hidden');
        }
    
        $.post(
            "sources/admin.queries.php", {
                type: "filesIntegrityCheck",
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
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                
                let html = '';
                if (data.error === false) {
                    html = '<i class="fa-solid fa-circle-check text-success mr-2"></i>Project files integrity check is successfull';
                } else {
                    // Create a list
                    let ul = '<ul id="files-integrity-result">';
                    let files = JSON.parse(data.files);
                    let numberOfFiles = Object.keys(files).length;
                    $.each(files, function(i, value) {
                        ul += '<li value="'+i+'">' + value + '</li>';
                    });

                    // Prepare the HTML
                    html = '<i class="fa-solid fa-circle-xmark text-danger mr-2"></i>Project files integrity check performed!<br>'
                        + '<b>' + numberOfFiles + '</b> files are not part of the project.' + 
                        '<i class="fa-regular fa-eye infotip ml-2 text-info pointer text-warning infotip pointer" id="button-show-files-integrity-result" title="show_files"></i>' +
                        '<div class="alert alert-light' + (refreshingData ? '' : ' hidden') + '" role="alert" id="files-integrity-result-container">' +
                        '<div class="alert alert-warning" role="alert"><?php echo $lang->get('unknown_files_should_be_deleted'); ?>' +
                        '<div class="btn-group ml-2" role="group">'+
                            '<button type="button" class="btn btn-primary btn-sm infotip" id="refresh_unknown_files" title="<?php echo $lang->get('refresh'); ?>"><i class="fa-solid fa-arrows-rotate"></i></button>' +
                            '<button type="button" class="btn btn-danger btn-sm infotip" id="delete_unknown_files" title="<?php echo $lang->get('delete'); ?>"><i class="fa-solid fa-trash"></i></button>' +
                            '<button type="button" class="btn btn-info btn-sm infotip" id="hide_files" title="<?php echo $lang->get('hide'); ?>"><i class="fa-solid fa-eye-slash"></i></button>' +
                        '</div></div>' +
                        ul + '</ul></div>';                        

                    // Create the button to show/hide the list
                    $(document)
                        .on('click', '#button-show-files-integrity-result', function(event) {
                            event.preventDefault();
                            event.stopPropagation();
                            $('#files-integrity-result-container').removeClass('hidden');
                        })
                        .on('click', '#refresh_unknown_files', function(event) {
                            event.preventDefault();
                            event.stopPropagation();
                            // Show loader
                            $('#files-integrity-result').html('<i class="fa-solid fa-spinner fa-spin"></i>');
                            // Launch the integrity check
                            performProjectFilesIntegrityCheck(true);
                        })
                        .on('click', '#hide_files', function(event) {
                            event.preventDefault();
                            event.stopPropagation();
                            $('#files-integrity-result-container').addClass('hidden');
                        })
                        .on('click', '#delete_unknown_files', function(event) {   
                            event.preventDefault();
                            event.stopPropagation();                         
                            // Ask the user if he wants to delete the files
                            if (confirm('<?php echo $lang->get('delete_unknown_files'); ?>')) {
                                // Show loader
                                $('#files-integrity-result').html('<i class="fa-solid fa-spinner fa-spin"></i>');
                            } else {
                                // Cancel
                                return false;
                            }
                            // Launch delete unknown files
                            performDeleteFilesIntegrityCheck();
                        });
                }
                // Display the result
                $('#project-files-check-status').html(html);
                requestRunning = false;
            }
        );
    }

    /**
     * Perform delete unknown files
     */
    function performDeleteFilesIntegrityCheck()
    {
        $.post(
            "sources/admin.queries.php", {
                type: "deleteFilesIntegrityCheck",
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
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }

                if (data.deletionResults === '') {
                    // No files to delete
                    $('#files-integrity-result').html('<i class="fa-solid fa-circle-check text-success mr-2"></i><?php echo $session->get('done'); ?>');
                    return false;
                }

                // Display the result as a list
                // Initialize the HTML output
                let output = '<ul style="margin-left:-60px;">';
                let showSuccessful = true;
                
                // Process each file result
                $.each(data.deletionResults, function(file, result) {
                    // Skip successful operations if not showing them
                    if (!showSuccessful && result.success) {
                        return true; // continue to next iteration
                    }
                    
                    //const className = result.success ? 'success' : 'error';
                    const icon = result.success ? '<i class="fa-solid fa-check text-success mr-1"></i>' : '<i class="fa-solid fa-xmark text-danger mr-1"></i>';
                    const message = result.success ? '<?php echo $lang->get('server_returned_data');?>' : 'Error: ' + result.error;
                    
                    output += '<li>' + icon + '<b>' + file + '</b><br/>' + message + '</li>';
                });
                
                output += '</ul>';

                $('#files-integrity-result').html(output);

            }
        );
    }

    /**
     * Perform DB integrity check
     */
    function performDBIntegrityCheck()
    {
        $.post(
            "sources/admin.queries.php", {
                type: "tablesIntegrityCheck",
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
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                
                let html = '',
                    tablesInError = '',
                    cnt = 0,
                    tables = JSON.parse(data.tables);
                if (data.error === false) {
                    $.each(tables, function(i, value) {
                        if (cnt < 5) {
                            tablesInError += '<li>' + value + '</li>';
                        } else {
                            tablesInError += '<li>...</li>';
                            return false;
                        }
                        cnt++;
                    });

                    if (tablesInError === '') {
                        html = '<i class="fa-solid fa-circle-check text-success mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check is successfull';
                    } else {
                        html = '<i class="fa-solid fa-circle-xmark text-warning mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check has identified issues with the following tables:'
                            + '<i class="fa-regular fa-circle-question infotip ml-2 text-info" title="You should consider to run Upgrade process to fix this or perform manual changes on tables"></i>';
                        html += '<ul class="fs-6">' + tablesInError + '</ul>';
                    }
                } else {
                    html = '<i class="fa-solid fa-circle-xmark text-danger mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check could not be performed!'
                        + 'Error returned: ' + data.message;
                }
                $('#db-integrity-check-status').html(html);                
    
                // Show tooltips
                $('.infotip').tooltip();

                requestRunning = false;
                
                // Perform project files integrity check
                setTimeout(
                    performProjectFilesIntegrityCheck,
                    500
                );
            }
        );
    }
</script>
