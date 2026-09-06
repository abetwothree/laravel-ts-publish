<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Team;

/**
 * Regression fixture: a mixed EnumResource::collection()/direct-access ternary's per-arm shape
 * (Task 28) must survive every result collector, not only analyzeReturnArray(). Each property
 * below reaches a different collector that used to drop the shape and silently reproduce the
 * pre-fix (missing `[]`) answer.
 *
 * @mixin Team
 */
class MixedEnumMergedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // ThisPropertyHandler::extractPropertiesFromArray(), reached through $this->merge([...]).
            $this->merge([
                'wrapped_history_or_scalar_merged' => $request->boolean('wrap')
                    ? EnumResource::collection($this->status_history)
                    : $this->latest_status,
            ]),
            // ResourceAstAnalyzer::collectVariableArrayAssignments(), reached through a spread
            // method that builds its array in a local variable and assigns keys onto it.
            ...$this->assignedMixed($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function assignedMixed(Request $request): array
    {
        $data = ['assigned_marker' => true];
        $data['wrapped_history_or_scalar_assigned'] = $request->boolean('wrap')
            ? EnumResource::collection($this->status_history)
            : $this->latest_status;

        return $data;
    }
}
