<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Effects;

use Workbench\App\Models\Post;

/**
 * A subject that is no API resource: its `resource` is a property of its own, and nothing forwards to it.
 */
class InterveningSubject
{
    public bool $is_pinned = true;

    public function __construct(public Post $resource) {}
}
