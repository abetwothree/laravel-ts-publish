<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose helper, read as a value, spreads its parent's `m()` through `parent::`. */
final class OverwrittenSpreadValue extends OverwrittenSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['v' => $this->helper()];
    }

    /** @return array<string, mixed> */
    public function helper(): array
    {
        return ['k' => 1, ...parent::m()];
    }
}
