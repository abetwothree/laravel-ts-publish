<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use Workbench\App\Models\User;

/** A test-only collaborator building a page's props: a first-class callable and an API resource. */
class CardPropsBuilder
{
    /**
     * The props a page renders for a user.
     *
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        return ['stamp' => now(...), 'card' => new UserCardResource($user)];
    }
}
