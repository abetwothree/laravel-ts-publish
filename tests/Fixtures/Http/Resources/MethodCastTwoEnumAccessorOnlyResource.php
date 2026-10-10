<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose toArray() #[TsCasts] imports a type named like one of the two enums its
 * `only()` key reads.
 *
 * @mixin Warehouse
 */
class MethodCastTwoEnumAccessorOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['review_priority' => ['type' => 'StatusType | null', 'import' => '@js/types/status']])]
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['review_priority']),
            'crm_contact_partial' => $this->primaryContact?->only(['status', 'images']),
        ];
    }
}
