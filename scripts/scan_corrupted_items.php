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
 * @return array{count:int, items:array<int, array<string, mixed>>, truncated:bool, limit:int}
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
        'SELECT sk.object_id, sk.share_key, sk.encryption_version, i.label, i.pw, i.pw_len, i.created_at, i.updated_at
         FROM ' . prefixTable('sharekeys_items') . ' sk
         JOIN ' . prefixTable('items') . ' i ON i.id = sk.object_id
         WHERE sk.user_id = %i AND i.deleted_at IS NULL
         ORDER BY sk.object_id',
        TP_USER_ID
    );

    $corrupted = array();
    $ok = 0;

    foreach ($tpSharekeys as $row) {
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
                    'updated_at_human' => date('Y-m-d H:i:s', (int) ($row['updated_at'] ?? 0)),
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
                    'updated_at_human' => date('Y-m-d H:i:s', (int) ($row['updated_at'] ?? 0)),
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
                    'updated_at_human' => date('Y-m-d H:i:s', (int) ($row['updated_at'] ?? 0)),
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
                'updated_at_human' => date('Y-m-d H:i:s', (int) ($row['updated_at'] ?? 0)),
            );

            if (count($corrupted) >= $limit) {
                break;
            }
        }
    }

    return array(
        'count' => count($corrupted),
        'items' => $corrupted,
        'truncated' => count($corrupted) >= $limit,
        'limit' => $limit,
        'ok_count' => $ok,
    );
}

// CLI entrypoint
if (php_sapi_name() === 'cli' && (isset($argv[0]) && basename((string) $argv[0]) === basename(__FILE__))) {
    $limit = 2000;
    $result = tpScanCorruptedItemsViaTpUser($limit);

    echo 'Items corrompus: ' . $result['count'] . PHP_EOL;
    foreach ($result['items'] as $c) {
        $reason = (string) ($c['reason'] ?? '');
        if ($reason === 'exception' && isset($c['exception_message'])) {
            $reason .= ': ' . (string) $c['exception_message'];
        }
        echo '  id=' . str_pad((string) ($c['id'] ?? 0), 6)
            . ' len_stored=' . str_pad((string) ($c['len_stored'] ?? 0), 4)
            . ' len_actual=' . str_pad((string) ($c['len_actual'] ?? 0), 4)
            . ' reason=' . $reason
            . ' [' . substr((string) ($c['label'] ?? ''), 0, 50) . ']'
            . PHP_EOL;
    }
}
