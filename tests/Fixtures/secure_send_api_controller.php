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
 * @file      secure_send_api_controller.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

// Router-provided dependencies of SecureSendController, without booting an instance.
// Load after secure_send_memory_db.php and secure_send_dependencies.php.

if (!defined('API_ROOT_PATH')) {
    define('API_ROOT_PATH', dirname(__DIR__, 2) . '/app/api');
}

/** Record the activity marks BaseController::markApiFunctionalActivity() makes. */
function markUserFunctionalActivity(int $userId, ?int $timestamp = null, int $minInterval = 300): void
{
    $GLOBALS['secureSendApiActivity'][] = $userId;
}

/** The router has already loaded the database layer. */
function loadClasses(string $className = ''): void
{
    $GLOBALS['secureSendApiLoadedClasses'][] = $className;
}

/** API folder resolver: answers what the test configures and records each call. */
class FolderAccessModel
{
    public static bool $canAccess = true;
    public static bool $satisfiesRestriction = true;
    public static array $calls = [];

    public function canAccessItemInFolder(array $userData, int $folderId, int $itemId): bool
    {
        self::$calls[] = ['canAccessItemInFolder', (int) $userData['id'], $folderId, $itemId];
        return self::$canAccess;
    }

    public function satisfiesItemRestriction(int $itemId, int $userId): bool
    {
        self::$calls[] = ['satisfiesItemRestriction', $userId, $itemId];
        return self::$satisfiesRestriction;
    }
}
