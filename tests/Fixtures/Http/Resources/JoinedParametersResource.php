<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use RuntimeException;
use Workbench\App\Models\Post;

/**
 * Closure parameters a write inside a block may replace, read past that block: transform()'s passed the title, a
 * property no rule types or a call no rule types, ones, whenLoaded()'s too, that a value no rule types replaces, and
 * one a `try` writes.
 *
 * @mixin Post
 */
final class JoinedParametersResource extends JsonResource
{
    /** A value no rule types. */
    public mixed $vague = 5;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'joined' => $this->transform($this->title, function ($title) {
                foreach ($this->comments as $comment) {
                    if ($comment->content !== '') {
                        $title = $comment->id;

                        continue;
                    }
                }

                return $title;
            }),
            'vague_joined' => $this->transform($this->vague, function ($value) {
                foreach ($this->comments as $comment) {
                    if ($comment->content !== '') {
                        $value = $comment->id;

                        continue;
                    }
                }

                return $value;
            }),
            'kept_upper' => $this->transform($this->title, function ($title) {
                if ($this->is_pinned) {
                    $title = Str::upper($title);
                }

                return $title;
            }),
            'kept_filtered' => $this->transform($this->options, function ($options) {
                if ($this->is_pinned) {
                    $options = array_filter($options);
                }

                return $options;
            }),
            'kept_author' => $this->whenLoaded('author', function ($author) {
                if ($this->is_pinned) {
                    $author = json_decode('{}');
                }

                return $author;
            }),
            'opaque_joined' => $this->transform($this->opaque(), function ($value) {
                foreach ($this->comments as $comment) {
                    if ($comment->content !== '') {
                        $value = $comment->id;

                        continue;
                    }
                }

                return $value;
            }),
            'caught_joined' => $this->transform($this->title, function ($title) {
                try {
                    $title = $this->risky();
                } catch (RuntimeException) {
                }

                return $title;
            }),
        ];
    }

    /**
     * A value no rule types.
     */
    public function opaque(): mixed
    {
        return json_decode('"s"');
    }

    /**
     * A count, which a pinned post has none of yet.
     */
    private function risky(): int
    {
        return $this->is_pinned ? throw new RuntimeException('pinned') : 3;
    }
}
