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
 * @file      load.js.php
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

// Is maintenance on-going?
if (
    isset($SETTINGS['maintenance_mode']) === true
    && (int) $SETTINGS['maintenance_mode'] === 1
    && ($session_user_admin === null
        || (int) $session_user_admin === 1)
) {
    ?>
    <script type="text/javascript">
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('index_maintenance_mode_admin'); ?>',
            '<?php echo langHdl('information'); ?>', {
                timeOut: 0
            }
        );
    </script>
<?php
}
?>

<script type="text/javascript">
    var userScrollPosition = 0;
    let hourInMinutes = 60;

    /**
    *   Add 1 hour to session duration
    **/
    function IncreaseSessionTime(duration)
    {
        duration = duration || hourInMinutes;
        $.post(
            'sources/main.queries.php',
            {
                type     : 'increase_session_time',
                duration : parseInt(duration, 10) * hourInMinutes,
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                if (data[0].new_value !== 'expired') {
                    $('#temps_restant').val(data[0].new_value);
                    $('#date_end_session').val(data[0].new_value);
                    $('#countdown').css('color', 'white');
                } else {
                    $(location).attr('href', 'index.php?session=expired');
                }
            },
            'json'
        );
    }

    // Start real time
    // get list of last items
    if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').user_id !== '' &&
        (Date.now() - store.get('teampassUser').sessionStartTimestamp) < (store.get('teampassUser').sessionDuration * 1000)
    ) {
        $.when(
            // Load teampass settings
            loadSettings()
        ).then(function() {
            $.when(
                // Refresh list of last items shopwn
                refreshListLastSeenItems()
            ).then(function() {
                setTimeout(
                    function() {
                        // Check if new privatekey needs to be adapted
                        var data = {
                            'user_id': store.get('teampassUser').user_id,
                            'fields' : 'special, auth_type',
                        }
                        $.post(
                            "sources/main.queries.php", {
                                type: "get_user_info",
                                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                                key: "<?php echo $_SESSION['key']; ?>"
                            },
                            function(data) {
                                //decrypt data
                                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                                console.log(data)

                                if (data.error === false && data.queryResults.special === 'generate-keys') {
                                    // Now we need to perform re-encryption due to LDAP password change
                                    console.log('User has to regenerate keys')
                                    // HIde
                                    $('.content-header, .content').addClass('hidden');
                                    $('#dialog-user-temporary-code-info').html('<i class="icon fas fa-info mr-2"></i><?php echo langHdl('renecyption_expected');?>');

                                    // Show passwords inputs and form
                                    $('#dialog-user-temporary-code').removeClass('hidden');

                                    // ----
                                } else if (data.error === false && data.queryResults.special === 'auth-pwd-change' && data.queryResults.auth_type === 'local') {
                                    // USer's password has been reseted, he shall change it
                                    console.log('User has to change his auth password')
                                    // HIde
                                    $('.content-header, .content').addClass('hidden');

                                    // Show passwords inputs and form
                                    $('#dialog-user-change-password-info')
                                        .html('<i class="icon fas fa-info mr-2"></i><?php echo langHdl('user_has_to_change_password_info');?>')
                                        .removeClass('hidden');
                                    $('#dialog-user-change-password').removeClass('hidden');

                                // ----
                                } else if (data.error === false && data.queryResults.special === 'auth-pwd-change' && data.queryResults.auth_type === 'ldap') {
                                    // USer's password has been reseted, he shall change it
                                    console.log('LDAP user password has to change his auth password')
                                    // HIde
                                    $('.content-header, .content').addClass('hidden');

                                    // Show passwords inputs and form
                                    $('#dialog-ldap-user-change-password-info')
                                        .html('<i class="icon fas fa-info mr-2"></i><?php echo langHdl('ldap_user_has_changed_his_password');?>')
                                        .removeClass('hidden');
                                    $('#dialog-ldap-user-change-password').removeClass('hidden');
                                    
                                    // ----
                                } else if (
                                    (data.error === false && data.queryResults.special === 'user_added_from_ldap' && data.queryResults.auth_type === 'ldap')
                                    || data.queryResults.special === 'otc_is_required_on_next_login'
                                ) {
                                    // USer's password has been reseted, he shall change it
                                    console.log('NEW LDAP user password - we need to encrypt items')
                                    // HIde
                                    $('.content-header, .content').addClass('hidden');

                                    // Show form
                                    $('#dialog-ldap-user-build-keys-database').removeClass('hidden');
                                }
                            }
                        );
                    },
                    500
                );
            }).then(function() {
                setTimeout(
                    function() {
                        $.when(
                            // send email
                            $.post(
                                "sources/main.queries.php", {
                                    type: "send_waiting_emails",
                                    key: "<?php echo $_SESSION['key']; ?>"
                                }
                            )
                        ).then(function() {
                            // send statistics
                            $.post(
                                "sources/main.queries.php", {
                                    type: "sending_statistics",
                                    key: "<?php echo $_SESSION['key']; ?>"
                                }
                            );
                        });
                    },
                    5000
                );
            });
        });
    }
    //-- end


    // Countdown
    countdown();

    $(".show_hide_password a").on('click', function(event) {
        event.preventDefault();
        if($('.how_hide_password input').attr("type") === "text"){
            $('.show_hide_password input').attr('type', 'password');
            $('.show_hide_password i').addClass( "fa-eye-slash" );
            $('.show_hide_password i').removeClass( "fa-eye" );
        }else if($('#show_hide_password input').attr("type") === "password"){
            $('.show_hide_password input').attr('type', 'text');
            $('.show_hide_password i').removeClass( "fa-eye-slash" );
            $('.show_hide_password i').addClass( "fa-eye" );
        }
    });
    
    if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').special === 'generate-keys'
    ) {
        // Now we need to perform re-encryption due to LDAP password change
        console.log('User has to regenerate keys')
        // HIde
        $('.content-header, .content').addClass('hidden');
        $('#dialog-user-temporary-code-info').html('<i class="icon fas fa-info mr-2"></i><?php echo langHdl('renecyption_expected');?>');

        // Show passwords inputs and form
        $('#dialog-user-temporary-code').removeClass('hidden');
        
        // ---
    } else if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').special === 'ldap_password_has_changed_do_reencryption'
    ) {
        // Now we need to perform re-encryption due to LDAP password change
        console.log('show password change')
        // HIde
        $('.content-header, .content, #button_do_sharekeys_reencryption').addClass('hidden');
        $('#warning-text-reencryption').html('<i class="icon fas fa-info mr-2"></i>'.langHdl('ldap_password_change_warning'));

        // Show passwords inputs and form
        $('#dialog-encryption-keys, .ask-for-previous-password').removeClass('hidden');

        $('#sharekeys_reencryption_target_user').val(store.get('teampassUser').user_id);

        $('#button_do_sharekeys_reencryption').removeClass('hidden');
        
        // ---
    } else if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').shown_warning_unsuccessful_login === false
    ) {
        // If login attempts experimented
        // Prepare modal
        showModalDialogBox(
            '#warningModal',
            '<i class="fas fa-user-shield fa-lg warning mr-2"></i><?php echo langHdl('caution'); ?>',
            '<?php echo langHdl('login_attempts_identified_since_last_connection'); ?>',
            '<?php echo langHdl('see_detail'); ?>',
            '<?php echo langHdl('cancel'); ?>'
        );

        // Actions on modal buttons
        $(document).on('click', '#warningModalButtonClose', function() {
            store.update(
                'teampassUser', {},
                function(teampassUser) {
                    teampassUser.shown_warning_unsuccessful_login = true;
                }
            );
        });
        $(document).on('click', '#warningModalButtonAction', function() {
            // SHow user
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>');

            // Action
            store.update(
                'teampassUser', {},
                function(teampassUser) {
                    teampassUser.shown_warning_unsuccessful_login = true;
                }
            );
            document.location.href = "index.php?page=profile&tab=timeline";
        });
    } else if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').special === 'private_items_to_encrypt'
    ) {
        // If user has to re-encrypt his personal item passwords
        $('#dialog-encryption-personal-items-after-upgrade').removeClass('hidden');
        $('.content, .content-header').addClass('hidden');
        
        // Actions on modal buttons
        $(document).on('click', '#button_do_personal_items_reencryption', function() {
            // SHow user
            toastr.remove();
            toastr.info('<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>');

            defusePskRemoval(store.get('teampassUser').user_id, 'psk', 0);
            
            function defusePskRemoval(userId, step, start)
            {
                if (step === 'psk') {
                    // Inform user
                    $("#user-current-defuse-psk-progress").html('<b><?php echo langHdl('encryption_keys'); ?> </b> [' + start + ' - ' + (parseInt(start) + <?php echo NUMBER_ITEMS_IN_BATCH;?>) + '] ' +
                        '... <?php echo langHdl('please_wait'); ?><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');

                    var data = {'userPsk' : $('#user-current-defuse-psk').val()};
                    // Do query
                    $.post(
                        "sources/main.queries.php", {
                            type: "user_psk_reencryption",
                            'start': start,
                            'length': <?php echo NUMBER_ITEMS_IN_BATCH;?>,
                            userId: userId,
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                            key: '<?php echo $_SESSION['key']; ?>'
                        },
                        function(data) {
                            data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                            console.log(data)
                            if (data.error === true) {
                                // error
                                toastr.remove();
                                toastr.error(
                                    data.message,
                                    '<?php echo langHdl('caution'); ?>', {
                                        timeOut: 5000,
                                        progressBar: true
                                    }
                                );

                                // Enable buttons
                                $("#user-current-defuse-psk-progress").html('<?php echo langHdl('provide_current_psk_and_click_launch'); ?>');
                                $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                                return false;
                            } else {
                                // Start looping on all steps of re-encryption
                                defusePskRemoval(data.userId, data.step, data.start);
                            }
                        }
                    );
                } else {
                    // Finished
                    $("#user-current-defuse-psk-progress").html('<i class="fas fa-check text-success mr-3"></i><?php echo langHdl('done'); ?>');

                    toastr.remove();
                }
            }

        });
        $(document).on('click', '#button_close_personal_items_reencryption', function() {
            $('#dialog-encryption-personal-items-after-upgrade').addClass('hidden');
            $('.content, .content-header').removeClass('hidden');
        });
    }


    // Show tooltips
    $('.infotip').tooltip();

    // Load user profiles
    $('.user-panel').click(function() {
        document.location.href = "index.php?page=profile";
    });

    // Sidebar redirection
    $('.nav-link').click(function() {
        if ($(this).data('name') !== undefined) {
            //NProgress.start();
            document.location.href = "index.php?page=" + $(this).data('name');
        }
    });

    // User menu action
    $('.user-menu').click(function() {
        if ($(this).data('name') !== undefined) {
            if ($(this).data('name') === 'increase_session') {
                showExtendSession();
            } else if ($(this).data('name') === 'password-change') {
                console.log('show password change')
                // HIde
                $('.content-header, .content, #button_do_user_change_password').addClass('hidden');

                // Add DoCheck button
                $('#button_do_user_change_password').after('<button class="btn btn-primary" id="button_do_pwds_checks"><?php echo langHdl('perform_checks'); ?></button>');

                // Show passwords inputs and form
                $('#dialog-user-change-password-progress').html('<i class="icon fas fa-info mr-2"></i><?php echo langHdl('change_your_password_info_message'); ?>');
                $('#dialog-user-change-password').removeClass('hidden');

                // Actions
                $('#button_do_pwds_checks').click(function() {
                    if ($('#profile-password').val() !== $('#profile-password-confirm').val()) {
                        $('#button_do_user_change_password').addClass('hidden');
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('passwords_not_the_same'); ?>',
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );
                    } else if (parseInt($('#profile-password-complex').val()) >= parseInt(store.get('teampassSettings').personal_saltkey_security_level)) {
                        $('#button_do_user_change_password').removeClass('hidden');
                        $('#button_do_pwds_checks').remove();
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('hit_launch_to_start'); ?>',
                            '<?php echo langHdl('ready_to_go'); ?>', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#button_do_user_change_password').addClass('hidden');
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('complexity_level_not_reached'); ?>',
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    }
                });
                // ----
            } else if ($(this).data('name') === 'profile') {
                //NProgress.start();
                document.location.href = "index.php?page=profile";
            } else if ($(this).data('name') === 'logout') {
                /*// Prepare modal
                showModalDialogBox(
                    '#warningModal',
                    '<i class="fas fa-sign-out-alt fa-lg mr-2"></i><?php echo TP_TOOL_NAME; ?>',
                    '<?php echo langHdl('logout_confirm'); ?>',
                    '<?php echo langHdl('confirm'); ?>',
                    '<?php echo langHdl('cancel'); ?>'
                );

                // Actions on modal buttons
                $(document).on('click', '#warningModalButtonAction', function() {
                    // SHow user
                    toastr.remove();
                    toastr.info(
                        '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
                    );

                    window.location.href = "./includes/core/logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>
                });*/
                window.location.href = "./includes/core/logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>
            }
        }
    });

    $('.close-element').click(function() {
        $(this).closest('.card').addClass('hidden');

        $('.content-header, .content').removeClass('hidden');
    });

    /**
     * When clicking save Personal saltkey
     */
    /*
    $('#button_save_user_psk').click(function() {
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        // Prepare data
        var data = {
            "psk": sanitizeString($("#user_personal_saltkey").val()),
            "complexity": $("#psk_strength_value").val()
        };

        //
        $.post(
            "sources/main.queries.php", {
                type: "store_personal_saltkey",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, '<?php echo $_SESSION['key']; ?>');

                // Is there an error?
                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('warning'); ?>',
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.pskDefinedInDatabase = 1;
                        }
                    )

                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.pskSetForSession = data.encrypted_psk;
                        }
                    )

                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('alert_page_will_reload'); ?>'
                    );

                    location.reload();
                }
            }
        );
    });
    */

    // For Personal Saltkey
    $("#profile-password").simplePassMeter({
        "requirements": {},
        "container": "#profile-password-strength",
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
    });
    $("#profile-password").bind({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#profile-password-complex").val(score);
        }
    }).change({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#profile-password-complex").val(score);
        }
    });

    // Hide sidebar footer icons when reducing sidebar
    $('a[data-widget="pushmenu"]').click(function(event) {
        if ($('#sidebar-footer').hasClass('hidden') === true) {
            setTimeout(function() {
                $('#sidebar-footer').removeClass('hidden');
            }, 300);
        } else {
            $('#sidebar-footer').addClass('hidden');
        }
    });


    var clipboardCopy = new ClipboardJS(".clipboard-copy", {
        text: function(trigger) {
            var elementId = $(trigger).data('clipboard-text');
            console.log($('#' + elementId).val())
            return String($('#' + elementId).val());
        }
    });

    clipboardCopy.on('success', function(e) {
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('copy_to_clipboard'); ?>',
            '<?php echo langHdl('information'); ?>', {
                timeOut: 2000
            }
        );
    });

    // Progress bar
    setTimeout(
        function() {
            $(".fade").removeClass("out");
        },
        1000
    );


    /**
    * USER HAS DECIDED TO CHANGE HIS AUTH PASSWORD
     */
    $(document).on('click', '#dialog-user-change-password-do', function() {
        // Start by changing the user password and send it by email
        if ($('#profile-current-password').val() !== "" && $('#profile-password').val() !== "" && $('#profile-password-confirm').val() !== "") {
            // Case where a user is changing his authentication password
            console.log('Reencryption based upon user decision to change his auth password');

            // Show progress
            $('#dialog-user-change-password-progress').html('<b><?php echo langHdl('please_wait'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');
            toastr.remove();
            toastr.info(
                '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );
            
            // Disable buttons
            $('#dialog-user-change-password-do, #dialog-user-change-password-close').attr('disabled', 'disabled');
            
            data = {
                'user_id': store.get('teampassUser').user_id,
                'old_password': $('#profile-current-password').val(),
                'new_password': $('#profile-password').val(),
            }
            console.log(data);

            // Check user current password
            // and change the password
            // and use the password to re-encrypt the privatekey
            $.post(
                'sources/main.queries.php', {
                    type: 'change_user_auth_password',
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

                        $("#dialog-user-change-password-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                        // Enable buttons
                        $('#dialog-user-change-password-do, #dialog-user-change-password-close').removeAttr('disabled');
                    } else {
                        // SUCCESS
                        $('#dialog-user-change-password-close').removeAttr('disabled');
                        toastr.remove();
                        toastr.success(
                            data.message,
                            '<?php echo langHdl('success'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        $("#dialog-user-change-password-progress").html('');
                    }
                }
            );
        } else {
            // Show error
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('password_cannot_be_empty'); ?>',
                '<?php echo langHdl('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });
    $(document).on('click', '#dialog-user-change-password-close', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-user-change-password, #dialog-user-change-password-info').addClass('hidden');
    });
    

    /**
    * ADMIN HAS DECIDED TO CHANGE THE USER'S AUTH PASSWORD
     */
    $(document).on('click', '#dialog-admin-change-user-password-do', function() {
        // When an admin changes the user auth password
        console.log('Reencryption based upon admin decision to change user auth password');

        // Show progress
        $('#dialog-admin-change-user-password-progress').html('<b><?php echo langHdl('please_wait'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );
        
        // Disable buttons
        $('#dialog-admin-change-user-password-do, #dialog-admin-change-user-password-close').attr('disabled', 'disabled');            
        
        // ENsure we have a user id
        if ($('#admin_change_user_password_target_user').val() !== '') {
            // Case where change is for user's account
            data = {
                'user_id': $('#admin_change_user_password_target_user').val(),
                'special': 'auth-pwd-change',
                'password': '',
                'self_change': false,
            }
            console.log(data);
            
            $.post(
                'sources/main.queries.php', {
                    type: 'initialize_user_password',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data)
                    store.set(
                        'teampassUser', {
                            admin_user_password: data.user_pwd,
                            admin_user_email: data.user_email,
                        }
                    );

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

                        // Enable buttons
                        $('#dialog-admin-change-user-password-do, #dialog-admin-change-user-password-close').removeAttr('disabled');
                    } else {
                        // Inform user
                        userShareKeysReencryption(
                            $('#admin_change_user_password_target_user').val(),
                            false,
                            'dialog-admin-change-user-password'
                        );
                    }
                }
            );
        }
    });
    $(document).on('click', '#dialog-admin-change-user-password-close', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-admin-change-user-password').addClass('hidden');
    });
    
    $(document).on('click', '.temp-button', function() {
        
        if ($(this).data('action') === "show-user-pwd") {
            // show password
            $('#temp-user-pwd').attr('type', 'text');
            $(this).prop( "disabled", true );
            setTimeout(
                () => {
                    $('#temp-user-pwd').attr('type', 'hidden');
                    $(this).prop( "disabled", false );
                },
                5000
            );
        } else if ($(this).data('action') === "send-user-pwd") {
            // Send email
            console.log('Preparing for email sending');
            
            // Prepare data
            var data = {
                'receipt': $('#temp-user-email').val(),
                'subject': '[Teampass] <?php echo langHdl('your_new_password');?>',
                'body': '<?php echo langHdl('email_body_temporary_login_password');?>',
                'pre_replace' : {
                    '#enc_code#' : $('#temp-user-pwd').val(),
                }
            }
            console.log(data);
            // Prepare form
            $('#dialog-admin-change-user-password-info').html('<?php echo langHdl('sending_email_message');?>');
            toastr.remove();
            toastr.info(
                '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );

            // Launch action
            $.post(
                'sources/main.queries.php', {
                    type: 'mail_me',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data);
                    if (data.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Fianlize UI

                        $('#dialog-admin-change-user-password-info').html('');
                        $('#dialog-admin-change-user-password-do, #dialog-admin-change-user-password-close').removeAttr('disabled');

                        // HIde
                        $('.content-header, .content').removeClass('hidden');

                        // SHow form
                        $('#dialog-admin-change-user-password').addClass('hidden');

                        store.set(
                            'teampassUser', {
                                admin_user_password: '',
                                admin_user_email: '',
                            }
                        );
                        
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
        }        
    });
    

    /**
    * USER PROVIDES HIS TEMPORARY CODE TO
     */
    $(document).on('click', '#dialog-user-temporary-code-do', function() {
        // Perform a renecryption based upon a temporary code
        console.log('Reencryption based upon users temporary code');

        // Show progress
        $('#dialog-user-temporary-code-progress').html('<b><?php echo langHdl('please_wait'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );
        
        // Disable buttons
        $('#dialog-user-temporary-code-do, #dialog-user-temporary-code-close').attr('disabled', 'disabled');            
        
        // Start by testing if the temporary code is correct to decrypt an item
        data = {
            'user_id': store.get('teampassUser').user_id,
            'password': $('#dialog-user-temporary-code-value').val(),
        }
        console.log(data);
        $.post(
            'sources/main.queries.php', {
                type: 'test_current_user_password_is_correct',
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

                    $("#dialog-user-temporary-code-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                    // Enable buttons
                    $('#dialog-user-temporary-code-do, #dialog-user-temporary-code-close').removeAttr('disabled');
                } else {
                    // Change privatekey encryption with user-s password
                    data = {
                        'user_id': store.get('teampassUser').user_id,
                        'current_code': $('#dialog-user-temporary-code-current-password').val(),
                        'new_code': $('#dialog-user-temporary-code-value').val(),
                        'action_type' : 'encrypt_privkey_with_user_password',
                    }
                    console.log(data);
                    
                    $.post(
                        'sources/main.queries.php', {
                            type: 'change_private_key_encryption_password',
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

                                // Enable buttons
                                $('#dialog-user-temporary-code-do, #dialog-user-temporary-code-close').removeAttr('disabled');
                            } else {
                                // Inform user
                                // Enable close button
                                $('#dialog-user-temporary-code-close').removeAttr('disabled');
                                $('#dialog-user-temporary-code-do').attr('disabled', 'disabled');

                                // Finished
                                $("#dialog-user-temporary-code-progress").html('<i class="fas fa-check text-success mr-3"></i><?php echo langHdl('done'); ?>');
                                toastr.remove();

                                store.update(
                                    'teampassUser', {},
                                    function(teampassUser) {
                                        teampassUser.special = 'none';
                                    }
                                );
                            }
                        }
                    );
                }
            }
        );
    });
    $(document).on('click', '#dialog-user-temporary-code-close', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-user-temporary-code').addClass('hidden');
    });


    /**
    * NEW LDAP USER HAS TO BUILD THE ITEMS DATABASE
     */
    $(document).on('click', '#dialog-ldap-user-build-keys-database-do', function() {
        if ($('#dialog-ldap-user-build-keys-database-code').val() === '') {

            return false;
        }
        // Perform a renecryption based upon a temporary code
        console.log('Building items keys database for new LDAP user');

        // Show progress
        $('#dialog-ldap-user-build-keys-database-progress').html('<b><?php echo langHdl('please_wait'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );
        
        // Disable buttons
        $('#dialog-ldap-user-build-keys-database-do, #dialog-ldap-user-build-keys-database-close').attr('disabled', 'disabled');            
        
        // Start by testing if the temporary code is correct to decrypt an item
        data = {
            'user_id': store.get('teampassUser').user_id,
            'password' : $('#dialog-ldap-user-build-keys-database-code').val(),
        }
        console.log(data);

        $.post(
            'sources/main.queries.php', {
                type: 'test_current_user_password_is_correct',
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

                    $("#dialog-ldap-user-build-keys-database-progress").html('<?php echo langHdl('bad_code'); ?>');

                    // Enable buttons
                    $('#dialog-ldap-user-build-keys-database-do, #dialog-ldap-user-build-keys-database-close').removeAttr('disabled');
                } else {
                    // Change privatekey encryption with user-s password
                    data = {
                        'user_id': store.get('teampassUser').user_id,
                        'current_code': $('#dialog-ldap-user-build-keys-database-code').val(),
                        'new_code': '',
                        'action_type' : '',
                    }
                    console.log(data);
                    
                    $.post(
                        'sources/main.queries.php', {
                            type: 'change_private_key_encryption_password',
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

                                
                                $("#dialog-ldap-user-build-keys-database-progress").html('<i class="fas fa-exclamation-circle text-danger mr-3"></i><?php echo langHdl('bad_code'); ?>');

                                // Enable buttons
                                $('#dialog-ldap-user-build-keys-database-do, #dialog-ldap-user-build-keys-database-close').removeAttr('disabled');
                            } else {
                                // Inform user
                                // Enable close button
                                $('#dialog-ldap-user-build-keys-database-close').removeAttr('disabled');
                                $('#dialog-ldap-user-build-keys-database-do').attr('disabled', 'disabled');

                                // Finished
                                $("#dialog-ldap-user-build-keys-database-progress").html('<i class="fas fa-check text-success mr-3"></i><?php echo langHdl('done'); ?>');
                                toastr.remove();

                                store.update(
                                    'teampassUser', {},
                                    function(teampassUser) {
                                        teampassUser.special = 'none';
                                    }
                                );

                                // refresh the page
                                window.location.href = 'index.php?page=items';
                            }
                        }
                    );
                }
            }
        );




        /*
        // Inform user
        userShareKeysReencryption(
            store.get('teampassUser').user_id,
            true,
            'dialog-ldap-user-build-keys-database'
        );*/
    });
    $(document).on('click', '#dialog-user-temporary-code-close', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-user-temporary-code').addClass('hidden');
    });


    /**
    * USER PASSWORD IN LDAP HAS CHANGED
    */
    $(document).on('click', '#dialog-ldap-user-change-password-do', function() {
        // Start by changing the user password and send it by email
        if ($('#dialog-ldap-user-change-password-old').val() !== "" && $('#dialog-ldap-user-change-password-current').val() !== "") {
            // Case where a user is changing his authentication password
            console.log('Reencryption based upon user auth password changed in LDAP');

            // Show progress
            $('#dialog-ldap-user-change-password-progress').html('<b><?php echo langHdl('please_wait'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');
            toastr.remove();
            toastr.info(
                '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );
            
            // Disable buttons
            $('#dialog-ldap-user-change-password-do, #dialog-ldap-user-change-password-close').attr('disabled', 'disabled');
            
            data = {
                'user_id': store.get('teampassUser').user_id,
                'previous_password': $('#dialog-ldap-user-change-password-old').val(),
                'current_password': $('#dialog-ldap-user-change-password-current').val(),
            }
            console.log(data);

            // Check user current password
            // and change the password
            // and use the password to re-encrypt the privatekey
            $.post(
                'sources/main.queries.php', {
                    type: 'change_user_ldap_auth_password',
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

                        $("#dialog-ldap-user-change-password-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                        // Enable buttons
                        $('#dialog-ldap-user-change-password-do, #dialog-ldap-user-change-password-close').removeAttr('disabled');
                    } else {
                        // SUCCESS
                        $('#dialog-ldap-user-change-password-close').removeAttr('disabled');
                        toastr.remove();
                        toastr.success(
                            data.message,
                            '<?php echo langHdl('success'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        $("#dialog-ldap-user-change-password-progress").html('');
                    }
                }
            );
        } else {
            // Show error
            toastr.remove();
            toastr.error(
                '<?php echo langHdl('password_cannot_be_empty'); ?>',
                '<?php echo langHdl('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });
    $(document).on('click', '#dialog-user-change-password-close', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-user-change-password, #dialog-user-change-password-info').addClass('hidden');
    });
    // --- END ---


    function loadSettings() {
        return $.post(
            "sources/main.queries.php", {
                type: "get_teampass_settings",
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
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
                };

                // Test if JSON object
                if (typeof data === 'object') {
                    // Store settings in localstorage
                    store.update(
                        'teampassSettings', {},
                        function(teampassSettings) {
                            $.each(data, function(key, value) {
                                teampassSettings[key] = value;
                            });
                        }
                    );

                    // Store some User info
                    store.update(
                        'teampassUser', {},
                        function(teampassUser) {
                            teampassUser['user_admin'] = <?php echo isset($_SESSION['user_admin']) === true ? (int) $_SESSION['user_admin'] : 0; ?>;
                            teampassUser['user_id'] = <?php echo isset($_SESSION['user_id']) === true ? (int) $_SESSION['user_id'] : 0; ?>;
                            teampassUser['user_manager'] = <?php echo isset($_SESSION['user_manager']) === true ? (int) $_SESSION['user_manager'] : 0; ?>;
                            teampassUser['user_can_manage_all_users'] = <?php echo isset($_SESSION['user_can_manage_all_users']) === true ? (int) $_SESSION['user_can_manage_all_users'] : 0; ?>;
                            teampassUser['user_read_only'] = <?php echo isset($_SESSION['user_admin']) === true ? (int) $_SESSION['user_read_only'] : 1; ?>;
                            teampassUser['key'] = '<?php echo isset($_SESSION['key']) === true ? $_SESSION['key'] : 0; ?>';
                            teampassUser['login'] = "<?php echo isset($_SESSION['login']) === true ? $_SESSION['login'] : 0; ?>";
                            teampassUser['lastname'] = "<?php echo isset($_SESSION['lastname']) === true ? $_SESSION['lastname'] : 0; ?>";
                            teampassUser['name'] = "<?php echo isset($_SESSION['name']) === true ? $_SESSION['name'] : 0; ?>";
                            teampassUser['pskDefinedInDatabase'] = <?php echo isset($_SESSION['user']['encrypted_psk']) === true ? 1 : 0; ?>;
                            teampassUser['can_create_root_folder'] = <?php echo isset($_SESSION['can_create_root_folder']) === true ? (int) $_SESSION['can_create_root_folder'] : 0; ?>;
                            teampassUser['pskDefinedInDatabase'] = <?php echo isset($_SESSION['user']['encrypted_psk']) === true ? 1 : 0; ?>;
                            teampassUser['special'] = '<?php echo isset($_SESSION['user']['special']) === true ? $_SESSION['user']['special'] : 'none'; ?>';
                        }
                    );
                }
            }
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    function showExtendSession() {
        // Prepare modal
        showModalDialogBox(
            '#warningModal',
            '<i class="fas fa-clock fa-lg warning mr-2"></i><?php echo langHdl('index_add_one_hour'); ?>',
            '<div class="form-group">' +
            '<label for="warningModal-input" class="col-form-label"><?php echo langHdl('index_session_duration') . ' (' . langHdl('minutes') . ')'; ?>:</label>' +
            '<input type="text" class="form-control" id="warningModal-input" value="<?php echo isset($_SESSION['user']['session_duration']) === true ? (int) $_SESSION['user']['session_duration'] / 60 : 60; ?>">' +
            '</div>',
            '<?php echo langHdl('confirm'); ?>',
            '<?php echo langHdl('cancel'); ?>'
        );

        // Actions on modal buttons
        $(document).on('click', '#warningModalButtonAction', function() {
            // SHow user
            toastr.remove();
            toastr.info(
                '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );

            // Perform action
            $.when(
                IncreaseSessionTime(
                    $('#warningModal-input').val()
                )
            ).then(function() {
                toastr.remove();
                toastr.success(
                    '<?php echo langHdl('done'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );
                $('#warningModal').modal('hide');
            });
        });
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    function showPersonalSKDialog() {
        $('#dialog-request-psk').removeClass('hidden');

        // Hide other
        $('.content-header, .content').addClass('hidden');

        $('#user_personal_saltkey').focus();

        toastr.remove();
    }

    /**
     * Loads the last seen items
     *
     * @return void
     */
    function refreshListLastSeenItems() {
        $.post(
            "sources/main.queries.php", {
                type: 'refresh_list_items_seen',
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                try {
                    data = $.parseJSON(data)
                } catch (e) {
                    return false;
                }
                //check if format error
                if (data.error === '') {
                    if (data.html_json === null || data.html_json === '') {
                        $('#index-last-pwds').html('<li><?php echo langHdl('none'); ?></li>');
                    } else {
                        // Prepare HTML
                        var html_list = '';
                        $.each(data.html_json, function(i, value) {
                            html_list += '<li onclick="showItemCard($(this).closest(\'li\'))" class="pointer" data-item-edition="0" data-item-id="' + value.id + '" data-item-sk="' + value.perso + '" data-item-expired="0" data-item-restricted="' + value.restricted + '" data-item-display="1" data-item-open-edit="0" data-item-reload="0" data-item-tree-id="' + value.tree_id + '" data-is-search-result="0">' +
                                '<i class="fa fa-caret-right mr-2"></i>' + value.label + '</li>';
                        });
                        $('#index-last-pwds').html(html_list);
                    }

                    // show notification
                    if (data.existing_suggestions !== 0) {
                        blink('#menu_button_suggestion', -1, 500, 'ui-state-error');
                    }
                } else {
                    toastr.remove();
                    toastr.error(
                        data.error,
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    }

    /**
     * Show an item
     *
     * @return void
     */
    function showItemCard(itemDefinition) {
        // Show circle-notch
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        if (window.location.href.indexOf('page=items') === -1) {
            location.replace('<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=items&group=' + itemDefinition.data().itemTreeId + '&id=' + itemDefinition.data().itemId);
        } else {
            $('#items_list').html('<ul class="liste_items" id="full_items_list"></ul>');
            Details(itemDefinition, 'show');
            if (itemDefinition.data().itemTreeId !== $('#open_folder').val()) {
                ListerItems(itemDefinition.data().itemTreeId, '', 0);
            }

            // Hide sidebar-mini
            $('body')
                .removeClass('control-sidebar-slide-open');
        }
    }

    /**
     * Open defect report page
     *
     * @return void
     */
    function generateBugReport() {
        $('#dialog-bug-report-text').html('');
        $('#dialog-bug-report').removeClass('hidden');

        // Scroll to top
        $(window).scrollTop(0);

        var data = {
            'browser_name': platform.name,
            'browser_version': platform.version,
            'os': platform.os.family,
            'os_archi': platform.os.architecture,
            'current_page': window.location.href.substring(window.location.href.lastIndexOf("/")+1),
        }
        
        $.post(
            "sources/main.queries.php", {
                type: 'generate_bug_report',
                data: JSON.stringify(data),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');

                // Show data
                $('#dialog-bug-report-text').html(data.html);

                // Open Github
                $('#dialog-bug-report-github-button').click(function() {
                    window.open('https://github.com/nilsteampassnet/TeamPass/issues/new', '_blank');
                    return false;
                });
            }
        );
    }



    function userShareKeysReencryption(
        userId = null,
        erase_existing_keys = false,
        divIdDialog = '',
        to_be_continued = false
    ) {
        console.log('USER SHAREKEYS RE-ENCRYPTION START');

        $('#'+divIdDialog+'-progress').html('<b><?php echo langHdl('clearing_old_sharekeys'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');

        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        var data = {
            'user_id': userId,
            'self_change': erase_existing_keys,
        }
        console.log(data)
        $.post(
            "sources/main.queries.php", {
                type: "user_sharekeys_reencryption_start",
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data)
                if (data.error === true) {
                    // error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    $("#"+divIdDialog+'-progress').html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                    // Enable buttons
                    $('#'+divIdDialog+'-do, #'+divIdDialog+'-close').removeAttr('disabled');
                    return false;
                } else {
                    // Start looping on all steps of re-encryption
                    userShareKeysReencryptionNext(data.userId, data.step, data.start, erase_existing_keys, divIdDialog, to_be_continued);
                }
            }
        );
    }

    function userShareKeysReencryptionNext(
        userId,
        step,
        start,
        erase_existing_keys = false,
        divIdDialog,
        to_be_continued
    ) {
        var stepText = '';
        console.log('Performing '+step)

        // Prepare progress string
        if (step === 'step0') {
            stepText = '<?php echo langHdl('inititialization'); ?>';
        } else if (step === 'step1') {
            stepText = '<?php echo langHdl('items'); ?>';
        } else if (step === 'step2') {
            stepText = '<?php echo langHdl('logs'); ?>';
        } else if (step === 'step3') {
            stepText = '<?php echo langHdl('suggestions'); ?>';
        } else if (step === 'step4') {
            stepText = '<?php echo langHdl('fields'); ?>';
        } else if (step === 'step5') {
            stepText = '<?php echo langHdl('files'); ?>';
        } else if (step === 'step6') {
            stepText = '<?php echo langHdl('personal_items'); ?>';
        }

        if (step !== 'finished') {
            // Inform user
            $("#"+divIdDialog+'-progress').html('<b><?php echo langHdl('encryption_keys'); ?> - ' +
                stepText + '</b> [' + start + ' - ' + (parseInt(start) + <?php echo NUMBER_ITEMS_IN_BATCH;?>) + '] ' +
                '... <?php echo langHdl('please_wait'); ?><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');

            var data = {
                'action': step,
                'start': start,
                'length': <?php echo NUMBER_ITEMS_IN_BATCH;?>,
                'user_id': userId,
            }
            // Do query
            $.post(
                "sources/main.queries.php", {
                    type: "user_sharekeys_reencryption_next",
                    data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    console.log(data);
                    
                    if (data.error === true) {
                        // error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo langHdl('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );

                        // Enable buttons
                        $('#'+divIdDialog+'-do, #'+divIdDialog+'-close').removeAttr('disabled');
                        return false;
                    } else {
                        // Start looping on all steps of re-encryption
                        userShareKeysReencryptionNext(data.userId, data.step, data.start, erase_existing_keys, divIdDialog, to_be_continued);
                    }
                }
            );
        } else {console.log("Finishing : "+to_be_continued)
            if (to_be_continued !== true) {
                // Enable close button
                $('#'+divIdDialog+'-close').removeAttr('disabled');

                // Finished
                $("#"+divIdDialog+'-progress').html('<i class="fas fa-check text-success mr-3"></i><?php echo langHdl('done'); ?>');
                toastr.remove();

                // Unlog if same user
                if (userId === <?php echo $_SESSION['user_id']; ?>) {
                    toastr.success(
                        '<?php echo langHdl('logout_on_going'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>',
                        '', {
                            timeOut: 4000
                        }
                    );

                    window.location.href = "./includes/core/logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>
                } else if (store.get('teampassUser').admin_user_password) {
                    // now select if sending by email
                    $('#dialog-admin-change-user-password-info').html('<i class="fas fa-envelope-open-text fa-lg warning mr-2"></i><?php echo langHdl('information'); ?><br><br>'+
                    '<i class="fas fa-info-circle mr-2"></i><?php echo langHdl('send_user_password_by_email'); ?>'+
                    '<div class="row">'+
                        '<div class="col-lg-2"><button type="button" class="btn btn-block btn-secondary mr-2 temp-button"  data-action="show-user-pwd"><?php echo langHdl('show_user_password'); ?></button></div>'+
                        '<div class="col-lg-2"><input class="form-control form-item-control" type="hidden" id="temp-user-pwd" value="'+store.get('teampassUser').admin_user_password+'"></div>'+
                        '<div class="col-lg-2"><button type="button" class="btn btn-block btn-secondary mr-2 temp-button"  data-action="send-user-pwd"><?php echo langHdl('send_by_email'); ?></button>'+
                        '<input class="form-control form-item-control" type="hidden" id="temp-user-email" value="'+store.get('teampassUser').admin_user_email+'"></div>'+
                    '</div>');


                    $("#dialog-admin-change-user-password-progress").html('<?php echo langHdl('done'); ?>');
                    $("#dialog-admin-change-user-password-do").removeAttr('disabled');
                }
            }
        }
    }

    // This permits to manage the column width of tree/items
    $(document).on('click', '.columns-position', function() {
        var colLeft = $('#folders-tree-card').find('.column-left'),
            colRight = $('#folders-tree-card').find('.column-right'),
            counterLeft = $(colLeft).attr("class").match(/col-md-[\w-]*\b/)[0].split('-')[2],
            counterRight = $(colRight).attr("class").match(/col-md-[\w-]*\b/)[0].split('-')[2];

        // Toogle class
        if ($('#folders-tree-card').hasClass('hidden') === false) {
            if ($(this).hasClass('tree-decrease') === true && counterRight < 9) {
                $(colLeft).toggleClass('col-md-' + counterLeft + ' col-md-' + (parseInt(counterLeft) - 1));
                $(colRight).toggleClass('col-md-' + counterRight + ' col-md-' + (parseInt(counterRight) + 1));
            } else if ($(this).hasClass('tree-increase') === true && counterLeft < 9) {
                $(colLeft).toggleClass('col-md-' + counterLeft + ' col-md-' + (parseInt(counterLeft) + 1));
                $(colRight).toggleClass('col-md-' + counterRight + ' col-md-' + (parseInt(counterRight) - 1));
            }
        }
    })

    $(function() {
        // In case that session was expired and login form was reloaded
        // Force the launchIdentify as if the user has clicked the button
        if ($("#auto_log").length > 0) {
            $("#but_identify_user").click();
        }
    });



    /**
     * 
     * @param {integer} duration
     * 
     */
    function clearClipboardTimeout(duration) {
        // Wait for duration
        $(this).delay(duration * 1000).queue(function() {
            navigator.clipboard.writeText("Cleared by Teampass").then(function() {
                // clipboard successfully set
            }, function() {
                // clipboard write failed
            });

            $(this).dequeue();
        });
    }
</script>
