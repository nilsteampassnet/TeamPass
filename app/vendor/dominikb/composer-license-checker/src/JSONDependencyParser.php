<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use Dominikb\ComposerLicenseChecker\Contracts\DependencyParser as DependencyParserContract;

class JSONDependencyParser implements DependencyParserContract
{
    public function parse(string $dependencyOutput): array
    {
        $dependencyOutput = json_decode($dependencyOutput, true);

        $parsed = [];
        foreach ($dependencyOutput['dependencies'] as $name => $info) {
            $parsed[] = (new Dependency)
                ->setName($name)
                ->setVersion($info['version'])
                ->setLicenses($info['license']);
        }

        return $parsed;
    }
}
