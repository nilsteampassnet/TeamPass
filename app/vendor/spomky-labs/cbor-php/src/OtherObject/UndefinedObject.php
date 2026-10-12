<?php

declare(strict_types=1);

namespace CBOR\OtherObject;

use CBOR\Normalizable;
use CBOR\OtherObject as Base;

final class UndefinedObject extends Base implements Normalizable
{
    public function __construct()
    {
        parent::__construct(self::OBJECT_UNDEFINED, null);
    }

    public static function create(): self
    {
        return new self();
    }

    public static function supportedAdditionalInformation(): array
    {
        return [self::OBJECT_UNDEFINED];
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data): Base
    {
        return new self();
    }

    /**
     * PHP has no counterpart for the CBOR "undefined" simple value, so it normalizes to null. Use the object itself
     * when the distinction with an explicit CBOR "null" matters.
     */
    public function normalize(): mixed
    {
        return null;
    }
}
