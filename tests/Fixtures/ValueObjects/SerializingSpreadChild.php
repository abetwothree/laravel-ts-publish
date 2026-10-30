<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose helper spreads the `__serialize()` a Laravel trait gives its parent through `parent::`. */
final class SerializingSpreadChild extends SerializingSpreadBase
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['v' => $this->helper()];
    }

    /** @return array<string, mixed> */
    public function helper(): array
    {
        return ['k' => 1, ...parent::__serialize()];
    }
}
