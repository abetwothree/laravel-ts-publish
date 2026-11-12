<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** Spreads a helper whose own `@return` types the member a cache read leaves unknown (GH-154). */
class MiddlewareWithDocumentedSpreadHelper extends Middleware
{
    public function share(Request $request): array
    {
        return [...parent::share($request), ...$this->recipientProps()];
    }

    /** @return array{recipients: list<array{name: string, settings: array{digest: bool}|null}>} */
    protected function recipientProps(): array
    {
        return ['recipients' => array_map(fn ($n) => ['name' => $n, 'settings' => cache('mail.'.$n)], ['a', 'b'])];
    }
}
