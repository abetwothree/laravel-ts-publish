<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Lists and keyless values beside maps and named keys, and maps alone that may be empty (GH-161 D1, D2, D4a, D4b, D5).
 *
 * @property-read CreditLine $resource
 */
class ListSpreadsResource extends JsonResource
{
    /**
     * @return array{labels_then_ints_under_return: array<string, string|int>}
     */
    public function toArray(Request $request): array
    {
        $ids = $this->resource->ints;
        $built = [...$this->resource->labels, ...$this->resource->ints];
        $intsThenTitle = [...$this->resource->ints, 'by' => $this->resource->title];
        $labelsThenInts = [...$this->resource->labels, ...$this->resource->ints];

        return [
            'labels_method_then_ints' => [...$this->resource->tones(), ...$this->resource->ints],
            'ints_then_labels' => [...$this->resource->ints, ...$this->resource->labels],
            'labels_then_ints_under_return' => [...$this->resource->labels, ...$this->resource->ints],
            'labels_then_array_values' => [...$this->resource->labels, ...array_values($this->resource->ints)],
            'labels_then_helper_list' => [...$this->resource->labels, ...$this->ids()],
            'labels_then_local_list' => [...$this->resource->labels, ...$ids],
            'labels_then_other_list_method' => [...$this->resource->labels, ...$this->resource->idList()],
            'labels_then_keyless_count' => [...$this->resource->labels, $this->resource->count],
            'labels_then_nullable_list' => [...$this->resource->labels, ...($this->resource->maybeInts ?? [])],
            'labels_then_rows' => [...$this->resource->labels, ...$this->resource->rows],
            'labels_then_static_helper_list' => [...$this->resource->labels, ...self::listed($this->resource->ints)],
            'ints_plus_key' => $this->resource->ints + ['x' => 1],
            'local_built_labels_then_ints' => $built,
            'local_ints_then_key_over_key' => ['by' => $this->resource->author, 'z' => 1, ...$intsThenTitle],
            'keyless_item_beside_key' => ['a' => 1, 'x'],
            'list_plus_keyed' => explode(',', 'a,b') + ['a' => 'x'],
            'keyed_plus_list' => ['a' => 'x'] + $this->resource->ints,
            'coalesced_map_alone' => [...($this->resource->maybeCounts ?? [])],
            'local_map_list_over_key' => ['by' => $this->resource->author, ...$labelsThenInts],
            'map_bool_key_list' => [...$this->resource->labels, 'x' => $this->resource->flag, ...$this->resource->ints],
            'map_list_bool_key' => [...$this->resource->labels, ...$this->resource->ints, 'x' => $this->resource->flag],
            'map_list_nullable_key' => [
                ...$this->resource->labels,
                ...$this->resource->ints,
                'note' => $this->resource->note,
            ],
            'map_nullable_key' => [...$this->resource->labels, 'note' => $this->resource->note],
            'labels_method_plus_key' => $this->resource->tones() + ['by' => 1],
            'empty_default_map_alone' => [...$this->resource->labels],
            'preset_default_map_alone' => [...$this->resource->presets],
            'literal_body_map_alone' => [...$this->resource->labelsLit()],
            'literal_body_map_then_counts' => [...$this->resource->labelsLit(), ...$this->resource->counts],
            'literal_body_map_then_labels' => [...$this->resource->labelsLit(), ...$this->resource->labels],
            'emptiness_guarded_spread_throws' => [...$this->guardedSpreadThrows()],
            'emptiness_guarded_spread_returns' => [...$this->guardedSpreadReturns()],
        ];
    }

    /**
     * The values listed.
     *
     * @param  list<int>  $values
     * @return list<int>
     */
    private static function listed(array $values): array
    {
        return array_values($values);
    }

    /**
     * The ids.
     *
     * @return list<int>
     */
    private function ids(): array
    {
        return $this->resource->ints;
    }

    /**
     * A map past an exact emptiness guard that throws, spread under `row`.
     */
    private function guardedSpreadThrows(): array
    {
        $labels = $this->resource->maybeLabels ?? [];

        if ($labels === []) {
            throw new RuntimeException('labels');
        }

        return ['row' => [...$labels]];
    }

    /**
     * The same past a guard that returns another key.
     */
    private function guardedSpreadReturns(): array
    {
        $labels = $this->resource->maybeLabels ?? [];

        if ($labels === []) {
            return ['n' => 0];
        }

        return ['row' => [...$labels]];
    }
}
