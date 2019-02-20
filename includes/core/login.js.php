<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2019 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

?>
<script type="text/javascript">
// On page load
$(function() {
    // Set focus on login input
    $('#login').focus();

    // Manage DUO SEC login
    if ($("#2fa_user_selection").val() === "duo" && $("#duo_sig_response").val() !== "") {
        $("#login").val($("#duo_login").val());
        $("#pw").val($("#duo_pwd").val());

        // checking that response is corresponding to user credentials
        $.post(
            "sources/identify.php",
            {
                type :            "identify_duo_user_check",
                login:            sanitizeString($("#login").val()),
                pwd:              sanitizeString($("#duo_pwd").val()),
                sig_response:     $("#duo_sig_response").val()
            },
            function(data) {
                console.log("After identify_duo_user_check:");
                console.log(data);
                var ret = data[0].resp.split("|");
                if (ret[0] === "ERR") {
                    $("#div-2fa-duo-progress")
                        .addClass('alert alert-info ')
                        .html('<i class="fas fa-exclamation-triangle text-danger mr-2"></i>' + ret[1]);
                } else {
                    // finally launch identification process inside Teampass.
                    alertify
                        .message(
                            '<i class="fas fa-cog fa-spin fa-lg mr-2"></i><?php echo langHdl('please_wait'); ?>',
                            0
                        )
                        .dismissOthers();
                    

                    console.log(window.atob($("#duo_data").val()));
                    $.post(
                        "sources/identify.php",
                        {
                            type :     "identify_user",
                            data :     prepareExchangedData(window.atob($("#duo_data").val()), "encode", "<?php echo $_SESSION['key']; ?>")
                        },
                        function(receivedData) {
                            var data = JSON.parse(receivedData);
                            
                            $('#div-2fa-duo, #div-2fa-duo-progress').removeClass('hidden');

                            if (data.error !== false) {
                                alertify.message('<?php echo langHdl('done'); ?>', 1).dismissOthers();
                                $("#div-2fa-duo-progress")
                                    .html('<i class="fas fa-exclamation-triangle text-danger mr-2"></i>' + data.message);
                            } else {
                                //redirection for admin is specific
                                $("#div-2fa-duo-progress")
                                    .html('<i class="fas fa-info-circle text-info mr-2"></i><?php echo langHdl('please_wait'); ?>');
                                if (data.user_admin !== 1) {
                                    setTimeout(
                                        function(){
                                            window.location.href="index.php?page=items";
                                        },
                                        1
                                    );
                                } else {
                                    setTimeout(
                                        function(){
                                            window.location.href="index.php?page=manage_main";
                                        },
                                        1
                                    );
                                }
                            }
                        }
                    );
                }
            },
            "json"
        );
    }

    // Click on log in button
    $('#but_identify_user').click(function() {
        launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>');
    });

    // Click on forgot password button
    $('#link_forgot_user_pwd').click(function() {
        alertify.prompt(
            '<?php echo langHdl('forgot_my_pw'); ?>',
            '<?php echo langHdl('forgot_my_pw_text'); ?>',
            '<?php echo langHdl('your_login'); ?>'
            , function(evt, value) {
                alertify
                    .message(
                        '<?php echo '<span class="fas fa-cog fa-spin fa-lg"></span>&nbsp;'.langHdl('please_wait'); ?>',
                        0
                    )
                    .dismissOthers();
                $.post(
                    "sources/main.queries.php",
                    {
                        type  : "recovery_send_pw_by_email",
                        login : value
                    },
                    function(data) {
                        if (data[0].error !== '') {
                            alertify.error(data[0].message, 10).dismissOthers(); 
                        } else {
                            alertify.success(data[0].message).dismissOthers(); 
                        }
                    },
                    "json"
                );
            }
            , function() {
                alertify.error('Cancel');
            }
        );
    });

    // Show tooltips
    $('.infotip').tooltip();
});

