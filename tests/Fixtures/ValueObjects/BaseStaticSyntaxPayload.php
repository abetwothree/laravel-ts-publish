<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/**
 * A payload whose helper reads its private `priv()` through `$this->`, and through `$this::`, which PHP looks up on
 * the runtime class with no regard for the private one.
 */
abstract class BaseStaticSyntaxPayload
{
    /** @return array<string, mixed> */
    protected function helper(): array
    {
        return ['static_syntax' => $this::priv(), 'arrow' => $this->priv()];
    }

    private function priv(): int
    {
        return 1;
    }
}
