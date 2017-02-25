<?php
/**
 *
 * @file          items.export.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('./sources/SecureHandler.php');
session_start();
if (
    (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
        !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
        !isset($_SESSION['key']) || empty($_SESSION['key'])) &&
    $_GET['key'] != $_SESSION['key'])
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

// connect to the server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

echo '
<div class="ui-state-highlight ui-corner-all" style="margin:10px;padding:10px;">
    <i class="fa fa-bullhorn"></i>&nbsp;'.$LANG['print_out_warning'].'
</div>

<div>
    <label for="export_selected_folders" class="form_label">'.$LANG['select_folders'].' :</label>
    <select id="export_selected_folders" multiple class="text ui-widget-content ui-corner-all" style="padding:10px;">
    ';

//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$folders = $tree->getDescendants();

// show list of all folders
foreach ($folders as $f) {
    // Be sure that user can only see folders he/she is allowed to
    if (!in_array($f->id, $_SESSION['forbiden_pfs'])) {
        $displayThisNode = false;
        // Check if any allowed folder is part of the descendants of this node
        $nodeDescendants = $tree->getDescendants($f->id, true, false, true);
        foreach ($nodeDescendants as $node) {
            if (in_array($node, $_SESSION['groupes_visibles'])) {
                $displayThisNode = true;
                break;
            }
        }

        if ($displayThisNode == true) {
            if ($f->title == $_SESSION['user_id'] && $f->nlevel == 1) {
                $f->title = $_SESSION['login'];
            }
            echo "<option value='".$f->id."'>".$f->title."</option>";
        }
    }
}

echo '
    </select>
</div>

<div style="margin-top:5px;">
    <label class="form_label">'.$LANG['select_file_format'].':</label>
    <div class="export_radio_buttonset">
        <input type="radio" id="export_format_pdf" name="export_format" value="pdf" /><label for="export_format_pdf">'.$LANG['pdf'].'</label>
        <input type="radio" id="export_format_csv" name="export_format" value="csv" /><label for="export_format_csv">'.$LANG['csv'].'</label>
    </div>
</div>

<div style="margin-top:5px; display:none;" id="div_export_pdf_password">
    <label for="export_pdf_password" class="form_label">'.$LANG['file_protection_password'].':</label>
    <input type="password" id="export_pdf_password" />
</div>

<div style="text-align:center;margin-top:10px; display:none;" id="export_information"></div>';
?>
<script type="text/javascript">
    $(function() {
        $("select").multiselect();
        //$(".radio_buttonset").buttonset();

        $(".export_radio_buttonset").click(function(){
            if ($("input[name='export_format']:checked").val() == "pdf"){
                $("#div_export_pdf_password").show();
            } else {
                $("#export_pdf_password").val("");
                $("#div_export_pdf_password").hide();
            }
        })
    });

    function pollExport(export_format, remainingIds, currentID, nb)
    {
        $.ajax({
            url: "sources/export.queries.php",
            type : 'POST',
            dataType : "json",
            data : {
                type    : export_format,
                id     : currentID,
                ids     : remainingIds
            },
            complete : function(data, statut){
                var aIds = remainingIds.split(",");
                var currentID = aIds[0];
                aIds.shift();
                var nb2 = aIds.length;
                aIds = aIds.toString();
                $("#export_progress").html(Math.floor(((nb-nb2) / nb) * 100)+"%");
				//console.log(remainingIds+" ; "+currentID+" ; "+aIds+" ; "+nb+" ; "+nb2);
                if (currentID != "") {
                    pollExport(export_format, aIds, currentID, nb);
                } else {
                    //Send query
                    $.post(
                        "sources/export.queries.php",
                        {
                            type    : "finalize_export_pdf",
                            pdf_password : $("#export_pdf_password").val()
                        },
                        function(data) {
                            $("#export_information").html('<i class="fa fa-download"></i>&nbsp;'+data[0].text);
                        },
                        "json"
                    );
                }
            }
        })
    };

    function exportItemsToFile()
    {
        $("#export_information").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>&nbsp;...&nbsp;<span id="export_progress">0%</span>').attr("class","").show();

        //Get list of selected folders
        var ids = "";
        $("#export_selected_folders :selected").each(function(i, selected) {
            if (ids == "") ids = $(selected).val();
            else ids = ids + ";" + $(selected).val();
        });

        if (ids == "") {
            $("#export_information").show().html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['error_no_selected_folder'];?>").attr("class","ui-state-error");
            setTimeout(function(){$("#export_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        if ($("input[name='export_format']:checked").length == 0) {
            $("#export_information").show().html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['error_export_format_not_selected'];?>").attr("class","ui-state-error");
            setTimeout(function(){$("#export_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        // Get PDF encryption password and make sure it is set
        if (($("#export_pdf_password").val() == "") && ($("input[name='export_format']:checked").val() == "pdf")) {
            $("#export_information").show().html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['pdf_password_warning'];?>").attr("class","ui-state-error");
            setTimeout(function(){$("#export_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        // export format?
        var export_format = "";
        if ($("input[name='export_format']:checked").val() == "pdf") export_format = "export_to_pdf_format";
        else if ($("input[name='export_format']:checked").val() == "csv") export_format = "export_to_csv_format";

        if (export_format == "export_to_pdf_format") {

            $.post(
                "sources/export.queries.php",
                {
                    type    : "initialize_export_table"
                },
                function(data) {
                    // launch export by building content of export table
                    var aIds = ids.split(";");
                    var currentID = aIds[0];
                    aIds.shift();
                    var nb = aIds.length+1;
                    aIds = aIds.toString();
                    pollExport(export_format, aIds, currentID, nb);
                }
            );


        } else {
            //Send query
            $.post(
                "sources/export.queries.php",
                {
                    type    : export_format,
                    ids        : ids,
                    pdf_password : $("#pdf_password").val()
                },
                function(data) {
                    $("#export_information").html('<i class="fa fa-download"></i>&nbsp;'+data[0].text);
                },
                "json"
            );
        }
    }
</script>