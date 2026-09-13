<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X509\CertificationPath\PathValidation;

use function count;
use function in_array;
use function mb_strlen;
use function mb_strtolower;
use function preg_match;
use SpomkyLabs\Pki\X509\Certificate\Extension\NameConstraints\GeneralSubtree;
use SpomkyLabs\Pki\X509\CertificationPath\Exception\PathValidationException;
use SpomkyLabs\Pki\X509\GeneralName\DirectoryName;
use SpomkyLabs\Pki\X509\GeneralName\GeneralName;
use SpomkyLabs\Pki\X509\GeneralName\IPAddress;
use function strcspn;
use function strpos;

/**
 * Matches general names against the subtrees of a 'Name Constraints' certificate extension.
 *
 * Only the name forms for which RFC 5280 defines matching rules are supported. Constraints of any other form, as well
 * as constraints using the `minimum` and `maximum` fields, cannot be evaluated and are therefore rejected rather than
 * silently ignored.
 *
 * @see https://tools.ietf.org/html/rfc5280#section-4.2.1.10
 */
final class NameConstraintsMatcher
{
    /**
     * General name types for which a matching rule is implemented.
     *
     * @var list<int>
     */
    private const SUPPORTED_TAGS = [
        GeneralName::TAG_RFC822_NAME,
        GeneralName::TAG_DNS_NAME,
        GeneralName::TAG_DIRECTORY_NAME,
        GeneralName::TAG_URI,
        GeneralName::TAG_IP_ADDRESS,
    ];

    /**
     * Assert that every subtree of the set can be evaluated.
     *
     * @throws PathValidationException If a subtree uses an unsupported name form or a non-default range.
     */
    public static function assertSupported(GeneralSubtree $subtree): void
    {
        $base = $subtree->base();
        if (! in_array($base->tag(), self::SUPPORTED_TAGS, true)) {
            throw new PathValidationException(
                'Name constraint of type ' . $base->tag() . ' is not supported.'
            );
        }
        // RFC 5280 4.2.1.10: within this profile, the minimum field is always zero and the maximum field is absent.
        if ($subtree->getMin() !== 0 || $subtree->getMax() !== null) {
            throw new PathValidationException('Name constraint with minimum or maximum is not supported.');
        }
    }

    /**
     * Whether the given name falls within the subtree.
     *
     * The name and the subtree base are expected to be of the same type.
     *
     * @throws PathValidationException If the name cannot be evaluated against the constraint.
     */
    public static function matches(GeneralSubtree $subtree, GeneralName $name): bool
    {
        self::assertSupported($subtree);
        $base = $subtree->base();
        if ($base->tag() !== $name->tag()) {
            return false;
        }
        return match ($base->tag()) {
            GeneralName::TAG_RFC822_NAME => self::matchesEmail($base->string(), $name->string()),
            GeneralName::TAG_DNS_NAME => self::matchesDNS($base->string(), $name->string()),
            GeneralName::TAG_DIRECTORY_NAME => self::matchesDirectoryName($base, $name),
            GeneralName::TAG_URI => self::matchesURI($base->string(), $name->string()),
            GeneralName::TAG_IP_ADDRESS => self::matchesIPAddress($base, $name),
            default => throw new PathValidationException(
                'Name constraint of type ' . $base->tag() . ' is not supported.'
            ),
        };
    }

    /**
     * A host name that this matcher is able to compare byte for byte.
     *
     * Letters, digits, hyphen, underscore and the wildcard label, in non-empty labels, with no trailing dot. Anything
     * else has a spelling this comparison would miss, so it is refused rather than let through.
     *
     * @var string
     */
    private const HOST_SYNTAX = '/^[A-Za-z0-9_*](?:[A-Za-z0-9_*-]*[A-Za-z0-9_*])?'
        . '(?:\.[A-Za-z0-9_*](?:[A-Za-z0-9_*-]*[A-Za-z0-9_*])?)*$/';

    /**
     * Match a domain name against a dNSName constraint.
     *
     * A constraint matches the host itself and any host below it. A constraint starting with a dot matches subdomains
     * only.
     */
    private static function matchesDNS(string $base, string $name): bool
    {
        // an empty constraint matches every name of the type
        if ($base === '') {
            return true;
        }
        $base = self::assertBaseHost($base, 'dNSName constraint');
        $name = self::assertHost($name, 'dNSName');
        if (str_starts_with($base, '.')) {
            return str_ends_with($name, $base);
        }
        return $name === $base || str_ends_with($name, '.' . $base);
    }

    /**
     * Match a mailbox against an rfc822Name constraint.
     *
     * The constraint is either a complete mailbox, a host name matching every mailbox on that host, or a name starting
     * with a dot matching every mailbox below that domain.
     */
    private static function matchesEmail(string $base, string $name): bool
    {
        if ($base === '') {
            return true;
        }
        $namePos = strrpos($name, '@');
        if ($namePos === false) {
            return false;
        }
        $nameLocal = substr($name, 0, $namePos);
        $nameHost = self::assertHost(substr($name, $namePos + 1), 'rfc822Name host');
        $basePos = strrpos($base, '@');
        // complete mailbox: the local part is case sensitive, the host part is not
        if ($basePos !== false) {
            return substr($base, 0, $basePos) === $nameLocal
                && self::assertHost(substr($base, $basePos + 1), 'rfc822Name constraint host') === $nameHost;
        }
        $base = self::assertBaseHost($base, 'rfc822Name constraint');
        if (str_starts_with($base, '.')) {
            return str_ends_with($nameHost, $base);
        }
        return $nameHost === $base;
    }

