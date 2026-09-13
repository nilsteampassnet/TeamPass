<?php

declare(strict_types=1);

namespace Webauthn;

/**
 * @see https://www.w3.org/TR/webauthn/#iface-pkcredential
 */
class PublicKeyCredential extends Credential
{
    public function __construct(
        string $type,
        string $rawId,
        public readonly AuthenticatorResponse $response
    ) {
        parent::__construct($type, $rawId);
    }

    public static function create(string $type, string $rawId, AuthenticatorResponse $response): self
    {
        return new self($type, $rawId, $response);
    }

    public function getPublicKeyCredentialDescriptor(): PublicKeyCredentialDescriptor
    {
        $transport = $this->response instanceof AuthenticatorAttestationResponse ? $this->response->transports : [];

        return PublicKeyCredentialDescriptor::create($this->type, $this->rawId, $transport);
    }
}
