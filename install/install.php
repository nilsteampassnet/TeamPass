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


// Get some data
include "../includes/config/include.php";
// Load functions
require_once __DIR__.'/tp.functions.php';

$superGlobal = new SuperGlobal();

// Prepare variables
$serverPath = rtrim($superGlobal->get('DOCUMENT_ROOT', 'SERVER'), '/').
	substr($superGlobal->get('PHP_SELF', 'SERVER'), 0,-20);
$serverProtocol = null !== $superGlobal->get('HTTPS', 'SERVER') ? 'https://' : 'http://';
$serverUrl = $serverProtocol . $superGlobal->get('HTTP_HOST', 'SERVER') . substr($superGlobal->get('PHP_SELF', 'SERVER'), 0, strrpos($superGlobal->get('PHP_SELF', 'SERVER'), '/') - 8);

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
	<link rel="stylesheet" href="../plugins/bootstrap/css/bootstrap.min.css" />
	<link rel="stylesheet" href="../plugins/fontawesome-free-6/css/all.min.css" type="text/css">
	<link rel="stylesheet" href="css/install.css" type="text/css" />
    
</head>

<body class="bg-light">

	<form method="POST" id="installation" action="">
		<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">	
		<input type="hidden" id="installStep" name="installStep" value="<?php echo isset($post_step) ? htmlspecialchars($post_step) : '0';?>" />
	</form>

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
				//
				// STEP 1 - PATHS AND URLS
				//
				} elseif ($post_step === "1") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Teampass instance information</h5>
							</div>
							<div class="card-body">
								<div class="form-group">
									<label>Absolute path to TeamPass folder</label>
									<input type="text" class="form-control required" data-label="Absolute path" name="absolute_path" id="absolute_path" class="ui-widget" value="<?php echo $serverPath;?>">
								</div>
								<div class="form-group mt-3">
									<label>Full URL to TeamPass</label>
									<input type="text" class="form-control required" data-label="Url" name="url_path" id="url_path" class="ui-widget" value="<?php echo $serverUrl;?>">
								</div>
								<div class="form-group mt-3">
									<label>Absolute path to secure path</label><br>
									<small class="form-text text-muted">
										For security reasons, the secure path shall be defined outside the WWW folder of your server (example: /var/teampass/). It will host an encryption key used for several Teampass features.
									</small>
									<input type="text" class="form-control required" data-label="Secure path" name="secure_path" id="secure_path" class="ui-widget" value="">
								</div>
							</div>
						</div>
					</div>
				</div>				
				<?php
				//
				// STEP 2 - CHECKING SERVER CONFIGURATION
				//
				} elseif ($post_step === "2") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Checking server settings</h5>
							</div>
							<div class="card-body">
								<div>
									<div class="alert alert-info" role="alert">
										<i class="fa-solid fa-info-circle"></i>&nbsp;Next requirements are expected to run Teampass.
									</div>
								</div>
								<ul>
									<li>Directory <code>/install/</code> is writable&nbsp;<span id="check0"></span></li>
									<li>Directory <code>/includes/</code> is writable&nbsp;<span id="check1"></span></li>
									<li>Directory <code>/includes/config/</code> is writable&nbsp;<span id="check2"></span></li>
									<li>Directory <code>/includes/avatars/</code> is writable&nbsp;<span id="check3"></span></li>
									<li>Directory <code>/includes/libraries/csrfp/libs/</code> is writable&nbsp;<span id="check4"></span></li>
									<li>Directory <code>/includes/libraries/csrfp/js/</code> is writable&nbsp;<span id="check5"></span></li>
									<li>Directory <code>/includes/libraries/csrfp/log/</code> is writable&nbsp;<span id="check6"></span></li>
									<li>Directory <code>/files/</code> is writable&nbsp;<span id="check7"></span></li>
									<li>Directory <code>/upload/</code> is writable&nbsp;<span id="check8"></span></li>
									<li>PHP extension <code>mbstring</code> is loaded&nbsp;<span id="check9"></span></li>
									<li>PHP extension <code>openssl</code> is loaded&nbsp;<span id="check10"></span></li>
									<li>PHP extension <code>bcmath</code> is loaded&nbsp;<span id="check11"></span></li>
									<li>PHP extension <code>iconv</code> is loaded&nbsp;<span id="check12"></span></li>
									<li>PHP extension <code>xml</code> is loaded&nbsp;<span id="check13"></span></li>
									<li>PHP extension <code>gd</code> is loaded&nbsp;<span id="check14"></span></li>
									<li>PHP extension <code>curl</code> is loaded&nbsp;<span id="check15"></span></li>
									<li>PHP version is greater or equal to <code><?php echo MIN_PHP_VERSION;?></code>&nbsp;<span id="check17"></span></li>
									<li>Execution time limit is at least <code>30s</code>&nbsp;<span id="check18"></span></li>
								</ul>
							</div>
						</div>
					</div>
				</div>				
				<?php

				//
				// STEP 3 - CHECKING DATABASE CONNECTION
				//
				} elseif ($post_step === "3") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Checking database connection</h5>
							</div>
							<div class="card-body">
								<div>
									<div class="alert alert-info" role="alert">
										<i class="fa-solid fa-info-circle"></i>&nbsp;Please provide the database information on which Teampass will be installed.
									</div>
								</div>
								<div class="form-group">
									<label>Host</label>
									<input type="text" class="form-control required" data-label="Host" name="db_host" id="db_host" class="ui-widget" value="">
								</div>
								
								<div class="form-group mt-3">
									<label>Database name</label>
									<input type="text" class="form-control required" data-label="Name" name="db_bdd" id="db_bdd" class="ui-widget" value="">
								</div>
								
								<div class="form-group mt-3">
									<label>Login</label>
									<input type="text" class="form-control required" data-label="Login" name="db_login" id="db_login" class="ui-widget" value="">
								</div>
								
								<div class="form-group mt-3">
									<label>Password</label>
									<input type="text" class="form-control required" data-label="Password" name="db_pw" id="db_pw" class="ui-widget" value="">
								</div>
								
								<div class="form-group mt-3">
									<label>Port</label>
									<input type="text" class="form-control required" data-label="Port" name="db_port" id="db_port" class="ui-widget" value="3306">
								</div>

								<div class="form-group mt-3">
									<label>Table prefix</label>
									<input type="text" class="form-control required" data-label="Prefix" id="tbl_prefix" class="ui-widget" value="teampass_"><span id="check0"></span>
								</div>
							</div>
						</div>
					</div>
				</div>				
				<?php

				//
				// STEP 4 - TEAMPASS SETUP
				//
				} elseif ($post_step === "4") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Creating TeamPass administrator</h5>
							</div>
							<div class="card-body">
								<div>
									<div class="alert alert-info" role="alert">
										<i class="fa-solid fa-info-circle"></i>&nbsp;Please provide some information regarding Teampass administrator.
									</div>
								</div>
					
								<div class="form-group mt-3">
									<label>Name</label>
									<div class="row">
										<div class="col-6">
											<input type="text" class="form-control required" data-label="Name" id="admin_name" class="ui-widget" value="" placeholder="Name"><span id="check1"></span>
										</div>
										<div class="col-6">
											<input type="text" class="form-control required" data-label="Lastname" id="admin_lastname" class="ui-widget" value="" placeholder="Lastname"><span id="check2"></span>
										</div>
									</div>									
								</div>
					
								<div class="form-group mt-3">
									<label>Password</label> 
									<i class="fa-solid fa-info-circle text-info ml-2" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="The new password must: 1/Contain at least 10 characters 2/Contain at least one uppercase letter and one lowercase letter 3/Contain at least one number or special character 4/Not contain your name, first name, username, or email."></i>
									<div class="row">
										<div class="col-6">
											<input type="password" class="form-control required" data-label="Password" id="admin_pwd" class="ui-widget" value="" placeholder="Password"><span id="check1"></span>
										</div>
										<div class="col-6">
											<input type="password" class="form-control required" data-label="Password confirmation" id="admin_pwd_confirm" class="ui-widget" value="" placeholder="Password confirmation"><span id="check2"></span>
										</div>
									</div>									
								</div>
								
								<div class="form-group mt-3">
									<label>Email</label>
									<input type="email" class="form-control required" data-label="Email" id="admin_email" class="ui-widget" value=""><span id="check3"></span>
								</div>
							</div>
						</div>
					</div>
				</div>				
				<?php

				//
				// STEP 5 - TEAMPASS DATABASE POPULATING
				//
				} elseif ($post_step === "5") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Populating database</h5>
							</div>
							<div class="card-body">
								<span id="step5_start_message">
									Press click <span class="badge bg-primary">Start</span> to launch the creation process.
								</span>
								<div id="step5_results_div" class="hidden">
									<div class="progress mt-2">
										<div class="progress-bar" role="progressbar" id="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
									</div>
									
									<div class="form-group mt-3 scrollable-list">
										<ul id="step5_results" class="list-unstyled"></ul>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php

				//
				// STEP 6 - FINAL STEPS
				//
				} elseif ($post_step === "6") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Final tasks</h5>
							</div>
							<div class="card-body">
								<div>
									<ul>
										<li>Create <code>Secure file</code> <span id="check0"></span></li>
										<li>Chmod <code>folders and files</code> <span id="check1"></span></li>
										<li>Create <code>settings file</code> <span id="check2"></span></li>
										<li>Initiate <code>CSRF protection</code> <span id="check3"></span></li>
										<li>Add new <code>cron job</code> <span id="check4"></span></li>
										<li>Clean <code>installation data</code> <span id="check5"></span></li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php

				//
				// STEP 7 - THANK YOU
				//
				} elseif ($post_step === "7") {
					?>
				<div class="row">
					<div class="col-12">
						<div class="card card-primary">
							<div class="card-header">
								<h5>Thank you for installing <b>Teampass</b> 👍</h5>
							</div>
							<div class="card-body">
								<div class="card-body">
									<div class="alert alert-success">
										<h5>Congratulations 🎉</h5>
										<div class="mt-3">
											<p>Next step is now to move to the authentication page and start using <b>Teampass</b>.</p>
											<p>The Administrator login is <code>admin</code>, and the password is the one you defined during the installation process.</p>
										</div>
									</div>
									
									<div class="alert alert-light mt-2">
										<i><i class="fa-solid fa-circle-exclamation"></i> Please note that first page may be longer to load. Install files and folders will be deleted for security purpose.
										<br>
										In case warning "Install folder has to be removed!" is shown while login, this operation has failed and requires to be done manually.</i>
									</div>
									
									<div class="alert alert-info mt-3">
										For news, help and information, please visit <a href="https://teampass.net" target="_blank">TeamPass website</a>.
									</div>
									
									<div class="d-grid gap-2 col-6 mx-auto mt-5">
										<a class="btn btn-primary" href="../index.php"><i class="fa-solid fa-up-right-from-square"></i> Move to Teampass home page</a>
									</div>

								</div>
							</div>
						</div>
					</div>
				</div>
				<?php
				}
				?>

				<!-- MESSAGES -->
				<div class="row mt-3 mb-4 hidden">
					<div class="col-12">
						
					</div>
				</div>

				<!-- FOOTER -->
				<div class="row mt-3 mb-4" id="buttons-div">
					<div class="col-12">
						<button type="button" class="btn btn-primary <?php echo empty($post_step) ? 'hidden'  : '' ;?>" id="button_start" data-step="<?php echo $post_step;?>">Start</button>
						<button type="button" class="btn btn-secondary" id="button_next" <?php echo empty($post_step) ? ''  : 'disabled ' ;?>data-step="<?php echo empty($post_step) ? 1 : ((int) $post_step +1 );?>">Continue</button>
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

<script type="text/javascript" src="../plugins/jquery/jquery.min.js"></script>
<script type="text/javascript" src="../plugins/jqueryUI/jquery-ui.min.js"></script>
<script type="text/javascript" src="../includes/js/CreateRandomString.js"></script>
<script type="text/javascript" src="js/aes.min.js"></script>
<script type="text/javascript" src="../plugins/alertifyjs/alertify.min.js"></script>
<script type="text/javascript" src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="../plugins/store.js/dist/store.everything.min.js"></script>
<script type="text/javascript" src="./install-steps/install.js"></script>