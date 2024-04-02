<?php

namespace Elegant\Sanitizer\Laravel;

/**
 * @see \Illuminate\Foundation\Http\FormRequest
 * @see \Illuminate\Validation\ValidatesWhenResolvedTrait
 */
trait SanitizesInput
{
    /**
     * Sanitize input before validating.
     *
     * @return void
     */
    public function validateResolved()
    {
        $this->sanitize();
        parent::validateResolved();
    }

    /**
     * Sanitize this request's input.
     *
     * @return void
     */
    public function sanitize()
    {
        $sanitizer = app('sanitizer')->make($this->input(), $this->filters());
        $this->replace($sanitizer->sanitize());
    }

    /**
     * Filters to be applied to the input.
     *
     * @return array
     */
    public function filters()
    {
        return [];
    }
}
