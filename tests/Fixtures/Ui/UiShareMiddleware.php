<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Panel;
use Workbench\App\Packages\Ui\Pennies;

/**
 * Shares values of overridden classes, kept out of the workbench so no workbench page's shared data moves.
 */
class UiShareMiddleware extends Middleware
{
    /**
     * The props every page receives.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            'node' => new Panel,
            'pennies' => new Pennies(5),
            'chart' => $this->chart(),
        ];
    }

    /**
     * A chart, by its declared class.
     */
    protected function chart(): Chart
    {
        return new Chart;
    }
}
