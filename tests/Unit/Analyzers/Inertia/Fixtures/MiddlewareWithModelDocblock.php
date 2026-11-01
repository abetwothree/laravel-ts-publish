<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;

/** Shared props whose share() docblock names a model and an enum. */
final class MiddlewareWithModelDocblock extends Middleware
{
    /**
     * Shares the signed-in user and a status.
     *
     * @return array{owner: User, status: Status}
     */
    public function share(Request $request): array
    {
        return ['owner' => $request->user(), 'status' => Status::Draft];
    }
}
