<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Discovery;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Middleware;

#[TsCasts(['appName' => 'DiscoveredAppName'])]
class DiscoverableMiddleware extends Middleware {}
