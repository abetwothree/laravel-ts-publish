<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Packages\Ui\Chart;
use Workbench\App\Packages\Ui\Dial;
use Workbench\App\Packages\Ui\Gauge;
use Workbench\App\Packages\Ui\Node;
use Workbench\App\Packages\Ui\Panel;

/**
 * Shares a value whose type share()'s docblock shape states more closely than the body and one the body cannot type,
 * beside two a cast replaces: one bringing the docblock's name from another module, and one naming a global type.
 */
class UiDocblockShareMiddleware extends Middleware
{
    /**
     * The props every page receives.
     *
     * @return array{chart: Chart, nested: array{panel: Panel, n: int}, cast: Gauge, dial: Dial}
     */
    #[TsCasts(['cast' => ['type' => 'UiGauge', 'import' => '@acme/ui'], 'dial' => 'DialType'])]
    public function share(Request $request): array
    {
        return ['chart' => $this->node(), 'nested' => app('ui.nested'), 'cast' => app('ui.gauge'), 'dial' => $this->node()];
    }

    /**
     * A chart, by its declared parent.
     */
    protected function node(): Node
    {
        return new Chart;
    }
}
