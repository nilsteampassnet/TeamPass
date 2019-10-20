<?php

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      load.js.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
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
    
    if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').special === 'ldap_password_has_changed_do_reencryption'
    ) {
        // Now we need to perform re-encryption due to LDAP password change
        console.log('show password change')
        // HIde
        $('.content-header, .content, #button_do_sharekeys_reencryption, #warning-text-changing-password').addClass('hidden');

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
            if ($(this).data('name') === 'set_psk') {
                showPersonalSKDialog();
            } else if ($(this).data('name') === 'increase_session') {
                showExtendSession();
            } else if ($(this).data('name') === 'password-change') {
                console.log('show password change')
                // HIde
                $('.content-header, .content, #button_do_sharekeys_reencryption').addClass('hidden');

                // Show passwords inputs and form
                $('#dialog-encryption-keys, .ask-for-new-password').removeClass('hidden');

                $('#sharekeys_reencryption_target_user').val(store.get('teampassUser').user_id);

                // Actions
                $(document).on('change', '#dialog-encryption-keys .form-control', function() {
                    if ($('#profile-password-confirm').val() === $('#profile-password').val()) {
                        if ($('#profile-password-complex').val() >= store.get('teampassSettings').personal_saltkey_security_level) {
                            $('#button_do_sharekeys_reencryption').removeClass('hidden');
                        } else {
                            $('#button_do_sharekeys_reencryption').addClass('hidden');
                            toastr.remove();
                            toastr.warning(
                                '<?php echo langHdl('complexity_level_not_reached'); ?>',
                                '<?php echo langHdl('caution'); ?>', {
                                    timeOut: 5000,
                                    progressBar: true
                                }
                            );
                        }
                    }
                });
                // ----
            } else if ($(this).data('name') === 'profile') {
                //NProgress.start();
                document.location.href = "index.php?page=profile";
            } else if ($(this).data('name') === 'logout') {
                // Prepare modal
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
                });
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


    var clipboardCopy = new Clipboard(".clipboard-copy", {
        text: function(trigger) {
            var elementId = $(trigger).data('clipboard-text');
            return $('#' + elementId).val();
        }
    });

    clipboardCopy.on('success', function(e) {
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('copy_to_clipboard'); ?>',
            '<?php echo langHdl('information'); ?>', {
                timeOut: 1000
            }
        );
    });

    // Progress bar
    setTimeout(
        function() {
            //NProgress.done();
            $(".fade").removeClass("out");
        },
        1000
    );


    /**
     * MANAGE FORM FOR SHAREKEYS RE-ENCRYPTION
     */

    // User hits the LAUNCH button for sharekeys re-encryption
    $(document).on('click', '#button_do_sharekeys_reencryption', function() {
        // Start by changing the user password and send it by email
        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        $('#dialog-encryption-keys-progress').html('<b><?php echo langHdl('change_login_password'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');

        // Disable buttons
        $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').attr('disabled', 'disabled');

        // Case where LDAP user with new password (from AD)
        if ($('.ask-for-previous-password').hasClass('hidden') === false) {
            // Test if previous password is correct
            data = {
                'user_id': $('#sharekeys_reencryption_target_user').val(),
                'password': $('#profile-previous-password').val(),
            }
            console.log(data)
            // If LDAP is enabled, then check that this password is correct
            // Before starting with changing it in Teampass
            $.post(
                'sources/main.queries.php', {
                    type: 'test_current_user_password_is_correct',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data);
                    console.log('new pwd: ' + data.debug)

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

                        $("#dialog-encryption-keys-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                        // Enable buttons
                        $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                    } else {
                        // Now change user public/private keys
                        data = {
                            'user_id': $('#sharekeys_reencryption_target_user').val(),
                            'special': 'none',
                            'password': $('#profile-current-password').val(),
                        }
                        console.log(data)
                        // If LDAP is enabled, then check that this password is correct
                        // Before starting with changing it in Teampass
                        $.post(
                            'sources/main.queries.php', {
                                type: 'change_public_private_keys',
                                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                                key: "<?php echo $_SESSION['key']; ?>"
                            },
                            function(data) {
                                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                                console.log(data);
                                console.log('new pwd: ' + data.debug)

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

                                    $("#dialog-encryption-keys-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                                    // Enable buttons
                                    $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                                } else {
                                    // Inform user
                                    userShareKeysReencryption($('#sharekeys_reencryption_target_user').val(), true);
                                }
                            }
                        );
                    }
                }
            );
        } else {
            // Manage 2 cases
            // One is when an admin asks
            // Second is when user asks
            data = {
                'user_id': $('#sharekeys_reencryption_target_user').val(),
                'special': '',
                'password': $('.ask-for-new-password').hasClass('hidden') === true ? '' : $('#profile-password').val(),
                'self_change': true,
            }
            console.log(data)
            // If LDAP is enabled, then check that this password is correct
            // Before starting with changing it in Teampass
            $.post(
                'sources/main.queries.php', {
                    type: 'initialize_user_password',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                    console.log(data);
                    console.log('new pwd: ' + data.debug)

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

                        $("#dialog-encryption-keys-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                        // Enable buttons
                        $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                    } else {
                        // Inform user
                        userShareKeysReencryption($('#sharekeys_reencryption_target_user').val(), true);
                    }
                }
            );
        }
    });

    // Manage close button for button_close_sharekeys_reencryption
    $(document).on('click', '#button_close_sharekeys_reencryption', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-encryption-keys').addClass('hidden');

        // Clear field
        $('#sharekeys_reencryption_target_user').val('');
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
                            teampassUser['special'] = "<?php echo isset($_SESSION['user']['special']) === true ? $_SESSION['user']['special'] : 'none'; ?>";
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
                    if (data.html_json === null) {
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

        var data = {
            'browser_name': platform.name,
            'browser_version': platform.version,
            'os': platform.os.family,
            'os_archi': platform.os.architecture,
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



    function userShareKeysReencryption(userId = null, self_change = false) {
        console.log('USER SHAREKEYS RE-ENCRYPTION START');

        $("#dialog-encryption-keys-progress").html('<b><?php echo langHdl('clearing_old_sharekeys'); ?></b><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');

        toastr.remove();
        toastr.info(
            '<?php echo langHdl('in_progress'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );

        $.post(
            "sources/main.queries.php", {
                type: "user_sharekeys_reencryption_start",
                userId: userId,
                self_change: self_change,
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

                    $("#dialog-encryption-keys-progress").html('<?php echo langHdl('fill_in_fields_and_hit_launch'); ?>');

                    // Enable buttons
                    $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                    return false;
                } else {
                    // Start looping on all steps of re-encryption
                    userShareKeysReencryptionNext(data.userId, data.step, data.start, self_change);
                }
            }
        );
    }

    function userShareKeysReencryptionNext(userId, step, start, self_change = false) {
        var stepText = '';

        // Prepare progress string
        if (step === 'step1') {
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
            $("#dialog-encryption-keys-progress").html('<b><?php echo langHdl('encryption_keys'); ?> - ' +
                stepText + '</b> [' + start + ' - ' + (parseInt(start) + 200) + '] ' +
                '... <?php echo langHdl('please_wait'); ?><i class="fas fa-spinner fa-pulse ml-3 text-primary"></i>');

            // Do query
            $.post(
                "sources/main.queries.php", {
                    type: "user_sharekeys_reencryption_next",
                    'action': step,
                    'start': start,
                    'length': 200,
                    userId: userId,
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
                        $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                        return false;
                    } else {
                        // Start looping on all steps of re-encryption
                        userShareKeysReencryptionNext(data.userId, data.step, data.start, self_change);
                    }
                }
            );
        } else {
            // Finished
            $("#dialog-encryption-keys-progress").html('<i class="fas fa-check text-success mr-3"></i><?php echo langHdl('done'); ?>');

            toastr.remove();
            toastr.success(
                '<?php echo langHdl('logout_on_going'); ?><i class="fas fa-circle-notch fa-spin fa-2x ml-3"></i>',
                '', {
                    timeOut: 4000
                }
            );

            window.location.href = "./includes/core/logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>
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