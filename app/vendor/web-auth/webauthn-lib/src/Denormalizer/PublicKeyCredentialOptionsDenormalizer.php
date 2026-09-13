<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function array_key_exists;
use function assert;
use function count;
use function in_array;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webauthn\AuthenticationExtensions\AuthenticationExtensions;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

final class PublicKeyCredentialOptionsDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    /**
     * @throws BadMethodCallException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @var array{challenge?: string, rp?: array<string, mixed>, user?: array<string, mixed>, pubKeyCredParams?: list<array<string, mixed>>, authenticatorSelection?: array<string, mixed>, attestation?: string, excludeCredentials?: list<array{id: string, type?: string, transports?: list<string>}>, allowCredentials?: list<array{id: string, type?: string, transports?: list<string>}>, timeout?: int<1, max>, extensions?: array<string, mixed>, hints?: list<string>, rpId?: string, userVerification?: string} $data */
        if (array_key_exists('challenge', $data)) {
            $data['challenge'] = Base64UrlSafe::decodeNoPadding($data['challenge']);
        }

        foreach (['allowCredentials', 'excludeCredentials'] as $key) {
            if (array_key_exists($key, $data)) {
                /** @var list<array{id: string, type?: string, transports?: list<string>}> $credentials */
                $credentials = $data[$key] ?? [];
                foreach ($credentials as $item => $allowCredential) {
                    $data[$key][$item]['id'] = Base64UrlSafe::decodeNoPadding($allowCredential['id']);
                }
            }
        }
        if ($type === PublicKeyCredentialCreationOptions::class) {
            /** @var PublicKeyCredentialRpEntity $rp */
            $rp = ! isset($data['rp']) ? PublicKeyCredentialRpEntity::create() : $this->denormalizer->denormalize(
                $data['rp'],
                PublicKeyCredentialRpEntity::class,
                $format,
                $context
            );
            /** @var PublicKeyCredentialUserEntity $user */
            $user = $this->denormalizer->denormalize(
                $data['user'] ?? [],
                PublicKeyCredentialUserEntity::class,
                $format,
                $context
            );
            /** @var list<PublicKeyCredentialParameters> $pubKeyCredParams */
            $pubKeyCredParams = ! isset($data['pubKeyCredParams']) ? [] : $this->denormalizer->denormalize(
                $data['pubKeyCredParams'],
                PublicKeyCredentialParameters::class . '[]',
                $format,
                $context
            );
            /** @var ?AuthenticatorSelectionCriteria $authenticatorSelection */
            $authenticatorSelection = ! isset($data['authenticatorSelection']) ? null : $this->denormalizer->denormalize(
                $data['authenticatorSelection'],
                AuthenticatorSelectionCriteria::class,
                $format,
                $context
            );
            /** @var list<PublicKeyCredentialDescriptor> $excludeCredentials */
            $excludeCredentials = ! isset($data['excludeCredentials']) ? [] : $this->denormalizer->denormalize(
                $data['excludeCredentials'],
                PublicKeyCredentialDescriptor::class . '[]',
                $format,
                $context
            );
            /** @var ?AuthenticationExtensions $extensions */
            $extensions = ! isset($data['extensions']) ? null : $this->denormalizer->denormalize(
                $data['extensions'],
                AuthenticationExtensions::class,
                $format,
                $context
            );

            return PublicKeyCredentialCreationOptions::create(
                $rp,
                $user,
                $data['challenge'] ?? '',
                $pubKeyCredParams,
                $authenticatorSelection,
                $data['attestation'] ?? null,
                $excludeCredentials,
                $data['timeout'] ?? null,
                $extensions,
                $data['hints'] ?? [],
            );
        }
        if ($type === PublicKeyCredentialRequestOptions::class) {
            /** @var list<PublicKeyCredentialDescriptor> $allowCredentials */
            $allowCredentials = ! isset($data['allowCredentials']) ? [] : $this->denormalizer->denormalize(
                $data['allowCredentials'],
                PublicKeyCredentialDescriptor::class . '[]',
                $format,
                $context
            );
            /** @var ?AuthenticationExtensions $extensions */
            $extensions = ! isset($data['extensions']) ? null : $this->denormalizer->denormalize(
                $data['extensions'],
                AuthenticationExtensions::class,
                $format,
                $context
            );

            return PublicKeyCredentialRequestOptions::create(
                $data['challenge'] ?? '',
                $data['rpId'] ?? null,
                $allowCredentials,
                $data['userVerification'] ?? null,
                $data['timeout'] ?? null,
                $extensions,
                $data['hints'] ?? [],
            );
        }
        throw new BadMethodCallException('Unsupported type');
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return in_array(
            $type,
            [PublicKeyCredentialCreationOptions::class, PublicKeyCredentialRequestOptions::class],
            true
        );
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PublicKeyCredentialCreationOptions || $data instanceof PublicKeyCredentialRequestOptions;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            PublicKeyCredentialCreationOptions::class => true,
            PublicKeyCredentialRequestOptions::class => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        assert(
            $object instanceof PublicKeyCredentialCreationOptions || $object instanceof PublicKeyCredentialRequestOptions
        );
        $json = [
            'challenge' => Base64UrlSafe::encodeUnpadded($object->challenge),
            'timeout' => $object->timeout,
            'extensions' => $object->extensions->count() === 0 ? null : $this->normalizer->normalize(
                $object->extensions,
                $format,
                $context
            ),
            'hints' => count($object->hints) === 0 ? null : $object->hints,
        ];

        if ($object instanceof PublicKeyCredentialCreationOptions) {
            $json = [
                ...$json,
                'rp' => $this->normalizer->normalize($object->rp, $format, $context),
                'user' => $this->normalizer->normalize($object->user, $format, $context),
                'pubKeyCredParams' => $this->normalizer->normalize(
                    $object->pubKeyCredParams,
                    PublicKeyCredentialParameters::class . '[]',
                    $context
                ),
                'authenticatorSelection' => $object->authenticatorSelection === null ? null : $this->normalizer->normalize(
                    $object->authenticatorSelection,
                    $format,
                    $context
                ),
                'attestation' => $object->attestation,
                'excludeCredentials' => $this->normalizer->normalize($object->excludeCredentials, $format, $context),
            ];
        }
        if ($object instanceof PublicKeyCredentialRequestOptions) {
            $json = [
                ...$json,
                'rpId' => $object->rpId,
                'allowCredentials' => $this->normalizer->normalize($object->allowCredentials, $format, $context),
                'userVerification' => $object->userVerification,
            ];
        }

        return array_filter($json, static fn ($value): bool => $value !== null);
    }
}
