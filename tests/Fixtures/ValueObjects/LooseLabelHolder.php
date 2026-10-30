<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose LooseLabel property holds a subclass, so the class its `static` names is only assumed. */
final class LooseLabelHolder
{
    public LooseLabel $held;

    public function __construct()
    {
        $this->held = new LooseLabelChild;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['viaStatic' => $this->held->viaStatic()];
    }
}
