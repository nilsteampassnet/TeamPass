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
 * @file      secure_send_attachments.php
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\File;
use Defuse\Crypto\Key;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;

require_once __DIR__ . '/secure_send_item_attachments.php';

const SECURE_SEND_DOWNLOAD_SECONDS = 300;
const SECURE_SEND_MAX_ATTACHMENTS = 10;

/** Refusal specific to the authenticated Web attachment form. */
final class SecureSendAttachmentRefusal extends RuntimeException
{
}

/**
 * Store a reference to each encrypted file set until its contents have been removed.
 */
function secureSendAttachmentSchemaSql(string $table): string
{
    return 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
        reference CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        send_id INT NULL,
        created_at BIGINT UNSIGNED NOT NULL,
        download_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (reference),
        UNIQUE KEY (send_id),
        KEY (created_at)
    ) ENGINE=InnoDB';
}

/**
 * Interpret the attachment setting in megabytes, including the existing "100mb" format.
 */
function secureSendUploadLimit(array $settings): int
{
    $value = trim((string) ($settings['upload_maxfilesize'] ?? ''));
    if (preg_match('/^\d+(?:\.\d+)?\s*(?:mb)?$/iD', $value) !== 1) {
        return 0;
    }

    return (int) min(2147483647, (float) $value * 1048576);
}

/**
 * Reject unusable uploads before reserving a file set or creating a link.
 *
 * @return array{name: string, source_id: int, open: Closure}
 */
