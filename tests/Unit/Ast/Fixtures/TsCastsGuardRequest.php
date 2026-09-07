<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Foundation\Http\FormRequest;

/** Overrides aimed at the three keys `validated()` declines: a prohibited one, a dotted one, a wildcard. */
#[TsCasts([
    'secret' => 'string',
    'options.default' => 'number',
    'tags.*' => 'number',
])]
class TsCastsGuardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'secret' => ['prohibited'],
            'options' => ['array'],
            'options.default' => ['string'],
            'tags' => ['array'],
            'tags.*' => ['string'],
        ];
    }
}
