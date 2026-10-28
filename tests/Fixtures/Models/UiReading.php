<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\PenniesCast;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Badge;

/**
 * A cast and a new-style accessor whose values inherit an override; its test creates the `ui_readings` table.
 */
class UiReading extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $appends = ['badge'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['pennies' => PenniesCast::class];
    }

    protected function badge(): Attribute
    {
        return Attribute::make(get: fn (): Badge => new Badge);
    }
}
