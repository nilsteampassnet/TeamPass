<?php

namespace Elegant\Sanitizer\Filters;

use Carbon\Carbon;
use Elegant\Sanitizer\Contracts\Filter;

class FormatDate implements Filter
{
    /**
     * Format date.
     *
     * @param mixed $value
     * @param array $options
     * @return mixed
     */
    public function apply($value, array $options = [])
    {
        if (!$value) {
            return $value;
        }

        if (sizeof($options) != 2) {
            throw new \InvalidArgumentException('Format Date filter requires both the current date format as well as the target format.');
        }

        $currentFormat = trim($options[0]);
        $targetFormat = trim($options[1]);
        return Carbon::createFromFormat($currentFormat, $value)->format($targetFormat);
    }
}
