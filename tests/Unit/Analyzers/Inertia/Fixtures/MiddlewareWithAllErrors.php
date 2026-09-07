<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

class MiddlewareWithAllErrors extends Middleware
{
    protected $withAllErrors = true;

    public function share(Request $request): array
    {
        return ['appName' => 'Laravel'];
    }
}
