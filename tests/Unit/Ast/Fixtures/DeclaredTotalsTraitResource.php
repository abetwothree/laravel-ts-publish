<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Concerns\ReadsDeclaredCartTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Spreads a trait helper whose inline `@var` names a class this file does not import. */
final class DeclaredTotalsTraitResource extends JsonResource
{
    use ReadsDeclaredCartTotals;

    /**
     * Spreads the trait's helper.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->declaredTotals()];
    }
}
