<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;

/** A test-only helper whose untyped returns are short ternaries over a resource built around a nullable value. */
class NullableWrapBuilder
{
    /** Wraps the status the value names, if any. */
    public static function statusFrom(int $value)
    {
        return EnumResource::make(Status::tryFrom($value)) ?: null;
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
