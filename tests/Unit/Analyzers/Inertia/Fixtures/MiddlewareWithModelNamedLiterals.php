<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;

/** Shares the user under a docblock and a cast that also spell its model's name as a string literal. */
final class MiddlewareWithModelNamedLiterals extends Middleware
{
    /**
     * The shared props, one under a cast importing the model's name from another module.
     *
     * @return array{doc: 'User'|User|null}
     */
    #[TsCasts(['ui' => ['type' => 'User', 'import' => '@js/types/ui'], 'cast' => "'User' | User"])]
    public function share(Request $request): array
    {
        return ['doc' => $request->user(), 'cast' => $request->user(), 'ui' => $request->user()];
    }
}
