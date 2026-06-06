<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Requests\StorePostRequest;

/**
 * Demonstrates an Inertia controller that also uses FormRequest validation,
 * used by tests to verify the combined annotateRequestPayload + annotatePageProps output.
 */
class InertiaFormRequestController
{
    /**
     * Show the form for creating a new post.
     *
     * @return Inertia response with no form request type.
     */
    public function create(): Response
    {
        return Inertia::render('InertiaFormRequest/Create');
    }

    /**
     * Store a new post validated via StorePostRequest.
     *
     * @return Inertia response confirming the created post title.
     */
    public function store(StorePostRequest $request): Response
    {
        return Inertia::render('InertiaFormRequest/Success', [
            'title' => $request->validated('title'),
        ]);
    }
}
