<?php
session_start();
//Session teampass tag
$_SESSION['CPM'] = 1;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>TeamPass Installation</title>
        <link rel="stylesheet" href="install.css" type="text/css" />
        <script type="text/javascript" src="../includes/js/functions.js"></script>
        <script type="text/javascript" src="install.js"></script>
        <script type="text/javascript" src="js/jquery.min.js"></script>
		<script type="text/javascript" src="js/jquery-ui.min.js"></script>
        <script type="text/javascript" src="gauge/gauge.js"></script>

        <script type="text/javascript">
        //if(typeof $=='undefined') {function $(v) {return(document.getElementById(v));}}
        $(function() {
        	if($("#approve_license").val() != undefined){
        		$("#but_next").attr("disabled", "disabled");

        		$("#approve_license").click(function() {
					if($("#approve_license").is(':checked')) $("#but_next").removeAttr("disabled");
					else $("#but_next").attr("disabled", "disabled");
				});
        	}
            if ( document.getElementById("progressbar") ){
                gauge.add($("progressbar"), { width:600, height:30, name: 'pbar', limit: true, gradient: true, scale: 10, colors:['#ff0000','#00ff00']});
                if ( document.getElementById("step").value == "1" ) gauge.modify($('pbar'),{values:[0.20,1]});
                else if ( document.getElementById("step").value == "2" ) gauge.modify($('pbar'),{values:[0.35,1]});
                else if ( document.getElementById("step").value == "3" ) gauge.modify($('pbar'),{values:[0.55,1]});
                else if ( document.getElementById("step").value == "4" ) gauge.modify($('pbar'),{values:[0.70,1]});
                else if ( document.getElementById("step").value == "5" ) gauge.modify($('pbar'),{values:[0.85,1]});
            }
        });

        function goto_next_page(page){
            if (page == "3" && document.getElementById("cpm_is_utf8").value == 1) {
                page = "4";
            }
            document.getElementById("step").value=page;
            document.install.submit();
        }

        function Check(step){
            if ( step != "" ){
                if ( step == "step1" ){
                    var data = "type="+step+
                    "&abspath="+escape(document.getElementById("root_path").value);
                    document.getElementById("loader").style.display = "";
                }else
                if ( step == "step2" ){
                    document.getElementById("loader").style.display = "";
                    var data = "type="+step+
                    "&db_host="+document.getElementById("db_host").value+
                    "&db_login="+escape(document.getElementById("db_login").value)+
                    "&tbl_prefix="+escape(document.getElementById("tbl_prefix").value)+
                    "&db_password="+encodeURIComponent(document.getElementById("db_pw").value)+
                    "&db_bdd="+document.getElementById("db_bdd").value;
                }else
                if ( step == "step3" ){
                    document.getElementById("res_step3").innerHTML = '<img src="images/ajax-loader.gif" alt="" />';
                    var data = "type="+step+
                    "&prefix_before_convert="+document.getElementById("prefix_before_convert").checked;
                    document.getElementById("loader").style.display = "";
                }else
                if ( step == "step4" ){
                    var data = "type="+step;
                    document.getElementById("loader").style.display = "";
                }else
                if ( step == "step5" ){
                    document.getElementById("loader").style.display = "";
                    var data = "type="+step;
                }
                httpRequest("upgrade_ajax.php",data);
            }
        }
        </script>
    </head>
    <body>
<?php
require_once("../includes/language/english.php");
require_once("../includes/include.php");

if (isset($_POST['db_host'])) {
	$_SESSION['db_host'] = $_POST['db_host'];
	$_SESSION['db_bdd'] = $_POST['db_bdd'];
	$_SESSION['db_login'] = $_POST['db_login'];
	$_SESSION['db_pw'] = $_POST['db_pw'];
	$_SESSION['tbl_prefix'] = $_POST['tbl_prefix'];
	if (isset($_POST['send_stats'])) {
		$_SESSION['send_stats'] = $_POST['send_stats'];
	}else{
		$_SESSION['send_stats'] = "";
	}
}

