<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/** Inertia actions whose typed parameters are read by the props they render. */
class TypedActionParametersController
{
    /** Reads a scalar, an enum and a nullable model, each by its declared type, the model also bare. */
    public function show(int $id, Status $status, ?Post $post = null): Response
    {
        return Inertia::render('Typed/Show', [
            'id' => $id,
            'status' => $status,
            'title' => $post?->title,
            'post' => $post,
        ]);
    }

    /** Reads an abstract model parameter bare, which names no class a published file exports. */
    public function abstractModel(Model $model): Response
    {
        return Inertia::render('Typed/AbstractModel', [
            'model' => $model,
        ]);
    }

    /** Reads a nullable scalar and a union parameter. */
    public function filter(?string $term = null, int|string $key = 0): Response
    {
        return Inertia::render('Typed/Filter', [
            'term' => $term,
            'key' => $key,
        ]);
    }
}
