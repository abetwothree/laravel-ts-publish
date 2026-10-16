<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\JsonApi;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource naming an attribute the model's #[TsCasts] types, and casting another one itself.
 *
 * @mixin Post
 */
#[TsCasts(['rating' => '`${number}` | null'])]
class CastedAttributesPostResource extends JsonApiResource
{
    public $attributes = ['metadata', 'rating', 'status'];
}
