<?php

namespace Fork;

use Closure;
use Exception;

class Fork
{
    protected ?Closure $toExecuteBeforeInChildTask = null;

    protected ?Closure $toExecuteBeforeInParentTask = null;

    protected ?Closure $toExecuteAfterInChildTask = null;

    protected ?Closure $toExecuteAfterInParentTask = null;

    protected ?int $concurrent = null;

    /** @var \Spatie\Fork\Task[] */
    protected array $queue = [];

    /** @var \Spatie\Fork\Task[] */
    protected array $runningTasks = [];

    public function __construct()
    {
        if (! function_exists('pcntl_fork')) {
            throw new Exception("Cannot create process forks: PCNTL is not supported on this system.");
        }
    }

    public static function new(): self
    {
        return new self();
    }

    public function before(callable $child = null, callable $parent = null): self
    {
        $this->toExecuteBeforeInChildTask = $child;
        $this->toExecuteBeforeInParentTask = $parent;

        return $this;
    }

    public function after(callable $child = null, callable $parent = null): self
    {
        $this->toExecuteAfterInChildTask = $child;
        $this->toExecuteAfterInParentTask = $parent;

        return $this;
    }

    public function concurrent(int $concurrent): self
    {
        $this->concurrent = $concurrent;

        return $this;
    }

    public function run(callable ...$callables): array
    {
        $tasks = [];

        foreach ($callables as $order => $callable) {
            $tasks[] = Task::fromCallable($callable, $order);
        }

        return $this->waitFor(...$tasks);
    }

    protected function waitFor(Task ...$queue): array
    {
        $output = [];

        $this->startRunning(...$queue);

        while ($this->isRunning()) {
            foreach ($this->runningTasks as $task) {
                if (! $task->isFinished()) {
                    continue;
                }

                $output[$task->order()] = $this->finishTask($task);

                $this->shiftTaskFromQueue();
            }

            if ($this->isRunning()) {
                usleep(1_000);
            }
        }

        return $output;
    }

    protected function runTask(Task $task): Task
    {
        if ($this->toExecuteBeforeInParentTask) {
            ($this->toExecuteBeforeInParentTask)();
        }

        return $this->forkForTask($task);
    }

    protected function finishTask(Task $task): mixed
    {
        $output = $task->output();

        if ($this->toExecuteAfterInParentTask) {
            ($this->toExecuteAfterInParentTask)($output);
        }

        unset($this->runningTasks[$task->order()]);

        return $output;
    }

    protected function forkForTask(Task $task): Task
    {
        [$socketToParent, $socketToChild] = Connection::createPair();

        $processId = pcntl_fork();

        if ($this->currentlyInChildTask($processId)) {
            $socketToChild->close();

            $this->executeInChildTask($task, $socketToParent);

            exit;
        }

        $socketToParent->close();

        return $task
            ->setStartTime(time())
            ->setPid($processId)
            ->setConnection($socketToChild);
    }

    protected function currentlyInChildTask(int $pid): bool
    {
        return $pid === 0;
    }

    protected function executeInChildTask(
        Task $task,
        Connection $connectionToParent,
    ): void {
        if ($this->toExecuteBeforeInChildTask) {
            ($this->toExecuteBeforeInChildTask)();
        }

        $output = $task->execute();

        $connectionToParent->write($output);

        if ($this->toExecuteAfterInChildTask) {
            ($this->toExecuteAfterInChildTask)($output);
        }

        $connectionToParent->close();
    }

    protected function shiftTaskFromQueue(): void
    {
        if (! count($this->queue)) {
            return;
        }

        $firstTask = array_shift($this->queue);

        $this->runningTasks[] = $this->runTask($firstTask);
    }

    protected function startRunning(
        Task ...$queue
    ): void {
        $this->queue = $queue;

        foreach ($this->queue as $task) {
            $this->runningTasks[$task->order()] = $this->runTask($task);

            unset($this->queue[$task->order()]);

            if ($this->concurrencyLimitReached()) {
                break;
            }
        }
    }

    protected function isRunning(): bool
    {
        return count($this->runningTasks) > 0;
    }

    protected function concurrencyLimitReached(): bool
    {
        return $this->concurrent && count($this->runningTasks) >= $this->concurrent;
    }
}
