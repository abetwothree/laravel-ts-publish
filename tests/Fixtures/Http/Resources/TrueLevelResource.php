<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A test-only sibling of SevenLevelResource whose `LEVEL` is a bool. */
class TrueLevelResource extends BasePrivateLevelResource
{
    public const LEVEL = true;
}
