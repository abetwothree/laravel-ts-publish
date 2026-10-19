<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A tag is read whole, whatever its spacing: the wrapped `$resource`'s spaced `@var` still names its model, and a
 * helper's `@return` keeps a spaced `| null`, an array of collections and a union of spaced generics.
 */
final class SpacedDocblockResource extends JsonResource
{
    /** @var null | Post */
    public $resource;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle(),
            'pages' => $this->pages(),
            'threads' => $this->threads(),
        ];
    }

    /**
     * @return string | null
     */
    private function subtitle(): mixed
    {
        return null;
    }

    /**
     * @return Collection<int, Comment>[]
     */
    private function pages(): mixed
    {
        return [];
    }

    /**
     * @return array<int, Comment>|array<int, Post>
     */
    private function threads(): mixed
    {
        return [];
    }
}
