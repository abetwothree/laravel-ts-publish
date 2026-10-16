<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Its toArray() sends the wrap key only on some requests, so Laravel's response nests the body only on the others.
 */
class SometimesDataKeyPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->when($request->boolean('full'), ['id' => 1]),
            'extra' => true,
        ];
    }
}
