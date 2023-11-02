<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class StripTags implements Filter
{
    /**
     * Strips HTML and PHP tags from the given string
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        return is_string($value) ? strip_tags($value) : $value;
    }
}
