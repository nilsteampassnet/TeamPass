<?php
/**
 *
 * @file          items.import.php
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
//load main functions needed
require_once 'sources/main.functions.php';
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
<input type="hidden" id="folder_id_selected" value="', isset($_GET["folder_id"]) ? filter_var($_GET["folder_id"], FILTER_SANITIZE_NUMBER_INT) : '', '" />
<input type="hidden" id="import_user_token" value="" />
<div id="import_tabs">
    <ul>
        <li><a href="#tabs-1">CSV</a></li>
        <li><a href="#tabs-2">Keepass</a></li>
    </ul>
    <!-- TAB1 -->
    <div id="tabs-1">
        <!-- show some info -->
        <div class="ui-state-highlight ui-corner-all" style="padding:10px;" id="csv_import_info">
            <table border="0">
                <tr>
                <td valign="center"><span class="fa fa-info-circle fa-2x"></span>&nbsp;</td>
                <td>'.$LANG['csv_import_information'].'</td>
                </tr>
            </table>
        </div>
        <!-- show input file -->
        <div id="upload_container_csv">
            <div id="filelist_csv"></div><br />
            <a id="pickfiles_csv" href="#">'.$LANG['csv_import_button_text'].'</a>
        </div>

        <div style="display:none;margin-top:10px;" id="div_import_csv_selection">
            <div style="">'.$LANG['csv_import_options'].':</div>
            <div style="margin-left:20px;">
            <input type="checkbox" id="import_csv_anyone_can_modify" /><label for="import_csv_anyone_can_modify">'.$LANG['import_csv_anyone_can_modify_txt'].'</label><br />
            <input type="checkbox" id="import_csv_anyone_can_modify_in_role" /><label for="import_csv_anyone_can_modify_in_role">'.$LANG['import_csv_anyone_can_modify_in_role_txt'].'</label>
            </div>
            <div style="margin-top:10px;">'.$LANG['csv_import_items_selection'].':</div>
            <div id="import_selection" style="margin:10px 0 0 10px;"></div>
        </div>
    </div>
    <!-- end tab -->

    <!-- TAB2 -->
    <div id="tabs-2">
        <!-- Prepare a list of all folders that the user can choose -->
        <div style="margin-top:10px;" id="keypass_import_options">
            <label><b>'.$LANG['import_keepass_to_folder'].'</b></label>&nbsp;
            <select id="import_keepass_items_to">
                <option value="0">'.$LANG['root'].'</option>';
//Load Tree
$tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
$folders = $tree->getDescendants();
$prevLevel = 0;

// show list of all folders
foreach ($folders as $t) {
    if (in_array($t->id, $_SESSION['groupes_visibles'])) {
        if (is_numeric($t->title)) {
            $user = DB::queryfirstrow("SELECT login FROM ".prefix_table("users")." WHERE id = %i", $t->title);
            $t->title = $user['login'];
            $t->id = $t->id."-perso";
        }
        $ident="&nbsp;&nbsp;";
        for ($x=1; $x<$t->nlevel; $x++) {
            $ident .= "&nbsp;&nbsp;";
        }
        if (isset($_GET['folder_id']) && filter_var($_GET['folder_id'], FILTER_SANITIZE_NUMBER_INT) == $t->id) {
            $selected = " selected";
        }else {
            $selected = "";
        }
        if ($prevLevel < $t->nlevel) {
            echo '<option value="'.$t->id.'"'.$selected.'>'.$ident.$t->title.'</option>';
        } elseif ($prevLevel == $t->nlevel) {
            echo '<option value="'.$t->id.'"'.$selected.'>'.$ident.$t->title.'</option>';
        } else {
            echo '<option value="'.$t->id.'"'.$selected.'>'.$ident.$t->title.'</option>';
        }
        $prevLevel = $t->nlevel;
    }
}
        echo '
            </select>
        </div>

        <!-- upload options -->
        <div style="">'.$LANG['csv_import_options'].':</div>
            <div style="margin-left:20px;">
            <input type="checkbox" id="import_kps_anyone_can_modify" /><label for="import_kps_anyone_can_modify">'.$LANG['import_csv_anyone_can_modify_txt'].'</label><br />
            <input type="checkbox" id="import_kps_anyone_can_modify_in_role" /><label for="import_kps_anyone_can_modify_in_role">'.$LANG['import_csv_anyone_can_modify_in_role_txt'].'</label>
        </div>

        <!-- uploader -->
         <div id="upload_container_kp" style="text-align:center;margin-top:10px;">
            <div id="filelist_kp"></div><br />
            <a id="pickfiles_kp" href="#">'.$LANG['keepass_import_button_text'].'</a>
        </div>

        <div id="kp_import_information" style="margin:10px 0 0 10px;"></div>
    </div>
    <!-- end tab -->
</div>

<div style="text-align:center;margin-top:8px; display:none;" id="import_information"></div>';

?>
<script type="text/javascript">
    $(function() {
        $("select").multiselect({
            multiple: false
        });
        $("#import_tabs").tabs();

        // CSV IMPORT
        var uploader_csv = new plupload.Uploader({
            runtimes : "gears,html5,flash,silverlight,browserplus",
            browse_button : "pickfiles_csv",
            container : "upload_container_csv",
            max_file_size : "10mb",
            chunk_size : "1mb",
            unique_names : true,
            dragdrop : true,
            multiple_queues : false,
            multi_selection : false,
            max_file_count : 1,
            url : "sources/upload/upload.files.php",
            flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
            silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
            filters : [
                {title : "CSV files", extensions : "csv"}
            ],
            init: {
                FilesAdded: function(up, files) {
                    // generate and save token
                    $.post(
                        "sources/main.queries.php",
                        {
                            type : "save_token",
                            size : 25,
                            capital: true,
                            numeric: true,
                            ambiguous: true,
                            reason: "import_items_from_csv",
                            duration: 10
                        },
                        function(data) {
                            $("#import_user_token").val(data[0].token);
                            up.start();
                        },
                        "json"
                    );
                },
                BeforeUpload: function (up, file) {
                    up.settings.multipart_params = {
                        "PHPSESSID":"'.$_SESSION['user_id'];?>",
                        "csvFile":file.name,
                        "type_upload":"import_items_from_csv",
                        "user_token": $("#import_user_token").val()
                    };
                },
                UploadComplete: function(up, files) {
                    $.each(files, function(i, file) {
                        ImportCSV(file.name);
                        up.splice();    // clear the file queue
                    });
                }
            }
        });

        // Uploader options
        uploader_csv.bind("UploadProgress", function(up, file) {
            $("#" + file.id + " b").html(file.percent + "%");
        });
        uploader_csv.bind("Error", function(up, err) {
            $("#filelist_csv").html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
                ", Message: " + err.message +
                (err.file ? ", File: " + err.file.name : "") +
                "</div>"
            );
            up.splice();    // Clear the file queue
            up.refresh(); // Reposition Flash/Silverlight
        });
        uploader_csv.bind("+", function(up, file) {
            $("#" + file.id + " b").html("100%");
        });

        // Load CSV click
        $("#uploadfiles_csv").click(function(e) {
            uploader_csv.start();
            e.preventDefault();
        });
        uploader_csv.init();

        //-----------------------------------------------------

        // KEYPASS IMPORT
        var uploader_kp = new plupload.Uploader({
            runtimes : "gears,html5,flash,silverlight,browserplus",
            browse_button : "pickfiles_kp",
            container : "upload_container_kp",
            max_file_size : "10mb",
            chunk_size : "1mb",
            unique_names : true,
            dragdrop : true,
            multiple_queues : false,
            multi_selection : false,
            max_file_count : 1,
            url : "sources/upload/upload.files.php",
            flash_swf_url : "includes/libraries/Plupload/plupload.flash.swf",
            silverlight_xap_url : "includes/libraries/Plupload/plupload.silverlight.xap",
            filters : [
                {title : "Keypass files", extensions : "xml"}
            ],
            init: {
                FilesAdded: function(up, files) {
                    // generate and save token
                    $.post(
                        "sources/main.queries.php",
                        {
                            type : "save_token",
                            size : 25,
                            capital: true,
                            numeric: true,
                            ambiguous: true,
                            reason: "import_items_from_keypass",
                            duration: 10
                        },
                        function(data) {
                            $("#import_user_token").val(data[0].token);
                            up.start();
                        },
                        "json"
                    );
                },
                BeforeUpload: function (up, file) {
                    $("#import_status_ajax_loader").show();
                    up.settings.multipart_params = {
                        "PHPSESSID":"'.$_SESSION['user_id'];?>",
                        "xmlFile":file.name,
                        "type_upload":"import_items_from_keypass",
                        "user_token": $("#import_user_token").val()
                    };
                },
                UploadComplete: function(up, files) {
                    ImportKEEPASS(files[0].name);
                    up.splice();        // clear the file queue
                }
            }
        });
        // Uploader options
        uploader_kp.bind("UploadProgress", function(up, file) {
            $("#" + file.id + " b").html(file.percent + "%");
        });
        uploader_kp.bind("Error", function(up, err) {
            $("#filelist_kp").html("<div class='ui-state-error ui-corner-all'>Error: " + err.code +
                ", Message: " + err.message +
                (err.file ? ", File: " + err.file.name : "") +
                "</div>"
            );
            up.splice();    // clear the file queue
            up.refresh(); // Reposition Flash/Silverlight
        });
        uploader_kp.bind("+", function(up, file) {
            $("#" + file.id + " b").html("100%");
        });

        // Load CSV click
        $("#uploadfiles_kp").click(function(e) {
            uploader_kp.start();
            e.preventDefault();
        });
        uploader_kp.init();
    });

    /*
     * Import Items to Database
     */


    //Permits to upload passwords from CSV file
    function ImportCSV(file)
    {
        $("#import_information").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>').attr("class","").show();
        $("#import_selection").html("");
        $("#div_import_csv_selection").hide();
        $.post(
            "sources/import.queries.php",
            {
                type        : "import_file_format_csv",
                file        : file,
                folder_id   : $("#folder_id_selected").val()
            },
            function(data) {
                if (data[0].error == "bad_structure") {
                    $("#import_information").html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['import_error_no_read_possible'];?>").show();
                } else {
                    $("#div_import_csv_selection").show();
                    $("#import_selection").html(data[0].output+'<div style="text-align:center;margin-top:8px; display:none;" id="csv_import_information"></div><div style=""><button id="but_csv_start"><?php echo $LANG['import_button'];?></button></div>');
                    $("#item_all_selection").click(function() {
                        if ($("#item_all_selection").prop("checked")) {
                            $("input[class='item_checkbox']:not([disabled='disabled'])").attr("checked", true);
                        } else {
                            $("input[class='item_checkbox']:not([disabled='disabled'])").removeAttr("checked");
                        }
                    });
                    $("#but_csv_start").click(function() {
                        launchCSVItemsImport();
                    });
                    $("select").multiselect({
                        multiple: false,
                        selectedText: function(numChecked, numTotal, checkedItems){
                            return $(checkedItems[0]).attr('title') + ' checked';
                        }
                    });
                    $("button").button();
                    $(".ui-dialog-buttonpane button:contains('<?php echo $LANG['import_button'];?>')").button("disable");
                    $("#import_information").show().html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['alert_message_done'];?>").attr("class","ui-state-highlight");
                    setTimeout(function(){$("#import_information").effect( "fade", "slow" );}, 1000);
                }
            },
            "json"
        );
    }

    //get list of items checked by user
    function launchCSVItemsImport()
    {
        $("#csv_import_information").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>').attr("class","").show();
        var items = "";

        //Get data checked
        $("input[class=item_checkbox]:checked").each(function() {
            var elem = $(this).attr("id").split("-");
            if (items == "") items = $("#item_to_import_values-"+elem[1]).val();
            else items = items + "@_#sep#_@" + $("#item_to_import_values-"+elem[1]).val();

        });

        if (items == "") {
            $("#csv_import_information").html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['error_no_selected_folder'];?>").attr("class","ui-state-error");
            setTimeout(function(){$("#csv_import_information").effect( "fade", "slow" );}, 1000);
            return;
        }

        //Lauchn ajax query that will insert items into DB
        $.post(
            "sources/import.queries.php",
            {
                type        : "import_items",
                folder    : $("#import_items_to").val(),
                data        : aes_encrypt(items),
                import_csv_anyone_can_modify    : $("#import_csv_anyone_can_modify").prop("checked"),
                import_csv_anyone_can_modify_in_role    : $("#import_csv_anyone_can_modify_in_role").prop("checked")
            },
            function(data) {
                //after inserted, disable the checkbox in order to prevent against new insert
                var elem = data[0].items.split(";");
                for (var i=0; i<elem.length; i++) {
                    $("#item_to_import-"+elem[i]).attr("disabled", true);
                    $("#item_text-"+elem[i]).css("textDecoration", "line-through");
                }

                ListerItems($('#hid_cat').val(), "", 0)

                $("#csv_import_information").show().html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['alert_message_done'];?>").attr("class","ui-state-highlight");
                setTimeout(function(){$("#csv_import_information").effect( "fade", "slow" );}, 1000);

            },
            "json"
        );
    }



    //Permits to upload passwords from KEEPASS file
    function ImportKEEPASS(file)
    {
        $("#import_information").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>').attr("class","").show();

        //check if file has good format
        $.post(
            "sources/import.queries.php",
            {
                type        : "import_file_format_keepass",
                file        : file,
                destination        : $("#import_keepass_items_to").val()
            },
            function(data) {
                $("#kp_import_information").html(data[0].message + "<?php echo '<br><br><b>'.$LANG['alert_page_will_reload'].'</b>';?>");
                $("#import_information").show().html("<i class='fa fa-exclamation-circle'></i>&nbsp;<?php echo $LANG['alert_message_done'];?>").attr("class","ui-state-highlight");
                setTimeout(function(){$("#import_information").effect( "fade", "slow" );document.location = "index.php?page=items"}, 1000);
            },
            "json"
        );
    }
</script>
