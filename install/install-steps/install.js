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
 * @file      install.js
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */



$(function() {
    // Initialize the session
    browserSession(
        'init',
        'TeamPassInstallation', {
        }
    );

    // Display or not action buttons
    if ($('#installStep').val() !== '7') {
        $('#buttons-div').removeClass('hidden');
    } else {
        $('#buttons-div').addClass('hidden');
        // Clear browser session
        store.remove('TeamPassInstallation');
    }

    // Next button clicked
    $("#button_next").on('click', function(event) {
        event.preventDefault();
        var nextStep = parseInt($(this).data('step'));

        if (nextStep > 0) {
            moveToNextStep(nextStep)
            return;

        } else {
            console.error('Unknown step:', nextStep);
        }
    });

    // Start button clicked
    $("#button_start").on('click', function(event) {
        event.preventDefault();
        var step = parseInt($(this).data('step'));
        $('#button_next').prop('disabled', true);

        // Check if all fields are filled
        var allFieldsValid = true;
        $('.required').each(function() {
            var $this = $(this);
            
            if ($this.val().trim() === '') {
                allFieldsValid = false;
                show_loader('error', '<i class="fa-regular fa-circle-xmark"></i> Field ' + $this.data('label') + ' is mandatory!');
                return false;
            }
        });

        // Steps
        if (step === 1 && allFieldsValid) {
            // Validate the fields
            performStep1();
            return;

        } else if (step === 2) {
            // Validate server settings
            performStep2();
            return;
            
        } else if (step === 3 && allFieldsValid) {
            // Validate the Database
            performStep3();
            return;

        } else if (step === 4 && allFieldsValid) {
            // Validate server settings
            if ($('#admin_pwd').val() !== $('#admin_pwd_confirm').val()) {
                show_loader('error', '<i class="fa-regular fa-circle-xmark"></i> Password confirmation is not correct!');
                return false;
            }
            performStep4();
            return;

        } else if (step === 5) {
            // Populating database
            performStep5();
            return;

        } else if (step === 6) {
            // Final step
            performStep6();
            return;

        } else {
            console.error('Error at step '+step+', allFieldsValid='+allFieldsValid);
        }        
    });
});

// Handle tooltips
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el, {
            trigger: 'hover focus',
            container: 'body'
        });
    });
});


/**
 * Perform step 6
 */
function performStep6() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');

    const checks = [
        { id: 'check0', action: 'secureFile' },
        { id: 'check1', action: 'chmod' },
        { id: 'check2', action: 'settingsFile' },
        { id: 'check3', action: 'csrf' },
        { id: 'check4', action: 'cronJob', optional: true },
        { id: 'check5', action: 'cleanInstall' }
    ];
    
    let errorOccurred = false; // Variable for tracking errors
    let currentStep = 1; // Number of checks performed
    const totalSteps = checks.length; // Number of checks to perform

    // Permits to perform a check
    function performCheck(index) {
        if (index >= checks.length) {
            if (errorOccurred) {
                show_loader('error', '<i class="fa-regular fa-circle-xmark text-alert"></i> Some errors occurred. Please fix them all before continuing.');
            } else {
                show_loader('success', '<i class="fas fa-check text-success"></i> All checks successfull!', 2);

                // Update the next step
                $('#installStep').val('7');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);
            }
            return;
        }

        const check = checks[index];
        
        // Display a progress indicator
        $('#'+check.id).html('<i class="fas fa-spinner fa-spin text-primary"></i>');

        // AJAX call
        $.ajax({
            url: './install-steps/run.step6.php',
            method: 'POST',
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                action: check['action'],
                dbHost: store.get('TeamPassInstallation').dbHost,
                dbName: store.get('TeamPassInstallation').dbName,
                dbLogin: store.get('TeamPassInstallation').dbLogin,
                dbPw: store.get('TeamPassInstallation').dbPw,
                dbPort: store.get('TeamPassInstallation').dbPort,
                tablePrefix: store.get('TeamPassInstallation').tablePrefix
            },
            success: function(response) {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                
                if (response.success) {
                    $(`#${check.id}`).html('<i class="fas fa-check text-success"></i>'); 
                } else {
                    if (check.optional) {
                        $(`#${check.id}`).html('<i class="fas fa-exclamation-triangle text-warning"></i> <div class="alert alert-info" role="alert">Optional check failed: ' + response.message + '</div>'); // Warning
                    } else {
                        errorOccurred = true; // Blocking error
                        $(`#${check.id}`).html('<i class="fas fa-times text-danger"></i> <div class="alert alert-warning" role="alert">' + response.message + '</div>');
                    }
                }
            },
            error: function() {
                if (check.optional) {
                    $(`#${check.id}`).html('<i class="fas fa-exclamation-triangle text-warning"></i> <div class="alert alert-info" role="alert">Optional check failed: Network error</div>'); // Warning
                } else {
                    errorOccurred = true; // Blocking error
                    $(`#${check.id}`).html('<i class="fas fa-exclamation-triangle text-warning"></i>');
                }
            },
            complete: function() {
            currentStep++;
            // Move to the next check
            setTimeout(function() {
                performCheck(index + 1);
            }, 200);
            }
        });
        }

        // Start the checks
    performCheck(0);
}

