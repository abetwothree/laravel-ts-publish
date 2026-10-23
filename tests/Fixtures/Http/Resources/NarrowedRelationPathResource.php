<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * An `instanceof` ternary on a `$this->prop` relation collection, whose proven arm reads an element through it.
 *
 * @mixin Post
 */
final class NarrowedRelationPathResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'image_id' => $this->images instanceof EloquentCollection ? $this->images->first()?->id : null,
            'comment_id' => $this->comments instanceof EloquentCollection ? $this->comments->first()?->id : null,
            'author_post_title' => $this->author?->posts instanceof EloquentCollection ? $this->author->posts->first()?->title : null,
        ];
    }
}
