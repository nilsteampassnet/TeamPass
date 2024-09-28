<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use DateTimeImmutable;

class NoLookupLicenses extends License
{
    public function __construct(string $shortName)
    {
        $this->setShortName($shortName);
        $this->setCan([]);
        $this->setCannot([]);
        $this->setMust([]);
        $this->setSource('-');
        $this->setCreatedAt(new DateTimeImmutable);
    }
}
