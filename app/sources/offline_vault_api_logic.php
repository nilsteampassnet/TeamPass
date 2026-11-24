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
 * @file      offline_vault_api_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/*
 * Decisions of GET /offlineVault/policy: whether a browser extension user may keep an
 * encrypted copy of their items on their computer, and within which limits. DB-free.
 *
 * The extension enforces the limits on the user's machine — it erases its copy when the
 * retention runs out without a successful contact, and as soon as a policy answer says it is
 * no longer allowed. Nothing here can recall a copy already made: the policy is what the next
 * contact applies.
 */

/** Bounds of the retention an administrator may grant, in days. */
const OFFLINE_VAULT_RETENTION_MIN_DAYS = 1;
const OFFLINE_VAULT_RETENTION_MAX_DAYS = 365;
const OFFLINE_VAULT_RETENTION_DEFAULT_DAYS = 14;

/** Bounds of the shortest automatic synchronization interval, in minutes. */
const OFFLINE_VAULT_SYNC_MIN_MINUTES = 15;
const OFFLINE_VAULT_SYNC_MAX_MINUTES = 1440;
const OFFLINE_VAULT_SYNC_DEFAULT_MINUTES = 60;

/**
 * Clamp the retention an administrator typed.
 *
 * @param mixed $value Stored setting (string from teampass_misc), null when absent
 * @return int Days, within OFFLINE_VAULT_RETENTION_MIN_DAYS..OFFLINE_VAULT_RETENTION_MAX_DAYS
 */
function offlineVaultRetentionDays($value): int
{
    if ($value === null || $value === '' || is_numeric($value) === false) {
        return OFFLINE_VAULT_RETENTION_DEFAULT_DAYS;
    }

    return min(OFFLINE_VAULT_RETENTION_MAX_DAYS, max(OFFLINE_VAULT_RETENTION_MIN_DAYS, (int) $value));
}

/**
 * Clamp the shortest automatic synchronization interval an administrator typed.
 *
 * @param mixed $value Stored setting, null when absent
 * @return int Minutes, within OFFLINE_VAULT_SYNC_MIN_MINUTES..OFFLINE_VAULT_SYNC_MAX_MINUTES
 */
function offlineVaultMinSyncMinutes($value): int
{
    if ($value === null || $value === '' || is_numeric($value) === false) {
        return OFFLINE_VAULT_SYNC_DEFAULT_MINUTES;
    }

    return min(OFFLINE_VAULT_SYNC_MAX_MINUTES, max(OFFLINE_VAULT_SYNC_MIN_MINUTES, (int) $value));
}

/**
 * Role ids allowed to keep an offline vault. An empty list means every role.
 *
 * Stored by the generic select2 save as a JSON list of numbers, e.g. [1,3].
 *
 * @param mixed $value Stored setting
 * @return int[] Positive role ids
 */
function offlineVaultAllowedRoles($value): array
{
    $ids = [];
    foreach (explode(',', str_replace(['"', '[', ']', ' '], '', (string) ($value ?? ''))) as $chunk) {
        if ($chunk !== '' && ctype_digit($chunk) === true && (int) $chunk > 0) {
            $ids[] = (int) $chunk;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Build the policy a browser extension applies to its offline vault.
 *
 * Fails closed: a missing setting reads as disabled, an administrator is refused (they have no
 * item access), and a role restriction the caller does not meet refuses. A read-only account
 * may keep a copy: reading is all an offline vault does.
 *
 * @param array $settings TeamPass settings
 * @param array $userData Authenticated API user: fresh is_admin, the token's roles
 * @return array Policy body
 */
function offlineVaultApiPolicy(array $settings, array $userData): array
{
    $enabled = (int) ($settings['offline_vault_enabled'] ?? 0) === 1;
    $reason = null;
    if ($enabled === false) {
        $reason = 'disabled';
    } elseif ((int) ($userData['is_admin'] ?? 0) === 1) {
        $reason = 'admin';
    } else {
        $allowedRoles = offlineVaultAllowedRoles($settings['offline_vault_roles'] ?? '');
        if ($allowedRoles !== []) {
            $heldRoles = offlineVaultAllowedRoles(str_replace(';', ',', (string) ($userData['roles'] ?? '')));
            if (array_intersect($allowedRoles, $heldRoles) === []) {
                $reason = 'role_not_allowed';
            }
        }
    }

    $maxDays = offlineVaultRetentionDays($settings['offline_vault_max_retention_days'] ?? null);

    return [
        'enabled' => $enabled,
        'allowed' => $reason === null,
        'reason' => $reason,
        'retention_days' => ['max' => $maxDays, 'default' => min(7, $maxDays)],
        'totp_allowed' => (int) ($settings['offline_vault_totp_allowed'] ?? 0) === 1,
        // A missing row keeps personal folders: they are the user's own items.
        'personal_folders_allowed' => (int) ($settings['offline_vault_personal_folders'] ?? 1) === 1,
        'min_sync_interval_minutes' => offlineVaultMinSyncMinutes($settings['offline_vault_min_sync_minutes'] ?? null),
    ];
}
