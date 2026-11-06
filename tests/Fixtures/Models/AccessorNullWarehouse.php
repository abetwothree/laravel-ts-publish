<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Accessors on the nullable `warehouses` columns, each getter declaring its return another way. Laravel runs every
 * getter for a NULL column too.
 */
class AccessorNullWarehouse extends Model
{
    protected $table = 'warehouses';

    /** An old-style getter declaring a non-null string. */
    public function getPriorityAttribute(mixed $value): string
    {
        return (string) $value;
    }

    /** An old-style getter declaring a nullable int. */
    public function getPrimaryContactIdAttribute(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    /**
     * A getter declaring `?array{a: ?int}`.
     *
     * @return Attribute<array{a: int|null}|null, never>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?array => $value === null ? null : ['a' => null]);
    }

    /**
     * A getter declaring a non-null `array{a: ?int}`, whose only null is nested.
     *
     * @return Attribute<array{a: int|null}, never>
     */
    protected function coordinateData(): Attribute
    {
        return Attribute::make(get: fn (?string $value): array => ['a' => null]);
    }

    /**
     * A getter declaring a non-null `array{a: int}`.
     *
     * @return Attribute<array{a: int}, never>
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: fn (?string $value): array => ['a' => 1]);
    }

    /**
     * A getter declaring `?array` natively, whose docblock leaves the null out.
     *
     * @return Attribute<array{a: int|null}, never>
     */
    protected function color(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?array => $value === null ? null : ['a' => null]);
    }

    /**
     * An untyped getter whose docblock declares a nested null only.
     *
     * @return Attribute<array{a: int|null}, never>
     */
    protected function managerId(): Attribute
    {
        return Attribute::make(get: fn ($value) => ['a' => null]);
    }

    /** An untyped getter returning the column. */
    protected function secondaryContactId(): Attribute
    {
        return Attribute::make(get: fn ($value) => $value);
    }
}
