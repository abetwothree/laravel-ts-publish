<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * The runtime class of BaseStaticSyntaxPayload's helper, redeclaring its private `priv()` as a public method.
 */
final class StaticSyntaxPayload extends BaseStaticSyntaxPayload
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...$this->helper()];
    }

    public function priv(): string
    {
        return 'x';
    }
}
