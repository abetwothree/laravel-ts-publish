<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\CreditLine;

/**
 * Typed maps and shapes written whole into a local, given a key, then spread over earlier keys (GH-161 C2, C3, C4).
 *
 * @property-read CreditLine $resource
 */
class TypedSeedSpreadResource extends JsonResource
{
    /**
     * @return array{tones_given_key_under_map_return: array<string, string|int>}
     */
    public function toArray(Request $request): array
    {
        $underReturn = $this->resource->tones();
        $underReturn['by'] = $this->resource->title;

        $shape = $this->resource->extras();
        $shape['x'] = 1;

        $property = $this->resource->labels;
        $property['by'] = $this->resource->title;

        $box = $this->resource;
        $received = $box->tones();
        $received['by'] = $this->resource->title;

        $static = CreditLine::defaults();
        $static['by'] = $this->resource->title;

        $filtered = array_filter($this->resource->labels);
        $filtered['by'] = $this->resource->title;

        $plussed = $this->resource->tones();
        $plussed += ['by' => $this->resource->title];

        $unset = $this->resource->tones();
        unset($unset['tone']);

        $delegated = $this->delegatedTones();
        $delegated['by'] = $this->resource->title;

        /** @var array<string, string> $documented */
        $documented = $this->resource->tones();
        $documented['by'] = $this->resource->title;

        $coalesced = $this->resource->maybeLabels ?? [];
        $coalesced['by'] = $this->resource->title;

        $meta = $this->resource->meta;
        $meta['x'] = 1;

        $collected = collect($this->resource->labels)->all();
        $collected['by'] = $this->resource->title;

        $counted = $this->resource->counted();

        if ($this->resource->pick === 'x') {
            $counted['by'] = $this->resource->title;
        }

        $toned = $this->resource->tones();
        $toned['by'] = $this->resource->title;

        $twoKeys = $this->resource->tones();
        $twoKeys['by'] = $this->resource->title;
        $twoKeys['z'] = 'q';

        $inIf = [];

        if ($this->resource->flag) {
            $inIf = $this->resource->tones();
        }

        $inIf['by'] = $this->resource->title;

        $onePath = $this->resource->tones();

        if ($this->resource->pick === 'x') {
            $onePath['by'] = $this->resource->title;
        }

        $numbered = $this->resource->tones();
        $numbered['n'] = 5;

        $numberedSpread = $this->resource->tones();
        $numberedSpread['n'] = 5;

        $open = [...$this->resource->labels, 'by' => $this->resource->title];

        return [
            'tones_given_key_under_map_return' => ['by' => $this->resource->author, 'z' => 1, ...$underReturn],
            'shape_method_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$shape],
            'map_property_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$property],
            'local_receiver_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$received],
            'static_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$static],
            'filtered_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$filtered],
            'map_plus_assigned_key' => ['by' => $this->resource->author, 'z' => 1, ...$plussed],
            'map_with_key_unset' => ['by' => $this->resource->author, 'z' => 1, ...$unset],
            'own_delegate_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$delegated],
            'docblock_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$documented],
            'coalesced_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$coalesced],
            'helper_built_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$this->titledTones()],
            'shape_property_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$meta],
            'collected_map_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$collected],
            'number_map_given_key_on_one_path' => ['by' => $this->resource->author, 'z' => 1, ...$counted],
            'map_over_earlier_tone' => ['tone' => $this->resource->author, ...$toned],
            'map_given_two_keys' => ['by' => $this->resource->author, 'z' => 1, ...$twoKeys],
            'map_written_in_if_given_key' => ['by' => $this->resource->author, 'z' => 1, ...$inIf],
            'string_map_given_key_on_one_path' => ['by' => $this->resource->author, 'z' => 1, ...$onePath],
            'map_given_number_key_as_value' => $numbered,
            'map_given_number_key_spread' => ['by' => $this->resource->author, ...$numberedSpread],
            'typed_open_local_over_keys' => ['by' => $this->resource->author, 'z' => 1, ...$open],
            'top_tones_given_key' => [...$this->topTonesGivenKey()],
            'top_map_over_earlier_tone' => [...$this->topMapOverTone()],
            'top_shape_method_given_key' => [...$this->topShapeGivenKey()],
            'returned_map_given_number_key' => [...$this->returnedNumberedMap()],
            'top_typed_open_local_over_keys' => [...$this->topOpenLocal()],
        ];
    }

    /**
     * The line's tones, by delegating.
     *
     * @return array<string, string>
     */
    private function delegatedTones(): array
    {
        return $this->resource->tones();
    }

    /**
     * The line's tones with `by` set.
     */
    private function titledTones(): array
    {
        $tones = $this->resource->tones();
        $tones['by'] = $this->resource->title;

        return $tones;
    }

    /**
     * The tones given `by`, spread over earlier keys at the top of the literal.
     */
    private function topTonesGivenKey(): array
    {
        $tones = $this->resource->tones();
        $tones['by'] = $this->resource->title;

        return ['by' => $this->resource->author, 'z' => 1, ...$tones];
    }

    /**
     * The tones given `by`, spread over an earlier `tone` at the top of the literal.
     */
    private function topMapOverTone(): array
    {
        $tones = $this->resource->tones();
        $tones['by'] = $this->resource->title;

        return ['tone' => $this->resource->author, ...$tones];
    }

    /**
     * A closed shape given `x`, spread over an earlier `by` at the top of the literal.
     */
    private function topShapeGivenKey(): array
    {
        $shape = $this->resource->extras();
        $shape['x'] = 1;

        return ['by' => $this->resource->author, ...$shape];
    }

    /**
     * The tones given a number key, returned whole.
     */
    private function returnedNumberedMap(): array
    {
        $tones = $this->resource->tones();
        $tones['n'] = 5;

        return $tones;
    }

    /**
     * A literal of the labels and `by`, spread over earlier keys at the top of the literal.
     */
    private function topOpenLocal(): array
    {
        $open = [...$this->resource->labels, 'by' => $this->resource->title];

        return ['by' => $this->resource->author, 'z' => 1, ...$open];
    }
}
