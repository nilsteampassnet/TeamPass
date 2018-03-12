<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta content="text/html;charset=utf-8" http-equiv="Content-Type">
        <meta content="utf-8" http-equiv="encoding">
        <title>TeamPass Installation</title>
        <link rel="stylesheet" href="css/install.css" type="text/css" />
        <link rel="stylesheet" href="css/overcast/jquery-ui-1.10.3.custom.min.css" type="text/css" />
        <script type="text/javascript" src="../includes/js/functions.js"></script>
        <script type="text/javascript" src="js/jquery.min.js"></script>
        <script type="text/javascript" src="js/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js/aes.min.js"></script>
        <script type="text/javascript" src="install.js"></script>
        <script type="text/javascript">
        </script>
    </head>

    <body>
<?php
// define root path
$abs_path = rtrim($_SERVER['DOCUMENT_ROOT'], '/').substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF']) - 20);
if (isset($_SERVER['HTTPS'])) {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}

    echo '
    <input type="hidden" id="page_id" value="1" />
    <input type="hidden" id="step_res" value="" />
    <input type="hidden" id="hid_db_host" value="" />
    <input type="hidden" id="hid_db_login" value="" />
    <input type="hidden" id="hid_db_pwd" value="" />
    <input type="hidden" id="hid_db_port" value="" />
    <input type="hidden" id="hid_db_bdd" value="" />
    <input type="hidden" id="hid_db_pre" value="" />
    <input type="hidden" id="hid_abspath" value="" />
    <input type="hidden" id="hid_url_path" value="" />';
    // # LOADER
    echo '
    <div style="position:absolute;top:49%;left:49%;display:none;z-index:9999999;" id="loader"><img src="images/76.gif" /></div>';
    // # HEADER ##
    echo '
    <div id="top">
        <div id="logo"><img src="../includes/images/canevas/logo.png" /></div>
    </div>
    <div id="main">
        <div id="menu">
            <div style="font-weight:bold;text-align:center;">Installation steps</div>
            <ul>
                <li id="menu_step1" class="li_inprogress"><span id="step_1">Welcome</span>&nbsp;<span id="res_1"></span></li>
                <li id="menu_step2"><span id="step_2">Server checks</span>&nbsp;<span id="res_2"></span></li>
                <li id="menu_step3"><span id="step_3">Database connection</span>&nbsp;<span id="res_3"></span></li>
                <li id="menu_step4"><span id="step_4">Preparation</span>&nbsp;<span id="res_4"></span></li>
                <li id="menu_step5"><span id="step_5">Tables creation</span>&nbsp;<span id="res_5"></span></li>
                <li id="menu_step6"><span id="step_6">Finalization</span>&nbsp;<span id="res_6"></span></li>
                <li id="menu_step8">Resume&nbsp;<span id="res_8"></span></li>
            </ul>
        </div>
        <div id="content">
            <div id="step_name">Welcome</div>
            <div id="step_error" class="ui-widget ui-state-error error"></div>
            <div style="height:400px;overflow:auto;">
                <div id="step_content" style="">
                    Before starting, be sure to:<br />
                    - upload the complete package on the server,<br />
                    - have the database connection information (*)<br />
                    <br />
                    <br />
                    <i>* Mysql database suggestions:<br />
                    - create a new database (for example teampass),<br />
                    - create a new mysql user (for example teampass_root),<br />
                    - set full admin rights for this user on teampass database,<br />
                    - allow access from localhost to the database<br /></i><br />
                    <div style="padding:5px;" class="ui-widget ui-state-highlight">
                         TeamPass is distributed under GNU AFFERO GPL licence.
                    </div>
                </div>
            </div>
        </div>
    </div>
        <div id="action_buttons">
            <span id="step_result"></span>
            <input type="button" id="but_launch" onclick="checkPage()" class="button" value="LAUNCH" />
            <input type="button" id="but_next" onclick="GotoNextStep()" class="button" value="NEXT" />
            <input type="button" id="but_restart" onclick="document.location = \'install.php\'" class="button" value="RESTART" />
            <input type="button" id="but_start" onclick="document.location = \''.$protocol.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'\'" class="button" style="display: none;" value="Start" />
            &nbsp;&nbsp;
        </div>';
?>
    </body>
