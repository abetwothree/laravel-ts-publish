<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` spreads `parent::m()`, which reaches back into it once through `m2()`. */
final class CyclingSpreadBase extends CyclingSpreadGrand
{
    private int $depth = 0;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...parent::m()];
    }

    /** @return array<string, mixed> */
    protected function m2(): array
    {
        if (++$this->depth > 1) {
            return [];
        }

        return [...parent::m(), 'b' => 1];
    }
}
