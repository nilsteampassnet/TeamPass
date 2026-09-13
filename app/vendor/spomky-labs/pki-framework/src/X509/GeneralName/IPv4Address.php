<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\GeneralName;

use function array_map;
use function array_slice;
use function count;
use function implode;
use UnexpectedValueException;
use function unpack;

final class IPv4Address extends IPAddress
{
    /**
     * @param string $ip Dotted quad address
     * @param null|string $mask Optional subnet mask, as a dotted quad
     */
    public static function create(string $ip, ?string $mask = null): self
    {
        // refuse here rather than when the name is encoded, so that a caller cannot build a constraint whose
        // meaning differs from what it wrote
        self::addressToOctets($ip, 4, 'Address');
        if ($mask !== null) {
            self::addressToOctets($mask, 4, 'Mask');
        }

        return new self($ip, $mask);
    }

    /**
     * Initialize from octets.
     */
    public static function fromOctets(string $octets): self
    {
        $mask = null;
        $bytes = unpack('C*', $octets);
        /** @var array<int, int> $bytes */
        $bytes = $bytes === false ? [] : $bytes;
        switch (count($bytes)) {
            case 4:
                $ip = implode('.', array_map(static fn (int $v): string => (string) $v, $bytes));
                break;
            case 8:
                $ip = implode('.', array_map(static fn (int $v): string => (string) $v, array_slice($bytes, 0, 4)));
                $mask = implode('.', array_map(static fn (int $v): string => (string) $v, array_slice($bytes, 4, 4)));
                break;
            default:
                throw new UnexpectedValueException('Invalid IPv4 octet length.');
        }
        return self::create($ip, $mask);
    }

    protected function octets(): string
    {
        $octets = self::addressToOctets($this->ip, 4, 'Address');
        if (isset($this->mask)) {
            $octets .= self::addressToOctets($this->mask, 4, 'Mask');
        }

        return $octets;
    }
}
