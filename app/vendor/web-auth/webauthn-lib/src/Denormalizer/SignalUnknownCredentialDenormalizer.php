<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function assert;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\Signal\UnknownCredential;

class SignalUnknownCredentialDenormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            UnknownCredential::class => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof UnknownCredential);

        /** @var array<string, mixed> $normalized_rp */
        $normalized_rp = $this->normalizer->normalize($data->rp, $format, $context);

        /** @var array<string, mixed> $normalized_credential */
        $normalized_credential = $this->normalizer->normalize($data->credential, $format, $context);

        return [
            'rpId' => $normalized_rp['id'],
            'credentialId' => $normalized_credential['id'],
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UnknownCredential;
    }
}
