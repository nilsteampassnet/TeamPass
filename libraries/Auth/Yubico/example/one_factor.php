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

<body onLoad="document.login.key.focus();">

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
        <h4>Demo YubiKey only</h4>

<?php include 'authenticate.php';
if ($authenticated == 0) { ?>
    <h1 class="ok">Congratulations <?php if ($realname) { print "$realname!"; }?></h1>
    <p>You have been successfully authenticated with the YubiKey.
<?php } else { ?>
    <ol style="list-style-position: outside;">
    <li>Place your YubiKey in the USB-port.</li>
    <li>Touch YubiKey button.</li>
    </ol>
    <br />

<?php if ($authenticated > 0) { ?>
        <h1 class="fail">Login failure. Please try again. </h1>
<?php } ?>

    <form name="login" method="post" style="border: 1px solid #e5e5e5; background-color: #f1f1f1; padding: 10px; margin: 0px; font-size:12px;"
    onSubmit="key.value = (key.value).toLowerCase(); return true;">
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td>
                    <b>YubiKey</b>
            </td>
            <td>
                  <input autocomplete="off" type="text" name="key" class="yubiKeyInput">
            </td>
        </tr>
    </table>
    </form>

<?php } ?>

    <br /><br />
    <p>&raquo; <a href="one_factor.php">Try again</a></p>
    <p>&raquo; <a href="two_factor.php">Demo YubiKey + password</a></p>
    <p>&raquo; <a href="two_factor_legacy.php">Demo YubiKey + username/password</a></p>
    <p>&raquo; <a href="admin.php">Set username/password for Demo</a></p>
    <p>&raquo; <a href="./">Back to main page</a></p>
    <br /><br /><br /><br /><br />

<?php if ($authenticated >= 0) { ?>
    <h3>Technical details</h3>
    More information about the performed transcaction:
    <br /><br />
    <?php include 'debug.php';
} ?>

</div>
</body>
</html>
