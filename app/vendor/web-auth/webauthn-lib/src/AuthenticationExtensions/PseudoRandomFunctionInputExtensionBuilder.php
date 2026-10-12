<?php

declare(strict_types=1);

namespace Webauthn\AuthenticationExtensions;

use function array_key_exists;
use ParagonIE\ConstantTime\Base64UrlSafe;

final class PseudoRandomFunctionInputExtensionBuilder
{
    /**
     * @var array{eval?: array{first: string, second?: string}, evalByCredential?: array<string, array{first: string, second?: string}>}
     */
    private array $values = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function withInputs(string $first, null|string $second = null): self
    {
        $eval = [
            'first' => Base64UrlSafe::encodeUnpadded($first),
        ];
        if ($second !== null) {
            $eval['second'] = Base64UrlSafe::encodeUnpadded($second);
        }
        $this->values['eval'] = $eval;

        return $this;
    }

    /**
     * The credential ID is expected in its raw binary form, as stored in a credential record. The specification requires
     * the keys of the "evalByCredential" map to be the base64url encoding of the credential ID, so the encoding is done
     * here and MUST NOT be done by the caller.
     */
    public function withCredentialInputs(string $credentialId, string $first, null|string $second = null): self
    {
        $eval = [
            'first' => Base64UrlSafe::encodeUnpadded($first),
        ];
        if ($second !== null) {
            $eval['second'] = Base64UrlSafe::encodeUnpadded($second);
        }
        if (! array_key_exists('evalByCredential', $this->values)) {
            $this->values['evalByCredential'] = [];
        }
        $this->values['evalByCredential'][Base64UrlSafe::encodeUnpadded($credentialId)] = $eval;

        return $this;
    }

    public function build(): PseudoRandomFunctionInputExtension
    {
        return new PseudoRandomFunctionInputExtension('prf', $this->values);
    }
}
