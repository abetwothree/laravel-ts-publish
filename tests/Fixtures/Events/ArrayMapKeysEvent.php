<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/** A test-only event whose array_map() keys reach json_encode() unchanged, as no resource re-indexes them. */
final class ArrayMapKeysEvent implements ShouldBroadcast
{
    /** @var list<array{message: string}> */
    public array $rows = [['message' => 'a'], ['message' => 'b']];

    /** @var array<string, int> */
    public array $scores = ['x' => 1, 'y' => 2];

    /** The channel it broadcasts on. */
    public function broadcastOn(): Channel
    {
        return new Channel('array-map-keys');
    }

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'typed_arrow' => array_map(fn (array $row): string => $row['message'], $this->rows),
            'string_keyed_literal' => array_map(fn (int $value): int => $value * 2, ['a' => 1, 'b' => 2]),
            'string_keyed_prop' => array_map(fn (int $value): int => $value * 2, $this->scores),
            'gap_keyed_literal' => array_map(fn (string $value): string => strtoupper($value), [1 => 'a', 3 => 'b']),
            'two_arrays_string_keyed' => array_map(fn (int $a, int $b): int => $a + $b, $this->scores, $this->scores),
        ];
    }
}
