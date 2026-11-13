<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
define('LOG_TASKS', false);

$database = (string) getenv('TEAMPASS_TEST_DB');
if (preg_match('/^teampass_test_[a-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('A disposable teampass_test_* database is required.');
}
DB::$host = getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1';
DB::$port = (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306);
DB::$user = getenv('TEAMPASS_TEST_USER') ?: 'root';
DB::$password = (string) getenv('TEAMPASS_TEST_PASSWORD');
DB::$dbName = $database;
$prefix = 'email_cleanup_' . bin2hex(random_bytes(4)) . '_';

/** Bind the real handler's queries to this run's disposable tables. */
function prefixTable(string $table): string
{
    return $GLOBALS['prefix'] . $table;
}

/** Report a regression even when PHP assertions are disabled. */
function emailCleanupExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Load the production class without its installation bootstrap or CLI entry point.
$source = (string) file_get_contents(__DIR__ . '/../../app/scripts/background_tasks___handler.php');
$class = '';
$recording = false;
$depth = 0;
foreach (token_get_all($source) as $token) {
    if (is_array($token) && $token[0] === T_CLASS) {
        $recording = true;
    }
    if (!$recording) {
        continue;
    }
    $class .= is_array($token) ? $token[1] : $token;
    if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
        $depth++;
    } elseif ($token === '}' && --$depth === 0) {
        break;
    }
}
emailCleanupExpect($depth === 0 && str_starts_with($class, 'class BackgroundTasksHandler '), 'Missing handler class');
eval($class);

try {
    DB::query('CREATE TABLE %b (increment_id INT PRIMARY KEY, process_type VARCHAR(50),
        status VARCHAR(20), is_in_progress INT, updated_at BIGINT, finished_at BIGINT, error_message TEXT, arguments MEDIUMTEXT)',
        prefixTable('background_tasks'));
    DB::query('CREATE TABLE %b (increment_id INT PRIMARY KEY, task_id INT)', prefixTable('background_subtasks'));
    $now = time();
    foreach ([
        [1, 'failed', $now - 7200], // Old email without subtasks: the original INNER JOIN missed it.
        [2, 'failed', $now - 7200], // Old task with several subtasks: delete the whole group.
        [3, 'failed', $now - 60],   // Recent failure must remain.
        [4, 'completed', $now - 7200],
        [5, 'failed', 0],          // No final timestamp: do not apply retention yet.
        [6, 'queued', 0],
    ] as [$id, $status, $finishedAt]) {
        DB::insert(prefixTable('background_tasks'), ['increment_id' => $id, 'process_type' => 'send_email',
            'status' => $status, 'is_in_progress' => -1, 'updated_at' => $now, 'finished_at' => $finishedAt]);
    }
    foreach ([[1, 2], [2, 2], [3, 3], [4, 4]] as [$id, $taskId]) {
        DB::insert(prefixTable('background_subtasks'), ['increment_id' => $id, 'task_id' => $taskId]);
    }
    foreach ([[7, 'secure_send_email', $now - 1200], [8, 'send_email', $now - 1200],
        [9, 'secure_send_email', $now], [10, 'create_user_keys', $now - 1200]] as [$id, $type, $updatedAt]) {
        DB::insert(prefixTable('background_tasks'), ['increment_id' => $id, 'process_type' => $type,
            'status' => 'in_progress', 'is_in_progress' => 1, 'updated_at' => $updatedAt,
            'finished_at' => 0, 'arguments' => 'sealed-invitation-secret-canary']);
    }
    $reflection = new ReflectionClass(BackgroundTasksHandler::class);
    $handler = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('maxExecutionTime')->setValue($handler, 600);
    $reflection->getProperty('maxTimeBeforeRemoval')->setValue($handler, 3600);
    $cleanup = $reflection->getMethod('cleanupStaleTasks');
    $cleanup->invoke($handler);
    $remaining = DB::queryFirstColumn('SELECT increment_id FROM %b ORDER BY increment_id', prefixTable('background_tasks'));
    emailCleanupExpect(array_map('intval', $remaining) === [3, 4, 5, 6, 7, 8, 9, 10], 'Incorrect failed-task retention');
    $remaining = DB::queryFirstColumn('SELECT increment_id FROM %b ORDER BY increment_id', prefixTable('background_subtasks'));
    emailCleanupExpect(array_map('intval', $remaining) === [3, 4], 'Orphaned or incorrectly deleted subtasks');
    $task = DB::queryFirstRow('SELECT * FROM %b WHERE increment_id=7', prefixTable('background_tasks'));
    emailCleanupExpect($task['status'] === 'failed'
        && json_decode($task['arguments'], true) === ['failed' => true, 'interrupted' => true], 'Stale invitation retained its payload');
    foreach ([8 => 'failed', 9 => 'in_progress', 10 => 'queued'] as $id => $status) {
        $task = DB::queryFirstRow('SELECT * FROM %b WHERE increment_id=%i', prefixTable('background_tasks'), $id);
        emailCleanupExpect($task['status'] === $status && $task['arguments'] === 'sealed-invitation-secret-canary',
            'Cleanup changed another payload or failed a resumable/recent task');
    }
    $reflection->getMethod('markTaskFailed')->invoke($handler, 9, 'private-worker-stderr-canary');
    $task = DB::queryFirstRow('SELECT * FROM %b WHERE increment_id=9', prefixTable('background_tasks'));
    emailCleanupExpect($task['status'] === 'failed'
        && json_decode($task['arguments'], true) === ['failed' => true, 'interrupted' => true]
        && !str_contains($task['error_message'], 'private-worker-stderr-canary'), 'Direct interruption retained private task data');
    $cleanup->invoke($handler);
    emailCleanupExpect((int) DB::queryFirstField('SELECT COUNT(*) FROM %b', prefixTable('background_tasks')) === 8,
        'Repeated cleanup changed retained tasks');
    echo "OK: failed tasks with and without subtasks obey retention; recent and other statuses remain.\n";
    echo "OK: both handler interruption paths wipe invitations; ordinary, recent and resumable task payloads are preserved.\n";
} finally {
    DB::query('DROP TABLE IF EXISTS %b', prefixTable('background_subtasks'));
    DB::query('DROP TABLE IF EXISTS %b', prefixTable('background_tasks'));
}
