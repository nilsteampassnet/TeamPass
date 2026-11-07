<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\EmailHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Minimal persistence boundary for the production handler's completion methods. */
final class DB
{
    public static string $status = 'queued';
    public static array $updates = [];
    /** Return the current status or the process type using the actual query's selected field. */
    public static function queryFirstField(string $sql, mixed ...$arguments): string
    {
        return str_contains($sql, 'SELECT process_type') ? 'send_email' : self::$status;
    }
    /** Email failures do not notify backup-report paths. */
    public static function queryFirstRow(string $sql, mixed ...$arguments): array { return ['process_type' => 'send_email', 'arguments' => '{}']; }
    /** Capture the actual handler update. */
    public static function update(string $table, array $values, string $where, mixed ...$arguments): void { self::$updates[] = $values; }
}

/** Capture diagnostics without writing to an installation journal. */
final class TaskLogger
{
    public array $messages = [];
    /** Record the handler's own message. */
    public function log(string $message, string $level): void { $this->messages[] = $message; }
}

/** Use the normal table-prefix boundary. */
function prefixTable(string $table): string { return 'email_handler_test_' . $table; }

final class EmailHandlerOutcomeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        define(__NAMESPACE__ . '\\LOG_TASKS', true);
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/scripts/background_tasks___handler.php');
        $class = '';
        $recording = false;
        $depth = 0;
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_CLASS) { $recording = true; }
            if (!$recording) { continue; }
            $class .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { ++$depth; }
            elseif ($token === '}' && --$depth === 0) { break; }
        }
        self::assertStringStartsWith('class BackgroundTasksHandler ', $class);
        eval('namespace ' . __NAMESPACE__ . '; use Symfony\\Component\\Process\\Process; ' . $class);
    }

    private function handler(TaskLogger $logger): object
    {
        $class = new \ReflectionClass(__NAMESPACE__ . '\\BackgroundTasksHandler');
        $handler = $class->newInstanceWithoutConstructor();
        $class->getProperty('logger')->setValue($handler, $logger);
        DB::$updates = [];
        return $handler;
    }

    /** A clean process exit is not enough to report an email as accepted. */
    public function testSuccessfulProcessReportsActualEmailTaskStatus(): void
    {
        foreach (['queued', 'failed', 'completed'] as $status) {
            DB::$status = $status;
            $logger = new TaskLogger();
            $handler = $this->handler($logger);
            $process = $this->createMock(Process::class);
            $process->method('isSuccessful')->willReturn(true);
            (new \ReflectionMethod($handler, 'handleProcessCompletion'))->invoke($handler, ['process' => $process, 'task' => ['increment_id' => 123, 'process_type' => 'send_email']]);
            self::assertSame(['Email task 123 status: ' . $status], $logger->messages);
            self::assertSame([], DB::$updates);
        }
    }

    /** Provider stderr cannot reach task errors or logs even if PHP crashes outside the worker catch. */
    public function testCrashedEmailWorkerIsFailedWithoutRawStderr(): void
    {
        DB::$status = 'in_progress';
        $logger = new TaskLogger();
        $handler = $this->handler($logger);
        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(false);
        $process->method('getExitCode')->willReturn(1);
        $process->method('getErrorOutput')->willReturn('private-access-token private-mail-body');
        (new \ReflectionMethod($handler, 'handleProcessCompletion'))->invoke($handler, ['process' => $process, 'task' => ['increment_id' => 123, 'process_type' => 'send_email']]);
        self::assertSame('failed', DB::$updates[0]['status']);
        self::assertSame(-1, DB::$updates[0]['is_in_progress']);
        self::assertStringContainsString('acceptance is unknown', DB::$updates[0]['error_message']);
        self::assertStringNotContainsString('private', json_encode(DB::$updates) . json_encode($logger->messages));
    }
}
