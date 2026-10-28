<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\GaugeCard;
use Illuminate\Database\Eloquent\Model;

/**
 * A nullable column cast to a value object whose own member is nullable, so only the column's null is top-level.
 */
final class GaugeCardHolder extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * The card column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['card' => GaugeCard::class];
    }
}
