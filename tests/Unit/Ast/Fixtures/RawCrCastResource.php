<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** CR LF signatures cast by double-quoted PHP strings, which hold a raw CR where the published name writes `\r`. */
#[TsCasts(["[key: `\${string}\r\n`]" => 'number'])]
final class RawCrCastResource extends JsonResource
{
    /** Spreads the signatures below. */
    public function toArray(Request $request): array
    {
        return [...$this->keys(), ...$this->methodCast()];
    }

    /** A key the class-level cast retypes. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\r\n"] = 'x';
        }

        return $data;
    }

    /** A key its own cast retypes. */
    #[TsCasts(["[key: `\${string}\r\n_m`]" => 'number'])]
    public function methodCast(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\r\n_m"] = 'x';
        }

        return $data;
    }
}
