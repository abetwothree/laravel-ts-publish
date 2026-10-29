<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Post;

/**
 * Locals assigned inside an `if`, `try` or `foreach` block, each read after its assignment in that block: one passed to
 * a transform() callback, one a closure in its block shadows with its own write, a filled `new` model, and one read
 * past its block.
 *
 * @mixin Post
 */
final class BlockScopedLocalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        if ($this->is_pinned) {
            $author = $this->author;
            $data += ['if_name' => $author?->name];
        }

        try {
            $title = $this->title;
            $data['try_title'] = $title;
            $data['shadowed'] = $this->when($request->has('shadow'), function () {
                $title = 7;

                return $title;
            });
        } catch (RuntimeException) {
        }

        foreach ($this->comments as $comment) {
            $writer = $comment->user;
            $data['loop_writer'] = $writer->name;
            $data['loop_transformed'] = $this->transform($writer, fn ($loaded) => $loaded->name);
        }

        if ($this->is_pinned) {
            $names = $this->tags->pluck('name')->all();
            $data['coalesced'] = $names ?? [];
        }

        if ($request->has('draft')) {
            $draft = new Post;
            $draft->fill(['title' => 'Draft']);
            $data['draft_title'] = $draft->title;
        }

        if ($this->is_pinned) {
            $count = $this->comments->count();
        }

        $data['after_block'] = $count ?? 0;

        return $data;
    }
}
