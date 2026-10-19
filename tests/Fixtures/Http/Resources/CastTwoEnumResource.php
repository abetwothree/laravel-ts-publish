<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose class-level #[TsCasts] retypes a key that reads an accessor of two Status enums.
 *
 * @mixin Warehouse
 */
#[TsCasts(['review_priority' => ['type' => 'StatusType | null', 'import' => '@js/types/status']])]
class CastTwoEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_priority' => $this->review_priority,
            'crm_contact_partial' => $this->primaryContact?->only(['status', 'images']),
        ];
    }
}
