<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose helper reads two untyped properties: one copies the other, which wraps what it reads. */
final class PairedWrapPayload
{
    protected $p = 'none';

    protected $q = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['h' => $this->both()];
    }

    /** Both properties, through a helper that declares no return type. */
    public function both()
    {
        return ['p' => $this->p, 'q' => $this->q];
    }

    /** Copy the wrapping property into the other. */
    public function copyQ(): static
    {
        $this->p = $this->q;

        return $this;
    }

    /** Wrap what the helper reads of the property, one level deeper each call. */
    public function wrapQ(): static
    {
        $this->q = ['v' => $this->both()['q']];

        return $this;
    }
}
