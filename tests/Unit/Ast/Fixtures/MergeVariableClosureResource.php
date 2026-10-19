<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Order;

/**
 * Merges closures that build their array in a local variable and return it.
 *
 * @mixin Order
 */
class MergeVariableClosureResource extends JsonResource
{
    /** Merges a variable read completely, one beside a `return []` guard, and one the walk cannot read. */
    public function toArray(Request $request): array
    {
        return [
            $this->merge(function () {
                $m = ['merged_a' => 1];
                $m['merged_b'] = 'x';

                return $m;
            }),
            $this->merge(function () {
                if ($this->id === 0) {
                    return [];
                }

                $m = ['guarded' => true];

                return $m;
            }),
            $this->merge(function () {
                if ($this->id > 0) {
                    return ['literal' => 1];
                }

                $m = ['appended' => 1];
                $m[] = 2;

                return $m;
            }),
        ];
    }
}