/**
 * Perform step 5
 */
function performStep5() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');
    updateProgressBar(0, 0); // Initialize the progress bar
    $('#step5_results').html(''); // Clear previous results
    $('#step5_start_message').addClass('hidden');
    $('#step5_results_div').removeClass('hidden');

    // List of checks to perform
    const checks = [
        { id: 'check0', action: 'utf8' },
        { id: 'check1', action: 'api' },
        { id: 'check2', action: 'automatic_del' },
        { id: 'check3', action: 'cache' },
        { id: 'check4', action: 'cache_tree' },
        { id: 'check5', action: 'categories' },
        { id: 'check6', action: 'categories_folders' },
        { id: 'check7', action: 'categories_items' },
        { id: 'check8', action: 'defuse_passwords' },
        { id: 'check9', action: 'emails' },
        { id: 'check10', action: 'export' },
        { id: 'check11', action: 'files' },
        { id: 'check12', action: 'items' },
        { id: 'check13', action: 'items_change' },
        { id: 'check14', action: 'items_edition' },
        { id: 'check15', action: 'items_otp' },
        { id: 'check16', action: 'kb' },
        { id: 'check17', action: 'kb_categories' },
        { id: 'check18', action: 'kb_items' },
        { id: 'check19', action: 'ldap_groups_roles' },
        { id: 'check20', action: 'languages' },
        { id: 'check21', action: 'log_items' },
        { id: 'check22', action: 'log_system' },
        { id: 'check23', action: 'misc' },
        { id: 'check24', action: 'nested_tree' },
        { id: 'check25', action: 'notification' },
        { id: 'check26', action: 'otv' },
        { id: 'check27', action: 'background_tasks' },
        { id: 'check28', action: 'background_subtasks' },
        { id: 'check29', action: 'background_tasks_logs' },
        { id: 'check30', action: 'restriction_to_roles' },
        { id: 'check31', action: 'rights' },
        { id: 'check32', action: 'roles_title' },
        { id: 'check33', action: 'roles_values' },
        { id: 'check34', action: 'sharekeys_fields' },
        { id: 'check35', action: 'sharekeys_files' },
        { id: 'check36', action: 'sharekeys_items' },
        { id: 'check37', action: 'sharekeys_logs' },
        { id: 'check38', action: 'sharekeys_suggestions' },
        { id: 'check39', action: 'suggestion' },
        { id: 'check40', action: 'tags' },
        { id: 'check41', action: 'templates' },
        { id: 'check42', action: 'tokens' },
        { id: 'check43', action: 'users' },
        { id: 'check44', action: 'auth_failures' }
    ];
    
    let errorOccurred = false; // Variable to track errors
    let currentStep = 1; // Number of checks performed
    const totalSteps = checks.length; // Total number of checks

    // Function to perform a check
    function performCheck(index) {
        if (index >= checks.length) {
            if (errorOccurred) {
                show_loader('error', '<i class="fa-regular fa-circle-xmark text-alert"></i> Errors occurred. Please fix the issues before continuing.');
            } else {
                show_loader('success', '<i class="fas fa-check text-success"></i> All checks succeeded!', 2);

                // Update the next step
                $('#installStep').val('6');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);
            }
            return;
        }

        const check = checks[index];
        
        // Display a progress indicator
        $('#step5_results').prepend('<div>Creating table <code>'+check.action+'</code> <span id="'+check.id+'"><i class="fas fa-spinner fa-spin text-primary"></i></span></div>');

        // AJAX call
        $.ajax({
            url: './install-steps/run.step5.php',
            method: 'POST',
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                action: check['action'],
                dbHost: store.get('TeamPassInstallation').dbHost,
                dbName: store.get('TeamPassInstallation').dbName,
                dbLogin: store.get('TeamPassInstallation').dbLogin,
                dbPw: store.get('TeamPassInstallation').dbPw,
                dbPort: store.get('TeamPassInstallation').dbPort,
                tablePrefix: store.get('TeamPassInstallation').tablePrefix
            },
            success: function(response) {
                if (typeof response === 'string') {
                    response = JSON.parse(response);
                }
                
                if (response.success) {
                    $(`#${check.id}`).html('<i class="fas fa-check text-success"></i>'); // Green checkmark
                } else {
                    errorOccurred = true; // An error occurred
                    $(`#${check.id}`).html('<i class="fas fa-times text-danger"></i> <div class="alert alert-warning" role="alert">' + response.message + '</div>'); // Red cross
                }
            },
            error: function() {
                errorOccurred = true; // An error occurred
                $(`#${check.id}`).html('<i class="fas fa-exclamation-triangle text-warning"></i>'); // Warning icon
            },
            complete: function() {
                updateProgressBar(currentStep, totalSteps);
                currentStep++;
                // Move to the next check
                setTimeout(function() {
                    performCheck(index + 1);
                }, 200);
            }
        });
    }

    // Start the checks
    performCheck(0);
}


