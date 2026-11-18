<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** A test-only controller whose props pick one shape from two alike but for a case value one writes (GH-166). */
class AlikeArmsController
{
    /** Renders each ternary, whose arms the `c` and `draft` query flags pick. */
    public function show(Request $request, Post $post): Response
    {
        $draft = $request->boolean('draft');

        return Inertia::render('AlikeArms/Show', [
            'lst' => $request->boolean('c') ? [$draft ? Status::Draft->value : $post->status->value] : [$post->status->value],
            'obj' => $request->boolean('c')
                ? ['s' => $draft ? Status::Draft->value : $post->status->value]
                : ['s' => $post->status->value],
        ]);
    }
}
