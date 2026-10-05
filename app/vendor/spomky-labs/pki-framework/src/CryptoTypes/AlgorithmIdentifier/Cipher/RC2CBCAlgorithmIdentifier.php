<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\Cipher;

use LogicException;
use SpomkyLabs\Pki\ASN1\Element;
use SpomkyLabs\Pki\ASN1\Type\Constructed\Sequence;
use SpomkyLabs\Pki\ASN1\Type\Primitive\Integer;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
use SpomkyLabs\Pki\CryptoTypes\AlgorithmIdentifier\SpecificAlgorithmIdentifier;
use UnexpectedValueException;

/*
 * Parameters may be seen in various forms. This implementation attemts
 * to take them all into consideration.
 * # RFC 2268 - A Description of the RC2(r) Encryption Algorithm
 * RC2-CBCParameter ::= CHOICE {
 * iv IV,
 * params SEQUENCE {
 * version RC2Version,
 * iv IV
 * }
 * }
 * # RFC 2898 - PKCS #5: Password-Based Cryptography Specification Version 2.0
 * RC2-CBC-Parameter ::= SEQUENCE {
 * rc2ParameterVersion INTEGER OPTIONAL,
 * iv OCTET STRING (SIZE(8))
 * }
 * # RFC 3370 - Cryptographic Message Syntax (CMS) Algorithms
 * RC2CBCParameter ::= SEQUENCE {
 * rc2ParameterVersion INTEGER,
 * iv OCTET STRING  }  -- exactly 8 octets
 */

/**
 * Algorithm identifier for RC2 cipher in CBC mode.
 *
 * @see http://www.alvestrand.no/objectid/1.2.840.113549.3.2.html
 * @see http://www.oid-info.com/get/1.2.840.113549.3.2
 * @see https://tools.ietf.org/html/rfc2268#section-6
 * @see https://tools.ietf.org/html/rfc3370#section-5.2
 * @see https://tools.ietf.org/html/rfc2898#appendix-C
 */
final class RC2CBCAlgorithmIdentifier extends BlockCipherAlgorithmIdentifier
{
    /**
     * RFC 2268 translation table for effective key bits.
     *
     * This table maps effective key bytes from 0..255 to version number.
     *
     * @var array<int> ekb => version
     */
    private const EKB_TABLE = [
        0xBD, 0x56, 0xEA, 0xF2, 0xA2, 0xF1, 0xAC, 0x2A,
        0xB0, 0x93, 0xD1, 0x9C, 0x1B, 0x33, 0xFD, 0xD0,
        0x30, 0x04, 0xB6, 0xDC, 0x7D, 0xDF, 0x32, 0x4B,
        0xF7, 0xCB, 0x45, 0x9B, 0x31, 0xBB, 0x21, 0x5A,
        0x41, 0x9F, 0xE1, 0xD9, 0x4A, 0x4D, 0x9E, 0xDA,
        0xA0, 0x68, 0x2C, 0xC3, 0x27, 0x5F, 0x80, 0x36,
        0x3E, 0xEE, 0xFB, 0x95, 0x1A, 0xFE, 0xCE, 0xA8,
        0x34, 0xA9, 0x13, 0xF0, 0xA6, 0x3F, 0xD8, 0x0C,
        0x78, 0x24, 0xAF, 0x23, 0x52, 0xC1, 0x67, 0x17,
        0xF5, 0x66, 0x90, 0xE7, 0xE8, 0x07, 0xB8, 0x60,
        0x48, 0xE6, 0x1E, 0x53, 0xF3, 0x92, 0xA4, 0x72,
        0x8C, 0x08, 0x15, 0x6E, 0x86, 0x00, 0x84, 0xFA,
        0xF4, 0x7F, 0x8A, 0x42, 0x19, 0xF6, 0xDB, 0xCD,
        0x14, 0x8D, 0x50, 0x12, 0xBA, 0x3C, 0x06, 0x4E,
        0xEC, 0xB3, 0x35, 0x11, 0xA1, 0x88, 0x8E, 0x2B,
        0x94, 0x99, 0xB7, 0x71, 0x74, 0xD3, 0xE4, 0xBF,
        0x3A, 0xDE, 0x96, 0x0E, 0xBC, 0x0A, 0xED, 0x77,
        0xFC, 0x37, 0x6B, 0x03, 0x79, 0x89, 0x62, 0xC6,
        0xD7, 0xC0, 0xD2, 0x7C, 0x6A, 0x8B, 0x22, 0xA3,
        0x5B, 0x05, 0x5D, 0x02, 0x75, 0xD5, 0x61, 0xE3,
        0x18, 0x8F, 0x55, 0x51, 0xAD, 0x1F, 0x0B, 0x5E,
        0x85, 0xE5, 0xC2, 0x57, 0x63, 0xCA, 0x3D, 0x6C,
        0xB4, 0xC5, 0xCC, 0x70, 0xB2, 0x91, 0x59, 0x0D,
        0x47, 0x20, 0xC8, 0x4F, 0x58, 0xE0, 0x01, 0xE2,
        0x16, 0x38, 0xC4, 0x6F, 0x3B, 0x0F, 0x65, 0x46,
        0xBE, 0x7E, 0x2D, 0x7B, 0x82, 0xF9, 0x40, 0xB5,
        0x1D, 0x73, 0xF8, 0xEB, 0x26, 0xC7, 0x87, 0x97,
        0x25, 0x54, 0xB1, 0x28, 0xAA, 0x98, 0x9D, 0xA5,
        0x64, 0x6D, 0x7A, 0xD4, 0x10, 0x81, 0x44, 0xEF,
        0x49, 0xD6, 0xAE, 0x2E, 0xDD, 0x76, 0x5C, 0x2F,
        0xA7, 0x1C, 0xC9, 0x09, 0x69, 0x9A, 0x83, 0xCF,
        0x29, 0x39, 0xB9, 0xE9, 0x4C, 0xFF, 0x43, 0xAB,
    ];

