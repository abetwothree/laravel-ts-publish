<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` calls its own private `label()` on instances of the runtime class it builds,
 * which PHP runs over BuiltPrivateResource's public namesake.
 */
class BuiltPrivateBaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'late_built' => new static($this->resource)->label(),
            'late_made' => static::make($this->resource)->label(),
        ];
    }

    /** @return array<string, mixed> */
    private function label(): array
    {
        return ['base_label' => 1];
    }
}
