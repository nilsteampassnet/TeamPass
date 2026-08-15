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
 * @file      emails_templates_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Normalization and validation of the email templates, kept free of any database or session
 * access so the whole save contract can be unit-tested on its own — same pattern as
 * log_display_logic.php and security_posture_logic.php.
 *
 * Included by both:
 *   - app/sources/emails_templates.queries.php        (production)
 *   - tests/Unit/EmailsTemplatesLogicTest.php         (unit tests, on the real functions)
 */

use voku\helper\AntiXSS;

if (function_exists('emailsTemplatesNormalizeSubject') === false) {
    /**
     * Normalizes a subject before storage.
     *
     * A subject ends up in a mail header: no markup, no newline.
     *
     * @param string $value Raw value submitted by the administrator
     *
     * @return string
     */
    function emailsTemplatesNormalizeSubject(string $value): string
    {
        $value = strip_tags($value);
        // A line break becomes a space, then runs of whitespace are collapsed so a pasted
        // multi-line subject does not leave a double space behind.
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}

if (function_exists('emailsTemplatesNormalizeBody') === false) {
    /**
     * Normalizes a body before storage.
     *
     * Mirrors what sendMailToUser() does at send time (xss_clean plus newline removal), so what
     * the administrator sees stored is what will actually be sent.
     *
     * @param string  $value   Raw value submitted by the administrator
     * @param AntiXSS $antiXss Shared sanitizer instance
     *
     * @return string
     */
    function emailsTemplatesNormalizeBody(string $value, AntiXSS $antiXss): string
    {
        $value = (string) $antiXss->xss_clean($value);
        $value = str_replace(["\r", "\n"], '', $value);

        return trim($value);
    }
}

if (function_exists('emailsTemplatesMissingTokens') === false) {
    /**
     * Lists the required tokens missing from a body.
     *
     * A body without one of them produces an email nobody can act upon — no password, no reset
     * link, no code — so the caller must refuse the save.
     *
     * @param string             $content Body to check
     * @param array<int, string> $tokens  Tokens that must be present
     *
     * @return array<int, string> The missing tokens, in declaration order
     */
    function emailsTemplatesMissingTokens(string $content, array $tokens): array
    {
        $missing = [];
        foreach ($tokens as $token) {
            if (strpos($content, (string) $token) === false) {
                $missing[] = (string) $token;
            }
        }

        return $missing;
    }
}

if (function_exists('emailsTemplatesSampleValues') === false) {
    /**
     * Builds the sample values used to render a preview.
     *
     * The samples are deliberately realistic — the administrator's own login, the instance URL,
     * today's date — so the preview shows the shape of the real message rather than obvious
     * dummy text. Every token declared in the catalog has an entry; a token added later without
     * one falls back to a visible placeholder (see emailsTemplatesRenderPreview()).
     *
     * @param array<string, string> $context Keys: url, login, name, lastname, email, date, time,
     *                                       datetime, secret_placeholder
     *
     * @return array<string, string> [token => sample value]
     */
    function emailsTemplatesSampleValues(array $context): array
    {
        $url = (string) ($context['url'] ?? 'https://teampass.example.com');
        $login = (string) ($context['login'] ?? 'jdoe');
        $name = (string) ($context['name'] ?? 'John');
        $lastname = (string) ($context['lastname'] ?? 'Doe');
        $email = (string) ($context['email'] ?? 'jdoe@example.com');
        $date = (string) ($context['date'] ?? '2026-01-31');
        $time = (string) ($context['time'] ?? '09:15:00');
        $datetime = (string) ($context['datetime'] ?? $date . ' ' . $time);
        // #password# and #enc_code# are substituted when the email is actually sent, not when
        // it is prepared: the preview must not pretend to know them.
        $secret = (string) ($context['secret_placeholder'] ?? '••••••••');

        return [
            // Accounts
            '#login#' => $login,
            '#tp_login#' => $login,
            '#name#' => $name,
            '#firstname#' => $name,
            // '#lastname#' historically carries the FIRST name in the user emails.
            '#lastname#' => $name,
            '#password#' => $secret,
            '#enc_code#' => $secret,
            '#2FACode#' => '123456',
            '#reset_url#' => $url . '/reset-password.php?token=' . str_repeat('a', 16),
            '#tp_link#' => $url,
            '#url#' => $url,
            '#unlock_at#' => $datetime,
            // Inactive accounts
            '#inactivity_days#' => '90',
            '#grace_days#' => '7',
            '#action#' => 'disable',
            // Security notifications
            '#tp_user#' => $login,
            '#tp_name#' => $name . ' ' . $lastname,
            '#tp_email#' => $email,
            '#tp_ip#' => '203.0.113.42',
            '#tp_date#' => $date,
            '#tp_time#' => $time,
            '#tp_unlock_at#' => $datetime,
            // Security digest
            '#breached#' => '2',
            '#weak#' => '5',
            '#reused#' => '3',
            '#overdue#' => '1',
            '#total#' => '11',
            // Items
            '#label#' => 'Production database',
            '#link#' => $url . '/index.php?page=items&group=12&id=345',
            '#item_label#' => 'Production database',
            '#item_id#' => '345',
            '#item_category#' => '12',
            '#folder_name#' => 'Infrastructure',
            '#changes#' => '<ul><li>Password changed</li></ul>',
            '#tp_item#' => 'Infrastructure / Production database',
            '#tp_item_author#' => $login,
            // Scheduled backup
            '#tp_status#' => 'success',
            '#tp_datetime#' => $datetime,
            '#tp_message#' => 'Backup completed',
            '#tp_file#' => 'teampass-backup-20260131.sql',
            '#tp_size#' => '42.5 MB',
            '#tp_output_dir#' => '/var/backups/teampass',
            '#tp_retention_days#' => '30',
            '#tp_purge_deleted#' => '2',
            '#tp_externalized_report#' => '',
            '#tp_externalized_status#' => 'success',
            '#tp_externalized_message#' => 'Copy completed',
            '#tp_externalized_destination#' => 'sftp',
            '#tp_externalized_target#' => 'backup.example.com:/teampass',
            '#tp_externalized_file#' => 'teampass-backup-20260131.sql',
            '#tp_externalized_size#' => '42.5 MB',
            '#tp_externalized_retention_days#' => '30',
            '#tp_externalized_retention_count#' => '10',
            '#tp_externalized_purge_deleted#' => '1',
            '#tp_externalized_retry#' => '-',
        ];
    }
}

if (function_exists('emailsTemplatesRenderPreview') === false) {
    /**
     * Substitutes the sample values into a subject or a body.
     *
     * Tokens are replaced longest first so a marker that is the prefix of another one cannot
     * consume it. A token declared by the catalog but absent from the sample map is rendered as
     * a visible placeholder rather than left raw, which makes the omission obvious in the modal.
     *
     * @param string                $content Content to render
     * @param array<int, string>    $tokens  Tokens declared for this template
     * @param array<string, string> $samples Sample values, from emailsTemplatesSampleValues()
     *
     * @return string
     */
    function emailsTemplatesRenderPreview(string $content, array $tokens, array $samples): string
    {
        $ordered = $tokens;
        usort($ordered, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($ordered as $token) {
            $content = str_replace(
                $token,
                $samples[$token] ?? ('[' . trim($token, '#') . ']'),
                $content
            );
        }

        return $content;
    }
}

if (function_exists('emailsTemplatesUnusedTokens') === false) {
    /**
     * Lists the optional tokens the administrator dropped from a body.
     *
     * Purely informational: the save succeeds, the caller only warns.
     *
     * @param string             $content        Body to check
     * @param array<int, string> $tokens         Every token the call site substitutes
     * @param array<int, string> $requiredTokens Tokens already covered by the blocking check
     *
     * @return array<int, string>
     */
    function emailsTemplatesUnusedTokens(string $content, array $tokens, array $requiredTokens): array
    {
        $unused = [];
        foreach ($tokens as $token) {
            if (in_array($token, $requiredTokens, true) === true) {
                continue;
            }
            if (strpos($content, (string) $token) === false) {
                $unused[] = (string) $token;
            }
        }

        return $unused;
    }
}
