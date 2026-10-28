<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Models\User;
use Workbench\App\Packages\Ui\Panel;

/**
 * Shares share()'s docblock list of a union of an overridden class and a model another prop imports.
 */
class UiUnionListShareMiddleware extends Middleware
{
    /**
     * The props every page receives.
     *
     * @return array{listed: list<Panel|User>}
     */
    public function share(Request $request): array
    {
        return ['listed' => app('ui.listed'), 'owner' => $request->user() ?? new User()->forceFill(['name' => 'Ada'])];
    }
}
