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
 * @file      utilities.database.js.php
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

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || $superGlobal->get('key', 'SESSION') === null
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
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.database') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    var oTableLoggedIn,
        oTableConnections;


    // Prepare tooltips
    $('.infotip').tooltip();


    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        if (e.target.hash === '#in_edition') {

        } else if (e.target.hash === '#logged_in') {
            showLoggedIn();
        }
    })

    //Launch the datatables pluggin
    oTableConnections = $('#table-in_edition').DataTable({
        'paging': true,
        'searching': true,
        'sPaginationType': 'listbox',
        'order': [
            [2, 'asc']
        ],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=items_in_edition',
            /*data: function(d) {
                d.letter = _alphabetSearch
            }*/
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user']['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            toastr.remove();
            toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        },
        'drawCallback': function() {
            // Inform user
            toastr.remove();
            toastr.success(
                '<?php echo $lang->get('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );
        },
        'columnDefs': [{
            'width': '80px',
            'targets': 0,
            'render': function(data, type, row, meta) {
                return '<i class="far fa-trash-alt text-danger pointer action" data-id="' + $(data).data('id') + '" data-type="item-edited"></i>';
            }
        }],
    });

    /**
     * Undocumented function
     *
     * @return void
     */
    function showLoggedIn() {
        oTableLoggedIn = $('#table-logged_in').DataTable({
            'retrieve': true,
            'paging': true,
            'sPaginationType': 'listbox',
            'searching': true,
            'order': [
                [2, 'asc']
            ],
            'info': true,
            'processing': false,
            'serverSide': true,
            'responsive': true,
            'stateSave': true,
            'autoWidth': true,
            'ajax': {
                url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=users_logged_in',
                /*data: function(d) {
                    d.letter = _alphabetSearch
                }*/
            },
            'language': {
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user']['user_language']; ?>.txt'
            },
            'preDrawCallback': function() {
                toastr.remove();
                toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
            },
            'drawCallback': function() {
                // Inform user
                toastr.remove();
                toastr.success(
                    '<?php echo $lang->get('done'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );
            },
            'columnDefs': [{
                'width': '80px',
                'targets': 0,
                'render': function(data, type, row, meta) {
                    return '<i class="far fa-trash-alt text-danger pointer action" data-id="' + $(data).data('id') + '" data-type="disconnect-user"></i>';
                }
            }],
        });
    }

    $(document).on('click', '.action', function() {
        toastr.remove();
        toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if ($(this).data('type') === "item-edited") {
            $.post(
                "sources/items.queries.php", {
                    type: "free_item_for_edition",
                    id: $(this).data('id'),
                    key: "<?php echo $superGlobal->get('key', 'SESSION'); ?>"
                },
                function(data) {
                    oTableConnections.ajax.reload();

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        } else if ($(this).data('type') === "disconnect-user") {
            $.post(
                "sources/users.queries.php", {
                    type: "disconnect_user",
                    user_id: $(this).data('id'),
                    key: "<?php echo $superGlobal->get('key', 'SESSION'); ?>"
                },
                function(data) {
                    oTableLoggedIn.ajax.reload();

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        }
    });



    //]]>
</script>
