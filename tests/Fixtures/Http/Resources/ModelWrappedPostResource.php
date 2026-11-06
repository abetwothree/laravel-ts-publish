<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\ModelWrappingResource;
use Workbench\App\Models\Post;

/**
 * A resource whose mixin names its model over its parent's `@var Model`.
 *
 * @mixin Post
 */
final class ModelWrappedPostResource extends ModelWrappingResource {}
