<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$rootPath = dirname(__DIR__);
require_once $rootPath . '/app/config/settings.php';
require_once $rootPath . '/app/config/include.php';
require_once $rootPath . '/sources/main.functions.php';
loadClasses('DB');

// Decrypt TP_USER private key
$tpUser = DB::queryFirstRow('SELECT pw FROM ' . prefixTable('users') . ' WHERE id=%i', TP_USER_ID);
$tpPwdResult = cryption($tpUser['pw'], '', 'decrypt');
$tpPassword = $tpPwdResult['string'];
$tpPk = DB::queryFirstRow('SELECT private_key FROM ' . prefixTable('user_private_keys') . ' WHERE user_id=%i AND is_current=1', TP_USER_ID);
$tpPrivateKeyB64 = decryptPrivateKey($tpPassword, $tpPk['private_key']);

$tpSharekeys = DB::query(
    'SELECT sk.object_id, sk.share_key, sk.encryption_version, i.label, i.pw, i.pw_len, i.created_at, i.updated_at
     FROM ' . prefixTable('sharekeys_items') . ' sk
     JOIN ' . prefixTable('items') . ' i ON i.id = sk.object_id
     WHERE sk.user_id = %i AND i.deleted_at IS NULL
     ORDER BY sk.object_id',
    TP_USER_ID
);

echo 'Scan ' . count($tpSharekeys) . " items via TP_USER...\n\n";
$corrupted = [];
$ok = 0;

foreach ($tpSharekeys as $row) {
    try {
        $itemKey = decryptUserObjectKey($row['share_key'], $tpPrivateKeyB64);
        if (empty($itemKey)) {
            $corrupted[] = ['id' => $row['object_id'], 'label' => $row['label'], 'reason' => 'empty_key', 'len_stored' => $row['pw_len'], 'len_actual' => 0, 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
            continue;
        }

        $decryptedB64 = doDataDecryption($row['pw'], $itemKey);
        if (empty($decryptedB64) && !empty($row['pw'])) {
            $corrupted[] = ['id' => $row['object_id'], 'label' => $row['label'], 'reason' => 'decrypt_failed', 'len_stored' => $row['pw_len'], 'len_actual' => 0, 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
            continue;
        }

        $plaintext = base64_decode((string)$decryptedB64);
        $storedLen = (int)$row['pw_len'];
        $actualLen = strlen($plaintext);

        // Detect corruption: non-printable bytes OR significant length mismatch
        $hasBinaryBytes = !empty($plaintext) && !mb_check_encoding($plaintext, 'UTF-8');
        $lenMismatch = ($storedLen > 0 && abs($storedLen - $actualLen) > 2);

        if ($hasBinaryBytes || $lenMismatch) {
            $corrupted[] = ['id' => $row['object_id'], 'label' => $row['label'], 'len_stored' => $storedLen, 'len_actual' => $actualLen, 'reason' => $hasBinaryBytes ? 'binary_bytes' : 'len_mismatch', 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
        } else {
            $ok++;
        }
    } catch (Exception $e) {
        $corrupted[] = ['id' => $row['object_id'], 'label' => $row['label'], 'reason' => 'exception: ' . $e->getMessage(), 'len_stored' => $row['pw_len'], 'len_actual' => 0, 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
    }
}

echo "Items OK       : $ok\n";
echo 'Items corrompus: ' . count($corrupted) . "\n\n";
foreach ($corrupted as $c) {
    echo '  id=' . str_pad((string)$c['id'], 6) . ' len_stored=' . str_pad((string)$c['len_stored'], 4) . ' len_actual=' . str_pad((string)$c['len_actual'], 4) . ' reason=' . str_pad($c['reason'], 20) . ' [' . substr($c['label'], 0, 50) . "] ". ' created=' . str_pad((string)$c['len_stored'], 4) . "\n";
}
