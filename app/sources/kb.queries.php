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
DB::$encoding = 'utf8mb4';
DB::query('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

$requestType = htmlspecialchars((string) $request->request->get('type', $request->query->get('type', '')), ENT_QUOTES, 'UTF-8');
$adminMaintenanceTypes = ['load_deleted_kbs', 'restore_deleted_kbs', 'purge_deleted_kbs', 'datatables_logs', 'purge_logs'];
if (in_array($requestType, $adminMaintenanceTypes, true) === true) {
    $requiredPage = in_array($requestType, ['datatables_logs', 'purge_logs'], true) ? 'utilities.logs' : 'utilities.deletion';
    if ((int) $session->get('user-admin') !== 1 || $checkUserAccess->userAccessPage($requiredPage) === false) {
        $session->set('system-error_code', ERR_NOT_ALLOWED);
        include TEAMPASS_ROOT . '/public/error.php';
        exit;
    }
} elseif ((int) $session->get('user-admin') === 1 || $checkUserAccess->userAccessPage('kb') === false) {
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
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
    } while (true);
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
        $basePath = defined('TEAMPASS_STORAGE') ? TEAMPASS_STORAGE . '/files' : __DIR__ . '/../../storage/files';
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

/**
 * Returns a safe MIME type for use in Content-Type headers.
 * Detects the real MIME type from the stored file via mime_content_type()
 * and neutralises any type that could trigger in-browser execution (HTML, JS, SVG…).
 */
function kbSanitizeMimeType(string $storedFilePath): string
{
    $dangerous = [
        'text/html', 'text/javascript', 'application/javascript',
        'application/x-javascript', 'application/xhtml+xml', 'image/svg+xml',
    ];

    $mimeType = is_file($storedFilePath) ? (string) mime_content_type($storedFilePath) : '';
    if ($mimeType === '') {
        return 'application/octet-stream';
    }

    foreach ($dangerous as $pattern) {
        if (stripos($mimeType, $pattern) !== false) {
            return 'application/octet-stream';
        }
    }

    return $mimeType;
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

function kbLoadAssociatedItemIds(int $kbId): array
{
    if ($kbId <= 0) {
        return [];
    }

    $rows = DB::query(
        'SELECT item_id
        FROM ' . prefixTable('kb_items') . '
        WHERE kb_id = %i',
        $kbId
    );

    return array_values(array_unique(array_filter(
        array_map(static fn (array $row): int => (int) ($row['item_id'] ?? 0), $rows),
        static fn (int $itemId): bool => $itemId > 0
    )));
}

function kbBuildPersistedAssociatedItemIds(int $kbId, array $requestedItemIds, SessionInterface $session): array
{
    $visibleRequestedItemIds = kbFilterAllowedItemIds($requestedItemIds, $session);
    if ($kbId <= 0) {
        return $visibleRequestedItemIds;
    }

    $existingItemIds = kbLoadAssociatedItemIds($kbId);
    if (empty($existingItemIds)) {
        return $visibleRequestedItemIds;
    }

    // Preserve associations the editor cannot currently see to avoid dropping links
    // after a role change or reduced folder access.
    $visibleExistingItemIds = kbFilterAllowedItemIds($existingItemIds, $session);
    $hiddenExistingItemIds = array_values(array_diff($existingItemIds, $visibleExistingItemIds));

    return array_values(array_unique(array_merge($visibleRequestedItemIds, $hiddenExistingItemIds)));
}

function kbNormalizeTextLineEndings(string $text): string
{
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function kbSanitizeTextInput(string $text): string
{
    $text = kbNormalizeTextLineEndings($text);
    $text = str_replace("\0", '', $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

    return trim($text);
}

function kbNormalizeLegacyRichTextMarkers(string $text): string
{
    $decodedText = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($decodedText !== $text && preg_match('/<(?:a\s|br\s*\/?>|\/?p\b)/iu', $decodedText) === 1) {
        $text = $decodedText;
    }

    $text = preg_replace_callback(
        '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/isu',
        static function (array $matches): string {
            $href = trim(html_entity_decode(strip_tags((string) $matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $label = trim(html_entity_decode(strip_tags((string) $matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($href === '') {
                return $label;
            }

            return ($label !== '' && $label !== $href ? $label . ' ' : '') . '(' . $href . ')';
        },
        $text
    ) ?? $text;
    $text = preg_replace('/<br\s*\/?>/iu', "\n", $text) ?? $text;
    $text = preg_replace('/<\/p>\s*<p[^>]*>/iu', "\n\n", $text) ?? $text;
    $text = preg_replace('/<\/?p[^>]*>/iu', "\n", $text) ?? $text;

    return $text;
}

function kbFormatTimestamp(int $timestamp): string
{
    global $SETTINGS;

    $dateFormat = (string) ($SETTINGS['date_format'] ?? 'Y-m-d');
    $timeFormat = (string) ($SETTINGS['time_format'] ?? 'H:i');

    return date(trim($dateFormat . ' ' . $timeFormat), $timestamp);
}

function kbBuildDescriptionExcerpt(string $description, int $maxLength = 180): string
{
    $excerpt = trim((string) preg_replace('/\s+/u', ' ', kbNormalizeTextLineEndings($description)));
    if ($excerpt === '') {
        return '';
    }

    if (mb_strlen($excerpt) <= $maxLength) {
        return $excerpt;
    }

    return rtrim(mb_substr($excerpt, 0, max(1, $maxLength - 1))) . '…';
}

function kbRenderInlineText(string $text): string
{
    $pattern = '/(\[([^\]\n]{1,200})\]\(((?:https?:\/\/|www\.)[^\s)<>]+|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\)|((?:https?:\/\/|www\.)[^\s<]+|[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}))/iu';
    $result = '';
    $offset = 0;

    if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) !== false) {
        foreach ($matches[0] as $index => $matchData) {
            $match = (string) $matchData[0];
            $position = (int) $matchData[1];
            $markdownLabel = (string) ($matches[2][$index][0] ?? '');
            $markdownCandidate = (string) ($matches[3][$index][0] ?? '');
            $directCandidate = (string) ($matches[4][$index][0] ?? '');
            $candidate = $markdownCandidate !== '' ? $markdownCandidate : $directCandidate;
            $label = $markdownLabel !== '' ? $markdownLabel : $candidate;
            $trailing = '';

            if ($markdownCandidate === '') {
                $trimmedCandidate = rtrim($candidate, ".,;:!?)]}>\"'");
                $trailing = substr($candidate, strlen($trimmedCandidate));
                $candidate = $trimmedCandidate;
                $label = $candidate;
            }

            if ($position > $offset) {
                $result .= htmlspecialchars(substr($text, $offset, $position - $offset), ENT_QUOTES, 'UTF-8');
            }

            $href = '';
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                $href = 'mailto:' . $candidate;
            } else {
                $url = preg_match('/^www\./iu', $candidate) === 1 ? 'https://' . $candidate : $candidate;
                if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
                    $href = $url;
                }
            }

            if ($href !== '') {
                $result .= '<a class="tp-kb-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' .
                    htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
                    '</a>';
            } else {
                $result .= htmlspecialchars($match, ENT_QUOTES, 'UTF-8');
            }

            if ($trailing !== '') {
                $result .= htmlspecialchars($trailing, ENT_QUOTES, 'UTF-8');
            }

            $offset = $position + strlen($match);
        }
    }

    if ($offset < strlen($text)) {
        $result .= htmlspecialchars(substr($text, $offset), ENT_QUOTES, 'UTF-8');
    }

    return $result === '' ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $result;
}

function kbBuildRichTextHtml(string $text): string
{
    $text = trim(kbNormalizeLegacyRichTextMarkers(kbNormalizeTextLineEndings($text)));
    if ($text === '') {
        return '';
    }

    $blocks = preg_split("/\n{2,}/", $text) ?: [];
    $htmlBlocks = [];
    foreach ($blocks as $block) {
        $block = trim($block, "\n");
        if ($block === '') {
            continue;
        }

        $lines = array_values(array_filter(
            array_map(static fn (string $line): string => rtrim($line), explode("\n", $block)),
            static fn (string $line): bool => trim($line) !== ''
        ));
        if (empty($lines)) {
            continue;
        }

        $unorderedItems = [];
        $orderedItems = [];
        $quoteLines = [];
        $isUnordered = true;
        $isOrdered = true;
        $isQuote = true;

        foreach ($lines as $line) {
            if (preg_match('/^\s*[-*•]\s+(.+)$/u', $line, $matches) === 1) {
                $unorderedItems[] = $matches[1];
            } else {
                $isUnordered = false;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $matches) === 1) {
                $orderedItems[] = $matches[1];
            } else {
                $isOrdered = false;
            }

            if (preg_match('/^\s*>\s?(.*)$/u', $line, $matches) === 1) {
                $quoteLines[] = $matches[1];
            } else {
                $isQuote = false;
            }
        }

        if ($isUnordered === true && !empty($unorderedItems)) {
            $itemsHtml = array_map(static fn (string $item): string => '<li>' . kbRenderInlineText(trim($item)) . '</li>', $unorderedItems);
            $htmlBlocks[] = '<ul>' . implode('', $itemsHtml) . '</ul>';
            continue;
        }

        if ($isOrdered === true && !empty($orderedItems)) {
            $itemsHtml = array_map(static fn (string $item): string => '<li>' . kbRenderInlineText(trim($item)) . '</li>', $orderedItems);
            $htmlBlocks[] = '<ol>' . implode('', $itemsHtml) . '</ol>';
            continue;
        }

        if ($isQuote === true && !empty($quoteLines)) {
            $htmlBlocks[] = '<blockquote>' . implode('<br>', array_map(static fn (string $line): string => kbRenderInlineText(trim($line)), $quoteLines)) . '</blockquote>';
            continue;
        }

        $htmlBlocks[] = '<p>' . implode('<br>', array_map(static fn (string $line): string => kbRenderInlineText($line), $lines)) . '</p>';
    }

    return implode("\n", $htmlBlocks);
}

function kbGetDescriptionHtml(string $description): string
{
    return kbBuildRichTextHtml($description);
}

function kbCanComment(array $kb, SessionInterface $session): bool
{
    return kbIsAdmin($session) === false
        && (int) ($session->get('user-id') ?? 0) > 0
        && (int) ($kb['allow_comments'] ?? 0) === 1;
}

function kbCanDeleteComment(array $comment, array $kb, SessionInterface $session): bool
{
    $userId = (int) ($session->get('user-id') ?? 0);
    if ($userId <= 0) {
        return false;
    }

    return (int) ($comment['author_id'] ?? 0) === $userId
        || (int) ($kb['author_id'] ?? 0) === $userId;
}

function kbBuildCommentAuthorLabel(array $comment): string
{
    $name = trim((string) ($comment['author_name'] ?? ''));
    $lastname = trim((string) ($comment['author_lastname'] ?? ''));
    $login = trim((string) ($comment['author_login'] ?? ''));

    $fullName = trim($name . ' ' . $lastname);

    return $fullName !== '' ? $fullName : ($name !== '' ? $name : $login);
}

function kbCountComments(int $kbId): int
{
    return (int) DB::queryFirstField(
        'SELECT COUNT(*)
        FROM ' . prefixTable('kb_comments') . '
        WHERE kb_id = %i',
        $kbId
    );
}

function kbLoadComments(int $kbId, array $kb, SessionInterface $session): array
{
    $rows = DB::query(
        'SELECT c.id,
            c.kb_id,
            c.content,
            c.author_id,
            c.created_at,
            c.updated_at,
            u.login AS author_login,
            u.name AS author_name,
            u.lastname AS author_lastname
        FROM ' . prefixTable('kb_comments') . ' AS c
        LEFT JOIN ' . prefixTable('users') . ' AS u ON (u.id = c.author_id)
        WHERE c.kb_id = %i
        ORDER BY c.created_at ASC, c.id ASC',
        $kbId
    );

    $comments = [];
    foreach ($rows as $row) {
        $comments[] = [
            'id' => (int) ($row['id'] ?? 0),
            'kb_id' => (int) ($row['kb_id'] ?? 0),
            'content' => (string) ($row['content'] ?? ''),
            'content_html' => kbGetDescriptionHtml((string) ($row['content'] ?? '')),
            'author' => kbBuildCommentAuthorLabel($row),
            'author_id' => (int) ($row['author_id'] ?? 0),
            'created_at' => (int) ($row['created_at'] ?? 0),
            'created_at_label' => !empty($row['created_at']) ? kbFormatTimestamp((int) $row['created_at']) : '',
            'can_delete' => kbCanDeleteComment($row, $kb, $session),
        ];
    }

    return $comments;
}

function kbPurgeCommentsForKbIds(array $kbIds): void
{
    if (empty($kbIds)) {
        return;
    }

    DB::delete(prefixTable('kb_comments'), 'kb_id IN %li', $kbIds);
}

function kbReassignCommentsToKbId(int $oldKbId, int $newKbId): void
{
    if ($oldKbId === $newKbId) {
        return;
    }

    DB::update(
        prefixTable('kb_comments'),
        ['kb_id' => $newKbId],
        'kb_id = %i',
        $oldKbId
    );
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
            k.allow_comments,
            c.category AS category,
            u.login AS author_login
        FROM ' . prefixTable('kb') . ' AS k
        LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON (c.id = k.category_id)
        LEFT JOIN ' . prefixTable('users') . ' AS u ON (u.id = k.author_id)
        WHERE k.id = %i
            AND k.deleted_at IS NULL',
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

function kbBuildEntryPayload(array $kb, SessionInterface $session, string $baseUrl, bool $includeComments = true): array
{
    $associatedItems = kbLoadAssociatedItems((int) $kb['id'], $session, $baseUrl);
    $comments = $includeComments === true ? kbLoadComments((int) $kb['id'], $kb, $session) : [];
    $commentsCount = $includeComments === true ? count($comments) : kbCountComments((int) $kb['id']);

    return [
        'id' => (int) $kb['id'],
        'label' => (string) ($kb['label'] ?? ''),
        'description' => (string) ($kb['description'] ?? ''),
        'description_html' => kbGetDescriptionHtml((string) ($kb['description'] ?? '')),
        'description_excerpt' => kbBuildDescriptionExcerpt((string) ($kb['description'] ?? '')),
        'category' => (string) ($kb['category'] ?? ''),
        'author' => kbBuildAuthorLabel(isset($kb['author_login']) ? (string) $kb['author_login'] : ''),
        'author_id' => (int) ($kb['author_id'] ?? 0),
        'anyone_can_modify' => (int) ($kb['anyone_can_modify'] ?? 0),
        'allow_comments' => (int) ($kb['allow_comments'] ?? 0),
        'items_count' => count($associatedItems),
        'associated_items' => $associatedItems,
        'attachments' => kbSerializeAttachments((int) $kb['id']),
        'comments' => $comments,
        'comments_count' => $commentsCount,
        'can_comment' => kbCanComment($kb, $session),
        'can_edit' => kbCanEdit($kb, $session),
        'can_delete' => kbCanDelete($kb, $session),
    ];
}

function kbEditionLockHeartbeatTimeout(): int
{
    return defined('EDITION_LOCK_HEARTBEAT_TIMEOUT')
        ? (int) EDITION_LOCK_HEARTBEAT_TIMEOUT
        : 300;
}

function kbLatestEditionLock(int $kbId): ?array
{
    $lock = DB::queryFirstRow(
        'SELECT timestamp, user_id, increment_id
        FROM ' . prefixTable('kb_edition') . '
        WHERE kb_id = %i
        ORDER BY increment_id DESC',
        $kbId
    );

    return $lock === null ? null : $lock;
}

function kbCreateEditionLock(int $kbId, int $userId, int $timestamp): void
{
    DB::insert(
        prefixTable('kb_edition'),
        [
            'timestamp' => $timestamp,
            'kb_id' => $kbId,
            'user_id' => $userId,
        ]
    );
}

function kbReleaseEditionLock(int $kbId, int $userId, string $userLogin): void
{
    DB::delete(
        prefixTable('kb_edition'),
        'kb_id = %i AND user_id = %i',
        $kbId,
        $userId
    );

    if (DB::affectedRows() > 0) {
        emitKbEditionLockEvent('stopped', $kbId, $userLogin, $userId);
    }
}

function kbReleaseAllEditionLocks(int $kbId, string $userLogin, int $userId): void
{
    DB::delete(prefixTable('kb_edition'), 'kb_id = %i', $kbId);
    if (DB::affectedRows() > 0) {
        emitKbEditionLockEvent('stopped', $kbId, $userLogin, $userId);
    }
}

function kbIsLocked(int $kbId, SessionInterface $session, int $userId, string $actionType = ''): array
{
    if ($kbId <= 0 || $userId <= 0) {
        return ['status' => false];
    }

    $now = time();
    $lastLock = kbLatestEditionLock($kbId);

    if ($lastLock === null) {
        if ($actionType === 'edit') {
            kbCreateEditionLock($kbId, $userId, $now);
            emitKbEditionLockEvent('started', $kbId, (string) ($session->get('user-login') ?? ''), $userId);
        }

        return ['status' => false];
    }

    if ((int) $lastLock['user_id'] === $userId) {
        if ($actionType === 'edit') {
            DB::update(
                prefixTable('kb_edition'),
                ['timestamp' => $now],
                'increment_id = %i',
                (int) $lastLock['increment_id']
            );
        }

        return ['status' => false];
    }

    $heartbeatTimeout = kbEditionLockHeartbeatTimeout();
    $elapsed = abs($now - (int) $lastLock['timestamp']);

    if ($elapsed > $heartbeatTimeout) {
        $lockOwnerLogin = DB::queryFirstField(
            'SELECT login FROM ' . prefixTable('users') . ' WHERE id = %i',
            (int) $lastLock['user_id']
        );
        emitKbEditionLockEvent('stopped', $kbId, (string) ($lockOwnerLogin ?? ''), (int) $lastLock['user_id']);
        DB::delete(prefixTable('kb_edition'), 'kb_id = %i', $kbId);

        if ($actionType === 'edit') {
            kbCreateEditionLock($kbId, $userId, $now);
            emitKbEditionLockEvent('started', $kbId, (string) ($session->get('user-login') ?? ''), $userId);
        }

        return ['status' => false];
    }

    return [
        'status' => true,
        'delay' => max(0, $heartbeatTimeout - $elapsed),
    ];
}

function kbEditionLockSaveStatus(int $kbId, int $userId): array
{
    if ($kbId <= 0 || $userId <= 0) {
        return ['allowed' => false, 'reason' => 'invalid'];
    }

    $locks = DB::query(
        'SELECT timestamp, user_id, increment_id
        FROM ' . prefixTable('kb_edition') . '
        WHERE kb_id = %i
        ORDER BY increment_id DESC',
        $kbId
    );
    if (count($locks) === 0) {
        return ['allowed' => false, 'reason' => 'missing'];
    }

    $now = time();
    $timeout = kbEditionLockHeartbeatTimeout();
    $hasOwnActiveLock = false;

    foreach ($locks as $lock) {
        $elapsed = abs($now - (int) $lock['timestamp']);
        if ($elapsed > $timeout) {
            continue;
        }

        if ((int) $lock['user_id'] !== $userId) {
            return [
                'allowed' => false,
                'reason' => 'locked_by_other_user',
                'delay' => max(0, $timeout - $elapsed),
            ];
        }

        $hasOwnActiveLock = true;
    }

    return $hasOwnActiveLock === true
        ? ['allowed' => true]
        : ['allowed' => false, 'reason' => 'missing_active_lock'];
}

function kbEditionLockErrorPayload(Language $lang, array $status): array
{
    return [
        'error' => true,
        'message' => $lang->get('kb_currently_being_updated'),
        'edition_locked' => true,
        'edition_locked_delay' => $status['delay'] ?? null,
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
            'allow_comments' => (int) ($kb['allow_comments'] ?? 0),
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
    case 'handle_kb_edition_lock':
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

        $kbId = (int) ($payload['kb_id'] ?? 0);
        $action = (string) ($payload['action'] ?? '');
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }

        if ($action === 'release_lock') {
            kbReleaseEditionLock($kbId, (int) $session->get('user-id'), (string) ($session->get('user-login') ?? ''));
            echo (string) prepareExchangedData(['error' => false], 'encode');
            break;
        }

        if ($action === 'renew_lock') {
            if (!kbCanEdit($kb, $session)) {
                echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
                break;
            }

            DB::update(
                prefixTable('kb_edition'),
                ['timestamp' => time()],
                'kb_id = %i AND user_id = %i',
                $kbId,
                (int) $session->get('user-id')
            );

            echo (string) prepareExchangedData(['error' => false], 'encode');
            break;
        }

        echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
        break;

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
                k.allow_comments,
                c.category AS category,
                u.login AS author_login
            FROM ' . prefixTable('kb') . ' AS k
            LEFT JOIN ' . prefixTable('kb_categories') . ' AS c ON (c.id = k.category_id)
            LEFT JOIN ' . prefixTable('users') . ' AS u ON (u.id = k.author_id)
            WHERE k.deleted_at IS NULL
            ORDER BY c.category ASC, k.label ASC'
        );

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = kbBuildEntryPayload($row, $session, (string) ($SETTINGS['cpassman_url'] ?? ''), false);
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
        $lockForEdit = (int) ($payload['lock_for_edit'] ?? 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }
        if ($lockForEdit === 1) {
            if (!kbCanEdit($kb, $session)) {
                echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
                break;
            }

            $editionLocked = kbIsLocked($kbId, $session, (int) $session->get('user-id'), 'edit');
            if (($editionLocked['status'] ?? false) === true) {
                echo (string) prepareExchangedData(
                    [
                        'error' => false,
                        'edition_locked' => true,
                        'edition_locked_delay' => $editionLocked['delay'] ?? null,
                        'message' => $lang->get('kb_currently_being_updated'),
                    ],
                    'encode'
                );
                break;
            }
        }

        $entry = kbBuildEntryPayload($kb, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        if ($logView === 1) {
            kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_shown');
        }

        echo (string) prepareExchangedData(['error' => false, 'edition_locked' => false, 'entry' => $entry], 'encode');
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
        $description = (string) ($payload['description'] ?? '');
        $anyoneCanModify = (int) ($payload['anyone_can_modify'] ?? 0) === 1 ? 1 : 0;
        $allowComments = (int) ($payload['allow_comments'] ?? 0) === 1 ? 1 : 0;
        $requestedAssociatedItems = is_array($payload['associated_items'] ?? null) ? $payload['associated_items'] : [];
        $keepLockAfterSave = (int) ($payload['keep_lock_after_save'] ?? 0) === 1 ? 1 : 0;

        $label = trim((string) $antiXss->xss_clean($label));
        $category = trim((string) $antiXss->xss_clean($category));
        $description = kbSanitizeTextInput($description);

        if ($label === '' || $category === '' || $description === '') {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('all_fields_are_required')], 'encode');
            break;
        }

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
            $editionLockForSave = kbEditionLockSaveStatus($kbId, (int) $session->get('user-id'));
            if ($editionLockForSave['allowed'] !== true) {
                echo (string) prepareExchangedData(kbEditionLockErrorPayload($lang, $editionLockForSave), 'encode');
                break;
            }

            $oldAssociatedItems = kbLoadAssociatedItemIds($kbId);

            DB::update(
                prefixTable('kb'),
                [
                    'category_id' => $categoryId,
                    'label' => $label,
                    'description' => $description,
                    'anyone_can_modify' => $anyoneCanModify,
                    'allow_comments' => $allowComments,
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
                    'allow_comments' => $allowComments,
                ]
            );
            $kbId = (int) DB::insertId();
            if ($keepLockAfterSave === 1) {
                kbCreateEditionLock($kbId, (int) $session->get('user-id'), time());
                emitKbEditionLockEvent('started', $kbId, (string) ($session->get('user-login') ?? ''), (int) $session->get('user-id'));
            }
        }

        $associatedItemsToPersist = kbBuildPersistedAssociatedItemIds($kbId, $requestedAssociatedItems, $session);
        DB::delete(prefixTable('kb_items'), 'kb_id = %i', $kbId);
        foreach ($associatedItemsToPersist as $itemId) {
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
            if ((int) ($oldKb['allow_comments'] ?? 0) !== $allowComments) {
                $changes[] = 'allow_comments';
            }
            sort($oldAssociatedItems);
            $newAssociatedItems = $associatedItemsToPersist;
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

        emitKbEvent(
            $oldKb === null ? 'created' : 'updated',
            $kbId,
            $label,
            (string) ($session->get('user-login') ?? ''),
            (int) $session->get('user-id')
        );

        if ($oldKb !== null && $keepLockAfterSave !== 1) {
            kbReleaseAllEditionLocks($kbId, (string) ($session->get('user-login') ?? ''), (int) $session->get('user-id'));
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
        $editionLocked = kbIsLocked($kbId, $session, (int) $session->get('user-id'));
        if (($editionLocked['status'] ?? false) === true) {
            echo (string) prepareExchangedData(kbEditionLockErrorPayload($lang, $editionLocked), 'encode');
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
        kbReleaseAllEditionLocks($kbId, (string) ($session->get('user-login') ?? ''), (int) $session->get('user-id'));
        emitKbEvent(
            'deleted',
            $kbId,
            (string) $kb['label'],
            (string) ($session->get('user-login') ?? ''),
            (int) $session->get('user-id')
        );

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
        $editionLockForSave = kbEditionLockSaveStatus($kbId, (int) $session->get('user-id'));
        if ($editionLockForSave['allowed'] !== true) {
            echo (string) prepareExchangedData(kbEditionLockErrorPayload($lang, $editionLockForSave), 'encode');
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

            $uploadedFile->move($directory, $storedName);

            $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
            $mimeType = kbSanitizeMimeType($storedPath);

            if ($fileSize <= 0) {
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
        emitKbEvent(
            'updated',
            $kbId,
            (string) $kb['label'],
            (string) ($session->get('user-login') ?? ''),
            (int) $session->get('user-id')
        );
        $entry = kbBuildEntryPayload($kb, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_attachment_uploaded'), 'entry' => $entry], 'encode');
        break;

    case 'add_comment':
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

        $kbId = (int) ($payload['kb_id'] ?? 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }
        if (!kbCanComment($kb, $session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_comments_disabled')], 'encode');
            break;
        }

        $comment = kbSanitizeTextInput((string) ($payload['comment'] ?? ''));
        if ($comment === '') {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_comment_required')], 'encode');
            break;
        }
        if (mb_strlen($comment) > 3000) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_comment_too_long')], 'encode');
            break;
        }

        $createdAt = time();
        DB::insert(
            prefixTable('kb_comments'),
            [
                'kb_id' => $kbId,
                'content' => $comment,
                'author_id' => (int) $session->get('user-id'),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        );

        kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_modification', 'comment_add');
        $entry = kbBuildEntryPayload($kb, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_comment_added'), 'entry' => $entry], 'encode');
        break;

    case 'delete_comment':
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

        $commentId = (int) ($payload['comment_id'] ?? 0);
        $kbId = (int) ($payload['kb_id'] ?? 0);
        $kb = kbLoadRow($kbId);
        if ($kb === null) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_direct_link_not_found')], 'encode');
            break;
        }

        $comment = DB::queryFirstRow(
            'SELECT id, kb_id, author_id
            FROM ' . prefixTable('kb_comments') . '
            WHERE id = %i',
            $commentId
        );
        if ($comment === null || (int) ($comment['kb_id'] ?? 0) !== $kbId) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('kb_comment_not_found')], 'encode');
            break;
        }
        if (!kbCanDeleteComment($comment, $kb, $session)) {
            echo (string) prepareExchangedData(['error' => true, 'message' => $lang->get('error_not_allowed_to')], 'encode');
            break;
        }

        DB::delete(prefixTable('kb_comments'), 'id = %i', $commentId);
        kbInsertLog($SETTINGS, $session, $kbId, (string) $kb['label'], 'at_modification', 'comment_delete');
        $entry = kbBuildEntryPayload($kb, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_comment_deleted'), 'entry' => $entry], 'encode');
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
        $editionLockForSave = kbEditionLockSaveStatus($kbId, (int) $session->get('user-id'));
        if ($editionLockForSave['allowed'] !== true) {
            echo (string) prepareExchangedData(kbEditionLockErrorPayload($lang, $editionLockForSave), 'encode');
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
        emitKbEvent(
            'updated',
            $kbId,
            (string) $kb['label'],
            (string) ($session->get('user-login') ?? ''),
            (int) $session->get('user-id')
        );
        $entry = kbBuildEntryPayload($kb, $session, (string) ($SETTINGS['cpassman_url'] ?? ''));
        echo (string) prepareExchangedData(['error' => false, 'message' => $lang->get('kb_attachment_deleted'), 'entry' => $entry], 'encode');
        break;

    case 'download_attachment':
        if (kbIsAdmin($session)) {
            $session->set('system-error_code', ERR_NOT_ALLOWED);
            include TEAMPASS_ROOT . '/public/error.php';
            exit;
        }
        if ($key !== (string) $session->get('key')) {
            $session->set('system-error_code', ERR_NOT_ALLOWED);
            include TEAMPASS_ROOT . '/public/error.php';
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
                        'allow_comments' => (int) ($entry['allow_comments'] ?? 0),
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
                        'allow_comments' => (int) ($entry['allow_comments'] ?? 0),
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
            kbReassignCommentsToKbId((int) ($entry['original_id'] ?? 0), $newKbId);
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
            kbPurgeCommentsForKbIds(array_values(array_unique(array_filter($kbIdsToPurge))));
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
