<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post card whose body reads the first post's id past the query's pluck().
 *
 * @mixin Post
 */
class QueriedIdCardResource extends JsonResource
{
    /**
     * The card's keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'firstId' => Post::query()->pluck('id')->first()];
    }
}