</html>
<?php
echo '
<div id="text_step2" style="display:none;">
    <h5>Teampass instance information:</h5>
    <table>
        <tr>
            <td style="width:150px;">
                <label for="root_path" class="label_block_big">Absolute path to teampass folder :</label>
            </td>
            <td>
                <input type="text" id="root_path" name="root_path" class="ui-widget" style="width:450px;" value="'.$abs_path.'" />
            </td>
        </tr>
        <tr>
            <td style="width:150px;">
                <label for="url_path" class="label_block_big">Full URL to teampass :</label>
            </td>
            <td>
                <input type="text" id="url_path" name="url_path" class="ui-widget" style="width:450px;" value="'.$protocol.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8).'" /
            </td>
        </tr>
    </table>

    <h5>Following elements will be checked:</h5>
    <ul>
    <li>Directory "/install/" is writable&nbsp;<span id="res2_check0"></span></li>
    <li>Directory "/includes/" is writable&nbsp;<span id="res2_check1"></span></li>
    <li>Directory "/includes/config/" is writable&nbsp;<span id="res2_check2"></span></li>
    <li>Directory "/includes/avatars/" is writable&nbsp;<span id="res2_check3"></span></li>
    <li>Directory "/includes/libraries/csrfp/libs/" is writable&nbsp;<span id="res2_check4"></span></li>
    <li>Directory "/includes/libraries/csrfp/js/" is writable&nbsp;<span id="res2_check5"></span></li>
    <li>Directory "/includes/libraries/csrfp/log/" is writable&nbsp;<span id="res2_check6"></span></li>
    <li>Directory "/files/" is writable&nbsp;<span id="res2_check7"></span></li>
    <li>Directory "/upload/" is writable&nbsp;<span id="res2_check8"></span></li>
    <li>PHP extension "mcrypt" is loaded&nbsp;<span id="res2_check9"></span></li>
    <li>PHP extension "mbstring" is loaded&nbsp;<span id="res2_check10"></span></li>
    <li>PHP extension "openssl" is loaded&nbsp;<span id="res2_check11"></span></li>
    <li>PHP extension "bcmath" is loaded&nbsp;<span id="res2_check12"></span></li>
    <li>PHP extension "iconv" is loaded&nbsp;<span id="res2_check13"></span></li>
    <li>PHP extension "xml" is loaded&nbsp;<span id="res2_check14"></span></li>
    <li>PHP extension "gd" is loaded&nbsp;<span id="res2_check15"></span></li>
    <li>PHP extension "curl" is loaded&nbsp;<span id="res2_check16"></span></li>
    <li>PHP version is greater or equal to 5.5.0&nbsp;<span id="res2_check17"></span></li>
    <li>Execution time limit&nbsp;<span id="res2_check18"></span></li>
    </ul>
</div>';

echo '
<div id="text_step3" style="display:none;">
    <h5>Database information:</h5>
    <div style="margin:5px 0 5px 0;">Database connection Information</div>
    <table>
        <tr>
            <td style="width:150px;">
                <label for="db_host" class="label_block_big">Host :</label>
            </td>
            <td>
                <input type="text" id="db_host" value="" style="width:350px;" />
            </td>
        </tr>
        <tr>
            <td style="width:150px;">
                <label for="db_bdd" class="label_block_big">DataBase name :</label>
            </td>
            <td>
                <input type="text" id="db_bdd" value="" style="width:350px;" />
            </td>
        </tr>
        <tr>
            <td style="width:150px;">
                <label for="db_login" class="label_block_big">Login :</label>
            </td>
            <td>
                <input type="text" id="db_login" value="" style="width:350px;" />
            </td>
        </tr>
        <tr>
            <td style="width:150px;">
                <label for="db_pw" class="label_block_big">Password :</label>
            </td>
            <td>
                <input type="text" id="db_pw" value="" style="width:350px;" title="Double quotes not allowed!" />
            </td>
        </tr>
        <tr>
            <td style="width:150px;">
                <label for="db_port" class="label_block_big">Port :</label>
            </td>
            <td>
                <input type="text" id="db_port" value="3306" style="width:350px;" />
            </td>
        </tr>
    </table>
</div>';

echo '
<div id="text_step4" style="display:none;">
    <table>
        <tr>
            <td colspan="2">
                <h5>Teampass set-up:</h5>
            </td>
        </tr>
        <tr>
            <td style="width:250px;">
                <label for="tbl_prefix" class="label_block_big">Table prefix :</label>
            </td>
            <td>
                <input type="text" id="tbl_prefix" value="teampass_" style="width:350px;" />&nbsp;<span id="res4_check0"></span>
            </td>
        </tr>
        <tr>
            <td style="width:250px;">
                <label for="sk_path" class="label_block_big">Absolute path to SaltKey :
                    <img src="images/information-white.png" alt="" title="The SaltKey is stored in a file called sk.php. But for security reasons, this file should be stored in a folder outside the www folder of your server (example: /var/teampass/). So please, indicate here the path to this folder.  If this field remains empty, this file will be stored in folder <path to Teampass>/includes/." />
                </label>
            </td>
            <td>
                <input type="text" id="sk_path" value="" style="width:350px;" />&nbsp;<span id="res4_check2"></span>
            </td>

            <div class="line_entry">
        </tr>
        <tr>
            <td colspan="2">
                <h5>Administrator account set-up:</h5>
            </td>
        </tr>
        <tr>
            <td style="width:250px;">
                <label for="admin_pwd" class="label_block_big">Administrator password :</label>
            </td>
            <td>
                <input type="text" id="admin_pwd" style="width:350px;" />&nbsp;<span id="res4_check10"></span>
            </td>
        </tr>
    </table>
