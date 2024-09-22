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
 * @file      utilities.database.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('utilities.database') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
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
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
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
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $session->get('user-language'); ?>.txt'
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
                    key: "<?php echo $session->get('key'); ?>"
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
                    key: "<?php echo $session->get('key'); ?>"
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