    /**
     * Match a URI against a uniformResourceIdentifier constraint.
     *
     * The constraint applies to the host part of the authority component.
     *
     * @throws PathValidationException If the URI has no host that can be compared, in which case RFC 5280 mandates a
     * rejection.
     */
    private static function matchesURI(string $base, string $name): bool
    {
        $host = self::assertHost(self::uriHost($name), 'URI host');
        if ($base === '') {
            return true;
        }
        $base = self::assertBaseHost($base, 'uniformResourceIdentifier constraint');
        if (str_starts_with($base, '.')) {
            return str_ends_with($host, $base);
        }
        return $host === $base;
    }

    /**
     * Extract the host of a URI's authority component.
     *
     * parse_url() is not used. It follows neither RFC 3986 nor the WHATWG URL standard that consumers of these names
     * implement, and the two disagree on inputs an attacker chooses: for the special schemes the standard ends the
     * authority at a backslash, while parse_url() runs past it and takes the host after the last "@". A name matched
     * on one host and resolved on another defeats the constraint entirely.
     *
     * The authority therefore ends at the first of "/", "\", "?" or "#", userinfo is removed at the first "@", and a
     * port is removed after it.
     *
     * @throws PathValidationException If the URI carries no authority.
     */
    private static function uriHost(string $uri): string
    {
        $schemeEnd = strpos($uri, '://');
        if ($schemeEnd === false) {
            throw new PathValidationException(
                "URI '{$uri}' has no authority and cannot be matched against a name constraint."
            );
        }
        $authority = substr($uri, $schemeEnd + 3);
        $end = strcspn($authority, '/\\?#');
        $authority = substr($authority, 0, $end);
        // userinfo ends at the first "@"; anything after it is the host, so a later "@" belongs to neither
        $at = strpos($authority, '@');
        if ($at !== false) {
            $authority = substr($authority, $at + 1);
        }
        // a port follows the host, and a bracketed IPv6 literal is not a name this matcher compares
        $colon = strpos($authority, ':');
        if ($colon !== false) {
            $authority = substr($authority, 0, $colon);
        }

        return $authority;
    }

    /**
     * Assert that a host name has a spelling this matcher can compare, and return it folded to lower case.
     *
     * @throws PathValidationException If the name has any other spelling.
     */
    private static function assertHost(string $host, string $what): string
    {
        if ($host === '' || preg_match(self::HOST_SYNTAX, $host) !== 1) {
            throw new PathValidationException(
                "{$what} '{$host}' is not a host name that can be matched against a name constraint."
            );
        }

        return mb_strtolower($host, '8bit');
    }

    /**
     * Assert the same of a constraint base, which may additionally start with a dot to mean "below this domain".
     *
     * @throws PathValidationException If the base has any other spelling.
     */
    private static function assertBaseHost(string $base, string $what): string
    {
        if (str_starts_with($base, '.')) {
            return '.' . self::assertHost(substr($base, 1), $what);
        }

        return self::assertHost($base, $what);
    }

    /**
     * Match a distinguished name against a directoryName constraint.
     *
     * The constraint matches whenever it is a prefix of the name, an empty constraint therefore matching every name.
     */
    private static function matchesDirectoryName(GeneralName $base, GeneralName $name): bool
    {
        if (! $base instanceof DirectoryName || ! $name instanceof DirectoryName) {
            return false;
        }
        $baseRDNs = $base->dn()
            ->all();
        $nameRDNs = $name->dn()
            ->all();
        if (count($baseRDNs) > count($nameRDNs)) {
            return false;
        }
        foreach ($baseRDNs as $i => $rdn) {
            if (! $rdn->equals($nameRDNs[$i])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Match an IP address against an iPAddress constraint.
     *
     * The constraint carries a subnet mask of the same length as the address itself.
     *
     * @throws PathValidationException If an address cannot be decoded.
     */
    private static function matchesIPAddress(GeneralName $base, GeneralName $name): bool
    {
        if (! $base instanceof IPAddress || ! $name instanceof IPAddress) {
            return false;
        }
        $baseAddress = self::toOctets($base->address());
        $nameAddress = self::toOctets($name->address());
        // an IPv4 constraint never matches an IPv6 address and vice versa
        if (mb_strlen($baseAddress, '8bit') !== mb_strlen($nameAddress, '8bit')) {
            return false;
        }
        if (! $base->hasMask()) {
            return $baseAddress === $nameAddress;
        }
        $mask = self::toOctets($base->mask());
        if (mb_strlen($mask, '8bit') !== mb_strlen($baseAddress, '8bit')) {
            throw new PathValidationException('Invalid subnet mask in iPAddress name constraint.');
        }
        return ($baseAddress & $mask) === ($nameAddress & $mask);
    }

    /**
     * @throws PathValidationException If the address is not a valid IPv4 or IPv6 address.
     */
    private static function toOctets(string $address): string
    {
        $octets = inet_pton($address);
        if ($octets === false) {
            throw new PathValidationException("Invalid IP address '{$address}' in certification path.");
        }
        return $octets;
    }
}
