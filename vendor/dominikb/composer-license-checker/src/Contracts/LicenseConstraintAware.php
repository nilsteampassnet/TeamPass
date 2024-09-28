<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

interface LicenseConstraintAware
{
    public function setLicenseConstraintHandler(LicenseConstraintHandler $handler): void;
}
