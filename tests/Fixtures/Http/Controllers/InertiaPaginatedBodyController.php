<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\OrderCollection;
use Workbench\App\Models\Order;

class InertiaPaginatedBodyController
{
    /**
     * A paginated collection whose own toArray() returns its wrap key beside another.
     */
    public function index(): Response
    {
        return Inertia::render('Orders/Index', [
            'orders' => new OrderCollection(Order::query()->paginate()),
        ]);
    }
}
