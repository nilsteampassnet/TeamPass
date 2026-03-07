<?php

declare(strict_types=1);

use Ratchet\ConnectionInterface;

/**
 * Minimal WebSocket connection stub for unit tests.
 *
 * Implements the Ratchet ConnectionInterface so it can be passed to
 * ConnectionManager without any real network socket.  All messages
 * sent through send() are captured in $sentMessages for assertions.
 */
class MockWsConnection implements ConnectionInterface
{
    /** Unique resource identifier (mirrors Ratchet's real property). */
    public int $resourceId;

    /** Arbitrary per-connection data (user_id, accessible_folders, …). */
    public array $userData = [];

    /** Folder/channel subscriptions tracked on the connection object. */
    public array $subscriptions = [];

    /** All payloads passed to send(), in order of emission. */
    public array $sentMessages = [];

    /** Whether close() was called on this connection. */
    public bool $closed = false;

    public function __construct(int $resourceId, array $userData = [])
    {
        $this->resourceId = $resourceId;
        $this->userData   = $userData;
    }

    public function send($data): ConnectionInterface
    {
        $this->sentMessages[] = $data;
        return $this;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
