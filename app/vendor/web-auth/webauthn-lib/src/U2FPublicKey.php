<?php

declare(strict_types=1);

namespace Webauthn;

use CBOR\ByteStringObject;
use CBOR\MapItem;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Cose\Algorithms;
use Cose\Key\Ec2Key;
use function strlen;

/**
 * @internal
 */
final readonly class U2FPublicKey
{
    private const U2F_KEY_PREFIX = "\x04";

    private const U2F_KEY_LENGTH = 65;

    private const U2F_KEY_PART_SIZE = 32;

    public static function isU2FKey(string $publicKey): bool
    {
        return $publicKey[0] === self::U2F_KEY_PREFIX && strlen($publicKey) === self::U2F_KEY_LENGTH;
    }

    public static function convertToCoseKey(string $publicKey): string
    {
        return MapObject::create([
            MapItem::create(
                UnsignedIntegerObject::create(Ec2Key::TYPE),
                UnsignedIntegerObject::create(Ec2Key::TYPE_EC2)
            ),
            MapItem::create(
                UnsignedIntegerObject::create(Ec2Key::ALG),
                NegativeIntegerObject::create(Algorithms::COSE_ALGORITHM_ES256)
            ),
            MapItem::create(
                NegativeIntegerObject::create(Ec2Key::DATA_CURVE),
                UnsignedIntegerObject::create(Ec2Key::CURVE_P256)
            ),
            MapItem::create(
                NegativeIntegerObject::create(Ec2Key::DATA_X),
                ByteStringObject::create(substr($publicKey, 1, self::U2F_KEY_PART_SIZE))
            ),
            MapItem::create(
                NegativeIntegerObject::create(Ec2Key::DATA_Y),
                ByteStringObject::create(substr($publicKey, 1 + self::U2F_KEY_PART_SIZE))
            ),
        ])->__toString();
    }
}
