<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Workbench\App\Packages\Ui\Gauge;

/**
 * A castable value object whose member holds an overridden class or null, cast by a caster that returns null for null.
 */
final class GaugeCard implements Castable
{
    public function __construct(public ?Gauge $gauge = null, public int $count = 0) {}

    /**
     * The caster that reads and writes the card.
     *
     * @param  array<int, string>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return GaugeCardCast::class;
    }
}
