<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use Dominikb\ComposerLicenseChecker\Contracts\DependencyLoaderAware;
use Dominikb\ComposerLicenseChecker\Contracts\LicenseLookupAware;
use Dominikb\ComposerLicenseChecker\Traits\DependencyLoaderAwareTrait;
use Dominikb\ComposerLicenseChecker\Traits\LicenseLookupAwareTrait;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReportCommand extends Command implements LicenseLookupAware, DependencyLoaderAware
{
    use LicenseLookupAwareTrait, DependencyLoaderAwareTrait;

    protected function configure()
    {
        $this->setDefinition(new InputDefinition([
            new InputOption(
                'project-path',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Path to directory of composer.json file',
                realpath('.')
            ),
            new InputOption(
                'composer',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path to composer executable',
                realpath('./vendor/bin/composer')
            ),
            new InputOption(
                'no-cache',
                null,
                InputOption::VALUE_NONE,
                'Disables caching of license lookups'
            ),
            new InputOption(
                'show-packages',
                null,
                InputOption::VALUE_NONE,
                'Shows the packages for each license.'
            ),
            new InputOption(
                'grouped',
                null,
                InputOption::VALUE_NONE,
                'Display the packages grouped. Only valid with the \'show-packages\' option.'
            ),
            new InputOption(
                'filter',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Filter for specific licences.'
            ),
        ]));
    }

    public static function getDefaultName(): ?string
    {
        return 'report';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('grouped') && ! $input->getOption('show-packages')) {
            throw new \InvalidArgumentException('The option "grouped" is only allowed with "show-packages" option');
        }

        $dependencies = $this->dependencyLoader->loadDependencies(
            $input->getOption('composer'),
            $input->getOption('project-path')
        );

        $dependencies = $this->filterLicenses($dependencies, $input->getOption('filter'));

        $groupedByName = $this->groupDependenciesByLicense($dependencies);

        $shouldCache = ! $input->getOption('no-cache');

        $licenses = $this->lookUpLicenses(array_keys($groupedByName), $output, $shouldCache);

        /* @var License $license */
        $this->outputFormattedLicenses($output, $input, $licenses, $groupedByName);

        return self::SUCCESS;
    }

    /**
     * @param  Dependency[]  $dependencies
     * @return array
     */
    private function groupDependenciesByLicense(array $dependencies): array
    {
        $grouped = [];

        foreach ($dependencies as $dependency) {
            [$license] = $dependency->getLicenses();

            if (! isset($grouped[$license])) {
                $grouped[$license] = [];
            }
            $grouped[$license][] = $dependency;
        }

        return $grouped;
    }

    /**
     * @param  Dependency[]  $dependencies
     * @param  string[]  $filters
     * @return array
     */
    private function filterLicenses(array $dependencies, array $filters): array
    {
        if ($filters === []) {
            return $dependencies;
        }

        $validLicences = [];

        foreach ($dependencies as $dependency) {
            foreach ($dependency->getLicenses() as $license) {
                if (in_array(strtolower($license), array_map('strtolower', $filters))) {
                    $validLicences[] = $dependency;
                    continue 2;
                }
            }
        }

        return $validLicences;
    }

    private function lookUpLicenses(array $licenses, OutputInterface $output, $useCache = true): array
    {
        if (! $useCache) {
            $this->licenseLookup->setCache(new NullAdapter);
        }

        $lookedUp = [];
        foreach ($licenses as $license) {
            $output->writeln("Looking up $license ...");
            $lookedUp[$license] = $this->licenseLookup->lookUp($license);
        }

        return $lookedUp;
    }

    /**
     * @param  OutputInterface  $output
     * @param  InputInterface  $input
     * @param  License[]  $licenses
     * @param  array  $groupedByName
     */
    protected function outputFormattedLicenses(OutputInterface $output, InputInterface $input, array $licenses, array $groupedByName): void
    {
        foreach ($licenses as $license) {
            $dependencies = $groupedByName[$license->getShortName()];

            $usageCount = count($dependencies);
            $headline = sprintf(PHP_EOL.'Count %d - %s (%s)', $usageCount, $license->getShortName(),
                $license->getSource());
            $output->writeln($headline);
            $licenseTable = new Table($output);
            $licenseTable->setHeaders(['CAN', 'CAN NOT', 'MUST']);

            $can = $license->getCan();
            $cannot = $license->getCannot();
            $must = $license->getMust();
            $columnWidth = max(count($can), count($cannot), count($must));

            $can = array_pad($can, $columnWidth, null);
            $cannot = array_pad($cannot, $columnWidth, null);
            $must = array_pad($must, $columnWidth, null);

            $inlineHeading = function ($key) {
                return is_string($key) ? $key : '';
            };

            $can = array_map_keys($can, $inlineHeading);
            $cannot = array_map_keys($cannot, $inlineHeading);
            $must = array_map_keys($must, $inlineHeading);

            for ($i = 0; $i < $columnWidth; $i++) {
                $licenseTable->addRow([
                    'CAN' => $can[$i],
                    'CANNOT' => $cannot[$i],
                    'MUST' => $must[$i],
                ]);
            }
            $licenseTable->render();

            if ($input->getOption('show-packages') || $output->isVerbose()) {
                $output->writeln('');
                $output->writeln($this->outputFormatPackages($input, $dependencies));
            }
        }
    }

    /**
     * Generates a output string for the 'show-packages' option.
     *
     * @param  InputInterface  $input
     * @param  array  $dependencies
     * @return string
     */
    protected function outputFormatPackages(InputInterface $input, array $dependencies): string
    {
        $packages = [];
        if ($input->getOption('grouped')) {
            foreach ($dependencies as $dependency) {
                $packages[] = $dependency->getName();
            }

            return 'packages: '.implode(', ', $packages);
        }

        foreach ($dependencies as $dependency) {
            $packages[] = sprintf('%s (%s)', $dependency->getName(), $dependency->getVersion());
        }

        return implode(PHP_EOL, $packages);
    }
}
