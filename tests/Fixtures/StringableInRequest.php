<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A `Rule::in()` over Stringable values, which validation compares by their strings. */
class StringableInRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dock' => ['required', Rule::in([new DockCode('7'), str('abc')])],
        ];
    }
}
