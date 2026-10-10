<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose inline array spreads a helper whose #[TsCasts] imports a type named like the model it
 * casts, beside a key that reads another model of that name.
 *
 * @mixin Warehouse
 */
class InlineSpreadCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [...$this->contacts(), 'x' => 1],
            'crm' => $this->primaryContact,
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['manager' => ['type' => 'User | null', 'import' => '@js/types/user']])]
    protected function contacts(): array
    {
        return ['manager' => $this->manager];
    }
}
