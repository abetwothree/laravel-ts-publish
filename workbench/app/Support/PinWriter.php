<?php

declare(strict_types=1);

namespace Workbench\App\Support;

use Workbench\App\Models\Post;

/** Writes a post handed to it, for a fixture whose discriminant another class's static method changes. */
final class PinWriter
{
    /** Clears the post's pin. */
    public static function unpin(Post $post): bool
    {
        $post->is_pinned = false;

        return true;
    }
}
