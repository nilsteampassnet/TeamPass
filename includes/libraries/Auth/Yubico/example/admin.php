<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta http-equiv="Cache-Control" content="no-cache, must-revalidate">
    <title></title>
    <link rel="stylesheet" type="text/css" href="style.css" />

    <style type="text/css">
    <!--
    -->
    </style>
</head>
</head>

<body onLoad="document.login.username.focus();">

      <div id="stripe">
      &nbsp;
      </div>

      <div id="container">

        <div id="logoArea">
           <img src="yubicoLogo.gif" alt="yubicoLogo" width="150" height="75"/>
        </div>

        <div id="greenBarContent">
            <div id="greenBarImage">
                <img src="yubikey.jpg" alt="yubikey" width="150" height="89"/>
            </div>
            <div id="greenBarText">
                <h3>Basic Login Demo</h3>
            </div>
        </div>
        <div id="bottomContent">
        <h4>Set username/password for demo</h4>
<?php include 'authenticate.php';
if (preg_match("<[^a-zA-Z0-9_!%&/()=-]>", $password) ||
    preg_match("<[^a-zA-Z0-9_!%&/()=-]>", $username)) {
  $authenticated = 100;
}
if ($authenticated == 0) {

  $query  = sprintf ("DELETE FROM demoserver WHERE id='%s'",
             pg_escape_string ($identity));
  pg_query($query) or die('Error, admin delete failed: ' . pg_last_error());
  $query  = sprintf ("INSERT INTO demoserver (id, username, password) values ('%s', '%s', '%s')",
             pg_escape_string ($identity),
             pg_escape_string ($username),
             pg_escape_string ($password));
  pg_query($query) or die('Error, admin insert failed: ' . pg_last_error());
 ?>

        <h1 class="ok">Congratulations <?php if ($realname) { print "$realname!"; }?></h1>
        You have successfully set the username/password to use with the Demo server.
        <p>&raquo; <a href="two_factor.php">Demo YubiKey + password</a></p>
        <p>&raquo; <a href="two_factor_legacy.php">Demo YubiKey + username/password</a></p>
        <p>&raquo; <a href="./">Back to main page</a></p>
<?php } else { ?>

    <ol>
        <li>Place your YubiKey in the USB-port.</li>
        <li>Enter Username and Password.</li>
        <li>Touch YubiKey button.</li>
    </ol>
<br>

<?php if ($authenticated > 0) { ?>
<h1 class="fail">
Authentication failure. Please try again. </h1><br>
<?php } ?>

        <form name="login" method="post" style="border: 1px solid #e5e5e5; background-color: #f1f1f1; padding: 10px; margin: 0px;"
        onSubmit="key.value = (key.value).toLowerCase(); return true;">
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td width="150">
                        <b>Username</b>
                </td>
                <td width="470">
                      <input autocomplete="off" type="text" name="username">
                </td>
            </tr>
            <tr>
                <td colspan=2>&nbsp;</td>
            </tr>
            <tr>
                <td width="150">
                        <b>Password</b>
                </td>
                <td width="470">
                      <input autocomplete="off" type="password" name="password">
                </td>
            </tr>
            <tr>
                <td colspan=2>&nbsp;</td>
            </tr>

            <tr>
                <td width="150">
                        <b>YubiKey</b>
                </td>
                <td width="470">
                     <input autocomplete="off" type="text" name="key" class="yubiKeyInput">
                     <input type="hidden" name="mode" value="admin"><input type="submit" value="Go" style="border: 0px; font-size: 0px; background: none; padding: 0px; margin: 0px; width: 0px; height: 0px;" />
                </td>
            </tr>
        </table>
    </form>
    <p>&raquo; <a href="two_factor.php">Demo YubiKey + password</a></p>
    <p>&raquo; <a href="two_factor_legacy.php">Demo YubiKey + username/password</a></p>
    <p>&raquo; <a href="./">Back to main page</a></p>
<?php } ?>
</div>
</body>
</html>
