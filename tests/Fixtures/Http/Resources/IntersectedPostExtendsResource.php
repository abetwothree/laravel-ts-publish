<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Workbench\App\Http\Resources\Discriminated\IntersectedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that inherits its parent's intersection and publishes it beside an extends clause.
 *
 * @mixin Post
 */
#[TsResource(discriminated: false)]
#[TsExtends('HasTimestamps', '@/types/common')]
class IntersectedPostExtendsResource extends IntersectedPostResource {}
