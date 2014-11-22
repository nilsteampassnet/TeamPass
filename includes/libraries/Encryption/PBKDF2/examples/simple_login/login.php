<?php
session_start();
require_once "../../PasswordHashClass.php";
$DB = new DB('sqlite::memory:'); // Replace with your own

if (isset($_POST['username']) && isset($_POST['password'])) {
	$result = $DB->pQuery("SELECT * FROM user_accounts WHERE username = ?", $_POST['username']);
	if (!empty($result)) {
		$user =& $result[0];
		if (PasswordHash::validate_password($_POST['password'], $user['password'])) {
			// Replace with your application logic
			die("LOGIN SUCCESS");
		}
	}
	// Replace with your application logic
	die("LOGIN FAILURE");
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>DEMO</title>
	</head>
	<body>
		<h1>Login</h1>
		<?php if (isset($_SESSION['msg'])) {
			echo "<p>" . htmlentities($_SESSION['msg'], ENT_QUOTES, 'UTF-8') . "</p>\n";
			unset($_SESSION['msg']);
		} ?>
		<form method="post">
			<label for="username">Username</label>
			<input type="text" name="username" id="username" />
			<br />
			<label for="password">Username</label>
			<input type="password" name="password" id="password" />
			<br />
			<button type="submit">Log In</button>
		</form>
	</body>
</html>
