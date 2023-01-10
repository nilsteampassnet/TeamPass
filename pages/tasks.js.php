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
 * @version   3.0.0.22
 * @file      tasks.js.php
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'tasks', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    var oTableProcesses,
        oTableProcessesDone;


    // Prepare tooltips
    $('.infotip').tooltip();


    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        if (e.target.hash === '#tasks_in_progress') {

        } else if (e.target.hash === '#finished') {
            showProcessesDone();
        }
    })

    //Launch the datatables pluggin
    oTableProcesses = $('#table-tasks_in_progress').DataTable({
        'paging': true,
        'searching': true,
        'sPaginationType': 'listbox',
        'order': [
            [1, 'asc']
        ],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': false,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=tasks_in_progress',
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user']['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            toastr.remove();
            toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
        },
        'drawCallback': function() {
            // Inform user
            toastr.remove();
            toastr.success(
                '<?php echo langHdl('refreshed'); ?>',
                '', {
                    timeOut: 1000
                }
            );
        },
        'columnDefs': [
            {
                'width': '100px',
                'targets': 0,
                'render': function(data, type, row, meta) {
                    if ($(data).data('type') === 'create_user_keys') {
                        if ($(data).data('done') === 1) {
                            return '<i class="fas fa-play text-warning"></i><i class="fas fa-eye pointer action ml-2" data-id="' + $(data).data('process-id') + '" data-type="task-detail"></i>';
                        } else {
                            return '<i class="fars fa-hand-papper text-info"></i><i class="fas fa-eye pointer action ml-2" data-id="' + $(data).data('process-id') + '" data-type="task-detail"></i>';
                        }
                    } else {
                        return '';
                    }
                }
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            },
            {
                className: 'dt-body-left'
            }
        ],
    });

    /**
     * Undocumented function
     *
     * @return void
     */
    function showProcessesDone() {
        oTableProcessesDone = $('#table-tasks_finished').DataTable({
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
                url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=tasks_finished',
            },
            'language': {
                'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user']['user_language']; ?>.txt'
            },
            'preDrawCallback': function() {
                toastr.remove();
                toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
            },
            'drawCallback': function() {
                // Inform user
                toastr.remove();
                toastr.success(
                    '<?php echo langHdl('done'); ?>',
                    '', {
                        timeOut: 1000
                    }
                );
            },
            'columnDefs': [{
                'width': '80px',
                'targets': 0,
                'render': function(data, type, row, meta) {
                    return '<i class="fas fa-square text-success"></i>';
                }
            }],
        });
    }

    $(document).on('click', '.action', function() {
        toastr.remove();
        toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if ($(this).data('type') === "task-detail") {
            $.post(
                "sources/utilities.queries.php", {
                    type: "show_process_detail",
                    id: $(this).data('id'),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    console.log(data)


                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // Prepare display
                    var html = '<div class="row">',
                        type = '',
                        icon = '',
                        dates = '';
                    $.each(data.tasks, function(i, task) {
                        if (task.is_in_progress === -1) {
                            type = 'success';
                            icon = 'thumbs-up';
                            dates = '<span class="badge badge-primary ml-2">' + task.created_at + '</span>' +
                                    '<span class="badge badge-secondary">' + task.finished_at + '</span>';
                        } else if (task.is_in_progress === 1) {
                            type = 'warning';
                            icon = 'play';
                            dates = '<span class="badge badge-primary ml-2">' + task.created_at + '</span>' +
                                    '<span class="badge badge-info">' + task.updated_at + '</span>';
                        } else if (task.is_in_progress === 0) {
                            type = 'info';
                            icon = 'pause';
                            dates = '<span class="badge badge-primary ml-2">' + task.created_at + '</span>';
                        }
                        html += '<div class="col-md-3 col-sm-6 col-12">' +
                            '<div class="alert alert-' + type + '">' +
                                '<h5><i class="fas fa-' + icon + ' mr-2"></i>' +task.step+ '</h5>' +
                                '<div>' + dates + '</div>' +
                                '<div class="progress mt-2">' +
                                    '<div class="progress-bar" style="width: ' + task.progress +'"></div>' +
                                '</div>' +
                            '</div>' +
                            '</div>';
                    });
                    html += '</div>';

                    // display tasks
                    showModalDialogBox(
                        '#warningModal',
                        '<i class="fas fa-tasks fa-lg mr-2"></i><?php echo langHdl('process_details'); ?>',
                        '<div class="form-group">'+
                        html+
                        '</div>',
                        '',
                        '<?php echo langHdl('close'); ?>'
                    );

                }
            );
        }
    });

    function fetchTaskData(){
        oTableProcesses.ajax.reload();
        setTimeout(fetchTaskData, 20000);
    }

    $(document).ready(function(){
        setTimeout(fetchTaskData,20000);
    });

    //]]>
</script>
