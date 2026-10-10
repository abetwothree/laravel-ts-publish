<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only parent resource with no toArray() and no model, so its delegation publishes nothing.
 */
class ReturnedBodylessParentResource extends JsonResource {}