var twoFaMethods = parseInt($("#2fa_google").val())
  + parseInt($("#2fa_agses").val())
  + parseInt($("#2fa_duo").val())
  + parseInt($("#2fa_yubico").val()
);
if (twoFaMethods > 1) {
    // At least 2 2FA methods have to be shown
    var loginButMethods = ['google', 'agses', 'duo'];

    // Show methods
    $("#2fa_selector").removeClass("hidden");

    // Hide login button
    $('#div-login-button').addClass('hidden');

    // Unselect any method
    $(".2fa_selector_select").prop('checked', false);

    // Prepare buttons
    $('.2fa-methods').radiosforbuttons({
        margin: 20,
        vertical: false,
        group: false,
        autowidth: true
    });

    // Handle click
    $('.radiosforbuttons-2fa_selector_select')
    .click(function() {
        $('.div-2fa-method').addClass('hidden');
        
        var twofaMethod = $(this).text().toLowerCase();

        // Save user choice
        $('#2fa_user_selection').val(twofaMethod);

        // Show 2fa method div
        $('#div-2fa-'+twofaMethod).removeClass('hidden');

        // Show login button if required
        if ($.inArray(twofaMethod, loginButMethods) !== -1) {
            $('#div-login-button').removeClass('hidden');
        } else {
            $('#div-login-button').addClass('hidden');
        }

        // Make focus
        if (twofaMethod === 'google') {
            $('#ga_code').focus();
        } else if (twofaMethod === 'yubico') {
            $('#yubico_key').focus();
        } else if (twofaMethod === 'agses') {
            startAgsesAuth();
        }
    });
} else if (twoFaMethods === 1) {
    // One 2FA method is expected
    if ($('#2fa_google').val() === '1') {
        $('#div-2fa-google').removeClass('hidden');
    } else if ($('#2fa_yubico').val() === '1') {
        $('#div-2fa-yubico').removeClass('hidden');
    } else if ($('#2fa_agses').val() === '1') {
        $('#div-2fa-agses').removeClass('hidden');
    }
    $('#login').focus();
} else {
    // No 2FA methods is expected
    $('#2fa_methods_selector').addClass('hidden');
}

$('.submit-button').keypress(function(event){
    if (event.keyCode === 10 || event.keyCode === 13) {
        launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', '');
        event.preventDefault();
    }
});

$('#yubico_key').change(function(event) {
    launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', '');
    event.preventDefault();
});


$(document).on('click', '#register-yubiko-key', function () {
    $('#yubiko-new-key').removeClass('hidden');
});


$("#new-user-password")
    .simplePassMeter({
        "requirements": {},
        "container": "#new-user-password-strength",
        "defaultText" : "<?php echo langHdl('index_pw_level_txt'); ?>",
        "ratings": [
            {"minScore": 0,
                "className": "meterFail",
                "text": "<?php echo langHdl('complex_level0'); ?>"
            },
            {"minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo langHdl('complex_level1'); ?>"
            },
            {"minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo langHdl('complex_level2'); ?>"
            },
            {"minScore": 60,
                "className": "meterGood",
                "text": "<?php echo langHdl('complex_level3'); ?>"
            },
            {"minScore": 70,
                "className": "meterGood",
                "text": "<?php echo langHdl('complex_level4'); ?>"
            },
            {"minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo langHdl('complex_level5'); ?>"
            },
            {"minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo langHdl('complex_level6'); ?>"
            }
        ]
    })
    .bind({
        "score.simplePassMeter" : function(jQEvent, scorescore) {
            $("#new-user-password-complexity-level").val(score);
        }
    }).change({
        "score.simplePassMeter" : function(jQEvent, scorescore) {
            $("#new-user-password-complexity-level").val(score);
        }
    });


/**
 * Undocumented function
 *
 * @return void
 */
$('#but_confirm_new_password').click(function() {
    if ($('#new-user-password').val() !== ''
        && $('#new-user-password').val() === $('#new-user-password-confirm').val()
    ) {
        // Check if current pwd expected
        if ($('#current-user-password').val() === '' && $('#current-user-password-div').hasClass('hidden') === false) {
            // Alert
            alertify.set('notifier','position', 'top-center');
            alertify
                .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('current_password_mandatory'); ?>', 5)
                .dismissOthers(); 
            return false;
        }

        // Prepare data
        var data = {
            "new_pw" : sanitizeString($("#new-user-password").val()),
            "current_pw" : sanitizeString($("#current-user-password").val()),
            "complexity" : $('#new-user-password-complexity-level').val(),
        };

        // Send query
        $.post(
            'sources/main.queries.php',
            {
                type                : 'change_pw',
                change_pw_origine   : 'user_change',
                key                 : '<?php echo $_SESSION['key']; ?>',
                data                : prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>')
            },
            function(data) {
                data = JSON.parse(data);
                if (data.error == 'complexity_too_low') {
                    // Alert
                    alertify.set('notifier','position', 'top-center');
                    alertify
                        .error('<i class="fa fa-ban fa-lg mr-3"></i>' + data.message, 5)
                        .dismissOthers(); 
                    // Clear
                    $('#new-user-password, #new-user-password-confirm').val('');
                } else if (data.error == 'pwd_hash_not_correct') {
                    // Alert
                    alertify.set('notifier','position', 'top-center');
                    alertify
                        .error('<i class="fa fa-ban fa-lg mr-3"></i>' + data.message, 5)
                        .dismissOthers();
                    // Clear
                    $('#new-user-password, #new-user-password-confirm').val('');
                } else {
                    location.reload(true);
                }
            }
        );
    } else {
        // Alert
        alertify.set('notifier','position', 'top-center');
        alertify
            .error('<i class="fa fa-ban fa-lg mr-3"></i><?php echo langHdl('index_pw_error_identical'); ?>', 5)
            .dismissOthers(); 
    }
});

