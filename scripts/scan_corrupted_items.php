<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      scan_corrupted_items.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009 - 2026 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

/**
 * Scan corrupted items using TP_USER (on-demand, callable from TeamPass Health).
 *
 * This script can be:
 * - executed in CLI mode (prints a human readable report),
 * - included from TeamPass sources (exposes tpScanCorruptedItemsViaTpUser()).
 */

// Block direct web access
if (php_sapi_name() !== 'cli' && (isset($_SERVER['SCRIPT_FILENAME']) && basename((string) $_SERVER['SCRIPT_FILENAME']) === basename(__FILE__))) {
    http_response_code(404);
    exit;
}

/**
 * Scan items via TP_USER sharekeys and detect corrupted password blobs.
 *
 * @param int $limit Maximum number of corrupted items to return (to protect memory/session size).
 *
 * @return array{count:int, items:array<int, array<string, mixed>>, ok_count:int, summary:array<string, mixed>}
 */
function tpScanCorruptedItemsViaTpUser(int $limit = 2000): array
{
    if ($limit < 1) {
        $limit = 1;
    }

    // In include mode, TeamPass has already loaded config/classes.
    // In CLI mode, load TeamPass bootstrap.
    if (php_sapi_name() === 'cli' && function_exists('loadClasses') === false) {
        $rootPath = dirname(__DIR__);
        require_once $rootPath . '/includes/config/settings.php';
        require_once $rootPath . '/includes/config/include.php';
        require_once $rootPath . '/sources/main.functions.php';
        loadClasses('DB');
    }

    if (defined('TP_USER_ID') === false) {
        throw new RuntimeException('TP_USER_ID is not defined.');
    }

    // Decrypt TP_USER private key
    $tpUser = DB::queryFirstRow('SELECT pw FROM ' . prefixTable('users') . ' WHERE id=%i', TP_USER_ID);
    if (is_array($tpUser) === false || isset($tpUser['pw']) === false) {
        throw new RuntimeException('Unable to load TP_USER password.');
    }

    $tpPwdResult = cryption((string) $tpUser['pw'], '', 'decrypt');
    $tpPassword = (string) ($tpPwdResult['string'] ?? '');

    $tpPk = DB::queryFirstRow(
        'SELECT private_key FROM ' . prefixTable('user_private_keys') . ' WHERE user_id=%i AND is_current=1',
        TP_USER_ID
    );
    if (is_array($tpPk) === false || isset($tpPk['private_key']) === false) {
        throw new RuntimeException('Unable to load TP_USER private key.');
    }

    $tpPrivateKeyB64 = decryptPrivateKey($tpPassword, (string) $tpPk['private_key']);
    if ($tpPrivateKeyB64 === '') {
        throw new RuntimeException('Failed to decrypt TP_USER private key. Check master encryption key.');
    }

    $tpSharekeys = DB::query(
        'SELECT sk.object_id, sk.share_key, sk.encryption_version, i.label, i.perso, i.pw, i.pw_len, i.created_at, i.updated_at,
                (SELECT MAX(l.date) FROM ' . prefixTable('log_items') . ' l
                 WHERE l.id_item = sk.object_id AND l.action = \'at_password_shown\') AS last_shown
         FROM ' . prefixTable('sharekeys_items') . ' sk
         JOIN ' . prefixTable('items') . ' i ON i.id = sk.object_id
         WHERE sk.user_id = %i AND i.deleted_at IS NULL
         ORDER BY sk.object_id',
        TP_USER_ID
    );

    $corrupted = array();
    $ok = 0;

    foreach ($tpSharekeys as $row) {
        $createdAtHuman  = date('Y-m-d H:i:s', (int) ($row['created_at'] ?? 0));
        $updatedAtHuman  = date('Y-m-d H:i:s', (int) ($row['updated_at'] ?? 0));
        $lastShownHuman  = $row['last_shown'] !== null
            ? date('Y-m-d H:i:s', (int) $row['last_shown'])
            : 'never';

        try {
            // Intentionally using decryptUserObjectKey() (not decryptUserObjectKeyWithMigration())
            // because this is a read-only diagnostic tool: we must not write to the DB during a scan.
            // v1 sharekeys are still decryptable via the built-in fallback.
            $itemKey = decryptUserObjectKey((string) $row['share_key'], $tpPrivateKeyB64);
            if ($itemKey === '') {
                $corrupted[] = array(
                    'id' => (int) $row['object_id'],
                    'label' => (string) $row['label'],
                    'reason' => 'empty_key',
                    'len_stored' => (int) $row['pw_len'],
                    'len_actual' => 0,
                    'created_at_human' => $createdAtHuman,
                    'updated_at_human' => $updatedAtHuman,
                    'is_personal' => (int) ($row['perso'] ?? 0),
                    'scope' => (int) ($row['perso'] ?? 0) === 1 ? 'personal' : 'shared',
                    'last_shown_human' => $lastShownHuman,
                );
                continue;
            }

            $decryptedB64 = doDataDecryption((string) $row['pw'], $itemKey);
            if ($decryptedB64 === '' && (string) $row['pw'] !== '') {
                $corrupted[] = array(
                    'id' => (int) $row['object_id'],
                    'label' => (string) $row['label'],
                    'reason' => 'decrypt_failed',
                    'len_stored' => (int) $row['pw_len'],
                    'len_actual' => 0,
                    'created_at_human' => $createdAtHuman,
                    'updated_at_human' => $updatedAtHuman,
                    'is_personal' => (int) ($row['perso'] ?? 0),
                    'scope' => (int) ($row['perso'] ?? 0) === 1 ? 'personal' : 'shared',
                    'last_shown_human' => $lastShownHuman,
                );
                continue;
            }

            $plaintext = base64_decode((string) $decryptedB64, true);
            if ($plaintext === false) {
                $plaintext = '';
            }

            $storedLen = (int) $row['pw_len'];
            $actualLen = strlen($plaintext);

            // Detect corruption: binary bytes OR significant length mismatch
            $hasBinaryBytes = $plaintext !== '' && mb_check_encoding($plaintext, 'UTF-8') === false;
            $lenMismatch = $storedLen > 0 && abs($storedLen - $actualLen) > 2;

            if ($hasBinaryBytes || $lenMismatch) {
                $corrupted[] = array(
                    'id' => (int) $row['object_id'],
                    'label' => (string) $row['label'],
                    'reason' => $hasBinaryBytes ? 'binary_bytes' : 'len_mismatch',
                    'len_stored' => $storedLen,
                    'len_actual' => $actualLen,
                    'created_at_human' => $createdAtHuman,
                    'updated_at_human' => $updatedAtHuman,
                    'is_personal' => (int) ($row['perso'] ?? 0),
                    'scope' => (int) ($row['perso'] ?? 0) === 1 ? 'personal' : 'shared',
                    'last_shown_human' => $lastShownHuman,
                );
            } else {
                $ok++;
            }

            if (count($corrupted) >= $limit) {
                break;
            }
        } catch (Exception $e) {
            $corrupted[] = array(
                'id' => (int) $row['object_id'],
                'label' => (string) $row['label'],
                'reason' => 'exception',
                'exception_message' => $e->getMessage(),
                'len_stored' => (int) $row['pw_len'],
                'len_actual' => 0,
                'created_at_human' => $createdAtHuman,
                'updated_at_human' => $updatedAtHuman,
                'last_shown_human' => $lastShownHuman,
            );

            if (count($corrupted) >= $limit) {
                break;
            }
        }
    }

    $total = count($corrupted) + $ok;

    // Breakdown by reason
    $byReason = [];
    $neverShown = 0;
    $createdTimestamps = [];
    foreach ($corrupted as $c) {
        $r = (string) ($c['reason'] ?? 'unknown');
        $byReason[$r] = ($byReason[$r] ?? 0) + 1;
        if (($c['last_shown_human'] ?? 'never') === 'never') {
            $neverShown++;
        }
        if (!empty($c['created_at_human']) && $c['created_at_human'] !== '1970-01-01 00:00:00') {
            $createdTimestamps[] = $c['created_at_human'];
        }
    }
    arsort($byReason);

    sort($createdTimestamps);
    $oldestCorrupted = count($createdTimestamps) > 0 ? $createdTimestamps[0] : null;
    $newestCorrupted = count($createdTimestamps) > 0 ? end($createdTimestamps) : null;

    $summary = array(
        'total_scanned'     => $total,
        'ok_count'          => $ok,
        'corrupted_count'   => count($corrupted),
        'corruption_rate'   => $total > 0 ? round(count($corrupted) / $total * 100, 2) : 0.0,
        'by_reason'         => $byReason,
        'never_shown'       => $neverShown,
        'oldest_corrupted'  => $oldestCorrupted,
        'newest_corrupted'  => $newestCorrupted,
        'truncated'         => count($corrupted) >= $limit,
        'limit'             => $limit,
    );

    return array(
        'count'   => count($corrupted),
        'items'   => $corrupted,
        'ok_count' => $ok,
        'summary' => $summary,
    );
}

// CLI entrypoint
if (php_sapi_name() === 'cli' && (isset($argv[0]) && basename((string) $argv[0]) === basename(__FILE__))) {
    $limit = 2000;
    $result = tpScanCorruptedItemsViaTpUser($limit);
    $s = $result['summary'];

    $sep = str_repeat('─', 60);

    // ── Corrupted items detail ─────────────────────────────────────────────
    if ($s['corrupted_count'] > 0) {
        echo 'Detail:' . PHP_EOL;
        foreach ($result['items'] as $c) {
            $reason = (string) ($c['reason'] ?? '');
            if ($reason === 'exception' && isset($c['exception_message'])) {
                $reason .= ': ' . (string) $c['exception_message'];
            }
            echo '  id=' . str_pad((string) ($c['id'] ?? 0), 6)
                . ' len_stored=' . str_pad((string) ($c['len_stored'] ?? 0), 4)
                . ' len_actual=' . str_pad((string) ($c['len_actual'] ?? 0), 4)
                . ' created=' . ($c['created_at_human'] ?? '?')
                . ' last_shown=' . str_pad(($c['last_shown_human'] ?? 'never'), 19)
                . ' reason=' . $reason
                . ' [' . substr((string) ($c['label'] ?? ''), 0, 40) . ']'
                . PHP_EOL;
        }
        echo PHP_EOL;
    }

    // ── Summary ────────────────────────────────────────────────────────────
    echo $sep . PHP_EOL;
    echo '  TEAMPASS SCAN SUMMARY' . PHP_EOL;
    echo $sep . PHP_EOL;
    echo sprintf('  Items scanned   : %d', $s['total_scanned']) . PHP_EOL;
    echo sprintf('  OK              : %d', $s['ok_count']) . PHP_EOL;
    echo sprintf('  Corrupted       : %d  (%.2f %%)', $s['corrupted_count'], $s['corruption_rate']) . PHP_EOL;
    echo PHP_EOL;

    if (!empty($s['by_reason'])) {
        echo '  Breakdown by reason:' . PHP_EOL;
        foreach ($s['by_reason'] as $reason => $cnt) {
            echo sprintf('    %-20s %d', $reason, $cnt) . PHP_EOL;
        }
        echo PHP_EOL;
    }

    echo sprintf('  Never shown      : %d  (among corrupted)', $s['never_shown']) . PHP_EOL;
    echo sprintf('  Oldest corrupted : %s', $s['oldest_corrupted'] ?? 'n/a') . PHP_EOL;
    echo sprintf('  Newest corrupted : %s', $s['newest_corrupted'] ?? 'n/a') . PHP_EOL;

    if ($s['truncated']) {
        echo PHP_EOL . '  /!\ Results truncated to ' . $s['limit'] . ' items.' . PHP_EOL;
    }

    echo $sep . PHP_EOL;

    // ── Reason explanations and fixes ─────────────────────────────────────
    if (!empty($s['by_reason'])) {
        /** @var array<string, array{explanation: string, fix: string}> $reasonDocs */
        $reasonDocs = [
            'empty_key' => [
                'explanation' => 'The sharekey for this item could not be decrypted with TP_USER\'s private key,'
                    . ' resulting in an empty object key. This typically means the sharekey entry is'
                    . ' corrupted, missing, or was encrypted with a different key pair.',
                'fix'         => 'Re-generate sharekeys for the affected items via the TeamPass admin panel'
                    . ' (Admin > Utilities > Re-encrypt) or by running the background re-encryption task.',
            ],
            'decrypt_failed' => [
                'explanation' => 'The object key was recovered successfully but the item\'s encrypted password'
                    . ' blob could not be decrypted with it. The ciphertext is likely corrupted or was'
                    . ' encrypted with a different object key than the one stored in the sharekey.',
                'fix'         => 'The encrypted blob is unrecoverable without the original object key.'
                    . ' If a backup exists from before the corruption occurred, restore the `pw` column'
                    . ' for these item IDs. Otherwise, the password must be re-entered manually.',
            ],
            'binary_bytes' => [
                'explanation' => 'Decryption succeeded but the resulting plaintext contains non-UTF-8 bytes,'
                    . ' indicating the decrypted data is garbage. This can happen when an AES SHA-256'
                    . ' decryption attempt produces a valid PKCS#7 padding by chance on SHA-1 ciphertext'
                    . ' (false positive, ~0.4% probability), or when the stored ciphertext is truncated.',
                'fix'         => 'Same as `decrypt_failed`: the plaintext cannot be recovered from the'
                    . ' current ciphertext. Restore from backup or re-enter the password manually.'
                    . ' To prevent future occurrences, ensure the phpseclib v3 migration has completed'
                    . ' (Admin > Background tasks).',
            ],
            'len_mismatch' => [
                'explanation' => 'Decryption succeeded and the plaintext is valid UTF-8, but its length'
                    . ' differs significantly from the value stored in `pw_len`. This is usually caused'
                    . ' by the `xss_clean()` call inside `doDataEncryption()` stripping or altering'
                    . ' characters (e.g. HTML tags, special chars) before encryption, so `pw_len` reflects'
                    . ' the original input while the stored ciphertext encodes the sanitised version.',
                'fix'         => 'This is generally a cosmetic issue, the stored password may be usable'
                    . ' as-is. Verify the actual decrypted value in the UI. If the password is correct,'
                    . ' simply re-save it to update `pw_len`. If the value is wrong, re-enter the password.',
            ],
            'exception' => [
                'explanation' => 'An unexpected PHP exception was thrown during sharekey or ciphertext'
                    . ' decryption. See the exception message in the detail list above for the exact'
                    . ' error. Common causes: malformed base64, invalid PEM key, missing PHP extension.',
                'fix'         => 'Investigate the specific exception message. For base64/PEM errors,'
                    . ' the sharekey or private key record in the database may be truncated — check'
                    . ' column length limits. For missing extensions, ensure `sodium` and `openssl`'
                    . ' are enabled in php.ini.',
            ],
        ];

        echo PHP_EOL;
        echo $sep . PHP_EOL;
        echo '  REASON EXPLANATIONS & SUGGESTED FIXES' . PHP_EOL;
        echo $sep . PHP_EOL;

        foreach (array_keys($s['by_reason']) as $reason) {
            $doc = $reasonDocs[$reason] ?? null;
            echo PHP_EOL . '  [' . strtoupper($reason) . ']' . PHP_EOL;
            if ($doc !== null) {
                echo '  Explanation:' . PHP_EOL;
                foreach (explode("\n", wordwrap('    ' . $doc['explanation'], 76, "\n    ")) as $line) {
                    echo $line . PHP_EOL;
                }
                echo '  Fix:' . PHP_EOL;
                foreach (explode("\n", wordwrap('    ' . $doc['fix'], 76, "\n    ")) as $line) {
                    echo $line . PHP_EOL;
                }
            } else {
                echo '  No documentation available for this reason code.' . PHP_EOL;
            }
        }

        echo PHP_EOL . $sep . PHP_EOL;
    }
}
