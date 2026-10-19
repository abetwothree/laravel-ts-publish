<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource that spreads a helper casting a key, then a helper that sets the key again without a cast,
 * beside a key that reads another model of the value's name.
 *
 * @mixin Warehouse
 */
class SpreadOverCastSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->cast(),
            ...$this->plain(),
            'crm' => $this->primaryContact,
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['owner' => 'string'])]
    protected function cast(): array
    {
        return ['owner' => $this->manager];
    }

    /** @return array<string, mixed> */
    protected function plain(): array
    {
        return ['owner' => $this->manager];
    }
}
