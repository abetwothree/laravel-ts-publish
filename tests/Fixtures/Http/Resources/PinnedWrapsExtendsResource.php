<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Workbench\App\Http\Resources\Discriminated\PinnedWrapsResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that publishes arms and no shared key beside an extends clause.
 *
 * @mixin Post
 */
#[TsExtends('HasTimestamps', '@/types/common')]
class PinnedWrapsExtendsResource extends PinnedWrapsResource {}
