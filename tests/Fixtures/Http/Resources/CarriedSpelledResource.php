<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\TeamResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose `toArray()` #[TsCasts] displaces a model, an enum and a resource, and spells each name,
 * without an import, on a key with no class behind it, beside a key that wraps the displaced enum.
 *
 * @mixin Warehouse
 */
class CarriedSpelledResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts([
        'owner' => 'string',
        'state' => 'string',
        'team' => 'string',
        'label' => 'User | null',
        'tag' => 'StatusType',
        'ref' => 'TeamResource | null',
    ])]
    public function toArray(Request $request): array
    {
        return [
            'owner' => $this->manager,
            'state' => $this->status,
            'team' => TeamResource::make($this->manager),
            'wrapped' => EnumResource::make($this->status),
            'label' => 'x',
            'tag' => 'x',
            'ref' => 'x',
        ];
    }
}