/**
 * 
 */
function launchIdentify(isDuo, redirect, psk)
{
    if (redirect == undefined) {
        redirect = ""; //Check if redirection
    }
    
    // Check credentials are set
    if ($("#pw").val() === "" || $("#login").val() === "") {
            // Show warning
            if ($("#pw").val() === "") $("#pw").addClass("ui-state-error");
            if ($("#login").val() === "") $("#login").addClass("ui-state-error");

            // Clear 2fa code
            if ($("#yubico_key").length > 0) {
                $("#yubico_key").val("");
            }
            if ($("#ga_code").length > 0) {
                $("#ga_code").val("");
            }

            return false;
        }

        // 2FA method
        var user2FaMethod = $("input[name=2fa_selector_select]:checked").data('mfa');

        if (user2FaMethod !== "") {
            if ((user2FaMethod === "yubico" && $("#yubico_key").val() === "")
                || (user2FaMethod === "google" && $("#ga_code").val() === "")
            ) {
                return false;
            }
        } else {

        }

    // launch identification
    showAlertify('<span class="fa fa-cog fa-spin fa-2x"></span>', 0, 'top-center', 'notify');

    // Clear localstorage
    store.remove('teampassApplication');
    store.remove('teampassSettings');
    store.remove('teampassUser');
    store.remove('teampassItem');

    //create random string
    var randomstring = CreateRandomString(10);

    // get timezone
    var d = new Date();
    var TimezoneOffset = d.getTimezoneOffset()*60;

    // get some info
    var client_info = '';

    // Get 2fa
    $.post(
        'sources/identify.php',
        {
            type : 'get2FAMethods'
        },
        function(data) {
            var data = JSON.parse(data),
                mfaData = {};
            console.log("get2FAMethods")
            //console.log(data)
            console.log('>> '+user2FaMethod)

            // Google 2FA
            if (user2FaMethod === 'agses' && $('#agses_code').val() !== undefined) {
                mfaData['agses_code'] = $('#agses_code').val();
            }
    
            // Google 2FA
            if (user2FaMethod === 'google' && $('#ga_code').val() !== undefined) {
                mfaData['GACode'] = $('#ga_code').val();
            }
            
            // Yubico
            if (user2FaMethod === 'yubico' && $('#yubico_key').val() !== undefined) {
                mfaData['yubico_key'] = $('#yubico_key').val();
                mfaData['yubico_user_id'] = $('#yubico_user_id').val();
                mfaData['yubico_user_key'] = $('#yubico_user_key').val();                
            }

            // Other values
            mfaData['login'] = sanitizeString($('#login').val());
            mfaData['pw'] = sanitizeString($('#pw').val());
            mfaData['duree_session'] = sanitizeString($('#session_duration').val());
            mfaData['screenHeight'] = $('body').innerHeight();
            mfaData['randomstring'] = randomstring;
            mfaData['TimezoneOffset'] = TimezoneOffset;
            mfaData['client'] = client_info;
            mfaData['user_2fa_selection'] = user2FaMethod;

            // Handle if DUOSecurity is enabled
            if (user2FaMethod === 'agses' && $('#agses_code').val() === '') {
                startAgsesAuth();
            } else if (user2FaMethod !== 'duo' || $('#login').val() === 'admin') {
                identifyUser(redirect, psk, mfaData, randomstring);
            } else {
                // Handle if DUOSecurity is enabled
                $('#duo_data').val(window.btoa(JSON.stringify(mfaData)));
                loadDuoDialog();
            }
        }
    );
}

// DUO box - identification
function loadDuoDialog()
{
    $('#div-2fa-duo').removeClass('hidden');
    $('#div-2fa-duo-progress')
        .load(
            '<?php echo $SETTINGS['cpassman_url']; ?>/includes/core/duo.load.php',
            null,
            function(responseText, textStatus, xhr) {
                if (textStatus === "error") {
                    alert("Error while loading " + url + "\n\n"+responseText);
                }
            }
        );
}


