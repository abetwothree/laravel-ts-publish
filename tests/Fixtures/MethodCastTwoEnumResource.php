<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose toArray() #[TsCasts] retypes a key that reads an accessor of two Status enums.
 *
 * @mixin Warehouse
 */
class MethodCastTwoEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['review_priority' => ['type' => 'StatusType | null', 'import' => '@js/types/status']])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_priority' => $this->review_priority,
            'crm_contact_partial' => $this->primaryContact?->only(['status', 'images']),
        ];
    }
}
