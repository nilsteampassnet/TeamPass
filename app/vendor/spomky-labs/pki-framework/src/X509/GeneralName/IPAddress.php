<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\GeneralName;

use InvalidArgumentException;
use LogicException;
use function mb_strlen;
use SpomkyLabs\Pki\ASN1\Type\Primitive\OctetString;
use SpomkyLabs\Pki\ASN1\Type\Tagged\ImplicitlyTaggedType;
use SpomkyLabs\Pki\ASN1\Type\TaggedType;
use SpomkyLabs\Pki\ASN1\Type\UnspecifiedType;
use function sprintf;
use UnexpectedValueException;

/**
 * Implements *iPAddress* CHOICE type of *GeneralName*.
 *
 * Concrete classes `IPv4Address` and `IPv6Address` furthermore implement the parsing semantics.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-4.2.1.6
 */
abstract class IPAddress extends GeneralName
{
    protected function __construct(
        protected string $ip,
        protected ?string $mask = null
    ) {
        parent::__construct(self::TAG_IP_ADDRESS);
    }

    /**
     * @return self
     */
    public static function fromChosenASN1(UnspecifiedType $el): GeneralName
    {
        $octets = $el->asOctetString()
            ->string();
        return match (mb_strlen($octets, '8bit')) {
            4, 8 => IPv4Address::fromOctets($octets),
            16, 32 => IPv6Address::fromOctets($octets),
            default => throw new UnexpectedValueException('Invalid octet length for IP address.'),
        };
    }

    public function string(): string
    {
        return $this->ip . (isset($this->mask) ? '/' . $this->mask : '');
    }

    /**
     * Get IP address as a string.
     */
    public function address(): string
    {
        return $this->ip;
    }

    /**
     * Check whether mask is present.
     */
    public function hasMask(): bool
    {
        return isset($this->mask);
    }

    /**
     * Get subnet mask as a string.
     */
    public function mask(): string
    {
        if (! $this->hasMask()) {
            throw new LogicException('mask is not set.');
        }
        return $this->mask;
    }

    /**
     * Get octet representation of the IP address.
     */
    abstract protected function octets(): string;

    /**
     * Convert a textual address to its network octets, refusing anything that is not an address of the expected
     * family.
     *
     * Splitting the string on its separators and packing the parts accepts input that is not an address at all:
     * a compressed IPv6 address yields as many parts as it has written groups, so it packs to fewer than sixteen
     * octets and is then decoded as a different name form entirely. inet_pton() is the only conversion that is
     * total on what it accepts and rejects everything else.
     *
     * @param string $address Textual address
     * @param int $length Expected number of octets, 4 for IPv4 and 16 for IPv6
     * @param string $what Name of the value, used in the error message
     */
    protected static function addressToOctets(string $address, int $length, string $what): string
    {
        $octets = inet_pton($address);
        if ($octets === false || mb_strlen($octets, '8bit') !== $length) {
            throw new InvalidArgumentException(sprintf('%s is not a valid %s address.', $what, $length === 4 ? 'IPv4' : 'IPv6'));
        }

        return $octets;
    }

    protected function choiceASN1(): TaggedType
    {
        return ImplicitlyTaggedType::create($this->tag, OctetString::create($this->octets()));
    }
}
