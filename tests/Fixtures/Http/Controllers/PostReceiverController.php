<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PublishChecks;

/** A controller holding a model and a value object in promoted properties, for receiver calls with no backing model. */
final class PostReceiverController
{
    public function __construct(private Post $post, private PublishChecks $checks) {}
}
