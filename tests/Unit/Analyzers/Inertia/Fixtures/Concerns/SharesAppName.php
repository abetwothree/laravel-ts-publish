<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures\Concerns;

use Illuminate\Http\Request;

/** Supplies a middleware's whole share() from its own file. */
trait SharesAppName
{
    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            'appName' => (string) config('app.name'),
            'fromTrait' => true,
        ];
    }
}
