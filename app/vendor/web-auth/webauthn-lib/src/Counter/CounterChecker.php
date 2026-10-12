<?php

declare(strict_types=1);

namespace Webauthn\Counter;

use Webauthn\CredentialRecord;

interface CounterChecker
{
    public function check(CredentialRecord $credentialRecord, int $currentCounter): void;
}
