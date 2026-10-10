<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose spread declares a key that an explicit entry redeclares, before a later resource member.
 *
 * @mixin Image
 */
class RedeclaredKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review' => [
                ...$this->baseReview(),
                'subject' => $this->reviewable->toResource(),
                'again' => new UserResource($this->resource),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function baseReview(): array
    {
        return ['subject' => $this->reviewable->toResource()];
    }
}
