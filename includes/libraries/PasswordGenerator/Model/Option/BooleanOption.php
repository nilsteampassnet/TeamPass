<?php

namespace PasswordGenerator\Model\Option;

use InvalidArgumentException;

//require_once(dirname(__FILE__).'/Option.php');

class BooleanOption extends Option
{
    /**
     * {@inheritdoc}
     */
    public function setValue($value)
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('Boolean required');
        }

        parent::setValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return self::TYPE_BOOLEAN;
    }
}
