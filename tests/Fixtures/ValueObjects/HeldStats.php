<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A value object an API resource holds in place of a model: typed public properties and a typed method. */
final class HeldStats implements Summarizable
{
    public function __construct(public int $total = 3, public string $label = 'x', public ?float $ratio = null) {}

    /** The label and the total. */
    public function summary(): string
    {
        return $this->label.':'.$this->total;
    }
}
