<?php

namespace Elegant\Sanitizer\Filters;

use Elegant\Sanitizer\Contracts\Filter;

class EscapeHTML implements Filter
{
    /**
     * Removes HTML tags and encodes special characters of the given string.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        return is_string($value) ? htmlspecialchars(strip_tags($value)) : $value;
    }
}
