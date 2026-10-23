<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Regression fixture (Task 12 review, Critical 2): two TOP-LEVEL assignments to the
 * same variable, separated by a guard-clause return. Each branch reads the assignment
 * that reaches it: `early` the notes and `late` the key, never the one recorded last.
 *
 * @mixin Order
 */
class LocalVarGuardClauseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $x = $this->notes;

        if ($request->boolean('early')) {
            return ['early' => $x];
        }

        $x = $this->resource->getKey();

        return ['late' => $x];
    }
}
