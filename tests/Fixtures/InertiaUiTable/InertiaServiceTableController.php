<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InertiaServiceTableController
{
    /**
     * Inject the table-bearing CRUD resource (its index() returns a table),
     * but declare NO inline table in this controller's own file.
     */
    public function __construct(private PostTableCrudResource $resource) {}

    /**
     * Inertia index route — props come from the table-bearing resource.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Tables/Index', $this->resource->index());
    }

    /**
     * Non-Inertia action that touches the table-bearing resource, mirroring a
     * CRUD store(): no Inertia::render(), returns a redirect.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->resource->create();

        return redirect('/');
    }
}
