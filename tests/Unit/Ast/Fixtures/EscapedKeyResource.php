<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Interpolated keys whose literal text holds a backslash, each of which TypeScript would read as an escape. */
final class EscapedKeyResource extends JsonResource
{
    /** Spreads the keys below, the way a published resource reaches them. */
    public function toArray(Request $request): array
    {
        return [...$this->keys()];
    }

    /** One key per backslash position: after, before and between placeholders, `\b`, `\\`, trailing, before `${`. */
    public function keys(): array
    {
        $data = [];

        foreach ([['a', 'b'], ['c', 'd']] as [$name, $kind]) {
            $data["{$name}\\unit"] = 'x';
            $data["unit\\{$name}"] = 'x';
            $data["{$name}\\{$kind}"] = 'x';
            $data["b\\b{$name}"] = 'x';
            $data["{$name}\\\\pair"] = 'x';
            $data["{$name}_end\\"] = 'x';
            $data["{$name}\\\${x}"] = 'x';
            $data[$name.'\\cat'] = 'x';
        }

        return $data;
    }
}
