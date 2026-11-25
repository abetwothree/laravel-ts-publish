<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Spreads;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\Spreads\OptionsHolder;

/**
 * Merge closures whose returns include a partly read variable; collect() of a value or [] (GH-121 B7, GH-153 B8).
 *
 * @property-read OptionsHolder $resource
 */
class ClosureCollectReadsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $keyed = $this->resource->flag ? ['x' => 'a'] : null;
        $split = ['pinned' => $this->resource->flag, 'place' => $this->resource->flag ? 'top' : 0];

        return [
            'values_of_keyed_local' => array_values($keyed ?? []),
            'collected_keyed_local' => collect($keyed ?? [])->values()->all(),
            'values_of_nullable_map' => array_values($this->resource->record ?? []),
            'collected_nullable_map' => collect($this->resource->record ?? [])->values()->all(),
            'values_of_nullable_shape' => array_values($this->resource->shape ?? []),
            'collected_nullable_shape' => collect($this->resource->shape ?? [])->values()->all(),
            'values_of_nullable_list' => array_values($this->resource->list ?? []),
            'collected_nullable_list' => collect($this->resource->list ?? [])->values()->all(),
            'collected_split_or_empty' => collect($this->resource->flag ? $split : []),
            $this->mergeWhen($this->resource->id > 0, function () use ($request) {
                $m = ['merged_when_lone_variable' => $this->resource->id];
                $m[$request->string('key')->toString()] = 1;

                return $m;
            }),
            $this->merge(function () use ($request) {
                if ($this->resource->id > 0) {
                    return ['merged_literal_beside_variable' => 1];
                }

                $m = ['merged_variable_beside_literal' => 2];
                $m[$request->string('key')->toString()] = 3;

                return $m;
            }),
            $this->merge(function () use ($request) {
                if ($this->resource->id === 0) {
                    return [];
                }

                $m = ['merged_variable_beside_empty_guard' => true];
                $m[$request->string('key')->toString()] = 1;

                return $m;
            }),
            $this->merge(function () use ($request) {
                $m = ['merged_variable_returned_twice' => 1];

                if ($this->resource->id > 0) {
                    return $m;
                }

                $m[$request->string('key')->toString()] = 2;

                return $m;
            }),
            $this->merge(function () {
                if ($this->resource->id > 0) {
                    return ['merged_literal_beside_appended' => 1];
                }

                $m = ['merged_appended_beside_literal' => 1];
                $m[] = 2;

                return $m;
            }),
            $this->mergeWhen($request->has('key'), function () use ($request) {
                if ($this->resource->id > 0) {
                    return ['merged_when_literal_beside_variable' => 1];
                }

                $m = ['merged_when_variable_beside_literal' => 2];
                $m[$request->string('key')->toString()] = 3;

                return $m;
            }),
            $this->merge(function () use ($request) {
                if ($this->resource->id > 0) {
                    $a = ['merged_if_variable' => 1];
                    $a[$request->string('key')->toString()] = 1;

                    return $a;
                }

                $b = ['merged_else_variable' => 'x'];
                $b[] = 2;

                return $b;
            }),
        ];
    }
}
