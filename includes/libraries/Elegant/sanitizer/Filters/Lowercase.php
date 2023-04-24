<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class Lowercase implements Filter
{
    /**
     * Converts the given string to all lowercase.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        return is_string($value) ? mb_strtolower($value, 'UTF-8') : $value;
    }
}
