<?php

declare(strict_types=1);

namespace Webauthn;

abstract class PublicKeyCredentialEntity
{
    /**
     * This property is not deprecated as such: the deprecation is scoped to the concrete entities. It is deprecated on
     * PublicKeyCredentialRpEntity, from which it is removed in 6.0.0, but not on PublicKeyCredentialUserEntity, where
     * it remains a first-class field.
     */
    public string $name;

    /**
     * @deprecated since 5.1.0 and will be removed in 6.0.0. This value is always null.
     */
    public ?string $icon = null;

    public function __construct(string $name, ?string $icon = null)
    {
        if ($name !== '') {
            trigger_deprecation(
                'web-auth/webauthn-lib',
                '5.3.0',
                'Setting the "name" field on "PublicKeyCredentialRpEntity" is deprecated since 5.3.0 and will be removed in 6.0.0. The serialized options default rp.name to the Relying Party ID. Please set "" instead.'
            );
        }
        $this->name = $name;

        if ($icon !== null) {
            trigger_deprecation(
                'web-auth/webauthn-lib',
                '5.1.0',
                'The parameter "$icon" is deprecated since 5.1.0 and will be removed in 6.0.0. This value has no effect. Please set "null" instead.'
            );
        }
    }
}
