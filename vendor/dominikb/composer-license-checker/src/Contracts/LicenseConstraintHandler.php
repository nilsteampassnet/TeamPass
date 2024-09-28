<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

use Dominikb\ComposerLicenseChecker\ConstraintViolation;
use Dominikb\ComposerLicenseChecker\Dependency;

interface LicenseConstraintHandler
{
    public function setBlocklist(array $licenses): void;

    public function setAllowlist(array $licenses): void;

    /**
     * @param  Dependency[]|Dependency  $dependencies
     */
    public function allow($dependencies): void;

    /**
     * @param  Dependency[]  $dependencies
     * @return ConstraintViolation[]
     */
    public function detectViolations(array $dependencies): array;
}