// LOADER
echo '
    <div style="position:absolute;top:49%;left:49%;display:none;" id="loader"><img src="images/ajax-loader.gif" /></div>';

// HEADER
echo '
        <div id="top">
            <div id="logo"><img src="../includes/images/canevas/logo.png" /></div>
        </div>
        <div id="content">
            <div id="center" class="ui-corner-bottom">
                <form name="install" method="post" action="">';

//HIDDEN THINGS
echo '
                    <input type="hidden" id="step" name="step" value="', isset($_POST['step']) ? $_POST['step']:'', '" />
                    <input type="hidden" id="actual_cpm_version" name="actual_cpm_version" value="', isset($_POST['actual_cpm_version']) ? $_POST['actual_cpm_version']:'', '" />
                    <input type="hidden" id="cpm_is_utf8" name="cpm_is_utf8" value="', isset($_POST['cpm_is_utf8']) ? $_POST['cpm_is_utf8']:'', '" />
					<input type="hidden" name="menu_action" id="menu_action" value="" />';

if ( !isset($_GET['step']) && !isset($_POST['step'])  ){
	//ETAPE O
	echo '
	                 <h2>This page will help you to upgrade the TeamPass\'s database</h2>

	                 Before starting, be sure to:<br />
	                 - upload the complete package on the server and overwrite existing files,<br />
	                 - have the database connection informations,<br />
	                 - get some CHMOD rights on the server.<br />
	                 <br />
	                 <span style="font-weight:bold; font-size:14px;color:#C60000;"><img src="../includes/images/error.png" />&nbsp;ALWAYS BE SURE TO CREATE A DUMP OF YOUR DATABASE BEFORE UPGRADING</span>
	                 <div style="" class="ui-widget ui-state-highlight">
	                 	<h4>Read and approve Licence before continuing</h4>';
						// Display the license file
						$Fnm = "../license.txt";
						if (file_exists($Fnm)) {
							$tab = file($Fnm);
							echo '
							<div style="float:left;width:100%;height:250px;overflow:auto;">
								<div style="float:left;font-style:italic;">';
								$show = false;
								$cnt = 0;
								while(list($cle,$val) = each($tab)) {
									echo $val."<br />";
								}
								echo '
									<div  style="width:100%; margin-top:20px; margin-bottom:20px;">
										<label for="approve_license" style="width:100%;">I\'ve read and I accept the License</label><input type="checkbox" id="approve_license" />
									</div>
								</div>
							</div>';
						}
					echo '
	                 </div>
	                 &nbsp;
	                 ';

}else if ( (isset($_POST['step']) && $_POST['step'] == 1) || (isset($_GET['step']) && $_GET['step'] == 1) ){
//define root path
	$abs_path = "";
	if(strrpos($_SERVER['DOCUMENT_ROOT'],"/") == 1) $abs_path = strlen($_SERVER['DOCUMENT_ROOT'])-1;
	else $abs_path = $_SERVER['DOCUMENT_ROOT'];
	$abs_path .= substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF'])-20);
	//ETAPE 1
	echo '
	                 <h3>Step 1 - Check server</h3>

	                 <fieldset><legend>Please give me</legend>
	                 <label for="root_path" style="width:300px;">Absolute path to TeamPass folder :</label><input type="text" id="root_path" name="root_path" class="step" style="width:560px;" value="'.$abs_path.'" /><br />
	                 </fieldset>

	                 <h4>Next elements will be checked.</h4>
	                 <div style="margin:15px;" id="res_step1">
	                 <span style="padding-left:30px;font-size:13pt;">File "settings.php" is writable</span><br />
	                 <span style="padding-left:30px;font-size:13pt;">Directory "/install/" is writable</span><br />
	                 <span style="padding-left:30px;font-size:13pt;">Directory "/includes/" is writable</span><br />
	                 <span style="padding-left:30px;font-size:13pt;">Directory "/files/" is writable</span><br />
	                 <span style="padding-left:30px;font-size:13pt;">Directory "/upload/" is writable</span><br />
	                 <span style="padding-left:30px;font-size:13pt;">PHP extension "mcrypt" is loaded</span><br />
                     <span style="padding-left:30px;font-size:13pt;">PHP version is gretter or equal to 5.3.0</span><br />
	                 </div>
	                 <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step1"></div>
	                 <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step1_error"></div>
	                 <input type="hidden" id="step1" name="step1" value="" />';


}else if ( (isset($_POST['step']) && $_POST['step'] == 2) || (isset($_GET['step']) && $_GET['step'] == 2) ){
	//ETAPE 2
	echo '
	                 <h3>Step 2</h3>
	                 <fieldset><legend>Database Informations</legend>
	                 <label for="db_host">Host :</label><input type="text" id="db_host" name="db_host" class="step" /><br />
	                 <label for="db_db">Database name :</label><input type="text" id="db_bdd" name="db_bdd" class="step" /><br />
	                 <label for="db_login">Login :</label><input type="text" id="db_login" name="db_login" class="step" /><br />
	                 <label for="db_pw">Password :</label><input type="password" id="db_pw" name="db_pw" class="step" /><br />
	                 <label for="tbl_prefix">Table prefix :</label><input type="text" id="tbl_prefix" name="tbl_prefix" class="step" value="teampass_" />
	                 </fieldset>

	                 <fieldset><legend>Anonymous statistics</legend>
	                 <input type="checkbox" name="send_stats" id="send_stats" />Send monthly anonymous statistics.<br />
	                 Please considere sending your statistics as a way to contribute to futur improvments of TeamPass. Indeed this will help the creator to evaluate how the tool is used and by this way how to improve the tool. When enabled, the tool will automatically send once by month a bunch of statistics without any action from you. Of course, those data are absolutely anonymous and no data is exported, just the next informations : number of users, number of folders, number of items, tool version, ldap enabled, and personal folders enabled.<br>
	                 This option can be enabled or disabled through the administration panel.
	                 </fieldset>

	                 <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step2"></div>
	                 <input type="hidden" id="step2" name="step2" value="" />';
}