/**
 * Perform step 4
 */
function performStep4() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');

    $.ajax({
        type: 'POST',
        url: './install-steps/run.step4.php',
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            adminPassword: $('#admin_pwd').val(),
            adminEmail: $('#admin_email').val(),
            adminName: $('#admin_name').val(),
            adminLastname: $('#admin_lastname').val(),
            dbHost: store.get('TeamPassInstallation').dbHost,
            dbName: store.get('TeamPassInstallation').dbName,
            dbLogin: store.get('TeamPassInstallation').dbLogin,
            dbPw: store.get('TeamPassInstallation').dbPw,
            dbPort: store.get('TeamPassInstallation').dbPort,
            tablePrefix: store.get('TeamPassInstallation').tablePrefix
        },
        success: function(response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }

            // If success
            if (response.success && response.message) {
                show_loader('success', response.message);

                // Update the next step
                $('#installStep').val('5');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);

            } else {
                show_loader('error', response.message);
                console.error('Teampass error:', response.message);
            }
        },
        error: function(xhr) {
            show_loader('error', xhr.responseText);
            console.error('Teampass error:', xhr.status, xhr.responseText);
        }
    });
}

/**
 * 
 */
function performStep3() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');

    $.ajax({
        type: 'POST',
        url: './install-steps/run.step3.php',
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            dbHost: $('#db_host').val(),
            dbName: $('#db_bdd').val(),
            dbLogin: $('#db_login').val(),
            dbPw: $('#db_pw').val(),
            dbPort: $('#db_port').val(),
            tablePrefix: $('#tbl_prefix').val(),
            teampassAbsolutePath: store.get('TeamPassInstallation').teampassAbsolutePath,
            teampassUrl: store.get('TeamPassInstallation').teampassUrl,
            teampassSecurePath: store.get('TeamPassInstallation').teampassSecurePath
        },
        success: function(response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }

            // If success
            if (response.success && response.message) {
                show_loader('success', response.message);

                // Store the data to browser session
                store.update(
                    'TeamPassInstallation',
                    function(TeamPassInstallation) {
                        TeamPassInstallation.dbHost = $('#db_host').val(),
                        TeamPassInstallation.dbName = $('#db_bdd').val(),
                        TeamPassInstallation.dbLogin = $('#db_login').val(),
                        TeamPassInstallation.dbPw = $('#db_pw').val(),
                        TeamPassInstallation.dbPort = $('#db_port').val(),
                        TeamPassInstallation.tablePrefix = $('#tbl_prefix').val()
                    }
                )

                // Update the next step
                $('#installStep').val('4');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);

            } else {
                show_loader('error', response.message);
                console.error('Teampass error:', response.message);
            }
        },
        error: function(xhr) {
            show_loader('error', xhr.responseText);
            console.error('Teampass error:', xhr.status, xhr.responseText);
        }
    });
}


