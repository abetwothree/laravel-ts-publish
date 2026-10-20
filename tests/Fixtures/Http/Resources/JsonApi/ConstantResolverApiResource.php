<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource whose relationship resolver is a class constant holding no class name, so Laravel guesses the
 * relationship's resource from the related model. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class ConstantResolverApiResource extends JsonApiResource
{
    public const string AUTHOR = 'author';

    public $attributes = ['title'];

    /**
     * The relationships an include can send.
     *
     * @return array<string, string>
     */
    public function toRelationships(Request $request): array
    {
        return ['author' => self::AUTHOR];
    }
}
