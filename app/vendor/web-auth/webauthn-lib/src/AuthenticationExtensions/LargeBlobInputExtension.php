<?php

declare(strict_types=1);

namespace Webauthn\AuthenticationExtensions;

use function assert;
use function in_array;

final class LargeBlobInputExtension extends AuthenticationExtension
{
    public const REQUIRED = 'required';

    public const PREFERRED = 'preferred';

    public static function support(string $support): AuthenticationExtension
    {
        assert(in_array($support, [self::REQUIRED, self::PREFERRED], true), 'Invalid support value.');

        return self::create('largeBlob', [
            'support' => $support,
        ]);
    }

    public static function read(): AuthenticationExtension
    {
        return self::create('largeBlob', [
            'read' => true,
        ]);
    }

    public static function write(string $value): AuthenticationExtension
    {
        return self::create('largeBlob', [
            'write' => $value,
        ]);
    }
}
