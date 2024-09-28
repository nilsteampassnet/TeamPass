<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

interface LicenseLookupAware
{
    public function setLicenseLookup(LicenseLookup $licenseLookup): void;
}
