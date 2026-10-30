<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Post;

/** A test-only controller of page casts, mostly import-less, over the classes its props carry and on a props helper. */
class ImportlessCastController
{
    /** Renders the route-bound post under a cast that names its model. */
    #[TsCasts(['post' => 'Post | null'])]
    public function show(Post $post): Response
    {
        return Inertia::render('ImportlessCast/Show', ['post' => $post]);
    }

    /** Renders the notes a props helper builds under its own cast. */
    public function notes(): Response
    {
        return Inertia::render('ImportlessCast/Notes', ['notes' => $this->noteProps()]);
    }

    /** Renders a model under a cast not spelling it, beside a cast that does over a value the engine cannot type. */
    #[TsCasts(['meta' => 'Record<string, unknown>', 'owner' => 'User | null'])]
    public function carried(Post $post): Response
    {
        return Inertia::render('ImportlessCast/Carried', [
            'meta' => ['author' => $post->author],
            'owner' => request()->user(),
        ]);
    }

    /** Renders the author under a cast that imports its own `User`. */
    #[TsCasts(['author' => ['type' => 'User', 'import' => '@js/types/ui']])]
    public function castFromModule(Post $post): Response
    {
        return Inertia::render('ImportlessCast/CastFromModule', ['author' => $post->author]);
    }

    /** Renders the author beside a cast that spells its model over a value the engine cannot type. */
    #[TsCasts(['owner' => 'User | null'])]
    public function castBesideModel(Post $post): Response
    {
        return Inertia::render('ImportlessCast/CastBesideModel', [
            'owner' => request()->user(),
            'author' => $post->author,
        ]);
    }

    /** The notes, a key of which the helper's cast types through its signature. */
    #[TsCasts(['[key: `${string}_note`]' => 'number'])]
    private function noteProps(): array
    {
        return ['x_note' => 'y', 'n' => 1];
    }
}
