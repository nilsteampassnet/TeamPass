<?php

declare(strict_types=1);

namespace Cose\Structure;

use CBOR\ByteStringObject;
use CBOR\CBORObject;
use CBOR\Decoder;
use CBOR\DecoderInterface;
use CBOR\IndefiniteLengthByteStringObject;
use CBOR\IndefiniteLengthListObject;
use CBOR\IndefiniteLengthMapObject;
use CBOR\IndefiniteLengthTextStringObject;
use CBOR\ListObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\OtherObject\OtherObjectInterface;
use CBOR\StringStream;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use function in_array;
use InvalidArgumentException;
use function is_int;
use function ord;
use function sprintf;
use function strlen;

/**
 * The RFC 9052 rules that sit above the CBOR shape of a COSE message: how a protected bucket is encoded and decoded,
 * what a header label may be, which CBOR tag number a message type carries, and the shape of the signature and
 * recipient lists.
 *
 * These are deliberately static functions over plain CBOR objects rather than methods on a message class. Since
 * spomky-labs/cbor-php 3.4.0 the six COSE structures live upstream, as CBOR\Tag\CoseSign1Tag and its siblings: that
 * library owns the shape of a message, this one owns what RFC 9052 says the shape means. Everything here therefore
 * applies to an upstream message, to the deprecated Cose\...Tag classes, and to a header map a caller assembled
 * itself.
 *
 * {@see CoseHeaders} is the ergonomic form of the header half of this: it reads the two buckets of a message once
 * and answers label lookups against them.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-3
 * @see https://www.rfc-editor.org/rfc/rfc9052#section-1.5
 * @see https://github.com/web-auth/cose-lib/issues/166
 * @see \Cose\Tests\Structure\HeaderMapHelperTest
 */
final class HeaderMapHelper
{
    /**
     * A COSE header is a flat map of a handful of parameters whose values nest a couple of levels at most, so the
     * decoder built when none is given is bounded far below the cbor-php default of 1000: a protected header crafted
     * to nest thousands of levels is rejected instead of being walked.
     */
    public const DEFAULT_PROTECTED_HEADER_MAX_DEPTH = 32;

    /**
     * Decode the protected bucket, strictly.
     *
     * Three RFC 9052 rules the upstream accessor does not apply, in one place:
     *
     * - Section 3: "Recipients MUST accept both a zero-length byte string and a zero-length map encoded in a byte
     *   string." The zero-length byte string is the form senders are told to prefer, and decoding it as CBOR yields
     *   nothing at all, hence the guard before the decoder runs.
     * - Section 3 CDDL: "empty_or_serialized_map = bstr .cbor header_map / bstr .size 0". The ".cbor" control of
     *   RFC 8610 section 3.8.4 carries exactly one data item, so trailing bytes make the bucket malformed.
     * - Section 1.5: a key that is neither an integer nor a text string is not a label at all.
     */
    public static function decodeProtected(
        ByteStringObject|IndefiniteLengthByteStringObject $protectedHeader,
        ?DecoderInterface $decoder = null,
        int $maxDepth = self::DEFAULT_PROTECTED_HEADER_MAX_DEPTH
    ): MapObject {
        $raw = $protectedHeader->getValue();
        if ($raw === '') {
            return MapObject::create();
        }

        $stream = new StringStream($raw);
        $decoder ??= Decoder::create(null, null, $maxDepth);
        $decoded = $decoder->decode($stream);

        // cbor-php exposes no end-of-stream predicate, so the probe is a read that has to fail.
        $trailing = true;
        try {
            $stream->read(1);
        } catch (InvalidArgumentException) {
            $trailing = false;
        }
        if ($trailing) {
            throw new InvalidArgumentException(
                'Invalid protected header. The byte string carries trailing data after the header map.'
            );
        }

        if (! $decoded instanceof MapObject && ! $decoded instanceof IndefiniteLengthMapObject) {
            throw new InvalidArgumentException('Protected header is not a valid Map object.');
        }

        return self::assertValidLabels($decoded);
    }

