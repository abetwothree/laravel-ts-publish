<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource whose `toArray()` calls methods on instances it builds as `self` and as `static`, whose bodies
 * reach a helper and a `$with` default BuiltInstanceResource overrides.
 */
class BaseBuiltInstanceResource extends JsonResource
{
    /** @var array<string, mixed> */
    public $with = ['base_meta' => 1];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'built' => new self($this->resource)->label(),
            'late_built' => new static($this->resource)->label(),
            'late_made' => static::make($this->resource)->label(),
            'built_with' => new self($this->resource)->with($request),
            'late_with' => new static($this->resource)->with($request),
            ...self::with($request),
        ];
    }

    /** @return array<string, mixed> */
    public function label(): array
    {
        return [...$this->part()];
    }

    /** @return array<string, mixed> */
    public function part(): array
    {
        return ['base_part' => 1];
    }
}
