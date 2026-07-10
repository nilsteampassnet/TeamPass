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
 * @file      classification.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Data Classification & Ownership (F4 — Enterprise governance).
 *
 * Pure, DB-free classification logic (unit-tested by
 * tests/Unit/ClassificationLogicTest.php). MVP scope per the strategy
 * analysis: labelling + filtering + reporting — policy ENFORCEMENT is
 * deliberately deferred. Classification is metadata, never secret
 * content, so no crypto is involved.
 */

/**
 * The fixed classification scale.
 *
 * Level 0 (no row in the classification table) means "unclassified".
 * Keys are the levels, values are the language-file suffixes.
 *
 * @return array<int, string> level => slug
 */
function classificationLevels(): array
{
    return [
        0 => 'unclassified',
        1 => 'public',
        2 => 'internal',
        3 => 'confidential',
        4 => 'restricted',
    ];
}

/**
 * Is this a valid classification level?
 *
 * @param int $level Candidate level (0 = clear the classification)
 *
 * @return bool True when the level exists on the scale
 */
function classificationValidLevel(int $level): bool
{
    return array_key_exists($level, classificationLevels());
}

/**
 * Language key for a classification level label.
 *
 * @param int $level Classification level
 *
 * @return string Language key (e.g. 'classification_level_restricted')
 */
function classificationLabelKey(int $level): string
{
    $levels = classificationLevels();

    return 'classification_level_' . ($levels[$level] ?? 'unclassified');
}

/**
 * Build the classification-coverage report rows (feeds the F6 Reports page).
 *
 * Metadata only: counts of classified items per level plus the derived
 * unclassified remainder. Never any item name or password.
 *
 * @param array<int, int> $levelCounts Items count per level (1..4)
 * @param int             $totalItems  Total active shared items in the vault
 *
 * @return array<int, array<string, int|string|float>> Rows {level, slug, items, percent}
 */
function classificationCoverage(array $levelCounts, int $totalItems): array
{
    $levels = classificationLevels();
    $classified = 0;
    $rows = [];

    foreach ($levels as $level => $slug) {
        if ($level === 0) {
            continue;
        }
        $count = max(0, (int) ($levelCounts[$level] ?? 0));
        $classified += $count;
        $rows[] = [
            'level' => $level,
            'slug' => $slug,
            'items' => $count,
            'percent' => $totalItems > 0 ? round($count * 100 / $totalItems, 1) : 0.0,
        ];
    }

    // Unclassified remainder (never negative, even on inconsistent inputs)
    $unclassified = max(0, $totalItems - $classified);
    array_unshift($rows, [
        'level' => 0,
        'slug' => 'unclassified',
        'items' => $unclassified,
        'percent' => $totalItems > 0 ? round($unclassified * 100 / $totalItems, 1) : 0.0,
    ]);

    return $rows;
}
