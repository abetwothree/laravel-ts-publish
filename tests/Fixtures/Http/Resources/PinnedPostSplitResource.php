<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that turns its arms on, so it publishes them whatever the config says.
 *
 * @mixin Post
 */
#[TsResource(discriminated: true)]
class PinnedPostSplitResource extends PinnedPostResource {}
