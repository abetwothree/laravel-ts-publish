<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Controllers\Concerns\BuildsBaseProps;
use Inertia\Inertia;
use Inertia\Response;

/** A test-only controller reading its trait's props helper under two aliases, one of them protected. */
class AliasedHelperController
{
    use BuildsBaseProps {
        buildBase as props;
        buildBase as protected guardedProps;
    }

    /** Render the helper's props under each alias, spread and whole, beside its own name. */
    public function index(): Response
    {
        return Inertia::render('AliasedHelper/Index', [
            'meta' => $this->props(),
            'spread' => [...$this->props()],
            'guarded' => $this->guardedProps(),
            'base' => $this->buildBase(),
        ]);
    }
}
