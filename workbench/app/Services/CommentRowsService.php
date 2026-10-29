<?php

declare(strict_types=1);

namespace Workbench\App\Services;

use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * Helpers declared only `: array` that map a collection reached through a model parameter or a query chain, so the
 * body fallback reads their shape.
 */
final class CommentRowsService
{
    /** The ids of a post's comments, mapped through an untyped parameter. */
    public function forPost(Post $post): array
    {
        return $post->comments->map(fn ($comment) => ['id' => $comment->id])->values()->all();
    }

    /** Every comment's id, mapped over a query's result. */
    public function all(): array
    {
        return Comment::query()->get()->map(fn (Comment $comment) => ['id' => $comment->id])->all();
    }

    /** The unflagged comments' ids, mapped over a local holding a query's result. */
    public function unflagged(): array
    {
        $rows = Comment::query()->where('is_flagged', false)->get();

        return $rows->map(fn ($comment) => ['id' => $comment->id])->all();
    }
}
