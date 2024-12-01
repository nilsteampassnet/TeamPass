/*
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @version   
 * @file      install.js
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */



$(function() {

    browserSession(
        'init',
        'TeamPassInstallation', {
        }
    );

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
        console.log('>> '+step);
        $('#button_next').prop('disabled', true);

        // Steps
        if (step === 1) {
            // Validate the fields
            if ($('#absolute_path').val() === '') {
                show_loader('error', '<i class="fa-regular fa-circle-xmark"></i> Please enter the Absolute path');
                return;
            }
            if ($('#url_path').val() === '') {
                show_loader('error', '<i class="fa-regular fa-circle-xmark"></i> Please enter the URL path');
                return;
            }
            if ($('#secure_path').val() === '') {
                show_loader('error', '<i class="fa-regular fa-circle-xmark"></i> Please enter the Secure path');
                return;
            }
            performStep1();
            return;

        } else if (step === 2) {
            // Validate server settings
            performStep2();
            return;
            
        } else {
            console.error('Unknown step:', step);
        }
        
    });

});


//prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
//data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
/*

            randomInstalldKey: store.get('TeamPassInstallation').randomInstalldKey,
            settingsPath: store.get('TeamPassInstallation').settingsPath,
            secureFile: store.get('TeamPassInstallation').secureFile,
            securePath: store.get('TeamPassInstallation').securePath
*/

function performStep2() {

    // Liste des vérifications à effectuer
    const checks = [
        { id: 'check0', type: 'directory', path: store.get('TeamPassInstallation').teampassAbsolutePath+'install1/' },
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

    let errorOccurred = false; // Variable pour suivre les erreurs

    // Fonction pour effectuer une vérification
    function performCheck(index) {
        if (index >= checks.length) {
            // Toutes les vérifications sont terminées
            if (errorOccurred) {
                show_loader('error', '<i class="fa-regular fa-circle-xmark text-alert"></i> Des erreurs sont survenues. Veuillez corriger les problèmes avant de continuer.');
            } else {
                show_loader('success', '<i class="fas fa-check text-success"></i> Toutes les vérifications ont réussi !', 2);

                // Update the next step
                $('#installStep').val('3');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);
            }
            return;
        }

        const check = checks[index];
        
        // Affiche un indicateur de progression
        $(`#${check.id}`).html('<i class="fas fa-spinner fa-spin text-primary"></i>');

        // Appel AJAX vers run.step2.php
        $.ajax({
            url: './install/run.step2.php',
            method: 'POST',
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            },
            data: check,
            success: function(response) {
                // Analyse la réponse (attend un objet JSON : { success: true/false })
                if (response.success) {
                    $(`#${check.id}`).html('<i class="fas fa-check text-success"></i>'); // Coche verte
                } else {
                    errorOccurred = true; // Une erreur s'est produite
                    $(`#${check.id}`).html('<i class="fas fa-times text-danger"></i>'); // Croix rouge
                }
            },
            error: function() {
                errorOccurred = true; // Une erreur s'est produite
                $(`#${check.id}`).html('<i class="fas fa-exclamation-triangle text-warning"></i>'); // Icône de problème
            },
            complete: function() {
                // Passer à la vérification suivante
                performCheck(index + 1);
            }
        });
    }

    // Démarrer les vérifications
    performCheck(0);
}


/**
 * Step 1
 */
function performStep1() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');

    $.ajax({
        type: 'POST',
        url: './install/run.step1.php',
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            action: 'step1',
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
                        TeamPassInstallation.teampassAbsolutePath = $('#absolute_path').val(),
                        TeamPassInstallation.teampassUrl = $('#url_path').val(),
                        TeamPassInstallation.teampassSecurePath = $('#secure_path').val()
                    }
                )

                // Update the next step
                $('#installStep').val('2');

                // Handle the buttons
                $('#button_next').prop('disabled', false);
                $('#button_start').prop('disabled', true);

                /*
                // Post the form
                setTimeout(function() {
                    $('#installation').off('submit').submit();
                }, 1000);
                */
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


function step0__() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');

    $.ajax({
        type: 'POST',
        url: 'install/run.step0.php',
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            action: 'step0'
        },
        success: function(response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }
            console.info(response);

            if (response.success && response.message) {
                show_loader('success', response.message);

                // Update the step
                $('#installStep').val('1');

                // Store the data to browser session
                store.update(
                    'TeamPassInstallation',
                    function(TeamPassInstallation) {
                        TeamPassInstallation.randomInstalldKey = response.data.randomInstalldKey,
                        TeamPassInstallation.settingsPath = response.data.rootPath,
                        TeamPassInstallation.secureFile = response.data.secureFile,
                        TeamPassInstallation.securePath = response.data.securePath,
                        TeamPassInstallation.settingsFileStatus = response.data.status
                    }
                )

                // Post the form
                setTimeout(function() {
                    $('#installation').off('submit').submit();
                }, 1000);
            } else {
                console.error('Error:', response.message);
            }
        },
        error: function(xhr) {
            show_loader('error', xhr.responseText);
            console.error('Error:', xhr.status, xhr.responseText);
        }
    });
}


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


function hide_loader() {
    //alertify.
}


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