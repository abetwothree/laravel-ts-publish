<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A local assigned inside a block and returned from it, beside a later return of a literal: in toArray() itself, in a
 * closure, and in a spread helper's loop.
 *
 * @mixin Post
 */
final class BlockScopedReturnsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->boolean('short')) {
            $name = $this->author->name;

            return ['id' => $this->id, 'short_name' => $name];
        }

        return [
            'id' => $this->id,
            'closure_block' => $this->when($request->has('closure'), function () {
                if ($this->is_pinned) {
                    $title = $this->title;

                    return $title;
                }

                return 7;
            }),
            ...$this->firstContent(),
        ];
    }

    /**
     * The first comment's content, or 0 when the post has none.
     *
     * @return array<string, mixed>
     */
    private function firstContent(): array
    {
        foreach ($this->comments as $comment) {
            $first = $comment->content;

            return ['first' => $first];
        }

        return ['first' => 0];
    }
}
