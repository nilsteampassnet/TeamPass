<?php
require_once 'Auth/Yubico.php';
include 'config.php';

$username = $_REQUEST["username"];
$password = $_REQUEST["password"];
$mode = $_REQUEST["mode"];
$key = $_REQUEST["key"];
$passwordkey = $_REQUEST["passwordkey"];

# Quit early on no input
if (!$key && !$passwordkey) {
  $authenticated = -1;

  return;
 }

# Prepare passwordkey using password and key variables
if (($password && $key) && !$passwordkey) {
  $passwordkey = $password . ':' . $key;
}

# Convert passwordkey fields into password + key variables
if ($passwordkey) {
  $ret = Auth_Yubico::parsePasswordOTP($passwordkey);
} else {
  $ret = Auth_Yubico::parsePasswordOTP($key);
}

if (!$ret) {
  $authenticated = 31;

  return;
}

$identity = $ret['prefix'];
$key = $ret['otp'];

# Check OTP
$yubi = new Auth_Yubico($CFG[__CLIENT_ID__], $CFG[__CLIENT_KEY__]);
$auth = $yubi->verify($key);
if (PEAR::isError($auth)) {
  $authenticated = 1;

  return;
 } else {
  $authenticated = 0;
 }

# Fetch realname
$dbconn = pg_connect($CFG[__PGDB__])
  or error_log('Could not connect: ' . pg_last_error());
if (!$dbconn) {
  $authenticated = 2;

  return;
 }

# Admin mode doesn't need realname or username/password-checking
if ($mode == "admin") {
  return;
 }

$query  = sprintf ("SELECT username FROM demoserver WHERE id='%s'",
           pg_escape_string($identity));
$result = pg_query($query);
if ($result) {
  $row = pg_fetch_row($result);
  if ($row[0]) {
    $realname = $row[0];
  }
 }

# Check password (two-factor)
if ($passwordkey) {
  $query  = sprintf ("SELECT password FROM demoserver WHERE id='%s'",
             pg_escape_string($identity));
  $result = pg_query($query);
  if ($result) {
    $row = pg_fetch_row($result);
    if ($row[0]) {
      $db_password = $row[0];
    }
  }

  if ($db_password == $ret['password']) {
    $authenticated = 0;
  } else {
    $authenticated = 4;

    return;
  }
 }

# Check username (two-factor legacy)
if ($mode == "legacy") {
  if ($realname == $username) {
    $authenticated = 0;
  } else {
    $authenticated = 5;

    return;
  }
 }
