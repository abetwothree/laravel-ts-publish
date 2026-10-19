<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Label;

/**
 * Starts its variable from `parent::toArray()`, the model's own serialization, and adds one key.
 *
 * `Label` has no relation and no accessor, so the delegated base publishes only keys the response carries.
 *
 * @mixin Label
 */
final class ReturnedParentVariableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['display_name'] = strtoupper($this->name);

        return $data;
    }
}
