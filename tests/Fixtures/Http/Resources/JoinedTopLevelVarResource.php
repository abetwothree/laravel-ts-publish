<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Locals an inline `@var` types at the top level over a value no rule types, which a later write in an `if` block
 * replaces: with a value no rule types, with a string, and under a `@var` of its own.
 *
 * @mixin Post
 */
final class JoinedTopLevelVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Comment $top */
        $top = $this->opaque($this->comments->first());

        if ($this->is_pinned) {
            $top = $this->opaque($this->comments->last());
        }

        /** @var int $topNumber */
        $topNumber = $this->opaque(1);

        if ($this->is_pinned) {
            $topNumber = 'x';
        }

        /** @var Comment $topTyped */
        $topTyped = $this->opaque($this->comments->first());

        if ($this->is_pinned) {
            /** @var Comment $topTyped */
            $topTyped = $this->opaque($this->comments->last());
        }

        return [
            'top_var_nested_write' => $top->content,
            'top_var_nested_number' => $topNumber,
            'top_var_nested_typed' => $topTyped->content,
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
