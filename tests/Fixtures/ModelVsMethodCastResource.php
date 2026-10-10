<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * A test-only resource whose toArray() #[TsCasts] retypes a key its model's #[TsCasts] also retypes.
 *
 * @mixin Address
 */
class ModelVsMethodCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['longitude' => 'MethodLongitude'])]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'longitude' => $this->longitude];
    }
}
