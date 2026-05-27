<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use Dominikb\ComposerLicenseChecker\Contracts\DependencyLoader as DependencyLoaderContract;
use Dominikb\ComposerLicenseChecker\Contracts\DependencyParser;
use Dominikb\ComposerLicenseChecker\Exceptions\CommandExecutionException;
use Symfony\Component\Console\Command\Command;

class DependencyLoader implements DependencyLoaderContract
{
    /**
     * @var DependencyParser
     */
    private $dependencyParser;

    public function __construct(DependencyParser $dependencyParser)
    {
        $this->dependencyParser = $dependencyParser;
    }

    /**
     * @throws CommandExecutionException
     */
    public function loadDependencies(string $composer, string $project, bool $withoutDev): array
    {
        $commandOutput = $this->runComposerLicenseCommand($composer, $project, $withoutDev);

        return $this->dependencyParser->parse(join(PHP_EOL, $commandOutput));
    }

    /**
     * @throws CommandExecutionException
     */
    private function runComposerLicenseCommand(string $composer, string $project, bool $withoutDev): array
    {
        $command = sprintf('%s licenses%s --format json --working-dir %s', escapeshellarg($composer), $withoutDev ? ' --no-dev' : '', escapeshellarg($project));

        return $this->exec($command);
    }

    /**
     * @throws CommandExecutionException
     */
    protected function exec(string $command): array
    {
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new CommandExecutionException('Error when trying to fetch licenses from Composer', Command::INVALID);
        }

        return $output;
    }
}
