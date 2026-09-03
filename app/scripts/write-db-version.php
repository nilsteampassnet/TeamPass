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
 * ---
 * @file      write-db-version.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * CLI helper for the Docker entrypoint. Records the TeamPass version reached by
 * the database in teampass_misc, the counterpart of read-db-version.php.
 *
 * The standalone upgrade_run_X.Y.Z.php scripts apply the schema changes but
 * never write teampass_version: only the web wizard (install/upgrade_ajax.php)
 * does. The container auto-upgrade therefore left the recorded version frozen,
 * which made it replay the same migration on every boot and kept the install
 * directory reachable forever. The entrypoint now calls this helper after each
 * successful migration step, so the chain advances one version at a time and is
 * resumable when a later step fails.
 *
 * Usage: php write-db-version.php <X.Y.Z>
 * Exit code 0 on success, 1 otherwise (the caller keeps the install directory).
 */

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

$settingsFile = __DIR__ . '/../config/settings.php';
$includeFile  = __DIR__ . '/../config/include.php';
$autoloadFile = __DIR__ . '/../vendor/autoload.php';

// The version to record is passed by the entrypoint, which walks the upgrade
// chain step by step. Accept only a plain dotted numeric version.
$version = isset($argv[1]) === true ? trim((string) $argv[1]) : '';
if (preg_match('/^[0-9]+(\.[0-9]+){1,2}$/', $version) !== 1) {
    fwrite(STDERR, 'write-db-version: invalid or missing version argument' . PHP_EOL);
    exit(1);
}

// Not installed yet, or dependencies missing: nothing to record.
if (is_file($settingsFile) === false || is_file($autoloadFile) === false) {
    fwrite(STDERR, 'write-db-version: instance not installed' . PHP_EOL);
    exit(1);
}

require_once $settingsFile;   // DB_HOST, DB_USER, DB_PASSWD, DB_NAME, DB_PREFIX, DB_PORT, SECUREFILE
require_once $includeFile;    // TEAMPASS_SECRETS
require_once $autoloadFile;   // Defuse\Crypto

// The DB password is Defuse-encrypted in settings.php with the SECUREFILE
// master key. Decrypt it exactly like read-db-version.php does.
$dbPassword = DB_PASSWD;
if (strncmp($dbPassword, 'def', 3) === 0) {
    $keyFile = TEAMPASS_SECRETS . '/' . SECUREFILE;
    if (is_file($keyFile) === false) {
        fwrite(STDERR, 'write-db-version: master key file not found' . PHP_EOL);
        exit(1);
    }
    try {
        $key = Key::loadFromAsciiSafeString((string) file_get_contents($keyFile));
        $dbPassword = Crypto::decrypt($dbPassword, $key);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'write-db-version: cannot decrypt the database password' . PHP_EOL);
        exit(1);
    }
}

// Build the table name from the trusted config prefix (identifiers cannot be
// bound); reject anything unexpected as a defense-in-depth measure.
$prefix = defined('DB_PREFIX') === true ? (string) DB_PREFIX : 'teampass_';
if (preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1) {
    fwrite(STDERR, 'write-db-version: unexpected table prefix' . PHP_EOL);
    exit(1);
}
$table = $prefix . 'misc';

mysqli_report(MYSQLI_REPORT_OFF);
$link = @mysqli_connect(DB_HOST, DB_USER, $dbPassword, DB_NAME, (int) DB_PORT);
if ($link === false) {
    fwrite(STDERR, 'write-db-version: cannot connect to the database' . PHP_EOL);
    exit(1);
}

/**
 * Run a prepared statement with a single string parameter.
 *
 * @param mysqli $link  Open database connection.
 * @param string $query SQL statement holding exactly one placeholder.
 * @param string $param Value to bind.
 *
 * @return bool True when the statement executed successfully.
 */
function wdvExecute($link, string $query, string $param): bool
{
    $stmt = mysqli_prepare($link, $query);
    if ($stmt === false) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $param);
    $done = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $done !== false;
}

// Mirror install/upgrade_ajax.php: when only the legacy key exists (TeamPass
// 3.1.5.x and earlier), rename it instead of leaving two competing rows behind.
$existing = @mysqli_query(
    $link,
    'SELECT COUNT(*) FROM `' . $table . "` WHERE type = 'admin' AND intitule = 'teampass_version'"
);
$hasCurrentKey = $existing !== false && (int) (mysqli_fetch_row($existing)[0] ?? 0) > 0;

if ($hasCurrentKey === true) {
    $ok = wdvExecute(
        $link,
        'UPDATE `' . $table . "` SET valeur = ? WHERE type = 'admin' AND intitule = 'teampass_version'",
        $version
    );
} else {
    $ok = wdvExecute(
        $link,
        'UPDATE `' . $table . "` SET valeur = ?, intitule = 'teampass_version'"
        . " WHERE type = 'admin' AND intitule = 'cpassman_version'",
        $version
    );

    // Neither key was present (a database that never went through the wizard):
    // create the row so the next boot reads a version instead of nothing.
    if ($ok === true && mysqli_affected_rows($link) === 0) {
        $ok = wdvExecute(
            $link,
            'INSERT INTO `' . $table . "` (type, intitule, valeur) VALUES ('admin', 'teampass_version', ?)",
            $version
        );
    }
}

if ($ok === false) {
    fwrite(STDERR, 'write-db-version: ' . mysqli_error($link) . PHP_EOL);
    mysqli_close($link);
    exit(1);
}

mysqli_close($link);
exit(0);
