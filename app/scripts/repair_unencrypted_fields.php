<?php
/**
 * Teampass - Repair script for custom field encryption mismatch (issue #5161)
 *
 * Finds rows in teampass_categories_items where:
 *   - encryption_type = 'teampass_aes'  (data IS encrypted in DB)
 *   - encrypted_data  = 0               (field definition says NOT encrypted)
 *
 * For each such row the script:
 *   1. Locates a valid sharekey (tries the TP system user first, then all real users).
 *   2. Decrypts the stored value.
 *   3. Writes the plaintext back with encryption_type = 'not_set'.
 *   4. Deletes all orphaned sharekeys_fields rows for that categories_items id.
 *
 * Usage (CLI only):
 *   php scripts/repair_unencrypted_fields.php [--diagnose|--repair]
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

$rootPath = dirname(__DIR__);
require_once $rootPath . '/includes/config/settings.php';
require_once $rootPath . '/includes/config/include.php';
require_once $rootPath . '/sources/main.functions.php';

loadClasses('DB');

$options = getopt('', ['diagnose', 'repair', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
TeamPass — Repair unencrypted custom fields (issue #5161)
==========================================================

Usage: php scripts/repair_unencrypted_fields.php [OPTIONS]

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

// Fetch TP system user private key once (used as primary key source)
$tpUser = DB::queryFirstRow(
    'SELECT pk.private_key, u.public_key
     FROM ' . prefixTable('users') . ' AS u
     LEFT JOIN ' . prefixTable('user_private_keys') . ' AS pk
            ON pk.user_id = u.id AND pk.is_current = 1
     WHERE u.id = %i',
    (int) TP_USER_ID
);

foreach ($affected as $row) {
    $ciId      = (int) $row['ci_id'];
    $itemId    = (int) $row['item_id'];
    $fieldId   = (int) $row['field_id'];
    $fieldTitle = $row['field_title'];

    echo "  [item={$itemId} field={$fieldId} '{$fieldTitle}' ci_id={$ciId}]  ";

    // ------------------------------------------------------------------
    // Step 1: find a usable sharekey.
    // Try TP_USER first; if missing, iterate over real users until one works.
    // ------------------------------------------------------------------
    $objectKey    = '';
    $shareKeyUsed = null;

    // --- Try TP system user ---
    if ($tpUser !== null && !empty($tpUser['private_key'])) {
        $sk = DB::queryFirstRow(
            'SELECT share_key, increment_id
             FROM ' . prefixTable('sharekeys_fields') . '
             WHERE object_id = %i AND user_id = %i',
            $ciId,
            (int) TP_USER_ID
        );
        if ($sk !== null) {
            $candidate = decryptUserObjectKey($sk['share_key'], $tpUser['private_key']);
            if (!empty($candidate)) {
                $objectKey    = $candidate;
                $shareKeyUsed = 'TP_USER';
            }
        }
    }

    // --- Try real users if TP key failed ---
    if (empty($objectKey)) {
        $userKeys = DB::query(
            'SELECT sf.share_key, sf.increment_id, sf.user_id,
                    pk.private_key
             FROM ' . prefixTable('sharekeys_fields') . ' AS sf
             INNER JOIN ' . prefixTable('user_private_keys') . ' AS pk
                     ON pk.user_id = sf.user_id AND pk.is_current = 1
             WHERE sf.object_id = %i
               AND sf.user_id  != %i',
            $ciId,
            (int) TP_USER_ID
        );
        foreach ($userKeys as $uk) {
            if (empty($uk['private_key'])) {
                continue;
            }
            $candidate = decryptUserObjectKey($uk['share_key'], $uk['private_key']);
            if (!empty($candidate)) {
                $objectKey    = $candidate;
                $shareKeyUsed = 'user_id=' . $uk['user_id'];
                break;
            }
        }
    }

    if (empty($objectKey)) {
        echo "SKIP — no usable sharekey found (object key unrecoverable)\n";
        $noKey++;
        continue;
    }

    // ------------------------------------------------------------------
    // Step 2: decrypt the stored value.
    // ------------------------------------------------------------------
    $plaintext = doDataDecryption($row['data'], $objectKey);

    if ($plaintext === '') {
        echo "ERROR — decryption returned empty (sharekey via {$shareKeyUsed})\n";
        $errors++;
        continue;
    }

    // doDataDecryption returns base64-encoded output; decode to get actual string
    $plaintext = base64_decode($plaintext);

    if ($plaintext == false) {
        echo "ERROR — base64_decode of decrypted value failed\n";
        $errors++;
        continue;
    }

    echo "OK (sharekey via {$shareKeyUsed})";

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
echo "No key / unrecoverable: {$noKey}\n";
echo "Errors              : {$errors}\n\n";

if ($noKey > 0) {
    echo "WARNING: {$noKey} row(s) could not be decrypted because no valid sharekey was found.\n";
    echo "These field values are unrecoverable. Consider deleting them manually:\n";
    echo "  DELETE ci FROM " . prefixTable('categories_items') . " ci\n";
    echo "    INNER JOIN " . prefixTable('categories') . " c ON c.id = ci.field_id\n";
    echo "  WHERE ci.encryption_type = '" . TP_ENCRYPTION_NAME . "' AND c.encrypted_data = 0;\n\n";
}
