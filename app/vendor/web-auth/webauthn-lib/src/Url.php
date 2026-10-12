<?php

declare(strict_types=1);

namespace Webauthn;

/**
 * Represents a URL that can be either an absolute HTTPS URL or a Symfony route name.
 * For Passkey Endpoints, URLs must always be absolute HTTPS URLs.
 *
 * @see https://w3c.github.io/webappsec-passkey-endpoints/
 */
final readonly class Url
{
    /**
     * @param string $path The absolute HTTPS URL or Symfony route name
     * @param array<string, mixed> $params Route parameters (only used when path is a route name)
     */
    public function __construct(
        public string $path,
        public array $params = [],
    ) {
    }

    /**
     * @param array<string, mixed> $params Route parameters
     */
    public static function create(string $path, array $params = []): self
    {
        return new self($path, $params);
    }
}
