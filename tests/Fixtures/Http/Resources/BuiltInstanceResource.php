<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BaseBuiltInstanceResource's `toArray()`: it overrides the helper the built instances'
 * `label()` spreads, redeclares `$with`, and declares the `with()` its parent leaves to JsonResource.
 */
class BuiltInstanceResource extends BaseBuiltInstanceResource
{
    /** @var array<string, mixed> */
    public $with = ['child_meta' => true];

    /** @return array<string, mixed> */
    public function part(): array
    {
        return ['child_part' => true];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['meta' => 1];
    }
}
