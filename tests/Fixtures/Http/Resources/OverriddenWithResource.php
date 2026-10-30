<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\RetagsWith;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource that spreads its `$with` keys and overrides the trait `retag()` that writes them. */
final class OverriddenWithResource extends JsonResource
{
    use RetagsWith;

    public $with = ['version' => '1.0'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->with($request)];
    }

    /** Keep the keys as they are. */
    public function retag(): static
    {
        return $this;
    }
}
