<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Enums\Visibility;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Renders an EnumResource prop, which Inertia sends as the resource's objects rather than the bare cases.
 */
class InertiaEnumResourceController
{
    /**
     * Every status case inside the enum resource collection's wrap key.
     *
     * Result should be { statuses: { data: AsEnum<typeof Status>[] } }
     */
    public function index(): Response
    {
        return Inertia::render('Enums/Statuses', [
            'statuses' => EnumResource::collection(Status::cases()),
        ]);
    }

    /**
     * A ternary over two enums' resources, and one of a resource and a bare case of its enum.
     *
     * Result should be { either: AsEnum<typeof Role> | AsEnum<typeof Status>,
     * mixed: AsEnum<typeof Status> | StatusType }
     */
    public function unions(Request $request): Response
    {
        $admin = $request->boolean('admin');

        return Inertia::render('Enums/Unions', [
            'either' => $admin ? EnumResource::make(Role::Admin) : EnumResource::make(Status::Draft),
            'mixed' => $admin ? EnumResource::make(Status::Draft) : Status::Published,
        ]);
    }

    /**
     * The post's nullable visibility, which Inertia sends as `[]` while it is null.
     *
     * Result should be { visibility: AsEnum<typeof Visibility> | never[] }
     */
    public function visibility(Post $post): Response
    {
        return Inertia::render('Enums/Visibility', [
            'visibility' => EnumResource::make($post->visibility),
        ]);
    }

    /**
     * Unions of EnumResource props with a bare case, a nested ternary, a third wrap, a same-named enum and a collection
     * (GH-108, GH-160). Each wrap arm sends its enum's object, each bare case its value.
     */
    public function mixes(Request $request, Post $post): Response
    {
        $a = $request->boolean('a');
        $b = $request->boolean('b');

        return Inertia::render('Enums/Mixes', [
            'wrap_or_other_case' => $a ? EnumResource::make(Role::Admin) : Status::Published,
            'wrap_or_nested_nullable_case' => $a ? EnumResource::make(Status::Draft) : ($b ? Status::Published : null),
            'three_wraps' => $a
                ? EnumResource::make(Role::Admin)
                : ($b ? EnumResource::make(Status::Draft) : EnumResource::make(Visibility::Public)),
            'same_named_wraps' => $a ? EnumResource::make(Status::Draft) : EnumResource::make(CrmStatus::Lead),
            'collection_or_case' => $a ? EnumResource::collection(Status::cases()) : Status::Published,
            'nested_two_wraps' => [
                'x' => $a ? EnumResource::make($post->visibility) : EnumResource::make($post->status),
            ],
        ]);
    }
}
