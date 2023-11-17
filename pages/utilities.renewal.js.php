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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.logs') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
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
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user']['user_language']; ?>.txt'
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
            language: '<?php echo $superGlobal->get('user_language_code', 'SESSION'); ?>'
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
