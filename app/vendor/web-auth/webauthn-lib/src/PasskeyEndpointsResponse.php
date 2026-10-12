<?php

declare(strict_types=1);

namespace Webauthn;

/**
 * Represents the Passkey Endpoints response as defined in the W3C Passkey Endpoints specification.
 *
 * @see https://w3c.github.io/webappsec-passkey-endpoints/
 */
final readonly class PasskeyEndpointsResponse
{
    /**
     * @param null|Url $enroll URL to the passkey enrollment/creation interface
     * @param null|Url $manage URL to the passkey management interface
     * @param null|Url $prfUsageDetails URL to informational page about PRF (Pseudo-Random Function) extension usage
     */
    public function __construct(
        public null|Url $enroll = null,
        public null|Url $manage = null,
        public null|Url $prfUsageDetails = null,
    ) {
    }

    /**
     * @param null|Url $enroll URL to the passkey enrollment/creation interface
     * @param null|Url $manage URL to the passkey management interface
     * @param null|Url $prfUsageDetails URL to informational page about PRF extension usage
     */
    public static function create(
        null|Url $enroll = null,
        null|Url $manage = null,
        null|Url $prfUsageDetails = null,
    ): self {
        return new self($enroll, $manage, $prfUsageDetails);
    }

    /**
     * Creates an empty response indicating passkey support without exposing specific endpoints.
     */
    public static function createEmpty(): self
    {
        return new self();
    }
}
