<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose merge calls and spread helpers join a value the engine cannot type with a typed one: a
 * merged closure's literal and built local, a merged literal beside a `null` or `[]` default, a spread helper's
 * literal and built local, and a merged helper whose own join leaves the value untyped.
 *
 * @mixin Post
 */
final class DroppedBranchJoinResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            $this->mergeWhen($this->id > 0, fn () => ['merged' => $this->opaqueRows()], ['merged' => $this->title]),
            $this->mergeWhen($this->id > 0, function () {
                $built = [];
                $built['merged_built'] = $this->opaqueRows();

                return $built;
            }, ['merged_built' => $this->title]),
            $this->mergeWhen($this->id > 0, ['merged_null' => $this->opaqueRows()], ['merged_null' => null]),
            ...$this->spreadRows(),
            ...$this->spreadBuilt(),
            $this->mergeWhen($this->id > 0, $this->twoRows(), ['nested' => $this->title]),
            $this->mergeWhen($this->id > 0, ['merged_rows' => $this->opaqueRows()], ['merged_rows' => []]),
        ];
    }

    /** @return array<string, mixed> */
    public function spreadRows(): array
    {
        if ($this->id > 1) {
            return ['spread' => $this->opaqueRows()];
        }

        return ['spread' => $this->title];
    }

    /** @return array<string, mixed> */
    public function spreadBuilt(): array
    {
        if ($this->id > 1) {
            return ['spread_built' => $this->title];
        }

        $built = [];
        $built['spread_built'] = $this->opaqueRows();

        return $built;
    }

    /** @return array<string, mixed> */
    public function twoRows(): array
    {
        if ($this->id > 5) {
            return ['nested' => $this->opaqueRows()];
        }

        return ['nested' => $this->opaqueRows()];
    }

    /** Deliberately untyped. */
    public function opaqueRows()
    {
        return $this->resource->getAttribute('tags');
    }
}
