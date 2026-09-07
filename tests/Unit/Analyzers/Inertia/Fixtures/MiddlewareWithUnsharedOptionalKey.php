<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** `filters` is declared only in the docblock, never shared, so it must keep its optional marker. */
#[TsCasts(['appName' => 'AppName'])]
class MiddlewareWithUnsharedOptionalKey
{
    /**
     * @return array{
     *      appName?: string,
     *      filters?: array<string, string>
     *  }
     */
    public function share(Request $request): array
    {
        return ['appName' => 1];
    }
}
