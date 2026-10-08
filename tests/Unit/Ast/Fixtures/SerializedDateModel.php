<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use DateTime;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Dates a model writes two ways: Model::toArray() runs serializeDate() on a new-style getter's date, whether its
 * closure or only its docblock declares it and whatever else it may return, but hands an old-style getter's DateTime
 * to json_encode() as it is.
 */
final class SerializedDateModel extends Model
{
    protected $table = 'posts';

    /**
     * An old-style getter, whose DateTime json_encode() writes as its date object.
     */
    public function getClockedAtAttribute(): DateTime
    {
        return new DateTime('2026-01-01');
    }

    /**
     * A new-style getter whose closure declares a nullable DateTimeImmutable.
     *
     * @return Attribute<DateTimeImmutable|null, never>
     */
    protected function openedOn(): Attribute
    {
        return Attribute::get(fn (): ?DateTimeImmutable => null);
    }

    /**
     * A new-style getter whose date only its docblock names.
     *
     * @return Attribute<DateTime, never>
     */
    protected function reviewedOn(): Attribute
    {
        return Attribute::get(fn () => new DateTime('2026-01-01'));
    }

    /**
     * A new-style getter whose closure declares a date, a number or null.
     *
     * @return Attribute<DateTimeImmutable|int|null, never>
     */
    protected function pausedAt(): Attribute
    {
        return Attribute::get(fn (): DateTimeImmutable|int|null => null);
    }

    /**
     * A new-style getter whose docblock alone names a nullable date.
     *
     * @return Attribute<?DateTime, never>
     */
    protected function closedOn(): Attribute
    {
        return Attribute::get(fn () => null);
    }

    /**
     * A new-style getter whose docblock alone names a date, a number or null.
     *
     * @return Attribute<DateTime|int|null, never>
     */
    protected function lockedOn(): Attribute
    {
        return Attribute::get(fn () => null);
    }
}
