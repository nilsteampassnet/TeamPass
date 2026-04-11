<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

use Dominikb\ComposerLicenseChecker\License;

interface LicenseLookup extends CacheAwareContract
{
    public function lookUp(string $license): License;
}
