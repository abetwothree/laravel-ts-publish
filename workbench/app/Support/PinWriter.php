<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** Writes a post handed to it, alone or in a pair, for fixtures whose discriminant another class changes. */
final class PinWriter
{
    /** Clears the post's pin. */
    public static function unpin(Post $post): bool
    {
        $post->is_pinned = false;

        return true;
    }

    /**
     * Clears the pin of the object a pair holds second.
     *
     * @param  array{string, Post|JsonResource}  $pair
     */
    public static function unpinSecond(array $pair): bool
    {
        $pair[1]->is_pinned = false;

        return true;
    }
}
