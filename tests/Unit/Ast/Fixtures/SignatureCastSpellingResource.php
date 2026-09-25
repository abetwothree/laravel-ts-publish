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
    '[key: `${string}\\\\_y`]' => 'Money',
    '[key: `${string}\\_y`]' => ['type' => 'Money', 'import' => '@/types/money'],
    '[key: `${string}\\\\_z`]' => 'number',
    '[key: `${string}\\_z`]' => ['type' => 'boolean', 'optional' => true],
])]
final class SignatureCastSpellingResource extends JsonResource
{
    /** Spreads every signature below. */
    public function toArray(Request $request): array
    {
        return [...$this->keys(), ...$this->methodCast()];
    }

    /** Keys the class-level casts retype; `_both`, `_y` and `_z` are cast under exact and single-backslash names. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_cast"] = 'x';
            $data["{$name}\\unit"] = 'x';
            $data["{$name}\\_both"] = 'x';
            $data["{$name}\\_y"] = 'x';
            $data["{$name}\\_z"] = 'x';
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
