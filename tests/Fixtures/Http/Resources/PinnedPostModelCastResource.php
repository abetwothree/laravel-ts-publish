<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\PinnedCastPost;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;

/**
 * A test-only resource over a model whose `#[TsCasts]` types the key `placement` follows, so its keys publish flat.
 *
 * @mixin PinnedCastPost
 */
class PinnedPostModelCastResource extends PinnedPostResource {}
