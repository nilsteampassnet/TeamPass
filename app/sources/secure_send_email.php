<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/secure_send_access.php';
require_once __DIR__ . '/secure_send_logic.php';
require_once __DIR__ . '/secure_send_url.php';
require_once __DIR__ . '/emails_queue_logic.php';

/** Return the metadata-only delivery association shared by installation and upgrade. */
function secureSendEmailSchemaSql(string $table): string
{
    if (preg_match('/^[a-zA-Z0-9_]+$/D', $table) !== 1) {
        throw new InvalidArgumentException('Invalid delivery table name');
    }
    return 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
        `send_id` INT UNSIGNED NOT NULL,
        `task_id` INT NOT NULL,
        `originator` INT UNSIGNED NOT NULL,
        `created_at` BIGINT UNSIGNED NOT NULL,
        `address_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (`send_id`),
        UNIQUE KEY `idx_task` (`task_id`),
        KEY `idx_sender_period` (`originator`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
}

/** Accept exactly one mailbox, without display names, lists or header control characters. */
function secureSendEmailRecipient(mixed $value): string
{
    if (!is_string($value) || strlen($value) > 254 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
        throw new InvalidArgumentException('invalid_email');
    }
    $value = trim($value);
    // The shared transport splits recipient strings on commas; quoted lists cannot be represented safely.
    if (strpbrk($value, ',;<>') !== false || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('invalid_email');
    }
    return $value;
}

/** Check whether a sending identity has been configured; provider acceptance is checked later. */
function secureSendEmailIsConfigured(array $settings): bool
{
    if (filter_var($settings['email_from'] ?? '', FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    return match ($settings['email_transport'] ?? 'smtp') {
        'smtp' => ($settings['email_smtp_auth_mode'] ?? '') === 'microsoft_oauth'
            ? trim((string) ($settings['email_microsoft_mailbox'] ?? '')) !== ''
            : trim((string) ($settings['email_smtp_server'] ?? '')) !== '',
        'microsoft_graph' => trim((string) ($settings['email_microsoft_mailbox'] ?? '')) !== '',
        'gmail_api' => trim((string) ($settings['email_google_sender'] ?? '')) !== '',
        default => false,
    };
}

/** Serialize per-sender reservations; failed and pending deliveries also consume the hourly budget. */
function secureSendEmailReserve(int $originator, int $now): void
{
    $user = DB::queryFirstRow('SELECT id FROM ' . prefixTable('users') . '
        WHERE id = %i AND disabled = 0 AND deleted_at IS NULL FOR UPDATE', $originator);
    if (!$user) {
        throw new InvalidArgumentException('not_allowed');
    }
    $count = (int) DB::queryFirstField('SELECT COUNT(*) FROM ' . prefixTable('secure_send_email') . '
        WHERE originator = %i AND created_at > %i', $originator, $now - 3600);
    if ($count >= 10) {
        throw new InvalidArgumentException('email_rate_limited');
    }
}

/** Render a generic invitation; item labels, note contents and passphrases never enter the message. */
function secureSendEmailMessage(array $delivery, array $link, array $settings): array
{
    $recipient = secureSendEmailRecipient($delivery['recipient'] ?? null);
    if (!secureSendEmailIsConfigured($settings)) {
        throw new InvalidArgumentException('email_not_configured');
    }
    // Only the URL generated during this creation may be delivered. Never accept a caller's URL.
    $url = (string) $delivery['url'];
    $base = secureSendBaseUrl($settings, (int) $link['shared_globaly'] === 1);
    if (!str_starts_with($url, $base . '/index.php?') || parse_url($url, PHP_URL_SCHEME) !== 'https') {
        throw new InvalidArgumentException('email_requires_https');
    }
    $language = new \TeampassClasses\Language\Language($settings['default_language'] ?? 'english');
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $expiry = (new DateTimeImmutable('@' . (int) $link['time_limit']))
        ->setTimezone(new DateTimeZone($settings['timezone'] ?? 'UTC'))
        ->format(($settings['date_format'] ?? 'd/m/Y') . ' ' . ($settings['time_format'] ?? 'H:i:s') . ' T');
    $body = strtr($language->get('secure_send_email_body'), [
        '#link#' => $escape($url), '#expires#' => $escape($expiry),
    ]);
    return [
        'subject' => getEmailTemplateSubject('secure_send_link', $language),
        'body' => $body, 'receivers' => $recipient, 'receiver_name' => '', 'encryptedUserPassword' => '',
    ];
}

/** Insert encrypted delivery and its safe association in the caller's creation transaction. */
function secureSendEmailQueue(array $message, array $link, array $settings, int $now): void
{
    DB::insert(prefixTable('background_tasks'), [
        'created_at' => $now, 'process_type' => 'secure_send_email',
        'status' => 'queued',
        'arguments' => json_encode(emailQueueSeal($message), JSON_THROW_ON_ERROR),
    ]);
    $taskId = (int) DB::insertId();
    DB::insert(prefixTable('secure_send_email'), [
        'send_id' => (int) $link['id'], 'task_id' => $taskId,
        'originator' => (int) $link['originator'], 'created_at' => $now,
        'address_hash' => hash('sha256', secureSendBaseUrl($settings, (int) $link['shared_globaly'] === 1)),
    ]);
}

/** Resolve profile tokens at submission time, using current disclosure policy and identity. */
function secureSendEmailPersonalize(array $message, int $taskId, array $settings): array
{
    if (!str_contains($message['subject'], '#sender#') && !str_contains($message['body'], '#sender#')
        && !str_contains($message['subject'], '#sender_prefix#') && !str_contains($message['body'], '#sender_prefix#')) {
        return $message;
    }
    $name = '';
    if ((int) ($settings['secure_send_show_sender_name'] ?? 0) === 1) {
        $link = DB::queryFirstRow('SELECT o.originator, o.item_id, o.send_type FROM ' . prefixTable('secure_send_email') . ' e
            INNER JOIN ' . prefixTable('otv') . ' o ON o.id = e.send_id AND o.originator = e.originator
            WHERE e.task_id = %i', $taskId) ?: [];
        $sender = secureSendPublicSender($link, true);
        if ($sender === null) {
            throw new RuntimeException('Secure Send is no longer available; email was not submitted.');
        }
        // Decode through the public-identity helper, then remove header controls and Unicode separators.
        $name = trim(preg_replace('/[\p{C}\p{Z}]+/u', ' ', $sender['display_name']) ?? '');
    }
    $language = new \TeampassClasses\Language\Language($settings['default_language'] ?? 'english');
    $replacements = [
        '#sender#' => $name !== '' ? $name : $language->getShipped('secure_send_email_anonymous_sender'),
        '#sender_prefix#' => $name !== '' ? $name . ' — ' : '',
    ];
    // The subject is plain text. A customized HTML body needs its own contextual escaping.
    $message['subject'] = strtr($message['subject'], $replacements);
    $message['body'] = strtr($message['body'], array_map(
        static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $replacements
    ));
    return $message;
}

/** Recheck current policy and source access immediately before each attempt, without consuming a view. */
function secureSendEmailCanDeliver(int $taskId, array $settings, ?int $now = null): bool
{
    $link = DB::queryFirstRow('SELECT o.*, e.address_hash FROM ' . prefixTable('secure_send_email') . ' e
        INNER JOIN ' . prefixTable('otv') . ' o ON o.id = e.send_id AND o.originator = e.originator
        WHERE e.task_id = %i', $taskId) ?: [];
    if (!$link || !secureSendIsAvailable($link, $settings, $now ?? time())
        || secureSendReadEligibleOriginator((int) $link['originator']) === []) {
        return false;
    }
    // Address changes must not send an old URL after an administrator changes the sharing host.
    try {
        $base = secureSendBaseUrl($settings, (int) $link['shared_globaly'] === 1);
        if (parse_url($base, PHP_URL_SCHEME) !== 'https'
            || !hash_equals((string) $link['address_hash'], hash('sha256', $base))) {
            return false;
        }
    } catch (InvalidArgumentException $e) {
        return false;
    }
    if ($link['send_type'] !== 'note') {
        $item = secureSendReadItem((int) $link['item_id'], (int) $link['originator']);
        if (!$item) {
            return false;
        }
        if ((int) ($settings['enable_delete_after_consultation'] ?? 0) === 1) {
            $deletion = DB::queryFirstRow('SELECT del_type, del_value FROM ' . prefixTable('automatic_del') . '
                WHERE item_id = %i AND del_enabled = 1', (int) $link['item_id']) ?: [];
            if (($deletion['del_type'] ?? null) !== null
                && (((int) $deletion['del_type'] === 1 && (int) $deletion['del_value'] <= 0)
                    || ((int) $deletion['del_type'] === 2 && (int) $deletion['del_value'] <= ($now ?? time())))) {
                return false;
            }
        }
    }
    return true;
}

/** Read delivery outcomes for the authenticated owner's links; never return a recipient or URL. */
function secureSendEmailStatuses(int $originator): array
{
    $statuses = [];
    foreach (DB::query('SELECT e.send_id, t.status FROM ' . prefixTable('secure_send_email') . ' e
        LEFT JOIN ' . prefixTable('background_tasks') . ' t ON t.increment_id = e.task_id
        INNER JOIN ' . prefixTable('otv') . ' o ON o.id = e.send_id AND o.originator = e.originator
        WHERE e.originator = %i', $originator) as $row) {
        $statuses[(int) $row['send_id']] = match ($row['status']) {
            'completed' => 'accepted', 'failed' => 'failed',
            'queued', 'in_progress' => 'queued',
            default => 'unknown',
        };
    }
    return $statuses;
}

/** Remove bounded obsolete associations only after their quota window and delivery have ended. */
function secureSendEmailPruneMetadata(int $now): void
{
    $ids = DB::queryFirstColumn('SELECT e.send_id FROM ' . prefixTable('secure_send_email') . ' e
        LEFT JOIN ' . prefixTable('otv') . ' o ON o.id = e.send_id
        LEFT JOIN ' . prefixTable('background_tasks') . ' t ON t.increment_id = e.task_id
        WHERE e.created_at <= %i AND o.id IS NULL
        AND (t.increment_id IS NULL OR t.status IN (%s, %s)) LIMIT 100', $now - 3600, 'completed', 'failed');
    if ($ids !== []) {
        DB::delete(prefixTable('secure_send_email'), 'send_id IN %li', $ids);
    }
}
