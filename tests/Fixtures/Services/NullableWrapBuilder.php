<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\ProfileResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

/** A test-only helper whose untyped returns wrap a nullable value in a resource, most behind a short ternary. */
class NullableWrapBuilder
{
    /** Wraps the status the value names, if any. */
    public static function statusFrom(int $value)
    {
        return EnumResource::make(Status::tryFrom($value)) ?: null;
    }

    /** Wraps the status the value names, which may be none, with no short ternary over it. */
    public static function status(int $value)
    {
        return EnumResource::make(Status::tryFrom($value));
    }

    /** Wraps the user's profile, which may be absent, with no short ternary over it. */
    public static function profile(User $user)
    {
        return new ProfileResource($user->profile);
    }

    /** Wraps whatever user it is handed. */
    public static function user(mixed $user)
    {
        return UserResource::make($user) ?: null;
    }

    /** Wraps the status the value names, if any, from an instance. */
    public function instanceStatusFrom(int $value)
    {
        return EnumResource::make(Status::tryFrom($value)) ?: null;
    }
}
