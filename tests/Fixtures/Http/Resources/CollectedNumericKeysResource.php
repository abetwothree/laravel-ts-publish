<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource holding collections keyed by numbers, which its filter never enters, alone, under a named key
 * or beside null, a string, an empty or a keyed array, beside an array it lists, the arrays a collection's all() or a
 * spread hands back, which it enters, and the arrays a map() returns.
 *
 * @mixin Depot
 */
class CollectedNumericKeysResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->id === 0) {
            return ['kept' => collect(['6' => $this->name])];
        }

        return [
            'kept' => collect(['6' => $this->name]),
            'kept_int' => collect([42 => $this->name]),
            'listed' => ['6' => $this->name],
            'nested_kept' => ['inner' => collect(['6' => $this->name])],
            'mapped_rows' => collect([$this->name])->map(fn ($name) => ['1' => $name]),
            'spread_rows' => [...collect([[6 => $this->name]])],
            'spread_nested' => ['a' => [...collect([[6 => $this->name]])]],
            'spread_keyed' => ['a' => [...collect([['k' => [6 => $this->name]]])]],
            'codes' => collect([200 => 'OK', 404 => 'Not Found']),
            'codes_or_none' => $this->id > 1 ? collect([200 => 'OK', 404 => 'Not Found']) : null,
            'codes_or_text' => $this->id > 2 ? collect([200 => 'OK', 404 => 'Not Found']) : 'none',
            'codes_or_empty' => $this->id > 3 ? collect([200 => 'OK', 404 => 'Not Found']) : [],
            'codes_or_shape' => $this->id > 4 ? collect([200 => 'OK']) : ['label' => $this->name],
            'rows' => collect([['1' => $this->name]]),
            'rows_listed' => collect([['1' => $this->name]])->all(),
            'order_codes' => $this->whenLoaded(
                'orders',
                fn ($orders) => $orders->map(fn ($order) => collect([5 => $order->currency]))->all(),
            ),
        ];
    }
}
