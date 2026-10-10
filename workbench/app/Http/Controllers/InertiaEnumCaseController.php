<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Renders bare enum cases as page props. One `Status` only: two same-named enums in one route file import one name
 * twice.
 */
class InertiaEnumCaseController
{
    public function show(Post $post): Response
    {
        return Inertia::render('Posts/EnumCase', [
            'status' => Status::Published,
            'kept' => $post->status ?? Status::Draft,
            'nested' => ['status' => Status::Draft],
            'cases' => [Status::Draft, Status::Published],
        ]);
    }
}
