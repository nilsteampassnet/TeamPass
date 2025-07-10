<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

class Dependency
{
    /** @var string */
    private $name;

    /** @var string */
    private $version;

    /** @var string[] */
    private $licenses;

    // This is a constant that is used to represent the absence of a license.
    // Matches the value of the 'none' license in the composer.json file.
    const NO_LICENSES = ['none'];

    /**
     * Dependency constructor.
     *
     * @param  string  $name
     * @param  string  $version
     * @param  string[]  $licenses
     */
    public function __construct(string $name = '', string $version = '', array $licenses = [])
    {
        $this->name = $name;
        $this->version = $version;
        $this->licenses = $licenses;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param  string  $version
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function hasAnyLicense(): bool
    {
        return ! empty($this->licenses);
    }

    /**
     * @return string[]
     */
    public function getLicenses(): array
    {
        if ($this->hasAnyLicense()) {
            return $this->licenses;
        } else {
            return self::NO_LICENSES;
        }
    }

    /**
     * @param  string[]  $licenses
     */
    public function setLicenses(array $licenses): self
    {
        $this->licenses = $licenses;

        return $this;
    }

    public function getAuthorName(): string
    {
        return explode('/', $this->name)[0];
    }

    public function getPackageName(): string
    {
        $parts = explode('/', $this->name);

        if (count($parts) != 2) {
            return '';
        }

        return $parts[1];
    }
}