</div>';

echo '
<div id="text_step5" style="display:none;">
    <h5>Now populating database</h5>
    <ul id="pop_db"></ul>
    <!--<ul>
    <li>Add table "items"&nbsp;<span id="res5_check0"></span></li>
    <li>Add table "log_items"&nbsp;<span id="res5_check1"></span></li>
    <li>Add table "misc"&nbsp;<span id="res5_check2"></span></li>
    <li>Add table "nested_tree"&nbsp;<span id="res5_check3"></span></li>
    <li>Add table "rights"&nbsp;<span id="res5_check4"></span></li>
    <li>Add table "users"&nbsp;<span id="res5_check5"></span></li>
    <li>Add Admin account&nbsp;<span id="res5_check6"></span></li>
    <li>Add table "tags"&nbsp;<span id="res5_check7"></span></li>
    <li>Add table "log_system"&nbsp;<span id="res5_check8"></span></li>
    <li>Add table "files"&nbsp;<span id="res5_check9"></span></li>
    <li>Add table "cache"&nbsp;<span id="res5_check10"></span></li>
    <li>Add table "roles_title"&nbsp;<span id="res5_check11"></span></li>
    <li>Add table "roles_values"&nbsp;<span id="res5_check12"></span></li>
    <li>Add table "kb"&nbsp;<span id="res5_check13"></span></li>
    <li>Add table "kb_categories"&nbsp;<span id="res5_check14"></span></li>
    <li>Add table "kb_items"&nbsp;<span id="res5_check15"></span></li>
    <li>Add table "restriction_to_roles"&nbsp;<span id="res5_check16"></span></li>
    <li>Add table "languages"&nbsp;<span id="res5_check18"></span></li>
    <li>Add table "emails"&nbsp;<span id="res5_check19"></span></li>
    <li>Add table "automatic_del"&nbsp;<span id="res5_check20"></span></li>
    <li>Add table "items_edition"&nbsp;<span id="res5_check21"></span></li>
    <li>Add table "categories"&nbsp;<span id="res5_check22"></span></li>
    <li>Add table "categories_items"&nbsp;<span id="res5_check23"></span></li>
    <li>Add table "categories_folders"&nbsp;<span id="res5_check24"></span></li>
    <li>Add table "api"&nbsp;<span id="res5_check25"></span></li>
    <li>Add table "otv"&nbsp;<span id="res5_check26"></span></li>
    <li>Add table "suggestion"&nbsp;<span id="res5_check27"></span></li>
    <li>Add table "tokens"&nbsp;<span id="res5_check28"></span></li>
    <li>Add table "items_change"&nbsp;<span id="res5_check29"></span></li>
    </ul>-->
</div>';


echo '
<div id="text_step6" style="display:none;">
    <h5>Finalization:</h5>
    <ul>
    <li>Write the new setting.php file for your server configuration <span id="res6_check0"></span></li>
    <li>Write the new sk.php file for data encryption <span id="res6_check1"></span></li>
    <li>Change directory security permissions <span id="res6_check2"></span></li>
    </ul>
</div>';


echo '
<div id="text_step7" style="display:none;">
    <h4>Thank you for installing <b>Teampass</b>.</h4>
    <div style="margin-top:20px;">
        The final step is now to move to the authentication page and start using <b>Teampass</b>.<br>
        The Administrator login is `<b>admin</b>`.
        <br>
        Its password is the one you have written during the installation process.
    </div>
    <div style="margin-top:8px;">
        <i>Please note that first page may be longer to load. Install files and folders will be deleted for security purpose.
        <br>
        In case warning "Install folder has to be removed!" is shown while login, this operation has failed and requires to be done manually.</i>
    </div>
    <div style="margin-top:40px; text-align:center;">
        <a id="link_home_page" href="../index.php">Move to home page</a>
    </div>
    <div style="margin-top:80px; font-size:10px;">
        For news, help and information, please visit <a href="https://teampass.net" target="_blank">TeamPass website</a>.
    </div>
</div>';
?>
