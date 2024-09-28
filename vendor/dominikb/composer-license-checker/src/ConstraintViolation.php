<?php

declare(strict_types=1);

namespace Dominikb\ComposerLicenseChecker;

class ConstraintViolation
{
    /** @var string */
    protected $title;

    /** @var Dependency[] */
    protected $violators = [];

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    public function add(Dependency $violator): void
    {
        $this->violators[] = $violator;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return Dependency[]
     */
    public function getViolators(): array
    {
        return $this->violators;
    }

    public function hasViolators(): bool
    {
        return count($this->violators) > 0;
    }
}
