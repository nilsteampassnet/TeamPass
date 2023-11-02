<?php

namespace Fork;

use Generator;
use Socket;
use ErrorException;

class Connection
{
    protected int $timeoutSeconds;
    protected int $timeoutMicroseconds;

    protected function __construct(
        protected Socket $socket,
        protected int $bufferSize = 1024,
        protected float $timeout = 0.0001,
    ) {
        socket_set_nonblock($this->socket);

        $this->timeoutSeconds = floor($this->timeout);

        $this->timeoutMicroseconds = ($this->timeout * 1_000_000) - ($this->timeoutSeconds * 1_000_000);
    }

    /**
     * @return self[]
     */
    public static function createPair(): array
    {
        socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);

        [$socketToParent, $socketToChild] = $sockets;

        return [
            new self($socketToParent),
            new self($socketToChild),
        ];
    }

    public function close(): self
    {
        socket_close($this->socket);

        return $this;
    }

    public function write(string $payload): self
    {
        socket_set_nonblock($this->socket);

        while ($payload !== '') {
            $write = [$this->socket];

            $read = null;

            $except = null;

            try {
                $selectResult = socket_select($read, $write, $except, $this->timeoutSeconds, $this->timeoutMicroseconds);
            } catch (ErrorException $e) {
                if ($this->isInterruptionErrorException()) {
                    continue;
                }

                throw $e;
            }

            if ($selectResult === false) {
                break;
            }

            if ($selectResult <= 0) {
                break;
            }

            $length = strlen($payload);

            $amountOfBytesSent = socket_write($this->socket, $payload, $length);

            if ($amountOfBytesSent === false || $amountOfBytesSent === $length) {
                break;
            }

            $payload = substr($payload, $amountOfBytesSent);
        }

        return $this;
    }

    public function read(): Generator
    {
        socket_set_nonblock($this->socket);

        while (true) {
            $read = [$this->socket];

            $write = null;

            $except = null;

            try {
                $selectResult = socket_select($read, $write, $except, $this->timeoutSeconds, $this->timeoutMicroseconds);
            } catch (ErrorException $e) {
                if ($this->isInterruptionErrorException()) {
                    continue;
                }

                throw $e;
            }

            if ($selectResult === false) {
                break;
            }

            if ($selectResult <= 0) {
                break;
            }

            $outputFromSocket = socket_read($this->socket, $this->bufferSize);

            if ($outputFromSocket === false || $outputFromSocket === '') {
                break;
            }

            yield $outputFromSocket;
        }
    }

    private function isInterruptionErrorException(): bool
    {
        return 4 === socket_last_error();
    }
}
