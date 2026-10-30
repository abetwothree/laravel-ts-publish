<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose helper, read as a value, spreads `parent::m()`, which reaches back into it once. */
final class CyclingOverwriteValue extends CyclingOverwriteGrand
{
    private int $depth = 0;

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

    /** @return array<string, mixed> */
    protected function m2(): array
    {
        if (++$this->depth > 1) {
            return [];
        }

        return [...parent::m(), 'b' => 1];
    }
}
