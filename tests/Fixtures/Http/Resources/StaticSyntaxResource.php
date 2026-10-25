<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A test-only runtime class of BaseStaticSyntaxResource's `toArray()`: it overrides every helper that body reaches and
 * declares `publishable()`, which the model it wraps also has.
 */
class StaticSyntaxResource extends BaseStaticSyntaxResource
{
    /** @return array<string, mixed> */
    public function label(): array
    {
        return ['child_label' => true];
    }

    public function publishable(): string
    {
        return 'child';
    }

    /** @return array<string, mixed> */
    protected function helper(): array
    {
        return ['child_helper' => true];
    }

    /** @return array<string, mixed> */
    protected function lateHelper(): array
    {
        return ['child_late' => true];
    }
}