    /**
     * Encode the protected bucket.
     *
     * RFC 9052 section 3: "Senders SHOULD encode a zero-length map as a zero-length byte string rather than as a
     * zero-length map (encoded as h'a0')." Upstream createFromComponents() emits h'a0'; a sender that wants the
     * preferred form passes the byte string this returns.
     */
    public static function encodeProtected(MapObject|IndefiniteLengthMapObject $protectedHeader): ByteStringObject
    {
        $checked = self::assertValidLabels($protectedHeader);

        return ByteStringObject::create($checked->count() === 0 ? '' : (string) $checked);
    }

    /**
     * Check that every key of a header map is a label and that no label appears twice, and hand back an equivalent
     * definite-length map.
     *
     * RFC 9052 section 1.5: "label = int / tstr" and "the presence a label that is neither a text string nor an
     * integer is an error". Section 3: "Labels in each of the maps MUST be unique. When processing messages, if a
     * label appears multiple times, the message MUST be rejected as malformed." A byte string key is the case worth
     * naming: cbor-php normalizes h'31' to the string "1", so without this check a byte string key would answer a
     * lookup for the integer label 1, the algorithm parameter.
     */
    public static function assertValidLabels(MapObject|IndefiniteLengthMapObject $header): MapObject
    {
        $checked = MapObject::create();
        foreach ($header as $item) {
            $key = $item->getKey();
            if (! self::isLabel($key)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid header label. A label shall be an integer or a text string, got "%s" (RFC 9052 section 1.5).',
                    $key::class
                ));
            }
            $checked->add($key, $item->getValue());
        }

        return $checked;
    }

    /**
     * Look a label up by value *and* by type.
     *
     * The map accessors of cbor-php are keyed by the normalized key, and PHP turns the numeric string offset "1"
     * into the integer 1, so int 1 and tstr "1" -- two distinct labels for RFC 9052 -- share one offset there. This
     * lookup compares the major type as well, which is what makes a lookup for the label 1 mean the algorithm
     * parameter and nothing else.
     */
    public static function findLabel(MapObject|IndefiniteLengthMapObject $header, int|string $label): ?CBORObject
    {
        foreach ($header as $item) {
            $key = $item->getKey();
            $matches = is_int($label)
                ? ($key instanceof UnsignedIntegerObject || $key instanceof NegativeIntegerObject)
                    && $key->normalize() === (string) $label
                : ($key instanceof TextStringObject || $key instanceof IndefiniteLengthTextStringObject)
                    && $key->normalize() === $label;
            if ($matches) {
                return $item->getValue();
            }
        }

        return null;
    }

    /**
     * Check that the CBOR tag a message carries is the one its structure claims.
     *
     * RFC 9052 section 2 maps each message type to one tag number, and the decoder dispatches on that number, so
     * this is for the paths that bypass it: a class constructed directly, or createFromLoadedData() called on the
     * value of a GenericTag. Neither the upstream classes nor the deprecated ones check it, so a message can
     * otherwise keep claiming to be a COSE_Sign1 while serializing as a COSE_Mac0.
     *
     * The comparison is on the decoded number, not on the raw components, so a non-minimal encoding of the right
     * number stays acceptable exactly as it is on the decoder path (RFC 8949 allows it outside deterministic
     * encoding).
     */
    public static function assertTagNumber(int $additionalInformation, ?string $data, int $expected, string $name): void
    {
        $number = self::tagNumber($additionalInformation, $data, $name);
        if ($number !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'Not a valid %s object. Expected the CBOR tag %d, got %d.',
                $name,
                $expected,
                $number
            ));
        }
    }

    /**
     * Check a "signatures" list against "signatures : [+ COSE_Signature]" and "COSE_Signature = [ Headers,
     * signature : bstr ]" (RFC 9052 section 4.1), Headers being the protected byte string and the unprotected map.
     */
    public static function assertSignatureList(ListObject|IndefiniteLengthListObject $signatures): void
    {
        if ($signatures->count() === 0) {
            throw new InvalidArgumentException(
                'Not a valid CoseSign object. The signatures list shall hold at least one COSE_Signature (RFC 9052 section 4.1).'
            );
        }
        foreach ($signatures as $signature) {
            if (! self::isList($signature)
                || $signature->count() !== 3
                || ! self::isByteString($signature->get(0))
                || ! self::isMap($signature->get(1))
                || ! self::isByteString($signature->get(2))
            ) {
                throw new InvalidArgumentException(
                    'Not a valid CoseSign object. Each signature shall be a COSE_Signature [bstr, map, bstr].'
                );
            }
        }
    }

    /**
     * Check a "recipients" list against "recipients : [+COSE_recipient]" and "COSE_recipient = [ Headers,
     * ciphertext : bstr / nil, ? recipients : [+COSE_recipient] ]" (RFC 9052 section 5.1).
     *
     * The nested list is walked with the same rule, which is what bounds a recipient tree to well-formed levels
     * rather than to whatever the decoder happened to accept.
     */
    public static function assertRecipientList(
        ListObject|IndefiniteLengthListObject $recipients,
        string $name = 'COSE_recipient'
    ): void {
        if ($recipients->count() === 0) {
            throw new InvalidArgumentException(sprintf(
                'Not a valid %s object. The recipients list shall hold at least one COSE_recipient (RFC 9052 section 5.1).',
                $name
            ));
        }
        foreach ($recipients as $recipient) {
            if (! self::isList($recipient)
                || ! in_array($recipient->count(), [3, 4], true)
                || ! self::isByteString($recipient->get(0))
                || ! self::isMap($recipient->get(1))
                || ! (self::isByteString($recipient->get(2)) || self::isNil($recipient->get(2)))
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Not a valid %s object. Each recipient shall be a COSE_recipient [bstr, map, bstr / nil, ? [+ COSE_recipient]].',
                    $name
                ));
            }
            if ($recipient->count() === 4) {
                $nested = $recipient->get(3);
                if (! self::isList($nested)) {
                    throw new InvalidArgumentException(sprintf(
                        'Not a valid %s object. The nested recipients of a COSE_recipient shall be a List object.',
                        $name
                    ));
                }
                self::assertRecipientList($nested, $name);
            }
        }
    }

    /**
     * Whether an item is the CBOR "nil" simple value, which is how RFC 9052 spells detached content.
     *
     * The decoder maps simple value 22 to NullObject only when the caller's OtherObjectManager knows that class; an
     * empty manager yields a GenericObject carrying the same head. Both are nil on the wire, so both are nil here.
     */
    public static function isNil(CBORObject $object): bool
    {
        return $object instanceof OtherObjectInterface
            && $object->getAdditionalInformation() === CBORObject::OBJECT_NULL;
    }

    /**
     * @phpstan-assert-if-true IndefiniteLengthListObject|ListObject $object
     */
    private static function isList(CBORObject $object): bool
    {
        return $object instanceof ListObject || $object instanceof IndefiniteLengthListObject;
    }

    private static function isMap(CBORObject $object): bool
    {
        return $object instanceof MapObject || $object instanceof IndefiniteLengthMapObject;
    }

    private static function isByteString(CBORObject $object): bool
    {
        return $object instanceof ByteStringObject || $object instanceof IndefiniteLengthByteStringObject;
    }

    private static function isLabel(CBORObject $key): bool
    {
        return $key instanceof UnsignedIntegerObject
            || $key instanceof NegativeIntegerObject
            || $key instanceof TextStringObject
            || $key instanceof IndefiniteLengthTextStringObject;
    }

    /**
     * A tag number is the argument of a major type 6 head: the additional information carries it directly below 24,
     * and announces its width above.
     */
    private static function tagNumber(int $additionalInformation, ?string $data, string $name): int
    {
        if ($additionalInformation < 24) {
            return $additionalInformation;
        }

        $width = match ($additionalInformation) {
            CBORObject::LENGTH_1_BYTE => 1,
            CBORObject::LENGTH_2_BYTES => 2,
            CBORObject::LENGTH_4_BYTES => 4,
            CBORObject::LENGTH_8_BYTES => 8,
            default => throw new InvalidArgumentException(sprintf(
                'Not a valid %s object. The additional information %d is not a valid CBOR tag head.',
                $name,
                $additionalInformation
            )),
        };
        if ($data === null || strlen($data) !== $width) {
            throw new InvalidArgumentException(sprintf(
                'Not a valid %s object. The CBOR tag head announces %d byte(s) of tag number.',
                $name,
                $width
            ));
        }

        $number = 0;
        for ($i = 0; $i < $width; ++$i) {
            $number = ($number << 8) | ord($data[$i]);
        }
        if ($number < 0) {
            throw new InvalidArgumentException(sprintf(
                'Not a valid %s object. The CBOR tag number exceeds the platform integer range.',
                $name
            ));
        }

        return $number;
    }
}
