<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toArray() spreads parent::toArray(), JsonResource's whole model, which toAttributes() then
 * sends. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class ParentToArraySpreadApiResource extends JsonApiResource
{
    /**
     * The model's own array, with one key more.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'extra' => 1];
    }
}