/**
 * Step 3
 * 
 */
function performStep2() {
    // List of checks to perform
    const checks = [
        { id: 'check0', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'install/' },
        { id: 'check1', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'includes/' },
        { id: 'check2', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'includes/config/' },
        { id: 'check3', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'includes/avatars/' },
        { id: 'check4', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'includes/libraries/csrfp/libs/' },
        { id: 'check5', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'includes/libraries/csrfp/js/' },
        { id: 'check6', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'includes/libraries/csrfp/log/' },
        { id: 'check7', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'files/' },
        { id: 'check8', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'upload/' },
        { id: 'check9', type: 'extension', name: 'mbstring' },
        { id: 'check10', type: 'extension', name: 'openssl' },
        { id: 'check11', type: 'extension', name: 'bcmath' },
        { id: 'check12', type: 'extension', name: 'iconv' },
        { id: 'check13', type: 'extension', name: 'xml' },
        { id: 'check14', type: 'extension', name: 'gd' },
        { id: 'check15', type: 'extension', name: 'curl' },
        { id: 'check17', type: 'php_version', version: '' },
        { id: 'check18', type: 'execution_time', limit: 30 }
    ];

    let errorOccurred = false; // Variable to track errors

    // Function to perform a check
    function performCheck(index) {
        if (index >= checks.length) {
            if (errorOccurred) {
                show_loader('error', '<i class="fa-regular fa-circle-xmark text-alert"></i> Errors occurred. Please fix the issues before continuing.');
            } else {
                show_loader('success', '<i class="fas fa-check text-success"></i> All checks succeeded!', 2);

                // Update the next step
                $('#installStep').val('3');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);
            }
            return;
        }

        const check = checks[index];
        
        // Display a progress indicator
        $(`#${check.id}`).html('<i class="fas fa-spinner fa-spin text-primary"></i>');

        // AJAX call
        $.ajax({
            url: './install-steps/run.step2.php',
            method: 'POST',
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            },
            data: check,
            success: function(response) {
                if (response.success) {
                    $(`#${check.id}`).html('<i class="fas fa-check text-success"></i>'); // Green checkmark
                } else {
                    errorOccurred = true; // An error occurred
                    $(`#${check.id}`).html('<i class="fas fa-times text-danger"></i>'); // Red cross
                }
            },
            error: function() {
                errorOccurred = true; // An error occurred
                $(`#${check.id}`).html('<i class="fas fa-exclamation-triangle text-warning"></i>'); // Warning icon
            },
            complete: function() {
                // Move to the next check
                performCheck(index + 1);
            }
        });
    }

    // Start the checks
    performCheck(0);
}


