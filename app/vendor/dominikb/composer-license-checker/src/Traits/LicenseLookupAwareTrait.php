<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Traits;

use Dominikb\ComposerLicenseChecker\Contracts\LicenseLookup;

trait LicenseLookupAwareTrait
{
    /** @var LicenseLookup|null */
    protected $licenseLookup;

    public function setLicenseLookup(LicenseLookup $licenseLookup): void
    {
        $this->licenseLookup = $licenseLookup;
    }
}
