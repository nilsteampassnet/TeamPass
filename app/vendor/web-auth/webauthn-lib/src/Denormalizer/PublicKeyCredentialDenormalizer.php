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
use Webauthn\AuthenticatorResponse;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\Util\Base64;

final class PublicKeyCredentialDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @throws InvalidDataException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        is_array($data) || throw InvalidDataException::create(
            $data,
            'Invalid public key credential. Shall be an object.'
        );
        /** @var array{id: string, rawId: string, type: string, response: array<string, mixed>} $data */
        $keys = ['id', 'rawId', 'type', 'response'];
        foreach ($keys as $key) {
            array_key_exists($key, $data) || throw InvalidDataException::create($data, 'Missing ' . $key);
        }
        foreach (['id', 'rawId', 'type'] as $key) {
            is_string($data[$key]) || throw InvalidDataException::create(
                $data,
                sprintf('Invalid parameter "%s". Shall be a string.', $key)
            );
        }
        is_array($data['response']) || throw InvalidDataException::create(
            $data,
            'Invalid parameter "response". Shall be an object.'
        );
        $data['type'] === PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY || throw InvalidDataException::create(
            $data,
            'Invalid parameter "type". Shall be "public-key".'
        );
        $id = Base64UrlSafe::decodeNoPadding($data['id']);
        $rawId = Base64::decode($data['rawId']);
        hash_equals($id, $rawId) || throw InvalidDataException::create($data, 'Invalid ID');
        $data['rawId'] = $rawId;

        /** @var AuthenticatorResponse $response */
        $response = $this->denormalizer->denormalize(
            $data['response'],
            AuthenticatorResponse::class,
            $format,
            $context
        );

        return PublicKeyCredential::create($data['type'], $data['rawId'], $response);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === PublicKeyCredential::class;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            PublicKeyCredential::class => true,
        ];
    }
}
