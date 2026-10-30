<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Error;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource that spreads its `$with` keys, which only an anonymous object and a static closure write. */
final class ForeignWithResource extends JsonResource
{
    public $with = ['version' => '1.0'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->with($request)];
    }

    /** Write a `$with` that is never this resource's: an anonymous object's own, then one from a static closure. */
    public function relabel(): static
    {
        $other = new class
        {
            public $with = [];

            /** Set the anonymous object's own keys. */
            public function set(): void
            {
                $this->with = ['other' => 1];
            }
        };
        $other->set();

        try {
            array_map(static fn (): array => $this->with = ['other' => 2], [1]);
        } catch (Error) {
        }

        return $this;
    }
}
