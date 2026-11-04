<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** List elements whose `Rule::in()` values hold an apostrophe, which their literal union escapes. */
final class QuotedRuleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tags' => ['array'],
            'tags.*' => [Rule::in(["it's", 'b'])],
            'maybe' => ['array'],
            'maybe.*' => ['nullable', Rule::in(["it's", null])],
            'pipes' => ['array'],
            'pipes.*' => [Rule::in(["it's|x"])],
            'plain' => ['array'],
            'plain.*' => [Rule::in(['a', 'b'])],
        ];
    }
}
