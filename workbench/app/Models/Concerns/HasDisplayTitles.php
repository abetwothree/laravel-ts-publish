<?php

declare(strict_types=1);

namespace Workbench\App\Models\Concerns;

/**
 * Fixture: display names RosterSlot shares with the models its morphTo can hold, so the resource's own model also
 * declares a method called on the relation.
 */
trait HasDisplayTitles
{
    /** The model's singular label. */
    public static function singularLabel(): string
    {
        return class_basename(static::class);
    }

    /** The record's display title. */
    public function displayTitle(): string
    {
        return (string) $this->getKey();
    }
}
