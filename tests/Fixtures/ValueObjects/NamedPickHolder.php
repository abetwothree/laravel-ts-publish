<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload reading `run()` on an object declared as the base, which is a further subclass. */
final class NamedPickHolder
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['v' => $this->root()->run()];
    }

    /** The root, declared as the base though it is the further subclass. */
    public function root(): NamedPickRoot
    {
        return new NamedPickSub;
    }
}
