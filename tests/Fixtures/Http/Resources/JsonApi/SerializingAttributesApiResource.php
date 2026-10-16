<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose toAttributes() spreads parent::jsonSerialize(), which builds the resource object and so
 * calls toAttributes() again: Laravel never returns from it. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class SerializingAttributesApiResource extends JsonApiResource
{
    /**
     * The attributes, spread over the resource's own serialized document.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [...parent::jsonSerialize(), 'headline' => $this->title];
    }
}
