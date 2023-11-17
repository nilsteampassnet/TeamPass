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
 * @file      emails.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$superGlobal = new SuperGlobal();
$lang = new Language(); 

if ($superGlobal->get('key', 'SESSION') === null) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('emails') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $(document).on('click', '.button', function() {
        var action = $(this).data('action');

        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if (action === 'send-test-email') {
            $.post(
                'sources/admin.queries.php', {
                    type: 'admin_email_test_configuration',
                    key: '<?php echo $superGlobal->get('key', 'SESSION'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $superGlobal->get('key', 'SESSION'); ?>');
                    console.log(data);
                    if (data.error === true) {
                        // ERROR
                        toastr.remove();
                        toastr.warning(
                            '<?php echo $lang->get('none_selected_text'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
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
        } else if (action === 'send-waiting-emails') {
            $('#unsent-emails')
                .append('<span id="unsent-emails-progress" class="ml-3"></span>');
            sendEmailsBacklog();
        }
    });


    function sendEmailsBacklog(counter = "") {
        $('#unsent-emails-progress')
            .html('<i class="fas fa-cog fa-spin ml-2"></i>' +
                '<?php echo $lang->get('remaining_emails_to_send'); ?> ' + counter);
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_email_send_backlog',
                key: '<?php echo $superGlobal->get('key', 'SESSION'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $superGlobal->get('key', 'SESSION'); ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('none_selected_text'); ?>',
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
                                '<?php echo $lang->get('done'); ?>');
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
            }
        );
    }

    //]]>
</script>
