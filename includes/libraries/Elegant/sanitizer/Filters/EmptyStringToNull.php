<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class EmptyStringToNull implements Filter
{
    /**
     * If the given string is empty set it to null.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        return (is_string($value) && '' === $value) ? null : $value;
    }
}
