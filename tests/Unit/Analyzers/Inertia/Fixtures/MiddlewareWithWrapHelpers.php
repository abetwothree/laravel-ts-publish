<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;

/** Shares a short ternary its own static helper returns; an API resource reads the same helpers. */
class MiddlewareWithWrapHelpers extends Middleware
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

    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['status' => self::statusFrom($request->integer('s', 9))];
    }
}
