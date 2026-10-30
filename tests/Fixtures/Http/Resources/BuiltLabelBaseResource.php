<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource base whose summary reads a label a method rewrites from `static::LABEL`. */
class BuiltLabelBaseResource extends JsonResource
{
    public const int LABEL = 5;

    protected $label = 'none';

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['label' => $this->label];
    }

    /** Rewrite the label from the runtime class's constant. */
    public function relabel(): static
    {
        $this->label = static::LABEL;

        return $this;
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return ['label' => $this->label];
    }

    /** The summary of an instance of this class itself, which `new self` builds whatever class runs this. */
    public function freshSummary()
    {
        return new self($this->resource)->summary();
    }
}
