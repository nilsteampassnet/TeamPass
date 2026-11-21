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
 * It is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 * PURPOSE. See <https://www.gnu.org/licenses/>.
 *
 * @file      secure_send_item_attachments.php
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use TeampassClasses\CryptoManager\CryptoManager;

require_once __DIR__ . '/secure_send_access.php';

/**
 * Accept only a bounded list of distinct attachment IDs; empty means no documents selected.
 *
 * @return list<int>
 */
function secureSendItemAttachmentSelection(mixed $input): array
{
    if (!is_array($input) || !array_is_list($input) || count($input) > SECURE_SEND_MAX_ATTACHMENTS) {
        throw new SecureSendAttachmentRefusal('invalid_attachment');
    }
    $ids = [];
    foreach ($input as $id) {
        if (!is_int($id) || $id <= 0 || isset($ids[$id])) {
            throw new SecureSendAttachmentRefusal('invalid_attachment');
        }
        $ids[$id] = $id;
    }

    return array_values($ids);
}

/**
 * Build sources from confirmed files on the already-authorized item using the sender's keys.
 *
 * @param list<int> $ids Selected file IDs
 * @return list<array{name: string, source_id: int, open: Closure}>
 */
function secureSendItemAttachmentInputs(int $itemId, array $ids, array $sender, array $settings): array
{
    $rows = DB::query('SELECT f.id, f.name, f.extension, f.file, f.status, s.share_key, s.increment_id AS sharekey_id
        FROM ' . prefixTable('files') . ' f
        LEFT JOIN ' . prefixTable('sharekeys_files') . ' s ON s.object_id = f.id AND s.user_id = %i
        WHERE f.id_item = %i AND f.confirmed = 1 AND f.id IN %li ORDER BY f.id',
        (int) $sender['id'], $itemId, $ids);
    if (count($rows) !== count($ids)) {
        throw new SecureSendAttachmentRefusal('invalid_attachment');
    }
    $files = [];
    foreach ($rows as $file) {
        $extension = (string) $file['extension'];
        $name = str_replace('b64:', '', basename((string) $file['name'], '.' . $extension));
        $name = isBase64($name) ? (string) base64_decode($name, true) : $name;
        $files[] = [
            'name' => secureSendAttachmentName($name . ($extension === '' ? '' : '.' . $extension)),
            'source_id' => (int) $file['id'],
            'open' => static fn () => secureSendOpenItemAttachment($file, $sender, $settings),
        ];
    }

    return $files;
}

/**
 * Open current or legacy attachment storage and decrypt with the authorized sharekey when necessary.
 *
 * @return resource
 */
function secureSendOpenItemAttachment(array $file, array $sender, array $settings)
{
    $base = realpath((string) $settings['path_to_upload_folder']);
    $storedName = (string) $file['file'];
    $input = false;
    foreach ([$storedName, base64_decode($storedName, true)] as $name) {
        if ($base === false || !is_string($name) || $name === '' || strpbrk($name, "/\\\0") !== false) {
            continue;
        }
        $path = $base . DIRECTORY_SEPARATOR . TP_FILE_PREFIX . $name;
        if (!is_link($path) && is_file($path)) {
            $input = @fopen($path, 'rb');
            if ($input !== false) {
                break;
            }
        }
    }
    if ($input === false) {
        throw new SecureSendAttachmentRefusal('invalid_attachment');
    }
    $encrypted = $file['share_key'] !== null || in_array($file['status'], [TP_ENCRYPTION_NAME, 'encrypted'], true);
    if (!$encrypted) {
        return $input;
    }

    $output = null;
    try {
        if (empty($file['share_key'])) {
            throw new SecureSendCreateRefusal('cannot_decrypt');
        }
        $encodedKey = decryptUserObjectKeyWithMigration(
            $file['share_key'], (string) ($sender['private_key'] ?? ''), (string) ($sender['public_key'] ?? ''),
            (int) $file['sharekey_id'], 'sharekeys_files'
        );
        $key = base64_decode($encodedKey, true);
        if ($key === false || $key === '') {
            throw new SecureSendCreateRefusal('cannot_decrypt');
        }
        $output = tmpfile();
        if ($output === false) {
            throw new RuntimeException('attachment_storage_unavailable');
        }
        CryptoManager::decryptAttachmentToStream($input, $output, $key);

        return $output;
    } catch (Throwable $e) {
        if (is_resource($output)) {
            fclose($output);
        }
        throw new SecureSendCreateRefusal('cannot_decrypt');
    } finally {
        fclose($input);
    }
}

/**
 * Resolve files still confirmed on their original item, optionally under the publish lock.
 *
 * @param list<int> $ids
 * @return list<int>
 */
function secureSendExistingItemAttachmentIds(int $itemId, array $ids, bool $lock = false): array
{
    if ($itemId <= 0 || $ids === []) {
        return [];
    }

    return array_map('intval', DB::queryFirstColumn('SELECT id FROM ' . prefixTable('files')
        . ' WHERE id_item = %i AND confirmed = 1 AND id IN %li' . ($lock ? ' FOR UPDATE' : ''),
        $itemId, $ids));
}

/**
 * Recheck ACL after disclosure, allowing exactly the automatic deletion caused by this reveal.
 */
function secureSendCanDownloadItemFiles(array $link, int $autoDeletedAt): bool
{
    $itemId = (int) $link['item_id'];
    $senderId = (int) $link['originator'];
    if (secureSendReadItem($itemId, $senderId) !== []) {
        return true;
    }
    if ($autoDeletedAt <= 0) {
        return false;
    }
    $sender = secureSendReadEligibleOriginator($senderId);

    return $sender !== [] && (int) $sender['admin'] !== 1 && DB::queryFirstField(
        'SELECT i.id FROM ' . prefixTable('items') . ' i
        WHERE i.id = %i AND i.inactif = 1 AND i.deleted_at = %i AND ' . securityPostureItemAccessSql($senderId, 'i'),
        $itemId, $autoDeletedAt
    ) !== null;
}
