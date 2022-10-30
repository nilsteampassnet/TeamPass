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
 * @file      emails.js.php
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

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], '2fa', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $(document).on('click', '.button', function() {
        var action = $(this).data('action');

        toastr.remove();
        toastr.info('<?php echo langHdl('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if (action === 'send-test-email') {
            $.post(
                'sources/admin.queries.php', {
                    type: 'admin_email_test_configuration',
                    key: '<?php echo $_SESSION['key']; ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                    console.log(data);
                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.warning(
                            '<?php echo langHdl('none_selected_text'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
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
        } else if (action === 'send-waiting-emails') {
            $('#unsent-emails')
                .append('<span id="unsent-emails-progress" class="ml-3"></span>');
            sendEmailsBacklog();
        }
    });


    function sendEmailsBacklog(counter = "") {
        $('#unsent-emails-progress')
            .html('<i class="fas fa-cog fa-spin ml-2"></i>' +
                '<?php echo langHdl('remaining_emails_to_send'); ?> ' + counter);
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_email_send_backlog',
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    if (data.counter > 0) {
                        sendEmailsBacklog(data.counter);
                    } else {
                        $('#unsent-emails-progress')
                            .html('<i class="fas fa-check ml-2 text-success mr-2"></i>' +
                                '<?php echo langHdl('done'); ?>');
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
            }
        );
    }


    $(document).on('click', '#button-duo-save', function() {
        // Prepare data
        var data = {
            'akey': $('#duo_akey').val(),
            'ikey': $('#duo_ikey').val(),
            'skey': $('#duo_skey').val(),
            'host': $('#duo_host').val(),
        }
        console.log(data);

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'save_duo_in_sk_file',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
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
    });


    //]]>
</script>
