<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Concerns;

use Workbench\App\Models\Post;

/**
 * Supplies a helper whose typed parameter its body reads, from the trait's own file.
 */
trait SummarizesPosts
{
    /**
     * The post's headline fields.
     *
     * @return array<string, mixed>
     */
    private function headline(Post $post): array
    {
        return ['headline_id' => $post->id, 'headline' => $post->title];
    }
}
