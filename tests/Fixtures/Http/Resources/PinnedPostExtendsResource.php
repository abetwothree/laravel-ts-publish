<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that publishes its arms beside an extends clause.
 *
 * @mixin Post
 */
#[TsExtends('HasTimestamps', '@/types/common')]
class PinnedPostExtendsResource extends PinnedPostResource {}
