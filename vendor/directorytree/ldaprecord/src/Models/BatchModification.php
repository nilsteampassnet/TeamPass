<?php

namespace LdapRecord\Models;

use InvalidArgumentException;

class BatchModification
{
    use DetectsResetIntegers;

    /**
     * The array keys to be used in batch modifications.
     */
    public const KEY_ATTRIB = 'attrib';

    public const KEY_MODTYPE = 'modtype';

    public const KEY_VALUES = 'values';

    /**
     * The attribute of the modification.
     */
    protected ?string $attribute = null;

    /**
     * The original value of the attribute before modification.
     */
    protected array $original = [];

    /**
     * The values of the modification.
     */
    protected array $values = [];

    /**
     * The modtype integer of the batch modification.
     */
    protected ?int $type = null;

    /**
     * Constructor.
     */
    public function __construct(string $attribute = null, int $type = null, array $values = [])
    {
        $this->setAttribute($attribute)
            ->setType($type)
            ->setValues($values);
    }

    /**
     * Set the original value of the attribute before modification.
     */
    public function setOriginal(array|string $original = []): static
    {
        $this->original = $this->normalizeAttributeValues($original);

        return $this;
    }

    /**
     * Returns the original value of the attribute before modification.
     */
    public function getOriginal(): array
    {
        return $this->original;
    }

    /**
     * Set the attribute of the modification.
     */
    public function setAttribute(string $attribute = null): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    /**
     * Returns the attribute of the modification.
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * Set the values of the modification.
     */
    public function setValues(array $values = []): static
    {
        // Null and empty values must also not be added to a batch
        // modification. Passing null or empty values will result
        // in an exception when trying to save the modification.
        $this->values = array_filter($this->normalizeAttributeValues($values), function ($value) {
            return is_numeric($value) && $this->valueIsResetInteger((int) $value) || ! empty($value);
        });

        return $this;
    }

    /**
     * Normalize all of the attribute values.
     */
    protected function normalizeAttributeValues(array|string $values = []): array
    {
        // We must convert all of the values to strings. Only strings can
        // be used in batch modifications, otherwise we will we will
        // receive an LDAP exception while attempting to save.
        return array_map('strval', (array) $values);
    }

    /**
     * Returns the values of the modification.
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Set the type of the modification.
     */
    public function setType(int $type = null): static
    {
        if (is_null($type)) {
            return $this;
        }

        if (! $this->isValidType($type)) {
            throw new InvalidArgumentException('Given batch modification type is invalid.');
        }

        $this->type = $type;

        return $this;
    }

    /**
     * Returns the type of the modification.
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * Determines if the batch modification is valid in its current state.
     */
    public function isValid(): bool
    {
        return ! is_null($this->get());
    }

    /**
     * Builds the type of modification automatically
     * based on the current and original values.
     */
    public function build(): static
    {
        switch (true) {
            case empty($this->original) && empty($this->values):
                return $this;
            case ! empty($this->original) && empty($this->values):
                return $this->setType(LDAP_MODIFY_BATCH_REMOVE_ALL);
            case empty($this->original) && ! empty($this->values):
                return $this->setType(LDAP_MODIFY_BATCH_ADD);
            default:
                return $this->determineBatchTypeFromOriginal();
        }
    }

    /**
     * Determine the batch modification type from the original values.
     */
    protected function determineBatchTypeFromOriginal(): static
    {
        $added = $this->getAddedValues();
        $removed = $this->getRemovedValues();

        switch (true) {
            case ! empty($added) && ! empty($removed):
                return $this->setType(LDAP_MODIFY_BATCH_REPLACE);
            case ! empty($added):
                return $this->setValues($added)->setType(LDAP_MODIFY_BATCH_ADD);
            case ! empty($removed):
                return $this->setValues($removed)->setType(LDAP_MODIFY_BATCH_REMOVE);
            default:
                return $this;
        }
    }

    /**
     * Get the values that were added to the attribute.
     */
    protected function getAddedValues(): array
    {
        return array_values(
            array_diff($this->values, $this->original)
        );
    }

    /**
     * Get the values that were removed from the attribute.
     */
    protected function getRemovedValues(): array
    {
        return array_values(
            array_diff($this->original, $this->values)
        );
    }

    /**
     * Returns the built batch modification array.
     */
    public function get(): ?array
    {
        switch ($this->type) {
            case LDAP_MODIFY_BATCH_REMOVE_ALL:
                // A values key cannot be provided when
                // a remove all type is selected.
                return [
                    static::KEY_ATTRIB => $this->attribute,
                    static::KEY_MODTYPE => $this->type,
                ];
            case LDAP_MODIFY_BATCH_REMOVE:
                // Fallthrough.
            case LDAP_MODIFY_BATCH_ADD:
                // Fallthrough.
            case LDAP_MODIFY_BATCH_REPLACE:
                return [
                    static::KEY_ATTRIB => $this->attribute,
                    static::KEY_MODTYPE => $this->type,
                    static::KEY_VALUES => $this->values,
                ];
            default:
                // If the modtype isn't recognized, we'll return null.
                return null;
        }
    }

    /**
     * Determines if the given modtype is valid.
     */
    protected function isValidType(int $type): bool
    {
        return in_array($type, [
            LDAP_MODIFY_BATCH_REMOVE_ALL,
            LDAP_MODIFY_BATCH_REMOVE,
            LDAP_MODIFY_BATCH_REPLACE,
            LDAP_MODIFY_BATCH_ADD,
        ]);
    }
}
