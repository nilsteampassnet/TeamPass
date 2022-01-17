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
 * @file      login.js.php
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

if (isset($_SESSION['CPM']) === false || (int) $_SESSION['CPM'] !== 1) {
    die('Hacking attempt...');
}

?>
<script type="text/javascript">
    // On page load
    $(function() {
        // Set focus on login input
        $('#login').focus();

        // Prepare iCheck format for checkboxes
        $('input[type="checkbox"].flat-blue').iCheck({
            checkboxClass: 'icheckbox_flat-blue'
        });

        // Manage DUO SEC login
        if ($("#2fa_user_selection").val() === "duo" && $("#duo_sig_response").val() !== "") {
            $("#login").val($("#duo_login").val());
            $("#pw").val($("#duo_pwd").val());

            // checking that response is corresponding to user credentials
            $.post(
                "sources/identify.php", {
                    type: "identify_duo_user_check",
                    login: sanitizeString($("#login").val()),
                    pwd: sanitizeString($("#duo_pwd").val()),
                    sig_response: $("#duo_sig_response").val()
                },
                function(data) {
                    console.log("After identify_duo_user_check:");
                    console.log(data);
                    var ret = data[0].authenticated_username.split("|");
                    if (ret[0] === "ERR") {
                        $("#div-2fa-duo-progress")
                            .addClass('alert alert-info ')
                            .html('<i class="fas fa-exclamation-triangle text-danger mr-2"></i>' + ret[1]);
                    } else {
                        // finally launch identification process inside Teampass.
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
                        );

                        $.post(
                            "sources/identify.php", {
                                type: "identify_user",
                                data: prepareExchangedData(window.atob($("#duo_data").val()), "encode", "<?php echo $_SESSION['key']; ?>"),
                            },
                            function(receivedData) {
                                var data = prepareExchangedData(receivedData, 'decode', "<?php echo $_SESSION['key']; ?>");
                                console.log(data);

                                if (data.error !== false) {
                                    // Show error
                                    toastr.remove();
                                    toastr.error(
                                        data.message,
                                        '<?php echo langHdl('caution'); ?>', {
                                            timeOut: 5000,
                                            progressBar: true
                                        }
                                    );
                                    // ---
                                } else if (data.message === 'ask_for_otc') {
                                    // Manage the case where the user keys OTC is asked
                                    $('#user-one-time-code-card-body').removeClass('hidden');
                                    $('#but_identify_user').addClass('hidden');
                                    $('#login, #pw').addAttribute('disabled');

                                    toastr.remove();
                                    toastr.warning(
                                        '<?php echo langHdl('one_time_code_expected'); ?>',
                                        '', {
                                            timeOut: 5000
                                        }
                                    );

                                    // User clicks on button
                                    $(document).on('click', '#but_confirm_otc', function() {
                                        // OTC is mandatory
                                        if ($('#user-one-time-code').val() === '') {
                                            return false;
                                        }

                                        toastr.remove();
                                        toastr.info(
                                            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
                                        );

                                        // Change the User's Private key
                                        parameters = {
                                            'user_id': data.user_id,
                                            'password': $('#pw').val(),
                                            'otc': $('#user-one-time-code').val(),
                                        }
                                        $.post(
                                            "sources/users.queries.php", {
                                                type: "change_user_privkey_with_otc",
                                                key: store.get('teampassUser').sessionKey,
                                                data: prepareExchangedData(JSON.stringify(parameters), "encode", store.get('teampassUser').sessionKey),
                                            },
                                            function(receivedData) {
                                                receivedData = prepareExchangedData(receivedData, 'decode', store.get('teampassUser').sessionKey);

                                                if (receivedData.error !== false) {
                                                    // Show error
                                                    toastr.remove();
                                                    toastr.error(
                                                        receivedData.message,
                                                        '<?php echo langHdl('caution'); ?>', {
                                                            timeOut: 5000,
                                                            progressBar: true
                                                        }
                                                    );
                                                } else {
                                                    // redirection for admin is specific
                                                    if (data.user_admin !== 1) {
                                                        setTimeout(
                                                            function() {
                                                                window.location.href = "index.php?page=items";
                                                            },
                                                            1
                                                        );
                                                    } else {
                                                        setTimeout(
                                                            function() {
                                                                window.location.href = "index.php?page=manage_main";
                                                            },
                                                            1
                                                        );
                                                    }
                                                }
                                            }
                                        );
                                    });

                                    // ---
                                } else {
                                    // redirection for admin is specific
                                    if (data.user_admin !== 1) {
                                        setTimeout(
                                            function() {
                                                window.location.href = "index.php?page=items";
                                            },
                                            1
                                        );
                                    } else {
                                        setTimeout(
                                            function() {
                                                window.location.href = "index.php?page=manage_main";
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
            console.log('User starts auth');
            launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>');
        });

        // Show tooltips
        $('.infotip').tooltip();
    });

    // Ensure session is ready in case of disconnection
    if (store.get('teampassSettings') === undefined) {
        store.set(
            'teampassSettings', {},
            function(teampassSettings) {}
        );
        $.when(
            // Load teampass settings
            loadSettings()
        ).then(function() {
            showMFAMethod();
        });

    } else {
        showMFAMethod();
    }



    $('.submit-button').keypress(function(event) {
        if (event.keyCode === 10 || event.keyCode === 13) {
            launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', '');
            event.preventDefault();
        }
    });

    $('#yubico_key').change(function(event) {
        launchIdentify('', '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', '');
        event.preventDefault();
    });


    $(document).on('click', '#register-yubiko-key', function() {
        $('#yubiko-new-key').removeClass('hidden');
    });


    $("#new-user-password")
        .simplePassMeter({
            "requirements": {},
            "container": "#new-user-password-strength",
            "defaultText": "<?php echo langHdl('index_pw_level_txt'); ?>",
            "ratings": [{
                    "minScore": 0,
                    "className": "meterFail",
                    "text": "<?php echo langHdl('complex_level0'); ?>"
                },
                {
                    "minScore": 25,
                    "className": "meterWarn",
                    "text": "<?php echo langHdl('complex_level1'); ?>"
                },
                {
                    "minScore": 50,
                    "className": "meterWarn",
                    "text": "<?php echo langHdl('complex_level2'); ?>"
                },
                {
                    "minScore": 60,
                    "className": "meterGood",
                    "text": "<?php echo langHdl('complex_level3'); ?>"
                },
                {
                    "minScore": 70,
                    "className": "meterGood",
                    "text": "<?php echo langHdl('complex_level4'); ?>"
                },
                {
                    "minScore": 80,
                    "className": "meterExcel",
                    "text": "<?php echo langHdl('complex_level5'); ?>"
                },
                {
                    "minScore": 90,
                    "className": "meterExcel",
                    "text": "<?php echo langHdl('complex_level6'); ?>"
                }
            ]
        })
        .bind({
            "score.simplePassMeter": function(jQEvent, score) {
                $("#new-user-password-complexity-level").val(score);
            }
        }).change({
            "score.simplePassMeter": function(jQEvent, score) {
                $("#new-user-password-complexity-level").val(score);
            }
        });


    /**
     * Undocumented function
     *
     * @return void
     */
    $('#but_confirm_new_password').click(function() {
        if ($('#new-user-password').val() !== '' &&
            $('#new-user-password').val() === $('#new-user-password-confirm').val()
        ) {
            // Check if current pwd expected
            if ($('#current-user-password').val() === '' &&
                $('#current-user-password-div').hasClass('hidden') === false &&
                $('#confirm-no-current-password').is(':checked') === false
            ) {
                // Alert
                toastr.remove();
                toastr.error(
                    '<?php echo langHdl('current_password_mandatory'); ?>',
                    '<?php echo langHdl('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // Prepare data
            var data = {
                "new_pw": sanitizeString($("#new-user-password").val()),
                "current_pw": sanitizeString($("#current-user-password").val()),
                "complexity": $('#new-user-password-complexity-level').val(),
                "change_request": 'reset_user_password_expected',
                "user_id": store.get('teampassUser').user_id,
            };

            // Send query
            $.post(
                'sources/main.queries.php', {
                    type: 'change_pw',
                    key: store.get('teampassUser').sessionKey,
                    data: prepareExchangedData(JSON.stringify(data), 'encode', store.get('teampassUser').sessionKey)
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', store.get('teampassUser').sessionKey);
                    console.log(data);

                    if (data.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('password_changed'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );

                        // Check if user has a old DEFUSE PSK
                        if (store.get('teampassUser').user_has_psk === true) {
                            $('#card-user-treat-psk').removeClass('hidden');
                            $('.confirm-password-card-body').addClass('hidden');
                        } else {
                            // Reload the current page
                            location.reload(true);
                        }
                    }
                }
            );
        } else {
            // Alert
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('confirmation_seems_wrong'); ?>',
                '<?php echo langHdl('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });


    $(document).on('click', '#but_confirm_defuse_psk', function() {
        console.log("START RE-ENCRYPTING PERSONAL ITEMS --> " + $('#user-old-defuse-psk').val());

        if ($('#user-old-defuse-psk').val() !== '') {
            toastr.remove();
            toastr.info(
                '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );

            // Prepare data
            var data = {
                "psk": $("#user-old-defuse-psk").val(),
            };

            //
            $.post(
                "sources/main.queries.php", {
                    type: "convert_items_with_personal_saltkey_start",
                    data: prepareExchangedData(JSON.stringify(data), "encode", store.get('teampassUser').sessionKey),
                    key: store.get('teampassUser').sessionKey
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', store.get('teampassUser').sessionKey);
                    console.log(data);

                    // Is there an error?
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        if (data.items_list.length > 0 || data.files_list.length > 0) {
                            encryptPersonalItems(data.items_list, data.files_list, data.psk);
                        } else {
                            // Finished
                            toastr.remove();
                            toastr.success(
                                '<?php echo langHdl('alert_page_will_reload'); ?>',
                                '', {
                                    timeOut: 10000
                                }
                            );
                            location.reload();
                        }

                        /**
                         *
                         */
                        function encryptPersonalItems(items, files, psk) {
                            console.log('----\n' + psk);
                            console.log(items);
                            if (items.length > 0 || files.length > 0) {
                                // Manage files & items
                                // Prepare data
                                var data = {
                                    "psk": psk,
                                    'files': files,
                                    'items': items,
                                };

                                // Launch query
                                $.post(
                                    "sources/main.queries.php", {
                                        type: "convert_items_with_personal_saltkey_progress",
                                        data: prepareExchangedData(JSON.stringify(data), "encode", store.get('teampassUser').sessionKey),
                                        key: '<?php echo $_SESSION['key']; ?>'
                                    },
                                    function(data) {
                                        data = prepareExchangedData(data, store.get('teampassUser').sessionKey);

                                        // Is there an error?
                                        if (data.error === true) {
                                            toastr.remove();
                                            toastr.error(
                                                data.message,
                                                '<?php echo langHdl('caution'); ?>', {
                                                    timeOut: 5000,
                                                    progressBar: true
                                                }
                                            );
                                        } else {
                                            // Loop on items / files
                                            encryptPersonalItems(data.items_list, data.files_list);
                                        }
                                    }
                                );
                            } else {
                                // Finisehd
                                toastr.remove();
                                toastr.success(
                                    '<?php echo langHdl('alert_page_will_reload'); ?>',
                                    '', {
                                        timeOut: 10000
                                    }
                                );
                                location.reload();
                            }
                        }
                    }
                }
            );
        } else {
            // Alert
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('empty_psk'); ?>',
                '<?php echo langHdl('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });

    $(document).on('click', '#but_confirm_forgot_defuse_psk', function() {
        // Is the user sure?
        showModalDialogBox(
            '#warningModal',
            '<?php echo langHdl('your_attention_is_required'); ?>',
            '<?php echo langHdl('i_cannot_remember_info'); ?>',
            '<?php echo langHdl('confirm'); ?>',
            '<?php echo langHdl('cancel'); ?>'
        );

        // Actions on modal buttons
        $(document).on('click', '#warningModalButtonAction', function() {
            // Disable buttons && field
            $('.btn-block, #user-old-defuse-psk').attr("disabled", "disabled");

            // Now launch query
            $.post(
                "sources/main.queries.php", {
                    type: "user_forgot_his_personal_saltkey",
                    key: store.get('teampassUser').sessionKey
                },
                function(data) {
                    data = prepareExchangedData(data, store.get('teampassUser').sessionKey);

                    // Is there an error?
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('alert_page_will_reload'); ?>',
                            '', {
                                timeOut: 10000
                            }
                        );

                        location.reload();
                    }
                }
            );
        });
        $(document).on('click', '#warningModalButtonClose', function() {
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('cancel'); ?>',
                '<?php echo langHdl('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        });
    });

    /**
     * 
     */
    function launchIdentify(isDuo, redirect, psk) {
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
        //console.log(user2FaMethod)
        if (user2FaMethod !== "") {
            if ((user2FaMethod === "yubico" && $("#yubico_key").val() === "") ||
                (user2FaMethod === "otp" && $("#ga_code").val() === "")
            ) {
                return false;
            }
        }

        // launch identification
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>',
            '', {
                positionClass: "toast-top-center"
            }
        );

        // Clear localstorage
        store.remove('teampassApplication');
        store.remove('teampassSettings');
        store.remove('teampassUser');
        store.remove('teampassItem');

        //create random string
        var randomstring = CreateRandomString(10);

        // get timezone
        var d = new Date();
        var TimezoneOffset = d.getTimezoneOffset() * 60;

        // get some info
        var client_info = '';
        
        // Get 2fa
        $.post(
            'sources/identify.php', {
                type: 'get2FAMethods'
            },
            function(data) {
                try {
                    data = prepareExchangedData(
                        data,
                        "decode",
                        "<?php echo $_SESSION['key']; ?>"
                    );
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('server_answer_error'); ?>',
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                    return false;
                }
                console.log(data);

                var mfaData = {},
                    mfaMethod = '',
                    nbMfaMethods = 0;

                // Get selected user MFA method
                if ($(".2fa_selector_select").length > 1) {
                    mfaMethod = $(".2fa_selector_select:checked").data('mfa');
                } else {
                    if (data.google === true) {
                        mfaMethod = 'google';
                        nbMfaMethods ++;
                    } else if (data.duo === true) {
                        mfaMethod = 'duo';
                        nbMfaMethods ++;
                    } else if (data.yubico === true) {
                        mfaMethod = 'yubico';
                        nbMfaMethods ++;
                        //} else if (data.agses === true) {
                        //    mfaMethod = 'agses';
                    }
                }

                if (nbMfaMethods > 1) {
                    $('#2fa_methods_selector').removeClass('hidden');
                }


                // Google 2FA
                if (mfaMethod === 'google' && data.google === true) {
                    if ($('#ga_code').val() !== undefined && $('#ga_code').val() !== '') {
                        mfaData['GACode'] = $('#ga_code').val();
                    } else {
                        $('#ga_code').focus();
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('ga_bad_code'); ?>',
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        //return false;
                    }
                }

                // Yubico
                if (mfaMethod === 'yubico' && data.yubico === true) {
                    if ($('#yubico_key').val() !== undefined && $('#yubico_key').val() !== '') {
                        mfaData['yubico_key'] = $('#yubico_key').val();
                        mfaData['yubico_user_id'] = $('#yubico_user_id').val();
                        mfaData['yubico_user_key'] = $('#yubico_user_key').val();
                    } else {
                        $('#yubico_key').focus();
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('press_your_yubico_key'); ?>',
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        //return false;
                    }
                }

                // Other values
                mfaData['login'] = ($('#login').val());
                mfaData['pw'] = ($('#pw').val());
                mfaData['duree_session'] = ($('#session_duration').val());
                mfaData['screenHeight'] = $('body').innerHeight();
                mfaData['randomstring'] = randomstring;
                mfaData['TimezoneOffset'] = TimezoneOffset;
                mfaData['client'] = client_info;
                mfaData['user_2fa_selection'] = mfaMethod;

                console.log(mfaData);

                // Handle if DUOSecurity is enabled
                if (mfaMethod !== 'duo' || $('#login').val() === 'admin') {
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
    function loadDuoDialog() {
        $('#div-2fa-duo').removeClass('hidden');
        $('#div-2fa-duo-progress')
            .load(
                '<?php echo $SETTINGS['cpassman_url']; ?>/includes/core/duo.load.php',
                null,
                function(responseText, textStatus, xhr) {
                    if (textStatus === "error") {
                        alert("Error while loading " + url + "\n\n" + responseText);
                    }
                }
            );
    }


    //Identify user
    function identifyUser(redirect, psk, data, randomstring) {
        // Check if session is still existing
        $.post(
            "sources/checks.php", {
                type: "checkSessionExists",
            },
            function(check_data) {
                if (parseInt(check_data) === 1) {
                    console.info('Session existance check:')
                    console.log(data);
                    //send query
                    $.post(
                        "sources/identify.php", {
                            type: "identify_user",
                            data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>')
                        },
                        function(receivedData) {
                            var data = prepareExchangedData(
                                receivedData,
                                "decode",
                                "<?php echo $_SESSION['key']; ?>"
                            );
                            console.info('Identification answer:')
                            console.log(data);
                            toastr.remove();
                            
                            // Maintenance mode is enabled?
                            if (data.error === 'maintenance_mode_enabled') {
                                toastr.warning(
                                    '<?php echo langHdl('index_maintenance_mode_admin'); ?>',
                                    '<?php echo langHdl('caution'); ?>', {
                                        timeOut: 0
                                    }
                                );
                                return false;
                            }

                            if (data.error === false && data.mfaStatus === 'ga_temporary_code_correct') {
                                $('#div-2fa-google-qr')
                                    .removeClass('hidden')
                                    .html('<div class="col-12 alert alert-info">' +
                                        '<p class="text-center">' + data.value + '</p>' +
                                        '<p class="text-center"><i class="fas fa-mobile-alt fa-lg mr-1"></i>' +
                                        '<?php echo langHdl('mfa_flash'); ?></p></div>');
                                $('#ga_code')
                                    .val('')
                                    .focus();

                                toastr.remove();
                                toastr.success(
                                    '<?php echo langHdl('done'); ?>',
                                    '', {
                                        timeOut: 1000
                                    }
                                );
                            } else if (data.value === randomstring) {
                                // Update session
                                store.update(
                                    'teampassUser', {},
                                    function(teampassUser) {
                                        teampassUser.sessionDuration = 3600;
                                        teampassUser.sessionStartTimestamp = Date.now();
                                        teampassUser.sessionKey = data.session_key;
                                        teampassUser.user_id = data.user_id;
                                        teampassUser.pwd = data.pw;
                                        teampassUser.user_has_psk = data.has_psk;
                                        teampassUser.shown_warning_unsuccessful_login = data.shown_warning_unsuccessful_login;
                                        teampassUser.nb_unsuccessful_logins = data.nb_unsuccessful_logins;
                                        teampassUser.special = data.special;
                                    }
                                );

                                //redirection for admin is specific
                                if (parseInt(data.user_admin) === 1) {
                                    window.location.href = 'index.php?page=admin';
                                } else if (data.initial_url !== '' && data.initial_url !== null) {
                                    window.location.href = data.initial_url;
                                } else {
                                    window.location.href = 'index.php?page=items';
                                }
                            } else if (data.error === true || data.error !== '') {
                                //toastr.remove();
                                toastr.error(
                                    data.message,
                                    '<?php echo langHdl('caution'); ?>', {
                                        timeOut: 10000,
                                        progressBar: true,
                                        positionClass: "toast-top-right"
                                    }
                                );
                            } else {
                                toastr.remove();
                                toastr.error(
                                    '<?php echo langHdl('error_bad_credentials'); ?>',
                                    '<?php echo langHdl('caution'); ?>', {
                                        timeOut: 5000,
                                        progressBar: true,
                                        positionClass: "toast-top-right"
                                    }
                                );
                            }

                            // Clear Yubico
                            if ($("#yubico_key").length > 0) {
                                $("#yubico_key, #yubico_user_id, #yubico_user_key").val("");
                            }
                        }
                    );
                } else {
                    // No session was found, warn user
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('alert_page_will_reload'); ?>',
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    // Delay page submit
                    $(this).delay(500).queue(function() {
                        document.location.reload(true);
                        $(this).dequeue();
                    });
                }
            }
        );
    }

    function getGASynchronization() {
        if ($("#login").val() != "" && $("#pw").val() != "") {
            $("#ajax_loader_connexion").show();
            $("#connection_error").hide();
            $("#div_ga_url").hide();

            data = {
                'login': $("#login").val(),
                'pw': $("#pw").val(),
                'send_mail': 1
            }
            $.post(
                'sources/main.queries.php', {
                    type: 'ga_generate_qr',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data);

                    if (data.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Inform user
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('share_sent_ok'); ?>',
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
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
        
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        data = {
            'login': $("#login").val(),
            'pwd': $("#pw").val(),
            'send_email': 1
        }
        console.log(data);
        $.post(
            'sources/main.queries.php', {
                type: 'ga_generate_qr',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Inform user
                    toastr.remove();
                    toastr.success(
                        data.email_result,
                        '<?php echo langHdl('success'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    }

    /**
     * Permits to manage the MFA method to show
     *
     * @return void
     */
    function showMFAMethod() {
        var twoFaMethods = (parseInt(store.get('teampassSettings').google_authentication) === 1 ? 1 : 0) +
            (parseInt(store.get('teampassSettings').agses_authentication_enabled) === 1 ? 1 : 0) +
            (parseInt(store.get('teampassSettings').duo) === 1 ? 1 : 0) +
            (parseInt(store.get('teampassSettings').yubico_authentication) === 1 ? 1 : 0);

        if (twoFaMethods > 1) {
            // Show only expected MFA
            $('#2fa_methods_selector').removeClass('hidden');

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
                    console.log(twofaMethod)

                    // Save user choice
                    $('#2fa_user_selection').val(twofaMethod);

                    // Show 2fa method div
                    $('#div-2fa-' + twofaMethod).removeClass('hidden');

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
            // Show only expected MFA
            $('#2fa_methods_selector').addClass('hidden');
            // One 2FA method is expected
            if (parseInt(store.get('teampassSettings').google_authentication) === 1) {
                $('#div-2fa-google').removeClass('hidden');
            } else if (parseInt(store.get('teampassSettings').yubico_authentication) === 1) {
                $('#div-2fa-yubico').removeClass('hidden');
            }
            $('#login').focus();
        } else {
            // No 2FA methods is expected
            $('#2fa_methods_selector').addClass('hidden');
        }
    }
</script>
