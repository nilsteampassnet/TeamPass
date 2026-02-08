<?php
  if ($argc < 2) {
      die("Usage: php diagnose_integrity.php <login_utilisateur>\n");
  }

  $userLogin = $argv[1];

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

  echo "=== Diagnostic pour: $userLogin ===\n\n";

  $user = DB::queryFirstRow(
      'SELECT id, login, user_derivation_seed, public_key, key_integrity_hash,
              encryption_version, special, auth_type
       FROM ' . prefixTable('users') . ' WHERE login = %s',
      $userLogin
  );

  if (!$user) die("Utilisateur non trouvé!\n");

  // Load server secret
  $ascii_key = file_get_contents(SECUREPATH.'/'.SECUREFILE);
  $key = Key::loadFromAsciiSafeString($ascii_key);
  $serverSecret = $key->saveToAsciiSafeString();

  // Recalculate hash
  $recalculatedHash = hash_hmac(
      'sha256',
      $user['user_derivation_seed'] . $user['public_key'],
      $serverSecret
  );

  echo "Hash stocké:     {$user['key_integrity_hash']}\n";
  echo "Hash recalculé:  {$recalculatedHash}\n";
  echo "Match:           " . (hash_equals($recalculatedHash, $user['key_integrity_hash']) ? "OUI" : "NON") .
  "\n\n";

  if (!hash_equals($recalculatedHash, $user['key_integrity_hash'])) {
      echo "=== PROBLÈME DÉTECTÉ ===\n";
      echo "Longueur seed: " . strlen($user['user_derivation_seed']) . " (attendu: 64)\n";
      echo "Longueur pk:   " . strlen($user['public_key']) . "\n";
      echo "Longueur hash: " . strlen($user['key_integrity_hash']) . " (attendu: 64)\n";
      echo "Seed hex valide: " . (ctype_xdigit($user['user_derivation_seed']) ? "OUI" : "NON") . "\n";
  }