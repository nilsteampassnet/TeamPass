<?php

declare(strict_types=1);

if (! function_exists('array_map_keys')) {
    /**
     * @param  array  $input
     * @param  callable  $callback
     * @return array
     */
    function array_map_keys($input, $callback)
    {
        $output = [];

        foreach ($input as $key => $value) {
            $output[] = $callback($key, $value);
        }

        return $output;
    }
}