/**
 * Step 1
 */
function performStep1() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');

    $.ajax({
        type: 'POST',
        url: './install-steps/run.step1.php',
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            absolutePath: $('#absolute_path').val(),
            urlPath: $('#url_path').val(),
            securePath: $('#secure_path').val(),
        },
        success: function(response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }

            // If success
            if (response.success && response.message) {
                show_loader('success', response.message);

                // Store the data to browser session
                store.update(
                    'TeamPassInstallation',
                    function(TeamPassInstallation) {
                        // Force trailing slash if missing
                        const ensureTrailingSlash = (path) => {
                            return path.endsWith('/') ? path : path + '/';
                        };
                        TeamPassInstallation.teampassAbsolutePath = ensureTrailingSlash($('#absolute_path').val()),
                        TeamPassInstallation.teampassUrl = ensureTrailingSlash($('#url_path').val()),
                        TeamPassInstallation.teampassSecurePath = ensureTrailingSlash($('#secure_path').val())
                    }
                )

                // Update the next step
                $('#installStep').val('2');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);

            } else {
                show_loader('error', response.message);
                console.error('Teampass error:', response.message);
            }
        },
        error: function(xhr) {
            show_loader('error', xhr.responseText);
            console.error('Teampass error:', xhr.status, xhr.responseText);
        }
    });
}

/**
 * Move to next step
 */
function moveToNextStep(step) {
    // Remove any previous installation data
    if (step === 1) {
        store.remove('TeamPassInstallation');
    }

    // Update the step
    $('#installStep').val(step);

    // Just jump to next page
    $('#installation').off('submit').submit();
}

/**
 * Show loader
 */
function show_loader(statut, message, delay = 0) {
    if (statut === 'show') {
        alertify.message(message, delay).dismissOthers();
        return;
    }
    if (statut === 'hide') {
        alertify.dismissOthers();
        return;
    }
    if (statut === 'error') {
        alertify.error(message, delay).dismissOthers();
        return;
    }
    if (statut === 'success') {
        alertify.success(message, delay).dismissOthers();
        return;
    }
    if (statut === 'warning') {
        alertify.warning(message, delay).dismissOthers();
        return;
    }
}

/**
 * Browser session
 */
function browserSession(action, name, data)
{
    // Initialize the session
    if (action === 'init') {
        if (store.get(name) === 'undefined'
            || store.get(name) === undefined
        ) {
            store.set(
                name,
                data
            );
        } else {
            // Ensure all entries exist
            $(data).each(function(value, key) {
                store.update(
                    name,
                    function(bSession)
                    {
                        bSession.key = value;
                    }
                )
            });
        }
    }
}

/*
* Display a progress indicator
*/
function adjustScrollableHeight() {
    const windowHeight = window.innerHeight; // Total window height
    const headerHeight = document.querySelector('header').offsetHeight; // Header height
    const footerHeight = document.querySelector('footer').offsetHeight; // Footer height
    const otherElementsHeight = 350; // Compensate for other margins or elements
    const scrollableList = document.querySelector('.scrollable-list');

    if (scrollableList) {
        const availableHeight = windowHeight - (headerHeight + footerHeight + otherElementsHeight);
        scrollableList.style.maxHeight = availableHeight + 'px'; // Apply the calculated height
    }
}

// Adjust the height on window load and resize
window.addEventListener('load', adjustScrollableHeight);
window.addEventListener('resize', adjustScrollableHeight);

function updateProgressBar(currentStep, totalSteps) {
    const progressBar = document.getElementById('progressbar');
    
    // Calculate the progress percentage
    const percentage = Math.round((currentStep / totalSteps) * 100);
    
    // Update the progress bar properties
    progressBar.style.width = percentage + '%';  // Width of the bar
    progressBar.setAttribute('aria-valuenow', percentage);  // Update accessibility
    progressBar.textContent = percentage + '%';  // Displayed text
}
