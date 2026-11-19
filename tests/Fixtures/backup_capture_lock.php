<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

define('TASKS_LOCK_FILE', $argv[1] . '/handler.lock');
require __DIR__ . '/../../app/sources/backup.functions.php';
require __DIR__ . '/../../app/scripts/backgroundTaskLock.php';
/** Use fictitious connection metadata for the shared SQL lock. */
function prefixTable(string $table): string { return 'fixture_' . $table; }
/** Emulate the SQL lock and maintenance state without a database. */
final class DB
{
    public static bool $allow = true;
    public static function getMDB(): object { return (object) ['nested_transactions_count' => 0, 'dbName' => 'fixture']; }
    public static function queryFirstField(...$args): int { return self::$allow ? 1 : 0; }
    public static function queryFirstRow(...$args): array { return ['valeur' => '0']; }
    public static function update(...$args): void {}
    public static function query(...$args): array { return []; }
}
$capture = new TeamPassBackupCapture(true);
$contender = new BackgroundTaskLock(TASKS_LOCK_FILE);
$blocked = !$contender->acquire();
$busyReason = $contender->getFailureReason();
unset($capture);
$released = $contender->acquire();
$contender->release();
DB::$allow = false;
$refused = false;
try { new TeamPassBackupCapture(true); } catch (RuntimeException $exception) { $refused = true; }
$releasedAfterFailure = $contender->acquire();
$contender->release();
mkdir(TASKS_LOCK_FILE);
$permissionsReported = false;
try {
    new TeamPassBackupCapture(true);
} catch (RuntimeException $exception) {
    $permissionsReported = str_contains($exception->getMessage(), 'permissions');
} finally {
    rmdir(TASKS_LOCK_FILE);
}
echo json_encode(compact('blocked', 'released', 'refused', 'releasedAfterFailure', 'busyReason', 'permissionsReported'), JSON_THROW_ON_ERROR);
