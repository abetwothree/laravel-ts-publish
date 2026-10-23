<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A whenLoaded() parameter rewritten in a block that returns, read inside that block by a closure: one that captures it
 * and then writes its own copy, one that writes it as a local of its own, and a transform() callback it is passed to.
 * A closure that appends to a captured value still holds a value of its kind.
 *
 * @mixin Post
 */
final class BlockScopedParameterGapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'captured' => $this->whenLoaded('comments', function ($comments) {
                if ($this->is_pinned) {
                    $comments = $this->author;

                    return $this->when(true, function () use ($comments) {
                        $name = $comments->name;
                        $comments = null;

                        return $name;
                    });
                }

                return 1;
            }),
            'captured_written' => $this->whenLoaded('comments', function ($comments) {
                if ($this->is_pinned) {
                    $comments = $this->author;

                    return $this->when(true, function () use ($comments) {
                        $comments = 7;

                        return $comments;
                    });
                }

                return 1;
            }),
            'shadowed' => $this->whenLoaded('comments', function ($comments) {
                if ($this->is_pinned) {
                    $comments = $this->author;

                    return $this->when(true, function () {
                        $comments = 7;

                        return $comments;
                    });
                }

                return 1;
            }),
            'captured_appended' => $this->whenHas('title', function ($title) {
                return $this->when(true, function () use ($title) {
                    $title .= '!';

                    return $title;
                });
            }),
            'passed' => $this->whenLoaded('comments', function ($comments) {
                if ($this->is_pinned) {
                    $comments = $this->author;

                    return $this->transform($comments, fn ($author) => $author->name);
                }

                return 1;
            }),
        ];
    }
}
