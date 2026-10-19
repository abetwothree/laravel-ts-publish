<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Concerns\SharesAppName;
use Inertia\Middleware;

/** Takes its share() from a trait declared in another file. */
final class MiddlewareWithTraitShare extends Middleware
{
    use SharesAppName;
}
