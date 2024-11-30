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
    $("#button_next, #button_start").on('click', function(event) {
        event.preventDefault();
        var step = parseInt($(this).data('step'));
        console.log('>> '+step);
        $('#button_next').prop('disabled', true);

        // Step - 1
        if (step === 0) {
            step0();
            return;
        } else if (step === 1) {
            if ($('#absolute_path').val() === '') {
                show_loader('error', 'Please enter the Absolute path');
                return;
            }
            if ($('#url_path').val() === '') {
                show_loader('error', 'Please enter the URL path');
                return;
            }
            if ($('#secure_path').val() === '') {
                show_loader('error', 'Please enter the Secure path');
                return;
            }
            step1();
            return;
        } else {
            console.error('Unknown step:', step);
        }
        
    });

});


//prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
//data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');

/**
 * Step 1
 */
function step1() {
    show_loader('warning', '<i class="fa fa-spinner fa-spin"></i> Please wait...');
console.log(store.get('TeamPassInstallation'));
    $.ajax({
        type: 'POST',
        url: 'install/run.step1.php',
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            action: 'step1',
            absolutePath: $('#absolute_path').val(),
            urlPath: $('#url_path').val(),
            securePathField: $('#secure_path').val(),
            randomInstalldKey: store.get('TeamPassInstallation').randomInstalldKey,
            settingsPath: store.get('TeamPassInstallation').settingsPath,
            secureFile: store.get('TeamPassInstallation').secureFile,
            securePath: store.get('TeamPassInstallation').securePath
        },
        success: function(response) {
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }
            console.info(response);

            if (response.success && response.message) {
                show_loader('success', response.message);

                // Update the nextstep
                $('#installStep').val('2');

                // Post the form
                setTimeout(function() {
                    $('#installation').off('submit').submit();
                }, 1000);
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
 * Step 0
 */
function step0() {
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