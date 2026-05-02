<?php
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
 * @file      cleanup_install.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid request method']);
    exit;
}

session_start();

// Authorization check:
// - Upgrade flow: session has user_granted = '1'
// - Install flow: settings.php was created (proof of completed install)
$isUpgradeAuthorized = isset($_SESSION['user_granted']) && $_SESSION['user_granted'] === '1';
$isInstallAuthorized = file_exists(__DIR__ . '/../../app/config/settings.php');

if ($isUpgradeAuthorized === false && $isInstallAuthorized === false) {
    echo json_encode(['status' => 'error', 'msg' => 'Not authorized']);
    exit;
}

require_once __DIR__ . '/tp.functions.php';

$installDir = realpath(__DIR__);
if ($installDir === false) {
    // Directory already gone
    echo json_encode(['status' => 'ok']);
    exit;
}

deleteAllFolder($installDir);

echo json_encode(['status' => 'ok']);
