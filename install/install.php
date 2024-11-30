<?php
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
 * @file      install.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require '../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;


session_start();

// Get some data
include "../includes/config/include.php";
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

$superGlobal = new SuperGlobal();

// Prepare variables
$serverPath = rtrim($superGlobal->get('DOCUMENT_ROOT', 'SERVER'), '/').
	substr($superGlobal->get('PHP_SELF', 'SERVER'), 0,-20);
$serverProtocol = null !== $superGlobal->get('HTTPS', 'SERVER') ? 'https://' : 'http://';

// Fonction pour récupérer les valeurs POST avec une valeur par défaut si vide
function getPostValue($key, $defaultKey = null) {
	$superGlobal = new SuperGlobal();
    $value = $superGlobal->get($key, 'POST');
    if (empty($value) && $defaultKey !== null) {
        $value = $superGlobal->get($defaultKey, 'POST');
    }
    return $value;
}

// Récupère les valeurs POST
$post_step = $superGlobal->get('installStep', 'POST');
$post_db_host = getPostValue('db_host', 'hid_db_host');
$post_db_login = getPostValue('db_login', 'hid_db_login');
$post_db_pwd = getPostValue('db_pwd', 'hid_db_pwd');
$post_db_port = getPostValue('db_port', 'hid_db_port');
$post_db_bdd = getPostValue('db_bdd', 'hid_db_bdd');
$post_db_pre = getPostValue('db_pre', 'hid_db_pre');
$post_absolute_path = getPostValue('absolute_path', 'hid_absolute_path');
$post_url_path = getPostValue('url_path', 'hid_url_path');
$post_sk_path = getPostValue('sk_path', 'hid_sk_path');
$post_sk_filename = getPostValue('sk_filename', 'hid_sk_filename');
$post_sk_key = getPostValue('sk_key', 'hid_sk_key');

