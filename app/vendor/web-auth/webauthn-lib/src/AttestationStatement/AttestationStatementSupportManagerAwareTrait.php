<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

trait AttestationStatementSupportManagerAwareTrait
{
    private AttestationStatementSupportManager $attestationStatementSupportManager;

    public function setAttestationStatementSupportManager(AttestationStatementSupportManager $manager): void
    {
        $this->attestationStatementSupportManager = $manager;
    }
}
