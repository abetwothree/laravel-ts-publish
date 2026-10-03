<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Handover;
use Workbench\App\Services\HandoverRoster;

/**
 * Reads members typed by a docblock union whose arms render alike for two models that share a name.
 *
 * @mixin Handover
 */
class HandoverRosterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'members' => (new HandoverRoster)->members,
            'reviewers' => (new HandoverRoster)->reviewers(),
            'involved' => $request->boolean('crm') ? (new HandoverRoster)->members : $this->sender,
        ];
    }
}
