<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      UserModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2026 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

class UserModel
{
    /**
     * Returns a safe subset of user columns — never exposes credential or key material.
     *
     * @param int $limit Maximum rows to return (capped to 500).
     * @return array
     */
    public function getUsers(int $limit): array
    {
        $limit = min(max(1, $limit), 500);
        return DB::query(
            'SELECT id, login, name, lastname, email, admin, gestionnaire, disabled,
                    last_connection_time, is_ready_for_usage, personal_folder, auth_type
            FROM ' . prefixTable('users') . '
            WHERE deleted_at IS NULL
            ORDER BY id ASC LIMIT %i',
            $limit
        );
    }
}
