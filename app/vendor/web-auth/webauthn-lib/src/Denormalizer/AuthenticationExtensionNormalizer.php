<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function assert;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\AuthenticationExtensions\AuthenticationExtension;

final class AuthenticationExtensionNormalizer implements NormalizerInterface
{
    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            AuthenticationExtension::class => true,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        assert($object instanceof AuthenticationExtension);

        /** @var array<mixed> $value */
        $value = $object->value;

        return $value;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AuthenticationExtension;
    }
}
