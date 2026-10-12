<?php

declare(strict_types=1);

namespace Webauthn;

use function assert;
use CBOR\Stream;
use function fclose;
use function fopen;
use function fread;
use function fwrite;
use function rewind;
use function sprintf;
use function strlen;
use Webauthn\Exception\InvalidDataException;

final class StringStream implements Stream
{
    /**
     * @var resource
     */
    private $data;

    private readonly int $length;

    private int $totalRead = 0;

    public function __construct(string $data)
    {
        $this->length = strlen($data);
        $resource = fopen('php://memory', 'rb+');
        assert($resource !== false, 'The resource could not be opened.');
        fwrite($resource, $data);
        rewind($resource);
        $this->data = $resource;
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        $read = fread($this->data, $length);
        assert($read !== false, 'The data could not be read.');
        $bytesRead = strlen($read);
        strlen($read) === $length || throw InvalidDataException::create(null, sprintf(
            'Out of range. Expected: %d, read: %d.',
            $length,
            $bytesRead
        ));
        $this->totalRead += $bytesRead;

        return $read;
    }

    public function close(): void
    {
        fclose($this->data);
    }

    public function isEOF(): bool
    {
        return $this->totalRead === $this->length;
    }
}
