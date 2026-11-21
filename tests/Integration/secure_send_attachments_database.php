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
