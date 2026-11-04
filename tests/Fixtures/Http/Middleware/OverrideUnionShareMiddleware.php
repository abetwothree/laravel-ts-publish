<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Middleware;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;

/** Shared props whose share() docblock unions a class overridden as `User` with the `User` model, listed and bare. */
final class OverrideUnionShareMiddleware extends Middleware
{
    /**
     * Shares a list of people and users, and the signed-in user.
     *
     * @return array{listed: list<Person|User>, either: Person|User}
     */
    public function share(Request $request): array
    {
        return ['listed' => [new Person, new User(['name' => 'Ada'])], 'either' => new User(['name' => 'Ada'])];
    }
}
