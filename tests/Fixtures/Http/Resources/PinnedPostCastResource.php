<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose class `#[TsCasts]` types the key `placement` follows, so its keys publish flat.
 *
 * @mixin Post
 */
#[TsCasts(['is_pinned' => 'boolean'])]
class PinnedPostCastResource extends PinnedPostResource {}
