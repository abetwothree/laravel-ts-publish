<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GaugeCardCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Accessors on the nullable `warehouses` columns whose native getter return does not decide the null: one beside a
 * class cast, which toArray() runs instead of the getter, and one declaring `mixed`.
 */
class AccessorCastNullWarehouse extends Model
{
    protected $table = 'warehouses';

    /**
     * A class cast on the key the accessor below also reads.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['phone' => GaugeCardCast::class];
    }

    /** A getter declaring a non-null string, beside a class cast that reads a NULL as null. */
    protected function phone(): Attribute
    {
        return Attribute::make(get: fn (mixed $value): string => 'read');
    }

    /**
     * A getter declaring `mixed`, whose docblock declares a nested null only.
     *
     * @return Attribute<array{a: int|null}, never>
     */
    protected function coordinateData(): Attribute
    {
        return Attribute::make(get: fn (mixed $value): mixed => ['a' => null]);
    }
}
