<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\PostSummaryFormatter;
use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ReceiverShapedToArrayModel;
use Workbench\App\Models\Post;
use Workbench\App\ValueObjects\PublishChecks;

/** A controller holding models, a value object and a service in promoted properties, for receivers with no model. */
final class PostReceiverController
{
    public function __construct(
        private Post $post,
        private PublishChecks $checks,
        private ReceiverShapedToArrayModel $shaped,
        private PostSummaryFormatter $formatter,
    ) {}
}
