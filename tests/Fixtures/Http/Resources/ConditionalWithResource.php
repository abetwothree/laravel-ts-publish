<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post's id, with keys its response's `with()` holds only where the request is `shown`, which no `filter()` removes.
 *
 * @mixin Post
 */
class ConditionalWithResource extends JsonResource
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
     * The keys beside the body, each of the when() family leaving its value out for the `[]` Inertia then sends.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $shown = $request->boolean('shown');

        return [
            'flag' => $this->when($shown, 1),
            'visibility' => $this->when($shown, EnumResource::make($this->visibility)),
            'hidden' => $this->unless($shown, 'hidden'),
            'fallback' => $this->when($shown, 1, 'none'),
            'author' => $this->whenLoaded('author'),
            'category' => $this->whenNotNull($this->category_id),
            'nested' => ['inner' => $this->when($shown, fn () => ['n' => 2])],
        ];
    }
}
