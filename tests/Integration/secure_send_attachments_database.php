<?php

declare(strict_types=1);

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
 * @file      secure_send_attachments_database.php
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\File;
use Defuse\Crypto\Key;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use TeampassClasses\CryptoManager\CryptoManager;

require_once __DIR__ . '/../../app/sources/secure_send_create.php';
require_once __DIR__ . '/../../app/sources/secure_send_active.php';

/** Exercise Secure Send's real SQL, file cipher and recipient grants on synthetic uploads. */
function secureSendAttachmentDatabaseChecks(array $settings): void
{
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tp-send-files-' . bin2hex(random_bytes(8));
    check(mkdir($directory, 0700), 'Could not create an isolated fixture directory');
    $file = $directory . DIRECTORY_SEPARATOR . 'fixture';
    $content = "Synthetic attachment \0\r\n" . random_bytes(16384);
    file_put_contents($file, $content);
    $name = 'R&D - O’Brien - résumé.txt';
    $upload = new UploadedFile($file, $name, null, UPLOAD_ERR_OK, true);
    $settings['path_to_upload_folder'] = $directory;
    $settings['secure_send_allow_attachments'] = 1;
    $settings['upload_maxfilesize'] = '1mb';
    $sender = ['id' => 42];
    $note = ['send_type' => 'note', 'views' => 1, 'passphrase' => 'fixture phrase'];
    $table = prefixTable('secure_send_attachments');
    DB::query(secureSendAttachmentSchemaSql($table));
    DB::query(secureSendAttachmentSchemaSql($table));
    try {
        try {
            secureSendCreateLink($note, $sender, array_replace($settings, ['upload_maxfilesize' => '0.01mb']), $upload);
            check(false, 'Upload exceeded the TeamPass setting');
        } catch (SecureSendAttachmentRefusal $e) {
            check($e->getMessage() === 'attachment_too_large', 'Wrong upload limit refusal');
        }
        try {
            secureSendCreateLink($note, $sender, array_replace($settings, ['secure_send_allow_attachments' => 0]), $upload);
            check(false, 'Upload ignored the administrator setting');
        } catch (SecureSendAttachmentRefusal $e) {
            check($e->getMessage() === 'attachments_not_allowed', 'Wrong attachment policy refusal');
        }
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . $table) === 0, 'Refused uploads left staging records');
        $created = secureSendCreateLink($note, $sender, $settings, $upload);
        $id = $created['otv_id'];
        $reference = DB::queryFirstField('SELECT reference FROM ' . $table . ' WHERE send_id = %i', $id);
        $stored = secureSendAttachmentDirectory($settings, $reference) . DIRECTORY_SEPARATOR . '0.enc';
        check(!str_contains((string) file_get_contents($stored), 'Synthetic attachment'), 'File was stored without encryption');
        parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
        $confirmations = [];
        $page = secureSendPrepareRecipient($query, 'GET', $settings, $confirmations);
        check($page['result'] === null && !str_contains(json_encode($page), $name), 'GET revealed an attachment');
        $wrong = secureSendRedeem(secureSendRequestParameters($query), 'wrong phrase', $settings);
        check($wrong === ['error' => 'wrong_passphrase'], 'Bad passphrase returned a file grant');

        $page = secureSendPrepareRecipient($query + ['confirmation' => $page['token'], 'passphrase' => $note['passphrase']],
            'POST', $settings, $confirmations);
        $result = $page['result'];
        check($result['remaining_views'] === 0 && $result['downloads']['names'] === [$name], 'Final reveal lost the attachment');
        check(!isset($result['fields']['attachments']), 'Clear file keys escaped the recipient engine');
        check(secureSendRedeem(secureSendRequestParameters($query), $note['passphrase'], $settings) === ['error' => 'invalid_link'],
            'Attachment opened another reveal after the final view');
        $sendIds = array_column(secureSendActiveLinks($settings, 42, null, null, true, true), 'id');
        check(in_array($id, array_map('intval', $sendIds), true), 'Sender cannot revoke while a final download is pending');
        $token = $result['downloads']['token'];
        $grants = [$token => $result['downloads']['grant']];
        $request = ['download' => $token, 'attachment' => '0'];
        check(secureSendAuthorizeDownload($request, [], $settings, '', time()) === null, 'A different session used the grant');
        check(secureSendAuthorizeDownload($request, $grants, $settings, '', time() + 301) === null, 'An expired grant opened a file');
        $download = secureSendAuthorizeDownload($request, $grants, $settings, '', time());
        check(is_array($download), 'The authorized final download is unavailable');
        $plaintext = $directory . DIRECTORY_SEPARATOR . 'download';
        File::decryptFile($download['path'], $plaintext, Key::loadFromAsciiSafeString($download['key']));
        check(file_get_contents($plaintext) === $content, 'The downloaded bytes differ from the uploaded bytes');
        unlink($plaintext);

        check(!secureSendRevokeLink($id, 43, $settings) && is_file($stored), 'Another user revoked the send');
        check(secureSendRevokeLink($id, 42, $settings), 'The sender could not revoke their last-view link');
        check(!is_file($stored) && (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . $table) === 0,
            'Revocation retained encrypted attachment data');
        check(secureSendAuthorizeDownload($request, $grants, $settings, '', time()) === null, 'Revocation left a usable grant');

        $created = secureSendCreateLink($note, $sender, $settings, $upload);
        // Simulate an unavailable file store during revocation, then recover through maintenance.
        check(secureSendRevokeLink($created['otv_id'], 42, array_replace($settings, ['path_to_upload_folder' => ''])),
            'Unavailable file storage prevented logical revocation');
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . $table) === 1, 'Cleanup retry lost its storage reference');
        secureSendPruneAttachments($settings);
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . $table) === 0, 'Maintenance did not retry attachment cleanup');

        $created = secureSendCreateLink($note, $sender, $settings, $upload);
        DB::update(prefixTable('otv'), ['time_limit' => time() - 1], 'id = %i', $created['otv_id']);
        secureSendPurgeExpiredLinks($settings);
        check((int) DB::queryFirstField('SELECT COUNT(*) FROM ' . $table) === 0, 'Expiration retained attachment data');
        echo "OK: Secure Send attachment size, final view, session, revocation, expiry and storage retry\n";

        secureSendItemAttachmentDatabaseChecks($settings);
    } finally {
        foreach (DB::query('SELECT reference FROM ' . $table) as $row) {
            secureSendRemoveAttachmentSet($row['reference'], $settings);
        }
        @unlink($directory . DIRECTORY_SEPARATOR . 'download');
        unlink($file);
        @rmdir($directory . DIRECTORY_SEPARATOR . 'secure-send');
        rmdir($directory);
    }
}

