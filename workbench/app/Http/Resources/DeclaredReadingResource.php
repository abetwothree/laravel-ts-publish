<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * The engine reads each local's assigned value as a known type, so that reading stands over the inline `@var` and a
 * wider declaration never widens it. It cannot read what `$opaque` holds, so the declaration applies there, and inside
 * whenLoaded() the loaded relation's model, which is a `Model` too, types the member read.
 *
 * @mixin Post
 */
final class DeclaredReadingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var string|null $title */
        $title = $this->resource->title;

        /** @var array<string, int> $counts */
        $counts = ['a' => 1, 'b' => 2];

        /** @var array<string, int|string> $mixed */
        $mixed = ['a' => 1, 'b' => 'x'];

        /** @var array{a: int, b?: string} $shape */
        $shape = ['a' => 1, 'b' => 'x'];

        /** @var int|string $id */
        $id = $this->resource->id;

        /** @var int|null $commentCount */
        $commentCount = $this->resource->comments->count();

        /** @var string|int $either */
        $either = $this->resource->title;

        /** @var scalar $scalar */
        $scalar = $this->resource->title;

        /** @var User|null $author */
        $author = $this->resource->author;

        /** @var Model $opaque */
        $opaque = $this->resource->getRelationValue('author');

        return [
            'title' => $title,
            'counts' => $counts,
            'mixed' => $mixed,
            'shape' => $shape,
            'id' => $id,
            'comment_count' => $commentCount,
            'either' => $either,
            'scalar' => $scalar,
            'author' => $author,
            'opaque_name' => $this->whenLoaded('author', fn () => $opaque->name),
        ];
    }
}
