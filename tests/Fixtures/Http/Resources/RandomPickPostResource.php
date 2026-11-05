<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\RandomLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Picks its post's comments at random, directly and through a resource built around the post, and calls a method
 * whose return its argument decides through a resource built around labels.
 *
 * @extends JsonResource<Post>
 */
class RandomPickPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $labels = new RandomLabels(['a', 'b']);
        $arguments = [null];

        return [
            'comment' => $this->resource->comments->random(),
            'comments' => $this->resource->comments->random(2),
            'base_comment' => $this->resource->comments->toBase()->random(),
            'built_comment' => PageItemResource::make($this->resource)->comments->random(),
            'unpacked' => $this->resource->comments->random(...$arguments),
            'built_unpacked' => PageItemResource::make($this->resource)->comments->random(...$arguments),
            'label' => RandomLabelsResource::make($labels)->pick(),
            'labels' => RandomLabelsResource::make($labels)->labels,
        ];
    }
}
