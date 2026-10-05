<?php

declare(strict_types=1);

namespace Webauthn;

use function strlen;
use Webauthn\Exception\InvalidDataException;

class PublicKeyCredentialUserEntity extends PublicKeyCredentialEntity
{
    public readonly string $id;

    public function __construct(
        string $name,
        string $id,
        public readonly string $displayName,
        ?string $icon = null
    ) {
        parent::__construct('', $icon);
        strlen($id) <= 64 || throw InvalidDataException::create($id, 'User ID max length is 64 bytes');
        $this->id = $id;
        $this->name = $name;
    }

    public static function create(string $name, string $id, string $displayName, ?string $icon = null): self
    {
        return new self($name, $id, $displayName, $icon);
    }
}
