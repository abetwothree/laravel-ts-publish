<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StatusLeg;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;

/**
 * A cast whose `get()` declares a list of value objects, each holding enums, over a native `array`.
 *
 * @implements CastsAttributes<list<StatusLeg>, list<StatusLeg>>
 */
final class StatusLegsCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return list<StatusLeg>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        /** @var list<array{code: string, status: int, priority: int|null}> $rows */
        $rows = json_decode((string) $value, true) ?? [];

        return array_map(fn (array $row): StatusLeg => new StatusLeg(
            $row['code'],
            Status::from($row['status']),
            $row['priority'] === null ? null : Priority::from($row['priority']),
        ), $rows);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) json_encode($value);
    }
}
