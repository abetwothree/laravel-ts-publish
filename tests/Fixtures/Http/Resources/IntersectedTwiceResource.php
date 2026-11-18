<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Workbench\App\Http\Resources\Discriminated\PinnedPostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource intersected with two types of one name from two paths, one of them written twice.
 *
 * @mixin Post
 */
#[TsResource(discriminated: false)]
#[TsIntersect('Placement', '@/types/feed')]
#[TsIntersect('Placement', '@/types/pinned')]
#[TsIntersect('Placement', '@/types/feed')]
class IntersectedTwiceResource extends PinnedPostResource {}
