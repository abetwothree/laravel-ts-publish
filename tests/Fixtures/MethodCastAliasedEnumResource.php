<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose toArray() #[TsCasts] has no import and names an enum the file must alias.
 *
 * @mixin Warehouse
 */
class MethodCastAliasedEnumResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['status' => 'StatusType | null'])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'crm_contact_partial' => $this->primaryContact?->only(['status', 'images']),
        ];
    }
}