else if ( (isset($_POST['step']) && $_POST['step'] == 3 || isset($_GET['step']) && $_GET['step'] == 3) && (isset($_POST['actual_cpm_version'])) ){
	//ETAPE 3
	echo '
	                 <h3>Step 3 - Converting database to UTF-8</h3>';

	if (version_compare($_POST['actual_cpm_version'], $k['version'], "<")) {
		echo '
			Notice that TeamPass is now only using UTF-8 charset.
			This step will convert the database to this charset.<br />
			<p>
				Save previous tables before converting (prefix "old_" will be used)&nbsp;&nbsp;<input type="checkbox" id="prefix_before_convert" />
			</p>
			Click on the button when ready.

			<div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step3"></div>  ';
		$conversion_utf8 = true;
	}else{
		echo '
			The database seems already in UTF-8 charset';
		$conversion_utf8 = false;
	}
}

else if ( (isset($_POST['step']) && $_POST['step'] == 4) || (isset($_GET['step']) && $_GET['step'] == 4) ){
	//ETAPE 4

	echo '
	                 <h3>Step 4</h3>

	                 The upgrader will now update your database.
	                 <table>
	                     <tr><td>Misc table will be populated with new values</td><td><span id="tbl_1"></span></td></tr>
	                     <tr><td>Users table will be altered with news fields</td><td><span id="tbl_2"></span></td></tr>
	                     <tr><td>Nested_Tree table will be altered with news fields</td><td><span id="tbl_5"></span></td></tr>
	                     <tr><td>Table "tags" will be created</td><td><span id="tbl_3"></span></td></tr>
	                     <tr><td>Table "log_system" will be created</td><td><span id="tbl_4"></span></td></tr>
	                     <tr><td>Table "files" will be created</td><td><span id="tbl_6"></span></td></tr>
	                     <tr><td>Table "cache" will be created</td><td><span id="tbl_7"></span></td></tr>
	                     <tr><td>Change table "functions" to "roles"</td><td><span id="tbl_9"></span></td></tr>
	                     <tr><td>Add table "kb"</td><td><span id="tbl_10"></span></td></tr>
	                     <tr><td>Add table "kb_categories"</td><td><span id="tbl_11"></span></td></tr>
	                     <tr><td>Add table "kb_items"</td><td><span id="tbl_12"></span></td></tr>
	                     <tr><td>Add table "restriction_to_roles"</td><td><span id="tbl_13"></span></td></tr>
	                     <tr><td>Add table "keys"</td><td><span id="tbl_14"></span></td></tr>
	                     <tr><td>Populate table "keys"</td><td><span id="tbl_15"></span></td></tr>
	                     <tr><td>Add table "Languages"</td><td><span id="tbl_16"></span></td></tr>
	                 </table>

	                 <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step4"></div>
	                 <input type="hidden" id="step4" name="step4" value="" />';
}

