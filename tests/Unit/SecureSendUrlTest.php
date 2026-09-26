<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/secure_send_url.php';

class SecureSendUrlTest extends TestCase
{
    #[DataProvider('addresses')]
    public function testAddresses(string $main, string $public, bool $selected, string $expected): void
    {
        self::assertSame($expected, secureSendBaseUrl(['cpassman_url' => $main, 'otv_subdomain' => $public], $selected));
    }

    public static function addresses(): array
    {
        return [
            ['https://vault.example.com', 'share', false, 'https://vault.example.com'],
            ['https://vault.example.com:8443/vault/', 'share', true, 'https://share.vault.example.com:8443/vault'],
            ['https://www.example.com:8443/vault', 'share', true, 'https://share.example.com:8443/vault'],
            ['https://mywww.example.com/vault', 'share', true, 'https://share.mywww.example.com/vault'],
            ['https://vault.example.com:8443/vault', 'share.example.com', true, 'https://share.example.com:8443/vault'],
            ['https://vault.example.com:8443/vault', 'https://share.example.com', true, 'https://share.example.com'],
            ['https://vault.example.com', ' https://SHARE.example.com:9443/secret/ ', true, 'https://share.example.com:9443/secret'],
            ['http://localhost/vault/', '', false, 'http://localhost/vault'],
            ['https://tp_internal:8443/vault/', '', false, 'https://tp_internal:8443/vault'],
            ['', 'https://share.example.com', true, 'https://share.example.com'],
        ];
    }

    #[DataProvider('invalidAddresses')]
    public function testInvalidPublicAddressNeverFallsBack(string $public): void
    {
        $this->expectException(InvalidArgumentException::class);
        secureSendBaseUrl(['cpassman_url' => 'https://vault.example.com', 'otv_subdomain' => $public], true);
    }

    public static function invalidAddresses(): array
    {
        return array_map(static fn (string $value): array => [$value], [
            '', 'http://share.example.com', '//share.example.com', 'https://user:pass@share.example.com',
            'https://share.example.com?otv=1', 'https://share.example.com#key', 'https://share.example.com/?',
            "https://share.example.com\r\nHeader: value", 'https://share.example.com\\@evil.example.com',
            'https://share.example.com/../vault', 'https://share.example.com/%2e%2e/vault',
            'https://share.example.com/%0a', 'https://share.example.com//vault',
            'https://share.example.com:99999', 'share.example.com/path', '-bad', 'bad name',
        ]);
    }

    public function testOnlyAnExactHostnameIsAccepted(): void
    {
        $settings = ['cpassman_url' => 'https://vault.example.com', 'otv_subdomain' => 'https://share.example.com/vault'];
        self::assertTrue(secureSendHostIsAllowed($settings, ['shared_globaly' => 1], 'SHARE.example.com'));
        self::assertFalse(secureSendHostIsAllowed($settings, ['shared_globaly' => 1], 'share.example.com.evil.test'));
        self::assertFalse(secureSendHostIsAllowed($settings, ['shared_globaly' => 1], 'vault.example.com'));
        self::assertTrue(secureSendHostIsAllowed($settings, ['shared_globaly' => 0], 'share.example.com'));
        self::assertFalse(secureSendHostIsAllowed(['otv_subdomain' => 'bad/path'], ['shared_globaly' => 1], 'bad'));
    }

    public function testLinkPreservesPathPortAndEncodesCredentials(): void
    {
        self::assertSame('https://share.example.com:9443/vault/index.php?otv=1&key=a%2Bb%26c', secureSendUrl(
            ['otv_subdomain' => 'https://share.example.com:9443/vault/'], true, ['otv' => 1, 'key' => 'a+b&c']
        ));
    }

    public function testInternalHostsBypassPublicValidation(): void
    {
        foreach (['', 'tp_internal:8080', 'alias.example.net', 'backend', 'invalid host'] as $host) {
            self::assertTrue(secureSendHostIsAllowed(['otv_subdomain' => 'broken/path'], ['shared_globaly' => 0], $host));
        }
    }

    public function testPublicHostParsingRejectsAuthorityConfusion(): void
    {
        $settings = ['otv_subdomain' => 'https://share.example.com'];
        self::assertTrue(secureSendHostIsAllowed($settings, ['shared_globaly' => 1], 'SHARE.example.com.:443'));
        foreach (['', 'share.example.com/evil', 'user@share.example.com', 'share.example.com:99999',
            'share.example.com?x', 'share.example.com#x', "share.example.com\n", 'tp_internal',
            'share.example.com\\evil', 'share.example.com.evil.test'] as $host) {
            self::assertFalse(secureSendHostIsAllowed($settings, ['shared_globaly' => 1], $host), $host);
        }
    }

}
