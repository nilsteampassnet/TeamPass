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
 * @file      tasks.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('tasks') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    var oTableProcesses,
        oTableProcessesDone,
        manuelTaskIsRunning = false;


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
        /*function (data, callback, settings) {
            data.search.column = $("select", "#table-items_filter").val();
            $.ajax({
                url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=tasks_in_progress',
                method: 'POST',
                data: data,
                success: function (encryptedResponse) {
                    var decryptedData = prepareExchangedData(encryptedResponse, 'decode', '<?php echo $session->get('key'); ?>');
                    callback(JSON.parse(decryptedData.data));
                }
            });
        },*/
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
                '<?php echo $lang->get('refreshed'); ?>',
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
                            return '<i class="fa-solid fa-play text-warning"></i><i class="fa-solid fa-trash pointer confirm ml-2 text-danger" data-id="' + $(data).data('process-id') + '" data-type="task-delete"></i>';
                        } else {
                            return '<i class="fars fa-hand-papper text-info"></i><i class="fa-solid fa-trash pointer confirm ml-2 text-danger" data-id="' + $(data).data('process-id') + '" data-type="task-delete"></i>';
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
        toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        if ($(this).data('type') === "add-new-job") {
            console.log('add new job')
            $.post(
                "sources/utilities.queries.php", {
                    type: "handle_crontab_job",
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    console.log(data)

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('alert_page_will_reload'); ?>',
                        '<?php echo $lang->get('done'); ?>', {
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
            toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');
            
            $.post(
                "sources/utilities.queries.php", {
                    type: "task_delete",
                    id: $(this).data('id'),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    console.log(data)

                    $("#task-delete-user-confirm").modal('hide');

                    // Rrefresh list of tasks in Teampass
                    oTableProcesses.ajax.reload();
                    
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
            
            // Store in DB   
            $.post(
                "sources/admin.queries.php", {
                    type: "save_option_change",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                            '', {
                                closeButton: true,
                                positionClass: 'toast-bottom-right'
                            }
                        );
                        return false;
                    }
                    console.log(data)
                    if (data.error === false) {
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('saved'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );

                        // Manage feedback to user
                        $('#'+field+'_parameter_value').val(frequency === null ? '' : frequency + ';' +value,);
                        param = value.split(';');
                        if (param.length === 1) {
                            txt = ' <?php echo $lang->get('at');?> ' + param[0];
                        } else {
                            txt = ' <?php echo $lang->get('day');?> ' + param[1] + ' <?php echo $lang->get('at');?> ' + param[0];
                        }
                        $('#'+field+'_parameter').val(frequency === null ? '<?php echo $lang->get('not_defined');?>' : (data.message + txt));
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
            '<i class="fa-regular fa-circle-check fa-lg text-warning mr-2"></i><?php echo $lang->get('your_attention_please'); ?>',
            '<?php echo $lang->get('please_confirm_task_to_be_run'); ?>: <strong>' + $('#'+$(this).data('task')+'_text').text() + '</strong>',
            '<?php echo $lang->get('perform'); ?>',
            '<?php echo $lang->get('close'); ?>',
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
            manuelTaskIsRunning = true;

            // Inform user
            $('<i class="fa-solid fa-circle-notch fa-spin ml-2 text-teal" id="'+launchedTask+'_spinner"></i>').insertAfter($(this));
            launchedButton.prop('disabled', true);

            toastr.remove();
            toastr.success(
                '<?php echo $lang->get('started'); ?>',
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
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    } catch (e) {
                        // error
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                            '', {
                                closeButton: true,
                                positionClass: 'toast-bottom-right'
                            }
                        );
                        manuelTaskIsRunning = false;
                        return false;
                    }
                    console.log(data);
                    if (data.error === false) {
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );

                        $('#'+launchedTask+'_badge').text(data.datetime);
                        $('#'+launchedTask+'_spinner').remove();
                        launchedButton.prop('disabled', false);
                        requestRunning = false;
                        manuelTaskIsRunning = false;
                        $('#warningModal').modal('hide');
                    } else {
                        toastr.remove();
                        toastr.error(
                            '<?php echo $lang->get('error'); ?>',
                            data.output, {
                                closeButton: true,
                                positionClass: 'toast-bottom-right'
                            }
                        );
                    }
                }
            );
        });        
    });

    // get last tasks execution
    function refreshTasksTime()
    {
        if (manuelTaskIsRunning === true ) return false;

        $('#go_refresh').removeClass('hidden');
        $.post(
            "sources/tasks.queries.php",
            {
                type: "load_last_tasks_execution",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }

                $('#tasks_log_table_size').text(data.logSize);
                
                if (data.enabled === true){
                    let tasks = JSON.parse(data.task);
                    for (let i = 0; i < tasks.length; i++) {
                        $('#'+tasks[i].task+'_badge').text(tasks[i].datetime);

                    }
                } else {
                    $('.badge').text('');
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
