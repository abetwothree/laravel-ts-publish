<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;

/** The array_merge() spelling of InheritedShareMiddleware's spread; both must produce one shape. */
class ArrayMergeShareMiddleware extends SharedParentMiddleware
{
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'theme' => $request->integer('theme'),
            'sidebarOpen' => $request->boolean('sidebar'),
        ]);
    }
}
