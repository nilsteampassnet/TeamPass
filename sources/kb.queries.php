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
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      kb.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

require_once 'main.functions.php';

loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();
$antiXss = new AntiXSS();

$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars((string) $request->request->get('type', $request->query->get('type', '')), ENT_QUOTES, 'UTF-8'),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key', 'SESSION'), null),
    ]
);

echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->checkSession() === false
    || !isset($SETTINGS['enable_kb'])
    || (int) $SETTINGS['enable_kb'] !== 1
) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function kbAccessibleFolders(SessionInterface $session): array
{
    $folders = $session->get('user-accessible_folders');
    if (!is_array($folders)) {
        return [];
    }

    return array_values(array_unique(array_map('intval', $folders)));
}

function kbIsAdmin(SessionInterface $session): bool
{
    return (int) $session->get('user-admin') === 1;
}

function kbBuildAuthorLabel(?string $login): string
{
    return trim((string) $login);
}

function kbCanEdit(array $kb, SessionInterface $session): bool
{
    return (int) ($kb['author_id'] ?? 0) === (int) $session->get('user-id')
        || (int) ($kb['anyone_can_modify'] ?? 0) === 1;
}

function kbCanDelete(array $kb, SessionInterface $session): bool
{
    return (int) ($kb['author_id'] ?? 0) === (int) $session->get('user-id');
}

function kbGenerateToken(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        return str_replace('.', '', uniqid('kb', true));
    }
}

function kbBuildUniqueMiscIntitule(string $prefix, int $kbId = 0): string
{
    return $prefix . '_' . $kbId . '_' . str_replace('.', '', (string) microtime(true)) . '_' . kbGenerateToken();
}

function kbInsertMiscRow(string $type, string $intitulePrefix, array $payload, int $createdAt, int $kbId = 0): void
{
    $attempt = 0;
    do {
        try {
            DB::insert(
                prefixTable('misc'),
                [
                    'type' => $type,
                    'intitule' => kbBuildUniqueMiscIntitule($intitulePrefix, $kbId),
                    'valeur' => kbMiscEncode($payload),
                    'created_at' => $createdAt,
                ]
            );
            return;
        } catch (Throwable $exception) {
            $attempt++;
            if ($attempt >= 5 || strpos($exception->getMessage(), 'uniq_misc_type_intitule') === false) {
                throw $exception;
            }
            usleep(1000);
        }
    } while ($attempt < 5);
}

