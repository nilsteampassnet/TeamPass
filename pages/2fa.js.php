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
 * @file      2fa.js.php
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
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$superGlobal = new SuperGlobal();
$lang = new Language(); 

if ($session->get('key') === null) {
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
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('mfa') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $(document).on('click', '#button-duo-config-check', function() {
        toastr
            .info('<?php echo $lang->get('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Prepare data
        var data = {
            'duo_ikey': sanitizeString($('#duo_ikey').val()),
            'duo_skey': sanitizeString($('#duo_skey').val()),
            'duo_host': sanitizeString($('#duo_host').val())
        }
        console.log(data);

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'run_duo_config_check',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        data.message,
                        '', {
                            timeOut: 15000,
                            progressBar: true
                        }
                    );
                } else {
                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('duo-config-check-success'); ?>',
                        '', {
                            timeOut: 5000
                        }
                    );
                }
            }
        );
    });


    //]]>
</script>
