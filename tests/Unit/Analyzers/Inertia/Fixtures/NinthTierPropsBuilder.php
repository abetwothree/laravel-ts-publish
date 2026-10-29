<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

/** A test-only subclass the container can inject for TierPropsBuilder, whose `TIER` is an int. */
class NinthTierPropsBuilder extends TierPropsBuilder
{
    public const TIER = 9;
}
