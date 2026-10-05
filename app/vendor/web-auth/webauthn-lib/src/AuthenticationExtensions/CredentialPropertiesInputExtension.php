<?php

declare(strict_types=1);

namespace Webauthn\AuthenticationExtensions;

final class CredentialPropertiesInputExtension extends AuthenticationExtension
{
    public static function enable(): AuthenticationExtension
    {
        return self::create('credProps', true);
    }

    public static function disable(): AuthenticationExtension
    {
        return self::create('credProps', false);
    }
}
