<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Two renders of one page: an empty list and a case value in one, the list and the enum in the other (GH-166).
 */
class InertiaRedundantArmsController
{
    public function show(Post $post): Response
    {
        if ($post->id > 5) {
            return Inertia::render('Posts/RedundantArms', ['priorities' => [], 'status' => Status::Draft->value]);
        }

        return Inertia::render('Posts/RedundantArms', [
            'priorities' => [$post->priority],
            'status' => $post->status->value,
            'status_or_draft' => $post->id > 1 ? Status::Draft->value : $post->status->value,
        ]);
    }
}
