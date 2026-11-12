<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Moves a value only a ternary's `null` arm types through a list spread and an identity map, and sends one beside a
 * typed arm (GH-154).
 *
 * @property-read ReviewBox $resource
 */
final class ReviewTransportedNullsResource extends JsonResource
{
    /**
     * @return array{spread: list<array{tags: list<int>}>, identity: list<array{tags: list<int>}>,
     *     typedArm: list<array{tags: list<int>}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'spread' => [...array_map(fn ($r) => ['tags' => $r->n > 0 ? cache('t') : null], $this->resource->rows)],
            'identity' => array_map(fn ($x) => $x, array_map(fn ($r) => ['tags' => $r->n > 0 ? cache('t') : null], $this->resource->rows)),
            'typedArm' => array_map(fn ($r) => ['tags' => $r->n > 0 ? cache('t') : ($r->n < 0 ? 1 : null)], $this->resource->rows),
        ];
    }
}
