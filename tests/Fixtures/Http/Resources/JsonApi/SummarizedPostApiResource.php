<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * A JSON:API resource inheriting summary(), with attributes and a type of its own. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class SummarizedPostApiResource extends SummaryBaseApiResource
{
    public $attributes = ['title'];

    /**
     * The resource object's type.
     */
    public function toType(Request $request): string
    {
        return 'summarized_posts';
    }
}
