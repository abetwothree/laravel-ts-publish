<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A relation runs a method it does not declare on its Eloquent builder, and the builder runs an aggregate its
 * `$passthru` lists on the base query, so these calls answer from the query's declarations.
 *
 * @mixin Post
 */
class ForwardedRelationCallResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'count' => $this->comments()->count(),
            'exists' => $this->comments()->exists(),
            'doesnt_exist' => $this->comments()->doesntExist(),
            'first_content' => $this->comments()->first()?->content,
            'first_where_content' => $this->comments()->firstWhere('content', 'beta')?->content,
            'where_count' => $this->comments()->where('id', '>', 0)->count(),
            'max_id' => $this->comments()->max('id'),
        ];
    }
}
