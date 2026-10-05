<?php

declare(strict_types=1);

namespace CBOR;

use InvalidArgumentException;
use function sprintf;
use function strlen;

final class StringStream implements Stream
{
    private int $offset = 0;

    private readonly int $length;

    public function __construct(
        private readonly string $data
    ) {
        $this->length = strlen($data);
    }

    public static function create(string $data): self
    {
        return new self($data);
    }

    public function read(int $length): string
    {
        if ($length === 0) {
            return '';
        }

        $available = $this->length - $this->offset;
        if ($available < $length) {
            throw new InvalidArgumentException(sprintf(
                'Out of range. Expected: %d, read: %d.',
                $length,
                $available < 0 ? 0 : $available
            ));
        }

        $data = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $data;
    }
}
