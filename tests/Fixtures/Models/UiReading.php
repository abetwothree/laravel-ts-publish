<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\PenniesCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeChild;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Badge;

/**
 * A cast and new-style accessors whose values inherit an override, one already nullable; its test creates the
 * `ui_readings` table.
 */
class UiReading extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $appends = ['badge', 'maybe'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['pennies' => PenniesCast::class];
    }

    /**
     * A badge, by a getter closure that declares its class.
     */
    protected function badge(): Attribute
    {
        return Attribute::make(get: fn (): Badge => new Badge);
    }

    /**
     * Nothing yet, by a getter closure that declares a nullable class whose override already holds null.
     */
    protected function maybe(): Attribute
    {
        return Attribute::make(get: fn (): ?MaybeChild => null);
    }
}
