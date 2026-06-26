<?php

declare(strict_types=1);

namespace InertiaUI\Table;

use Illuminate\Contracts\Support\Arrayable;
use RuntimeException;

abstract class Table implements Arrayable
{
    protected ?string $resource = null;

    /**
     * Create a table instance like Inertia UI Table's static constructor.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Chainable sort configuration used by real apps.
     */
    public function defaultSort(?string $defaultSort = null): static
    {
        return $this;
    }

    /**
     * Fail loudly if route type analysis accidentally evaluates table serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        throw new RuntimeException('Inertia UI Table toArray() must not be called during route type analysis.');
    }
}
