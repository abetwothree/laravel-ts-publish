<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

/** A test-only final props collaborator, so the object injected is exactly this class, whose `TIER` is an int. */
final class FinalTierPropsBuilder extends TierPropsBuilder
{
    public const TIER = 9;
}
