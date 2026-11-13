<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: a readonly value object a resource carries next to its model, so a subject-declared
 * property publishes its own inline shape instead of a same-named model attribute, and one a
 * resource holds in place of a model.
 */
final readonly class PostStats
{
    public function __construct(public float $views, public int $shares) {}

    /** The counts as one line. */
    public function summary(): string
    {
        return "{$this->views} views, {$this->shares} shares";
    }
}
