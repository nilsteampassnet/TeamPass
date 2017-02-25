<?php
/**
 *
 * @file          index.php
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

require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

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
<div class="ui-state-highlight ui-corner-all" style="padding:10px;">
    <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;">&nbsp;d</span>'.$LANG['offline_mode_warning'].'
</div>
<div style="margin:10px 0 10px 0;">
    <label for="offline_mode_selected_folders" class="form_label">'.$LANG['select_folders'].' :</label>
    <select id="offline_mode_selected_folders" multiple class="text ui-widget-content ui-corner-all" style="padding:10px;">';

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

<div style="margin:10px 0 10px 0;">
    <label for="pdf_password" class="">'.$LANG['admin_action_db_restore_key'].' :</label>
    <input type="password" id="offline_password" name="offline_password" />
    <div id="offline_pw_strength" style="margin:10px 0 0 130px;"></div>
    <input type="hidden" id="offline_pw_strength_value" />
    <input type="hidden" id="min_offline_pw_strength_value" value="'.$_SESSION['settings']['offline_key_level'].'" />
</div>

<div style="text-align:center;margin-top:8px; display:none;" id="offline_information"></div>

<input type="hidden" id="offmode_number" />
<input type="hidden" id="offmode_list" />';

?>
<script type="text/javascript">
    $(function() {
        $("select").multiselect();

        $("#offline_password").simplePassMeter({
            "requirements": {},
            "container": "#offline_pw_strength",
            "defaultText" : "<?php echo $LANG['index_pw_level_txt'];?>",
            "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "<?php echo $LANG['complex_level0'];?>"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "<?php echo $LANG['complex_level1'];?>"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "<?php echo $LANG['complex_level2'];?>"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "<?php echo $LANG['complex_level3'];?>"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "<?php echo $LANG['complex_level4'];?>"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "<?php echo $LANG['complex_level5'];?>"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "<?php echo $LANG['complex_level6'];?>"
                }
            ]
        });

        $("#offline_password").bind({
            "score.simplePassMeter" : function(jQEvent, score) {
                $("#offline_pw_strength_value").val(score);
            }
        });

    });

    /*
    * Export to Offline mode file - step 1
     */
    function generateOfflineFile()
    {
        $("#offline_information").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>').attr("class","").show();

        //Get list of selected folders
        var ids = "";
        $("#offline_mode_selected_folders :selected").each(function(i, selected) {
            if (ids == "") ids = $(selected).val();
            else ids = ids + ";" + $(selected).val();
        });

        if (ids == "") {
            $("#offline_information").show().html("<?php echo $LANG['error_no_selected_folder'];?>").attr("class","ui-state-error");
            setTimeout(function(){$("#offline_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        if ($("#offline_password").val() == "") {
            $("#offline_information").show().html("<?php echo $LANG['pdf_password_warning'];?>").attr("class","ui-state-error");
            setTimeout(function(){$("#offline_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        if ($("#offline_pw_strength_value").val() < $("#min_offline_pw_strength_value").val()) {
            $("#offline_information").addClass("ui-state-error ui-corner-all").show().html("<?php echo $LANG['error_complex_not_enought'];?>");
            setTimeout(function(){$("#offline_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        pdf_password = sanitizeString($("#offline_password").val());

        //Send query
        $.post(
            "sources/export.queries.php",
            {
                type    : "export_to_html_format",
                ids        : ids,
                pdf_password : sanitizeString($("#offline_password").val())
            },
            function(data) {
                if (data[0].loop != null && data[0].loop == "true") {
                    exportHTMLLoop(ids, data[0].file,  ids.split(';').length, 1, pdf_password, data[0].file_link);
                }
                $("#offline_information").html(data[0].text);
            },
            "json"
        );
    }

    /*
     * Loading Item details step 2
     */
    function exportHTMLLoop(idsList, file, number, cpt, pdf_password, file_link)
    {
        // prpare list of ids to treat during this run
        if (idsList != "") {
            $("#offline_information").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?> - ' + Math.round((parseInt(cpt)*100)/parseInt(number)) + "%");

            tab = idsList.split(';');
            idTree = tab[0];
            tab = tab.slice(1, tab.length);
            idsList = tab.join(';');
            cpt = parseInt(cpt) + 1;

            $.post(
                "sources/export.queries.php",
                {
                    type 	: "export_to_html_format_loop",
                    idsList	: idsList,
                    idTree 	: idTree,
                    file    : file,
                    cpt     : cpt,
                    number  : number,
                    pdf_password : pdf_password,
                    file_link : file_link
                },
                function(data) {
                    // relaunch for next run
                    exportHTMLLoop (
                        data[0].idsList,
                        data[0].file,
                        number,
                        cpt,
                        pdf_password,
                        data[0].file_link
                    );
                },
                "json"
            );
        } else {
            $.post(
                "sources/export.queries.php",
                {
                    type 	: "export_to_html_format_finalize",
                    file    : file,
                    file_link : file_link
                },
                function(data) {
                    $("#offline_information").html('<i class="fa fa-download"></i>&nbsp;'+data[0].text);
                },
                "json"
            );
        }
    };
</script>