/** Verify item-specific selection, historic ciphertext, automatic deletion and source removal. */
function secureSendItemAttachmentDatabaseChecks(array $settings): void
{
    require_once __DIR__ . '/../../app/includes/libraries/phpseclibV1_autoload.php';
    DB::query('CREATE TABLE ' . prefixTable('files') . ' (
        id INT PRIMARY KEY, id_item INT, name TEXT, extension TEXT, file TEXT, status TEXT, confirmed INT
    ) ENGINE=InnoDB');
    foreach (['sharekeys_items', 'sharekeys_files'] as $table) {
        DB::query('CREATE TABLE ' . prefixTable($table) . ' (
            increment_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, object_id INT, share_key TEXT
        ) ENGINE=InnoDB');
    }
    DB::insert(prefixTable('items'), ['id' => 901, 'id_tree' => 7, 'label' => 'Item with documents',
        'login' => '', 'url' => '', 'description' => '', 'pw' => '']);
    DB::insert(prefixTable('sharekeys_items'), ['user_id' => 42, 'object_id' => 901, 'share_key' => 'fixture-item-key']);
    DB::insert(prefixTable('automatic_del'), ['item_id' => 901, 'del_enabled' => 1, 'del_type' => 1, 'del_value' => 1]);
    $settings['enable_delete_after_consultation'] = 1;
    $item = ['id' => 901, 'views' => 3];
    $sender = ['id' => 42];
    $sources = [];
    try {
        $key = 'fixture-key-for-two-cipher-versions';
        $name = "R&D — O'Brien \"review\"";
        $contents = [str_repeat("Current file \0\n", 26000), str_repeat("Legacy file \0\n", 28000)];
        foreach ($contents as $index => $bytes) {
            $id = 9010 + $index;
            $storageName = 'storage-' . $id;
            $path = $settings['path_to_upload_folder'] . DIRECTORY_SEPARATOR . TP_FILE_PREFIX . $storageName;
            $cipher = $index === 0 ? CryptoManager::createAESCipher('cbc') : new Crypt_AES();
            $cipher->setIV(str_repeat("\0", 16));
            $cipher->setPassword($key);
            check(file_put_contents($path, $cipher->encrypt($bytes)) !== false, 'Could not create item attachment fixture');
            $sources[] = $path;
            DB::insert(prefixTable('files'), ['id' => $id, 'id_item' => 901, 'name' => base64_encode($name . $index) . '.txt',
                'extension' => 'txt', 'file' => base64_encode($storageName), 'status' => TP_ENCRYPTION_NAME, 'confirmed' => 1]);
            DB::insert(prefixTable('sharekeys_files'), ['user_id' => 42, 'object_id' => $id, 'share_key' => base64_encode($key)]);
        }
        DB::insert(prefixTable('files'), ['id' => 9020, 'id_item' => 902, 'confirmed' => 1]);
        try {
            secureSendCreateLink($item + ['attachment_ids' => [9020]], $sender, $settings);
            check(false, 'An unrelated file was attached to the item');
        } catch (SecureSendAttachmentRefusal $e) {
            check($e->getMessage() === 'invalid_attachment', 'Wrong file-scope refusal');
        }
        DB::update(prefixTable('users'), ['admin' => 1], 'id = %i', 42);
        try {
            secureSendCreateLink($item + ['attachment_ids' => [9010]], $sender, $settings);
            check(false, 'An administrator shared a vault attachment');
        } catch (SecureSendCreateRefusal $e) {
            check($e->getMessage() === 'not_allowed', 'Wrong item-access refusal');
        } finally {
            DB::update(prefixTable('users'), ['admin' => 0], 'id = %i', 42);
        }
        $created = secureSendCreateLink($item + ['attachment_ids' => [9010, 9011]], $sender, $settings);
        parse_str((string) parse_url($created['url'], PHP_URL_QUERY), $query);
        $parameters = secureSendRequestParameters($query);
        $result = secureSendRedeem($parameters, '', $settings);
        check($result['error'] === '' && $result['downloads']['names'] === [$name . '0.txt', $name . '1.txt'],
            'Item selection lost a file or changed its displayed name');
        check(secureSendRedeem($parameters, '', $settings) === ['error' => 'invalid_link'],
            'An automatically deleted item allowed a new reveal');
        $managed = array_column(secureSendActiveLinks($settings, 42, null, null, true, true), null, 'id');
        check($managed[$created['otv_id']]['download_pending'] === true, 'The final item download cannot be revoked');

        $token = $result['downloads']['token'];
        $grants = [$token => $result['downloads']['grant']];
        foreach ($contents as $index => $bytes) {
            $request = ['download' => $token, 'attachment' => (string) $index];
            $download = secureSendAuthorizeDownload($request, $grants, $settings, '', time());
            check(is_array($download), 'The last permitted item reveal lost its file grant');
            $plain = $settings['path_to_upload_folder'] . DIRECTORY_SEPARATOR . 'download';
            File::decryptFile($download['path'], $plain, Key::loadFromAsciiSafeString($download['key']));
            check(file_get_contents($plain) === $bytes, 'Copy did not preserve the original attachment bytes');
            unlink($plain);
        }
        DB::delete(prefixTable('files'), 'id = %i', 9011);
        check(secureSendAuthorizeDownload(['download' => $token, 'attachment' => '1'], $grants, $settings, '', time()) === null,
            'A removed source file remained downloadable');
        check(secureSendAuthorizeDownload(['download' => $token, 'attachment' => '0'], $grants, $settings, '', time()) !== null,
            'Removing one file blocked the other attachment');
        check(secureSendRevokeLink($created['otv_id'], 42, $settings), 'Could not revoke item file grant');
        check(is_file($sources[0]) && is_file($sources[1]), 'Revoking a share removed a source file');
        echo "OK: Secure Send item selection, file scope, current and legacy bytes, final consultation and source removal\n";
    } finally {
        foreach ($sources as $path) {
            unlink($path);
        }
    }
}
