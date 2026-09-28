<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A map over a relation loaded under a name the model does not declare, so nothing names what the local holds. The
 * map parameter's type names each element, and the trailing values()/all() keep that list.
 *
 * @extends JsonResource<User>
 */
final class UserFeaturedPostsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'featured_posts' => $this->when($request->user() !== null, fn () => $this->whenLoaded(
                'featuredPosts',
                function (): array {
                    /** @var Collection<int, Post> $posts */
                    $posts = $this->resource->getRelation('featuredPosts');

                    return $posts->map(fn (Post $post) => [
                        'id' => $post->id,
                        'title' => $post->title,
                        'file' => $post->attachment?->filename ?: null,
                    ])->values()->all();
                },
            )),
        ];
    }
}
