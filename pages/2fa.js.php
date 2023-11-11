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

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$superGlobal = new SuperGlobal();

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
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
$checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('mfa') === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $(document).on('click', '#button-duo-config-check', function() {
        toastr
            .info('<?php echo langHdl('loading_item'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

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
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $superGlobal->get('key', 'SESSION'); ?>"),
                key: '<?php echo $superGlobal->get('key', 'SESSION'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $superGlobal->get('key', 'SESSION'); ?>');

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
                        '<?php echo langHdl('duo-config-check-success'); ?>',
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
