<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InertiaTableController
{
    /**
     * Create a controller with the service that returns table props.
     */
    public function __construct(private PostTableCrudResource $resource) {}

    /**
     * Direct table prop.
     */
    public function direct(): Response
    {
        return Inertia::render('Tables/Index', [
            'posts' => PostTable::make()->defaultSort('-id'),
        ]);
    }

    /**
     * Table whose model is declared via a query() method instead of $resource.
     */
    public function queryBased(): Response
    {
        return Inertia::render('Tables/Index', [
            'posts' => PostQueryTable::make()->defaultSort('-id'),
        ]);
    }

    /**
     * Service-layer table prop, matching Sonr CMS's controller/resource split.
     */
    public function service(Request $request): Response
    {
        return Inertia::render('Tables/Index', $this->resource->index());
    }

    /**
     * Sibling form route on the same table-bearing resource (no table prop).
     */
    public function serviceCreate(Request $request): Response
    {
        return Inertia::render('Tables/Create', $this->resource->create());
    }

    /**
     * Tainted sibling route with a #[TsCasts] escape hatch declaring prop types statically.
     */
    #[TsCasts(['mode' => 'string'])]
    public function castedCreate(Request $request): Response
    {
        return Inertia::render('Tables/Create', $this->resource->create());
    }
}
