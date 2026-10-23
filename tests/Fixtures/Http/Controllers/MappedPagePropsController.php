<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Comment;

/** An Inertia action whose props map a query's rows by a key and read an offset of a local array. */
class MappedPagePropsController
{
    /** Renders the comments keyed by id and mapped to their ids, and one offset of a literal shape. */
    public function show(): Response
    {
        $rows = Comment::query()->get();
        $shape = ['a' => 1];

        return Inertia::render('Comments/Keyed', [
            'rows' => $rows->keyBy('id')->map(fn (Comment $c) => ['id' => $c->id])->all(),
            'a' => $shape['a'],
        ]);
    }
}
