<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/**
 * Each level reads the one below twice through a fresh receiver, so a body fallback that analyzed every read would
 * analyze the bottom level 2^6 times from the top.
 */
final class NestedPostSummary
{
    /** The bottom level, a literal. */
    public function level0(): array
    {
        return ['views' => 1];
    }

    /** The level below, reached twice. */
    public function level1(): array
    {
        return ['inner' => (new NestedPostSummary)->level0() ?? (new NestedPostSummary)->level0()];
    }

    /** The level below, reached twice. */
    public function level2(): array
    {
        return ['inner' => (new NestedPostSummary)->level1() ?? (new NestedPostSummary)->level1()];
    }

    /** The level below, reached twice. */
    public function level3(): array
    {
        return ['inner' => (new NestedPostSummary)->level2() ?? (new NestedPostSummary)->level2()];
    }

    /** The level below, reached twice. */
    public function level4(): array
    {
        return ['inner' => (new NestedPostSummary)->level3() ?? (new NestedPostSummary)->level3()];
    }

    /** The level below, reached twice. */
    public function level5(): array
    {
        return ['inner' => (new NestedPostSummary)->level4() ?? (new NestedPostSummary)->level4()];
    }

    /** The level below, reached twice. */
    public function level6(): array
    {
        return ['inner' => (new NestedPostSummary)->level5() ?? (new NestedPostSummary)->level5()];
    }
}
