<?php

namespace Elegant\Sanitizer\Contracts;

interface DataAwareFilter
{
    /**
     * Set the data under filtering.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data);
}
