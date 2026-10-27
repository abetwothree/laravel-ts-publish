<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\JsonApi;

use Workbench\App\Models\Post;

/**
 * Inherits ParentToAttributesSpreadApiResource's toAttributes(), whose `parent::toAttributes()` is JsonApiResource's.
 * Laravel 12.45 and later.
 *
 * @mixin Post
 */
class InheritedToAttributesSpreadApiResource extends ParentToAttributesSpreadApiResource {}
