<?php

namespace Fork\Exceptions;

use Exception;
use Fork\Task;

class CouldNotManageTask extends Exception
{
    public static function make(Task $task): self
    {
        return new self("Could not reliably manage task that uses process id {$task->pid()}");
    }
}
