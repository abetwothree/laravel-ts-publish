<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Workbench\App\Models\Post;

/**
 * Redeclares the parent's callable property with another function's name.
 *
 * @mixin Post
 */
final class TransformCallableSourcesChildResource extends TransformCallableSourcesResource
{
    /** A function's name, in place of the parent's. */
    public string $measure = 'ucfirst';
}
