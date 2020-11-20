<?php

namespace LdapRecord\Models\Attributes;

use LdapRecord\Utilities;

class DistinguishedName
{
    /**
     * The underlying raw value.
     *
     * @var string|null
     */
    protected $value;

    /**
     * Constructor.
     *
     * @param string|null $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Get the underlying value.
     *
     * @return string|null
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * Set the underlying value.
     *
     * @param string|null $value
     *
     * @return $this
     */
    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get the Distinguished Name values without attributes.
     *
     * @return array
     */
    public function values()
    {
        return Utilities::explodeDn($this->value, $withoutAttributes = true) ?: [];
    }

    /**
     * Get the Distinguished Name components with attributes.
     *
     * @return array
     */
    public function components()
    {
        return Utilities::explodeDn($this->value, $withoutAttributes = false) ?: [];
    }

    /**
     * Convert the DN into an associative array.
     *
     * @return array
     */
    public function assoc()
    {
        $map = [];

        foreach ($this->components() as $rdn) {
            [$attribute, $value] = explode('=', $rdn);

            array_key_exists($attribute, $map)
                ? $map[$attribute][] = $value
                : $map[$attribute] = [$value];
        }

        return $map;
    }

    /**
     * Get the name value.
     *
     * @return string|null
     */
    public function name()
    {
        $values = $this->values();

        return reset($values) ?: null;
    }

    /**
     * Get the relative Distinguished name.
     *
     * @return string|null
     */
    public function relative()
    {
        $components = $this->components();

        return reset($components) ?: null;
    }

    /**
     * Get the parent Distinguished name.
     *
     * @return string|null
     */
    public function parent()
    {
        $components = $this->components();

        array_shift($components);

        return implode(',', $components) ?: null;
    }

    /**
     * Determine if the current Distinguished Name is a parent of the given child.
     *
     * @param DistinguishedName $child
     *
     * @return bool
     */
    public function isParentOf(self $child)
    {
        return $child->isChildOf($this);
    }

    /**
     * Determine if the current Distinguished Name is a child of the given parent.
     *
     * @param DistinguishedName $parent
     *
     * @return bool
     */
    public function isChildOf(self $parent)
    {
        if (
            empty($components = $this->components()) ||
            empty($parentComponents = $parent->components())
        ) {
            return false;
        }

        array_shift($components);

        return $this->compare($components, $parentComponents);
    }

    /**
     * Determine if the current Distinguished Name is an ancestor of the descendant.
     *
     * @param DistinguishedName $descendant
     *
     * @return bool
     */
    public function isAncestorOf(self $descendant)
    {
        return $descendant->isDescendantOf($this);
    }

    /**
     * Determine if the current Distinguished Name is a descendant of the ancestor.
     *
     * @param DistinguishedName $ancestor
     *
     * @return bool
     */
    public function isDescendantOf(self $ancestor)
    {
        if (
            empty($components = $this->components()) ||
            empty($ancestorComponents = $ancestor->components())
        ) {
            return false;
        }

        if (! $length = count($components) - count($ancestorComponents)) {
            return false;
        }

        array_splice($components, $offset = 0, $length);

        return $this->compare($components, $ancestorComponents);
    }

    /**
     * Compare whether the two Distinguished Name values are equal.
     *
     * @param array $values
     * @param array $other
     *
     * @return bool
     */
    protected function compare(array $values, array $other)
    {
        return $this->recase($values) == $this->recase($other);
    }

    /**
     * Recase the array values.
     *
     * @param array $values
     *
     * @return array
     */
    protected function recase(array $values)
    {
        return array_map([$this, 'normalize'], $values);
    }

    /**
     * Normalize the string value.
     *
     * @param string $value
     *
     * @return string
     */
    protected function normalize($value)
    {
        return strtolower($value);
    }
}
