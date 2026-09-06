<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * The model-side twin of DealEnumTrioResource: two User models sharing a basename, read across a
 * ternary so mergeUnion() builds the queue aliasPropertyType() consumes positionally. A ternary is
 * required — an inline array alone never reaches mergeUnion().
 *
 * trio pins the embedded channel: three reads, three rendered tokens, so the queue must keep its
 * repeat. collapsed_arms pins the branch-level channel against it: its two inner arms are the same
 * class, so analyzeClosureUnion() folds them to one rendered token and the queue must drop the
 * repeat — deduping both channels breaks trio, deduping neither breaks collapsed_arms.
 *
 * @mixin Warehouse
 */
class SameBasenameModelTrioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trio' => $this->id > 0
                ? ['a' => $this->primaryContact]
                : ['b' => $this->manager, 'c' => $this->secondaryContact],
            'collapsed_arms' => $this->id > 0
                ? ($this->id > 1 ? $this->primaryContact : $this->secondaryContact)
                : ['c' => $this->manager],
        ];
    }
}
