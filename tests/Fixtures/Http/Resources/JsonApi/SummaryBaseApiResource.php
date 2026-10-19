<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Workbench\App\Models\Post;

/**
 * A JSON:API base resource whose summary() returns the document its subclass serializes to. Laravel 12.45 and later.
 *
 * @mixin Post
 */
class SummaryBaseApiResource extends JsonApiResource
{
    public $attributes = ['content'];

    /**
     * The resource object's type.
     */
    public function toType(Request $request): string
    {
        return 'summary_bases';
    }

    /**
     * The document this resource serializes to.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->jsonSerialize();
    }
}
