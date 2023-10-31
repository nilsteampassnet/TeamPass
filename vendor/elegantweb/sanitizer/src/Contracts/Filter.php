<?php

namespace Elegant\Sanitizer\Contracts;

interface Filter
{
    /**
     * Return the result of applying this filter to the given input.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = []);
}
