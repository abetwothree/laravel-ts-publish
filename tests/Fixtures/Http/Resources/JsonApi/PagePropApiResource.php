<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A JSON:API resource, whose response is a document rather than its body under a wrap key. Laravel 12.45 and later.
 */
class PagePropApiResource extends JsonApiResource {}
