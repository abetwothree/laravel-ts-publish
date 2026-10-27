<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only runtime class of WrittenTallyBase's `toArray()` that writes each default a value no constant is. */
final class WrittenTallyChild extends WrittenTallyBase
{
    /** Move the tier to the number passed. */
    public function setTier(int $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    /** Keep the tier, or name it when empty, reading the tier it writes. */
    public function keepTier(): static
    {
        $this->tier = $this->tier ?: 'bronze';

        return $this;
    }

    /** Count one more, reading the count it writes. */
    public function bump(): static
    {
        $this->count = $this->count + 1;

        return $this;
    }

    /** Count one more in place. */
    public function tick(): static
    {
        $this->count++;

        return $this;
    }

    /** Keep the label, or name it when empty, reading the label it writes. */
    public function relabel(): static
    {
        $this->label = $this->label ?: 'none';

        return $this;
    }

    /** Move the label to the number passed. */
    public function setLabel(int $label): static
    {
        $this->label = $label;

        return $this;
    }

    /** Copy the label through the helper that keys it, which reads the label it writes. */
    public function copyLabel(): static
    {
        $this->label = $this->labelled()['label'];

        return $this;
    }

    /** Stress the label in place. */
    public function stress(): static
    {
        $this->label .= '!';

        return $this;
    }
}
