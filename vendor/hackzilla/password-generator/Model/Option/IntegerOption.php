<?php

namespace Hackzilla\PasswordGenerator\Model\Option;

use InvalidArgumentException;

class IntegerOption extends Option
{
    private $minRange;
    private $maxRange;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $settings = array())
    {
        parent::__construct($settings);

        $this->minRange = isset($settings['min']) ? $settings['min'] : ~PHP_INT_MAX;
        $this->maxRange = isset($settings['max']) ? $settings['max'] : PHP_INT_MAX;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if (!is_integer($value)) {
            throw new InvalidArgumentException('Integer required');
        }

        if ($this->minRange > $value || $this->maxRange < $value) {
            throw new InvalidArgumentException('Invalid Value');
        }

        parent::setValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return self::TYPE_INTEGER;
    }
}
