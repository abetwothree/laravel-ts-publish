<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that turns its arms off, so its keys publish flat whatever the config says.
 *
 * @mixin Post
 */
#[TsResource(discriminated: false)]
class PinnedPostFlatResource extends PinnedPostResource {}
