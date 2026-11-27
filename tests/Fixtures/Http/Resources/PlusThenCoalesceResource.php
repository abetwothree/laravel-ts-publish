<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose locals take a `+=` over a key a branch set to a `when()` value, then a `??=` of that key:
 * one `+=` always sets the key, and the other adds a helper's array that may lack it.
 *
 * @property-read Post $resource
 */
final class PlusThenCoalesceResource extends JsonResource
{
    /** Each local, under a key of its own. */
    public function toArray(Request $request): array
    {
        return ['laid' => $this->laid(), 'maybe_laid' => $this->maybeLaid()];
    }

    /**
     * A `k` the `+=` lays wherever the branch skipped it, which the `??=` never replaces.
     */
    private function laid(): array
    {
        $d = ['id' => $this->resource->id];

        if ($this->resource->id > 1) {
            $d['k'] = $this->when($this->resource->id > 2, 1);
        }

        $d += ['k' => 2];
        $d['k'] ??= 'z';

        return $d;
    }

    /**
     * A `k` the `+=` may still leave unset, which the `??=` then fills.
     */
    private function maybeLaid(): array
    {
        $d = ['id' => $this->resource->id];

        if ($this->resource->id > 1) {
            $d['k'] = $this->when($this->resource->id > 2, 1);
        }

        $d += $this->optionalK();
        $d['k'] ??= 'z';

        return $d;
    }

    /**
     * A `k` on one path only.
     */
    private function optionalK(): array
    {
        if ($this->resource->id > 3) {
            return ['k' => 1];
        }

        return [];
    }
}
