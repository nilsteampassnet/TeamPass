<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class EnumFilter implements Filter
{
    /**
     * Casts the given value to its corresponding enum type.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        if (sizeof($options) != 1) {
            throw new \InvalidArgumentException('Enum filter requires enumeration class name.');
        }

        if (!enum_exists($options[0])) {
            throw new \InvalidArgumentException('Enum filter requires valid enum class name.');
        } elseif (!method_exists($options[0], 'tryFrom')) {
            throw new \InvalidArgumentException("Enum filter doesn't support basic enums.");
        }

        return $options[0]::tryFrom($value);
    }
}
