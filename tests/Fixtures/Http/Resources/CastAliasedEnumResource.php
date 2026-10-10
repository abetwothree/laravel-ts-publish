<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose class-level #[TsCasts] has no import and names an enum the file must alias.
 *
 * @mixin Warehouse
 */
#[TsCasts(['status' => 'StatusType | null'])]
class CastAliasedEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'crm_contact_partial' => $this->primaryContact?->only(['status', 'images']),
        ];
    }
}
