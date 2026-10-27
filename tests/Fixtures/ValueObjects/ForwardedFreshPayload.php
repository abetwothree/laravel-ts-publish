<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** Spreads ForwardedFreshBase's payload() and reads `parent::fresh()`, each of which builds this class. */
final class ForwardedFreshPayload extends ForwardedFreshBase
{
    /** @return array<string, mixed> */
    public function kind(): array
    {
        return ['b' => true];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...$this->payload(), 'parent_fresh' => parent::fresh()->kind()];
    }
}
