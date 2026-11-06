<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares that it holds an instance of itself, whose undeclared members would forward to it again.
 *
 * @extends JsonResource<SelfHoldingResource>
 */
class SelfHoldingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [];
    }
}