    /**
     * @param int $effectiveKeyBits Number of effective key bits
     * @param null|string $iv Initialization vector
     */
    private function __construct(
        private readonly int $effectiveKeyBits,
        ?string $iv
    ) {
        parent::__construct(self::OID_RC2_CBC, $iv);
        $this->_checkIVSize($iv);
    }

    public static function create(int $_effectiveKeyBits = 64, ?string $iv = null): self
    {
        return new self($_effectiveKeyBits, $iv);
    }

    public function name(): string
    {
        return 'rc2-cbc';
    }

    /**
     * @return self
     */
    public static function fromASN1Params(?UnspecifiedType $params = null): SpecificAlgorithmIdentifier
    {
        if (! isset($params)) {
            throw new UnexpectedValueException('No parameters.');
        }
        $key_bits = 32;
        // rfc2268 a choice containing only IV
        if ($params->isType(Element::TYPE_OCTET_STRING)) {
            $iv = $params->asOctetString()
                ->string();
        } else {
            $seq = $params->asSequence();
            $idx = 0;
            // version is optional in rfc2898
            if ($seq->has($idx, Element::TYPE_INTEGER)) {
                $version = $seq->at($idx++)
                    ->asInteger()
                    ->intNumber();
                $key_bits = self::_versionToEKB($version);
            }
            // IV is present in all variants
            $iv = $seq->at($idx)
                ->asOctetString()
                ->string();
        }
        return self::create($key_bits, $iv);
    }

    /**
     * Get number of effective key bits.
     */
    public function effectiveKeyBits(): int
    {
        return $this->effectiveKeyBits;
    }

    public function blockSize(): int
    {
        return 8;
    }

    public function keySize(): int
    {
        return (int) round($this->effectiveKeyBits / 8);
    }

    public function ivSize(): int
    {
        return 8;
    }

    /**
     * @return Sequence
     */
    protected function paramsASN1(): Element
    {
        if ($this->effectiveKeyBits >= 256) {
            $version = $this->effectiveKeyBits;
        } else {
            $version = self::EKB_TABLE[$this->effectiveKeyBits];
        }
        if (! isset($this->initializationVector)) {
            throw new LogicException('IV not set.');
        }
        return Sequence::create(Integer::create($version), OctetString::create($this->initializationVector));
    }

    /**
     * Translate version number to number of effective key bits.
     */
    private static function _versionToEKB(int $version): int
    {
        static $lut;
        if ($version > 255) {
            return $version;
        }
        if (! isset($lut)) {
            $lut = array_flip(self::EKB_TABLE);
        }
        return $lut[$version];
    }
}
