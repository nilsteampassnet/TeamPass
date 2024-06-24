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
 * @file      load.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;

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
            '<?php echo $lang->get('index_maintenance_mode_admin'); ?>',
            '<?php echo $lang->get('information'); ?>', {
                timeOut: 0
            }
        );
    </script>
<?php
}
$lang = new Language($session->get('user-language') ?? 'english');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
?>

<script type="text/javascript">
    var userScrollPosition = 0,
        debugJavascript = true;
    let hourInMinutes = 60;


    $(document).ready(function() {
        // Don't redirect in some conditions
        // Exclude case where we get a code from Azure Entra
        if ($(location).attr('href').includes('?') === true && $(location).attr('href').includes('post_type') === false && $(location).attr('href').includes('code') === false && (store.get('teampassUser') === undefined || parseInt(store.get('teampassUser').user_id) <= 0)) {
            $(location).attr('href', 'index.php?session=expired');
            return false;
        }

        // prevent a resubmit on refresh and back button
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    });

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
                type_category: 'action_user',
                duration : parseInt(duration, 10) * hourInMinutes,
                key: "<?php echo $session->get('key'); ?>"
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
    if (store.get('teampassUser') !== undefined && parseInt(store.get('teampassUser').user_id) > 0
        && String('<?php echo $session->get('key'); ?>') === store.get('teampassUser').sessionKey
        && (Date.now() - store.get('teampassUser').sessionStartTimestamp) < (store.get('teampassUser').sessionDuration * 1000)
    ) {
        $.when(
            // Load teampass settings
            loadSettings()
        ).then(function() {
            $.when(
                // Refresh list of last items shopwn
                refreshListLastSeenItems()
            ).then(function() {
                // Check if new privatekey needs to be adapted
                var data = {
                    'user_id': store.get('teampassUser').user_id,
                    'fields' : 'special, auth_type, is_ready_for_usage, ongoing_process_id, otp_provided, keys_recovery_time',
                }
                if (debugJavascript === true) {
                    console.info('Sending request for:');
                    console.log(data);
                }
                $.post(
                    "sources/main.queries.php", {
                        type: "get_user_info",
                        type_category: 'action_user',
                        data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                        if (debugJavascript === true) {
                            console.info('Get user info results:');
                            console.log(data);
                        }

                        // update local storage
                        store.update(
                            'teampassUser', {},
                            function(teampassUser) {
                                teampassUser.special = data.queryResults.special;
                                teampassUser.auth_type = data.queryResults.auth_type;
                                teampassUser.is_ready_for_usage = data.queryResults.is_ready_for_usage;
                                teampassUser.ongoing_process_id = data.queryResults.ongoing_process_id;
                                teampassUser.keys_recovery_time = data.queryResults.keys_recovery_time;
                            }
                        );

                        if (data.error === false && data.queryResults.special === 'generate-keys') {
                            // Get generation keys progress status
                            getGenerateKeysProgress(store.get('teampassUser').user_id);

                            // ----
                        } else if (data.error === false && data.queryResults.special === 'auth-pwd-change' && data.queryResults.auth_type === 'local') {
                            // USer's password has been reseted, he shall change it
                            if (debugJavascript === true) console.log('User has to change his auth password')
                            // HIde
                            $('.content-header, .content').addClass('hidden');

                            // Show passwords inputs and form
                            $('#dialog-user-change-password-info')
                                .html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('user_has_to_change_password_info');?>')
                                .removeClass('hidden');
                            $('#dialog-user-change-password').removeClass('hidden');

                        // ----
                        } else if (data.error === false && data.queryResults.special === 'auth-pwd-change' && data.queryResults.auth_type === 'ldap') {
                            // USer's password has been reseted, he shall change it
                            if (debugJavascript === true) console.log('LDAP user password has to change his auth password')
                            // HIde
                            $('.content-header, .content').addClass('hidden');

                            // Show passwords inputs and form
                            $('#dialog-ldap-user-change-password-info')
                                .html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('ldap_user_has_changed_his_password');?>')
                                .removeClass('hidden');
                            $('#dialog-ldap-user-change-password').removeClass('hidden');
                            
                            // ----
                        } else if (
                            (data.error === false && data.queryResults.special === 'user_added_from_ldap' && data.queryResults.auth_type === 'ldap')
                            || (typeof data.queryResults !== 'undefined' && data.queryResults.special === 'otc_is_required_on_next_login')
                            || parseInt(data.queryResults.otp_provided) === 0
                        ) {
                            // USer's password has been reseted, he shall change it
                            if (debugJavascript === true) console.log('NEW LDAP user password - we need to encrypt items')
                            // HIde
                            $('.content-header, .content').addClass('hidden');

                            // Show form
                            $('#dialog-ldap-user-build-keys-database').removeClass('hidden');
                        } else if (typeof data.queryResults !== 'undefined' && data.queryResults.special === 'recrypt-private-key') {
                            // USer's password has been reseted, he shall change it
                            if (debugJavascript === true) console.log('NEW LDAP - we need to encrypt private key')
                            // HIde
                            $('.content-header, .content').addClass('hidden');

                            // Show form
                            $('#dialog-ldap-user-change-password').removeClass('hidden');
                        } else if (typeof data.queryResults !== 'undefined' && data.queryResults.keys_recovery_time === null && store.get('teampassUser').user_admin === 0) {
                            // User has not yet recovered his keys
                            $('#open_user_keys_management').removeClass('hidden');

                            // if profile page, then show warning
                            if (window.location.href.indexOf('page=profile') > -1) {
                                $('#keys_not_recovered').removeClass('hidden');
                                $('#profile-keys_download-date').text('<?php echo $lang->get('none'); ?>');
                            }

                            // handle button click redirection to profile page
                            $("#open_user_keys_management").on('click', function(event) {
                                event.preventDefault();
                                document.location.href = "index.php?page=profile&tab=keys";
                            });
                        }
                    }
                );
            }).then(function() {
                if (store.get('teampassSettings') === undefined || parseInt(store.get('teampassSettings').enable_tasks_manager) === 0) {
                    if (debugJavascript === true) {
                        console.log('Now sending emails');
                    }
                    setTimeout(
                        function() {
                            $.when(
                                // send email
                                $.post(
                                    "sources/main.queries.php", {
                                        type: "send_waiting_emails",
                                        type_category: 'action_mail',
                                        key: "<?php echo $session->get('key'); ?>"
                                    }
                                )
                            ).then(function() {
                                // send statistics
                                $.post(
                                    "sources/main.queries.php", {
                                        type: "sending_statistics",
                                        type_category: 'action_system',
                                        key: "<?php echo $session->get('key'); ?>"
                                    }
                                );
                            });
                        },
                        3000
                    );
                }

                // Save user location
                //console.info("DEBUG - Save user location -"+store.get('teampassUser').location_stored)
                if (store.get('teampassUser').location_stored !== 1) {
                // Save user location
                    var data = {
                        'user_id': store.get('teampassUser').user_id,
                        'action': 'perform',
                    }
                    $.post(
                        "sources/main.queries.php", {
                            type: 'save_user_location',
                            type_category: 'action_user',
                            data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                            key: "<?php echo $session->get('key'); ?>"
                        },
                        function(data) {
                            // update local storage
                            store.update(
                                'teampassUser', {},
                                function(teampassUser) {
                                    teampassUser.location_stored = 1;
                                }
                            );
                        }
                    );
                }
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
        /*console.log('User has to regenerate keys')
        // HIde
        $('.content-header, .content').addClass('hidden');
        $('#dialog-user-temporary-code-info').html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('renecyption_expected');?>');

        // Show passwords inputs and form
        $('#dialog-user-temporary-code').removeClass('hidden');
        */
        
        // ---
    } else if (store.get('teampassUser') !== undefined &&
        store.get('teampassUser').special === 'ldap_password_has_changed_do_reencryption'
    ) {
        // Now we need to perform re-encryption due to LDAP password change
        if (debugJavascript === true) console.log('show password change');
        // HIde
        $('.content-header, .content, #button_do_sharekeys_reencryption').addClass('hidden');
        $('#warning-text-reencryption').html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('ldap_password_change_warning'); ?>');

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
            '<i class="fa-solid fa-user-shield fa-lg warning mr-2"></i><?php echo $lang->get('caution'); ?>',
            '<?php echo $lang->get('login_attempts_identified_since_last_connection'); ?>',
            '<?php echo $lang->get('see_detail'); ?>',
            '<?php echo $lang->get('close'); ?>',
            false,
            false,
            false
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
            toastr.info('<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>');

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
            toastr.info('<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>');

            defusePskRemoval(store.get('teampassUser').user_id, 'psk', 0, -1);
            
            function defusePskRemoval(userId, step, start, counterItemsToTreat)
            {
                if (step === 'psk') {
                    // Inform user
                    $("#user-current-defuse-psk-progress").html('<b><?php echo $lang->get('encryption_keys'); ?> </b> [' + start + ' - ' + (parseInt(start) + <?php echo NUMBER_ITEMS_IN_BATCH;?>) + '] ' +
                        '... <?php echo $lang->get('please_wait'); ?><i class="fa-solid fa-spinner fa-pulse ml-3 text-primary"></i>');

                    var data = {
                        'userPsk' : $('#user-current-defuse-psk').val(),
                        'start': start,
                        'length': <?php echo NUMBER_ITEMS_IN_BATCH;?>,
                        'user_id': userId,
                        'counterItemsToTreat': counterItemsToTreat
                    };
                    // Do query
                    $.post(
                        "sources/main.queries.php", {
                            'type': "user_psk_reencryption",
                            'type_category': 'action_key',
                            'data': prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                            'key': '<?php echo $session->get('key'); ?>'
                        },
                        function(data) {
                            data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                            if (debugJavascript === true) console.log(data)
                            if (data.error === true) {
                                // error
                                toastr.remove();
                                toastr.error(
                                    data.message,
                                    '<?php echo $lang->get('caution'); ?>', {
                                        timeOut: 5000,
                                        progressBar: true
                                    }
                                );

                                // Enable buttons
                                $("#user-current-defuse-psk-progress").html('<?php echo $lang->get('provide_current_psk_and_click_launch'); ?>');
                                $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
                                return false;
                            } else {
                                // Start looping on all steps of re-encryption
                                defusePskRemoval(data.userId, data.step, data.start, data.counterItemsToTreat);
                            }
                        }
                    );
                } else {
                    // Finished
                    $("#user-current-defuse-psk-progress").html('<i class="fa-solid fa-check text-success mr-3"></i><?php echo $lang->get('done'); ?>');

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
            } else if ($(this).data('name') === 'sync-new-ldap-password') {
                // This case permits to handle a case where user has changed his password in LDAP                
                if (debugJavascript === true) console.log('LDAP user password has to change his auth password')
                // HIde
                $('.content-header, .content').addClass('hidden');

                // Show passwords inputs and form
                $('#dialog-ldap-user-change-password-info')
                    .html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('ldap_user_has_changed_his_password');?>')
                    .removeClass('hidden');
                $('#dialog-ldap-user-change-password').removeClass('hidden');

                // ----
            } else if ($(this).data('name') === 'password-change') {
                //console.log('show password change')
                // HIde
                $('.content-header, .content, #button_do_user_change_password').addClass('hidden');

                // Add DoCheck button
                $('#button_do_user_change_password').after('<button class="btn btn-primary" id="button_do_pwds_checks"><?php echo $lang->get('perform_checks'); ?></button>');

                // Show passwords inputs and form
                $('#dialog-user-change-password-progress').html('<i class="icon fa-solid fa-info mr-2"></i><?php echo $lang->get('change_your_password_info_message'); ?>');
                $('#dialog-user-change-password').removeClass('hidden');

                // Actions
                $('#button_do_pwds_checks').click(function() {
                    if ($('#profile-password').val() !== $('#profile-password-confirm').val()) {
                        $('#button_do_user_change_password').addClass('hidden');
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('passwords_not_the_same'); ?>',
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );
                    } else if (parseInt($('#profile-password-complex').val()) >= parseInt(store.get('teampassSettings').personal_saltkey_security_level)) {
                        $('#button_do_user_change_password').removeClass('hidden');
                        $('#button_do_pwds_checks').remove();
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('hit_launch_to_start'); ?>',
                            '<?php echo $lang->get('ready_to_go'); ?>', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#button_do_user_change_password').addClass('hidden');
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('complexity_level_not_reached'); ?>',
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    }
                });
                
                // ----
            } else if ($(this).data('name') === 'profile') {
                // Show profile page
                //document.location.href = "index.php?page=profile";
                window.location.replace("index.php?page=profile");

                // ----
            } else if ($(this).data('name') === 'logout') {
                // Logout directly to login form
                window.location.href = "./includes/core/logout.php?token=<?php echo $session->get('key'); ?>";

                // ----
            } else if ($(this).data('name') === 'generate-new_keys') {
                // User wants to generate new keys
                if (debugJavascript === true) console.log('show new keys generation');
                $('#warningModalButtonAction').attr('data-button-confirm', 'false');

                // SHow modal
                showModalDialogBox(
                    '#warningModal',
                    '<i class="fa-solid fa-person-digging fa-lg warning mr-2"></i><?php echo $lang->get('generate_new_keys'); ?> <b>',
                    '<div class="form-group">'+
                        '<?php echo $lang->get('generate_new_keys_info'); ?>' +
                    '</div>' +
                    '<div class="hidden" id="new-encryption-div">' +
                        '<div class="row">' +
                            '<div class="input-group mb-2">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text"><?php echo $lang->get('confirm_password'); ?></span>' +
                                '</div>' +
                                '<input id="encryption-otp" type="password" class="form-control form-item-control">' +
                                '<div class="input-group-append">' +
                                    '<button class="btn btn-outline-secondary btn-no-click" id="show-encryption-otp" title="<?php echo $lang->get('mask_pw'); ?>"><i class="fa-solid fa-low-vision"></i></button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="row mt-2<?php echo isset($SETTINGS['enable_pf_feature']) === true && (int) $SETTINGS['enable_pf_feature'] === 1  ? '' : ' hidden'; ?>">' +
                            '<h6><?php echo $lang->get('provide_recovery_keys'); ?></h6>' +
                            '<div class="input-group mb-2">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text"><?php echo $lang->get('public_key'); ?></span>' +
                                '</div>' +
                                '<textarea rows="1" id="recovery-public-key" class="form-control form-item-control"></textarea>' +
                            '</div>' +
                            '<div class="input-group mb-2">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text"><?php echo $lang->get('private_key'); ?></span>' +
                                '</div>' +
                                '<textarea rows="2" id="recovery-private-key" class="form-control form-item-control"></textarea>' +
                            '</div>' +
                        '</div>' +
                        '<div class="row mt-2<?php echo isset($SETTINGS['enable_pf_feature']) === true && (int) $SETTINGS['enable_pf_feature'] === 1  ? '' : ' hidden'; ?>">' +
                            '<div class="alert" id="confirm-no-recovery-keys-div">' +
                                '<div class="form-check">' +
                                    '<input type="checkbox" class="form-check-input" id="confirm-no-recovery-keys">' +
                                    '<label class="form-check-label ml-1" for="confirm-no-recovery-keys"><?php echo $lang->get('no_recovery_keys'); ?></label>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>',
                    '<?php echo $lang->get('perform'); ?>',
                    '<?php echo $lang->get('close'); ?>'
                );
                // XSS protection
                $('#encryption-otp').val(store.get('teampassUser').pwd);

                // Manage show/hide password
                $('#show-encryption-otp')
                    .mouseup(function() {
                        $('#encryption-otp').attr('type', 'password');
                    })
                    .mousedown(function() {
                        $('#encryption-otp').attr('type', 'text');
                    });
                $('.btn-no-click')
                    .click(function(e) {
                        e.preventDefault();
                    });
                $('#confirm-no-recovery-keys').click(function(e) {
                    //e.preventDefault();
                    if ($(this).prop('checked') === true) {
                        $('#confirm-no-recovery-keys-div').addClass('alert-danger');
                    } else {
                        $('#confirm-no-recovery-keys-div').removeClass('alert-danger');
                    }
                });
                $('#recovery-public-key, #recovery-private-key').focusout(function(e) {
                    e.preventDefault();
                    if ($('#recovery-public-key').val() !== '' && $('#recovery-private-key').val() !== '') {
                        $('#confirm-no-recovery-keys-div').removeClass('alert-danger');
                        $('#confirm-no-recovery-keys').prop('checked', false);
                    } else {
                        
                    }
                });

                // Manage click on button PERFORM
                $(document).on('click', '#warningModalButtonAction', function() {
                    // On PERFORM click
                    if ($('#warningModalButtonAction').attr('data-button-confirm') === 'false') {
                        $("#new-encryption-div").removeClass('hidden');
                        $('#warningModalButtonAction')
                            .html('<i class="fa-solid fa-triangle-exclamation warning mr-2"></i><?php echo $lang->get('confirm'); ?>')
                            .attr('data-button-confirm', 'true');

                    } else if ($('#warningModalButtonAction').attr('data-button-confirm') === 'true') {
                        // As reencryption relies on user's password
                        // ensure we have it
                        if ($('#encryption-otp').val() === '' || 
                            ($('#recovery-public-key').val() === '' || $('#recovery-private-key').val() === '') && $('#confirm-no-recovery-keys').prop('checked') === false
                        ) {
                            // No user password provided
                            $('#warningModalButtonAction')
                                .html('<?php echo $lang->get('perform'); ?>')
                                .attr('data-button-confirm', 'false');
                            
                        } else {
                            // We have the password, start reencryption
                            $('#warningModalButtonAction')
                                .addClass('disabled')
                                .html('<i class="fa-solid fa-spinner fa-spin"></i>');
                            $('#warningModalButtonClose').addClass('disabled');

                            // update the process
                            // add all tasks
                            var parameters = {
                                'user_id': parseInt(store.get('teampassUser').user_id),
                                'user_pwd': $('#encryption-otp').val(),
                                'encryption_key': '',
                                'delete_existing_keys': true,
                                'encrypt_with_user_pwd': true,
                                'send_email_to_user': true,
                                'email_body': 'email_body_user_config_4',
                                'generate_user_new_password': false,
                                'user_self_change': true,
                                'recovery_public_key': $('#recovery-public-key').val(),
                                'recovery_private_key': $('#recovery-private-key').val(),
                            };

                            $.post(
                                "sources/main.queries.php", {
                                    type: "user_new_keys_generation",
                                    type_category: 'action_key',
                                    data: prepareExchangedData(JSON.stringify(parameters), "encode", "<?php echo $session->get('key'); ?>"),
                                    key: "<?php echo $session->get('key'); ?>"
                                },
                                function(data_next1) { 
                                    data_next1 = prepareExchangedData(data_next1, 'decode', '<?php echo $session->get('key'); ?>');
                                    if (debugJavascript === true) console.log(data_next1)

                                    if (data_next1.error !== false) {
                                        // Show error
                                        toastr.remove();
                                        toastr.error(
                                            data_next1.message,
                                            '<?php echo $lang->get('caution'); ?>', {
                                                timeOut: 5000,
                                                progressBar: true
                                            }
                                        );
                                    } else {
                                        $("#new-encryption-div").after('<div><?php echo $lang->get('generate_new_keys_end'); ?></div>');
                                        // Show warning
                                        $('#user_not_ready').removeClass('hidden');
                                        // update local storage
                                        store.update(
                                            'teampassUser', {},
                                            function(teampassUser) {
                                                teampassUser.is_ready_for_usage = 0;
                                            }
                                        );
                                        $("#warningModalButtonAction").addClass('hidden');
                                        $('#warningModalButtonClose').removeClass('disabled');

                                        // Get follow up
                                        setTimeout(
                                            getGenerateKeysProgress,
                                            5000,
                                            store.get('teampassUser').user_id
                                        );
                                    }
                                }
                            );
                        }
                    }
                });

                // ----
            } else if ($(this).data('name') === 'generate-an-otp') {
                // User wants to generate an OTP
                $('#warningModalButtonAction').attr('data-button-confirm', 'false');

                // SHow modal
                showModalDialogBox(
                    '#warningModal',
                    '<i class="fa-solid fa-qrcode fa-lg warning mr-2"></i><?php echo $lang->get('generate_an_otp'); ?> <b>',
                    '<div class="form-group">'+
                        '<div class="row">' +
                            '<div class="input-group mb-2">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text"><?php echo $lang->get('generated-otp'); ?></span>' +
                                '</div>' +
                                '<input id="new-otp" type="text" class="form-control form-item-control" value="">' +
                                '<div class="input-group-append">' +
                                    '<button class="btn btn-outline-secondary btn-no-click" id="generate-new-secret" title="<?php echo $lang->get('generate_new_otp'); ?>"><i class="fa-solid fa-rotate"></i></button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="row">' +
                            '<div class="input-group mb-2">' +
                                '<div class="input-group-prepend">' +
                                    '<span class="input-group-text"><?php echo $lang->get('qrcode_label'); ?></span>' +
                                '</div>' +
                                '<input type="text" rows="1" id="otp-label" class="form-control form-item-control" value="">' +
                                '<div class="input-group-append">' +
                                    '<button class="btn btn-outline-secondary btn-no-click" id="generate-new-qrcode" title="<?php echo $lang->get('generate_qrcode'); ?>"><i class="fa-solid fa-qrcode"></i></button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="" style="height:200px;">' +
                            '<div class="text-center" id="new-otp-qrcode">' +
                            '</div>' +
                        '</div>' +
                    '</div>',
                    '',
                    '<?php echo $lang->get('close'); ?>'
                );

                launchOtpGeneration(false);

                // Manage click on button PERFORM
                $(document).on('click', '#generate-new-qrcode', function() {
                    event.preventDefault();
                    if ($('#otp-label').val() === '') {
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('please_provide_label'); ?>',
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        launchOtpGeneration(true, true, 'generate-new-qrcode', $(this).html());
                    }
                });

                $(document).on('click', '#generate-new-secret', function() {
                    event.preventDefault();
                    launchOtpGeneration(false, null, 'generate-new-secret', $(this).html());
                });

                function launchOtpGeneration(withQrCode, withSecret, zone, buttonHtml)
                {
                    $('#'+zone).html('<i class="fa-solid fa-spinner fa-spin text-warning"></i>');

                    // Load OTP
                    var parameters = {
                        'label': $('#otp-label').val(),
                        'with_qrcode': withQrCode,
                        'secret_key': withSecret === true ? $('#new-otp').val() : '',
                    }
                    $.post(
                        "sources/main.queries.php", {
                            type: "generate_an_otp",
                            type_category: 'action_utils',
                            data: prepareExchangedData(JSON.stringify(parameters), "encode", "<?php echo $session->get('key'); ?>"),
                            key: "<?php echo $session->get('key'); ?>"
                        },
                        function(data) { 
                            data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                            if (debugJavascript === true) console.log(data)

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
                                $('#new-otp').val(data.secret);
                                if (withQrCode === true) {
                                    $('#new-otp-qrcode').html('<img class="text-center" src="' + data.qrcode + '" />');
                                } else {
                                    $('#new-otp-qrcode').html('');
                                }
                            }
                            $('#'+zone).html(buttonHtml);
                        }
                    );
                }

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
            '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
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
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, '<?php echo $session->get('key'); ?>');

                // Is there an error?
                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('warning'); ?>',
                        '<?php echo $lang->get('caution'); ?>', {
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
                        '<?php echo $lang->get('alert_page_will_reload'); ?>'
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
            if (debugJavascript === true) console.log($('#' + elementId).val())
            return String($('#' + elementId).val());
        }
    });

    clipboardCopy.on('success', function(e) {
        toastr.remove();
        toastr.info(
            '<?php echo $lang->get('copy_to_clipboard'); ?>',
            '<?php echo $lang->get('information'); ?>', {
                timeOut: 2000
            }
        );
    });
    
    // Progress bar
    setTimeout(
        function() {
            $(".fade").removeClass("out");

            // Is user not ready
            if (typeof store.get('teampassUser') !== 'undefined' && typeof store.get('teampassUser').is_ready_for_usage !== 'undefined' && parseInt(store.get('teampassUser').is_ready_for_usage) === 0) {
                $('#user_not_ready').removeClass('hidden');
            }
        },
        5000
    );


    /**
    * USER HAS DECIDED TO CHANGE HIS AUTH PASSWORD
     */
    $(document).on('click', '#dialog-user-change-password-do', function() {
        // Start by changing the user password and send it by email
        if ($('#profile-password-confirm').val() !== $('#profile-password').val()) {
            // Show error
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('index_pw_error_identical'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }
        if ($('#profile-current-password').val() !== "" && $('#profile-password').val() !== "" && $('#profile-password-confirm').val() !== "") {
            // Case where a user is changing his authentication password
            if (debugJavascript === true) console.log('Reencryption based upon user decision to change his auth password');

            // Show progress
            $('#dialog-user-change-password-progress').html('<b><?php echo $lang->get('please_wait'); ?></b><i class="fa-solid fa-spinner fa-pulse ml-3 text-primary"></i>');
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );
            
            // Disable buttons
            $('#dialog-user-change-password-do, #dialog-user-change-password-close').attr('disabled', 'disabled');
            
            data = {
                'user_id': store.get('teampassUser').user_id,
                'old_password': $('#profile-current-password').val(),
                'new_password': $('#profile-password').val(),
            }
            if (debugJavascript === true) console.log(data);

            // Check user current password
            // and change the password
            // and use the password to re-encrypt the privatekey
            $.post(
                'sources/main.queries.php', {
                    type: 'change_user_auth_password',
                    type_category: 'action_password',
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

                        $("#dialog-user-change-password-progress").html('<?php echo $lang->get('fill_in_fields_and_hit_launch'); ?>');

                        // Enable buttons
                        $('#dialog-user-change-password-do, #dialog-user-change-password-close').removeAttr('disabled');
                    } else {
                        // SUCCESS
                        $('#dialog-user-change-password-close').removeAttr('disabled');
                        toastr.remove();
                        toastr.success(
                            data.message,
                            '<?php echo $lang->get('success'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        $("#dialog-user-change-password-progress").html('');
                        $('#dialog-user-change-password').addClass('hidden');
                        $('.content-header, .content').removeClass('hidden');
                    }
                }
            );
        } else {
            // Show error
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('password_cannot_be_empty'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
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
        if (debugJavascript === true) console.log('Reencryption based upon admin decision to change user auth password');

        // Show progress
        $('#dialog-admin-change-user-password-progress').html('<b><?php echo $lang->get('please_wait'); ?></b><i class="fa-solid fa-spinner fa-pulse ml-3 text-primary"></i>');
        toastr.remove();
        toastr.info(
            '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );
        
        // Disable buttons
        $('#dialog-admin-change-user-password-do, #dialog-admin-change-user-password-close').attr('disabled', 'disabled');            
        
        // ENsure we have a user id
        if ($('#admin_change_user_password_target_user').val() !== '') {
            // Case where change is for user's account
            // update the process
            // add all tasks
            var parameters = {
                'user_id': parseInt($('#admin_change_user_password_target_user').val()),
                'user_pwd': '',
                'encryption_key': '',
                'delete_existing_keys': true,
                'send_email_to_user': true,
                'encrypt_with_user_pwd': true,
                'generate_user_new_password': true,
                'email_body': 'email_body_user_config_3',
            };
            $.post(
                "sources/main.queries.php", {
                    type: "user_new_keys_generation",
                    type_category: 'action_key',
                    data: prepareExchangedData(JSON.stringify(parameters), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data_next1) { 
                    data_next1 = prepareExchangedData(data_next1, 'decode', '<?php echo $session->get('key'); ?>');
                    if (debugJavascript === true) console.log(data_next1)

                    if (data_next1.error !== false) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data_next1.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        // Reload table
                        if (typeof oTable !== 'undefined') {
                            oTable.ajax.reload();
                        }

                        // Show user password to admin
                        if ($('#dialog-admin-change-user-password-do-show-password').is(':checked') === true && data_next1.user_password !== '') {
                            showModalDialogBox(
                                '#warningModal',
                                '<i class="fas fa-user-shield fa-lg warning mr-2"></i><?php echo $lang->get('caution'); ?>',
                                '<?php echo $lang->get('user_password'); ?>&nbsp;<code class="ml-2">'+data_next1.user_password+'</code>',
                                '',
                                '<?php echo $lang->get('close'); ?>',
                                false,
                                false,
                                false
                            );
                        }

                        $('#dialog-admin-change-user-password-do-show-password').iCheck('uncheck');                        
                        $("#dialog-admin-change-user-password-progress").html('<?php echo $lang->get('generate_new_keys_end'); ?>');
                        // Show warning
                        // Enable buttons
                        $('#dialog-admin-change-user-password-close').removeAttr('disabled');
                        toastr.remove();
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
            if (debugJavascript === true) console.log('Preparing for email sending');
            
            // Prepare data
            var data = {
                'receipt': $('#temp-user-email').val(),
                'subject': '[Teampass] <?php echo $lang->get('your_new_password');?>',
                'body': '<?php echo $lang->get('email_body_temporary_login_password');?>',
                'pre_replace' : {
                    '#enc_code#' : $('#temp-user-pwd').val(),
                }
            }
            if (debugJavascript === true) console.log(data);
            // Prepare form
            $('#dialog-admin-change-user-password-info').html('<?php echo $lang->get('sending_email_message');?>');
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );

            // Launch action
            $.post(
                'sources/main.queries.php', {
                    type: 'mail_me',
                    type_category: 'action_mail',
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    if (debugJavascript === true) console.log(data);
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
                            '<?php echo $lang->get('done'); ?>',
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
        if (debugJavascript === true) console.log('Reencryption based upon users temporary code');

        // Show progress
        $('#dialog-user-temporary-code-progress').html('<b><?php echo $lang->get('please_wait'); ?></b><i class="fa-solid fa-spinner fa-pulse ml-3 text-primary"></i>');
        toastr.remove();
        toastr.info(
            '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );
        
        // Disable buttons
        $('#dialog-user-temporary-code-do, #dialog-user-temporary-code-close').attr('disabled', 'disabled');            
        
        // Start by testing if the temporary code is correct to decrypt an item
        data = {
            'user_id': store.get('teampassUser').user_id,
            'password': $('#dialog-user-temporary-code-value').val(),
        }
        if (debugJavascript === true) {
            console.log('Testing if temporary code is correct');
            console.log(data);
        }
        $.post(
            'sources/main.queries.php', {
                type: 'test_current_user_password_is_correct',
                type_category: 'action_password',
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

                    $("#dialog-user-temporary-code-progress").html('<?php echo $lang->get('fill_in_fields_and_hit_launch'); ?>');

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
                    if (debugJavascript === true) console.log(data);
                    
                    $.post(
                        'sources/main.queries.php', {
                            type: 'change_private_key_encryption_password',
                            type_category: 'action_key',
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

                                // Enable buttons
                                $('#dialog-user-temporary-code-do, #dialog-user-temporary-code-close').removeAttr('disabled');
                            } else {
                                // Inform user
                                // Enable close button
                                $('#dialog-user-temporary-code-close').removeAttr('disabled');
                                $('#dialog-user-temporary-code-do').attr('disabled', 'disabled');

                                // Finished
                                $("#dialog-user-temporary-code-progress").html('<i class="fa-solid fa-check text-success mr-3"></i><?php echo $lang->get('done'); ?>');
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
        if (debugJavascript === true) console.log('Building items keys database for new LDAP user');

        // Show progress
        $('#dialog-ldap-user-build-keys-database-progress').html('<b><?php echo $lang->get('please_wait'); ?></b><i class="fa-solid fa-spinner fa-pulse ml-3 text-primary"></i>');
        toastr.remove();
        toastr.info(
            '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
        );
        
        // Disable buttons
        $('#dialog-ldap-user-build-keys-database-do, #dialog-ldap-user-build-keys-database-close').attr('disabled', 'disabled');            
        
        // Start by testing if the temporary code is correct to decrypt an item
        data = {
            'user_id': store.get('teampassUser').user_id,
            'password' : $('#dialog-ldap-user-build-keys-database-code').val(),
        }
        if (debugJavascript === true) {
            console.log('Testing if temporary code is correct in LDAP user case');
            console.log(data);
        }

        $.post(
            'sources/main.queries.php', {
                type: 'test_current_user_password_is_correct',
                type_category: 'action_password',
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

                    $("#dialog-ldap-user-build-keys-database-progress").html('<?php echo $lang->get('bad_code'); ?>');

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
                    if (debugJavascript === true) console.log(data);
                    
                    $.post(
                        'sources/main.queries.php', {
                            type: 'change_private_key_encryption_password',
                            type_category: 'action_key',
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

                                
                                $("#dialog-ldap-user-build-keys-database-progress").html('<i class="fa-solid fa-exclamation-circle text-danger mr-3"></i><?php echo $lang->get('bad_code'); ?>');

                                // Enable buttons
                                $('#dialog-ldap-user-build-keys-database-do, #dialog-ldap-user-build-keys-database-close').removeAttr('disabled');
                            } else {
                                // Inform user
                                // Enable close button
                                $('#dialog-ldap-user-build-keys-database-close').removeAttr('disabled');
                                $('#dialog-ldap-user-build-keys-database-do').attr('disabled', 'disabled');

                                // Finished
                                $("#dialog-ldap-user-build-keys-database-progress").html('<i class="fa-solid fa-check text-success mr-3"></i><?php echo $lang->get('done'); ?>');
                                toastr.remove();

                                store.update(
                                    'teampassUser', {},
                                    function(teampassUser) {
                                        teampassUser.special = 'none';
                                        teampassUser.otp_provided = 1;
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
            if (debugJavascript === true) console.log('Reencryption based upon user auth password changed in LDAP');

            // Show progress
            $('#dialog-ldap-user-change-password-progress').html('<b><?php echo $lang->get('please_wait'); ?></b><i class="fa-solid fa-spinner fa-pulse ml-3 text-primary"></i>');
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
            );
            
            // Disable buttons
            $('#dialog-ldap-user-change-password-do, #dialog-ldap-user-change-password-close').attr('disabled', 'disabled');
            
            data = {
                'user_id': store.get('teampassUser').user_id,
                'previous_password': $('#dialog-ldap-user-change-password-old').val(),
                'current_password': $('#dialog-ldap-user-change-password-current').val(),
            }
            if (debugJavascript === true) console.log(data);

            // Check user current password
            // and change the password
            // and use the password to re-encrypt the privatekey
            $.post(
                'sources/main.queries.php', {
                    type: 'change_user_ldap_auth_password',
                    type_category: 'action_password',
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

                        $("#dialog-ldap-user-change-password-progress").html('<?php echo $lang->get('fill_in_fields_and_hit_launch'); ?>');

                        // Enable buttons
                        $('#dialog-ldap-user-change-password-do, #dialog-ldap-user-change-password-close').removeAttr('disabled');
                    } else {
                        // SUCCESS
                        $('#dialog-ldap-user-change-password-close').removeAttr('disabled');
                        toastr.remove();
                        toastr.success(
                            data.message,
                            '<?php echo $lang->get('success'); ?>', {
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
                '<?php echo $lang->get('password_cannot_be_empty'); ?>',
                '<?php echo $lang->get('caution'); ?>', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
        }
    });
    $(document).on('click', '#dialog-ldap-user-change-password-close', function() {
        // HIde
        $('.content-header, .content').removeClass('hidden');

        // SHow form
        $('#dialog-ldap-user-change-password, #dialog-ldap-user-change-password-info').addClass('hidden');
    });
    // --- END ---


    function loadSettings() {
        /*if (Number.isInteger(<?php echo $session->get('user-id'); ?>) === false) {
            return false;
        }*/
        if (debugJavascript === true) {
            console.log('Key appel get_teampass_settings : <?php echo $session->get('key'); ?>');
        }

        return $.post(
            "sources/main.queries.php", {
                type: "get_teampass_settings",
                type_category: 'action_system',
                key: '<?php echo $session->get('key'); ?>',
                xhrFields: {
                    withCredentials: true
                }
            },
            function(data) {
                try {
                    data = prepareExchangedData(
                        data,
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
                    console.log('Loading settings result:');
                    console.log(data);
                }

                // Test if JSON object
                if (typeof data === 'object') {
                    // Store settings in localstorage
                    // except sensitive data
                    var sensitiveData = ['ldap_hosts','ldap_username','ldap_password','ldap_bdn','email','bck_script_passkey'];

                    store.remove("teampassSettings");

                    store.update(
                        'teampassSettings', {},
                        function(teampassSettings) {
                            $.each(data, function(key, value) {
                                const containsKey = sensitiveData.some(element => {
                                    if (key.includes(element)) {
                                        return true;
                                    }
                                    return false;
                                });

                                if (containsKey === false) {
                                    teampassSettings[key] = value;
                                }
                            });
                        }
                    );

                    // Store some User info
                    store.update(
                        'teampassUser', {},
                        function(teampassUser) {
                            teampassUser['user_admin'] = <?php echo returnIfSet($session->get('user-admin'), 0); ?>;
                            teampassUser['user_id'] = <?php echo returnIfSet($session->get('user-id'), 0); ?>;
                            teampassUser['user_manager'] = <?php echo returnIfSet($session->get('user-manager'), 0); ?>;
                            teampassUser['user_can_manage_all_users'] = <?php echo returnIfSet($session->get('user-can_manage_all_users'), 0); ?>;
                            teampassUser['user_read_only'] = <?php echo returnIfSet($session->get('user-read_only'), 0); ?>;
                            teampassUser['key'] = '<?php echo returnIfSet($session->get('key'), 0); ?>';
                            teampassUser['login'] = "<?php echo returnIfSet($session->get('user-login'), 0); ?>";
                            teampassUser['lastname'] = "<?php echo returnIfSet($session->get('user-lastname'), 0); ?>";
                            teampassUser['name'] = "<?php echo returnIfSet($session->get('user-name'), 0); ?>";
                            teampassUser['pskDefinedInDatabase'] = <?php echo returnIfSet($session->get('user-encrypted_psk'), 0, 1); ?>;
                            teampassUser['can_create_root_folder'] = <?php echo returnIfSet($session->get('user-can_create_root_folder'), 0); ?>;
                            teampassUser['special'] = '<?php echo returnIfSet($session->get('user-special'), 0); ?>';
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
        // Calculate the max time to be added
        var data = {
            'user_id': store.get('teampassUser').user_id,
        }
        $.post(
            "sources/main.queries.php", {
                type: 'user_get_session_time',
                type_category: 'action_user',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) {
                    console.log('debug')
                    console.log(data);
                }

                if (data.timestamp !== undefined && data.max_session_duration > 0) {
                    // Prepare modal
                    showModalDialogBox(
                        '#warningModal',
                        '<i class="fa-solid fa-clock fa-lg warning mr-2"></i><?php echo $lang->get('index_add_one_hour'); ?>',
                        '<div class="form-group">' +
                        '<label for="warningModal-input" class="col-form-label"><?php echo $lang->get('extend_session_duration_by') . ' (' . $lang->get('minutes') . ')'; ?>:</label>' +
                        '<input type="number" max="'+(60*60*24)+'" class="form-control" id="warningModal-input" value="60">' +
                        '</div>' +
                        '<div class="form-text text-muted"><i class=\"fa-solid fa-info-circle mr-2\"></i><?php echo $lang->get('maximum_session_expiration_time'); ?>: '+data.max_session_duration+'</div>',
                        '<?php echo $lang->get('confirm'); ?>',
                        '<?php echo $lang->get('cancel'); ?>'
                    );

                    // Limit the user choice
                    $('#warningModal-input')
                        .attr('max', data.max_time_to_add)
                        .val(data.max_time_to_add);


                    // Actions on modal buttons
                    $(document).on('click', '#warningModalButtonAction', function() {
                        // Check if max value is not reached
                        if ($('#warningModal-input').val() > data.max_time_to_add) {
                            $('#warningModal-input').addClass('is-invalid');
                            return false;
                        }

                        // SHow user
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
                        );

                        // Perform action
                        $.when(
                            IncreaseSessionTime(
                                fieldDomPurifierWithWarning('#warningModal-input')
                            )
                        ).then(function() {
                            toastr.remove();
                            toastr.success(
                                '<?php echo $lang->get('done'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );
                            $('#warningModal').modal('hide');
                        });
                    });

                    // Remove css
                    $(document).on('focus', '#warningModal-input', function() {
                        if ($(this).hasClass('is-invalid')) {
                            $(this).removeClass('is-invalid');
                        }
                    });
                } else {
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('error_unknown'); ?>',
                        '', {
                            timeOut: 1000
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
                type_category: 'action_user',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                try {
                    data = $.parseJSON(data)
                } catch (e) {
                    return false;
                }
                if (debugJavascript === true) {
                    console.log('Refresh last item seen result');
                    console.log(data);
                }
                //check if format error
                if (data.error === '') {
                    if (data.html_json === null || data.html_json === '') {
                        $('#index-last-pwds').html('<li><?php echo $lang->get('none'); ?></li>');
                    } else {
                        // Prepare HTML
                        var html_list = '';
                        $.each(data.html_json, function(i, value) {
                            html_list += '<li onclick="showItemCard($(this).closest(\'li\'))" class="pointer" data-item-edition="0" data-item-id="' + value.id + '" data-item-sk="' + value.perso + '" data-item-expired="0" data-item-restricted="' + value.restricted + '" data-item-display="1" data-item-open-edit="0" data-item-reload="0" data-item-tree-id="' + value.tree_id + '" data-is-search-result="0">' +
                                '<i class="fa-solid fa-caret-right mr-2"></i>' + value.label + '</li>';
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
                        '<?php echo $lang->get('caution'); ?>', {
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
            '<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>'
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
                type_category: 'action_system',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');

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

    // Prevent usage of browser back button
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        const queryString = window.location.search
        const urlParams = new URLSearchParams(queryString);
        if (urlParams.get('page') === 'items') {
            // go back to list
            // Play with show and hide classes
            $('.form-item, .form-item-action, .form-folder-action, .item-details-card, .columns-position, #item-details-card-categories, #form-item-upload-pickfilesList, #card-item-expired')
                .addClass('hidden');
            $('#folders-tree-card').removeClass('hidden');

            history.go(1);
        }
    };

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
    
    /**
     * Perform call to get progress status
     * 
     * @param {integer} userId
     */
    function getGenerateKeysProgress(userId) {
        var data = {
            'user_id': userId,
        };

        $.post(
            "sources/users.queries.php", {
                type: "get_generate_keys_progress",
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) {
                    console.info('Process generation keys status:');
                    console.log(data);
                }

                if (data.error === false) {
                    // Show progress
                    $('#user_not_ready_progress')
                        .text('(' + data.status + ')')
                        .addClass('ml-1');
                    $('#user_not_ready').removeClass('hidden');

                    if (data.status !== 'finished') {
                        // If not finished, then run again after 10 seconds
                        setTimeout(
                            getGenerateKeysProgress,
                            10000,
                            store.get('teampassUser').user_id
                        );
                    } else {
                        // Progress is finished
                        // We need to finalize user public/private keys
                        
                        $('#user_not_ready').addClass('hidden');
                        $("#warningModalButtonAction").removeClass('hidden');
                        $('#user_not_ready_progress').html('');
                        toastr.success(
                            data.message,
                            '<?php echo $lang->get('alert_page_will_reload'); ?>', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );

                        // Delay page submit
                        $(this).delay(2000).queue(function() {
                            document.location.reload(true);
                            $(this).dequeue();
                        });
                    }
                }
            }
        );
    }
</script>
