<?php

declare(strict_types=1);

namespace Webauthn;

/**
 * @see https://w3c.github.io/webappsec-credential-management/#credential
 */
abstract class Credential
{
    public function __construct(
        public readonly string $type,
        public readonly string $rawId,
    ) {
    }
}
