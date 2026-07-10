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
 * @file      reviews.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Access Recertification Campaigns (F2 — Enterprise governance).
 *
 * Pure, DB-free campaign logic (unit-tested by
 * tests/Unit/AccessReviewsLogicTest.php). A campaign snapshots the
 * role↔folder grants at launch; each grant is then attested (access is
 * still needed) or revoked (grant is removed) by a reviewer, and the
 * decisions are kept as audit evidence.
 */

/**
 * Valid decisions a reviewer can take on a review item.
 *
 * @param string $decision Candidate decision
 *
 * @return bool True when the decision is one of 'attested' | 'revoked'
 */
function reviewsValidDecision(string $decision): bool
{
    return in_array($decision, ['attested', 'revoked'], true);
}

/**
 * Can a decision be recorded on this review item?
 *
 * Decisions are immutable evidence: only a pending item of an open
 * campaign can be decided — no overwrite, no decision on a closed campaign.
 *
 * @param string $reviewStatus    Campaign status ('open' | 'closed')
 * @param string $currentDecision Item decision ('pending' | 'attested' | 'revoked')
 *
 * @return bool True when the decision can be recorded
 */
function reviewsCanDecide(string $reviewStatus, string $currentDecision): bool
{
    return $reviewStatus === 'open' && $currentDecision === 'pending';
}

/**
 * Can a campaign be closed?
 *
 * A campaign is auditor evidence: it closes only when every grant has been
 * decided — no half-reviewed evidence.
 *
 * @param string $reviewStatus Campaign status
 * @param int    $pendingCount Number of items still pending
 *
 * @return bool True when the campaign can be closed
 */
function reviewsCanClose(string $reviewStatus, int $pendingCount): bool
{
    return $reviewStatus === 'open' && $pendingCount === 0;
}

/**
 * Build the snapshot rows for a new campaign from the current grants.
 *
 * Role and folder titles are copied (denormalised) on purpose: the evidence
 * must stay readable even if a role or folder is later renamed or deleted.
 *
 * @param array<int, array<string, mixed>> $grants   Rows {role_id, role_title, folder_id, folder_title, type}
 * @param int                              $reviewId The campaign id
 *
 * @return array<int, array<string, int|string>> Rows for the access_review_items table
 */
function reviewsBuildSnapshotRows(array $grants, int $reviewId): array
{
    $rows = [];
    $seen = [];
    foreach ($grants as $grant) {
        $roleId = (int) ($grant['role_id'] ?? 0);
        $folderId = (int) ($grant['folder_id'] ?? 0);
        if ($roleId <= 0 || $folderId <= 0) {
            continue;
        }
        // One decision per (role, folder) pair
        $key = $roleId . '-' . $folderId;
        if (isset($seen[$key]) === true) {
            continue;
        }
        $seen[$key] = true;

        $rows[] = [
            'review_id' => $reviewId,
            'role_id' => $roleId,
            'role_title' => (string) ($grant['role_title'] ?? ''),
            'folder_id' => $folderId,
            'folder_title' => (string) ($grant['folder_title'] ?? ''),
            'access_type' => (string) ($grant['type'] ?? ''),
            'decision' => 'pending',
        ];
    }

    return $rows;
}

/**
 * Compute campaign progress from decision counters.
 *
 * @param int $pending  Items still pending
 * @param int $attested Items attested
 * @param int $revoked  Items revoked
 *
 * @return array{total: int, decided: int, percent: float} Progress summary
 */
function reviewsProgress(int $pending, int $attested, int $revoked): array
{
    $total = $pending + $attested + $revoked;
    $decided = $attested + $revoked;

    return [
        'total' => $total,
        'decided' => $decided,
        'percent' => $total > 0 ? round($decided * 100 / $total, 1) : 0.0,
    ];
}
