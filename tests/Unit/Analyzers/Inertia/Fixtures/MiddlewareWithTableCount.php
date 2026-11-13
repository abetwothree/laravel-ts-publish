<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostQueryTable;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shares the count and the plucked ids of a table's query, chains that end past the table. */
class MiddlewareWithTableCount extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['n' => PostQueryTable::make()->query()->count(), 'ids' => PostQueryTable::make()->query()->pluck('id')];
    }
}