function kbMiscEncode(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function kbMiscDecode(string $payload): array
{
    $decoded = json_decode($payload, true);

    return is_array($decoded) ? $decoded : [];
}

function kbAttachmentBaseDirectory(array $SETTINGS): string
{
    $basePath = trim((string) ($SETTINGS['path_to_files_folder'] ?? ''));
    if ($basePath === '') {
        $basePath = trim((string) ($SETTINGS['path_to_upload_folder'] ?? ''));
    }
    if ($basePath === '') {
        return '';
    }

    $dir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kb_attachments';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function kbAttachmentSanitizeFilename(string $filename): string
{
    $filename = basename($filename);
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'file';
    return $filename === '' ? 'file' : $filename;
}


function kbNormalizeExtensionsList(string $extensions): array
{
    $normalized = [];
    foreach (explode(',', strtolower($extensions)) as $extension) {
        $extension = trim($extension);
        $extension = ltrim($extension, '.');
        if ($extension !== '') {
            $normalized[$extension] = $extension;
        }
    }

    return array_values($normalized);
}

function kbGetAllowedAttachmentExtensions(array $SETTINGS): array
{
    $extensions = [];
    foreach (['upload_docext', 'upload_imagesext', 'upload_pkgext', 'upload_otherext'] as $settingKey) {
        if (isset($SETTINGS[$settingKey]) === true) {
            foreach (kbNormalizeExtensionsList((string) $SETTINGS[$settingKey]) as $extension) {
                $extensions[$extension] = $extension;
            }
        }
    }

    return array_values($extensions);
}

function kbAttachmentExtensionIsAllowed(string $filename, array $SETTINGS): bool
{
    if ((int) ($SETTINGS['upload_all_extensions_file'] ?? 0) === 1) {
        return true;
    }

    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '') {
        return false;
    }

    return in_array($extension, kbGetAllowedAttachmentExtensions($SETTINGS), true);
}

function kbBuildAllowedAttachmentExtensionsLabel(array $SETTINGS): string
{
    $extensions = kbGetAllowedAttachmentExtensions($SETTINGS);
    if (empty($extensions) === true) {
        return '';
    }

    return implode(', ', array_map(static function (string $extension): string {
        return '.' . $extension;
    }, $extensions));
}

function kbAttachmentAcceptAttribute(array $SETTINGS): string
{
    if ((int) ($SETTINGS['upload_all_extensions_file'] ?? 0) === 1) {
        return '';
    }

    $extensions = kbGetAllowedAttachmentExtensions($SETTINGS);
    if (empty($extensions) === true) {
        return '';
    }

    return implode(',', array_map(static function (string $extension): string {
        return '.' . $extension;
    }, $extensions));
}

function kbAttachmentIntitule(int $kbId, string $attachmentId): string
{
    return 'kb_' . $kbId . '_' . $attachmentId;
}

function kbLoadAllAttachmentRows(): array
{
    return DB::query(
        'SELECT increment_id, intitule, valeur, created_at
        FROM ' . prefixTable('misc') . '
        WHERE type = %s
        ORDER BY created_at DESC, increment_id DESC',
        'kb_attachment'
    );
}

function kbLoadAttachmentRowsForKbId(int $kbId): array
{
    $rows = kbLoadAllAttachmentRows();
    $filtered = [];
    foreach ($rows as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        if ((int) ($payload['kb_id'] ?? 0) === $kbId) {
            $filtered[] = $row;
        }
    }

    return $filtered;
}

function kbFindAttachmentRowById(string $attachmentId): ?array
{
    foreach (kbLoadAllAttachmentRows() as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        if ((string) ($payload['attachment_id'] ?? '') === $attachmentId) {
            $row['payload'] = $payload;
            return $row;
        }
    }

    return null;
}

function kbSerializeAttachments(int $kbId): array
{
    $attachments = [];
    foreach (kbLoadAttachmentRowsForKbId($kbId) as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        $attachments[] = [
            'id' => (string) ($payload['attachment_id'] ?? ''),
            'name' => (string) ($payload['original_name'] ?? ''),
            'size' => (int) ($payload['size'] ?? 0),
            'uploaded_at' => (int) ($payload['uploaded_at'] ?? ($row['created_at'] ?? 0)),
        ];
    }

    return $attachments;
}

function kbDeleteAttachmentFileAndRow(array $row, array $SETTINGS): void
{
    $payload = isset($row['payload']) && is_array($row['payload']) ? $row['payload'] : kbMiscDecode((string) ($row['valeur'] ?? ''));
    $directory = kbAttachmentBaseDirectory($SETTINGS);
    $storedName = (string) ($payload['stored_name'] ?? '');
    if ($directory !== '' && $storedName !== '') {
        $filePath = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    DB::delete(prefixTable('misc'), 'increment_id = %i', (int) $row['increment_id']);
}

function kbPurgeAttachmentsForKbIds(array $kbIds, array $SETTINGS): void
{
    if (empty($kbIds)) {
        return;
    }

    foreach (kbLoadAllAttachmentRows() as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        if (in_array((int) ($payload['kb_id'] ?? 0), $kbIds, true)) {
            kbDeleteAttachmentFileAndRow($row + ['payload' => $payload], $SETTINGS);
        }
    }
}

function kbReassignAttachmentsToKbId(int $oldKbId, int $newKbId): void
{
    if ($oldKbId === $newKbId) {
        return;
    }

    foreach (kbLoadAllAttachmentRows() as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        if ((int) ($payload['kb_id'] ?? 0) !== $oldKbId) {
            continue;
        }

        $payload['kb_id'] = $newKbId;
        $attachmentId = (string) ($payload['attachment_id'] ?? kbGenerateToken());
        DB::update(
            prefixTable('misc'),
            [
                'intitule' => kbAttachmentIntitule($newKbId, $attachmentId),
                'valeur' => kbMiscEncode($payload),
                'updated_at' => time(),
            ],
            'increment_id = %i',
            (int) $row['increment_id']
        );
    }
}

function kbFilterAllowedItemIds(array $itemIds, SessionInterface $session): array
{
    $filteredIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn (int $id): bool => $id > 0)));
    if (empty($filteredIds)) {
        return [];
    }

    $folders = kbAccessibleFolders($session);
    if (empty($folders)) {
        return [];
    }

    $rows = DB::query(
        'SELECT id
        FROM ' . prefixTable('items') . '
        WHERE inactif = %i
            AND id IN %li
            AND id_tree IN %li',
        0,
        $filteredIds,
        $folders
    );

    return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
}

function kbGetDescriptionHtml(string $description): string
{
    return nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));
}

