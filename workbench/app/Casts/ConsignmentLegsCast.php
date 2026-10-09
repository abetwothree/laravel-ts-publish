<?php

declare(strict_types=1);

namespace Workbench\App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\ValueObjects\ConsignmentLeg;
use Workbench\App\ValueObjects\ConsignmentStop;

/**
 * Hydrates a list of legs: the native `array` return says nothing of the elements, and the `@return` docblock does.
 * Laravel calls get() for a null column too, so a consignment with no legs sends `[]`.
 *
 * @phpstan-type ConsignmentStopRow = array{note?: string|null, name: string, lat: float, lng: float}
 *
 * @implements CastsAttributes<list<ConsignmentLeg>, list<ConsignmentLeg>>
 */
class ConsignmentLegsCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<ConsignmentLeg>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        /** @var list<array{code: string, sequence: int, stop: ConsignmentStopRow|null}> $rows */
        $rows = json_decode((string) $value, true) ?? [];

        return array_map(fn (array $row): ConsignmentLeg => new ConsignmentLeg(
            $row['code'],
            $row['sequence'],
            $row['stop'] === null ? null : $this->stop($row['stop']),
        ), $rows);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) json_encode($value);
    }

    /**
     * Hydrate a stop with the note set() encoded, so the column reads back as it was written.
     *
     * @param  ConsignmentStopRow  $row
     */
    private function stop(array $row): ConsignmentStop
    {
        $stop = new ConsignmentStop($row['name'], $row['lat'], $row['lng']);
        $stop->note = $row['note'] ?? null;

        return $stop;
    }
}
