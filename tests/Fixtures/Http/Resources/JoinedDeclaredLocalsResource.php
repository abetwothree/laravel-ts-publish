<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Locals written at the top level and again in a block, under an inline `@var` there over a value no reader names, a
 * model's or a collection's, read past that block as a receiver and as a value; and one whose tag names another local.
 *
 * @mixin User
 */
final class JoinedDeclaredLocalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $n = null;

        if ($this->id > 0) {
            /** @var Post|null $n */
            $n = data_get($this->resource, 'posts.0');
        }

        $posts = null;

        if ($this->id > 0) {
            /** @var Collection<int, Post> $posts */
            $posts = data_get($this->resource, 'posts');
        }

        $o = null;

        if ($this->id > 0) {
            /** @var Post|null $other */
            $o = data_get($this->resource, 'posts.0');
        }

        return [
            't' => $n?->title,
            'post' => $n,
            'posts' => $posts,
            'posts_first_title' => $posts?->first()?->title,
            'other_title' => $o?->title,
        ];
    }
}