function kbLoadRow(int $kbId): ?array
{
    $row = DB::queryFirstRow(
        'SELECT k.id,
            k.category_id,
            k.label,
            k.description,
            k.author_id,
            k.anyone_can_modify,
            c.category AS category,
            u.login AS author_login
        FROM ' . prefixTable('kb') . ' AS k
        LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON (c.id = k.category_id)
        LEFT JOIN ' . prefixTable('users') . ' AS u ON (u.id = k.author_id)
        WHERE k.id = %i',
        $kbId
    );

    return $row === null ? null : $row;
}

function kbLoadAssociatedItems(int $kbId, SessionInterface $session, string $baseUrl): array
{
    $rows = DB::query(
        'SELECT i.id, i.label, i.id_tree
        FROM ' . prefixTable('kb_items') . ' AS ki
        INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = ki.item_id)
        WHERE ki.kb_id = %i
            AND i.inactif = %i
        ORDER BY i.label ASC',
        $kbId,
        0
    );

    $accessibleFolders = kbAccessibleFolders($session);
    $items = [];

    foreach ($rows as $row) {
        $treeId = (int) ($row['id_tree'] ?? 0);
        if (kbIsAdmin($session) === false && !in_array($treeId, $accessibleFolders, true)) {
            continue;
        }

        $items[] = [
            'id' => (int) $row['id'],
            'label' => (string) $row['label'],
            'tree_id' => $treeId,
            'link' => rtrim($baseUrl, '/') . '/index.php?page=items&group=' . $treeId . '&id=' . (int) $row['id'],
        ];
    }

    return $items;
}

function kbBuildEntryPayload(array $kb, SessionInterface $session, string $baseUrl): array
{
    $associatedItems = kbLoadAssociatedItems((int) $kb['id'], $session, $baseUrl);

    return [
        'id' => (int) $kb['id'],
        'label' => (string) ($kb['label'] ?? ''),
        'description' => (string) ($kb['description'] ?? ''),
        'description_html' => kbGetDescriptionHtml((string) ($kb['description'] ?? '')),
        'category' => (string) ($kb['category'] ?? ''),
        'author' => kbBuildAuthorLabel(isset($kb['author_login']) ? (string) $kb['author_login'] : ''),
        'author_id' => (int) ($kb['author_id'] ?? 0),
        'anyone_can_modify' => (int) ($kb['anyone_can_modify'] ?? 0),
        'items_count' => count($associatedItems),
        'associated_items' => $associatedItems,
        'attachments' => kbSerializeAttachments((int) $kb['id']),
        'can_edit' => kbCanEdit($kb, $session),
        'can_delete' => kbCanDelete($kb, $session),
    ];
}

function kbInsertLog(array $SETTINGS, SessionInterface $session, int $kbId, string $label, string $action, string $reason = ''): void
{
    $createdAt = time();
    kbInsertMiscRow(
        'kb_log',
        'kb_log_' . $action,
        [
            'kb_id' => $kbId,
            'label' => $label,
            'action' => $action,
            'reason' => $reason,
            'user_id' => (int) $session->get('user-id'),
            'user_login' => (string) $session->get('user-login'),
            'date' => $createdAt,
        ],
        $createdAt,
        $kbId
    );
}

function kbTrashEntry(array $kb, array $associatedItems, SessionInterface $session): void
{
    $createdAt = time();
    kbInsertMiscRow(
        'kb_deleted',
        'kb_deleted',
        [
            'original_id' => (int) ($kb['id'] ?? 0),
            'label' => (string) ($kb['label'] ?? ''),
            'description' => (string) ($kb['description'] ?? ''),
            'category' => (string) ($kb['category'] ?? ''),
            'author_id' => (int) ($kb['author_id'] ?? 0),
            'anyone_can_modify' => (int) ($kb['anyone_can_modify'] ?? 0),
            'associated_item_ids' => $associatedItems,
            'deleted_by' => (int) $session->get('user-id'),
            'deleted_at' => $createdAt,
        ],
        $createdAt,
        (int) ($kb['id'] ?? 0)
    );
}

function kbLoadDeletedEntries(): array
{
    $rows = DB::query(
        'SELECT increment_id, valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s
        ORDER BY created_at DESC, increment_id DESC',
        'kb_deleted'
    );

    $userIds = [];
    foreach ($rows as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        if (!empty($payload['deleted_by'])) {
            $userIds[] = (int) $payload['deleted_by'];
        }
    }
    $userIds = array_values(array_unique(array_filter($userIds)));

    $usersById = [];
    if (!empty($userIds)) {
        $users = DB::query(
            'SELECT id, login, name
            FROM ' . prefixTable('users') . '
            WHERE id IN %li',
            $userIds
        );
        foreach ($users as $user) {
            $usersById[(int) $user['id']] = $user;
        }
    }

    $entries = [];
    foreach ($rows as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        $deletedBy = $usersById[(int) ($payload['deleted_by'] ?? 0)] ?? null;
        $entries[] = [
            'trash_id' => (int) $row['increment_id'],
            'id' => (int) ($payload['original_id'] ?? 0),
            'label' => (string) ($payload['label'] ?? ''),
            'category' => (string) ($payload['category'] ?? ''),
            'date' => !empty($payload['deleted_at']) ? date('Y-m-d H:i:s', (int) $payload['deleted_at']) : '',
            'deleted_by' => $deletedBy === null ? '' : trim((string) ($deletedBy['name'] ?? '') . ' [' . (string) ($deletedBy['login'] ?? '') . ']'),
        ];
    }

    return $entries;
}

