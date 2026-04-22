<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Traits;

use Dominikb\ComposerLicenseChecker\Contracts\DependencyLoader;

trait DependencyLoaderAwareTrait
{
    /** @var DependencyLoader|null */
    protected $dependencyLoader;

    public function setDependencyLoader(DependencyLoader $loader): void
    {
        $this->dependencyLoader = $loader;
    }
}
