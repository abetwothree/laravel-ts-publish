<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Panel;

/**
 * Shares share()'s docblock union of an overridden class and a model another prop imports.
 */
class UiUnionShareMiddleware extends Middleware
{
    /**
     * The props every page receives.
     *
     * @return array{either: Panel|User}
     */
    public function share(Request $request): array
    {
        return ['either' => app('ui.either'), 'owner' => $request->user() ?? new User()->forceFill(['name' => 'Ada'])];
    }
}
