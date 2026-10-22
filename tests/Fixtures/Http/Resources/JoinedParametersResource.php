<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Parameters a write inside a block may replace, read past that block: transform()'s passed the title, a property no
 * rule types or a call no rule types, ones a value no rule types replaces, ones a `try` writes, ones passed on to
 * another transform(), ones their own write replaced first, one an inline `@var` there retypes, and helpers'.
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
            'passed_joined' => $this->transform($this->title, function ($title) {
                if ($this->is_pinned) {
                    $title = $this->id;
                }

                return $this->transform($title, fn ($value) => $value);
            }),
            'passed_joined_id' => $this->transform($this->author, function ($author) {
                if ($this->is_pinned) {
                    $author = $this->comments->first();
                }

                return $this->transform($author, fn ($value) => $value->id);
            }),
            'passed_kept' => $this->transform($this->title, function ($title) {
                if ($this->is_pinned) {
                    $title = json_decode($title);
                }

                return $this->transform($title, fn ($value) => $value);
            }),
            'declared_joined_id' => $this->whenLoaded('author', function ($author) {
                foreach ($this->comments as $comment) {
                    if ($this->is_pinned && $comment->content !== '') {
                        /** @var Comment|null $author */
                        $author = data_get($this->resource, 'comments.0');

                        break;
                    }
                }

                return $author?->id;
            }),
            'declared_joined' => $this->whenLoaded('author', function ($author) {
                foreach ($this->comments as $comment) {
                    if ($this->is_pinned && $comment->content !== '') {
                        /** @var Comment|null $author */
                        $author = data_get($this->resource, 'comments.0');

                        break;
                    }
                }

                return $author;
            }),
            'post_write_nested' => $this->transform($this->title, function ($title) {
                $title = trim($title);

                if ($this->is_pinned) {
                    $title = json_decode($title);
                }

                return $title;
            }),
            'post_write_nested_typed' => $this->transform($this->title, function ($title) {
                $title = trim($title);

                if ($this->is_pinned) {
                    $title = strlen($title);
                }

                return $title;
            }),
            'both_paths' => $this->transform($this->title, function ($title) {
                try {
                    $title = json_decode($title, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $title = [];
                }

                return $title;
            }),
            'expr_value' => $this->transform($this->title, function ($title) {
                $title = $this->opaque();

                return $title;
            }),
            'helper_joined' => $this->labelFor($this->title),
            'helper_copy' => $this->copyBefore($this->title),
            'helper_missing' => $this->titleOrMissing($this->title),
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

    /**
     * The label, or the post's key for a pinned post.
     */
    private function labelFor(string $label)
    {
        if ($this->is_pinned) {
            $label = $this->id;
        }

        return $label;
    }

    /**
     * The label, or for a pinned post an attribute the post lacks, which Laravel leaves out.
     */
    private function titleOrMissing(string $label)
    {
        if ($this->is_pinned) {
            $label = $this->whenHas('missing_column');
        }

        return $label;
    }

    /**
     * The label as it was passed, copied before the body writes it.
     */
    private function copyBefore(string $label)
    {
        $copy = $label;
        $label = 5;

        return $copy;
    }
}
