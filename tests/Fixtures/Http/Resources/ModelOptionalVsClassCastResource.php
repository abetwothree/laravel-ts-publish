<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * A test-only resource whose class-level #[TsCasts] retypes `latitude`, which its model casts optional, and says
 * nothing about `optional`.
 *
 * @mixin Address
 */
#[TsCasts(['latitude' => 'ClassLatitude'])]
class ModelOptionalVsClassCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'latitude' => $this->latitude];
    }
}
