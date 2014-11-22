<?php
session_start();
require_once "../../PasswordHashClass.php";

$DB = new DB('sqlite::memory:'); // Replace with your own

if (isset($_POST['username']) && isset($_POST['password'])) {
	
	// Other validation logic can take place here
	
	$hashed = PasswordHash::create_hash($_POST['password']);
	$check = $DB->pQuery(
			"SELECT * FROM user_accounts WHERE username = ?",
			array($_POST['username'])
		);
	if (!empty($check)) {
		$_SESSION['msg'] = "Username already in use.";
		header("Location: /signup.php");
	} else {
		$res = $DB->pQuery(
				"INSERT INTO user_accounts (username, password) VALUES (?, ?);",
				array($_POST['username'], $hashed)
			);
		if($res) {
			$_SESSION['msg'] = "Your registration was successful!";
			header("Location: /login.php");
		} else {
			$_SESSION['msg'] = "An error has occurred.";
			header("Location: /signup.php");
		}
	}
	exit;
} else {
	// Just a basic HTML form
	?>
<!DOCTYPE html>
<html>
	<head>
		<title>DEMO</title>
	</head>
	<body>
		<h1>Sign Up!</h1>
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
	<?php
}
