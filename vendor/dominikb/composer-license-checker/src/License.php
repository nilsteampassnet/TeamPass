<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

use DateTimeInterface;

class License
{
    /** @var string */
    protected $shortName;
    /** @var string[] */
    protected $can;
    /** @var string[] */
    protected $cannot;
    /** @var string[] */
    protected $must;
    /** @var string */
    protected $source;
    /** @var DateTimeInterface */
    protected $createdAt;

    public function __construct(string $shortName)
    {
        $this->shortName = $shortName;
    }

    /**
     * @return string
     */
    public function getShortName(): string
    {
        return $this->shortName;
    }

    /**
     * @param  string  $shortName
     * @return License
     */
    public function setShortName(string $shortName): self
    {
        $this->shortName = $shortName;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCan(): array
    {
        return $this->can;
    }

    /**
     * @param  string[]  $can
     * @return License
     */
    public function setCan(array $can): self
    {
        $this->can = $can;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getCannot(): array
    {
        return $this->cannot;
    }

    /**
     * @param  string[]  $cannot
     * @return License
     */
    public function setCannot(array $cannot): self
    {
        $this->cannot = $cannot;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getMust(): array
    {
        return $this->must;
    }

    /**
     * @param  string[]  $must
     * @return License
     */
    public function setMust(array $must): self
    {
        $this->must = $must;

        return $this;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param  string  $source
     * @return License
     */
    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return DateTimeInterface
     */
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param  DateTimeInterface  $createdAt
     * @return License
     */
    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
