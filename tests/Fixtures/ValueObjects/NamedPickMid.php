<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only subclass whose `pick()` writes a label from the runtime class's `L`. */
class NamedPickMid extends NamedPickRoot
{
    public const L = 5;

    protected $label = 'none';

    /** Write the label from `static::L` and send it. */
    public function pick()
    {
        $this->label = static::L;

        return ['label' => $this->label];
    }
}
