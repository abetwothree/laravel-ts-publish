<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;

/** Shared props whose share() docblock names a model under an optional key the value never sets. */
final class MiddlewareWithOptionalModelDocblock extends Middleware
{
    /**
     * Shares a count, leaving the owner out.
     *
     * @return array{owner?: User, n: int}
     */
    public function share(Request $request): array
    {
        return ['n' => 1];
    }
}
