<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Foundation\Http\FormRequest;

/** Overrides that contradict their own rules: optional both ways, and an import for a type with no token. */
#[TsCasts([
    'title' => ['type' => 'string', 'optional' => true],
    'note' => ['type' => 'string', 'optional' => false],
    'meta' => ['type' => 'Record<string, unknown>', 'import' => '@js/types/meta'],
])]
class TsCastsOverrideRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'note' => ['string'],
            'meta' => ['array'],
        ];
    }
}
