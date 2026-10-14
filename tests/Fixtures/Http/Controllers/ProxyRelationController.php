<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\ProxyRelationPost;
use Workbench\App\Models\Post;

/** A model-less subject holding a model with a `resource` relation, and a model in its own `$resource` property. */
final class ProxyRelationController
{
    public function __construct(
        private ProxyRelationPost $post,
        private Post $resource,
    ) {}
}
