<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A spread helper called inside closures whose parameter is named like a local the helper writes itself.
 *
 * @mixin Post
 */
final class SpreadHelperScopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'has_t' => $this->whenHas('title', fn ($t) => [...$this->ownId(), 'k' => 1]),
            'transform_t' => $this->transform($this->title, fn ($t) => [...$this->ownId()]),
            'loaded_t' => $this->whenLoaded('comments', fn ($t) => [...$this->ownId()]),
            'author_named' => $this->whenLoaded('author', fn ($author) => [...$this->named($this->resource)]),
            'author_passed' => $this->whenLoaded('author', fn ($author) => [...$this->named($author)]),
            ...$this->ownId(),
        ];
    }

    /**
     * The post's id through a local named like the callers' closure parameters.
     *
     * @return array<string, mixed>
     */
    private function ownId(): array
    {
        $t = $this->id;

        return ['v' => $t];
    }

    /**
     * A name read off an untyped parameter, which the caller passes the post or its author.
     *
     * @param  mixed  $subject
     * @return array<string, mixed>
     */
    private function named($subject): array
    {
        return ['n' => $subject->name];
    }
}
