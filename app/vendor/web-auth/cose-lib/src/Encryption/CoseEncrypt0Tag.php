<?php

declare(strict_types=1);

namespace Cose\Encryption;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\OtherObject\OtherObjectManager;
use CBOR\StringStream;
use CBOR\Tag;
use CBOR\Tag\CoseEncrypt0Tag as UpstreamTag;
use CBOR\Tag\TagManager;
use const E_USER_DEPRECATED;
use InvalidArgumentException;
use function sprintf;
use function trigger_error;

/**
 * A tagged COSE_Encrypt0 (RFC 9052, CBOR tag 16).
 *
 * @deprecated since 4.8.0, use \CBOR\Tag\CoseEncrypt0Tag from spomky-labs/cbor-php 3.4.0 or later instead. Will be removed
 * in 5.0.0.
 *
 * The six COSE structures were ported upstream in cbor-php 3.4.0, where they share AbstractCoseTag and are
 * registered in the default decoder, so Decoder::create() resolves tag 16 on its own. The replacement accepts what
 * this class rejects -- a zero-length protected header, a detached (nil) payload, the indefinite-length encodings --
 * and reads every item through the list it carries, so its accessors cannot drift from the bytes it serializes.
 *
 * What does not move upstream is the RFC 9052 layer above the CBOR shape, and that stays here: {@see \Cose\Structure\CoseHeaders}
 * for header labels typed as RFC 9052 section 1.5 defines them, and the Sig_structure, MAC_structure and
 * Enc_structure builders that a signature or a MAC is actually computed over. Both work on the upstream classes.
 *
 * Migrating: the class name changes, and the four-argument create() becomes createFromComponents() -- upstream
 * create() takes the whole list instead.
 *
 * @see https://github.com/web-auth/cose-lib/issues/176
 * @see \Cose\Tests\Structure\DeprecatedTagClassesTest
 */
final class CoseEncrypt0Tag extends Tag
{
    /**
     * A COSE header is a flat map of a handful of parameters whose values nest a couple of levels at most, so the
     * decoder built when none is given is bounded far below the cbor-php default of 1000: a protected header crafted
     * to nest thousands of levels is rejected instead of being walked.
     */
    public const DEFAULT_PROTECTED_HEADER_MAX_DEPTH = 32;

    private const TAG_ID = 16;

    private readonly ByteStringObject $protectedHeader;

    private readonly MapObject $unprotectedHeader;

    private readonly ByteStringObject $ciphertext;

    public function __construct(int $additionalInformation, ?string $data, CBORObject $object)
    {
        trigger_error(sprintf(
            'The class "%s" is deprecated since 4.8.0 and will be removed in 5.0.0. Use "%s" from spomky-labs/cbor-php 3.4.0 or later instead.',
            self::class,
            UpstreamTag::class
        ), E_USER_DEPRECATED);

        if (! $object instanceof ListObject) {
            throw new InvalidArgumentException('Not a valid CoseEncrypt0 object. No list.');
        }
        if ($object->count() !== 3) {
            throw new InvalidArgumentException('Not a valid CoseEncrypt0 object. The list shall have 3 items.');
        }

        $protectedHeader = $object->get(0);
        $unprotectedHeader = $object->get(1);
        $ciphertext = $object->get(2);

        if (! $protectedHeader instanceof ByteStringObject) {
            throw new InvalidArgumentException(
                'Not a valid CoseEncrypt0 object. The item 1 shall be a ByteString object.'
            );
        }
        if (! $unprotectedHeader instanceof MapObject) {
            throw new InvalidArgumentException('Not a valid CoseEncrypt0 object. The item 2 shall be a Map object.');
        }
        if (! $ciphertext instanceof ByteStringObject) {
            throw new InvalidArgumentException(
                'Not a valid CoseEncrypt0 object. The item 3 shall be a ByteString object.'
            );
        }

        parent::__construct($additionalInformation, $data, $object);
        $this->protectedHeader = $protectedHeader;
        $this->unprotectedHeader = $unprotectedHeader;
        $this->ciphertext = $ciphertext;
    }

    public static function getTagId(): int
    {
        return self::TAG_ID;
    }

    public static function createFromLoadedData(int $additionalInformation, ?string $data, CBORObject $object): self
    {
        return new self($additionalInformation, $data, $object);
    }

    public static function create(
        MapObject $protectedHeader,
        MapObject $unprotectedHeader,
        ByteStringObject $ciphertext
    ): self {
        $protectedHeaderAsBytesString = ByteStringObject::create((string) $protectedHeader);
        $object = ListObject::create([$protectedHeaderAsBytesString, $unprotectedHeader, $ciphertext]);

        [$additionalInformation, $data] = self::determineComponents(self::TAG_ID);

        return new self($additionalInformation, $data, $object);
    }

    public function getProtectedHeader(): ByteStringObject
    {
        return $this->protectedHeader;
    }

    /**
     * The nesting bound only applies to the decoder created here: a caller passing its own $decoder sets its own
     * bound, and $maxDepth is then ignored.
     */
    public function getProtectedHeaderAsMap(
        ?Decoder $decoder = null,
        int $maxDepth = self::DEFAULT_PROTECTED_HEADER_MAX_DEPTH
    ): MapObject {
        $stream = new StringStream($this->protectedHeader->getValue());
        $decoder ??= Decoder::create(TagManager::create(), OtherObjectManager::create(), $maxDepth);
        $decoded = $decoder->decode($stream);

        if (! $decoded instanceof MapObject) {
            throw new InvalidArgumentException('Protected header is not a valid Map object.');
        }

        return $decoded;
    }

    public function getUnprotectedHeader(): MapObject
    {
        return $this->unprotectedHeader;
    }

    public function getCiphertext(): ByteStringObject
    {
        return $this->ciphertext;
    }
}
