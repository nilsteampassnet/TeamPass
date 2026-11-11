<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function array_key_exists;
use function is_array;
use function is_string;
use ParagonIE\ConstantTime\Base64UrlSafe;
use function sprintf;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webauthn\AttestationStatement\AttestationObject;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CollectedClientData;
use Webauthn\Exception\InvalidDataException;
use Webauthn\Util\Base64;

final class AuthenticatorAttestationResponseDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @throws InvalidDataException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        is_array($data) || throw InvalidDataException::create(
            $data,
            'Invalid authenticator attestation response. Shall be an object.'
        );
        /** @var array{clientDataJSON: string, attestationObject: string, transports?: string[]} $data */
        $keys = ['clientDataJSON', 'attestationObject'];
        foreach ($keys as $key) {
            array_key_exists($key, $data) || throw InvalidDataException::create($data, 'Missing ' . $key);
            is_string($data[$key]) || throw InvalidDataException::create(
                $data,
                sprintf('Invalid parameter "%s". Shall be a string.', $key)
            );
        }
        $data['clientDataJSON'] = Base64UrlSafe::decodeNoPadding($data['clientDataJSON']);
        $data['attestationObject'] = Base64::decode($data['attestationObject']);

        /** @var CollectedClientData $clientDataJSON */
        $clientDataJSON = $this->denormalizer->denormalize(
            $data['clientDataJSON'],
            CollectedClientData::class,
            $format,
            $context
        );
        /** @var AttestationObject $attestationObject */
        $attestationObject = $this->denormalizer->denormalize(
            $data['attestationObject'],
            AttestationObject::class,
            $format,
            $context
        );

        return AuthenticatorAttestationResponse::create(
            $clientDataJSON,
            $attestationObject,
            $data['transports'] ?? [],
        );
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === AuthenticatorAttestationResponse::class;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            AuthenticatorAttestationResponse::class => true,
        ];
    }
}
