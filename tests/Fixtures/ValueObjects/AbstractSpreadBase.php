<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose helper spreads its parent's abstract `extra()` through `parent::`, which throws. */
final class AbstractSpreadBase extends AbstractSpreadGrand
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['v' => $this->helper()];
    }

    /** @return array<string, mixed> */
    public function helper(): array
    {
        return ['k' => 1, ...parent::extra()];
    }

    /** @return array<string, mixed> */
    protected function extra(): array
    {
        return ['b' => true];
    }
}
