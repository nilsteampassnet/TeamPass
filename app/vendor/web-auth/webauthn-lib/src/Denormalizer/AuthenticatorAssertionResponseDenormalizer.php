<?php

declare(strict_types=1);

namespace Webauthn\Denormalizer;

use function array_key_exists;
use function is_array;
use function is_string;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RangeException;
use function sprintf;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webauthn\AttestationStatement\AttestationObject;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorData;
use Webauthn\CollectedClientData;
use Webauthn\Exception\InvalidDataException;

final class AuthenticatorAssertionResponseDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    /**
     * @throws InvalidDataException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        is_array($data) || throw InvalidDataException::create(
            $data,
            'Invalid authenticator assertion response. Shall be an object.'
        );
        /** @var array{authenticatorData: string, signature: string, clientDataJSON: string, userHandle?: ?string, attestationObject?: string} $data */
        $keys = ['authenticatorData', 'signature', 'clientDataJSON'];
        foreach ($keys as $key) {
            array_key_exists($key, $data) || throw InvalidDataException::create($data, 'Missing ' . $key);
            is_string($data[$key]) || throw InvalidDataException::create(
                $data,
                sprintf('Invalid parameter "%s". Shall be a string.', $key)
            );
        }
        $data['authenticatorData'] = self::decodeBase64Url($data['authenticatorData'], 'authenticatorData');
        $data['signature'] = self::decodeBase64Url($data['signature'], 'signature');
        $data['clientDataJSON'] = Base64UrlSafe::decodeNoPadding($data['clientDataJSON']);
        $userHandle = $data['userHandle'] ?? null;
        if ($userHandle !== '' && $userHandle !== null) {
            $userHandle = self::decodeBase64Url($userHandle, 'userHandle');
        }

        /** @var CollectedClientData $clientDataJSON */
        $clientDataJSON = $this->denormalizer->denormalize(
            $data['clientDataJSON'],
            CollectedClientData::class,
            $format,
            $context
        );
        /** @var AuthenticatorData $authenticatorData */
        $authenticatorData = $this->denormalizer->denormalize(
            $data['authenticatorData'],
            AuthenticatorData::class,
            $format,
            $context
        );

        return AuthenticatorAssertionResponse::create(
            $clientDataJSON,
            $authenticatorData,
            $data['signature'],
            $userHandle ?? null,
            ! isset($data['attestationObject']) ? null : $this->denormalizer->denormalize(
                $data['attestationObject'],
                AttestationObject::class,
                $format,
                $context
            ),
        );
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        return $type === AuthenticatorAssertionResponse::class;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            AuthenticatorAssertionResponse::class => true,
        ];
    }

    /**
     * The binary members of an assertion response are base64url encoded (WebAuthn Level 3, section 5.1,
     * AuthenticatorAssertionResponseJSON). A correct padding is tolerated. Anything else is refused: a character outside
     * the base64url alphabet, such as the "+" and "/" of standard base64, an incorrect padding, a last character that
     * completes no byte, or pad bits that are not zero (RFC 4648, section 3.5).
     *
     * @throws InvalidDataException
     */
    private static function decodeBase64Url(string $encoded, string $member): string
    {
        try {
            return Base64UrlSafe::decode($encoded, true);
        } catch (RangeException $exception) {
            throw InvalidDataException::create(
                $encoded,
                sprintf('Invalid parameter "%s". Shall be base64url encoded.', $member),
                $exception
            );
        }
    }
}