function secureSendReadUpload(UploadedFile $upload, array $settings): array
{
    if (in_array($upload->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new SecureSendAttachmentRefusal('attachment_too_large');
    }
    if (!$upload->isValid()) {
        throw new SecureSendAttachmentRefusal('invalid_attachment');
    }
    $path = $upload->getPathname();
    $size = @filesize($path);
    if ($size === false || $size <= 0) {
        throw new SecureSendAttachmentRefusal('invalid_attachment');
    }
    if ($size > secureSendUploadLimit($settings)) {
        throw new SecureSendAttachmentRefusal('attachment_too_large');
    }

    return ['name' => secureSendAttachmentName($upload->getClientOriginalName()), 'source_id' => 0,
        'open' => static fn () => @fopen($path, 'rb')];
}

/**
 * Normalize a filename for use as display text and an HTTP attachment name.
 */
function secureSendAttachmentName(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = trim((string) preg_replace('/[\x00-\x1f\x7f]/', '', $name));

    return $name === '' || $name === '.' || $name === '..' || !mb_check_encoding($name, 'UTF-8')
        ? 'document'
        : mb_strcut($name, 0, 255, 'UTF-8');
}

/**
 * Resolve the private attachment directory, rejecting public paths and symlinked subdirectories.
 */
function secureSendAttachmentDirectory(array $settings, string $reference, bool $create = false): ?string
{
    if (preg_match('/^[a-f0-9]{32}$/D', $reference) !== 1) {
        throw new InvalidArgumentException('invalid_attachment_reference');
    }
    $configured = (string) ($settings['path_to_upload_folder'] ?? '');
    $base = $configured === '' ? false : realpath($configured);
    $public = realpath(TEAMPASS_ROOT . '/public');
    if ($base === false || $public === false || !is_dir($base)) {
        throw new RuntimeException('attachment_storage_unavailable');
    }
    $normalize = static function (string $path): string {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    };
    if ($normalize($base) === $normalize($public)
        || str_starts_with($normalize($base), $normalize($public) . '/')
    ) {
        throw new RuntimeException('attachment_storage_unavailable');
    }

    $root = $base . DIRECTORY_SEPARATOR . 'secure-send';
    if (is_link($root) || ($create && !is_dir($root) && !@mkdir($root, 0700) && !is_dir($root))) {
        throw new RuntimeException('attachment_storage_unavailable');
    }
    $directory = $root . DIRECTORY_SEPARATOR . $reference;
    if (is_link($directory) || ($create && !@mkdir($directory, 0700))) {
        throw new RuntimeException('attachment_storage_unavailable');
    }

    return is_dir($directory) ? $directory : null;
}

/**
 * Encrypt the supplied files before they become reachable through a committed share.
 *
 * @param list<array{name: string, source_id: int, open: Closure}> $files
 * @return array{reference: string, manifest: list<array{name: string, size: int, key: string, source_id: int}>}
 */
function secureSendStageAttachments(array $files, array $settings): array
{
    $reference = bin2hex(random_bytes(16));
    DB::insert(prefixTable('secure_send_attachments'), ['reference' => $reference, 'created_at' => time()]);
    try {
        $directory = secureSendAttachmentDirectory($settings, $reference, true);
        $manifest = [];
        foreach ($files as $index => $file) {
            $key = Key::createNewRandomKey();
            $input = ($file['open'])();
            if (!is_resource($input)) {
                throw new SecureSendAttachmentRefusal('invalid_attachment');
            }
            $output = null;
            try {
                $size = (int) (fstat($input)['size'] ?? 0);
                if ($size <= 0) {
                    throw new SecureSendAttachmentRefusal('invalid_attachment');
                }
                $output = @fopen($directory . DIRECTORY_SEPARATOR . $index . '.enc', 'xb');
                if ($output === false) {
                    throw new RuntimeException('attachment_storage_unavailable');
                }
                File::encryptResource($input, $output, $key);
            } finally {
                if (is_resource($output)) {
                    fclose($output);
                }
                fclose($input);
            }
            $manifest[] = ['name' => $file['name'], 'size' => $size, 'source_id' => $file['source_id'],
                'key' => $key->saveToAsciiSafeString()];
        }

        return ['reference' => $reference, 'manifest' => $manifest];
    } catch (Throwable $e) {
        secureSendDiscardStagedAttachments($reference, $settings);
        throw $e;
    }
}

/**
 * Remove a private file set and then release its cleanup record.
 *
 * An interrupted or failed unlink leaves the record for maintenance to retry.
 */
function secureSendRemoveAttachmentSet(string $reference, array $settings): void
{
    $directory = secureSendAttachmentDirectory($settings, $reference);
    if ($directory !== null) {
        $files = @scandir($directory);
        if ($files === false) {
            throw new RuntimeException('attachment_cleanup_failed');
        }
        foreach ($files as $file) {
            if (preg_match('/^\d+\.enc$/D', $file) !== 1) {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            if (!@unlink($path) && (file_exists($path) || is_link($path))) {
                throw new RuntimeException('attachment_cleanup_failed');
            }
        }
        if (!@rmdir($directory) && is_dir($directory)) {
            throw new RuntimeException('attachment_cleanup_failed');
        }
    }

    DB::delete(prefixTable('secure_send_attachments'), 'reference = %s', $reference);
}

/**
 * Clean up a failed upload while retaining evidence for a maintenance retry on I/O failure.
 */
function secureSendDiscardStagedAttachments(string $reference, array $settings): void
{
    try {
        // A connection can fail while reporting a commit. Never delete a published file set.
        if (DB::queryFirstField('SELECT reference FROM ' . prefixTable('secure_send_attachments')
            . ' WHERE reference = %s AND send_id IS NULL', $reference) !== null) {
            secureSendRemoveAttachmentSet($reference, $settings);
        }
    } catch (Throwable $e) {
        error_log('TEAMPASS Secure Send staged attachment cleanup failed (' . get_class($e) . ')');
    }
}

/**
 * Delete file sets whose shares were removed, and staged sets abandoned by interrupted requests.
 *
 * Pass link IDs after a committed deletion for immediate cleanup. Maintenance retries all failures.
 *
 * @param list<int> $sendIds
 */
function secureSendPruneAttachments(array $settings, array $sendIds = []): void
{
    if (!array_key_exists('secure_send_allow_attachments', $settings)) {
        return;
    }
    try {
        $table = prefixTable('secure_send_attachments');
        $linkTable = prefixTable('otv');
        $filter = $sendIds === []
            ? 'a.send_id IS NOT NULL OR a.created_at < %i'
            : 'a.send_id IN %li';
        $candidate = 'SELECT a.reference FROM ' . $table . ' a LEFT JOIN ' . $linkTable . ' o ON o.id = a.send_id
            WHERE o.id IS NULL AND (' . $filter . ')';
        $value = $sendIds === [] ? time() - 86400 : $sendIds;
        $rows = DB::query($candidate . ' ORDER BY a.created_at LIMIT 100', $value);
        foreach ($rows as $row) {
            DB::startTransaction();
            try {
                // A request may still publish a staging record while maintenance is inspecting it.
                $reference = DB::queryFirstField($candidate . ' AND a.reference = %s FOR UPDATE', $value, $row['reference']);
                if (is_string($reference)) {
                    secureSendRemoveAttachmentSet($reference, $settings);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
                error_log('TEAMPASS Secure Send attachment cleanup failed (' . get_class($e) . ')');
            }
        }
    } catch (Throwable $e) {
        error_log('TEAMPASS Secure Send attachment maintenance failed (' . get_class($e) . ')');
    }
}

/**
 * Seal a short-lived, session-owned download grant before committing a successful reveal.
 */
function secureSendGrantDownloads(array $link, array $manifest, array $settings, int $now, int $autoDeletedAt = 0): array
{
    if (!array_is_list($manifest) || $manifest === [] || count($manifest) > SECURE_SEND_MAX_ATTACHMENTS) {
        throw new UnexpectedValueException('invalid_attachment_manifest');
    }
    $table = prefixTable('secure_send_attachments');
    $reference = DB::queryFirstField('SELECT reference FROM ' . $table . ' WHERE send_id = %i', $link['id']);
    if (!is_string($reference) || secureSendAttachmentDirectory($settings, $reference) === null) {
        // A restore can retain the link while its transient file store was intentionally excluded.
        throw new RuntimeException('attachment_storage_unavailable');
    }
    $sourceIds = [];
    foreach ($manifest as $entry) {
        if (!is_array($entry)
            || !is_string($entry['name'] ?? null)
            || !is_string($entry['key'] ?? null)
            || !is_int($entry['size'] ?? null) || $entry['size'] <= 0
            || !is_int($entry['source_id'] ?? null) || $entry['source_id'] < 0
        ) {
            throw new UnexpectedValueException('invalid_attachment_manifest');
        }
        if ($entry['source_id'] > 0) {
            $sourceIds[] = $entry['source_id'];
        }
    }
    if ($sourceIds !== []) {
        $existing = secureSendExistingItemAttachmentIds((int) $link['item_id'], $sourceIds);
        $manifest = array_filter($manifest, static fn (array $file): bool => in_array($file['source_id'], $existing, true));
    }
    if ($manifest === []) {
        return [];
    }
    $expires = min((int) $link['time_limit'], $now + SECURE_SEND_DOWNLOAD_SECONDS);
    $sealed = cryption(json_encode([
        'id' => (int) $link['id'], 'reference' => $reference, 'files' => $manifest, 'expires' => $expires,
        'auto_deleted_at' => $autoDeletedAt,
    ], JSON_THROW_ON_ERROR), '', 'encrypt', $settings);
    if (!empty($sealed['error'])) {
        throw new RuntimeException('attachment_grant_failed');
    }
    DB::query('UPDATE ' . $table . ' SET download_until = GREATEST(download_until, %i)
        WHERE send_id = %i', $expires, $link['id']);

    return [
        'token' => bin2hex(random_bytes(32)),
        'grant' => ['expires' => $expires, 'sealed' => $sealed['string']],
        'names' => array_map(static fn (array $file): string => $file['name'], $manifest),
    ];
}

/**
 * Keep an existing file grant revocable while refusing any further reveal of an unavailable item.
 */
function secureSendHasDownloadWindow(int $sendId, array $settings): bool
{
    return array_key_exists('secure_send_allow_attachments', $settings) && DB::queryFirstField(
        'SELECT send_id FROM ' . prefixTable('secure_send_attachments') . ' WHERE send_id = %i AND download_until > %i',
        $sendId, time()
    ) !== null;
}

/**
 * Authorize a download using only a grant in this session and the current state of its link.
 *
 * A recipient can retry in the same session until the grant expires, without spending a view.
 */
function secureSendAuthorizeDownload(array $input, array $grants, array $settings, string $host, int $now): ?array
{
    $token = $input['download'] ?? null;
    $index = $input['attachment'] ?? null;
    if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1
        || !is_string($index) || preg_match('/^\d{1,2}$/D', $index) !== 1
        || (int) ($settings['secure_send_allow_attachments'] ?? 0) !== 1
    ) {
        return null;
    }
    $grant = $grants[$token] ?? null;
    if (!is_array($grant) || ($grant['expires'] ?? 0) <= $now || !is_string($grant['sealed'] ?? null)) {
        return null;
    }
    $decoded = cryption($grant['sealed'], '', 'decrypt', $settings);
    if (!empty($decoded['error'])) {
        return null;
    }
    $data = json_decode($decoded['string'], true, 16, JSON_THROW_ON_ERROR);
    $file = $data['files'][(int) $index] ?? null;
    if (!is_array($file) || (int) ($data['expires'] ?? 0) <= $now) {
        return null;
    }
    $link = DB::queryFirstRow('SELECT o.* FROM ' . prefixTable('otv') . ' o
        INNER JOIN ' . prefixTable('secure_send_attachments') . ' a ON a.send_id = o.id
        WHERE o.id = %i AND a.reference = %s AND a.download_until > %i',
        $data['id'], $data['reference'], $now);
    if (!$link || !secureSendIsAvailable($link, $settings, $now, false)
        || !secureSendHostIsAllowed($settings, $link, $host)
    ) {
        return null;
    }
    if ($file['source_id'] > 0) {
        if (!secureSendCanDownloadItemFiles($link, (int) ($data['auto_deleted_at'] ?? 0))
            || secureSendExistingItemAttachmentIds((int) $link['item_id'], [$file['source_id']]) === []
        ) {
            return null;
        }
    } elseif (secureSendPublicSender($link, false) === null) {
        return null;
    }

    $directory = secureSendAttachmentDirectory($settings, $data['reference']);
    if ($directory === null) {
        return null;
    }
    $path = $directory . DIRECTORY_SEPARATOR . (int) $index . '.enc';
    if (is_link($path) || !is_file($path)) {
        return null;
    }
    $file['path'] = $path;

    return $file;
}

/**
 * Decrypt to the response as an attachment after Defuse has authenticated the ciphertext.
 */
function secureSendStreamAttachment(array $file): void
{
    $key = Key::loadFromAsciiSafeString($file['key']);
    $fileHandle = @fopen($file['path'], 'rb');
    if ($fileHandle === false) {
        throw new RuntimeException('attachment_not_found');
    }
    try {
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('attachment_output_unavailable');
        }
        try {
            header('Content-Type: application/octet-stream');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . $file['size']);
            header('Content-Disposition: ' . HeaderUtils::makeDisposition('attachment', $file['name'], 'document'));
            File::decryptResource($fileHandle, $output, $key);
        } finally {
            fclose($output);
        }
    } finally {
        fclose($fileHandle);
    }
}
