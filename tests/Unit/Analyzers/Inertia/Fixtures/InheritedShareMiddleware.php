<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** Overrides `theme` and adds `sidebarOpen`, so both parent-chain merge rules are observable. */
class InheritedShareMiddleware extends SharedParentMiddleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'theme' => $request->integer('theme'),
            'sidebarOpen' => $request->boolean('sidebar'),
        ];
    }
}
