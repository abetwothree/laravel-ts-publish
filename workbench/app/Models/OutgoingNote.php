<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Set-only mutators whose docblock Get is `never`: it records no getter, not a read type. */
class OutgoingNote extends Model
{
    /** @return Attribute<never, string> */
    protected function subject(): Attribute
    {
        return Attribute::set(fn (?string $value): string => $value ?: 'Untitled');
    }

    /** @return Attribute<never, string> */
    protected function type(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => in_array($value, ['incoming', 'outgoing'], true) ? $value : 'incoming',
        );
    }

    /**
     * Set-only with NO backing column: nothing can be read, so it must stay omitted.
     *
     * @return Attribute<never, string>
     */
    protected function normalizedTag(): Attribute
    {
        return Attribute::set(fn (string $value): string => strtolower($value));
    }
}
