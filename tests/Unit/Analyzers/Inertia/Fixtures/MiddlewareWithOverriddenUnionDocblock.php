<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;

/** Shared props whose share() docblock unions a class overridden as `User` with the `User` model. */
final class MiddlewareWithOverriddenUnionDocblock extends Middleware
{
    /**
     * Shares a person and the signed-in user, or a person when no one is signed in.
     *
     * @return array{person: Person, either: Person|User}
     */
    public function share(Request $request): array
    {
        return ['person' => new Person, 'either' => $request->user() ?? new Person];
    }
}
