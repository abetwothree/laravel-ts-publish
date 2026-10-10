<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose class-level #[TsCasts] imports a type named like one of the two enums its
 * `only()` key reads.
 *
 * @mixin Warehouse
 */
#[TsCasts(['review_priority' => ['type' => 'StatusType | null', 'import' => '@js/types/status']])]
class CastTwoEnumAccessorOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['review_priority']),
            'crm_contact_partial' => $this->primaryContact?->only(['status', 'images']),
        ];
    }
}
