<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

/** A test-only provider that inherits its provide() body, whose `TIER` is an int. */
final class NinthTierMetadataProvider extends BaseTierMetadataProvider
{
    public const TIER = 9;
}
