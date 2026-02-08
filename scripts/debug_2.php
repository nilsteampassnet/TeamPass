<?php
  require_once 'includes/config/settings.php';
  require_once 'includes/config/include.php';
  require_once 'vendor/autoload.php';
  require_once 'sources/main.functions.php';

  use Defuse\Crypto\Key;

  DB::$host = DB_HOST;
  DB::$user = DB_USER;
  DB::$password = DB_PASSWD;
  DB::$dbName = DB_NAME;
  DB::$port = DB_PORT;
  DB::$encoding = DB_ENCODING;

  $users = ["edouard.tabut", "cynthia.taillard", "guerric.vanclef"];

  $key = \Defuse\Crypto\Key::loadFromAsciiSafeString(file_get_contents(SECUREPATH."/".SECUREFILE));
  $serverSecret = $key->saveToAsciiSafeString();

  foreach ($users as $login) {
      $user = DB::queryFirstRow("SELECT login, user_derivation_seed, public_key, key_integrity_hash,
          FROM_UNIXTIME(created_at) as created, FROM_UNIXTIME(last_pw_change) as last_pw
          FROM ".prefixTable("users")." WHERE login = %s", $login);

      $seedHash = md5($user["user_derivation_seed"]);
      $pkHash = md5($user["public_key"]);

      echo "=== $login ===\n";
      echo "Created: {$user["created"]}\n";
      echo "Last PW change: {$user["last_pw"]}\n";
      echo "Seed MD5: $seedHash\n";
      echo "PK MD5: $pkHash\n";
      echo "PK first 50 chars: " . substr($user["public_key"], 0, 50) . "\n";
      echo "PK last 20 chars: [" . substr($user["public_key"], -20) . "]\n";
      echo "Stored hash: {$user["key_integrity_hash"]}\n";

      // Test avec trim
      $recalc = hash_hmac("sha256", $user["user_derivation_seed"] . $user["public_key"], $serverSecret);
      $recalcTrim = hash_hmac("sha256", trim($user["user_derivation_seed"]) . trim($user["public_key"]),
  $serverSecret);

      echo "Recalc hash: $recalc\n";
      echo "Recalc (trimmed): $recalcTrim\n";
      echo "Match: " . (hash_equals($recalc, $user["key_integrity_hash"]) ? "YES" : "NO") . "\n";
      echo "Match trimmed: " . (hash_equals($recalcTrim, $user["key_integrity_hash"]) ? "YES" : "NO") .
  "\n\n";
  }