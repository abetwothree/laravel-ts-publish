<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource spreading one of two shapes whose `k` holds an empty list beside a list, over a literal `a` the
 * spread always writes again (GH-166).
 */
class SpreadNestedEmptyListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $c = $request->boolean('c');
        $d = $request->boolean('d');
        $e = $request->boolean('e');
        $x = $c
            ? ['a' => $this->when($d, 'x'), 'k' => array_filter($e ? ['z' => null] : [1, 2])]
            : ['a' => $this->when($d, 'x'), 'k' => array_filter($e ? ['z' => null] : [1, 2])];

        return ['a' => 1, ...$x];
    }
}
