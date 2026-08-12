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
