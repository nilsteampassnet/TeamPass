<?php
/**
 * @file          tptools.php
 * @author        Nils Laumaillé
 * @version       0.1
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();

global $k, $settings;
header("Content-type: text/html; charset=utf-8");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php


error_reporting(E_ERROR);
include '../includes/include.php';
include '../includes/settings.php';

//Class loader
require_once '../sources/SplClassLoader.php';

if (empty($_SESSION['key'])) {
    $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
    $pwgen->register();
    $pwgen = new Encryption\PwGen\pwgen();

    $pwgen->setLength(20);
    $pwgen->setSecure(true);
    $pwgen->setSymbols(true);
    $pwgen->setCapitalize(true);
    $pwgen->setNumerals(true);

    $_SESSION['key'] = $pwgen->generate();
}
$_SESSION['prefix_length'] = "15";

// connect to the server
require_once '../includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port, $encoding);

// get some numbers
DB::query(
    "SELECT id FROM ".$pre."items"
);
$counter_items = DB::count();
DB::query(
    "SELECT id FROM ".$pre."users"
);
$counter_users = DB::count();
DB::query(
    "SELECT id FROM ".$pre."nested_tree"
);
$counter_folders = DB::count();
?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
        <title>Teampass - Tools</title>
        <?php
        echo '
        <link rel="stylesheet" href="../includes/js/jquery-ui/jquery-ui.min.css" type="text/css" />
        <script type="text/javascript" src="../includes/js/functions.js"></script>
        <script type="text/javascript" src="../includes/js/jquery-ui/external/jquery/jquery.js"></script>
        <script type="text/javascript" src="../includes/js/jquery-ui/jquery-ui.min.js"></script>
        <script type="text/javascript" src="../includes/js/jcenter/jquery.center.js"></script>';
        ?>
        <script type="application/javascript">
            function launchTool(number)
            {
                $("#wait").html("   WAIT...");
                $("#div_loading").show();
                $("#tbl_res").html("<thead><tr><th style='width=30px'>Select</th><th>Existing Pwd</th><th></th><th>Preview Pwd</th><th>Status</th></tr></thead><tbody>");
                var counter_items = parseInt($("#nb_items").val());
                var index = 0;
                var cnt = 0;
                var ajaxReqs = [];
                for (index = 0; index < counter_items; ++index) {
                    if (index > counter_items) {break;}
                    ajaxReqs.push($.ajax({
                        url: "tptools.queries.php",
                        type : 'POST',
                        dataType : "json",
                        data : {
                            type:       "tool_"+number,
                            index:      index,
                            max_index:  counter_items
                        },
                        complete : function(data, statut){
                            data = $.parseJSON(data.responseText);
                            if (data[0].error == "" ) {
                                var reg=new RegExp("(&escapesq;)", "g");
                                var res = data[0].result.replace(reg, "\'");
                                $("#tbl_res").append(res);
                            } else {

                            }
                        }
                    }));
                    index += 10;
                }
                $.when.apply($, ajaxReqs).then(function() {
                    // all requests are complete
                    $("#wait").html("");
                    $("#next_action").html("<br>Indicate here the length of the password prefix: <input type='text' id='randkey_length' value='<?php echo $_SESSION['prefix_length'];?>' />&nbsp;<a href='javascript:void(0)' onclick='refreshNewPwd()'>Refresh</a><br><br><input type='button' value='Select PWD to clean and press this button' onclick='cleanPwd()' />");
                    $("#div_loading").hide();
                });
            }

            function cleanPwd()
            {
                if ($("#randkey_length").val() > 0) {
                    $("#wait").html("   WAIT...");

                    var ajaxReqs = [];
                    $('input:checkbox').each(function () {
                        var selectedId = (this.checked ? $(this).attr('id') : "");

                        if (selectedId != "") {
                            ajaxReqs.push($.ajax({
                                url: "tptools.queries.php",
                                type : 'POST',
                                dataType : "json",
                                data : {
                                    type:       "tool_1",
                                    action:     "tool_clean_1",
                                    id:         selectedId,
                                    prefix_len: $("#randkey_length").val()
                                },
                                complete : function(data, statut){
                                    data = $.parseJSON(data.responseText);
                                    if (data[0].error == "" ) {
                                        $("#res_"+data[0].result).text("CHANGED");
                                        $("#"+data[0].result).prop('checked', false);
                                        $("#"+data[0].result).prop('disabled', true);
                                    } else {

                                    }
                                }
                            }));
                        }
                    });
                    $.when.apply($, ajaxReqs).then(function() {
                        // all requests are complete
                        $("#wait").html("");
                    });
                } else {
                    alert("You must indicate the length of the prefix to delete. By default it is 15 characters.")
                }
            }

            function refreshNewPwd()
            {
                $('input:checkbox').each(function () {
                    var oldPw = $("#old_"+$(this).attr('id')).text();
                    $("#new_"+$(this).attr('id')).html(oldPw.substring(parseInt($("#randkey_length").val())));
                });
                return false;
            }

            $(function() {
                $("#div_loading").dialog({
                    modal: true,
                    autoOpen: false,
                    width: 200,
                    height: 150,
                    dialogClass: "dlgfixed",
                    position: "center",
                    title: "Please wait"
                });
                $("#div_loading").center(false);
                $("#div_loading").show()
            });
        </script>
        <style>
            .datagrid table { border-collapse: collapse; text-align: left; width: 100%; } .datagrid {font: normal 12px/150% Arial, Helvetica, sans-serif; background: #fff; overflow: hidden; border: 1px solid #36752D; -webkit-border-radius: 3px; -moz-border-radius: 3px; border-radius: 3px; }.datagrid table td, .datagrid table th { padding: 1px 0px; }.datagrid table thead th {background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #36752D), color-stop(1, #275420) );background:-moz-linear-gradient( center top, #36752D 5%, #275420 100% );filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#36752D', endColorstr='#275420');background-color:#36752D; color:#FFFFFF; font-size: 15px; font-weight: bold; border-left: 1px solid #36752D; } .datagrid table thead th:first-child { border: none; }.datagrid table tbody td { color: #275420; font-size: 12px;font-weight: normal; }.datagrid table tbody .alt td { background: #DFFFDE; color: #275420; }.datagrid table tbody td:first-child { border-left: none; }.datagrid table tbody tr:last-child td { border-bottom: none; }.datagrid table tfoot td div { border-top: 1px solid #36752D;background: #DFFFDE;} .datagrid table tfoot td { padding: 0; font-size: 12px } .datagrid table tfoot td div{ padding: 2px; }.datagrid table tfoot td ul { margin: 0; padding:0; list-style: none; text-align: right; }.datagrid table tfoot  li { display: inline; }.datagrid table tfoot li a { text-decoration: none; display: inline-block;  padding: 2px 8px; margin: 1px;color: #FFFFFF;border: 1px solid #36752D;-webkit-border-radius: 3px; -moz-border-radius: 3px; border-radius: 3px; background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #36752D), color-stop(1, #275420) );background:-moz-linear-gradient( center top, #36752D 5%, #275420 100% );filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#36752D', endColorstr='#275420');background-color:#36752D; }.datagrid table tfoot ul.active, .datagrid table tfoot ul a:hover { text-decoration: none;border-color: #275420; color: #FFFFFF; background: none; background-color:#36752D;}div.dhtmlx_window_active, div.dhx_modal_cover_dv { position: fixed !important; }
        </style>
    </head>
    <body>
    <?php
    echo '
    <input type="hidden" id="nb_items" value="'.$counter_items.'" /><input type="hidden" id="nb_users" value="'.$counter_users.'" /><input type="hidden" id="nb_folders" value="'.$counter_folders.'" />
    <div style="">This is a very basic utility for Teampass.<br>Please be sure to increase the maximum execution time to ensure better results.<br>Use this page by enabling Firebug to check answers from server.</div>
    <br>
    <div>
    SALTKey: <b><i>'.SALT.'</i></b>
    <br>
    Database: <b><i>'.$database.'</i></b>
    <br>
    Key generated: <b><i>'.$_SESSION['key'].'</i></b>
    <br>
    Number of Items: <b><i>'.$counter_items.'</i></b>
    <br>
    Number of Folders: <b><i>'.$counter_folders.'</i></b>
    <br>
    Number of Users: <b><i>'.$counter_users.'</i></b>
    </div>
    <br>
    <input type="button" value="Correct password prefix key" id="tool_1" onclick="launchTool(1)" /><span id="wait"></span>
    <br/>
    <div id="result" class="datagrid"><table id="tbl_res"></table></div>
    <br>
    <div id="next_action"></div>
    <br/>';
    ?>
    <div id="div_loading" style="display:none;">
        <div style="padding:5px; z-index:9999999;" class="ui-widget-content ui-state-focus ui-corner-all">
            <img src="../includes/images/76.gif" alt="" />
        </div>
    </div>
    </body>
</html>