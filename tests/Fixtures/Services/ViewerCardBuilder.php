<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\UserCardResource;
use Workbench\App\Models\User;

/** A test-only service building a user's card around whatever user it is handed, a guest's null included. */
class ViewerCardBuilder
{
    /** Builds the card. */
    public function card(?User $user)
    {
        return new UserCardResource($user);
    }
}
