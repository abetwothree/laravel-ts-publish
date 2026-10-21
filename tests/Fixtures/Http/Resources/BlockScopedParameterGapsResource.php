<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A whenLoaded() parameter rewritten in a block that returns, read inside that block by a closure: one that captures it
 * and then writes its own copy, and a transform() callback it is passed to.
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
