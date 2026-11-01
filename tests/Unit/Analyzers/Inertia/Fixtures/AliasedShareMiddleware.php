<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Concerns\SharesBase;
use Inertia\Middleware;

/** Takes its share() from a trait method it aliases, declared in another file. */
class AliasedShareMiddleware extends Middleware
{
    use SharesBase {
        shareBase as share;
    }
}
