<?php

namespace Hackzilla\PasswordGenerator\Model\Option;

use InvalidArgumentException;

class StringOption extends Option
{
    private $minRange;
    private $maxRange;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $settings = array())
    {
        parent::__construct($settings);

        $this->minRange = isset($settings['min']) ? $settings['min'] : 0;
        $this->maxRange = isset($settings['max']) ? $settings['max'] : 255;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('String required');
        }

        if ($this->minRange > strlen($value) || $this->maxRange < strlen($value)) {
            throw new InvalidArgumentException('Invalid Value');
        }

        parent::setValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return self::TYPE_STRING;
    }
}
