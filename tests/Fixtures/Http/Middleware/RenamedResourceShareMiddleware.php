<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Middleware;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;

/** Shared props whose share() docblock lists an override and the model beside a resource published as `Address`. */
final class RenamedResourceShareMiddleware extends Middleware
{
    /**
     * Shares a list of addresses, people and users, and a list of people and users.
     *
     * @return array{listed: list<AddressResource|Person|User>, control: list<Person|User>}
     */
    public function share(Request $request): array
    {
        return ['listed' => [new User(['name' => 'Ada'])], 'control' => [new User(['name' => 'Ada'])]];
    }
}
