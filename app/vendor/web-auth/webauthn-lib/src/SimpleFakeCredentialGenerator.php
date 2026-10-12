<?php

declare(strict_types=1);

namespace Webauthn;

use function count;
use function ord;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use function trigger_deprecation;

final readonly class SimpleFakeCredentialGenerator implements FakeCredentialGenerator
{
    public function __construct(
        private null|CacheItemPoolInterface $cache = null,
        private string $secret = '',
    ) {
        if ($secret === '') {
            trigger_deprecation(
                'web-auth/webauthn-lib',
                '5.3.5',
                'Instantiating "%s" without a secret is deprecated since 5.3.5 and a non-empty secret will be required in 6.0.0. With an empty secret the generated fake credentials depend only on the username and become predictable, which lets an unauthenticated requester tell fake responses from real ones and defeats the username enumeration protection. Provide a non-empty secret, for example the application secret.',
                self::class
            );
        }
    }

    /**
     * @return PublicKeyCredentialDescriptor[]
     */
    public function generate(Request $request, string $username): array
    {
        if ($this->cache === null) {
            return $this->generateCredentials($username);
        }

        $cacheKey = 'fake_credentials_' . hash('xxh128', $username);
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            /** @var PublicKeyCredentialDescriptor[] */
            return $cacheItem->get();
        }

        $credentials = $this->generateCredentials($username);
        $cacheItem->set($credentials);
        $this->cache->save($cacheItem);

        return $credentials;
    }

    /**
     * @return PublicKeyCredentialDescriptor[]
     */
    private function generateCredentials(string $username): array
    {
        $transports = [
            PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_USB,
            PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_NFC,
            PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_BLE,
            PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_HYBRID,
            PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_INTERNAL,
            PublicKeyCredentialDescriptor::AUTHENTICATOR_TRANSPORT_SMART_CARD,
        ];
        $seed = hash('sha256', $username . $this->secret, true);
        $count = (ord($seed[0]) % 3) + 1;

        $credentials = [];
        for ($i = 0; $i < $count; $i++) {
            $credSeed = hash('sha256', $seed . pack('N', $i), true);
            $transportCount = (ord($credSeed[0]) % 2) + 1;
            $selectedTransports = [];
            for ($j = 0; $j < $transportCount; $j++) {
                $index = ord($credSeed[$j + 1]) % count($transports);
                $selectedTransports[] = $transports[$index];
            }
            $selectedTransports = array_values(array_unique($selectedTransports));
            $credentials[] = PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                hash('sha256', $credSeed . $username),
                $selectedTransports
            );
        }

        return $credentials;
    }
}
