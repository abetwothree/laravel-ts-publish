<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * Regression fixture: a mixed EnumResource::collection()/direct-access ternary's per-arm shape
 * (Task 28) must survive ResourceAstAnalyzer::mergeReturnBranches(), not only the single-branch
 * analyzeReturnArray() path. Each `if`/`else` here returns its own array, so toArray() has more
 * than one top-level `return` and is merged rather than analyzed directly.
 *
 * @mixin Team
 */
class MixedEnumReturnBranchesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->is_active) {
            return [
                'id' => $this->id,
                'branch_active' => true,
            ];
        }

        return [
            'id' => $this->id,
            'wrapped_history_or_scalar' => $request->boolean('wrap')
                ? EnumResource::collection($this->status_history)
                : $this->latest_status,
        ];
    }
}
