<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker\Contracts;

use Dominikb\ComposerLicenseChecker\Dependency;

interface DependencyLoader
{
    /**
     * @return Dependency[]
     */
    public function loadDependencies(string $composer, string $project): array;
}
