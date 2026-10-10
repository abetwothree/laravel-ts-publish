<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * A test-only resource whose toArray() #[TsCasts] retypes `latitude`, which its model casts optional, and says nothing
 * about `optional`.
 *
 * @mixin Address
 */
class ModelOptionalVsMethodCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['latitude' => 'MethodLatitude'])]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'latitude' => $this->latitude];
    }
}
