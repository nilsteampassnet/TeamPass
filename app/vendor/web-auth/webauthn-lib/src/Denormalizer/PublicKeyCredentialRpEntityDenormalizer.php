<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function assert;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\PublicKeyCredentialRpEntity;

final class PublicKeyCredentialRpEntityDenormalizer implements NormalizerInterface
{
    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            PublicKeyCredentialRpEntity::class => true,
        ];
    }

    /**
     * Per W3C IDL, `PublicKeyCredentialEntity.name` is required. `PublicKeyCredentialRpEntity::$name` is deprecated
     * and removed in 6.0.0, so the name is defaulted to the Relying Party ID when it is left empty: the payload stays
     * well-formed for the clients that refuse to start a ceremony without it, and callers no longer have to set the
     * deprecated field themselves.
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): ?array
    {
        assert($object instanceof PublicKeyCredentialRpEntity);
        $name = $object->name;
        $data = array_filter(
            [
                'id' => $object->id,
                'name' => $name === '' ? $object->id : $name,
                'icon' => $object->icon,
            ],
            static fn (?string $value): bool => ($value !== null && $value !== ''),
        );

        return $data === [] ? null : $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PublicKeyCredentialRpEntity;
    }
}
