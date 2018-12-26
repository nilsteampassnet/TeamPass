<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'utilities.logs', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
//<![CDATA[

// Init
var oTableItems,
    oTableConnections,
    oTableItems,
    oTableFailed,
    oTableCopy,
    oTableAdmin,
    oTableErrors;


// Prepare tooltips
$('.infotip').tooltip();

// What type of form? Edit or new user
browserSession(
    'init',
    'teampassApplication',
    {
        logData : '',
    }
);
store.update(
    'teampassApplication',
    function (teampassApplication)
    {
        teampassApplication.logData = 'connections';
    }
);


$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    if (e.target.hash === '#connections') {
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.logData = 'connections';
            }
        );
    } else if (e.target.hash === '#failed') {
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.logData = 'failed';
            }
        );
        showFailed();
    } else if (e.target.hash === '#errors') {
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.logData = 'errors';
            }
        );
        showErrors();
    } else if (e.target.hash === '#copy') {
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.logData = 'copy';
            }
        );
        showCopy();
    } else if (e.target.hash === '#admin') {
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.logData = 'admin';
            }
        );
        showAdmin();
    } else if (e.target.hash === '#items') {
        store.update(
            'teampassApplication',
            function (teampassApplication)
            {
                teampassApplication.logData = 'items';
            }
        );
        showItems();
    }
});
    
