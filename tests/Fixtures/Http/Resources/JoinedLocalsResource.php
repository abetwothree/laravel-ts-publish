<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Post;

/**
 * Locals written more than once, each read past its writes: in sequence and in a branch, in a loop that breaks, past
 * an unset() or a closure that writes it by reference, across a try, its catch and a finally, and in its own rewrite;
 * and ones a reference or a variable variable writes.
 *
 * @mixin Post
 */
final class JoinedLocalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = 'draft';

        if ($this->is_pinned) {
            $status = 1;
        }

        $first = null;

        foreach ($this->comments as $comment) {
            if ($comment->content !== '') {
                $first = $comment->content;

                break;
            }
        }

        $removed = 'kept';

        if ($request->has('remove')) {
            unset($removed);
        }

        $captured = 'kept';
        $capture = function () use (&$captured): void {
            $captured = 1;
        };

        try {
            $caught = $this->titleOrThrow();
        } catch (RuntimeException) {
            $caught = null;
        }

        try {
            $finally = $this->titleOrThrow();
        } catch (RuntimeException) {
            $finally = null;
        } finally {
            $finally = 1;
        }

        $aliased = 'a';
        $alias = &$aliased;
        $aliased = 'b';
        $alias = 1;

        $closed = 'a';
        $set = function () use (&$closed): void {
            $closed = 1;
        };
        $closed = 'b';
        $set();

        $key = 'a';
        $$key = 5;

        $self = 'a';
        $self = $self.$this->title;
        $wrapped = $this->title;
        $wrapped = ['v' => $wrapped];

        return [
            'status' => $status,
            'first' => $first,
            'removed' => $removed,
            'captured' => $captured,
            'caught' => $caught,
            'finally' => $finally,
            'caught_read' => $this->caughtRead(),
            'self' => $self,
            'wrapped' => $wrapped,
            'aliased' => $aliased,
            'closed' => $closed,
            'key' => $key,
        ];
    }

    /**
     * The title, which a pinned post has none of yet.
     */
    private function titleOrThrow(): string
    {
        return $this->is_pinned ? throw new RuntimeException('pinned') : $this->title;
    }

    /**
     * The title read in the catch, before the catch writes the local again; a number when nothing throws.
     */
    private function caughtRead()
    {
        $value = 'none';

        try {
            $value = $this->title;
            $this->titleOrThrow();
        } catch (RuntimeException) {
            $seen = $value;
            $value = null;

            return $seen;
        }

        return 7;
    }
}
