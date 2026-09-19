<?php

declare(strict_types=1);

use TeampassClasses\Language\Language;

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
 * @file      log_display_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Display normalization of database-backed log values, kept free of any database or session
 * access so the whole encoding contract can be unit-tested on its own — same pattern as
 * security_posture_logic.php.
 *
 * Included by both:
 *   - app/sources/main.functions.php              (production, for the log data sources)
 *   - tests/Unit/UtilitiesLogsEncodingTest.php    (unit tests, on the real function)
 */

if (function_exists('normalizeLogDisplayValue') === false) {
    /**
     * Normalize a database-backed log value for safe display.
     *
     * Older records may contain HTML entities, and some values have already been encoded more than
     * once. Decode one storage layer here, then re-escape without double encoding. The DataTables
     * renderers decode the remaining display layer and escape it again before inserting it into the
     * DOM, so legacy accents remain readable without reintroducing stored XSS.
     *
     * The escaping step is what keeps the output inert: htmlspecialchars() always escapes '<', '>',
     * '"' and "'" whatever $double_encode is worth. Disabling double encoding only leaves an
     * already-valid entity untouched, so no markup can ever survive this function.
     *
     * @param mixed $value Value read from a log-related database column
     * @return string Value with one storage entity layer removed and safely re-escaped
     */
    function normalizeLogDisplayValue(mixed $value): string
    {
        $decodedValue = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return htmlspecialchars($decodedValue, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
    }
}

/**
 * Turn an administration log label into the sentence the monitoring page displays.
 *
 * Some labels carry their own payload after a colon ("at_email_template_updated:<id>:<lang>"), so
 * the mapping cannot be a plain translation lookup. A label with no mapping is returned as it was
 * stored: an unknown administrative action must stay visible, never be blanked out.
 */
function formatAdminLogLabel(string $label, Language $lang): string
{
    $direct = [
        'at_user_added' => 'user_creation',
        'at_user_deleted' => 'user_deletion',
        'user_deleted' => 'user_deletion',
        'at_user_updated' => 'user_updated',
        'at_user_new_keys' => 'new_keys_generated',
        'at_user_keys_download' => 'user_keys_downloaded',
        'at_2fa_google_code_send_by_email' => 'mfa_code_send_by_email',
        'authentication_lockout_removed' => 'authentication_lockout_removed',
        'at_licence_trial_requested' => 'licence_trial_log_requested',
        'at_licence_trial_activated' => 'licence_trial_log_activated',
        'at_licence_trial_link_sent' => 'licence_trial_log_link_sent',
    ];
    if (isset($direct[$label]) === true) {
        return (string) $lang->get($direct[$label]);
    }

    if (strpos($label, 'at_user_email_changed') !== false) {
        $change = explode(':', $label);

        return (string) $lang->get('log_user_email_changed') . ' ' . ($change[1] ?? '');
    }
    if (strpos($label, 'at_email_template_updated:') === 0 || strpos($label, 'at_email_template_reset:') === 0) {
        // Label carries "<action>:<template id>:<language>"
        $change = explode(':', $label);

        return (string) $lang->get($change[0]) . ' ' . ($change[1] ?? '') . ' (' . ($change[2] ?? '') . ')';
    }

    return $label;
}

/**
 * Resolve a knowledge-base log row's display fields without database access.
 * The handler normalizes these fields before sending them to the text renderer.
 *
 * @return array
 */
function formatKnowledgeBaseLogRow(array $row, array $user, Language $lang): array
{
    $reasonKeys = [
        'label' => 'label',
        'category' => 'category',
        'description' => 'description',
        'anyone_can_modify' => 'anyone_can_modify',
        'allow_comments' => 'kb_allow_comments',
        'associated_items' => 'kb_associated_items',
        'attachments_upload' => 'kb_attachment_uploaded',
        'attachments_delete' => 'kb_attachment_deleted',
        'comment_add' => 'kb_comment_added',
        'comment_delete' => 'kb_comment_deleted',
    ];
    $fullName = trim(trim((string) ($user['name'] ?? '')) . ' ' . trim((string) ($user['lastname'] ?? '')));
    $login = (string) ($user['login'] ?? $row['user_login'] ?? '');
    $row['user_display'] = $fullName === '' ? $login : $fullName . ($login === '' ? '' : ' [' . $login . ']');
    $row['action_display'] = $lang->get((string) ($row['action'] ?? ''));
    $row['reason_display'] = implode(', ', array_map(
        static fn (string $reason): string => isset($reasonKeys[$reason]) ? (string) $lang->get($reasonKeys[$reason]) : $reason,
        explode(', ', (string) ($row['reason'] ?? ''))
    ));

    return $row;
}

/**
 * Match the displayed knowledge-base log values, including translated actions and details.
 */
function knowledgeBaseLogRowMatchesSearch(array $row, string $searchValue): bool
{
    if ($searchValue === '') {
        return true;
    }
    $haystack = (string) ($row['label'] ?? '') . ' ' . (string) ($row['user_display'] ?? '') . ' '
        . (string) ($row['action_display'] ?? '') . ' ' . (string) ($row['reason_display'] ?? '');

    return mb_stripos($haystack, $searchValue, 0, 'UTF-8') !== false;
}
