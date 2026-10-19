<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * A test-only resource whose toArray() #[TsCasts] retypes `latitude`, which its model casts optional, and clears
 * `optional`.
 *
 * @mixin Address
 */
class ModelOptionalVsRequiredMethodCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['latitude' => ['type' => 'MethodLatitude', 'optional' => false]])]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'latitude' => $this->latitude];
    }
}
