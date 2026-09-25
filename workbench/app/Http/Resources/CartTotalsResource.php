<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CartTotals;

/**
 * A resource over a value object rather than a model. The inline `@var` on each local names what it holds, for the
 * reads after its assignment and before the variable is written again.
 */
final class CartTotalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartTotals $totals */
        $totals = $this->resource;

        /** @var CartTotals */
        $unnamed = $this->resource;

        /** @var string $label */
        $label = $totals->label();

        /** @var CartTotals $current */
        $current = $this->resource;
        $countBefore = $current->count;
        $current = $this->additional['previous'] ?? null;

        /** @var MissingTotals $missing */
        $missing = $this->resource;

        return [
            'subtotal' => $totals->subtotal,
            'chargeable' => $totals->chargeable,
            'count' => $totals->count,
            'note' => $totals->note(),
            'totals' => $totals,
            'unnamed_count' => $unnamed->count,
            'label' => $label,
            'count_before' => $countBefore,
            'count_after' => $current?->count,
            'missing_count' => $missing->count,
        ];
    }
}
