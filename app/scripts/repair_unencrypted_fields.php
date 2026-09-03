<?php
/**
 * Teampass - Repair script for custom field encryption mismatch (issue #5161)
 *
 * Finds rows in teampass_categories_items where:
 *   - encryption_type = 'teampass_aes'  (data IS encrypted in DB)
 *   - encrypted_data  = 0               (field definition says NOT encrypted)
 *
 * For each such row the script:
 *   1. Locates the TeamPass internal account sharekey - the only key usable outside
 *      a web session, every other private key being wrapped with its owner password.
 *   2. Decrypts the stored value.
 *   3. Writes the plaintext back with encryption_type = 'not_set'.
 *   4. Deletes all orphaned sharekeys_fields rows for that categories_items id.
 *
 * A row the script cannot open is never a lost value: it is reported with the users
 * who still hold a sharekey, and Tools > Restore missing sharekeys rebuilds the rest.
 *
 * Usage (CLI only):
 *   php app/scripts/repair_unencrypted_fields.php [--diagnose|--repair]
 *
 * Options:
 *   --diagnose   Show affected rows without making any change (default).
 *   --repair     Decrypt and fix the affected rows in the database.
 *
 * @file      repair_unencrypted_fields.php
 * @author    Nils Laumaille (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

$rootPath = dirname(__DIR__); // application root: <install>/app
require_once $rootPath . '/config/settings.php';
require_once $rootPath . '/config/include.php';
require_once $rootPath . '/sources/main.functions.php';

loadClasses('DB');

$options = getopt('', ['diagnose', 'repair', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
TeamPass — Repair unencrypted custom fields (issue #5161)
==========================================================

Usage: php app/scripts/repair_unencrypted_fields.php [OPTIONS]

Options:
  --diagnose   List affected rows without making changes (default)
  --repair     Decrypt and fix affected rows in the database
  --help       Show this help

HELP;
    exit(0);
}

$doRepair = isset($options['repair']);

echo "\n=== TeamPass — Custom field encryption repair (issue #5161) ===\n";
echo "Mode: " . ($doRepair ? "REPAIR (writes to DB)" : "DIAGNOSE (read-only)") . "\n\n";

// ------------------------------------------------------------------
// Find all categories_items rows that are encrypted in the DB
// but whose field definition says "not encrypted".
// ------------------------------------------------------------------
$affected = DB::query(
    'SELECT ci.id          AS ci_id,
            ci.item_id     AS item_id,
            ci.field_id    AS field_id,
            ci.data        AS data,
            ci.data_iv     AS data_iv,
            ci.encryption_type AS encryption_type,
            c.encrypted_data   AS encrypted_data,
            c.title            AS field_title
     FROM ' . prefixTable('categories_items') . ' AS ci
     INNER JOIN ' . prefixTable('categories') . ' AS c ON c.id = ci.field_id
     WHERE ci.encryption_type = %s
       AND c.encrypted_data   = 0',
    TP_ENCRYPTION_NAME
);

$total     = count($affected);
$fixed     = 0;
$noKey     = 0;
$errors    = 0;

if ($total === 0) {
    echo "No inconsistent rows found. Nothing to do.\n\n";
    exit(0);
}

echo "Found {$total} row(s) with encryption_type='" . TP_ENCRYPTION_NAME . "' but encrypted_data=0.\n\n";

// Fetch the TP system user private key once. It is the only key a CLI script can
// use: every other private key is AES-wrapped with its owner's password, which
// only ever exists in that user's web session.
$tpUser = DB::queryFirstRow(
    'SELECT u.pw, u.public_key, pk.private_key
     FROM ' . prefixTable('users') . ' AS u
     LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk
            ON pk.user_id = u.id AND pk.is_current = 1
     WHERE u.id = %i',
    (int) TP_USER_ID
);

// The stored private key is encrypted with the account password, which is itself
// sealed with the application master key. Unwrapping both is what makes the key
// usable - feeding the raw column to decryptUserObjectKey() never decrypts
// anything and reports every row as unrecoverable (#5342).
$tpPrivateKey = '';
if ($tpUser !== null && empty($tpUser['private_key']) === false) {
    $tpPasswordClear = cryption((string) ($tpUser['pw'] ?? ''), '', 'decrypt', []);
    $tpPrivateKey = decryptPrivateKey(
        (string) ($tpPasswordClear['string'] ?? ''),
        (string) $tpUser['private_key']
    );
}

if (empty($tpPrivateKey) === true) {
    echo "WARNING: the TeamPass internal account key could not be unlocked.\n";
    echo "         No row can be repaired from the command line until this is fixed.\n\n";
}

/**
 * Names the users who still hold a sharekey for a field value. A row this script
 * cannot open is not a lost value as long as somebody can still read it: any key
 * holder can re-save the item, and Tools > Restore missing sharekeys rebuilds the
 * rest from there.
 *
 * @param int $ciId categories_items row id
 * @return string Human readable summary of the remaining key holders
 */
