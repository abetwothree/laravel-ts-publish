<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A local written at the top level and again in a block, under an inline `@var` there over a value no reader names,
 * read past that block as a receiver and as a value.
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

        return ['t' => $n?->title, 'post' => $n];
    }
}
