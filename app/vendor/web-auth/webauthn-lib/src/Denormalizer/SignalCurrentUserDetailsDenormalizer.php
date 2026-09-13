<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function assert;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\Signal\CurrentUserDetails;

class SignalCurrentUserDetailsDenormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            CurrentUserDetails::class => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof CurrentUserDetails);

        /** @var array<string, mixed> $normalized_rp */
        $normalized_rp = $this->normalizer->normalize($data->rp, $format, $context);

        /** @var array<string, mixed> $normalized_user */
        $normalized_user = $this->normalizer->normalize($data->user, $format, $context);

        return [
            'rpId' => $normalized_rp['id'],
            'userId' => $normalized_user['id'],
            'name' => $normalized_user['name'],
            'displayName' => $normalized_user['displayName'],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CurrentUserDetails;
    }
}
