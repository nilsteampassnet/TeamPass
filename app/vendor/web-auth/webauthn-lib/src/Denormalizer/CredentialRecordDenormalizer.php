<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function array_key_exists;
use function assert;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\Exception\InvalidDataException;
use Webauthn\TrustPath\TrustPath;
use Webauthn\Util\Base64;

/**
 * @final
 */
class CredentialRecordDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    /**
     * @throws InvalidDataException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @var array{publicKeyCredentialId: string, credentialPublicKey: string, userHandle: string, type: string, transports: string[], attestationType: string, trustPath: array<string, mixed>, aaguid: string, counter: int, otherUI?: ?array<string, mixed>, backupEligible?: ?bool, backupStatus?: ?bool, uvInitialized?: ?bool} $data */
        $keys = ['publicKeyCredentialId', 'credentialPublicKey', 'userHandle'];
        foreach ($keys as $key) {
            array_key_exists($key, $data) || throw InvalidDataException::create($data, 'Missing ' . $key);
            $data[$key] = Base64::decode($data[$key]);
        }

        return CredentialRecord::create(
            $data['publicKeyCredentialId'],
            $data['type'],
            $data['transports'],
            $data['attestationType'],
            $this->denormalizer->denormalize($data['trustPath'], TrustPath::class, $format, $context),
            Uuid::fromString($data['aaguid']),
            $data['credentialPublicKey'],
            $data['userHandle'],
            $data['counter'],
            $data['otherUI'] ?? null,
            $data['backupEligible'] ?? null,
            $data['backupStatus'] ?? null,
            $data['uvInitialized'] ?? null,
        );
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === CredentialRecord::class;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            CredentialRecord::class => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof CredentialRecord);
        $result = [
            'publicKeyCredentialId' => Base64UrlSafe::encodeUnpadded($data->publicKeyCredentialId),
            'type' => $data->type,
            'transports' => $data->transports,
            'attestationType' => $data->attestationType,
            'trustPath' => $this->normalizer->normalize($data->trustPath, $format, $context),
            'aaguid' => $this->normalizer->normalize($data->aaguid, $format, $context),
            'credentialPublicKey' => Base64UrlSafe::encodeUnpadded($data->credentialPublicKey),
            'userHandle' => Base64UrlSafe::encodeUnpadded($data->userHandle),
            'counter' => $data->counter,
            'otherUI' => $data->otherUI,
            'backupEligible' => $data->backupEligible,
            'backupStatus' => $data->backupStatus,
            'uvInitialized' => $data->uvInitialized,
        ];

        return array_filter($result, static fn ($value): bool => $value !== null);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CredentialRecord;
    }
}
