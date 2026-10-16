<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toAttributes() spreads parent::toAttributes(), which returns its `$attributes` names.
 * Laravel 12.45 and later.
 *
 * @mixin Post
 */
class ParentToAttributesSpreadApiResource extends JsonApiResource
{
    public $attributes = ['title'];

    /**
     * The named attributes, with one key more.
     *
     * @return array<int|string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [...parent::toAttributes($request), 'extra' => 1];
    }
}
