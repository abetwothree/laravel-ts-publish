<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsResource;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/** A body-less collection that names a model and the resource it collects, each by attribute. Laravel 13 only. */
#[TsResource(model: Post::class)]
#[Collects(PostResource::class)]
class AttributedPostCollection extends ResourceCollection {}
