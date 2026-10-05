<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\GeneralName;

use function array_map;
use function array_slice;
use function count;
use function implode;
use function sprintf;
use UnexpectedValueException;
use function unpack;

final class IPv6Address extends IPAddress
{
    /**
     * @param string $ip Address, in any notation inet_pton() accepts, compressed included
     * @param null|string $mask Optional subnet mask, in the same notation
     */
    public static function create(string $ip, ?string $mask = null): self
    {
        // refuse here rather than when the name is encoded, so that a caller cannot build a constraint whose
        // meaning differs from what it wrote
        self::addressToOctets($ip, 16, 'Address');
        if ($mask !== null) {
            self::addressToOctets($mask, 16, 'Mask');
        }

        return new self($ip, $mask);
    }

    /**
     * Initialize from octets.
     */
    public static function fromOctets(string $octets): self
    {
        $mask = null;
        $words = unpack('n*', $octets);
        $words = $words === false ? [] : $words;
        switch (count($words)) {
            case 8:
                $ip = self::wordsToIPv6String($words);
                break;
            case 16:
                $ip = self::wordsToIPv6String(array_slice($words, 0, 8));
                $mask = self::wordsToIPv6String(array_slice($words, 8, 8));
                break;
            default:
                throw new UnexpectedValueException('Invalid IPv6 octet length.');
        }
        return self::create($ip, $mask);
    }

    /**
     * Convert an array of 16 bit words to an IPv6 string representation.
     *
     * @param int[] $words
     */
    protected static function wordsToIPv6String(array $words): string
    {
        $groups = array_map(static fn ($word) => sprintf('%04x', $word), $words);
        return implode(':', $groups);
    }

    protected function octets(): string
    {
        $octets = self::addressToOctets($this->ip, 16, 'Address');
        if (isset($this->mask)) {
            $octets .= self::addressToOctets($this->mask, 16, 'Mask');
        }

        return $octets;
    }
}
