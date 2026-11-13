<?php

declare(strict_types=1);

namespace Webauthn;

use function array_key_exists;
use function in_array;
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

        $tokenBinding = $data['tokenBinding'] ?? null;
        if ($tokenBinding !== null) {
            self::checkTokenBinding($data, $tokenBinding);
        }

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

    /**
     * The tokenBinding member is [RESERVED] since Webauthn Level 3, which still defines the TokenBinding dictionary it
     * holds (section 5.8.1): a required status and, when the status is "present", the Token Binding ID. A client that
     * sends the member sends that dictionary, and the FIDO Conformance Tools expect any other value to be refused. The
     * accepted statuses are "present" and "supported", the TokenBindingStatus values, and "not-supported", which the
     * tools name as well. The value itself is not used.
     *
     * @param mixed[] $data
     */
    private static function checkTokenBinding(array $data, mixed $tokenBinding): void
    {
        is_array($tokenBinding) || throw InvalidDataException::create(
            $data,
            'Invalid parameter "tokenBinding". Shall be an object.'
        );
        $status = $tokenBinding['status'] ?? null;
        in_array($status, ['present', 'supported', 'not-supported'], true) || throw InvalidDataException::create(
            $data,
            'Invalid parameter "tokenBinding.status". Shall be "present", "supported" or "not-supported".'
        );
        if ($status === 'present') {
            $id = $tokenBinding['id'] ?? null;
            (is_string($id) && $id !== '') || throw InvalidDataException::create(
                $data,
                'Invalid parameter "tokenBinding.id". Shall be a non-empty string when the status is "present".'
            );
        }
    }
}
