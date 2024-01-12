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
 * @file      utilities.renewal.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2024 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */



use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
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
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.logs') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

?>


<script type='text/javascript'>
    //<![CDATA[


    // Init
    var oTable;

    // Prepare tooltips
    $('.infotip').tooltip();

    oTable = $('#table-renewal').DataTable({
        'retrieve': true,
        'orderCellsTop': true,
        'fixedHeader': true,
        'paging': true,
        'sPaginationType': 'listbox',
        'searching': true,
        'order': [
            [1, 'asc']
        ],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/expired.datatables.php',
            data: function() {
                if ($('#renewal-date').datepicker("getDate") === '' || $('#renewal-date').datepicker("getDate") === null) {
                    return {};
                } else {
                    return {
                        "dateCriteria": $('#renewal-date').datepicker("getDate").valueOf()
                    }
                }
            }
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
        },
        'preDrawCallback': function() {
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        },
        'drawCallback': function() {
            // Inform user
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        },
        'columns': [{
                'width': '60px',
                className: 'dt-body-center'
            },
            {
                'width': '40%',
                className: 'dt-body-center'
            },
            {
                'width': '20%',
                className: 'dt-body-center'
            },
            {
                className: 'datatable.path'
            }
        ]
    });


    // Prepare datePicker
    $('#renewal-date').datepicker({
            format: '<?php echo str_replace(['Y', 'M'], ['yyyy', 'mm'], $SETTINGS['date_format']); ?>',
            todayHighlight: true,
            todayBtn: true,
            language: '<?php echo $session->get('user-language_code'); ?>'
        })
        .on('changeDate', function(e) {
            oTable.ajax.reload();
        });


    $('#renewal-date').addClear({
        symbolClass: "far fa-times-circle text-danger",
        onClear: function() {
            $('#renewal-date').datepicker('clearDates');
            oTable.ajax.reload();
        }
    });


    //]]>
</script>
