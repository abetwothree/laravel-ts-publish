<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns;

use Workbench\App\ValueObjects\CartTotals;

/**
 * A spread helper whose inline `@var` names a class only this trait's file imports.
 */
trait ReadsDeclaredCartTotals
{
    /**
     * The totals count, read through a local the `@var` types.
     *
     * @return array<string, mixed>
     */
    protected function declaredTotals(): array
    {
        /** @var CartTotals $totals */
        $totals = $this->resource;

        return ['trait_count' => $totals->count];
    }
}
