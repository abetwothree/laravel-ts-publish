<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;

/**
 * A test-only resource whose two return branches each spread a helper that casts a key with an import.
 *
 * @mixin Image
 */
class BranchedCastSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->id) {
            return [
                'id' => $this->id,
                ...$this->review(),
            ];
        }

        return [
            'label' => $this->alt_text,
            ...$this->review(),
        ];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['reviewable' => ['type' => 'UserResource | null', 'import' => '@js/types/user']])]
    private function review(): array
    {
        return ['reviewable' => $this->whenLoaded('reviewable', fn ($subject) => $subject->toResource())];
    }
}
