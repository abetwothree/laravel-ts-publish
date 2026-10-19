<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use DateTime;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A work shift, whose resource publishes each value as json_encode() writes it.
 */
class Shift extends Model
{
    protected $fillable = ['name'];

    /**
     * When the shift clocked in. Model::toArray() hands an old-style getter's DateTime to json_encode() as it is.
     */
    public function getClockedAtAttribute(): DateTime
    {
        return new DateTime('2026-01-01 08:00:00');
    }

    /**
     * The day the shift starts. Model::toArray() runs serializeDate() on a new-style getter's date.
     *
     * @return Attribute<DateTimeImmutable, never>
     */
    protected function startsOn(): Attribute
    {
        return Attribute::get(fn (): DateTimeImmutable => new DateTimeImmutable('2026-01-01'));
    }
}