else if ( (isset($_POST['step']) && $_POST['step'] == 5) || (isset($_GET['step']) && $_GET['step'] == 5) ){
	//ETAPE 5
	echo '
	                 <h3>Step 5 - Update setting file</h3>
	                 This step will write the new setting.php file for your server configuration.<br />
	                 Click on the button when ready.

	                 <div style="margin-top:20px;font-weight:bold;text-align:center;height:27px;" id="res_step5"></div>  ';
}

else if ( (isset($_POST['step']) && $_POST['step'] == 6) || (isset($_GET['step']) && $_GET['step'] == 6) ){
	//ETAPE 5
	echo '
	                 <h3>Step 6</h3>
	                 Upgrade is now finished!<br />
	                 You can delete "Install" directory from your server for more security.<br /><br />
	                 For news, help and information, visit the <a href="http://teampass.net" target="_blank">TeamPass website</a>.';
}


//buttons
if ( !isset($_POST['step']) ){
	echo '
	             <div id="buttons_bottom">
	                 <input type="button" id="but_next" onclick="goto_next_page(\'1\')" style="padding:3px;cursor:pointer;font-size:20px;" disabled="disabled" class="ui-state-default ui-corner-all" value="NEXT" />
	             </div>';
}elseif ( $_POST['step'] == 3 && $conversion_utf8 == false){
	echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_next" onclick="goto_next_page(\''. (intval($_POST['step'])+1) . '\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" />
                        </div>
                    </div>';
}elseif ( $_POST['step'] == 3 && $conversion_utf8 == true){
	echo '
                    <div style="width:900px;margin:auto;margin-top:30px;">
                        <div id="progressbar" style="float:left;margin-top:9px;"></div>
                        <div id="buttons_bottom">
                            <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'] .'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
                            <input type="button" id="but_next" onclick="goto_next_page(\''. (intval($_POST['step'])+1) . '\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
                        </div>
                    </div>';
}elseif ( $_POST['step'] == 6 ){
	echo '
	             <div id="buttons_bottom">
	                 <input type="button" id="but_next" onclick="javascript:window.location.href=\'http://' . $_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')-8) . '\';" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="Open TeamPass" />
	             </div>';
}else{
	echo '
	                 <div style="width:900px;margin:auto;margin-top:30px;">
	                     <div id="progressbar" style="float:left;margin-top:9px;"></div>
	                     <div id="buttons_bottom">
	                         <input type="button" id="but_launch" onclick="Check(\'step'.$_POST['step'] .'\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="LAUNCH" />
	                         <input type="button" id="but_next" onclick="goto_next_page(\''. (intval($_POST['step'])+1) . '\')" style="padding:3px;cursor:pointer;font-size:20px;" class="ui-state-default ui-corner-all" value="NEXT" disabled="disabled" />
	                     </div>
	                 </div>';
}

echo '
                </form>
            </div>
            </div>';
//FOOTER
// DON'T MODIFY THE FOOTER
echo '
    <div id="footer">
        <div style="width:500px;">
            '.$k['tool_name'].' '.$k['version'].' &#169; copyright 2009-2012
        </div>
        <div style="float:right;margin-top:-15px;">
        </div>
    </div>';
?>
    </body>
</html>