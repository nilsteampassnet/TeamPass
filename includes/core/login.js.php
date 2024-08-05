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
 * @file      login.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

?>
<script type="text/javascript">
    var debugJavascript = false;

    // On page load
    $(function() {
        // Do we have to upgrade first
        <?php
        if (upgradeRequired() === true) {
            ?>
            toastr.error(
                '<?php echo $lang->get('upgrade_requested_more'); ?>',
                '<h2></i><?php echo $lang->get('upgrade_requested'); ?></h2>',
                {
                    positionClass: "toast-bottom-full-width",
                    preventDuplicates: true,
                    tapToDismiss: false
                }
            );

            $('#but_identify_user').prop('disabled', true);
            <?php
        }
        ?>

        // Set focus on login input
        $('#login').focus();

        // Page has beed reloaded due to session key inconsistency
        if (store.get('teampassUser') !== null && typeof store.get('teampassUser') !== 'undefined'&& store.get('teampassUser').page_reload === 1) {
            // Set previous values
            $("#pw").val(store.get('teampassUser').pwd);
            $("#login").val(store.get('teampassUser').login);
            $("#select2fa-otp").prop('checked', store.get('teampassUser').mfaSelector);
            $("#ga_code").val(store.get('teampassUser').mfaCode);

            // Update session
            store.update(
                'teampassUser', {},
                function(teampassUser) {
                    teampassUser.page_reload = 0;
                }
            );
        }

        // Prepare iCheck format for checkboxes
        $('input[type="checkbox"].flat-blue').iCheck({
            checkboxClass: 'icheckbox_flat-blue'
        });

        // Manage DUO SEC login
        if ($("#2fa_user_selection").val() === "duo" && $("#duo_code").val() !== "" && $("#duo_state").val() !== "") {
            // disable form fields
            $("#login, #pw, #session_duration, #but_identify_user").prop("disabled", true);

            if (debugJavascript === true) {
                console.log('After identify_duo_user_check');
                console.log('{ Duo code: ' + $("#duo_code").val() + ', Duo state: ' + $("#duo_state").val() + '}');
            }
            
            toastr.success(
                '<?php echo $lang->get('loading_main_page'); ?><i class="fas fa-circle-notch fa-spin ml-3"></i>',
                '<h5><?php echo $lang->get('please_wait'); ?></h5>',
                {
                    positionClass: "toast-top-center",
                    preventDuplicates: true
                }
            );
            
            // Launch identification process inside Teampass.
            if (debugJavascript === true) {
                console.log('User starts auth');
            }
            
            launchIdentify(true, '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>');
        }

        // Click on log in button
        $('#but_identify_user').click(function() {
            if (debugJavascript === true) {
                console.log('User starts auth');
            }
            launchIdentify(false, '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>');
        });

        // Click on log in button with Azure Entra
        if($("#but_login_with_sso").length > 0) {
            $('#but_login_with_sso').click(function() {
                if (debugJavascript === true) {
                    console.log('User starts auth with Azure');
                }
                launchIdentify(false, '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', false, true);
            });
        }

        // Relaunch authentication
        if (($("#pw").val() !== "" || $("#login").val() !== "")) {
            $(this).delay(500).queue(function() {
                document.getElementById('but_identify_user').click();
                $(this).dequeue();
            });
        }
        
        // Show tooltips
        $('.infotip').tooltip();
    });
    
    // Ensure session is ready in case of disconnection
    const teampassSettings = store.get('teampassSettings');
    if (teampassSettings === null || typeof teampassSettings === 'undefined' || Object.keys(teampassSettings).length === 0) {
        store.set(
            'teampassSettings', {},
            function(teampassSettings) {}
        );
        $.when(
            // Load teampass settings
            (function() {
                return loadSettings();
            })()
        ).then(function() {
            showMFAMethod();
        });

    } else {
        showMFAMethod();
    }



    $('.submit-button').keypress(function(event) {
        if (event.keyCode === 10 || event.keyCode === 13) {
            launchIdentify(false, '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', '');
            event.preventDefault();
        }
    });

    $('#yubico_key').change(function(event) {
        launchIdentify(false, '<?php isset($nextUrl) === true ? $nextUrl : ''; ?>', '');
        event.preventDefault();
    });


    $(document).on('click', '#register-yubiko-key', function() {
        $('#yubiko-new-key').removeClass('hidden');
    });


    $("#new-user-password")
        .simplePassMeter({
            "requirements": {},
            "container": "#new-user-password-strength",
            "defaultText": "<?php echo $lang->get('index_pw_level_txt'); ?>",
            "ratings": [{
                    "minScore": 0,
                    "className": "meterFail",
                    "text": "<?php echo $lang->get('complex_level0'); ?>"
                },
                {
                    "minScore": 25,
                    "className": "meterWarn",
                    "text": "<?php echo $lang->get('complex_level1'); ?>"
                },
                {
                    "minScore": 50,
                    "className": "meterWarn",
                    "text": "<?php echo $lang->get('complex_level2'); ?>"
                },
                {
                    "minScore": 60,
                    "className": "meterGood",
                    "text": "<?php echo $lang->get('complex_level3'); ?>"
                },
                {
                    "minScore": 70,
                    "className": "meterGood",
                    "text": "<?php echo $lang->get('complex_level4'); ?>"
                },
                {
                    "minScore": 80,
                    "className": "meterExcel",
                    "text": "<?php echo $lang->get('complex_level5'); ?>"
                },
                {
                    "minScore": 90,
                    "className": "meterExcel",
                    "text": "<?php echo $lang->get('complex_level6'); ?>"
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
                    '<?php echo $lang->get('current_password_mandatory'); ?>',
                    '<?php echo $lang->get('caution'); ?>', {
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
                    type_category: 'action_password',
                    key: store.get('teampassUser').sessionKey,
                    data: prepareExchangedData(JSON.stringify(data), 'encode', store.get('teampassUser').sessionKey)
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', store.get('teampassUser').sessionKey);
                    if (debugJavascript === true) console.log(data);

                    if (data.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('password_changed'); ?>',
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
                '<?php echo $lang->get('confirmation_seems_wrong'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
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
                '<?php echo $lang->get('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
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
                    if (debugJavascript === true) console.log(data);

                    // Is there an error?
                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
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
                                '<?php echo $lang->get('alert_page_will_reload'); ?>',
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
                            if (debugJavascript === true) {
                                console.log('----\n' + psk);
                                console.log(items);
                            }
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
                                        key: '<?php echo $session->get('key'); ?>'
                                    },
                                    function(data) {
                                        data = prepareExchangedData(data, store.get('teampassUser').sessionKey);

                                        // Is there an error?
                                        if (data.error === true) {
                                            toastr.remove();
                                            toastr.error(
                                                data.message,
                                                '<?php echo $lang->get('caution'); ?>', {
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
                                    '<?php echo $lang->get('alert_page_will_reload'); ?>',
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
                '<?php echo $lang->get('empty_psk'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
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
            '<?php echo $lang->get('your_attention_is_required'); ?>',
            '<?php echo $lang->get('i_cannot_remember_info'); ?>',
            '<?php echo $lang->get('confirm'); ?>',
            '<?php echo $lang->get('cancel'); ?>'
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
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('alert_page_will_reload'); ?>',
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
                '<?php echo $lang->get('cancel'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        });
    });

    /**
     * 
     */
    function launchIdentify(isDuo, redirect, psk, sso = false) {
        if (redirect == undefined) {
            redirect = ""; //Check if redirection
        }

        // Check credentials are set
        if (($("#pw").val() === "" || $("#login").val() === "") && isDuo !== true) {
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
        if (user2FaMethod === "yubico" && $("#yubico_key").val() === "") {
            $("#yubico_key").addClass("ui-state-error");
            return false;
        } 
        /*else if (user2FaMethod === "google" && $("#ga_code").val() === "") {
            $("#ga_code").addClass("ui-state-error");
            return false;
        }*/

        if (isDuo !== true) {
            // launch identification
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>',
                '', {
                    positionClass: "toast-top-center"
                }
            );
        }

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
        if (debugJavascript === true) {
            console.log('KEY : <?php echo $session->get('key'); ?>')
        }

        // manage SSO login
        if (sso === true) {
            document.location.href="includes/core/login.sso.php";
            return false;
        }

        // Get 2fa
        //TODO : je pense que cela pourrait etre modifié pour ne pas faire de requete ajax ; on dispose des infos via `get_teampass_settings`
        $.post(
            'sources/identify.php', {
                type: 'get2FAMethods',
                login: $('#login').val(),
                xhrFields: {
                    withCredentials: true
                }
            },
            function(data) {
                //data = prepareExchangedData(data, 'decode', "<?php echo $session->get('key'); ?>");
                data = JSON.parse(data);

                // Handle the case where the user doesn't exists.
                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true,
                            positionClass: "toast-top-right"
                        }
                    );
                    return false;
                }

                if (debugJavascript === true) {
                    console.log("Recevied key "+data.key+' and local key<?php echo $session->get('key'); ?>')
                }
                if (data.key !== '<?php echo $session->get('key'); ?>') {
                    // Update session
                    store.update(
                        'teampassUser', {},
                        function(teampassUser) {
                            teampassUser.pwd = $("#pw").val();
                            teampassUser.login = $("#login").val();
                            teampassUser.mfaSelector = $("#select2fa-otp").is(":checked");
                            teampassUser.mfaCode = $("#ga_code").val();
                            teampassUser.page_reload = 1;
                        }
                    );

                    // Reload login page.
                    document.location.reload(true);

                    return false;
                }

                try {
                    data = prepareExchangedData(
                        data.ret,
                        "decode",
                        data.key
                    );
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('server_answer_error'); ?>',
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true,
                            positionClass: "toast-top-right"
                        }
                    );
                    return false;
                }
                if (debugJavascript === true) {
                    console.info('Get 2FA Methods answer:');
                    console.log(data);
                }

                let mfaData = {},
                    mfaMethod = '';

                // Get selected user MFA method
                if ($(".2fa_selector_select").length > 1) {
                    mfaMethod = $(".2fa_selector_select:checked").data('mfa');
                } else {
                    if (data.google === true) {
                        mfaMethod = 'google';
                    } else if (data.duo === true) {
                        mfaMethod = 'duo';
                    } else if (data.yubico === true) {
                        mfaMethod = 'yubico';
                    }
                }

                // Google 2FA
                if (mfaMethod === 'google' && data.google === true) {
                    mfaData['GACode'] = $('#ga_code').val();
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
                            '<?php echo $lang->get('press_your_yubico_key'); ?>',
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true,
                                positionClass: "toast-top-center"
                            }
                        );
                        return false;
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
                
                if (isDuo === true && $("#duo_code").val() !== "" && $("#duo_state").val() !== "") {
                    mfaData['duo_code'] = sanitizeString($("#duo_code").val());
                    mfaData['duo_state'] = sanitizeString($("#duo_state").val());
                    mfaData['user_2fa_selection'] = 'duo';
                } else if(mfaMethod === 'duo' && isDuo !== true) {
                    mfaData['duo_status'] = 'start_duo_auth';
                }

                if (debugJavascript === true) {
                    console.log('Data submitted to identifyUser:');
                    console.log(mfaData);
                }
                
                identifyUser(redirect, psk, mfaData, randomstring);
            }
        );
    }

    //Identify user
    function identifyUser(redirect, psk, data, randomstring) {
        var old_data = data;
        // Check if session is still existing
        //send query
        $.post(
            "sources/identify.php", {
                type: "identify_user",
                login: $('#login').val(),
                data: prepareExchangedData(
                    JSON.stringify(data),
                    'encode',
                    '<?php echo $session->get('key'); ?>'
                ),
                xhrFields: {
                    withCredentials: true
                },
            },
            function(receivedData) {
                try {
                    var data = prepareExchangedData(
                        receivedData,
                        "decode",
                        "<?php echo $session->get('key'); ?>"
                    );
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('server_answer_error'); ?>',
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true,
                            positionClass: "toast-top-right"
                        }
                    );
                    return false;
                }
                
                if (debugJavascript === true) {
                    console.info('Identification answer:')
                    console.log('SESSION KEY is: <?php echo $session->get('key'); ?>');
                    console.log(data);
                }
                
                // Maintenance mode is enabled?
                if (data.error === 'maintenance_mode_enabled') {
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('index_maintenance_mode_admin'); ?>',
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 0,
                            positionClass: "toast-top-right"
                        }
                    );
                    return false;
                }

                if (data.error === true) {
                    if (typeof data.extra !== 'undefined' && data.extra === 'ad_user_created') {
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('your_attention_please'); ?>',
                            '<?php echo $lang->get('ad_user_created_automatically'); ?>', {
                                timeOut: 0,
                                positionClass: "toast-top-center"
                            }
                        );
                        return false;
                    }  else if (data.error === true || data.error !== '') {
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>',
                            {
                                timeOut: 10000,
                                progressBar: true,
                                positionClass: "toast-top-right"
                            }
                        );
                        if(data.ga_bad_code === true)
                        {
                            $("#ga_code").addClass("ui-state-error");
                        }
                    } else {
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('error_bad_credentials'); ?>',
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true,
                                positionClass: "toast-top-right"
                            }
                        );
                    }
                } else if (data.error === false && data.mfaStatus === 'ga_temporary_code_correct') {
                    $('#div-2fa-google-qr')
                        .removeClass('hidden')
                        .html('<div class="col-12 alert alert-info">' +
                            '<p class="text-center">' + data.value + '</p>' +
                            '<p class="text-center"><i class="fas fa-mobile-alt fa-lg mr-1"></i>' +
                            '<?php echo $lang->get('mfa_flash'); ?></p></div>');
                    $('#ga_code')
                        .val('')
                        .focus();

                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                } else if(data.error === false && data.duo_url_ready === true) {
                    toastr.remove();
                    toastr.info(
                        '<?php echo $lang->get('duo_redirect_uri'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>',
                        '', {
                            timeOut: 5000,
                            progressBar: true,
                            positionClass: "toast-top-center"
                        }
                    );
                    setTimeout(
                        function() {
                            window.location.href = data.duo_redirect_url;
                        },
                        500
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
                            teampassUser.pwd = old_data.pw;
                            teampassUser.user_has_psk = data.has_psk;
                            teampassUser.shown_warning_unsuccessful_login = data.shown_warning_unsuccessful_login;
                            teampassUser.nb_unsuccessful_logins = data.nb_unsuccessful_logins;
                            teampassUser.special = data.special;
                            teampassUser.auth_type = '';
                            teampassUser.location_stored = 0;
                            teampassUser.mfaSelector = false;
                            teampassUser.mfaCode = '';
                            teampassUser.page_reload = 0;
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
                }

                // Clear Yubico
                if ($("#yubico_key").length > 0) {
                    $("#yubico_key, #yubico_user_id, #yubico_user_key").val("");
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
                'send_mail': 1,
                'token': CreateRandomString(100)
            }
            $.post(
                'sources/main.queries.php', {
                    type: 'ga_generate_qr',
                    type_category: 'action_user',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    if (debugJavascript === true) console.log(data);

                    if (data.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Inform user
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('share_sent_ok'); ?>',
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    }
                }
            );
        } else {
            $("#connection_error").html("<?php echo $lang->get('ga_enter_credentials'); ?>").show();
        }
    }

    function send_user_new_temporary_ga_code() {
        // Check login and password
        if ($("#login").val() === "" || $("#pw").val() === "") {
            $("#connection_error").html("<?php echo $lang->get('ga_enter_credentials'); ?>").show();
            return false;
        }
        
        toastr.remove();
        toastr.info(
            '<?php echo $lang->get('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        data = {
            'login': $("#login").val(),
            'pwd': $("#pw").val(),
            'send_email': 1,
            'token': CreateRandomString(100)
        }
        $.post(
            'sources/main.queries.php', {
                type: 'ga_generate_qr',
                type_category: 'action_user',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // Inform user
                    toastr.remove();
                    toastr.success(
                        data.email_result,
                        '<?php echo $lang->get('success'); ?>', {
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
                    if (debugJavascript === true) console.log(twofaMethod)

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
