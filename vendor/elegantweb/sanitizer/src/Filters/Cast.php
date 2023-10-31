<?php

namespace Elegant\Sanitizer\Filters;

use Illuminate\Support\Collection;
use Elegant\Sanitizer\Contracts\Filter;

class Cast implements Filter
{
    /**
     * Casts the given value to into the given type.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        if (is_null($value)) {
            return $value;
        }

        $type = isset($options[0]) ? $options[0] : null;
        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return is_array($value) ? (object) $value : json_decode($value, false);
            case 'array':
                return json_decode($value, true);
            case 'collection':
                $array = is_array($value) ? $value : json_decode($value, true);
                return new Collection($array);
            default:
                throw new \InvalidArgumentException("Wrong Sanitizer casting format: {$type}.");
        }
    }
}
