<?php

declare(strict_types=1);

namespace Webauthn;

use function array_key_exists;
use function is_array;
use function is_string;
use const JSON_THROW_ON_ERROR;
use ParagonIE\ConstantTime\Base64UrlSafe;
use function sprintf;
use Webauthn\Exception\InvalidDataException;

class CollectedClientData
{
    /**
     * @var mixed[]
     */
    public readonly array $data;

    public readonly string $type;

    public readonly string $challenge;

    public readonly string $origin;

    public readonly null|string $topOrigin;

    public readonly bool $crossOrigin;

    /**
     * @param mixed[] $data
     */
    public function __construct(
        public readonly string $rawData,
        array $data
    ) {
        $type = $data['type'] ?? '';
        (is_string($type) && $type !== '') || throw InvalidDataException::create(
            $data,
            'Invalid parameter "type". Shall be a non-empty string.'
        );
        $this->type = $type;

        $challenge = $data['challenge'] ?? '';
        is_string($challenge) || throw InvalidDataException::create(
            $data,
            'Invalid parameter "challenge". Shall be a string.'
        );
        $challenge = Base64UrlSafe::decodeNoPadding($challenge);
        $challenge !== '' || throw InvalidDataException::create(
            $data,
            'Invalid parameter "challenge". Shall not be empty.'
        );
        $this->challenge = $challenge;

        $origin = $data['origin'] ?? '';
        (is_string($origin) && $origin !== '') || throw InvalidDataException::create(
            $data,
            'Invalid parameter "origin". Shall be a non-empty string.'
        );
        $this->origin = $origin;

        /** @var string|null $topOrigin */
        $topOrigin = $data['topOrigin'] ?? null;
        $this->topOrigin = $topOrigin;
        /** @var bool $crossOrigin */
        $crossOrigin = $data['crossOrigin'] ?? false;
        $this->crossOrigin = $crossOrigin;

        $this->data = $data;
    }

    /**
     * @param mixed[] $data
     */
    public static function create(string $rawData, array $data): self
    {
        return new self($rawData, $data);
    }

    /**
     * @deprecated Since 5.3.0 and will be removed in 6.0.0. No replacement as not used anymore.
     */
    public static function createFormJson(string $data): self
    {
        $rawData = Base64UrlSafe::decodeNoPadding($data);
        $json = json_decode($rawData, true, flags: JSON_THROW_ON_ERROR);
        is_array($json) || throw InvalidDataException::create($data, 'Invalid JSON data.');

        return self::create($rawData, $json);
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return array_keys($this->data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key): mixed
    {
        if (! $this->has($key)) {
            throw InvalidDataException::create($this->data, sprintf('The key "%s" is missing', $key));
        }

        return $this->data[$key];
    }
}
