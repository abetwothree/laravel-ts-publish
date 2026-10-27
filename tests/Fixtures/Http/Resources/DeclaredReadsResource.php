<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/**
 * The class DeclaredReadsBaseResource's helpers run on: it redeclares KEY, part() and a by-value fill(), none of which
 * their `self` reaches.
 */
class DeclaredReadsResource extends DeclaredReadsBaseResource
{
    public const KEY = 'child_key';

    public const LEVEL = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->reads($request), ...$this->stashed(), ...$this->traitStashed()];
    }

    /** @return array<string, mixed> */
    public function part(): array
    {
        return ['child_part' => true];
    }

    /**
     * Takes a copy of the array it is passed.
     *
     * @param  array<string, mixed>  $out
     */
    private static function fill(array $out): void {}
}
