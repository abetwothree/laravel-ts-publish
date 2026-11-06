<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish;

use AbeTwoThree\LaravelTsPublish\LaravelTsPublish;
use LogicException;

/** A LaravelTsPublish that records how deep serialize() body reads nest, and stops one nested past two. */
class DepthLimitedLaravelTsPublish extends LaravelTsPublish
{
    /** The deepest the serialize() body reads have nested. */
    public int $deepest = 0;

    /** How deep the serialize() body reads under way nest. */
    private int $depth = 0;

    /**
     * Read the body as the package does, throwing past two nested reads, where only a missing guard reaches.
     *
     * @param  class-string  $caster
     * @return array<string, mixed>|null
     */
    protected function serializeBodyTypes(string $caster): ?array
    {
        $this->deepest = max($this->deepest, ++$this->depth);

        try {
            if ($this->depth > 2) {
                throw new LogicException('serializeBodyTypes() nested past two reads of one cast.');
            }

            return parent::serializeBodyTypes($caster);
        } finally {
            $this->depth--;
        }
    }
}
