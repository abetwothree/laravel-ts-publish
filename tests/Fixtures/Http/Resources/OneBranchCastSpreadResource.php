<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource with two return branches, only the last of which spreads a helper casting a key the other
 * sets, beside a key that reads another model of the value's name.
 *
 * @mixin Warehouse
 */
class OneBranchCastSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->id) {
            return [
                'owner' => $this->manager,
                'crm' => $this->primaryContact,
            ];
        }

        return [
            ...$this->cast(),
            'crm' => $this->primaryContact,
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['owner' => 'string'])]
    protected function cast(): array
    {
        return ['owner' => $this->manager];
    }
}
