<?php

namespace Hackzilla\PasswordGenerator\Model\Option;

interface OptionInterface
{
    /**
     * Option settings
     * Things like default, min, and max
     *
     * @param array $settings
     */
    public function __construct(array $settings);

    /**
     * Get option type
     *
     * @return string
     */
    public function getType();

    /**
     * Get option value
     *
     * @return mixed
     */
    public function getValue();

    /**
     * Set option string value.
     *
     * @param string $value
     *
     * @throws \InvalidArgumentException
     */
    public function setValue($value);
}
