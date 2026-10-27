<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

/** A test-only props collaborator whose untyped `$tier` its own method writes through `static::`. */
class TierPropsBuilder
{
    public const TIER = 'a';

    protected $tier = 'x';

    /** @return array<string, mixed> the props, once the tier is raised to the object's class's constant */
    public function build(): array
    {
        $this->raise();

        return ['tier' => $this->tier];
    }

    /** Raise the tier to the constant the object's class declares. */
    protected function raise(): void
    {
        $this->tier = static::TIER;
    }
}
