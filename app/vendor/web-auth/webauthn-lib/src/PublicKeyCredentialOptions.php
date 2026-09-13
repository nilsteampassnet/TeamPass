<?php

declare(strict_types=1);

namespace Webauthn;

use function in_array;
use InvalidArgumentException;
use function is_string;
use function sprintf;
use Webauthn\AuthenticationExtensions\AuthenticationExtension;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;

abstract class PublicKeyCredentialOptions
{
    public const HINT_SECURITY_KEY = 'security-key';

    public const HINT_CLIENT_DEVICE = 'client-device';

    public const HINT_HYBRID = 'hybrid';

    public const HINTS = [self::HINT_SECURITY_KEY, self::HINT_CLIENT_DEVICE, self::HINT_HYBRID];

    public AuthenticationExtensions $extensions;

    /**
     * @param positive-int|null $timeout
     * @param null|AuthenticationExtensions|array<array-key, AuthenticationExtension> $extensions
     * @param string[] $hints
     * @protected
     */
    public function __construct(
        public string $challenge,
        public null|int $timeout = null,
        null|array|AuthenticationExtensions $extensions = null,
        public array $hints = [],
    ) {
        ($this->timeout === null || $this->timeout > 0) || throw new InvalidArgumentException('Invalid timeout');
        if ($extensions === null) {
            $this->extensions = AuthenticationExtensions::create();
        } elseif ($extensions instanceof AuthenticationExtensions) {
            $this->extensions = $extensions;
        } else {
            $this->extensions = AuthenticationExtensions::create($extensions);
        }

        foreach ($this->hints as $hint) {
            is_string($hint) || throw new InvalidArgumentException('Invalid hint: hints must be strings');
            in_array($hint, self::HINTS, true) || throw new InvalidArgumentException(
                sprintf('Invalid hint "%s". Allowed values are: %s', $hint, implode(', ', self::HINTS))
            );
        }
    }
}
