<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose spread declares a key that an explicit entry redeclares, before a later model member.
 *
 * @mixin Image
 */
class RedeclaredKeyModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review' => [
                ...$this->baseReview(),
                'subject' => $this->reviewable,
                'again' => $this->uploader_from_docblock,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function baseReview(): array
    {
        return ['subject' => $this->reviewable];
    }
}
