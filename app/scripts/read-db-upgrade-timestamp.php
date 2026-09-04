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
 * @file      read-db-upgrade-timestamp.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * CLI helper for the Docker entrypoint. Prints misc.upgrade_timestamp, the
 * schema level the database actually reached.
 *
 * This is the value the application itself tests in upgradeRequired()
 * (app/sources/main.functions.php) against the UPGRADE_MIN_DATE constant, and
 * it is the ONLY signal that distinguishes two releases sharing the same
 * TP_VERSION. teampass_misc.teampass_version only ever stores TP_VERSION
 * ("3.2.2"), never TP_VERSION_MINOR, so a patch release that ships a schema
 * change (3.2.2.1 added items.revision_changed_at) is invisible to a version
 * comparison. The entrypoint used to rely on that comparison alone, concluded
 * "up to date", skipped the migration and then deleted the install directory —
 * while the application, testing the timestamp, disabled the login button and
 * asked for an upgrade that no longer had a wizard to run.
 *
 * Prints the timestamp on success, nothing otherwise (empty output makes the
 * caller keep the install directory rather than assume "up to date").
 */

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

$settingsFile = __DIR__ . '/../config/settings.php';
$includeFile  = __DIR__ . '/../config/include.php';
$autoloadFile = __DIR__ . '/../vendor/autoload.php';

// Not installed yet, or dependencies missing: emit nothing.
if (is_file($settingsFile) === false || is_file($autoloadFile) === false) {
    exit(0);
}

require_once $settingsFile;   // DB_HOST, DB_USER, DB_PASSWD, DB_NAME, DB_PREFIX, DB_PORT, SECUREFILE
require_once $includeFile;    // TEAMPASS_SECRETS
require_once $autoloadFile;   // Defuse\Crypto

// The DB password is Defuse-encrypted in settings.php with the SECUREFILE
// master key. Decrypt it exactly like cryption()/defuseReturnDecrypted() do.
$dbPassword = DB_PASSWD;
if (strncmp($dbPassword, 'def', 3) === 0) {
    $keyFile = TEAMPASS_SECRETS . '/' . SECUREFILE;
    if (is_file($keyFile) === false) {
        exit(0);
    }
    try {
        $key = Key::loadFromAsciiSafeString((string) file_get_contents($keyFile));
        $dbPassword = Crypto::decrypt($dbPassword, $key);
    } catch (\Throwable $e) {
        // Cannot decrypt: emit nothing so the caller keeps the install directory.
        exit(0);
    }
}

// Build the table name from the trusted config prefix (identifiers cannot be
// bound); reject anything unexpected as a defense-in-depth measure.
$prefix = defined('DB_PREFIX') === true ? (string) DB_PREFIX : 'teampass_';
if (preg_match('/^[A-Za-z0-9_]*$/', $prefix) !== 1) {
    exit(0);
}
$table = $prefix . 'misc';

// Plain (non-SSL) probe, matching read-db-version.php. An SSL-only DB simply
// yields nothing here and the caller keeps the install directory so the web
// upgrade wizard (which honours DB_SSL) stays reachable.
mysqli_report(MYSQLI_REPORT_OFF);
$link = @mysqli_connect(DB_HOST, DB_USER, $dbPassword, DB_NAME, (int) DB_PORT);
if ($link === false) {
    exit(0);
}

$stmt = mysqli_prepare(
    $link,
    'SELECT valeur FROM `' . $table . "` WHERE type = 'admin' AND intitule = ? LIMIT 1"
);
if ($stmt === false) {
    mysqli_close($link);
    exit(0);
}

$settingKey = 'upgrade_timestamp';
mysqli_stmt_bind_param($stmt, 's', $settingKey);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $timestamp);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if ($found === true && $timestamp !== null && trim((string) $timestamp) !== '') {
    echo trim((string) $timestamp);
}

mysqli_close($link);
exit(0);
