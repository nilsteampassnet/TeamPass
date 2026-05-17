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

$installDir = realpath(__DIR__);
if ($installDir === false) {
    // Directory already gone
    echo json_encode(['status' => 'ok']);
    exit;
}

/**
 * Recursively delete a directory and all its contents.
 *
 * Uses scandir() instead of glob() so dotfiles (e.g. .htaccess) are included.
 * Attempts chmod before each unlink/rmdir to maximise chances of success
 * when file ownership differs from the web server user.
 *
 * @param string $dir Absolute path to the directory to delete.
 * @return string[]   Paths that could not be removed (empty = full success).
 */
function robustDeleteDir(string $dir): array
{
    $failures = [];

    $entries = scandir($dir);
    if ($entries === false) {
        return [$dir];
    }

    foreach (array_diff($entries, ['.', '..']) as $entry) {
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        if (is_link($path)) {
            if (@unlink($path) === false) {
                $failures[] = $path;
            }
        } elseif (is_dir($path)) {
            $failures = array_merge($failures, robustDeleteDir($path));
        } else {
            @chmod($path, 0666);
            if (@unlink($path) === false) {
                $failures[] = $path;
            }
        }
    }

    if (empty($failures)) {
        @chmod($dir, 0777);
        if (@rmdir($dir) === false) {
            $failures[] = $dir;
        }
    }

    return $failures;
}

// After the HTTP response is sent, make a last-resort pass over whatever
// remains — this handles platforms where open file handles on executing
// scripts prevent rmdir in the main pass.
register_shutdown_function(static function() use ($installDir): void {
    if (is_dir($installDir) === false) {
        return;
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($installDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir()) {
            @chmod($item->getPathname(), 0777);
            @rmdir($item->getPathname());
        } else {
            @chmod($item->getPathname(), 0666);
            @unlink($item->getPathname());
        }
    }
    @rmdir($installDir);
});

$remaining = robustDeleteDir($installDir);

if (empty($remaining)) {
    echo json_encode(['status' => 'ok']);
    exit;
}

$relativeRemaining = array_values(array_map(
    static fn(string $p): string => str_replace($installDir . DIRECTORY_SEPARATOR, '', $p),
    $remaining
));

error_log('[TeamPass] install cleanup partial — could not remove: ' . implode(', ', $relativeRemaining));

echo json_encode([
    'status' => 'partial',
    'remaining' => $relativeRemaining,
    'msg' => 'Some files could not be deleted. A retry will occur automatically on next admin login.',
]);
