<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class Digit implements Filter
{
    /**
     * Removes all characters except digits from the given string.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        return is_string($value) ? preg_replace('/[^0-9]/si', '', $value) : $value;
    }
}
