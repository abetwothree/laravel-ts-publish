<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\F38FlatNamedLeafCollection;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Models\Supplier;

class InertiaStackedCollectionController
{
    /**
     * A paginated `$wrap = null` collection naming no API resource, stacked on one that names its own.
     */
    public function flatPaginated(): Response
    {
        return Inertia::render('Suppliers/Index', [
            'suppliers' => new F38FlatNamedLeafCollection(Supplier::query()->paginate()),
        ]);
    }
}
