<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Exercises local variable type tracking inside toArray().
 *
 * `shadowed` is a Task 12 regression: a variable assigned at the top level AND
 * reassigned inside nested control flow must not resolve through the (possibly
 * stale) top-level binding alone — it joins the values that can reach the read.
 *
 * @mixin Order
 */
class LocalVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $label = $this->notes ?? 'None';
        $key = $this->resource->getKey();
        $shadowed = 'top-level default';

        if ($request->boolean('flag')) {
            $shadowed = $this->notes;
        }

        return [
            'label' => $label,
            'key' => $key,
            'shadowed' => $shadowed,
        ];
    }
}
