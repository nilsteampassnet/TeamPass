<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * This file is part of TeamPass, licensed under the GNU GPL version 3 or later.
 * @file recovery_database.php
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use TeampassClasses\PasswordManager\PasswordManager;
use TeampassClasses\CryptoManager\CryptoManager;

$root = dirname(__DIR__, 2);
$token = bin2hex(random_bytes(6));
$fixture = $root . '/_tools/drp-fixture-' . $token;
if (!is_dir($root . '/_tools')) {
    mkdir($root . '/_tools', 0700);
}
mkdir($fixture, 0700);
mkdir($fixture . '/keys', 0700);
mkdir($fixture . '/config', 0700);
mkdir($fixture . '/upload', 0700);
define('TEAMPASS_FUNCTIONS_ONLY', true);
define('TEAMPASS_SECRETS', $fixture . '/keys');
define('TEAMPASS_APP', $fixture);
define('SECUREFILE', 'fixture.key');
define('DB_PREFIX', 'recovery_');
require $root . '/app/vendor/autoload.php';
require $root . '/app/config/include.php';
require $root . '/app/sources/main.functions.php';
require $root . '/public/install/tp.functions.php';
require $root . '/public/install/install-steps/install.functions.php';
require $root . '/app/sources/recovery.functions.php';

$port = (int) getenv('TEAMPASS_RECOVERY_TEST_PORT');
$dbUser = getenv('TEAMPASS_RECOVERY_TEST_USER') ?: 'root';
$dbPassword = getenv('TEAMPASS_RECOVERY_TEST_PASSWORD') ?: '';
$admin = new mysqli('127.0.0.1', $dbUser, $dbPassword, '', $port);
$source = 'tp_drp_source_' . $token;
$target = 'tp_drp_target_' . $token;
$jobId = '';
$scenario = $argv[1] ?? 'current';
$exitCode = 0;
$published = false;
$dockerPaths = [];
try {
    $admin->query('CREATE DATABASE `' . $source . '` CHARACTER SET utf8mb4');
    $admin->query('CREATE DATABASE `' . $target . '` CHARACTER SET utf8mb4');
    DB::$host = '127.0.0.1';
    DB::$port = $port;
    DB::$user = $dbUser;
    DB::$password = $dbPassword;
    DB::$dbName = $source;
    DB::$encoding = 'utf8mb4';
    // Load only the shipped installer class, without invoking its HTTP dispatcher.
    // This evaluates trusted repository code, never an archive or a supplied configuration.
    $installerPath = $root . '/public/install/install-steps/run.step5.php';
    $code = (string) file_get_contents($installerPath);
    $code = substr($code, strpos($code, 'class DatabaseInstaller'));
    $code = str_replace('__DIR__', var_export(dirname($installerPath), true), $code);
    $code = str_replace('new PasswordManager', 'new \\TeampassClasses\\PasswordManager\\PasswordManager', $code);
    eval($code);
    foreach (installAllowedActions('step5') as $action) {
        if (in_array($action, ['utf8', 'migrateToUtf8mb4', 'checkAndSetInnoDBEngine'], true)) {
            continue;
        }
        $installer = new DatabaseInstaller(['action' => $action, 'tablePrefix' => DB_PREFIX], [
            'teampassUrl' => 'https://old.example.test', 'adminPassword' => 'Synthetic-only-Password!',
            'adminEmail' => 'admin@example.test', 'adminName' => 'Fixture', 'adminLastname' => 'Admin',
        ]);
        $result = $installer->handleAction();
        if (!$result['success']) {
            throw new RuntimeException('Installer action failed: ' . $action);
        }
    }
    $master = \Defuse\Crypto\Key::createNewRandomKey();
    file_put_contents($fixture . '/keys/fixture.key', $master->saveToAsciiSafeString());
    file_put_contents($fixture . '/config/settings.php', '<?php this archive must never execute;');
    $pair = CryptoManager::generateRSAKeyPair(2048);
    $systemPassword = bin2hex(random_bytes(20));
    $private = encryptPrivateKey($systemPassword, base64_encode($pair['privatekey']));
    DB::insert(prefixTable('users'), ['id' => TP_USER_ID, 'login' => 'TP',
        'pw' => \Defuse\Crypto\Crypto::encrypt($systemPassword, $master), 'public_key' => base64_encode($pair['publickey'])]);
    DB::insert(prefixTable('user_private_keys'), ['user_id' => TP_USER_ID, 'private_key' => $private, 'is_current' => 1]);
    $objectKey = bin2hex(random_bytes(32));
    $secret = "R&D; O'Brien \"<value>\"\n";
    $encrypted = CryptoManager::aesGcmEncrypt($secret, $objectKey);
    DB::insert(prefixTable('items'), ['id' => 1, 'label' => 'R&amp;D O&#039;Brien &quot;&lt;&gt;',
        'pw' => base64_encode($encrypted['ciphertext']), 'pw_iv' => base64_encode($encrypted['meta'])]);
    DB::insert(prefixTable('sharekeys_items'), ['object_id' => 1, 'user_id' => TP_USER_ID,
        'share_key' => encryptUserObjectKey(base64_encode($objectKey), base64_encode($pair['publickey'])), 'encryption_version' => 3]);
    // More than one verification batch, with discontinuous identifiers and a
    // historical personal item whose owner's key exists but its TP key does not.
    $itemKey = DB::queryFirstField('SELECT share_key FROM ' . prefixTable('sharekeys_items') . ' WHERE object_id=1');
    for ($index = 0; $index < 101; $index++) {
        $itemId = 10 + $index * 2;
        DB::insert(prefixTable('items'), ['id' => $itemId, 'label' => 'Synthetic batch item',
            'pw' => base64_encode($encrypted['ciphertext']), 'pw_iv' => base64_encode($encrypted['meta'])]);
        DB::insert(prefixTable('sharekeys_items'), ['object_id' => $itemId, 'user_id' => TP_USER_ID,
            'share_key' => $itemKey, 'encryption_version' => 3]);
    }
    DB::insert(prefixTable('items'), ['id' => 2, 'label' => 'Synthetic historical personal item', 'perso' => 1,
        'pw' => base64_encode($encrypted['ciphertext']), 'pw_iv' => base64_encode($encrypted['meta'])]);
    $ownerPair = CryptoManager::generateRSAKeyPair(2048);
    DB::update(prefixTable('users'), ['public_key' => base64_encode($ownerPair['publickey'])], 'id=1');
    DB::insert(prefixTable('user_private_keys'), ['user_id' => 1, 'is_current' => 1,
        'private_key' => encryptPrivateKey('Synthetic-only-Password!', base64_encode($ownerPair['privatekey']))]);
    DB::insert(prefixTable('sharekeys_items'), ['object_id' => 2, 'user_id' => 1,
        'share_key' => encryptUserObjectKey(base64_encode($objectKey), base64_encode($ownerPair['publickey'])), 'encryption_version' => 3]);
    foreach (['active' => null, 'revoked' => 1] as $state => $revokedAt) {
        DB::insert(prefixTable('api_tokens'), ['user_id' => TP_USER_ID, 'token_hash' => hash('sha256', $state),
            'purpose' => 'external_device', 'wrapped_private_key' => 'synthetic', 'salt' => 'synthetic',
            'created_at' => 1, 'revoked_at' => $revokedAt]);
    }
    DB::insert(prefixTable('device_pairings'), ['id' => str_repeat('a', 32), 'user_id' => TP_USER_ID,
        'server_url' => 'https://old.example.test', 'owner_binding' => str_repeat('b', 64),
        'session_marker' => str_repeat('c', 64), 'user_key_fingerprint' => str_repeat('d', 64),
        'web_expires_at' => 999999999, 'state' => 'approved', 'created_at' => 1,
        'expires_at' => 999999999, 'updated_at' => 1]);
    if ($scenario === 'publish-unusable') {
        DB::update(prefixTable('user_private_keys'), ['private_key' => ''], 'user_id=%i', TP_USER_ID);
    }
    DB::update(prefixTable('misc'), ['valeur' => '3.2.2.7'], 'type=%s AND intitule=%s', 'admin', 'teampass_version_full');
    $previousMaintenance = $scenario === 'upgrade' ? '1' : '0';
    foreach (['maintenance_mode' => $previousMaintenance, 'cpassman_url' => 'https://old.example.test/team',
        'favicon' => 'https://old.example.test/team/favicon.ico',
        'url_to_files_folder' => 'https://custom.example.test/files'] as $name => $value) {
        DB::update(prefixTable('misc'), ['valeur' => $value], 'type=%s AND intitule=%s', 'admin', $name);
    }
    $settings = ['path_to_files_folder' => $fixture, 'path_to_upload_folder' => $fixture . '/upload'];
    foreach (DB::query('SELECT intitule,valeur FROM ' . prefixTable('misc') . ' WHERE type=%s', 'admin') as $row) {
        $settings[$row['intitule']] = $row['valeur'];
    }
    if ($scenario === 'upgrade') {
        foreach (['webauthn_credentials', 'sharekeys_webauthn', 'user_webauthn_credentials', 'secure_send_audit', 'device_pairings'] as $table) {
            DB::query('DROP TABLE ' . prefixTable($table));
        }
        DB::query('ALTER TABLE ' . prefixTable('api_tokens') . ' DROP COLUMN purpose, DROP COLUMN pairing_id, DROP COLUMN revoked_at');
        DB::query('ALTER TABLE ' . prefixTable('api_sessions') . ' DROP COLUMN source_token_id');
        DB::update(prefixTable('misc'), ['valeur' => '3.2.2'], 'type=%s AND intitule=%s', 'admin', 'teampass_version');
    }
    // Produce real encrypted packages using the shipped backup functions.
    DB::startTransaction();
    DB::update(prefixTable('items'), ['label' => 'uncommitted caller change'], 'id=1');
    $refused = tpCreateDatabaseBackup($settings, '', ['output_dir' => $fixture]);
    if ($refused['success'] || DB::getMDB()->nested_transactions_count !== 1
        || $admin->query('SELECT label FROM `' . $source . '`.`recovery_items` WHERE id=1')->fetch_row()[0] === 'uncommitted caller change') {
        throw new RuntimeException('Backup committed or replaced a caller transaction');
    }
    DB::rollback();
    DB::query('CREATE TABLE ' . prefixTable('nontransactional_fixture') . ' (id INT) ENGINE=MyISAM');
    $refused = tpCreateDatabaseBackup($settings, '', ['output_dir' => $fixture, 'include_tables' => [prefixTable('nontransactional_fixture')]]);
    DB::query('DROP TABLE ' . prefixTable('nontransactional_fixture'));
    if ($refused['success'] || DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule=%s AND type=%s', 'maintenance_mode', 'admin') !== $previousMaintenance) {
        throw new RuntimeException('A failed nontransactional capture was accepted or retained maintenance');
    }
    // A real second connection changes the live row immediately after snapshot
    // creation. The entire package must still contain the preceding state.
    $snapshots = 0;
    $rollbacks = 0;
    $hook = DB::addHook('post_run', static function (array $query) use ($admin, $source, &$snapshots, &$rollbacks): void {
        if ($query['query'] === 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY') {
            $snapshots++;
            $admin->query('UPDATE `' . $source . "`.`recovery_items` SET label='changed after snapshot' WHERE id=1");
        }
        if ($query['query'] === 'ROLLBACK') {
            $rollbacks++;
        }
        if (str_starts_with($query['query'], 'SHOW CREATE TABLE')) {
            $maintenance = $admin->query('SELECT valeur FROM `' . $source . "`.`recovery_misc` WHERE intitule='maintenance_mode' AND type='admin'")->fetch_row()[0];
            $lock = 'tp-backup-' . substr(hash('sha256', $source . ':recovery_misc'), 0, 48);
            $locked = $admin->query("SELECT GET_LOCK('" . $lock . "',0)")->fetch_row()[0];
            if ($maintenance !== '1' || (int) $locked !== 0) {
                throw new RuntimeException('Backup did not retain maintenance and its exclusive capture lock');
            }
        }
    });
    try {
        $backup = tpCreateBackupPackage($settings, 'synthetic-backup-password', ['output_dir' => $fixture, 'include_documents' => true]);
    } finally {
        DB::removeHook('post_run', $hook);
    }
    if ($snapshots !== 1 || $rollbacks !== 1
        || DB::queryFirstField('SELECT valeur FROM ' . prefixTable('misc') . ' WHERE intitule=%s AND type=%s', 'maintenance_mode', 'admin') !== $previousMaintenance
        || DB::queryFirstField('SELECT label FROM ' . prefixTable('items') . ' WHERE id=1') !== 'changed after snapshot') {
        throw new RuntimeException('Capture nesting, snapshot or maintenance cleanup failed');
    }
    $kit = tpCreateRecoveryPackage($settings, 'synthetic-recovery-password', ['output_dir' => $fixture]);
    if (!$backup['success'] || !$kit['success']) {
        throw new RuntimeException('Fixture package creation failed');
    }
    if ($scenario === 'upgrade') {
        tpRecoveryDecrypt($backup['filepath'], $fixture . '/old.zip', 'synthetic-backup-password');
        $zip = new ZipArchive();
        $zip->open($fixture . '/old.zip');
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['teampass']['files_version'] = '3.2.2.7';
        $manifest['teampass']['schema_level'] = (string) ((int) UPGRADE_MIN_DATE - 100);
        $zip->addFromString('manifest.json', tpBackupCanonicalJson($manifest));
        $zip->close();
        $backup['filepath'] = $fixture . '/old.tpbackup';
        \Defuse\Crypto\File::encryptFileWithPassword($fixture . '/old.zip', $backup['filepath'], 'synthetic-backup-password');
    }
    $prepareInput = [
        'kit_path' => $kit['filepath'], 'kit_password' => 'synthetic-recovery-password',
        'backup_path' => $backup['filepath'], 'backup_password' => 'synthetic-backup-password',
        'connection' => ['host' => '127.0.0.1', 'user' => $dbUser, 'password' => $dbPassword, 'database' => $target, 'port' => $port],
        'url' => 'https://restored.example.test',
    ];
    if ($scenario === 'publish-docker') {
        require_once __DIR__ . '/recovery_publication.php';
        $prepared = tpRecoveryTestDockerPrepare($root, $prepareInput, $dockerPaths);
    } elseif (str_starts_with($scenario, 'http')) {
        tpRecoveryWritePrivate($fixture . '/request.json', json_encode($prepareInput, JSON_THROW_ON_ERROR));
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'tests/Integration/recovery_http.php', $fixture . '/request.json'], $root);
        $process->setTimeout(120);
        $process->run();
        unlink($fixture . '/request.json');
        if (!$process->isSuccessful() || preg_match('/PREPARED_JOB:([a-f0-9]{32})/', $process->getOutput(), $preparedMatch) !== 1) {
            throw new RuntimeException($process->getErrorOutput());
        }
        $prepared = ['id' => $preparedMatch[1]];
    } else {
        $prepared = tpRecoveryPrepare($prepareInput);
    }
    $jobId = $prepared['id'];
    $restored = $scenario === 'publish-docker' ? tpRecoveryTestDockerRun($root, $jobId) : tpRecoveryRun($jobId);
    if ($restored['status'] !== 'verified') {
        throw new RuntimeException('Restore did not reach verification');
    }
    if ($restored['encryption']['total_items'] !== 103 || $restored['encryption']['missing_tp_sharekeys'] !== 1
        || !$restored['encryption']['complete'] || isset($restored['encryption']['last_id'])
        || $restored['encryption']['checked_items'] !== ($scenario === 'publish-unusable' ? 0 : 102)) {
        throw new RuntimeException('Batched encryption diagnostics were incomplete or disclosed an item identifier');
    }
    $targetDb = new mysqli('127.0.0.1', $dbUser, $dbPassword, $target, $port);
    if ($scenario === 'upgrade') {
        $targetDb->query('SELECT * FROM recovery_webauthn_credentials LIMIT 1');
        $targetDb->query('SELECT * FROM recovery_secure_send_audit LIMIT 1');
        $targetDb->query('SELECT source_token_id FROM recovery_api_sessions LIMIT 1');
        $targetDb->query('SELECT purpose,pairing_id,revoked_at FROM recovery_api_tokens LIMIT 1');
        if ($targetDb->query("SELECT valeur FROM recovery_misc WHERE type='admin' AND intitule='device_pairing_schema'")->fetch_row()[0] !== '3') {
            throw new RuntimeException('Device pairing migration did not run during recovery');
        }
        if ($targetDb->query("SELECT valeur FROM recovery_misc WHERE type='admin' AND intitule='teampass_version'")->fetch_row()[0] !== TP_VERSION) {
            throw new RuntimeException('Upgraded version was not recorded');
        }
    }
    $restoredItem = $targetDb->query('SELECT pw,pw_iv,label FROM recovery_items WHERE id=1')->fetch_assoc();
    if (CryptoManager::aesGcmDecrypt(base64_decode($restoredItem['pw']), base64_decode($restoredItem['pw_iv']), $objectKey) !== $secret) {
        throw new RuntimeException('Restored ciphertext did not preserve the secret');
    }
    if ($restoredItem['label'] !== 'R&amp;D O&#039;Brien &quot;&lt;&gt;') {
        throw new RuntimeException('Stored label encoding changed');
    }
    $personalKey = $targetDb->query('SELECT increment_id,share_key FROM recovery_sharekeys_items WHERE object_id=2 AND user_id=1')->fetch_assoc();
    $ownerObjectKey = decryptUserObjectKeyWithMigration($personalKey['share_key'], base64_encode($ownerPair['privatekey']),
        base64_encode($ownerPair['publickey']), (int) $personalKey['increment_id'], 'sharekeys_items');
    if ($ownerObjectKey !== base64_encode($objectKey)) {
        throw new RuntimeException('The personal owner sharekey was changed despite the missing TP sharekey');
    }
    $restoredSettings = array_column($targetDb->query("SELECT intitule,valeur FROM recovery_misc WHERE type='admin'")->fetch_all(MYSQLI_ASSOC), 'valeur', 'intitule');
    if ($restoredSettings['teampass_version_full'] !== TP_VERSION . '.' . TP_VERSION_MINOR
        || (int) $targetDb->query('SELECT COUNT(*) FROM recovery_api_tokens')->fetch_row()[0] !== 0
        || (int) $targetDb->query('SELECT COUNT(*) FROM recovery_device_pairings')->fetch_row()[0] !== 0) {
        throw new RuntimeException('Restored release metadata or device credential invalidation was incorrect');
    }
    if ($restoredSettings['favicon'] !== $restoredSettings['cpassman_url'] . '/favicon.ico'
        || $restoredSettings['url_to_files_folder'] !== 'https://custom.example.test/files') {
        throw new RuntimeException('Recovery did not rebase derived URLs or changed a custom URL');
    }
    if ($scenario === 'current') {
        require_once __DIR__ . '/recovery_crypto.php';
        tpTestRecoveryCrypto($root, $source, $jobId, $targetDb);
    }
    if ($scenario === 'publish-retry') {
        require_once __DIR__ . '/recovery_publication.php';
        tpRecoveryTestPublicationRetry($root, $jobId);
    }
    if (in_array($scenario, ['publish', 'http-publish', 'publish-retry', 'publish-unusable', 'publish-docker'], true)) {
        // Publication is opt-in and must only run in a disposable copy of the code.
        if (!is_file($root . '/_tools/drp-lab-allow-activation')) {
            throw new RuntimeException('Publication test requires an explicitly disposable checkout');
        }
        $arguments = [PHP_BINARY, 'app/scripts/disaster-recovery.php', '--activate=' . $jobId];
        if ($scenario === 'publish-unusable') { $arguments[] = '--acknowledge-unusable-tp-key'; }
        $process = new \Symfony\Component\Process\Process($arguments, $root);
        $process->setTimeout(120);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }
        $published = true;
        if ($scenario === 'publish-docker') { tpRecoveryTestDockerPublication($root); }
        if ($scenario === 'publish-retry') { tpRecoveryTestPublicationReceiptRetry($root, $jobId); }
        if (file_get_contents($root . '/secrets/fixture.key') !== $master->saveToAsciiSafeString()) {
            throw new RuntimeException('Publication changed the original master key');
        }
        if (is_file($root . '/storage/recovery/' . $jobId . '/job.json')) {
            throw new RuntimeException('Sensitive staging survived successful activation');
        }
        $receiptPath = $root . '/storage/recovery/' . $jobId . '/receipt.json';
        $receipt = json_decode((string) file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
        if (!isset($receipt['permissions']['status'])) {
            throw new RuntimeException('Publication did not automatically audit permissions');
        }
        if (($receipt['encryption']['missing_tp_sharekeys'] ?? null) !== 1
            || ($scenario === 'publish-unusable' && !isset($receipt['unusable_tp_key_acknowledged_at']))) {
            throw new RuntimeException('Publication lost the encryption diagnostic or explicit acknowledgement');
        }
        $keyMode = fileperms($root . '/secrets/fixture.key');
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--check-permissions=' . $jobId], $root);
        $process->setTimeout(120);
        $process->run();
        if (!in_array($process->getExitCode(), [0, 2], true)) {
            throw new RuntimeException($process->getErrorOutput());
        }
        $receipt = json_decode((string) file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
        clearstatcache();
        if (!isset($receipt['permissions']['status']) || $receipt['status'] !== 'complete'
            || fileperms($root . '/secrets/fixture.key') !== $keyMode
            || file_get_contents($root . '/secrets/fixture.key') !== $master->saveToAsciiSafeString()) {
            throw new RuntimeException('Permission audit changed restored keys or did not persist its review');
        }
        $process = new \Symfony\Component\Process\Process([PHP_BINARY, 'app/scripts/disaster-recovery.php', '--resume=' . $jobId], $root);
        $process->setTimeout(120);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }
        if ($targetDb->query("SELECT valeur FROM recovery_misc WHERE type='admin' AND intitule='recovery_pending_checks'")->fetch_row()[0] !== '0') {
            throw new RuntimeException('Operational hold was not released');
        }
        $receipt = json_decode((string) file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
        if ($receipt['status'] !== 'resumed' || !isset($receipt['resumed_at']) || $receipt['maintenance_enabled']) {
            throw new RuntimeException('Service resume was not recorded for the final cleanup step');
        }
        if ($scenario === 'publish-retry') { tpRecoveryTestPublicationReceiptRetry($root, $jobId); }
        // A repeated acknowledgement must not rewrite separately enabled maintenance.
        $targetDb->query("UPDATE recovery_misc SET valeur='1' WHERE type='admin' AND intitule='maintenance_mode'");
        $process->run();
        $repeated = json_decode((string) file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
        if (!$process->isSuccessful() || $repeated['resumed_at'] !== $receipt['resumed_at'] || !$repeated['maintenance_enabled']) {
            throw new RuntimeException('Repeated resume changed the acknowledgement or separately enabled maintenance');
        }
        $legacy = $receipt;
        $legacy['status'] = 'complete';
        unset($legacy['resumed_at']);
        file_put_contents($receiptPath, json_encode($legacy, JSON_THROW_ON_ERROR));
        $process->run();
        $repaired = json_decode((string) file_get_contents($receiptPath), true, 32, JSON_THROW_ON_ERROR);
        if (!$process->isSuccessful() || $repaired['status'] !== 'resumed' || !isset($repaired['resumed_at'])
            || !$repaired['maintenance_enabled']) {
            throw new RuntimeException('Legacy complete receipt was not repaired after prior service resume');
        }
        echo "OK: isolated publication, original master key, private staging cleanup, permission audit and idempotent service resume\n";
    }
    echo "OK: real encrypted backup, kit, SQL import, original keys and cryptographic verification\n";
} catch (Throwable $exception) {
    fwrite(STDERR, ($exception instanceof TeamPassRecoveryException ? $exception->reason : $exception->getMessage()) . "\n");
    $exitCode = 1;
} finally {
    if ($published) {
        // Only files created by this opt-in fixture in the explicitly disposable checkout.
        foreach (['app/config/settings.php', 'secrets/fixture.key', 'app/includes/libraries/csrfp/libs/csrfp.config.php'] as $relative) {
            unlink($root . '/' . $relative);
        }
    }
    foreach ($dockerPaths as $path) {
        if (is_link($path) || is_file($path)) { unlink($path); }
    }
    if ($jobId !== '') {
        tpRecoveryRemoveJobFiles($jobId);
    }
    // Names are generated above and are never provided by a user.
    $admin->query('DROP DATABASE `' . $source . '`');
    $admin->query('DROP DATABASE `' . $target . '`');
    $admin->close();
}
exit($exitCode);
