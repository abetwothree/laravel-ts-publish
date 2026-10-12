<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Helpers whose parameters declare no type, read as values: each body reads what its call passes, a first-class
 * callable's included, which Laravel calls with the loaded relation, the attribute or the transformed value.
 *
 * @mixin Post
 */
final class PassedArgumentHelpersResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'row' => $this->row($this->resource),
            'author_card' => $this->authorCard($this->author),
            'static_row' => self::staticRow($this->resource),
            'outer' => $this->outer($this->resource),
            'loaded_card' => $this->whenLoaded('author', $this->authorCard(...)),
            'loaded_count' => $this->whenLoaded('comments', $this->commentCount(...)),
            'has_title' => $this->whenHas('title', $this->boxed(...)),
            'transformed' => $this->transform($this->title, $this->boxed(...)),
        ];
    }

    /**
     * The post's identity, read off whatever the caller passes.
     *
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    private function row($post): array
    {
        return ['pk' => $post->id, 'title' => $post->title];
    }

    /**
     * A user's name and email.
     *
     * @param  mixed  $author
     * @return array<string, mixed>
     */
    private function authorCard($author): array
    {
        return ['name' => $author->name, 'email' => $author->email];
    }

    /**
     * The post's key from a static helper.
     *
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    private static function staticRow($post): array
    {
        return ['static_pk' => $post->id];
    }

    /**
     * Another helper's row, passed the same value.
     *
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    private function outer($post): array
    {
        return ['inner' => $this->row($post)];
    }

    /**
     * How many comments a loaded collection holds.
     *
     * @param  mixed  $comments
     * @return array<string, mixed>
     */
    private function commentCount($comments): array
    {
        return ['count' => $comments->count()];
    }

    /**
     * Whatever value it is called with, under one key.
     *
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function boxed($value): array
    {
        return ['value' => $value];
    }
}
