<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use Dominikb\ComposerLicenseChecker\Contracts\LicenseConstraintHandler;
use Dominikb\ComposerLicenseChecker\Exceptions\LogicException;

class ConstraintViolationDetector implements LicenseConstraintHandler
{
    /** @var string[] */
    protected $blocklist = [];

    /** @var string[] */
    protected $allowlist = [];

    /** @var Dependency[] */
    private $alwaysAllowed = [];

    public function setBlocklist(array $licenses): void
    {
        $this->blocklist = $licenses;
    }

    public function setAllowlist(array $licenses): void
    {
        $this->allowlist = $licenses;
    }

    public function allow($dependencies): void
    {
        if (! is_array($dependencies)) {
            $dependencies = [$dependencies];
        }

        foreach ($dependencies as $allowed) {
            $this->alwaysAllowed[] = $allowed;
        }
    }

    /**
     * @param  Dependency[]  $dependencies
     * @return ConstraintViolation[]
     *
     * @throws LogicException
     */
    public function detectViolations(array $dependencies): array
    {
        $this->ensureConfigurationIsValid();

        $possibleViolators = $this->exceptAllowed($dependencies);

        return [
            $this->detectBlocklistViolation($possibleViolators),
            $this->detectAllowlistViolation($possibleViolators),
        ];
    }

    /**
     * @throws LogicException
     */
    public function ensureConfigurationIsValid(): void
    {
        $overlap = array_intersect($this->blocklist, $this->allowlist);

        if (count($overlap) > 0) {
            $invalidLicenseConditionals = sprintf('"%s"', implode('", "', $overlap));
            throw new LogicException("Licenses must not be on the block- and allowlist at the same time: {$invalidLicenseConditionals}");
        }
    }

    /**
     * @param  Dependency[]  $dependencies
     */
    private function detectBlocklistViolation(array $dependencies): ConstraintViolation
    {
        $violation = new ConstraintViolation('Blocked license found!');

        if (! empty($this->blocklist)) {
            foreach ($dependencies as $dependency) {
                if ($this->allLicensesOnList($dependency->getLicenses(), $this->blocklist)) {
                    $violation->add($dependency);
                }
            }
        }

        return $violation;
    }

    /**
     * @param  Dependency[]  $dependencies
     */
    private function detectAllowlistViolation(array $dependencies): ConstraintViolation
    {
        $violation = new ConstraintViolation('Unallowed license found!');

        if (! empty($this->allowlist)) {
            foreach ($dependencies as $dependency) {
                if (! $this->anyLicenseOnList($dependency->getLicenses(), $this->allowlist)) {
                    $violation->add($dependency);
                }
            }
        }

        return $violation;
    }

    private function allLicensesOnList(array $licenses, array $list): bool
    {
        return count(array_intersect($licenses, $list)) === count($licenses);
    }

    private function anyLicenseOnList(array $licenses, array $list): bool
    {
        return count(array_intersect($licenses, $list)) > 0;
    }

    /**
     * @param  Dependency[]  $dependencies
     * @return Dependency[]
     */
    private function exceptAllowed(array $dependencies): array
    {
        $possiblyViolating = [];

        if (empty($this->alwaysAllowed)) {
            return $dependencies;
        }

        foreach ($dependencies as $dependency) {
            foreach ($this->alwaysAllowed as $allowedDependency) {
                if ($this->matches($allowedDependency, $dependency)) {
                    continue 2; // Outer for: test next dependency
                }
            }

            $possiblyViolating[] = $dependency;
        }

        return $possiblyViolating;
    }

    /**
     * Determine if the $original author and package name match for $tryMatch.
     * An empty string for either the author or package gets interpreted as a wilcard.
     *
     * @param  Dependency  $original
     * @param  Dependency  $tryMatch
     * @return bool
     */
    private function matches(Dependency $original, Dependency $tryMatch): bool
    {
        if ($original->getAuthorName()) {
            if (! ($original->getAuthorName() === $tryMatch->getAuthorName())) {
                return false;
            }
        }

        if ($original->getPackageName()) {
            if (! ($original->getPackageName() === $tryMatch->getPackageName())) {
                return false;
            }
        }

        return true;
    }
}
