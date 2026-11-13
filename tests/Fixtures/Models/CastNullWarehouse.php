<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\AnonymousCasterCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\LowercaseInboundCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\MixedGetCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\ThunkCastable;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\PlotPoint;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Casts\CoordinateCast;

/**
 * The nullable `warehouses` columns cast to classes, some whose `get()` Laravel calls for a NULL and that never returns
 * null, some that pass a NULL through, and a NOT NULL column cast to a class.
 */
class CastNullWarehouse extends Model
{
    protected $table = 'warehouses';

    /** @var array<string, string> */
    protected $casts = ['secondary_contact_id' => CoordinateCast::class];

    /**
     * Each column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => CoordinateCast::class,
            'phone' => LowercaseInboundCast::class,
            'coordinate_data' => MixedGetCast::class,
            'status' => AnonymousCasterCastable::class,
            'color' => PlotPoint::class,
            'priority' => ThunkCastable::class,
            'primary_contact_id' => CoordinateCast::class.':ignored',
        ];
    }
}
