<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\HeldStatsBodyParentResource;
use Workbench\App\Models\Post;

/**
 * A body-less subclass whose mixin names a post over its parent's value-object generic.
 *
 * @mixin Post
 */
final class MixinPostHeldStatsChildResource extends HeldStatsBodyParentResource {}
