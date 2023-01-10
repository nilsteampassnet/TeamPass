<?php

namespace Fork;

use Closure;
use Fork\Exceptions\CouldNotManageTask;

class Task
{
    protected const SERIALIZATION_TOKEN = '[[serialized::';

    protected string $name;

    protected int $order;

    protected int $pid;

    protected int $status;

    protected Connection $connection;

    protected ?Closure $successCallback = null;

    protected int $startTime;

    protected Closure $callable;

    protected string $output = '';

    public static function fromCallable(callable $callable, int $order): self
    {
        return new self($callable, $order);
    }

    public function __construct(callable $callable, int $order)
    {
        $this->callable = Closure::fromCallable($callable);

        $this->order = $order;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function pid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;

        return $this;
    }

    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function startTime(): int
    {
        return $this->startTime;
    }

    public function setStartTime($startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function execute(): string | bool
    {
        $output = ($this->callable)();

        if (is_string($output)) {
            return $output;
        }

        return self::SERIALIZATION_TOKEN . serialize($output);
    }

    public function output(): mixed
    {
        foreach ($this->connection->read() as $output) {
            $this->output .= $output;
        }

        $this->connection->close();

        $this->triggerSuccessCallback();

        $output = $this->output;

        if (str_starts_with($output, self::SERIALIZATION_TOKEN)) {
            $output = unserialize(
                substr($output, strlen(self::SERIALIZATION_TOKEN))
            );
        }

        return $output;
    }

    public function onSuccess(callable $callback): self
    {
        $this->successCallback = $callback;

        return $this;
    }

    public function isFinished(): bool
    {
        $this->output .= $this->connection->read()->current();

        $status = pcntl_waitpid($this->pid(), $status, WNOHANG | WUNTRACED);

        if ($status === $this->pid) {
            return true;
        }

        if ($status !== 0) {
            throw CouldNotManageTask::make($this);
        }

        return false;
    }

    public function triggerSuccessCallback(): mixed
    {
        if (! $this->successCallback) {
            return null;
        }

        return call_user_func_array($this->successCallback, [$this]);
    }
}
