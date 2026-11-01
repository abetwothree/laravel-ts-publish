<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post's id, with `merge()` and `mergeWhen()` values in its response's `with()`, which no `filter()` merges.
 *
 * @mixin Post
 */
class MergingWithResource extends JsonResource
{
    /**
     * The post's id.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /**
     * The keys beside the body: one key, then MergeValues Laravel sends whole under the next positional keys.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'a' => 1,
            $this->mergeWhen($request->boolean('shown'), ['b' => 2]),
            $this->merge(['m' => 3]),
            'listed' => [$this->merge(['n' => 4])],
        ];
    }
}
