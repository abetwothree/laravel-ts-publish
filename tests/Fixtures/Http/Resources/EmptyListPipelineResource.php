<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A collect() pipeline over a method declared to return the empty list, `list{}`. */
final class EmptyListPipelineResource extends JsonResource
{
    /**
     * The empty list, declared as the empty shape.
     *
     * @return list{}
     */
    public function none(): array
    {
        return [];
    }

    /**
     * Each pipeline step over the empty list.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'values' => collect($this->none())->values(),
            'all' => collect($this->none())->all(),
            'mapped_const' => collect($this->none())->map(fn ($value) => 1)->all(),
            'mapped_same' => collect($this->none())->map(fn ($value) => $value)->all(),
        ];
    }
}
