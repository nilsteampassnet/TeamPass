<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

interface DependencyLoaderAware
{
    public function setDependencyLoader(DependencyLoader $loader): void;
}
