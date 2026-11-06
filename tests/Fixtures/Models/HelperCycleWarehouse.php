<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LabelHelperSerializeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * The nullable `warehouses.phone` cast to a caster whose serialize() calls a helper that reads this attribute back.
 */
class HelperCycleWarehouse extends Model
{
    protected $table = 'warehouses';

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => LabelHelperSerializeCast::class,
        ];
    }
}
