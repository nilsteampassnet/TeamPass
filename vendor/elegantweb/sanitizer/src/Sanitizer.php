<?php

namespace Elegant\Sanitizer;

use Closure;
use InvalidArgumentException;
use UnexpectedValueException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationRuleParser;
use Illuminate\Validation\ClosureValidationRule;
use Elegant\Sanitizer\Contracts\Filter;
use Elegant\Sanitizer\Contracts\DataAwareFilter;

class Sanitizer
{
    /**
     * Data to sanitize.
     *
     * @var array
     */
    protected $data;

    /**
     * Filters to apply.
     *
     * @var array
     */
    protected $filters;

    /**
     * Available filters as [name => classPath].
     *
     * @var array
     */
    protected $availableFilters = [
        'capitalize' => \Elegant\Sanitizer\Filters\Capitalize::class,
        'cast' => \Elegant\Sanitizer\Filters\Cast::class,
        'escape' => \Elegant\Sanitizer\Filters\EscapeHTML::class,
        'format_date' => \Elegant\Sanitizer\Filters\FormatDate::class,
        'lowercase' => \Elegant\Sanitizer\Filters\Lowercase::class,
        'uppercase' => \Elegant\Sanitizer\Filters\Uppercase::class,
        'trim' => \Elegant\Sanitizer\Filters\Trim::class,
        'strip_tags' => \Elegant\Sanitizer\Filters\StripTags::class,
        'digit' => \Elegant\Sanitizer\Filters\Digit::class,
        'empty_string_to_null' => \Elegant\Sanitizer\Filters\EmptyStringToNull::class,
        'enum' => \Elegant\Sanitizer\Filters\EnumFilter::class,
    ];

    /**
     * Create a new sanitizer instance.
     *
     * @param array $data
     * @param array $filters Filters to be applied to each data attribute
     */
    public function __construct(array $data, array $filters)
    {
        $this->data = $data;
        $this->filters = $this->parseFilters($filters);
    }

    /**
     * Register an array of custom filter extensions.
     *
     * @param array $extensions
     * @return void
     */
    public function addExtensions(array $extensions)
    {
        $this->availableFilters = array_merge($this->availableFilters, $extensions);
    }

    /**
     * Parse a filter array.
     *
     * @param array $filters
     * @return array
     */
    protected function parseFilters(array $filters)
    {
        $parsed = [];

        $rawRules = (new ValidationRuleParser($this->data))->explode($filters);

        foreach ($rawRules->rules as $attribute => $attributeRules) {
            foreach (array_filter($attributeRules) as $attributeRule) {
                $parsed[$attribute][] = $this->parseFilter($attributeRule);
            }
        }

        return $parsed;
    }

    /**
     * Parse a filter.
     *
     * @param string|ClosureValidationRule $filter
     * @throws InvalidArgumentException for unsupported filter type
     * @return array|Closure
     */
    protected function parseFilter($filter)
    {
        if (is_string($filter)) {
            return $this->parseFilterString($filter);
        } elseif ($filter instanceof ClosureValidationRule) {
            return $filter->callback;
        } else {
            throw new InvalidArgumentException("Unsupported filter type.");
        }
    }

    /**
     * Parse a filter string formatted as 'filterName:option1,option2' into an array formatted as [name => filterName, options => [option1, option2]].
     *
     * @param string $filter Formatted as 'filterName:option1,option2' or just 'filterName'
     * @throws InvalidArgumentException for empty filter string
     * @return array Formatted as [name => filterName, options => [option1, option2]]
     */
    protected function parseFilterString($filter)
    {
        if ('' == $filter) {
            throw new InvalidArgumentException("Invalid filter string.");
        }

        if (strpos($filter, ':') !== false) {
            [$name, $options] = explode(':', $filter, 2);
            $options = str_getcsv($options);
        } else {
            $name = $filter;
            $options = [];
        }

        return [
            'name' => $name,
            'options' => $options,
        ];
    }

    /**
     * Apply the given filter to the value.
     *
     * @param array|Closure $filter
     * @param mixed $value
     * @return mixed
     */
    protected function applyFilter($filter, $value)
    {
        // Our filters can be a closure, so we first check it
        if ($filter instanceof Closure) {
            return call_user_func($filter, $value);
        }

        $name = $filter['name'];
        $options = $filter['options'];

        // If the filter name is a class
        if (class_exists($name) and in_array(Filter::class, class_implements($name))) {
            return (new $name)->apply($value, $options);
        }

        // If the filter name, is not a class, then it should be inside availableFilters array
        // assigned to the filter function/class
        // If the filter does not exist, throw an Exception:
        if (!isset($this->availableFilters[$name])) {
            throw new InvalidArgumentException("Filter [$name] not found.");
        }

        $filter = $this->availableFilters[$name];

        if ($filter instanceof Closure) {
            return call_user_func_array($filter, [$value, $options]);
        } elseif (in_array(Filter::class, class_implements($filter))) {
            $instance = new $filter;
            if ($instance instanceof DataAwareFilter) $instance->setData($this->data);
            return $instance->apply($value, $options);
        } else {
            throw new UnexpectedValueException("Invalid filter [$name] must be a Closure or a class implementing the Elegant\Sanitizer\Contracts\Filter interface.");
        }
    }

    /**
     * Apply the given filters to the value.
     *
     * @param array $filters
     * @param mixed $value
     * @return mixed
     */
    protected function applyFilters(array $filters, $value)
    {
        foreach ($filters as $filter)
            $value = $this->applyFilter($filter, $value);

        return $value;
    }

    /**
     * Sanitize the given data.
     *
     * @return array
     */
    public function sanitize()
    {
        $sanitized = $this->data;

        foreach ($this->filters as $attr => $filters)
            if (Arr::has($sanitized, $attr))
                Arr::set($sanitized, $attr, $this->applyFilters($filters, Arr::get($sanitized, $attr)));

        return $sanitized;
    }
}