// Reset session
if (null !== $superGlobal->get('PHPSESSID', 'COOKIE')) {
    setcookie('PHPSESSID', '', time() - 10, '/', '', false, true);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

// CSRF token
if (null === $superGlobal->get('csrf_token', 'SESSION')) {
    $superGlobal->put('csrf_token', bin2hex(random_bytes(32)), 'SESSION');
}
$csrf_token = $superGlobal->get('csrf_token', 'SESSION');

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">

<head>
	<title>TeamPass Installation</title>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="stylesheet" href="../plugins/alertifyjs/css/alertify.min.css"/>
	<link rel="stylesheet" href="../plugins/bootstrap-5.3.3-dist/css/bootstrap.min.css" />
	<link rel="stylesheet" href="../plugins/fontawesome-free-6.7.1-web/css/all.min.css" type="text/css">
	<link rel="stylesheet" href="css/install.css" type="text/css" />
    
</head>

<body class="bg-light">

	<form method="POST" id="installation" action="">
		<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">	
		<input type="text" id="installStep" name="installStep" value="<?php echo isset($post_step) ? htmlspecialchars($post_step) : '';?>" />
	</form>

	<!--
	<input type="hidden" id="step_res" value="" />
	<input type="hidden" name="hid_db_host" id="hid_db_host" value="', isset($post_db_host) ? $post_db_host : '', '" />
	<input type="hidden" name="hid_db_login" id="hid_db_login" value="', isset($post_db_login) ? $post_db_login : '', '" />
	<input type="hidden" name="hid_db_pwd" id="hid_db_pwd" value="', isset($post_db_pwd) ? $post_db_pwd : '', '" />
	<input type="hidden" name="hid_db_port" id="hid_db_port" value="', isset($post_db_port) ? $post_db_port : '', '" />
	<input type="hidden" name="hid_db_bdd" id="hid_db_bdd" value="', isset($post_db_bdd) ? $post_db_bdd : '', '" />
	<input type="hidden" name="hid_db_pre" id="hid_db_pre" value="', isset($post_db_pre) ? $post_db_pre : '', '" />
	<input type="hidden" name="hid_absolute_path" id="hid_absolute_path" value="', isset($post_absolute_path) ? $post_absolute_path : '', '" />
	<input type="hidden" name="hid_url_path" id="hid_url_path" value="', isset($post_url_path) ? $post_url_path : '', '" />
	<input type="hidden" name="hid_sk_path" id="hid_sk_path" value="', isset($post_sk_path) ? $post_sk_path : '', '" />
	<input type="hidden" name="hid_sk_filename" id="hid_sk_filename" value="', isset($post_sk_path) ? $post_sk_path : '', '" />
	<input type="hidden" name="hid_sk_key" id="hid_sk_key" value="', isset($post_sk_path) ? $post_sk_path : '', '" />
-->

	<div class="container">
		<div class="custom-block w-100 p-4 mt-4">
			<header class="mb-5">
				<h2 class="text-center">
					<span><img src="../includes/images/teampass-logo2-home.png" /></span>
					<span class="header-title mx-2"><?php echo strtoupper(TP_TOOL_NAME);?></span>
				</h2>
			</header>
			<div class="mb-4">
				<?php
				if (empty($post_step)) {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card bg-light">
							<div class="card-body">
								<h5 class="card-title"><i class="fa-solid fa-info-circle text-info"></i>&nbsp;Welcome to Teampass installation</h5>
								<p class="card-text">This seems to be the 1st time Teampass will be installed on this server.<br>
								It will proceed with installation of release <b><?php echo TP_VERSION;?></b>.</p>
							</div>
						</div>
					</div>

					<div class="col-12 mt-3">
						<div class="card bg-light">
							<div class="card-body">
								<h5 class="card-title"><i class="fa-solid fa-exclamation-circle text-info"></i>&nbsp;Before starting, be sure to:</h5>
								<p class="card-text">									
									<ul>
										<li>upload the complete package on the server</li>
										<li>have the database connection information</li>
									</ul>
								</p>
							</div>
						</div>
					</div>
					
					<div class="col-12 mt-3">
						<div class="card bg-light">
							<div class="card-body">
								<h5 class="card-title"><i class="fa-solid fa-file-contract text-info"></i>&nbsp;License</h5>
								<p class="card-text">
									TeamPass is distributed under GNU GENERAL PUBLIC LICENSE version 3. 
									<a class="text-primary" target="_blank" href="https://spdx.org/licenses/GPL-3.0-only.html#licenseText">
										<i class="fa-solid fa-arrow-up-right-from-square"></i>
									</a>
								</p>
							</div>
						</div>
					</div>
				</div>
				<?php
				} elseif ($post_step === "1") {
					?>
				<div class="row">

					<div class="col-12">
						<div class="card bg-light">
							<div class="card-body">
								<h5 class="card-title"><i class="fa-solid fa-info-circle text-info"></i>&nbsp;Welcome to Teampass installation</h5>
								<p class="card-text">This seems to be the 1st time Teampass will be installed on this server.<br>
								It will proceed with installation of release <b><?php echo TP_VERSION;?></b>.</p>
							</div>
						</div>
					</div>

				</div>
				<?php
				}
				?>


				<div class="row mt-3 mb-4">
					<div class="col-12">
						<button type="button" class="btn btn-primary <?php echo empty($post_step) ? 'hidden'  : '' ;?>" id="button_start" >Start</button>
						<button type="button" class="btn btn-secondary" id="button_next" disabled data-step="<?php echo $post_step;?>">Continue</button>
					</div>
				</div>
			</div>
			<footer class="text-center">
				<small><?php echo TP_TOOL_NAME . ' ' . TP_VERSION;?><i class="fa-regular fa-copyright m-2"></i>2009-<?php echo date('Y');?></small>
			</footer>
		</div>
			
    </div>

</body>
</html>

<script type="text/javascript" src="../includes/js/CreateRandomString.js"></script>
<script type="text/javascript" src="js/aes.min.js"></script>
<script type="text/javascript" src="../plugins/alertifyjs/alertify.min.js"></script>
<script type="text/javascript" src="../plugins/bootstrap-5.3.3-dist/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../plugins/jquery/jquery.min.js"></script>
<script type="text/javascript" src="../plugins/jqueryUI/jquery-ui.min.js"></script>
<script type="text/javascript" src="install.js"></script>
<script type="text/javascript" src="../plugins/store.js/dist/store.everything.min.js"></script>
<!--
<script type="text/javascript" src="../includes/js/functions.js"></script>
<script type="text/javascript" src="../includes/js/CreateRandomString.js"></script>
<script type="text/javascript" src="js/aes.min.js"></script>
<script type="text/javascript" src="install.js"></script>
-->

<?php
/*



	<?php
	// define root path
	


	// Get some data
	include "../includes/config/include.php";


	// # LOADER
	echo '
    <div style="position:absolute;top:49%;left:49%;display:none;z-index:9999999;" id="loader"><img src="images/76.gif" /></div>';
	
	// # HEADER ##
	echo '
	<div id="top">
		<div id="logo" class="lcol"><img src="../includes/images/teampass-logo2-home.png" /></div>
		<div class="lcol">
			<span class="header-title">' . strtoupper(TP_TOOL_NAME) . '</span>
		</div>
		
        <div id="content">
			<form name="upgrade" method="post" action="">
			
			
			<input type="hidden" id="step" name="step" value="', isset($post_step) ? $post_step : '', '" />
			<input type="hidden" id="page_id" value="1" />
			<input type="hidden" id="step_res" value="" />
			<input type="hidden" name="hid_db_host" id="hid_db_host" value="', isset($post_db_host) ? $post_db_host : '', '" />
			<input type="hidden" name="hid_db_login" id="hid_db_login" value="', isset($post_db_login) ? $post_db_login : '', '" />
			<input type="hidden" name="hid_db_pwd" id="hid_db_pwd" value="', isset($post_db_pwd) ? $post_db_pwd : '', '" />
			<input type="hidden" name="hid_db_port" id="hid_db_port" value="', isset($post_db_port) ? $post_db_port : '', '" />
			<input type="hidden" name="hid_db_bdd" id="hid_db_bdd" value="', isset($post_db_bdd) ? $post_db_bdd : '', '" />
			<input type="hidden" name="hid_db_pre" id="hid_db_pre" value="', isset($post_db_pre) ? $post_db_pre : '', '" />
			<input type="hidden" name="hid_absolute_path" id="hid_absolute_path" value="', isset($post_absolute_path) ? $post_absolute_path : '', '" />
			<input type="hidden" name="hid_url_path" id="hid_url_path" value="', isset($post_url_path) ? $post_url_path : '', '" />
			<input type="hidden" name="hid_sk_path" id="hid_sk_path" value="', isset($post_sk_path) ? $post_sk_path : '', '" />
			<input type="hidden" name="hid_sk_filename" id="hid_sk_filename" value="', isset($post_sk_path) ? $post_sk_path : '', '" />
			<input type="hidden" name="hid_sk_key" id="hid_sk_key" value="', isset($post_sk_path) ? $post_sk_path : '', '" />
			
		    <div class="card card-default color-palette-box">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-people-carry mr-2"></i>Initial installation
                </h3>
            </div>
            <div class="card-body">';
	if (!isset($_GET['step']) && !isset($post_step)) {
		//ETAPE O
		echo '
				<div class="row">
                    <div class="callout callout-warning col-12">
                        <h5><i class="fas fa-info-circle text-warning mr-2"></i>Welcome to Teampass installation</h5>
    
                        <p>This seems to be the 1st time Teampass will be installed on this server.<br>
						It will proceed with installation of release <b>' . TP_VERSION . '</b>.</p>
                    </div>

                    <div class="callout callout-info col-12 mt-3">
                        <h5><i class="fas fa-exclamation-circle text-info mr-2"></i>Before starting, be sure to:</h5>    
                        <p>
                        <ul>
                            <li>upload the complete package on the server</li>
                            <li>have the database connection information</li>
                        </ul>
                        </p>
                    </div>
					
					<div class="callout callout-danger col-12 mt-3">
                        <h5><i class="fas fa-ruler text-danger mr-2"></i>License</h5>
    
                        <p>TeamPass is distributed under GNU GENERAL PUBLIC LICENSE version 3.</p>
						<p><a class="text-primary" target="_blank" href="https://spdx.org/licenses/GPL-3.0-only.html#licenseText">Read complete license</a>
                    </div>
                </div>';
		// STEP1
	} elseif ((isset($post_step) && $post_step == 2)
		|| (isset($_GET['step']) && $_GET['step'] == 2)
		&& $post_user_granted === '1'
	) {
		//ETAPE 1
		echo '
	<div class="row">
		<div class="col-12">
			<div class="card card-primary">
				<div class="card-header">
					<h5>Teampass instance information</h5>
				</div>
				<div class="card-body">
					<div class="form-group">
						<label>Absolute path to TeamPass folder</label>
						<input type="text" class="form-control" name="absolute_path" id="absolute_path" class="ui-widget" value="' . $abs_path . '">
					</div>
					<div class="form-group">
						<label>Full URL to TeamPass</label>
						<input type="text" class="form-control" name="url_path" id="url_path" class="ui-widget" value="' . $protocol . $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8) . '">
					</div>
					<div class="form-group">
						<label>Absolute path to secure path</label>
						<small class="form-text text-muted">
							For security reasons, the secure path shall be defined outside the WWW folder of your server (example: /var/teampass/). It will host an encryption key used for several Teampass features.
						</small>
						<input type="text" class="form-control" name="sk_path" id="sk_path" class="ui-widget" value="">
					</div>
				</div>
			</div>
		</div>
	</div>';

	echo '
	<div class="row">
		<div class="col-12">
			<div class="card card-primary">
				<div class="card-header">
					<h5>Next elements to check</h5>
				</div>
				<div class="card-body">

					<ul>
					<li>Secure folder is available&nbsp;<span id="res2_check19"></span></li>
					<li>File "/includes/config/settings.php is available&nbsp;<span id="res2_check99"></span></li>
					<li>Directory "/install/" is writable&nbsp;<span id="res2_check0"></span></li>
					<li>Directory "/includes/" is writable&nbsp;<span id="res2_check1"></span></li>
					<li>Directory "/includes/config/" is writable&nbsp;<span id="res2_check2"></span></li>
					<li>Directory "/includes/avatars/" is writable&nbsp;<span id="res2_check3"></span></li>
					<li>Directory "/includes/libraries/csrfp/libs/" is writable&nbsp;<span id="res2_check4"></span></li>
					<li>Directory "/includes/libraries/csrfp/js/" is writable&nbsp;<span id="res2_check5"></span></li>
					<li>Directory "/includes/libraries/csrfp/log/" is writable&nbsp;<span id="res2_check6"></span></li>
					<li>Directory "/files/" is writable&nbsp;<span id="res2_check17"></span></li>
					<li>Directory "/upload/" is writable&nbsp;<span id="res2_check18"></span></li>
					<li>PHP extension "mbstring" is loaded&nbsp;<span id="res2_check7"></span></li>
					<li>PHP extension "openssl" is loaded&nbsp;<span id="res2_check8"></span></li>
					<li>PHP extension "bcmath" is loaded&nbsp;<span id="res2_check9"></span></li>
					<li>PHP extension "iconv" is loaded&nbsp;<span id="res2_check10"></span></li>
					<li>PHP extension "xml" is loaded&nbsp;<span id="res2_check12"></span></li>
					<li>PHP extension "gd" is loaded&nbsp;<span id="res2_check11"></span></li>
					<li>PHP extension "curl" is loaded&nbsp;<span id="res2_check13"></span></li>
					<li>PHP extension "gmp" is loaded&nbsp;<span id="res2_check16"></span></li>
					<li>PHP version is greater or equal to '.MIN_PHP_VERSION.'&nbsp;<span id="res2_check14"></span></li>
					<li>Execution time limit&nbsp;<span id="res2_check15"></span></li>
					</ul>
					
					<div class="" id="res_step2"></div>
					<div class="" id="res_step2_error"></div>
					
				</div>
			</div>
		</div>
	</div>';
		// STEP2
	} elseif ((isset($post_step) && $post_step == 3)
		|| (isset($_GET['step']) && $_GET['step'] == 3)
		&& $post_user_granted === '1'
	) {
		//ETAPE 2
		echo '
	<div class="row">
		<div class="col-12">
			<div class="card card-primary">
				<div class="card-header">
					<h5>Database connection Information</h5>
				</div>
				<div class="card-body">
					<div class="form-group">
						<label>Host</label>
						<input type="text" class="form-control" name="db_host" id="db_host" class="ui-widget" value="">
					</div>
					
					<div class="form-group">
						<label>Database name</label>
						<input type="text" class="form-control" name="db_bdd" id="db_bdd" class="ui-widget" value="">
					</div>
					
					<div class="form-group">
						<label>Login</label>
						<input type="text" class="form-control" name="db_login" id="db_login" class="ui-widget" value="">
					</div>
					
					<div class="form-group">
						<label>Password</label>
						<input type="text" class="form-control" name="db_pw" id="db_pw" class="ui-widget" value="">
					</div>
					
					<div class="form-group">
						<label>Port</label>
						<input type="text" class="form-control" name="db_port" id="db_port" class="ui-widget" value="3306">
					</div>
				</div>
			</div>
		</div>
	</div>';

		// STEP3
	} elseif ((isset($post_step) && $post_step == 4)
		|| (isset($_GET['step']) && $_GET['step'] == 4)
		&& $post_user_granted === '1'
	) {
		//ETAPE 3
		echo '
	<div class="row">
		<div class="col-12">
			<div class="card card-primary">
				<div class="card-header">
					<h5>Teampass set-up</h5>
				</div>
				<div class="card-body">
					<div class="form-group">
						<label>Table prefix</label>
						<input type="text" class="form-control" name="tbl_prefix" id="tbl_prefix" class="ui-widget" value="teampass_"><span id="res4_check0"></span>
					</div>
		
					<div class="form-group">
						<label>Teampass Administrator password</label>
						<input type="password" class="form-control" id="admin_pwd" class="ui-widget" value=""><span id="res4_check10"></span>
					</div>
					
					<div class="form-group">
						<label>Confirm Administrator password</label>
						<input type="password" class="form-control" id="admin_pwd_confirm" class="ui-widget" value=""><span id="res4_check11"></span>
					</div>
					
					<div class="form-group">
						<label>Administrator email</label>
						<input type="text" class="form-control" id="admin_email" class="ui-widget" value=""><span id="res4_check12"></span>
					</div>
				</div>
			</div>
		</div>
	</div>';


		// STEP4
	} elseif ((isset($post_step) && $post_step == 5)
		|| (isset($_GET['step']) && $_GET['step'] == 5)
		&& $post_user_granted === '1'
	) {
		//ETAPE 4
		echo '
	<div class="row">
		<div class="col-12">
			<div class="card card-primary">
				<div class="card-header">
					<h5>Preparing database</h5>
				</div>
				<div class="card-body">
					<ul id="pop_db"></ul>
				</div>
			</div>
			<div class="card card-info hidden progress" id="step5_wip">
				Work in progress... <i class="fas fa-cog fa-spin fa-2x"></i>
			</div>
		</div>
	</div>';

		// STEP5
	} elseif ((isset($post_step) && $post_step == 6)
		|| (isset($_GET['step']) && $_GET['step'] == 6)
		&& $post_user_granted === '1'
	) {
		//ETAPE 5
		echo '
	<div class="row">
		<div class="col-12">
			<div class="card card-primary">
				<div class="card-header">
					<h5>Finalization</h5>
				</div>
				<div class="card-body">
					<ul>
						<li>Create sk.php file <span id="res6_check0"></span></li>
						<li>Chmod some folders and files <span id="res6_check1"></span></li>
						<li>Create settings files <span id="res6_check2"></span></li>
						<li>Initiate CSRF protection <span id="res6_check3"></span></li>
						<li>Add new cron job <span id="res6_check5"></span></li>
						<li>Clean temporary installation data <span id="res6_check4"></span></li>
					</ul>
				</div>
			</div>
		</div>
	</div>';

		// STEP6
	} elseif ((isset($post_step) && $post_step == 7)
		|| (isset($_GET['step']) && $_GET['step'] == 7)
		&& $post_user_granted === '1'
	) {
		//ETAPE 6
		echo '
	<div class="row">
		<div class="callout callout-primary col-12">
			<h4>Thank you for installing <b>Teampass</b>.</h4>
			<div class="card-body">
				<div class="alert alert-info">
					The final step is now to move to the authentication page and start using <b>Teampass</b>.<br>
					The Administrator login is `<b>admin</b>`.
					<br>
					Its password is the one you have written during the installation process.
				</div>
				
				<div class="alert alert- mt-2">
					<i>Please note that first page may be longer to load. Install files and folders will be deleted for security purpose.
					<br>
					In case warning "Install folder has to be removed!" is shown while login, this operation has failed and requires to be done manually.</i>
				</div>
				
				<div class="callout callout-info text-center mt-3">
					<a class="text-primary" id="link_home_page" href="../index.php">Move to home page</a>
				</div>
				
				<div class="alert alert-warning mt-8">
					For news, help and information, please visit <a href="https://teampass.net" target="_blank">TeamPass website</a>.
				</div>
			</div>
		</div>
	</div>';
	}

	echo '	
	</div>	
        <div class="card-footer">';
	//buttons
	if (!isset($post_step)) {
		echo '
            <input type="button" class="btn btn-primary" id="but_next" target_id="2" class="button" value="START" />
            <input type="button" class="btn btn-primary" id="but_start" onclick="document.location = \'' . $protocol . $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/') - 8) . '\'" class="button" style="display: none;" value="Start" />';
	} elseif ($post_step == 7) {
		// Nothong to do
	} else {
		echo '
            <input type="button" id="but_launch" onclick="checkPage(\'step' . $post_step . '\')" class="btn btn-primary" value="START" />
            <input type="button" id="but_next" target_id="' . (intval($post_step) + 1) . '" class="btn btn-primary" value="NEXT" disabled="disabled">
			<input type="button" class="btn btn-primary" id="but_restart" onclick="document.location = \'install.php\'" class="button" value="RESTART" />';
	}

	echo '
        </div>
	</form>
	</div>';

	//FOOTER
	echo '
    <div id="footer">
        <div style="width:500px; font-size:16px;">
            ' . TP_TOOL_NAME . ' ' . TP_VERSION . ' <i class="far fa-copyright"></i> copyright 2009-'.date('Y').'
        </div>
        <div style="float:right;margin-top:-15px;">
        </div>
    </div>';
	*/
	?>
