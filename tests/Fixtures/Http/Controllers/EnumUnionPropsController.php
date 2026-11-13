<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\CheckedResourceElvisResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\NullableWrapBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;

/** A test-only controller whose props are unions of enum resources, and of an enum resource and a bare case. */
class EnumUnionPropsController
{
    /** Renders each ternary, whose arms the `c` query flag picks. */
    public function show(Request $request): Response
    {
        $c = $request->boolean('c');

        return Inertia::render('EnumUnions/Show', [
            'either' => $c ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft),
            'mixed' => $c ? EnumResource::make(Status::Draft) : Status::Published,
        ]);
    }

    /** Renders the same ternaries over enum resource collections, which Inertia sends inside their wrap key. */
    public function collections(Request $request): Response
    {
        $c = $request->boolean('c');

        return Inertia::render('EnumUnions/Collections', [
            'either' => $c ? EnumResource::collection(Role::cases()) : EnumResource::collection(Status::cases()),
            'mixed' => $c ? EnumResource::collection(Status::cases()) : Status::Published,
        ]);
    }

    /** Renders a match that wraps a case, wraps every case or reads a case bare, as the `n` query flag picks. */
    public function threeArms(Request $request): Response
    {
        return Inertia::render('EnumUnions/ThreeArms', [
            'status' => match ($request->integer('n')) {
                1 => EnumResource::make(Status::Draft),
                2 => EnumResource::collection(Status::cases()),
                default => Status::Published,
            },
        ]);
    }

    /** Renders a short ternary whose checked operand is an enum resource, which PHP always reads as true. */
    public function elvis(): Response
    {
        return Inertia::render('EnumUnions/Elvis', [
            'status' => EnumResource::make(Status::Draft) ?: Status::Published,
        ]);
    }

    /** Renders the short ternary a helper returns over an enum resource, which Inertia sends as it is returned. */
    public function helperElvis(Request $request): Response
    {
        return Inertia::render('EnumUnions/HelperElvis', [
            'status' => NullableWrapBuilder::statusFrom($request->integer('s', 9)),
        ]);
    }

    /** Renders the helper's short ternary itself, then a resolved resource whose body reads the same helper. */
    public function helperBesideSpread(Request $request): Response
    {
        return Inertia::render('EnumUnions/HelperBesideSpread', [
            'a' => NullableWrapBuilder::statusFrom($request->integer('s', 9)),
            ...CheckedResourceElvisResource::make(null)->resolve(),
        ]);
    }

    /** Renders the same two reads in the other order. */
    public function spreadBesideHelper(Request $request): Response
    {
        return Inertia::render('EnumUnions/SpreadBesideHelper', [
            ...CheckedResourceElvisResource::make(null)->resolve(),
            'a' => NullableWrapBuilder::statusFrom($request->integer('s', 9)),
        ]);
    }

    /** Renders short ternaries over an enum resource of a request enum, a payload the engine cannot type. */
    public function untypedElvis(Request $request): Response
    {
        return Inertia::render('EnumUnions/UntypedElvis', [
            'orNull' => EnumResource::make($request->enum('s', Status::class)) ?: null,
            'orCase' => EnumResource::make($request->enum('s', Status::class)) ?: Status::Published,
        ]);
    }
}
