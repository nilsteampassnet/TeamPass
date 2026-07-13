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
 * @file      learning.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * In-Context Micro-Learning (F11 — Scale & polish).
 *
 * Pure, DB-free tip catalogue + rotation logic (unit-tested by
 * tests/Unit/MicroLearningLogicTest.php). The rendering and the dismiss
 * persistence (localStorage) live in app/core/micro-learning.js.php.
 */

/**
 * The micro-learning tip catalogue.
 *
 * Contexts:
 *  - 'item_form'      — the new/edit item form is opened
 *  - 'password_focus' — the password field gets the focus
 *  - 'otv_share'      — the one-time-view share dialog is opened
 *  - 'daily'          — first-week rotation (one tip a day on login)
 *
 * Every 'lang' key must exist in every language file — enforced by the
 * sentinel unit test.
 *
 * @return array<int, array{id: string, context: string, lang: string}>
 */
function microLearningTipCatalogue(): array
{
    return [
        ['id' => 'unique_password', 'context' => 'item_form', 'lang' => 'microlearning_tip_unique_password'],
        ['id' => 'passphrase', 'context' => 'password_focus', 'lang' => 'microlearning_tip_passphrase'],
        ['id' => 'secure_share', 'context' => 'otv_share', 'lang' => 'microlearning_tip_secure_share'],
        ['id' => 'what_is_mfa', 'context' => 'daily', 'lang' => 'microlearning_tip_what_is_mfa'],
        ['id' => 'reuse_risk', 'context' => 'daily', 'lang' => 'microlearning_tip_reuse_risk'],
        ['id' => 'phishing', 'context' => 'daily', 'lang' => 'microlearning_tip_phishing'],
        ['id' => 'browser_extension', 'context' => 'daily', 'lang' => 'microlearning_tip_browser_extension'],
        ['id' => 'breach_check', 'context' => 'daily', 'lang' => 'microlearning_tip_breach_check'],
        ['id' => 'expiry', 'context' => 'daily', 'lang' => 'microlearning_tip_expiry'],
        ['id' => 'lock_leaving', 'context' => 'daily', 'lang' => 'microlearning_tip_lock_leaving'],
    ];
}

/**
 * Pick the daily-rotation tip for a given day, skipping dismissed tips.
 *
 * Deterministic: the same day always yields the same tip for the same
 * dismissed set, rotating through the catalogue day after day.
 *
 * @param array<int, array{id: string, context: string, lang: string}> $catalogue    Full catalogue
 * @param int                                                          $dayIndex     Day counter (e.g. days since epoch)
 * @param string[]                                                     $dismissedIds Tip ids the user dismissed
 *
 * @return array{id: string, context: string, lang: string}|null The tip to show, or null when none remains
 */
function microLearningDailyTip(array $catalogue, int $dayIndex, array $dismissedIds = []): ?array
{
    $daily = array_values(array_filter($catalogue, static function (array $tip) use ($dismissedIds): bool {
        return $tip['context'] === 'daily' && in_array($tip['id'], $dismissedIds, true) === false;
    }));

    if (count($daily) === 0) {
        return null;
    }

    return $daily[abs($dayIndex) % count($daily)];
}
