<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Casts on backslash signatures, each key pasted from the published name into a single-quoted PHP string. */
#[TsCasts([
    '[key: `${string}\\_cast`]' => 'number',
    '[key: `${string}\\unit`]' => 'boolean',
    '[key: `${string}\\\\_both`]' => 'number',
    '[key: `${string}\\_both`]' => 'boolean',
])]
final class SignatureCastSpellingResource extends JsonResource
{
    /** Spreads every signature below. */
    public function toArray(Request $request): array
    {
        return [...$this->keys(), ...$this->methodCast()];
    }

    /** Keys the class-level casts retype; `_both` is cast under its exact name and its single-backslash one. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_cast"] = 'x';
            $data["{$name}\\unit"] = 'x';
            $data["{$name}\\_both"] = 'x';
        }

        return $data;
    }

    /** A key its own cast retypes, pasted from the published name into a single-quoted PHP string. */
    #[TsCasts(['[key: `${string}\\_mc`]' => 'number'])]
    public function methodCast(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_mc"] = 'x';
        }

        return $data;
    }
}
