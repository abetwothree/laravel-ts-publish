<?php

declare(strict_types=1);

namespace Workbench\Crm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;
use Workbench\Crm\Models\Deal;

/**
 * Two Status enums sharing a basename catch what a distinct-named pair (Status/Priority)
 * cannot: aliasPropertyType() matches text, not FQCNs, so a repeat only misaligns once two
 * colliding enums force it to substitute per occurrence instead of reusing one bare name.
 *
 * @mixin Deal
 */
class DealEnumTrioResource extends JsonResource
{
    /**
     * A record embedded in a class-constant list: analyzeConstantListValue() must not
     * re-dedupe the positional list analyzeConstantRecordValue() already built correctly.
     *
     * @var list<array<string, Status|CrmStatus>>
     */
    protected const array MATRIX = [
        ['a' => Status::Draft, 'b' => CrmStatus::Active, 'c' => Status::Draft],
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trio' => ['a' => $this->status, 'b' => $this->crm_status, 'c' => $this->status],
            'matrix' => self::MATRIX,
        ];
    }
}
