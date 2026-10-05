<?php

declare(strict_types=1);

namespace Webauthn\AttestationStatement;

interface AttestationStatementSupportManagerAwareInterface
{
    public function setAttestationStatementSupportManager(AttestationStatementSupportManager $manager): void;
}
