<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use Dominikb\ComposerLicenseChecker\Contracts\DependencyLoaderAware;
use Dominikb\ComposerLicenseChecker\Contracts\LicenseConstraintAware;
use Dominikb\ComposerLicenseChecker\Contracts\LicenseLookupAware;
use Dominikb\ComposerLicenseChecker\Exceptions\CommandExecutionException;
use Dominikb\ComposerLicenseChecker\Traits\DependencyLoaderAwareTrait;
use Dominikb\ComposerLicenseChecker\Traits\LicenseConstraintAwareTrait;
use Dominikb\ComposerLicenseChecker\Traits\LicenseLookupAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckCommand extends Command implements LicenseLookupAware, LicenseConstraintAware, DependencyLoaderAware
{
    use LicenseLookupAwareTrait, LicenseConstraintAwareTrait, DependencyLoaderAwareTrait;

    /** @var SymfonyStyle */
    private $io;

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
                'allowlist',
                'a',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Set a license you want to permit for usage'
            ),
            new InputOption(
                'blocklist',
                'b',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Mark a specific license prohibited for usage'
            ),
            new InputOption(
                'allow',
                '',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Determine a vendor or package to always be allowed and never trigger violations'
            ),
        ]));
    }

    public static function getDefaultName(): ?string
    {
        return 'check';
    }

    /**
     * @throws CommandExecutionException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Reading through dependencies and checking their licenses ...');

        $this->ensureCommandCanBeExecuted();

        $dependencies = $this->dependencyLoader->loadDependencies(
            $input->getOption('composer'),
            $input->getOption('project-path')
        );

        $this->io->writeln(count($dependencies).' dependencies were found ...');
        $this->io->newLine();

        $violations = $this->determineViolations($dependencies,
            $input->getOption('blocklist'),
            $input->getOption('allowlist'),
            $input->getOption('allow')
        );

        try {
            $this->handleViolations($violations);
            $this->io->success('Command finished successfully. No violations detected!');
        } catch (CommandExecutionException $exception) {
            $this->io->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @throws CommandExecutionException
     */
    private function ensureCommandCanBeExecuted(): void
    {
        if (! $this->licenseLookup) {
            throw new CommandExecutionException('LicenseLookup must be set via setLicenseLookup() before the command can be executed!');
        }

        if (! $this->dependencyLoader) {
            throw new CommandExecutionException('DependencyLoader must be set via setDependencyLoader() before the command can be executed!');
        }
    }

    private function determineViolations(array $dependencies, array $blocklist, array $allowlist, array $allowed): array
    {
        $this->licenseConstraintHandler->setBlocklist($blocklist);
        $this->licenseConstraintHandler->setAllowlist($allowlist);

        $this->licenseConstraintHandler->allow(array_map(function ($dependency) {
            return new Dependency($dependency);
        }, $allowed));

        return $this->licenseConstraintHandler->detectViolations($dependencies);
    }

    /**
     * @param  ConstraintViolation[]  $violations
     *
     * @throws CommandExecutionException
     */
    private function handleViolations(array $violations): void
    {
        $violationsFound = false;

        foreach ($violations as $violation) {
            if ($violation->hasViolators()) {
                $this->io->error($violation->getTitle());
                $this->reportViolators($violation->getViolators());
                $violationsFound = true;
            }
        }

        if ($violationsFound) {
            throw new CommandExecutionException('Violators found during execution!');
        }
    }

    /**
     * @param  Dependency[]  $violators
     */
    private function reportViolators(array $violators): void
    {
        $byLicense = [];
        foreach ($violators as $violator) {
            $license = $violator->getLicenses()[0];

            if (! isset($byLicense[$license])) {
                $byLicense[$license] = [];
            }
            $byLicense[$license][] = $violator;
        }

        foreach ($byLicense as $license => $violators) {
            $violatorNames = array_map(function (Dependency $dependency) {
                return sprintf('"%s [%s]"', $dependency->getName(), $dependency->getVersion());
            }, $violators);

            $this->io->title($license);
            $this->io->writeln(implode(', ', $violatorNames));
        }
    }
}
