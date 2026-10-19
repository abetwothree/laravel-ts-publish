<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Keys a resource merges from outside itself, which the analysis does not read. */
final class MergedFields
{
    /**
     * The keys.
     *
     * @return array<string, int>
     */
    public static function all(): array
    {
        return ['extra_a' => 1];
    }
}
