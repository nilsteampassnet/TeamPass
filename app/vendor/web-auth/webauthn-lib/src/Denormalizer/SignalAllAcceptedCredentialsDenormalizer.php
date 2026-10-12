<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function assert;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\Signal\AllAcceptedCredentials;

class SignalAllAcceptedCredentialsDenormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            AllAcceptedCredentials::class => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof AllAcceptedCredentials);

        /** @var array<string, mixed> $normalized_rp */
        $normalized_rp = $this->normalizer->normalize($data->rp, $format, $context);

        /** @var array<string, mixed> $normalized_user */
        $normalized_user = $this->normalizer->normalize($data->user, $format, $context);

        /** @var array<int, array{id: string, type: string}> $normalized_credentials */
        $normalized_credentials = array_map(
            fn (PublicKeyCredentialDescriptor $credential) => $this->normalizer->normalize(
                $credential,
                $format,
                $context
            ),
            $data->allAcceptedCredentials
        );

        return [
            'rpId' => $normalized_rp['id'],
            'userId' => $normalized_user['id'],
            'allAcceptedCredentialIds' => array_map(
                static fn (array $credential): string => $credential['id'],
                $normalized_credentials
            ),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AllAcceptedCredentials;
    }
}