//Launch the datatables pluggin
oTableConnections = $('#table-connections').dataTable({
    'paging': true,
    'searching': true,
        'sPaginationType': 'listbox',
    'order': [[1, 'asc']],
    'info': true,
    'processing': false,
    'serverSide': true,
    'responsive': true,
    'stateSave': true,
    'autoWidth': true,
    'ajax': {
        url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=connections',
        /*data: function(d) {
            d.letter = _alphabetSearch
        }*/
    },
    'language': {
        'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
    },
    'preDrawCallback': function() {
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();
    },
    'drawCallback': function() {
        // Inform user
        alertify
            .success('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();
    },
});

/**
 * Undocumented function
 *
 * @return void
 */
function showFailed()
{
    oTableFailed = $('#table-failed').dataTable({
        'retrieve': true,
        'paging': true,
        'sPaginationType': 'listbox',
        'searching': true,
        'order': [[1, 'asc']],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=failed_auth',
            /*data: function(d) {
                d.letter = _alphabetSearch
            }*/
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
        },
        'drawCallback': function() {
            // Inform user
            alertify
                .success('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();
        },
    });
}

/**
 * Undocumented function
 *
 * @return void
 */
function showErrors()
{
    oTableErrors = $('#table-errors').dataTable({
        'retrieve': true,
        'paging': true,
        'sPaginationType': 'listbox',
        'searching': true,
        'order': [[1, 'asc']],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=errors',
            /*data: function(d) {
                d.letter = _alphabetSearch
            }*/
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
        },
        'drawCallback': function() {
            // Inform user
            alertify
                .success('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();
        },
    });
}

/**
 * Undocumented function
 *
 * @return void
 */
function showCopy()
{
    oTableCopy = $('#table-copy').dataTable({
        'retrieve': true,
        'paging': true,
        'sPaginationType': 'listbox',
        'searching': true,
        'order': [[1, 'asc']],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=copy',
            /*data: function(d) {
                d.letter = _alphabetSearch
            }*/
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
        },
        'drawCallback': function() {
            // Inform user
            alertify
                .success('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();
        },
    });
}

/**
 * Undocumented function
 *
 * @return void
 */
function showAdmin()
{
    oTableAdmin = $('#table-admin').dataTable({
        'retrieve': true,
        'paging': true,
        'sPaginationType': 'listbox',
        'searching': true,
        'order': [[1, 'asc']],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=admin',
            /*data: function(d) {
                d.letter = _alphabetSearch
            }*/
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
        },
        'drawCallback': function() {
            // Inform user
            alertify
                .success('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();
        },
    });
}

/**
 * Undocumented function
 *
 * @return void
 */
function showItems()
{
    /*$('#table-items thead tr').clone(true).appendTo( '#table-items thead' );
    $('#table-items thead tr:eq(1) th').each( function (i) {
        var title = $(this).text();
        $(this).html( '<input type="text" placeholder="Search '+title+'" style="width:100%;">' );
 
        $( 'input', this ).on( 'keyup change', function () {
            if ( table.column(i).search() !== this.value ) {
                table
                    .column(i)
                    .search( this.value )
                    .draw();
            }
        } );
    } );*/

    var columns = [
        {
            title: 'Date',
            column: 'l.date'
        }, 
        {
            title: 'ID',
            column: 'i.id'
        },
        {
            title: 'Label',
            column: 'i.label'
        },
        {
            title: 'Folder',
            column: 't.title'
        },
        {
            title: 'User',
            column: 'u.login'
        },
        {
            title: 'Action',
            column: 'l.action'
        }
    ];
    $("#table-items").one("preInit.dt", function () {
        $sel = $('<select class="form-control" id="items-search-column"></select>');
        $sel.html("<option value='all'>All Columns</option>");
        $.each(columns, function (i, opt) {
            $sel.append("<option value='" + opt.column + "'>" + opt.title + "</option>");
        });
        $("#table-items_filter label").append($sel);
    });

    oTableItems = $('#table-items').DataTable({
        'retrieve': true,
        'orderCellsTop': true,
        'fixedHeader': true,
        'paging': true,
        'sPaginationType': 'listbox',
        'searching': true,
        'order': [[1, 'asc']],
        'info': true,
        'processing': false,
        'serverSide': true,
        'responsive': true,
        'stateSave': true,
        'autoWidth': true,
        'ajax': {
            url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/logs.datatables.php?action=items',
            data: function (filter) {
                var val = $("select", "#table-items_filter").val();
                filter.search.column = val;
                return filter;
            }
        },
        'language': {
            'url': '<?php echo $SETTINGS['cpassman_url']; ?>/includes/language/datatables.<?php echo $_SESSION['user_language']; ?>.txt'
        },
        'preDrawCallback': function() {
            alertify
                .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
                .dismissOthers();
        },
        'drawCallback': function() {
            // Inform user
            alertify
                .success('<?php echo langHdl('done'); ?>', 1)
                .dismissOthers();
        },
    });

    $('#myTabContent').on('change', '#items-search-column', function() {
        oTableItems.ajax.reload();
    });

}

//iCheck for checkbox and radio inputs
$('.card-footer input[type="checkbox"]').iCheck({
    checkboxClass: 'icheckbox_flat-blue'
});

// Build date range picker
$('#purge-date-range')
    .daterangepicker({
        locale: {
            format: '<?php echo $SETTINGS['date_format']; ?>',
            applyLabel: '<?php echo langHdl('apply'); ?>',
            cancelLabel: '<?php echo langHdl('cancel'); ?>',
            fromLabel: '<?php echo langHdl('from'); ?>',
            toLabel: '<?php echo langHdl('to'); ?>',
        } 
    })
    .bind('keypress', function(e) {
        e.preventDefault(); 
    });

// Clear date range
$('#clear-purge-date').click(function() {
    $('#purge-date-range').val('');
    $('.group-confirm-purge').addClass('hidden');
    $('#checkbox-purge-confirm').iCheck('uncheck');
})

// Show confirm purge
$('.card-footer').on('change', '#purge-date-range', function() {
    if ($(this).val() !== '') {
        $('#checkbox-purge-confirm').iCheck('uncheck');
        $('.group-confirm-purge').removeClass('hidden');
    } else {
        $('.group-confirm-purge').addClass('hidden');
    }
});

// Now purge
$('#button-perform-purge').click(function() {
    if ($('#checkbox-purge-confirm').prop('checked') === true) {
        // inform user
        alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

        // Prepare data
        var dateRange = $('#purge-date-range').val().split('-');
        var data = {
            'dataType'  : store.get('teampassApplication').logData,
            'dateStart'  : dateRange[0].trim(),
            'dateEnd'   : dateRange[1].trim(),
        }
        
        // Send query
        $.post(
            "sources/utilities.queries.php",
            {
                type    : "purge_logs",
                data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                if (data.error !== false) {
                    // Show error
                    alertify
                        .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                        .dismissOthers();
                } else {
                    // Reload table
                    if (store.get('teampassApplication').logData === 'errors') {
                        oTableErrors.ajax.reload();
                    } else if (store.get('teampassApplication').logData === 'admin') {
                        oTableAdmin.ajax.reload();
                    } else if (store.get('teampassApplication').logData === 'connections') {
                        oTableConnections.ajax.reload();
                    } else if (store.get('teampassApplication').logData === 'Ffailed') {
                        oTableFailed.ajax.reload();
                    } else if (store.get('teampassApplication').logData === 'items') {
                        oTableItems.ajax.reload();
                    } else if (store.get('teampassApplication').logData === 'copy') {
                        oTableCopy.ajax.reload();
                    }
                }
            }
        );
    } else {
        alertify
            .message('<?php echo langHdl('please_confirm_by_clicking_checkbox'); ?>', 6)
            .dismissOthers();
    }
});

//]]>
</script>
