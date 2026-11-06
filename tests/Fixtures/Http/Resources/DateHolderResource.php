<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Holds a DateTimeImmutable, whose methods PHP types only tentatively, read directly and forwarded by `__call()`. */
final class DateHolderResource extends JsonResource
{
    /** @var DateTimeImmutable */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'forwarded_year' => $this->format('Y'),
            'held_year' => $this->resource->format('Y'),
            'forwarded_timestamp' => $this->getTimestamp(),
        ];
    }
}
