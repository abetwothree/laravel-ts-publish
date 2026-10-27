<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BuiltPrivateBaseResource's `toArray()`, declaring a public namesake of its private
 * `label()`.
 */
class BuiltPrivateResource extends BuiltPrivateBaseResource
{
    /** @return array<string, mixed> */
    public function label(): array
    {
        return ['child_label' => true];
    }
}
