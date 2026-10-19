<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * Merges its own model, whose `#[TsCasts]` types `latitude`, behind a condition.
 *
 * @mixin Address
 */
final class MergedAddressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, $this->mergeWhen($this->id > 0, fn () => $this->resource)];
    }
}
