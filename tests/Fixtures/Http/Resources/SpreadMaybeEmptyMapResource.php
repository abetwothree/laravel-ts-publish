<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a map that may be `[]` beside a map, nested in a value: after it, before it, before
 * two maps and after a key; and alone, and before a resource's resolved keys.
 *
 * @mixin Post
 */
final class SpreadMaybeEmptyMapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, string> $labels */
        $labels = $request->input('labels', []);
        /** @var array<string, int> $counts */
        $counts = $request->input('counts', []);
        /** @var array<string, int>|null $maybe */
        $maybe = $request->input('maybe');
        /** @var array<string, bool> $flags */
        $flags = $request->input('flags', []);
        $flagged = $request->boolean('flagged');

        return [
            'coalesced' => [...$labels, ...($maybe ?? [])],
            'or_empty' => [...$labels, ...($flagged ? $counts : [])],
            'empty_first' => [...($flagged ? $counts : []), ...$labels],
            'before_two' => [...($flagged ? $flags : []), ...$labels, ...$counts],
            'keyed' => ['by' => $this->title, ...$labels, ...($flagged ? $counts : [])],
            'alone' => [...($maybe ?? [])],
            'before_arm' => [...($maybe ?? []), ...UserResource::make($this->author)->resolve($request)],
        ];
    }
}
