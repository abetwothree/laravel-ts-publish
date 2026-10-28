<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Packages\Ui\Panel;

class UiPanelPageController
{
    /**
     * A prop built by a helper declaring a nullable class that inherits its parent's override.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('Ui/Panel', [
            'maybe' => $this->maybePanel($request->boolean('flag')),
        ]);
    }

    /**
     * A panel when asked for one.
     */
    protected function maybePanel(bool $flag): ?Panel
    {
        return $flag ? new Panel : null;
    }
}