function kbLoadLogRows(): array
{
    $rows = DB::query(
        'SELECT increment_id, intitule, valeur, created_at
        FROM ' . prefixTable('misc') . '
        WHERE type = %s
        ORDER BY created_at DESC, increment_id DESC',
        'kb_log'
    );

    $decodedRows = [];
    foreach ($rows as $row) {
        $payload = kbMiscDecode((string) ($row['valeur'] ?? ''));
        $decodedRows[] = [
            'increment_id' => (int) $row['increment_id'],
            'date' => (int) ($payload['date'] ?? $row['created_at'] ?? 0),
            'label' => (string) ($payload['label'] ?? ''),
            'user_login' => (string) ($payload['user_login'] ?? ''),
            'action' => (string) ($payload['action'] ?? $row['intitule'] ?? ''),
            'reason' => (string) ($payload['reason'] ?? ''),
            'user_id' => (int) ($payload['user_id'] ?? 0),
        ];
    }

    return $decodedRows;
}

$type = (string) $request->request->get('type', $request->query->get('type', ''));
$data = (string) $request->request->get('data', '');
$key = (string) $request->request->get('key', $request->query->get('key', ''));

switch ($type) {
    case 'list_kbs':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $rows = DB::query(
            'SELECT
                k.id,
                k.label,
                k.description,
                k.author_id,
                k.anyone_can_modify,
                c.category AS category,
                u.login AS author_login
            FROM ' . prefixTable('kb') . ' AS k
            LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON (c.id = k.category_id)
            LEFT JOIN ' . prefixTable('users') . ' AS u ON (u.id = k.author_id)
            ORDER BY c.category ASC, k.label ASC'
        );

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = kbBuildEntryPayload($row, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        }

        echo (string) prepareExchangedData(['error' => false, 'entries' => $entries], 'encode');
        break;

    case 'get_kb':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        if (!is_array($payload)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('json_error_format')], 'encode');
            break;
        }

        $kbId = (int) ($payload['id'] ?? 0);
        $logView = (int) ($payload['log_view'] ?? 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }

        $entry = kbBuildEntryPayload($kb, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        if ($logView === 1) {
            kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_shown');
        }

        echo (string) prepareExchangedData(['error' => false, 'entry' => $entry], 'encode');
        break;

    case 'save_kb':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        if (!is_array($payload)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('json_error_format')], 'encode');
            break;
        }

        $kbId = (int) ($payload['id'] ?? 0);
        $label = mb_substr(trim((string) ($payload['label'] ?? '')), 0, 200);
        $category = mb_substr(trim((string) ($payload['category'] ?? '')), 0, 50);
        $description = trim((string) ($payload['description'] ?? ''));
        $anyoneCanModify = (int) ($payload['anyone_can_modify'] ?? 0) === 1 ? 1 : 0;
        $associatedItems = is_array($payload['associated_items'] ?? null) ? $payload['associated_items'] : [];

        $label = trim((string) $antiXss->xss_clean($label));
        $category = trim((string) $antiXss->xss_clean($category));
        $description = trim((string) $antiXss->xss_clean($description));

        if ($label === '' || $category === '' || $description === '') {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('all_fields_are_required')], 'encode');
            break;
        }

        $allowedAssociatedItems = kbFilterAllowedItemIds($associatedItems, $session);
        $categoryRow = DB::queryFirstRow(
            'SELECT id
            FROM ' . prefixTable('kb_categories') . '
            WHERE category = %s',
            $category
        );
        if ($categoryRow === null) {
            DB::insert(
                prefixTable('kb_categories'),
                [
                    'category' => $category,
                ]
            );
            $categoryId = (int) DB::insertId();
        } else {
            $categoryId = (int) $categoryRow['id'];
        }

        $oldKb = null;
        $oldAssociatedItems = [];
        if ($kbId > 0) {
            $oldKb = kbLoadRow($kbId);
            if ($oldKb === null) {
                echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
                break;
            }
            if (!kbCanEdit($oldKb, $session)) {
                echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
                break;
            }

            $oldAssociatedRows = DB::query(
                'SELECT item_id
                FROM ' . prefixTable('kb_items') . '
                WHERE kb_id = %i',
                $kbId
            );
            $oldAssociatedItems = array_values(array_map(static fn (array $row): int => (int) $row['item_id'], $oldAssociatedRows));

            DB::update(
                prefixTable('kb'),
                [
                    'category_id' => $categoryId,
                    'label' => $label,
                    'description' => $description,
                    'anyone_can_modify' => $anyoneCanModify,
                ],
                'id = %i',
                $kbId
            );
        } else {
            DB::insert(
                prefixTable('kb'),
                [
                    'category_id' => $categoryId,
                    'label' => $label,
                    'description' => $description,
                    'author_id' => (int) $session->get('user-id'),
                    'anyone_can_modify' => $anyoneCanModify,
                ]
            );
            $kbId = (int) DB::insertId();
        }

        DB::delete(prefixTable('kb_items'), 'kb_id = %i', $kbId);
        foreach ($allowedAssociatedItems as $itemId) {
            DB::insert(
                prefixTable('kb_items'),
                [
                    'kb_id' => $kbId,
                    'item_id' => $itemId,
                ]
            );
        }

        if ($oldKb === null) {
            kbInsertLog($SETTINGS, $session, $kbId, $label, 'at_creation');
        } else {
            $changes = [];
            if ((string) ($oldKb['label'] ?? '') !== $label) {
                $changes[] = 'label';
            }
            if ((string) ($oldKb['category'] ?? '') !== $category) {
                $changes[] = 'category';
            }
            if ((string) ($oldKb['description'] ?? '') !== $description) {
                $changes[] = 'description';
            }
            if ((int) ($oldKb['anyone_can_modify'] ?? 0) !== $anyoneCanModify) {
                $changes[] = 'anyone_can_modify';
            }
            sort($oldAssociatedItems);
            $newAssociatedItems = $allowedAssociatedItems;
            sort($newAssociatedItems);
            if ($oldAssociatedItems !== $newAssociatedItems) {
                $changes[] = 'associated_items';
            }

            kbInsertLog(
                $SETTINGS,
                $session,
                $kbId,
                $label,
                'at_modification',
                empty($changes) ? '' : implode(', ', $changes)
            );
        }

        $entry = kbBuildEntryPayload(kbLoadRow($kbId) ?? [], $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_saved'), 'id' => $kbId, 'entry' => $entry], 'encode');
        break;

    case 'delete_kb':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        if (!is_array($payload)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('json_error_format')], 'encode');
            break;
        }

        $kbId = (int) ($payload['id'] ?? 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }
        if (!kbCanDelete($kb, $session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $associatedRows = DB::query(
            'SELECT item_id
            FROM ' . prefixTable('kb_items') . '
            WHERE kb_id = %i',
            $kbId
        );
        $associatedItems = array_values(array_map(static fn (array $row): int => (int) $row['item_id'], $associatedRows));

        kbTrashEntry($kb, $associatedItems, $session);
        DB::delete(prefixTable('kb_items'), 'kb_id = %i', $kbId);
        DB::delete(prefixTable('kb'), 'id = %i', $kbId);
        kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_delete');

        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_deleted')], 'encode');
        break;

    case 'search_categories':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        $term = is_array($payload) ? trim((string) ($payload['term'] ?? '')) : '';
        $termLike = '%' . $term . '%';
        $rows = $term === ''
            ? DB::query('SELECT category FROM ' . prefixTable('kb_categories') . ' ORDER BY category ASC LIMIT 20')
            : DB::query(
                'SELECT category
                FROM ' . prefixTable('kb_categories') . '
                WHERE category LIKE %s
                ORDER BY category ASC
                LIMIT 20',
                $termLike
            );

        $categories = array_values(array_map(static fn (array $row): string => (string) $row['category'], $rows));
        echo (string) prepareExchangedData(['error' => false, 'categories' => $categories], 'encode');
        break;

    case 'search_items':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        $term = is_array($payload) ? trim((string) ($payload['term'] ?? '')) : '';
        $folders = kbAccessibleFolders($session);
        if (empty($folders)) {
            echo (string) prepareExchangedData(['error' => false, 'results' => []], 'encode');
            break;
        }

        $termLike = '%' . $term . '%';
        $rows = $term === ''
            ? DB::query(
                'SELECT id, label
                FROM ' . prefixTable('items') . '
                WHERE inactif = %i AND id_tree IN %li
                ORDER BY label ASC
                LIMIT 20',
                0,
                $folders
            )
            : DB::query(
                'SELECT id, label
                FROM ' . prefixTable('items') . '
                WHERE inactif = %i
                    AND id_tree IN %li
                    AND label LIKE %s
                ORDER BY label ASC
                LIMIT 20',
                0,
                $folders,
                $termLike
            );

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int) $row['id'],
                'text' => (string) $row['label'],
            ];
        }

        echo (string) prepareExchangedData(['error' => false, 'results' => $results], 'encode');
        break;

    case 'upload_attachment':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $kbId = (int) $request->request->get('kb_id', 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }
        if (!kbCanEdit($kb, $session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $directory = kbAttachmentBaseDirectory($SETTINGS);
        if ($directory === '') {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_cannot_open_file')], 'encode');
            break;
        }

        $uploadedFiles = $request->files->get('attachments');
        if ($uploadedFiles instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $uploadedFiles = [$uploadedFiles];
        }
        if (!is_array($uploadedFiles) || empty($uploadedFiles)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('no_file_to_upload')], 'encode');
            break;
        }

        foreach ($uploadedFiles as $uploadedFile) {
            if ($uploadedFile === null) {
                continue;
            }

            if ($uploadedFile->isValid() === false) {
                echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_upload_runtime_not_found')], 'encode');
                break 2;
            }

            $originalName = kbAttachmentSanitizeFilename((string) $uploadedFile->getClientOriginalName());
            if (kbAttachmentExtensionIsAllowed($originalName, $SETTINGS) === false) {
                echo (string) prepareExchangedData([
                    'error' => true,
                    'message' => str_replace('%s', kbBuildAllowedAttachmentExtensionsLabel($SETTINGS), (string) $lang->get('kb_attachment_extension_not_allowed')),
                ], 'encode');
                break 2;
            }

            $fileSize = 0;
            try {
                $fileSize = (int) $uploadedFile->getSize();
            } catch (Throwable $exception) {
                $fileSize = (int) ($uploadedFile->getClientSize() ?? 0);
            }
            if ($fileSize <= 0 && (int) ($SETTINGS['upload_zero_byte_file'] ?? 0) !== 1) {
                echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_attachment_zero_byte_not_allowed')], 'encode');
                break 2;
            }

            $attachmentId = kbGenerateToken();
            $storedName = $attachmentId . '_' . $originalName;
            $uploadedAt = time();
            $mimeType = (string) ($uploadedFile->getClientMimeType() ?? '');

            $uploadedFile->move($directory, $storedName);

            if ($fileSize <= 0) {
                $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
                if (is_file($storedPath)) {
                    $resolvedSize = filesize($storedPath);
                    $fileSize = $resolvedSize === false ? 0 : (int) $resolvedSize;
                }
            }

            DB::insert(
                prefixTable('misc'),
                [
                    'type' => 'kb_attachment',
                    'intitule' => kbAttachmentIntitule($kbId, $attachmentId),
                    'valeur' => kbMiscEncode([
                        'attachment_id' => $attachmentId,
                        'kb_id' => $kbId,
                        'original_name' => $originalName,
                        'stored_name' => $storedName,
                        'mime_type' => $mimeType,
                        'size' => $fileSize,
                        'uploaded_by' => (int) $session->get('user-id'),
                        'uploaded_at' => $uploadedAt,
                    ]),
                    'created_at' => $uploadedAt,
                ]
            );
        }

        kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_modification', 'attachments_upload');
        $entry = kbBuildEntryPayload(kbLoadRow($kbId) ?? [], $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_attachment_uploaded'), 'entry' => $entry], 'encode');
        break;

    case 'delete_attachment':
        if (kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        if (!is_array($payload)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('json_error_format')], 'encode');
            break;
        }
        $attachmentId = trim((string) ($payload['attachment_id'] ?? ''));
        $kbId = (int) ($payload['kb_id'] ?? 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }
        if (!kbCanEdit($kb, $session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        $attachmentRow = kbFindAttachmentRowById($attachmentId);
        if ($attachmentRow === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('file_not_exists')], 'encode');
            break;
        }
        if ((int) ($attachmentRow['payload']['kb_id'] ?? 0) !== $kbId) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        kbDeleteAttachmentFileAndRow($attachmentRow, $SETTINGS);
        kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_modification', 'attachments_delete');
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_attachment_deleted')], 'encode');
        break;

    case 'download_attachment':
        if (kbIsAdmin($session)) {
            $session->set('system-error_code', ERR_NOT_ALLOWED);
            include $SETTINGS['cpassman_dir'] . '/error.php';
            exit;
        }
        if ($key !== (string) $session->get('key')) {
            $session->set('system-error_code', ERR_NOT_ALLOWED);
            include $SETTINGS['cpassman_dir'] . '/error.php';
            exit;
        }

        $attachmentId = trim((string) $request->query->get('attachment_id', ''));
        $attachmentRow = kbFindAttachmentRowById($attachmentId);
        if ($attachmentRow === null) {
            http_response_code(404);
            exit;
        }

        $payload = $attachmentRow['payload'];
        $kb = kbLoadRow((int) ($payload['kb_id'] ?? 0));
        if ($kb === null) {
            http_response_code(404);
            exit;
        }
        if (!kbCanEdit($kb, $session) && kbIsAdmin($session) === false) {
            // Any non-admin user can consult KB; attachment follows same visibility.
        }

        $directory = kbAttachmentBaseDirectory($SETTINGS);
        $storedName = (string) ($payload['stored_name'] ?? '');
        $filePath = $directory . DIRECTORY_SEPARATOR . $storedName;
        if ($directory === '' || $storedName === '' || is_file($filePath) === false) {
            http_response_code(404);
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . ((string) ($payload['mime_type'] ?? '') !== '' ? (string) $payload['mime_type'] : 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . rawurlencode((string) ($payload['original_name'] ?? 'attachment')) . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Pragma: public');
        header('Expires: 0');
        readfile($filePath);
        exit;

    case 'load_deleted_kbs':
        if (!kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        echo (string) prepareExchangedData(['error' => false, 'entries' => kbLoadDeletedEntries()], 'encode');
        break;

    case 'restore_deleted_kbs':
        if (!kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        $ids = is_array($payload) && is_array($payload['ids'] ?? null) ? array_values(array_unique(array_map('intval', $payload['ids']))) : [];
        if (empty($ids)) {
            echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('done')], 'encode');
            break;
        }

        $rows = DB::query(
            'SELECT increment_id, valeur
            FROM ' . prefixTable('misc') . '
            WHERE type = %s AND increment_id IN %li',
            'kb_deleted',
            $ids
        );

        foreach ($rows as $row) {
            $trashId = (int) $row['increment_id'];
            $entry = kbMiscDecode((string) ($row['valeur'] ?? ''));
            $label = trim((string) ($entry['label'] ?? ''));
            $category = trim((string) ($entry['category'] ?? ''));
            $description = (string) ($entry['description'] ?? '');
            if ($label === '' || $category === '') {
                DB::delete(prefixTable('misc'), 'increment_id = %i', $trashId);
                continue;
            }

            $categoryRow = DB::queryFirstRow(
                'SELECT id
                FROM ' . prefixTable('kb_categories') . '
                WHERE category = %s',
                $category
            );
            if ($categoryRow === null) {
                DB::insert(prefixTable('kb_categories'), ['category' => $category]);
                $categoryId = (int) DB::insertId();
            } else {
                $categoryId = (int) $categoryRow['id'];
            }

            $requestedId = (int) ($entry['original_id'] ?? 0);
            $rowExists = $requestedId > 0 ? DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('kb') . ' WHERE id = %i', $requestedId) : 0;
            if ($requestedId > 0 && (int) $rowExists === 0) {
                DB::insert(
                    prefixTable('kb'),
                    [
                        'id' => $requestedId,
                        'category_id' => $categoryId,
                        'label' => $label,
                        'description' => $description,
                        'author_id' => (int) ($entry['author_id'] ?? 0),
                        'anyone_can_modify' => (int) ($entry['anyone_can_modify'] ?? 0),
                    ]
                );
                $newKbId = $requestedId;
            } else {
                DB::insert(
                    prefixTable('kb'),
                    [
                        'category_id' => $categoryId,
                        'label' => $label,
                        'description' => $description,
                        'author_id' => (int) ($entry['author_id'] ?? 0),
                        'anyone_can_modify' => (int) ($entry['anyone_can_modify'] ?? 0),
                    ]
                );
                $newKbId = (int) DB::insertId();
            }

            $associatedIds = is_array($entry['associated_item_ids'] ?? null)
                ? array_values(array_unique(array_filter(array_map('intval', $entry['associated_item_ids']), static fn (int $value): bool => $value > 0)))
                : [];
            if (!empty($associatedIds)) {
                $existingItems = DB::query(
                    'SELECT id
                    FROM ' . prefixTable('items') . '
                    WHERE inactif = %i AND id IN %li',
                    0,
                    $associatedIds
                );
                foreach ($existingItems as $existingItem) {
                    DB::insert(
                        prefixTable('kb_items'),
                        [
                            'kb_id' => $newKbId,
                            'item_id' => (int) $existingItem['id'],
                        ]
                    );
                }
            }

            kbReassignAttachmentsToKbId((int) ($entry['original_id'] ?? 0), $newKbId);
            kbInsertLog($SETTINGS, $session, $newKbId, $label, 'at_restored');
            DB::delete(prefixTable('misc'), 'increment_id = %i', $trashId);
        }

        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('done')], 'encode');
        break;

    case 'purge_deleted_kbs':
        if (!kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        $ids = is_array($payload) && is_array($payload['ids'] ?? null) ? array_values(array_unique(array_map('intval', $payload['ids']))) : [];
        if (!empty($ids)) {
            $rows = DB::query(
                'SELECT increment_id, valeur
                FROM ' . prefixTable('misc') . '
                WHERE type = %s AND increment_id IN %li',
                'kb_deleted',
                $ids
            );
            $kbIdsToPurge = [];
            foreach ($rows as $row) {
                $entry = kbMiscDecode((string) ($row['valeur'] ?? ''));
                $kbIdsToPurge[] = (int) ($entry['original_id'] ?? 0);
            }
            kbPurgeAttachmentsForKbIds(array_values(array_unique(array_filter($kbIdsToPurge))), $SETTINGS);
            DB::delete(prefixTable('misc'), 'type = %s AND increment_id IN %li', 'kb_deleted', $ids);
        }

        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('done')], 'encode');
        break;

    case 'datatables_logs':
        if (!kbIsAdmin($session) || $key !== (string) $session->get('key')) {
            echo json_encode([
                'draw' => 0,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
            break;
        }

        $draw = (int) $request->request->get('draw', 0);
        $start = max(0, (int) $request->request->get('start', 0));
        $length = max(10, (int) $request->request->get('length', 10));
        $searchValue = trim((string) (($request->request->all()['search']['value'] ?? '') ?: ''));

        $rows = kbLoadLogRows();
        $recordsTotal = count($rows);

        $filteredRows = array_values(array_filter($rows, static function (array $row) use ($searchValue): bool {
            if ($searchValue === '') {
                return true;
            }

            $haystack = mb_strtolower(
                (string) ($row['label'] ?? '') . ' ' .
                (string) ($row['user_login'] ?? '') . ' ' .
                (string) ($row['action'] ?? '') . ' ' .
                (string) ($row['reason'] ?? '')
            );

            return mb_strpos($haystack, mb_strtolower($searchValue)) !== false;
        }));

        $recordsFiltered = count($filteredRows);
        $pagedRows = array_slice($filteredRows, $start, $length);
        $dataRows = [];
        foreach ($pagedRows as $row) {
            $dataRows[] = [
                date(($SETTINGS['date_format'] ?? 'Y-m-d') . ' ' . ($SETTINGS['time_format'] ?? 'H:i:s'), (int) ($row['date'] ?? time())),
                (string) ($row['label'] ?? ''),
                (string) ($row['user_login'] ?? ''),
                (string) ($row['action'] ?? ''),
                (string) ($row['reason'] ?? ''),
            ];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $dataRows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'purge_logs':
        if (!kbIsAdmin($session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }
        if ($key !== (string) $session->get('key')) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('key_is_not_correct')], 'encode');
            break;
        }

        $payload = prepareExchangedData($data, 'decode');
        if (!is_array($payload)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('json_error_format')], 'encode');
            break;
        }

        $dateStart = trim((string) ($payload['dateStart'] ?? ''));
        $dateEnd = trim((string) ($payload['dateEnd'] ?? ''));
        $filterUser = (int) ($payload['filter_user'] ?? -1);
        $filterAction = trim((string) ($payload['filter_action'] ?? 'all'));

        $rows = kbLoadLogRows();
        foreach ($rows as $row) {
            $rowDate = (int) ($row['date'] ?? 0);
            $keep = true;

            if ($dateStart !== '') {
                $startTimestamp = strtotime($dateStart . ' 00:00:00');
                if ($startTimestamp !== false && $rowDate < $startTimestamp) {
                    $keep = false;
                }
            }
            if ($keep && $dateEnd !== '') {
                $endTimestamp = strtotime($dateEnd . ' 23:59:59');
                if ($endTimestamp !== false && $rowDate > $endTimestamp) {
                    $keep = false;
                }
            }
            if ($keep && $filterUser !== -1 && (int) ($row['user_id'] ?? 0) !== $filterUser) {
                $keep = false;
            }
            if ($keep && $filterAction !== '' && $filterAction !== 'all' && (string) ($row['action'] ?? '') !== $filterAction) {
                $keep = false;
            }

            if ($keep) {
                DB::delete(prefixTable('misc'), 'increment_id = %i', (int) $row['increment_id']);
            }
        }

        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('done')], 'encode');
        break;

    default:
        echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('server_answer_error')], 'encode');
        break;
}
