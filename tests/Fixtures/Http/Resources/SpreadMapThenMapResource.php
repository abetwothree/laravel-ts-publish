<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a string map then a number map: at the top level, nested in a value, after a key and
 * before a resource's resolved keys.
 *
 * @mixin Post
 */
final class SpreadMapThenMapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, string> $labels */
        $labels = $request->input('labels', []);
        /** @var array<string, int> $counts */
        $counts = $request->input('counts', []);
        /** @var array<string, bool> $flags */
        $flags = $request->input('flags', []);

        return [
            'by' => $this->title,
            ...$labels,
            ...$counts,
            'row' => [...$labels, ...$counts],
            'deep' => ['in' => [...$labels, ...$counts]],
            'keyed' => ['by' => $this->title, ...$labels, ...$counts],
            'armed' => [...$labels, ...$counts, ...UserResource::make($this->author)->resolve($request)],
            'three' => [...$labels, ...$counts, ...$flags],
        ];
    }
}
