<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HelperCycleWarehouse;

/**
 * An untyped label helper that falls back to a warehouse's phone, whose cast's serialize() calls this helper back.
 */
final class WarehouseLabels
{
    /**
     * The value prefixed, or another warehouse's phone for an empty one.
     */
    public static function label($value)
    {
        return is_string($value) && $value !== '' ? 'h'.$value : (new HelperCycleWarehouse)->setRawAttributes(['phone' => 'k'])->phone;
    }
}
