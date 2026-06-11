<?php

declare(strict_types=1);

namespace Workbench\App\Events\Concerns;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;

#[TsExtends('HasTimestamps', '@/types/common')]
trait HasBroadcastTimestamps
{
    public string $occurredAt;
}
