<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class Digit implements Filter
{
    /**
     * Get only digit characters from the string.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        return preg_replace('/[^0-9]/si', '', $value);
    }
}
