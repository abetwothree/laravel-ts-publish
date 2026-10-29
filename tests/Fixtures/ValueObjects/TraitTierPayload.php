<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\Concerns\PromotesTier;

/** An untyped `$tier` its trait's promote() writes with this class's PROMOTED. */
final class TraitTierPayload
{
    use PromotesTier;

    public const PROMOTED = 5;

    protected $tier = 'gold';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['tier' => $this->tier];
    }
}
