<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource root declaring the `extra()` SpreadingBaseResource spreads through `parent::`. */
class SpreadingGrandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['g' => 1];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['ge' => 1];
    }
}
