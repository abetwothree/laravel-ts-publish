<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects\Concerns;

/**
 * Fixture: a trait-supplied `toArray()` whose `self` and `parent` name the class that uses the trait and its parent,
 * and whose `static` names the object's class.
 */
trait ShapesPanel
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'own' => self::own(),
            'root' => parent::root(),
            'late' => static::own(),
        ];
    }
}
