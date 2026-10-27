<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A helper whose `self::make()` and `self::collection()` build the class that calls it, since JsonResource's builders
 * build `new static`, while its `new self` builds this class.
 */
class ForwardedBuildBaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['base' => 1];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return ['base_summary' => 1];
    }

    /** @return array<string, mixed> */
    public function titled(): array
    {
        return ['title' => $this->title];
    }

    /** @return array<string, mixed> */
    protected function built(): array
    {
        return [
            'made' => self::make($this->resource),
            'listed' => self::collection([$this->resource]),
            'made_summary' => self::make($this->resource)->summary(),
            'made_titled' => self::make($this->resource)->titled(),
            'new_summary' => new self($this->resource)->summary(),
        ];
    }
}
