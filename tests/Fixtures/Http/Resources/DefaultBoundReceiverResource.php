<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Receivers bound to a callback parameter's default, a whenNotNull() closure Laravel calls with nothing, and a
 * collection handed to a helper or a callable.
 *
 * @mixin Post
 */
class DefaultBoundReceiverResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'default_author' => $this->when(true, fn ($post = new Post) => $post->author?->name),
            'default_key' => $this->when(true, fn ($post = new Post) => $post->getKey()),
            'not_null_author' => $this->whenNotNull(fn ($post = new Post) => $post->author?->name),
            'not_null_status' => $this->whenNotNull(fn ($status = Status::Draft) => $status->name),
            'not_null_rating' => $this->whenNotNull(fn () => $this->rating),
            'callable_first' => $this->whenLoaded('comments', $this->firstContent(...)),
            'helper_first' => $this->firstContent($this->comments),
            'closure_first' => $this->whenLoaded('comments', fn ($comments) => ['first' => $comments->first()?->content]),
        ];
    }

    /**
     * The first comment's content.
     *
     * @return array<string, mixed>
     */
    private function firstContent($comments): array
    {
        return ['first' => $comments->first()?->content];
    }
}
