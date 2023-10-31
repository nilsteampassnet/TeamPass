<?php

namespace Elegant\Sanitizer\Filters;

class Enum
{
    /**
     * The type of the enum.
     *
     * @var string|null
     */
    protected $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function __toString()
    {
        return "enum:" . $this->type;
    }
}