$describeHolders = static function (int $ciId): string {
    // Deleted and technical accounts are skipped: nobody can log in as them to
    // re-save the item, so naming them would send the administrator nowhere.
    $holders = DB::queryFirstColumn(
        'SELECT DISTINCT u.login
         FROM ' . prefixTable('sharekeys_fields') . ' AS sf
         INNER JOIN ' . prefixTable('users') . ' AS u ON u.id = sf.user_id
         WHERE sf.object_id = %i AND sf.share_key != "" AND u.deleted_at IS NULL
           AND u.id NOT IN %li
         ORDER BY u.login ASC',
        $ciId,
        [(int) TP_USER_ID, (int) OTV_USER_ID, (int) SSH_USER_ID, (int) API_USER_ID]
    );

    if (count($holders) === 0) {
        return 'no user holds a sharekey either';
    }

    $shown = array_slice($holders, 0, 5);
    $more = count($holders) - count($shown);

    return 'still readable by: ' . implode(', ', $shown) . ($more > 0 ? " (+{$more} more)" : '');
};

foreach ($affected as $row) {
    $ciId      = (int) $row['ci_id'];
    $itemId    = (int) $row['item_id'];
    $fieldId   = (int) $row['field_id'];
    $fieldTitle = $row['field_title'];

    echo "  [item={$itemId} field={$fieldId} '{$fieldTitle}' ci_id={$ciId}]  ";

    // ------------------------------------------------------------------
    // Step 1: find a usable sharekey. Only the TP system user qualifies here -
    // see the comment on $tpPrivateKey above.
    // ------------------------------------------------------------------
    $objectKey    = '';
    $shareKeyUsed = 'TP_USER';

    if (empty($tpPrivateKey) === false) {
        $sk = DB::queryFirstRow(
            'SELECT share_key, increment_id
             FROM ' . prefixTable('sharekeys_fields') . '
             WHERE object_id = %i AND user_id = %i',
            $ciId,
            (int) TP_USER_ID
        );
        if ($sk !== null) {
            $objectKey = decryptUserObjectKey((string) $sk['share_key'], $tpPrivateKey);
        }
    }

    if (empty($objectKey)) {
        echo 'SKIP — no server-side key; ' . $describeHolders($ciId) . "\n";
        $noKey++;
        continue;
    }

    // ------------------------------------------------------------------
    // Step 2: decrypt the stored value.
    // ------------------------------------------------------------------
    // An authenticated decryption that yields an empty string is a success, not a
    // failure: the field simply holds an empty value (#5342).
    $decrypted = doDataDecryptionWithStatus($row['data'], $objectKey, (string) ($row['data_iv'] ?? ''));

    if ($decrypted['success'] === false) {
        echo "ERROR — the stored value cannot be decrypted with the {$shareKeyUsed} key; "
            . $describeHolders($ciId) . "\n";
        $errors++;
        continue;
    }

    // doDataDecryption returns base64-encoded output; decode to get actual string
    $plaintext = (string) base64_decode($decrypted['string']);

    echo 'OK (sharekey via ' . $shareKeyUsed . ($plaintext === '' ? ', value is empty' : '') . ')';

    if (!$doRepair) {
        echo " [DRY RUN — no change written]\n";
        $fixed++;
        continue;
    }

    // ------------------------------------------------------------------
    // Step 3 (repair only): write plaintext back + clean sharekeys.
    // ------------------------------------------------------------------
    try {
        DB::startTransaction();

        DB::update(
            prefixTable('categories_items'),
            [
                'data'            => $plaintext,
                'data_iv'         => '',
                'encryption_type' => 'not_set',
            ],
            'id = %i',
            $ciId
        );

        // Delete all sharekeys for this field value — no longer needed
        DB::delete(
            prefixTable('sharekeys_fields'),
            'object_id = %i',
            $ciId
        );

        DB::commit();
        echo " — FIXED\n";
        $fixed++;
    } catch (Exception $e) {
        DB::rollback();
        echo " — DB ERROR: " . $e->getMessage() . "\n";
        $errors++;
    }
}

// ------------------------------------------------------------------
// Summary
// ------------------------------------------------------------------
echo "\n--- Summary ---\n";
echo "Total affected rows : {$total}\n";
if ($doRepair) {
    echo "Fixed               : {$fixed}\n";
} else {
    echo "Would fix           : {$fixed}\n";
    echo "(Re-run with --repair to apply changes)\n";
}
echo "No usable server-side key: {$noKey}\n";
echo "Errors              : {$errors}\n\n";

if ($noKey > 0) {
    echo "NOTE: {$noKey} row(s) have no key this script can use. That does NOT mean the\n";
    echo "      values are lost - only that they cannot be repaired from the command line.\n";
    echo "      Rebuild the missing keys from the web interface, as an administrator:\n";
    echo "        Tools > Restore missing sharekeys > Analyze, then Repair\n";
    echo "      Rows listed above with key holders can also be recovered by asking one of\n";
    echo "      them to open the item and save it again.\n";
    echo "      Never delete these rows before one of those two paths has been tried.\n\n";
}
