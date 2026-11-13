<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Tables\PostTable;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shares an Inertia UI Table beside a plain prop. */
class MiddlewareWithTable extends Middleware
{
    /**
     * The shared props.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return ['posts' => PostTable::make()->defaultSort('-id'), 'count' => 3];
    }
}
