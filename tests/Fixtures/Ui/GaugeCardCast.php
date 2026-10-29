<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Packages\Ui\Gauge;

/**
 * Reads a stored card as a card with a gauge, and a NULL column as null.
 *
 * @implements CastsAttributes<GaugeCard|null, GaugeCard|null>
 */
final class GaugeCardCast implements CastsAttributes
{
    /**
     * The card a stored value holds, or null for a NULL column.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GaugeCard
    {
        return $value === null ? null : new GaugeCard(new Gauge, 1);
    }

    /**
     * The stored value of a card, or null for none.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : '{}';
    }
}
