<?php

namespace Hackzilla\PasswordGenerator\Model\Option;

abstract class Option implements OptionInterface
{
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_INTEGER = 'integer';
    const TYPE_STRING = 'string';

    private $value = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $settings = array())
    {
        if (isset($settings['default'])) {
            $this->value = $settings['default'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public static function createFromType($type, array $settings = array())
    {
        switch ($type) {
            case self::TYPE_STRING:
                return new StringOption($settings);

            case self::TYPE_INTEGER:
                return new IntegerOption($settings);

            case self::TYPE_BOOLEAN:
                return new BooleanOption($settings);
        }

        return;
    }
}
