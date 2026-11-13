<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\EmailUpgradeProbe;

use function TeamPass\Tests\SourceStructure\lastCatchSource;
use function TeamPass\Tests\SourceStructure\variableOffset;

require __DIR__ . '/php_source_structure.php';
ini_set('error_log', 'php://stderr');
define('TEAMPASS_ROOT', dirname(__DIR__, 2));
$mode = $argv[1];
$recovery = str_starts_with($mode, 'recovery-');
if ($recovery) {
    $mode = substr($mode, strlen('recovery-'));
    define('TEAMPASS_RECOVERY_MIGRATION', true);
}
$statePath = $argv[2];
$state = ['timestamp_written' => false, 'queries_after_failure' => 0, 'failed' => false];
register_shutdown_function(static function () use ($statePath, &$state): void {
    file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
});

/** Exercise all six query failure branches, without connecting to an installation. */
function mysqli_query(object $connection, string $sql): mixed
{
    global $mode, $state;
    if ($state['failed']) { ++$state['queries_after_failure']; }
    $matches = match ($mode) {
        'settings' => str_contains($sql, 'INSERT IGNORE'),
        'tokens', 'exception' => str_contains($sql, 'CREATE TABLE'),
        'sharing' => str_contains($sql, 'CREATE TABLE') && str_contains($sql, 'secure_send_email'),
        'columns' => str_contains($sql, 'SHOW COLUMNS'),
        'scheduling' => str_contains($sql, 'ADD COLUMN'),
        'index_inspect' => str_contains($sql, 'SHOW INDEX'),
        'index_create' => str_contains($sql, 'ADD INDEX'),
        'timestamp' => str_contains($sql, 'upgrade_timestamp'),
        default => false,
    };
    if ($matches) {
        $state['failed'] = true;
        if ($mode === 'exception') { throw new \RuntimeException('private-database-detail'); }
        if ($mode === 'timestamp') { throw new \LogicException('private-non-email-detail'); }
        return false;
    }
    if (str_contains($sql, 'upgrade_timestamp')) { $state['timestamp_written'] = true; }
    return new \stdClass();
}
/** The fixture never interpolates real settings or secrets. */
function mysqli_real_escape_string(object $connection, string $value): string { return addslashes($value); }
/** Missing columns and index exercise the creation paths. */
function mysqli_num_rows(object $result): int { return 0; }
/** No real database resources are held. */
function mysqli_free_result(object $result): void {}
/** No real connection is opened. */
function mysqli_close(object $connection): void {}

final class ConfigManager
{
    /** The synthetic migration has no APCu cache to invalidate. */
    public static function invalidateCache(): void {}
}

$source = (string) file_get_contents(dirname(__DIR__, 2) . '/public/install/upgrade_run_3.2.4.php');
$start = variableOffset($source, '$emailUpgradeFailure');
$db_link = new \stdClass();
$pre = 'email_upgrade_fixture_';
if ($recovery) {
    $recoverySource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/scripts/recovery-migrate.php');
    eval('namespace ' . __NAMESPACE__ . '; use Throwable; use RuntimeException; try {' . substr($source, $start) . '}' . lastCatchSource($recoverySource));
} else {
    eval('namespace ' . __NAMESPACE__ . '; use Throwable; use RuntimeException;' . substr($source, $start));
}
