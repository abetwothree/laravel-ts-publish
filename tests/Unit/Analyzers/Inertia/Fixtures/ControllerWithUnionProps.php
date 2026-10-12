<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Props whose value is a ternary: required over plain arms, optional over a lazy one. */
class ControllerWithUnionProps
{
    public function index(Request $request): Response
    {
        $flag = $request->boolean('flag');

        return Inertia::render('Dashboard/Unions', [
            'y' => $flag ? 1 : 'two',
            'z' => $flag ? Inertia::optional(fn () => 1) : 2,
        ]);
    }
}
