<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Requests\UpdatePostRequest;

/** Props read from a validated request, where `title` may be absent and `priority` is nullable. */
class ControllerWithValidatedProps
{
    public function index(UpdatePostRequest $request, bool $flag): Response
    {
        $title = $request->validated('title');

        return Inertia::render('Dashboard/Validated', [
            'a' => $request->validated('title') ?? 'none',
            'b' => $request->validated('priority') ?? 0,
            'c' => $flag ? $request->validated('title') : 'none',
            'd' => $request->validated('title'),
            'e' => ['search' => $request->validated('title') ?? ''],
            'f' => $title ?? 'none',
            'h' => $request->validated('title') ?: 'none',
            'i' => function () use ($request, $flag) {
                if ($flag) {
                    return $request->validated('title');
                }

                return 'none';
            },
            'j' => Inertia::optional(fn () => $request->validated('title')) ?? 'none',
        ]);
    }
}
