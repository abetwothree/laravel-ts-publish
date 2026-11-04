<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose locals array_walk() rewrites through a callback that collects the key in a variadic
 * parameter, each element replaced by that list: a list's and a string-keyed array's.
 *
 * @mixin Post
 */
class VariadicWalkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $listed = [1, 2];
        array_walk($listed, function (&$value, ...$rest): void {
            $value = $rest;
        });

        $keyed = ['a' => 'x'];
        array_walk($keyed, function (&$value, ...$rest): void {
            $value = $rest;
        });

        return [
            'listed' => $listed,
            'keyed' => $keyed,
        ];
    }
}
