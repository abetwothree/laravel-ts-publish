<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\Concerns;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\InertiaUiTable\PostTable;
use Inertia\Inertia;
use Inertia\Response;

/** Supplies a controller's table action from its own file: InertiaTableController::direct(), moved. */
trait RendersPostTable
{
    /** Direct table prop. */
    public function direct(): Response
    {
        return Inertia::render('Tables/Index', [
            'posts' => PostTable::make()->defaultSort('-id'),
        ]);
    }
}
