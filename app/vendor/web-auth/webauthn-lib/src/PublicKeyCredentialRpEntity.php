<?php

declare(strict_types=1);

namespace Webauthn;

class PublicKeyCredentialRpEntity extends PublicKeyCredentialEntity
{
    /**
     * @deprecated since 5.3.0 and will be removed in 6.0.0. The serialized options default rp.name to the Relying Party ID. Please set "" instead.
     */
    public string $name = '';

    public function __construct(
        string $name = '',
        public readonly ?string $id = null,
        ?string $icon = null
    ) {

        parent::__construct($name, $icon);
    }

    public static function create(string $name = '', ?string $id = null, ?string $icon = null): self
    {
        return new self($name, $id, $icon);
    }
}
