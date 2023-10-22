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
                'width': '120px',
                'targets': 0,
                'render': function(data, type, row, meta) {
                    if ($(data).data('type') === 'create_user_keys') {
                        if (parseInt($(data).data('done')) === 1) {
                            return '<i class="fa-solid fa-play text-warning"></i><i class="fa-solid fa-eye pointer action ml-2" data-id="' + $(data).data('process-id') + '" data-type="task-detail"></i><i class="fa-solid fa-trash pointer confirm ml-2 text-danger" data-id="' + $(data).data('process-id') + '" data-type="task-delete"></i>';
                        } else {
                            return '<i class="fars fa-hand-papper text-info"></i><i class="fa-solid fa-eye pointer action ml-2" data-id="' + $(data).data('process-id') + '" data-type="task-detail"></i><i class="fa-solid fa-trash pointer confirm ml-2 text-danger" data-id="' + $(data).data('process-id') + '" data-type="task-delete"></i>';
                        }
                    } else if ($(data).data('type') === 'item_copy') {
                        return '<i class="fa-solid fa-trash pointer confirm ml-2 text-danger" data-id="' + $(data).data('process-id') + '" data-type="task-delete"></i>';
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

    // confirm task deletion
    $(document).on('click', '.confirm', function() {
        if ($(this).data('type') === "task-delete") {
            // show confirmation dialog
            $('#modal-btn-delete').data('id', $(this).data('id'));
            $("#task-delete-user-confirm").modal('show');
        }
    });

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
        } else if ($(this).data('type') === "add-new-job") {
            console.log('add new job')
            $.post(
                "sources/utilities.queries.php", {
                    type: "handle_crontab_job",
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    console.log(data)

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('alert_page_will_reload'); ?>',
                        '<?php echo langHdl('done'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );

                    // Delay page submit
                    $(this).delay(5000).queue(function() {
                        document.location.reload(true);
                        $(this).dequeue();
                    });
                }
            );
        }
    });

    function fetchTaskData(){
        oTableProcesses.ajax.reload();
        setTimeout(fetchTaskData, 20000);
    }


    $(document).ready(function(){
        // Handle tab selection from url
        let url = location.href.replace(/\/$/, "");
        if (location.hash) {
            const hash = url.split("#");
            $('#tasksSettingsPage a[href="#'+hash[1]+'"]').tab("show");
        }

        // Fetch data
        setTimeout(fetchTaskData,20000);

        // show value on slider
        $('.form-control-range').on("input", function() {
            $('#'+$(this).attr('id')+'_text').val($(this).val());
        });

        // Handle delete task
        $("#modal-btn-delete").on("click", function() {
            toastr.remove();
            toastr.info('<?php echo langHdl('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
            
            $.post(
                "sources/utilities.queries.php", {
                    type: "task_delete",
                    id: $(this).data('id'),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    console.log(data)

                    $("#task-delete-user-confirm").modal('hide');

                    // Rrefresh list of tasks in Teampass
                    oTableProcesses.ajax.reload();
                    
                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                }
            );
        });

        $("#modal-btn-cancel").on("click", function(){
            $("#task-delete-user-confirm").modal('hide');
        });

        // Handle define task - Click CANCEL button
        $("#modal-btn-task-cancel").on("click", function(){
            $("#task-define-modal").modal('hide');
        });

        // Handle define task - Click RESET button
        $("#modal-btn-task-reset").on("click", function(){
            $('#task-define-modal-parameter-hourly-value, #task-define-modal-parameter-daily-value, #task-define-modal-parameter-monthly-value, #task-define-modal-frequency').val('');
        });

        // Handle define task - Click SAVE button
        $('#modal-btn-task-save').on("click", function(){
            var field = $('#task-define-modal-type').val(),
                frequency = $('#task-define-modal-frequency').val(),
                value = $('#task-define-modal-frequency').val() === 'hourly' ? $('#task-define-modal-parameter-hourly-value').val() : 
                    ($('#task-define-modal-frequency').val() === 'monthly' ? $('#task-define-modal-parameter-daily-value').val() + ';' + $('#task-define-modal-parameter-monthly-value').val() :
                    ($('#task-define-modal-parameter-daily-value').val()));

            requestRunning = true;

            var data = {
                "field": field,
                "value": frequency === null ? '' : frequency + ';' +value,
                "translate": frequency,
            }
            console.log(data);
            
            // Store in DB   
            $.post(
                "sources/admin.queries.php", {
                    type: "save_option_change",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                            '', {
                                closeButton: true,
                                positionClass: 'toastr-top-right'
                            }
                        );
                        return false;
                    }
                    console.log(data)
                    if (data.error === false) {
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('saved'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );

                        // Manage feedback to user
                        $('#'+field+'_parameter_value').val(frequency === null ? '' : frequency + ';' +value,);
                        param = value.split(';');
                        if (param.length === 1) {
                            txt = ' <?php echo langHdl('at');?> ' + param[0];
                        } else {
                            txt = ' <?php echo langHdl('day');?> ' + param[1] + ' <?php echo langHdl('at');?> ' + param[0];
                        }
                        $('#'+field+'_parameter').val(frequency === null ? '<?php echo langHdl('not_defined');?>' : (data.message + txt));
                        $("#task-define-modal").modal('hide');
                        $('#task-define-modal-type, #task-define-modal-parameter-hourly-value, #task-define-modal-parameter-daily-value, #task-define-modal-frequency').val('');
                    }
                    requestRunning = false;
                }
            );
        });
    });

    // MANAGE TASK DEFINITION
    $(document).on('click', '.task-define', function() {
        // Open modal
        let task = $(this).data('task'),
            definition = $('#'+task+'_parameter_value').val().split(';');
        $('#task-define-modal-frequency option[value="'+definition[0]+'"]').prop('selected', true);
        console.log($('#'+task+'_parameter_value').val()+" -- "+definition[0]+";"+definition[1]+";"+definition[2])
        if (definition[0] === "hourly") {
            $('#task-define-modal-parameter-hourly').removeClass('hidden');
            $('#task-define-modal-parameter-daily, #task-define-modal-parameter-monthly').addClass('hidden');
            $('#task-define-modal-parameter-hourly-value').val(definition[1]);
        } else if (definition[0] === "daily") {
            $('#task-define-modal-parameter-daily').removeClass('hidden');
            $('#task-define-modal-parameter-hourly, #task-define-modal-parameter-monthly').addClass('hidden');
            $('#task-define-modal-parameter-daily-value').val(definition[1]);
        } else if (definition[0] === "monthly") {
            $('#task-define-modal-parameter-monthly, #task-define-modal-parameter-daily').removeClass('hidden');
            $('#task-define-modal-parameter-hourly').addClass('hidden');
            $('#task-define-modal-parameter-monthly-value').val(definition[2]);
            $('#task-define-modal-parameter-daily-value').val(definition[1]);
        } else {
            $('#task-define-modal-parameter-monthly, #task-define-modal-parameter-hourly').addClass('hidden');
            $('#task-define-modal-parameter-daily').removeClass('hidden');
            $('#task-define-modal-parameter-daily-value').val(definition[1]);
        }
        $('#task-define-modal-type').val(task);
        $("#task-define-modal").modal('show');
    });

    $(document).on('change', '#task-define-modal-frequency', function() {
        if ($(this).val() === "hourly") {
            $('#task-define-modal-parameter-hourly').removeClass('hidden');
            $('#task-define-modal-parameter-daily, #task-define-modal-parameter-monthly').addClass('hidden');
        } else if ($(this).val() === "monthly") {
            $('#task-define-modal-parameter-monthly, #task-define-modal-parameter-daily').removeClass('hidden');
            $('#task-define-modal-parameter-hourly').addClass('hidden');
        } else if ($(this).val() === "daily" || $(this).val() !== null) {
            $('#task-define-modal-parameter-daily').removeClass('hidden');
            $('#task-define-modal-parameter-hourly, #task-define-modal-parameter-monthly').addClass('hidden');
        }
    });
    
    $(document).on('click', '.task-perform', function(e) {
        e.stopPropagation();
        e.preventDefault();
        let taskButton = $(this);

        // Prepare modal
        showModalDialogBox(
            '#warningModal',
            '<i class="fa-regular fa-circle-check fa-lg text-warning mr-2"></i><?php echo langHdl('your_attention_please'); ?>',
            '<?php echo langHdl('please_confirm_task_to_be_run'); ?>: <strong>' + $('#'+$(this).data('task')+'_text').text() + '</strong>',
            '<?php echo langHdl('perform'); ?>',
            '<?php echo langHdl('close'); ?>',
            false,
            false,
            false
        );

        // Actions on modal buttons
        $(document).on('click', '#warningModalButtonAction', function(e) {
            e.stopPropagation();
            e.preventDefault();
            let launchedTask = $(taskButton).data('task'),
                launchedButton = $(taskButton);

            // Inform user
            $('<i class="fa-solid fa-circle-notch fa-spin ml-2 text-teal" id="'+launchedTask+'_spinner"></i>').insertAfter($(this));
            launchedButton.prop('disabled', true);

            toastr.remove();
            toastr.success(
                '<?php echo langHdl('started'); ?>',
                '', {
                    timeOut: 2000,
                    progressBar: true
                }
            );
            
            // Store in DB   
            $.post(
                "sources/tasks.queries.php",
                {
                    type: "perform_task",
                    task: launchedTask,
                    key: "<?php echo $_SESSION['key']; ?>"
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                            '', {
                                closeButton: true,
                                positionClass: 'toastr-top-right'
                            }
                        );
                        return false;
                    }
                    console.log(data);
                    if (data.error === false) {
                        toastr.remove();
                        toastr.success(
                            '<?php echo langHdl('done'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );
                    }
                    $('#'+launchedTask+'_badge').text(data.datetime);
                    $('#'+launchedTask+'_spinner').remove();
                    launchedButton.prop('disabled', false);
                    requestRunning = false;
                    $('#warningModal').modal('hide');
                }
            );
        });        
    });

    // get last tasks execution
    function refreshTasksTime()
    {
        $('#go_refresh').removeClass('hidden');
        $.post(
            "sources/tasks.queries.php",
            {
                type: "load_last_tasks_execution",
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toastr-top-right'
                        }
                    );
                    return false;
                }
                
                let tasks = JSON.parse(data.task);
                for (let i = 0; i < tasks.length; i++) {
                    $('#'+tasks[i].task+'_badge').text(tasks[i].datetime);

                }
                $('#go_refresh').addClass('hidden');
            }
        );
    }

    // On page load
    $(function() {
        refreshTasksTime();
        setInterval(refreshTasksTime, 10000);
    });

    //]]>
</script>