//Identify user
function identifyUser(redirect, psk, data, randomstring)
{
    // Check if session is still existing
    $.post(
        "sources/checks.php",
        {
            type : "checkSessionExists"
        },
        function(check_data) {
            if (parseInt(check_data) === 1) {
                //send query
                $.post(
                    "sources/identify.php",
                    {
                        type : "identify_user",
                        data : prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>')
                    },
                    function(receivedData) {
                        var data = JSON.parse(receivedData);
                        console.log(data);

                        if (data.value === randomstring) {
                            $("#connection_error").hide();
                            // Check if 1st connection
                            if (data.first_connection === true || data.password_change_expected === true) {
                                // Show field for current password
                                if (data.password_change_expected === true) {
                                    $('#current-user-password-div').removeClass('hidden');
                                }
                                $('.confirm-password-card-body').removeClass('hidden');
                                $('.login-card-body').addClass('hidden');
                                $('#confirm-password-level').html(data.password_complexity);

                                alertify
                                    .message('<i class="fas fa-info fa-lg mr-3"></i><?php echo langHdl('done'); ?>', 1)
                                    .dismissOthers(); 
                                    
                                return false;
                            }

                            store.update(
                                'teampassUser',
                                {},
                                function(teampassUser) {
                                    teampassUser.sessionDuration = 3600;
                                }
                            );
                            
                            //redirection for admin is specific
                            if (parseInt(data.user_admin) === 1) {
                                window.location.href='index.php?page=admin';
                            } else if (data.initial_url !== '' && data.initial_url !== null) {
                                window.location.href=data.initial_url;
                            } else {
                                window.location.href = 'index.php?page=items';
                            }
                        } else if (data.error === false && data.mfaStatus === 'ga_temporary_code_correct') {
                            $('#div-2fa-google-qr')
                                .removeClass('hidden')
                                .html('<div class="col-12 alert alert-info">' +
                                '<p class="text-center">' + data.value + '</p>' +
                                '<p class="text-center"><i class="fas fa-mobile-alt fa-lg mr-1"></i>' +
                                '<?php echo langHdl('mfa_flash'); ?></p></div>');
                            $('#ga_code')
                                .val('')
                                .focus();
                            alertify
                                .message('<i class="fas fa-info fa-lg mr-3"></i><?php echo langHdl('done'); ?>', 1)
                                .dismissOthers(); 
                        } else if (data.error === true) {
                            alertify.set('notifier','position', 'top-center');
                            alertify
                                .error('<i class="fas fa-ban fa-lg mr-3"></i>' + data.message, 5)
                                .dismissOthers(); 
                        } else {
                            showAlertify('<?php echo langHdl('error_bad_credentials'); ?>', 5, 'top-right');
                        }

                        // Clear Yubico
                        if ($("#yubico_key").length > 0) {
                            $("#yubico_key").val("");
                        }
                    }
                );
            } else {
                // No session was found, warn user
                // Attach the CSRFP tokenn to the form to prevent against error 403
                var csrfp = check_data.split(";");
                $("#form_identify").append(
                    "<input type='hidden' name='"+csrfp[0]+"' value='"+csrfp[1]+"' />" +
                    "<input type='hidden' name='auto_log' value='1' />"
                );

                // Warn user
                alertify.set('notifier','position', 'top-center');
                alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>Browser session is now expired. The page will be automatically reloaded in 2 seconds.', 5)
                    .dismissOthers(); 

                // Delay page submit
                $(this).delay(2000).queue(function() {
                    $("#form_identify").submit();
                    $(this).dequeue();
                });
            }
        }
    );
}

function getGASynchronization()
{
    if ($("#login").val() != "" && $("#pw").val() != "") {
        $("#ajax_loader_connexion").show();
        $("#connection_error").hide();
        $("#div_ga_url").hide();
        
        data = {
            'login'     : $("#login").val(),
            'pw'        : $("#pw").val(),
            'send_mail' : 1
        }
        $.post(
            'sources/main.queries.php',
            {
                type    : 'ga_generate_qr',
                data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                if (data.error !== false) {
                    // Show error
                    alertify
                        .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                        .dismissOthers();
                } else {
                    // Inform user
                    alertify
                        .success('<?php echo langHdl('share_sent_ok'); ?>', 1)
                        .dismissOthers();
                    //$("#div_ga_url").show(); -> TODO
                }
            }
        );
    } else {
        $("#connection_error").html("<?php echo langHdl('ga_enter_credentials'); ?>").show();
    }
}

function send_user_new_temporary_ga_code() {
    // Check login and password
    if ($("#login").val() === "" || $("#pw").val() === "") {
        $("#connection_error").html("<?php echo langHdl('ga_enter_credentials'); ?>").show();
        return false;
    }
    $("#div_loading").show();
    $("#connection_error").html("").hide();

    data = {
        'login'     : $("#login").val(),
        'pw'        : $("#pw").val(),
        'send_mail' : 1
    }
    $.post(
        'sources/main.queries.php',
        {
            type    : 'ga_generate_qr',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            console.log(data);

            if (data.error !== false) {
                // Show error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                    .dismissOthers();
            } else {
                // Inform user
                alertify
                    .success('<?php echo langHdl('share_sent_ok'); ?>', 1)
                    .dismissOthers();
                //$("#div_ga_url").show(); -> TODO
            }
        }
    );
}

</script>